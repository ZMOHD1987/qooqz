<?php
// api/helpers/safe_helpers.php
// Contains safe_htmlspecialchars helper to avoid type errors when non-strings are passed.
declare(strict_types=1);

if (!function_exists('safe_htmlspecialchars')) {
    /**
     * Convert input to string safely and call htmlspecialchars with UTF-8.
     * - Scalars cast to string
     * - null -> ''
     * - objects with __toString cast to string
     * - arrays/other objects -> print_r(...) to readable string
     */
    function safe_htmlspecialchars($value, int $flags = ENT_QUOTES, string $encoding = 'UTF-8', bool $double_encode = true): string {
        if (is_null($value)) return '';
        if (is_scalar($value)) return htmlspecialchars((string)$value, $flags, $encoding, $double_encode);
        if (is_object($value) && method_exists($value, '__toString')) {
            return htmlspecialchars((string)$value, $flags, $encoding, $double_encode);
        }
        // Arrays or other objects: safe printable representation
        return htmlspecialchars(print_r($value, true), $flags, $encoding, $double_encode);
    }
}