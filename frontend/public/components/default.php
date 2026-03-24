<?php
declare(strict_types=1);
/**
 * Component: default
 * Fallback component — renders section title/subtitle, or custom_html if provided.
 *
 * Available variables: $section, $sectionData, $lang, $tenantId, $apiBase,
 *   $_cardStyles (card style variables)
 */

$customHtml = $section['custom_html'] ?? '';
if ($customHtml !== '') {
    echo $customHtml;
}
