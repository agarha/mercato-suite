<?php

declare(strict_types=1);

namespace Mercato\AwsS3;

use Mercato\Core\Events\Outbox;
use Mercato\Core\Tenant\Resolver;
use RuntimeException;

final class Repository
{
    public function __construct(private readonly Resolver $tenantResolver, private readonly Outbox $outbox)
    {
    }

    /**
     * @param array<string,mixed> $data
     * @return array<string,mixed>
     */
    public function createUpload(array $data): array
    {
        global $wpdb;

        $tenantId = $this->tenantResolver->currentTenantId();
        $ownerType = $this->enum((string) ($data['owner_type'] ?? 'generic'), ['product', 'kyc', 'report', 'generic'], 'generic');
        $visibility = $this->enum((string) ($data['visibility'] ?? 'private'), ['public', 'private'], 'private');
        $fileName = \sanitize_file_name((string) ($data['file_name'] ?? ('upload-' . \time())));
        $contentType = (string) ($data['content_type'] ?? 'application/octet-stream');
        $bucket = (string) ($data['bucket'] ?? $this->bucket());
        $objectKey = $this->objectKey($tenantId, $ownerType, $fileName);
        $table = $wpdb->prefix . 'mercato_media';

        $inserted = $wpdb->insert($table, [
            'tenant_id' => $tenantId,
            'owner_type' => $ownerType,
            'owner_id' => isset($data['owner_id']) ? (int) $data['owner_id'] : null,
            'bucket' => $bucket,
            'object_key' => $objectKey,
            'content_type' => $contentType,
            'size_bytes' => isset($data['size_bytes']) ? (int) $data['size_bytes'] : null,
            'checksum_sha256' => isset($data['checksum_sha256']) ? (string) $data['checksum_sha256'] : null,
            'visibility' => $visibility,
            'scan_status' => 'pending',
            'kms_key_id' => $ownerType === 'kyc' ? (string) ($data['kms_key_id'] ?? 'alias/mercato-shared-kyc') : null,
        ]);

        if ($inserted === false) {
            throw new RuntimeException('Unable to create media record: ' . (string) $wpdb->last_error);
        }

        $mediaId = (int) $wpdb->insert_id;
        $uploadUrl = $this->presignedPutUrl($bucket, $objectKey, $contentType);

        $this->outbox->publish('mercato.media.uploaded.v1', [
            'media_id' => $mediaId,
            'bucket' => $bucket,
            'object_key' => $objectKey,
            'status' => 'pending_upload',
        ], (string) $mediaId, $tenantId);

        return [
            'media_id' => $mediaId,
            'bucket' => $bucket,
            'object_key' => $objectKey,
            'upload_method' => 'PUT',
            'upload_url' => $uploadUrl,
            'expires_in' => 900,
            'headers' => [
                'content-type' => $contentType,
            ],
        ];
    }

    /**
     * @return array<string,mixed>
     */
    public function complete(int $mediaId, ?string $scanStatus = null): array
    {
        global $wpdb;

        $tenantId = $this->tenantResolver->currentTenantId();
        $status = $this->enum($scanStatus ?? 'clean', ['pending', 'clean', 'infected', 'failed'], 'clean');
        $table = $wpdb->prefix . 'mercato_media';
        $updated = $wpdb->update($table, ['scan_status' => $status], ['media_id' => $mediaId, 'tenant_id' => $tenantId]);

        if ($updated === false) {
            throw new RuntimeException('Unable to complete media record: ' . (string) $wpdb->last_error);
        }

        $record = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$table} WHERE media_id = %d AND tenant_id = %d", $mediaId, $tenantId), ARRAY_A);
        if (!\is_array($record)) {
            throw new RuntimeException('Media record not found.');
        }

        $this->outbox->publish('mercato.media.scanned.v1', [
            'media_id' => $mediaId,
            'scan_status' => $status,
        ], (string) $mediaId, $tenantId);

        return $record;
    }

    private function objectKey(int $tenantId, string $ownerType, string $fileName): string
    {
        return \sprintf('tenant-%d/%s/%s-%s', $tenantId, $ownerType, \gmdate('YmdHis'), $fileName);
    }

    private function bucket(): string
    {
        return (string) (\getenv('MERCATO_S3_BUCKET') ?: 'mercato-local');
    }

    private function presignedPutUrl(string $bucket, string $objectKey, string $contentType): string
    {
        $endpoint = \rtrim((string) (\getenv('MERCATO_S3_PUBLIC_ENDPOINT') ?: \getenv('MERCATO_S3_ENDPOINT') ?: 'http://localhost:9002'), '/');
        $accessKey = (string) (\getenv('MERCATO_S3_ACCESS_KEY') ?: 'mercato');
        $secretKey = (string) (\getenv('MERCATO_S3_SECRET_KEY') ?: 'mercato-local-secret');
        $region = (string) (\getenv('MERCATO_S3_REGION') ?: 'us-east-1');
        $host = (string) \parse_url($endpoint, PHP_URL_HOST);
        $port = \parse_url($endpoint, PHP_URL_PORT);
        $scheme = (string) (\parse_url($endpoint, PHP_URL_SCHEME) ?: 'http');
        $hostHeader = $host . ($port === null ? '' : ':' . $port);
        $date = \gmdate('Ymd');
        $timestamp = \gmdate('Ymd\THis\Z');
        $scope = "{$date}/{$region}/s3/aws4_request";
        $encodedKey = \str_replace('%2F', '/', \rawurlencode($objectKey));
        $canonicalUri = '/' . \rawurlencode($bucket) . '/' . $encodedKey;
        $query = [
            'X-Amz-Algorithm' => 'AWS4-HMAC-SHA256',
            'X-Amz-Credential' => $accessKey . '/' . $scope,
            'X-Amz-Date' => $timestamp,
            'X-Amz-Expires' => '900',
            'X-Amz-SignedHeaders' => 'content-type;host',
        ];
        \ksort($query);
        $canonicalQuery = \http_build_query($query, '', '&', PHP_QUERY_RFC3986);
        $canonicalHeaders = "content-type:{$contentType}\nhost:{$hostHeader}\n";
        $canonicalRequest = "PUT\n{$canonicalUri}\n{$canonicalQuery}\n{$canonicalHeaders}\ncontent-type;host\nUNSIGNED-PAYLOAD";
        $stringToSign = "AWS4-HMAC-SHA256\n{$timestamp}\n{$scope}\n" . \hash('sha256', $canonicalRequest);
        $signature = \hash_hmac('sha256', $stringToSign, $this->signingKey($secretKey, $date, $region));

        return "{$scheme}://{$hostHeader}{$canonicalUri}?{$canonicalQuery}&X-Amz-Signature={$signature}";
    }

    /**
     * @return string binary signing key
     */
    private function signingKey(string $secretKey, string $date, string $region): string
    {
        $dateKey = \hash_hmac('sha256', $date, 'AWS4' . $secretKey, true);
        $dateRegionKey = \hash_hmac('sha256', $region, $dateKey, true);
        $dateRegionServiceKey = \hash_hmac('sha256', 's3', $dateRegionKey, true);
        return \hash_hmac('sha256', 'aws4_request', $dateRegionServiceKey, true);
    }

    /**
     * @param list<string> $allowed
     */
    private function enum(string $value, array $allowed, string $default): string
    {
        return \in_array($value, $allowed, true) ? $value : $default;
    }
}
