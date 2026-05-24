<?php

declare(strict_types=1);

namespace Mercato\Reviews;

use InvalidArgumentException;
use Mercato\Core\Events\Outbox;
use Mercato\Core\Tenant\Resolver;

final class Repository
{
    public function __construct(
        private readonly Resolver $tenants,
        private readonly Outbox $outbox,
    ) {
    }

    /**
     * @return array{vendor_id:int,average:float,count:int,reviews:list<array<string,mixed>>}
     */
    public function forVendor(int $vendorId, int $limit = 20): array
    {
        global $wpdb;
        $tenantId = $this->tenants->currentTenantId();
        $table = $wpdb->prefix . 'mercato_reviews';

        $reviews = $wpdb->get_results($wpdb->prepare(
            "SELECT review_id, rating, title, body, buyer_user_id, created_at
             FROM `{$table}`
             WHERE tenant_id = %d AND vendor_id = %d AND status = 'published'
             ORDER BY created_at DESC LIMIT %d",
            $tenantId,
            $vendorId,
            $limit
        ), ARRAY_A) ?: [];

        $summary = $wpdb->get_row($wpdb->prepare(
            "SELECT COALESCE(AVG(rating), 0) AS avg_rating, COUNT(*) AS review_count
             FROM `{$table}`
             WHERE tenant_id = %d AND vendor_id = %d AND status = 'published'",
            $tenantId,
            $vendorId
        ), ARRAY_A) ?: ['avg_rating' => 0, 'review_count' => 0];

        return [
            'vendor_id' => $vendorId,
            'average' => \round((float) $summary['avg_rating'], 2),
            'count' => (int) $summary['review_count'],
            'reviews' => $reviews,
        ];
    }

    /**
     * @param array<string,mixed> $body
     * @return array<string,mixed>
     */
    public function create(int $vendorId, int $buyerUserId, array $body): array
    {
        global $wpdb;
        $tenantId = $this->tenants->currentTenantId();

        $rating = (int) ($body['rating'] ?? 0);
        if ($rating < 1 || $rating > 5) {
            throw new InvalidArgumentException('rating must be between 1 and 5');
        }
        if ($vendorId <= 0) {
            throw new InvalidArgumentException('vendor_id required');
        }

        $title = \trim((string) ($body['title'] ?? ''));
        $text = \trim((string) ($body['body'] ?? ''));
        if (\function_exists('sanitize_text_field')) {
            $title = \sanitize_text_field($title);
        }
        $title = \substr($title, 0, 160);
        $text = \substr($text, 0, 4000);

        $jobId = isset($body['job_id']) ? (int) $body['job_id'] : null;
        $table = $wpdb->prefix . 'mercato_reviews';

        $wpdb->insert($table, [
            'tenant_id' => $tenantId,
            'vendor_id' => $vendorId,
            'buyer_user_id' => $buyerUserId,
            'job_id' => $jobId,
            'rating' => $rating,
            'title' => $title !== '' ? $title : null,
            'body' => $text !== '' ? $text : null,
            'status' => 'published',
        ], ['%d', '%d', '%d', '%d', '%d', '%s', '%s', '%s']);

        $id = (int) $wpdb->insert_id;
        $payload = [
            'review_id' => $id,
            'tenant_id' => $tenantId,
            'vendor_id' => $vendorId,
            'buyer_user_id' => $buyerUserId,
            'rating' => $rating,
            'title' => $title,
        ];

        $this->outbox->publish('mercato.review.created.v1', $payload, $tenantId);

        return $payload;
    }
}
