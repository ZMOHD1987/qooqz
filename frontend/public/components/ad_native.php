<?php
declare(strict_types=1);
/**
 * Component: ad_native
 * Renders custom HTML content from the section's custom_html field.
 *
 * Available variables: $section, $sectionData, $lang, $tenantId, $apiBase,
 *   $_cardStyles (card style variables)
 */

$customHtml = $section['custom_html'] ?? '';
if ($customHtml !== '') {
    echo $customHtml;
}
