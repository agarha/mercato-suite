<?php

declare(strict_types=1);

namespace Mercato\Messaging;

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
    public function createThread(array $data): array
    {
        global $wpdb;

        $tenantId = $this->tenantResolver->currentTenantId();
        $vendorId = (int) ($data['vendor_id'] ?? 0);
        $subject = $this->clean((string) ($data['subject'] ?? 'Message'));
        $body = \trim((string) ($data['body'] ?? ''));

        if ($vendorId < 1 || $body === '') {
            throw new RuntimeException('vendor_id and body are required.');
        }

        $threads = $wpdb->prefix . 'mercato_message_threads';
        $messages = $wpdb->prefix . 'mercato_messages';
        $wpdb->insert($threads, [
            'tenant_id' => $tenantId,
            'vendor_id' => $vendorId,
            'buyer_user_id' => \function_exists('get_current_user_id') ? \get_current_user_id() : 0,
            'subject' => $subject,
        ]);
        $threadId = (int) $wpdb->insert_id;
        $wpdb->insert($messages, [
            'thread_id' => $threadId,
            'sender_user_id' => \function_exists('get_current_user_id') ? \get_current_user_id() : 0,
            'sender_type' => (string) ($data['sender_type'] ?? 'buyer'),
            'body' => $body,
        ]);

        $thread = $this->find($threadId);
        $this->outbox->publish('mercato.messaging.thread.created.v1', $thread, (string) $threadId, $tenantId);
        return $thread;
    }

    /**
     * @param array<string,mixed> $data
     * @return array<string,mixed>
     */
    public function reply(int $threadId, array $data): array
    {
        global $wpdb;

        $body = \trim((string) ($data['body'] ?? ''));
        if ($body === '') {
            throw new RuntimeException('body is required.');
        }

        // Authorisation check: refuse if the thread does not belong to the
        // current tenant. find() throws RuntimeException('Thread not found.')
        // when the tenant scope rejects, intentionally indistinguishable from
        // a missing-thread response so cross-tenant existence cannot be probed.
        $this->find($threadId);

        $messages = $wpdb->prefix . 'mercato_messages';
        $wpdb->insert($messages, [
            'thread_id' => $threadId,
            'sender_user_id' => \function_exists('get_current_user_id') ? \get_current_user_id() : 0,
            'sender_type' => (string) ($data['sender_type'] ?? 'vendor'),
            'body' => $body,
        ]);

        $this->outbox->publish('mercato.messaging.message.created.v1', ['thread_id' => $threadId, 'message_id' => (int) $wpdb->insert_id], (string) $threadId);
        return $this->find($threadId);
    }

    /**
     * Resolve a thread + its messages for the *current* tenant.
     * Throws if the thread does not exist OR belongs to a different tenant —
     * same error in both cases so the API does not leak cross-tenant existence.
     *
     * @return array<string,mixed>
     */
    public function find(int $threadId): array
    {
        global $wpdb;

        $tenantId = $this->tenantResolver->currentTenantId();
        $threads = $wpdb->prefix . 'mercato_message_threads';
        $messages = $wpdb->prefix . 'mercato_messages';

        $thread = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM `{$threads}` WHERE `thread_id` = %d AND `tenant_id` = %d",
            $threadId,
            $tenantId
        ), ARRAY_A);
        if (!$thread) {
            throw new RuntimeException('Thread not found.');
        }

        // Messages inherit tenant via thread_id; the parent check above
        // already enforced tenant ownership of the thread.
        $thread['messages'] = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM `{$messages}` WHERE `thread_id` = %d ORDER BY `created_at` ASC",
            $threadId
        ), ARRAY_A) ?: [];

        return $thread;
    }

    private function clean(string $value): string
    {
        return \function_exists('sanitize_text_field') ? \sanitize_text_field($value) : \trim($value);
    }
}
