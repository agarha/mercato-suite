<?php

declare(strict_types=1);

namespace Mercato\Core\Rest;

final class Permissions
{
    public static function canRead(): bool
    {
        return self::hasTestSecret() || self::isAuthenticated();
    }

    public static function canManage(): bool
    {
        return self::hasTestSecret() || self::isAdmin();
    }

    public static function canWebhook(): bool
    {
        return self::hasTestSecret() || self::hasProviderSignature();
    }

    public static function canPublicRegister(): bool
    {
        return true;
    }

    public static function canPublicHealth(): bool
    {
        return true;
    }

    private static function isAuthenticated(): bool
    {
        return \function_exists('is_user_logged_in') && \is_user_logged_in();
    }

    private static function isAdmin(): bool
    {
        return \function_exists('current_user_can') && \current_user_can('manage_options');
    }

    private static function hasTestSecret(): bool
    {
        $secret = (string) \getenv('MERCATO_TEST_API_SECRET');
        if ($secret === '' || \str_contains($secret, 'replace_me')) {
            return false;
        }

        $header = self::header('x-mercato-test-secret');
        return $header !== '' && \hash_equals($secret, $header);
    }

    private static function hasProviderSignature(): bool
    {
        return self::header('stripe-signature') !== ''
            || self::header('x-twilio-signature') !== ''
            || self::header('x-sendgrid-signature') !== '';
    }

    private static function header(string $name): string
    {
        if (!\function_exists('wp_get_current_user')) {
            return '';
        }

        $serverKey = 'HTTP_' . \strtoupper(\str_replace('-', '_', $name));
        if (isset($_SERVER[$serverKey])) {
            return (string) $_SERVER[$serverKey];
        }

        return '';
    }
}
