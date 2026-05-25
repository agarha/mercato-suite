<?php

declare(strict_types=1);

namespace Mercato\Vendors;

use Mercato\Core\Container;
use Mercato\Core\Tenant\Resolver;
use RuntimeException;

/**
 * Self-signup orchestrator. Translates the multi-step storefront form into:
 *   1. WordPress user (or reuses the logged-in one)
 *   2. Vendor row with profile fields
 *   3. Primary vendor location with lat/lng + service radius
 *   4. Zero+ service-area declarations
 *   5. Zero+ services (product + offering pairs) tagged with categories
 *
 * The whole flow is "best-effort transactional": if any step after the
 * vendor insert fails we still leave a draft vendor in place so the admin
 * can complete onboarding manually. Step 1 (user creation) is gated by
 * tenant policy — when a logged-in WP user submits the form we skip
 * creating a new one.
 *
 * Lives in mercato-vendors because the vendor record is the spine of the
 * relationship; the orchestrator just hands off to peer repositories
 * (ProductsRepository for services) via the DI container.
 */
final class Signup
{
    public function __construct(
        private readonly Resolver $tenantResolver,
        private readonly Repository $vendors,
        private readonly Container $container,
    ) {
    }

    /**
     * @param array<string,mixed> $payload
     * @return array<string,mixed>
     */
    public function run(array $payload, int $loggedInUserId): array
    {
        $tenantId = $this->tenantResolver->currentTenantId();

        $userId = $loggedInUserId > 0
            ? $loggedInUserId
            : $this->ensureUser((array) ($payload['account'] ?? []));

        $businessPayload = (array) ($payload['business'] ?? []);
        $vendor = $this->vendors->register($businessPayload, $userId);
        $vendorId = (int) $vendor['vendor_id'];

        $created = [
            'tenant_id' => $tenantId,
            'user_id' => $userId,
            'vendor' => $vendor,
            'location' => null,
            'service_areas' => [],
            'services' => [],
            'warnings' => [],
            'sparks_granted' => 0,
        ];

        // Issue an email verification token and send the confirm link via
        // wp_mail. The verify URL lives on the storefront. Best-effort:
        // any failure (mail not configured) is a warning, not a blocker.
        try {
            $token = $this->vendors->issueVerificationToken($vendorId);
            $created['verification_token_sent'] = true;
            $this->sendVerificationEmail($tenantId, $vendor, $token);
        } catch (\Throwable $e) {
            $created['warnings'][] = 'verification_email: ' . $e->getMessage();
            $created['verification_token_sent'] = false;
        }

        // Grant the signup bonus from the rewards module if it's enabled.
        // Hard-tolerant: any failure (module unloaded, table missing) is a
        // warning, never blocks vendor creation.
        try {
            $rewardsRepoClass = '\\Mercato\\Rewards\\Repository';
            $rewardsLedgerClass = '\\Mercato\\Rewards\\Ledger';
            if ($this->container->has($rewardsRepoClass) && $this->container->has($rewardsLedgerClass)) {
                $cfg = $this->container->get($rewardsRepoClass)->config();
                if (!empty($cfg['enabled']) && (int) $cfg['signup_bonus_sparks'] > 0) {
                    $granted = $this->container->get($rewardsLedgerClass)->earn(
                        $userId,
                        'sparks',
                        (int) $cfg['signup_bonus_sparks'],
                        'signup_bonus',
                        'vendor',
                        $vendorId
                    );
                    $created['sparks_granted'] = (int) $cfg['signup_bonus_sparks'];
                    $created['sparks_balance'] = $granted;
                }
            }
        } catch (\Throwable $e) {
            $created['warnings'][] = 'sparks_grant: ' . $e->getMessage();
        }

        // Primary location: required for geo-discovery to find this provider.
        $location = (array) ($payload['location'] ?? []);
        if (!empty($location['latitude']) && !empty($location['longitude'])) {
            try {
                $created['location'] = $this->vendors->createLocation($vendorId, [
                    'label' => $location['label'] ?? 'Main location',
                    'address_line1' => $location['address_line1'] ?? null,
                    'city' => $location['city'] ?? null,
                    'region' => $location['region'] ?? null,
                    'postal_code' => $location['postal_code'] ?? null,
                    'country' => $location['country'] ?? null,
                    'latitude' => (float) $location['latitude'],
                    'longitude' => (float) $location['longitude'],
                    'service_radius_km' => isset($location['service_radius_km']) ? (float) $location['service_radius_km'] : 25.0,
                    'is_primary' => true,
                ]);
            } catch (\Throwable $e) {
                $created['warnings'][] = 'location: ' . $e->getMessage();
            }
        }

        // Service areas: TaskRabbit-style "Work Area Map" entries. May be
        // none if the provider works wherever the radius reaches.
        foreach ((array) ($payload['service_areas'] ?? []) as $area) {
            if (!\is_array($area)) {
                continue;
            }
            try {
                $created['service_areas'][] = $this->vendors->createServiceArea($vendorId, $area);
            } catch (\Throwable $e) {
                $created['warnings'][] = 'service_area: ' . $e->getMessage();
            }
        }

        // Services: one product + offering per item. Categories are attached
        // by category_ids on the product row.
        $productsRepoClass = '\Mercato\Products\Repository';
        if (\class_exists($productsRepoClass)) {
            /** @var \Mercato\Products\Repository $productsRepo */
            $productsRepo = $this->resolveProductsRepo();
            foreach ((array) ($payload['services'] ?? []) as $service) {
                if (!\is_array($service)) {
                    continue;
                }
                try {
                    $serviceRow = $productsRepo->create([
                        'vendor_id' => $vendorId,
                        'title' => (string) ($service['title'] ?? ''),
                        'description' => $service['description'] ?? null,
                        'price_minor' => (int) ($service['price_minor'] ?? 0),
                        'stock_quantity' => isset($service['stock_quantity']) ? (int) $service['stock_quantity'] : 99,
                        // Self-signup creates draft products until the vendor
                        // is approved. Admin flips status to active.
                        'status' => 'draft',
                        'category_ids' => isset($service['category_ids']) ? (array) $service['category_ids'] : [],
                        'duration_minutes' => isset($service['duration_minutes']) ? (int) $service['duration_minutes'] : null,
                    ]);
                    $created['services'][] = $serviceRow;
                } catch (\Throwable $e) {
                    $created['warnings'][] = 'service: ' . $e->getMessage();
                }
            }
        } else {
            $created['warnings'][] = 'services: mercato-products module unavailable';
        }

        return $created;
    }

