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
        $bucket = (string) ($data['bucket'] ?? ($ownerType === 'kyc' ? 'mercato-kyc' : 'mercato-public'));
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
        $uploadUrl = \add_query_arg([
            'rest_route' => '/mercato/v1/media/' . $mediaId . '/complete',
            'token' => \wp_create_nonce('mercato_media_' . $mediaId),
        ], \home_url('/'));

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
                'x-amz-server-side-encryption' => 'aws:kms',
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

    /**
     * @param list<string> $allowed
     */
    private function enum(string $value, array $allowed, string $default): string
    {
        return \in_array($value, $allowed, true) ? $value : $default;
    }
}
