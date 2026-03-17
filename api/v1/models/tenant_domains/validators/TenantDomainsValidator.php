<?php
declare(strict_types=1);

/**
 * TenantDomainsValidator
 *
 * Input validation for tenant domain records.
 */
final class TenantDomainsValidator
{
    private const ALLOWED_TYPES        = ['primary', 'custom', 'subdomain', 'alias'];
    private const ALLOWED_SSL_STATUSES = ['none', 'pending', 'active', 'failed'];
    private const MAX_DOMAIN_LENGTH    = 255;

    /**
     * Validate a create payload.
     *
     * @param  array $data
     * @return array<string, string>  Field-level errors; empty = valid
     */
    public static function validateCreate(array $data): array
    {
        $errors = [];

        // tenant_id
        if (empty($data['tenant_id']) || !is_numeric($data['tenant_id']) || (int)$data['tenant_id'] < 1) {
            $errors['tenant_id'] = 'tenant_id is required and must be a positive integer';
        }

        // domain
        $domainErrors = self::validateDomainString($data['domain'] ?? '');
        if ($domainErrors) {
            $errors['domain'] = $domainErrors;
        }

        // type (optional – defaults to 'custom')
        if (isset($data['type']) && !in_array($data['type'], self::ALLOWED_TYPES, true)) {
            $errors['type'] = 'type must be one of: ' . implode(', ', self::ALLOWED_TYPES);
        }

        // ssl_status (optional – defaults to 'none')
        if (isset($data['ssl_status']) && !in_array($data['ssl_status'], self::ALLOWED_SSL_STATUSES, true)) {
            $errors['ssl_status'] = 'ssl_status must be one of: ' . implode(', ', self::ALLOWED_SSL_STATUSES);
        }

        return $errors;
    }

    /**
     * Validate an update payload (all fields optional except domain when provided).
     *
     * @param  array $data
     * @return array<string, string>
     */
    public static function validateUpdate(array $data): array
    {
        $errors = [];

        if (isset($data['domain'])) {
            $domainErrors = self::validateDomainString($data['domain']);
            if ($domainErrors) {
                $errors['domain'] = $domainErrors;
            }
        }

        if (isset($data['type']) && !in_array($data['type'], self::ALLOWED_TYPES, true)) {
            $errors['type'] = 'type must be one of: ' . implode(', ', self::ALLOWED_TYPES);
        }

        if (isset($data['ssl_status']) && !in_array($data['ssl_status'], self::ALLOWED_SSL_STATUSES, true)) {
            $errors['ssl_status'] = 'ssl_status must be one of: ' . implode(', ', self::ALLOWED_SSL_STATUSES);
        }

        return $errors;
    }

    /**
     * Validate the domain string itself.
     *
     * @return string  Error message, or empty string when valid
     */
    public static function validateDomainString(string $domain): string
    {
        $domain = trim($domain);

        if ($domain === '') {
            return 'domain is required';
        }
        if (mb_strlen($domain) > self::MAX_DOMAIN_LENGTH) {
            return 'domain must not exceed ' . self::MAX_DOMAIN_LENGTH . ' characters';
        }

        // Strip protocol if accidentally included
        $stripped = preg_replace('#^https?://#i', '', $domain);
        $stripped = rtrim($stripped, '/');

        // Must look like a valid hostname (RFC 1123)
        // Accepts: example.com, sub.example.co.uk, *.example.com (wildcard)
        $pattern = '/^(\*\.)?([a-z0-9]([a-z0-9\-]{0,61}[a-z0-9])?\.)+[a-z]{2,}$/i';
        if (!preg_match($pattern, $stripped)) {
            return 'domain must be a valid hostname (e.g. example.com, sub.example.co.uk)';
        }

        return '';
    }
}