    /**
     * @param array<string,mixed> $account
     */
    private function ensureUser(array $account): int
    {
        if (!\function_exists('wp_create_user')) {
            throw new RuntimeException('WordPress user functions unavailable.');
        }

        $email = \filter_var((string) ($account['email'] ?? ''), FILTER_VALIDATE_EMAIL);
        if (!$email) {
            throw new RuntimeException('A valid email address is required.');
        }

        if (\function_exists('email_exists')) {
            $existing = \email_exists($email);
            if ($existing) {
                return (int) $existing;
            }
        }

        $login = isset($account['username']) && $account['username'] !== ''
            ? (string) $account['username']
            : \strtolower(\preg_replace('/[^a-z0-9]+/i', '', \explode('@', $email)[0]));
        $login = \substr($login, 0, 60) ?: 'pro_' . \time();

        // Make login unique to avoid clobbering an existing account.
        $base = $login;
        $i = 1;
        while (\function_exists('username_exists') && \username_exists($login)) {
            $login = $base . $i++;
            if ($i > 99) {
                $login = $base . \time();
                break;
            }
        }

        $password = isset($account['password']) && \strlen((string) $account['password']) >= 8
            ? (string) $account['password']
            : (\function_exists('wp_generate_password') ? \wp_generate_password(20) : \bin2hex(\random_bytes(10)));

        $userId = \wp_create_user($login, $password, $email);
        if ($userId instanceof \WP_Error) {
            throw new RuntimeException('Unable to create account: ' . $userId->get_error_message());
        }

        // Tag the user with first/last name + subscriber role until the
        // admin approves the vendor. Full vendor caps are granted on
        // approval, not on application.
        if (\function_exists('wp_update_user')) {
            \wp_update_user([
                'ID' => (int) $userId,
                'first_name' => (string) ($account['first_name'] ?? ''),
                'last_name' => (string) ($account['last_name'] ?? ''),
                'role' => 'subscriber',
            ]);
        }

        return (int) $userId;
    }

    /**
     * Send the email-verification link to the applicant. Uses the tenant's
     * storefront /verify-email page; the email body is plain-text so it
     * survives any mail provider (Mailpit, Postmark, SendGrid) without
     * extra setup.
     *
     * @param array<string,mixed> $vendor
     */
    private function sendVerificationEmail(int $tenantId, array $vendor, string $token): void
    {
        if (!\function_exists('wp_mail') || !\function_exists('home_url')) {
            return;
        }

        $email = (string) ($vendor['contact_email'] ?? '');
        if ($email === '') {
            // Fall back to the WP user's email if no contact_email was supplied
            $userId = (int) ($vendor['owner_user_id'] ?? 0);
            if ($userId > 0 && \function_exists('get_userdata')) {
                $u = \get_userdata($userId);
                if ($u && !empty($u->user_email)) {
                    $email = (string) $u->user_email;
                }
            }
        }
        if ($email === '') {
            return;
        }

        // Resolve tenant slug for the verify URL.
        global $wpdb;
        $tenants = $wpdb->prefix . 'mercato_tenants';
        $slug = (string) $wpdb->get_var($wpdb->prepare("SELECT tenant_slug FROM `{$tenants}` WHERE tenant_id = %d", $tenantId)) ?: 'gigsii';

        $verifyUrl = \home_url('/t/' . $slug . '/verify-email?token=' . \urlencode($token));
        $business = (string) ($vendor['business_name'] ?? 'your business');
        $brand = $slug === 'gigsii' ? 'Gigsii' : 'Mercato';
        $subject = '[' . $brand . '] Confirm your email for ' . $business;
        $body = "Hi,\n\n"
              . "Thanks for applying to join " . $brand . " as a pro. "
              . "Click the link below to confirm your email and unlock the rest of your provider dashboard:\n\n"
              . $verifyUrl . "\n\n"
              . "If you didn't request this, you can ignore the message.\n\n"
              . "— The " . $brand . " team";

        \wp_mail($email, $subject, $body);
    }

    /**
     * Late-bind the products repository so the Vendors module doesn't have
     * a hard load-order dependency on mercato-products. The Container is
     * injected; we look up the bound concrete class by FQCN string so the
     * use-statement isn't required.
     */
    private function resolveProductsRepo(): object
    {
        $cls = '\\Mercato\\Products\\Repository';
        if (!$this->container->has($cls)) {
            throw new RuntimeException('mercato-products module is not loaded.');
        }
        return $this->container->get($cls);
    }
}
