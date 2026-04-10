<?php
/**
 * frontend/partials/store_sections/attributes.php
 * Merchant Attributes Section — Display entity-specific attributes
 *
 * Expected variables:
 *   $entity, $lang, $pdo, $entityId (or $entity['id'])
 *   $sectionSettings — Section JSON settings
 */

$_eid = (int)($entity['id'] ?? $entityId ?? 0);

// Attributes are already loaded in $entity['attributes'] by the main query
$attrs = $entity['attributes'] ?? [];

// If not loaded, fetch from DB
if (empty($attrs) && $pdo && $_eid) {
    try {
        $atStmt = $pdo->prepare(
            "SELECT COALESCE(eat.name, ea.slug) AS attribute_name,
                    eat.description AS attribute_description,
                    ea.attribute_type,
                    eav.value
               FROM entities_attribute_values eav
          LEFT JOIN entities_attributes ea ON ea.id = eav.attribute_id
          LEFT JOIN entities_attribute_translations eat ON eat.attribute_id = ea.id AND eat.language_code = ?
              WHERE eav.entity_id = ? AND eav.value IS NOT NULL AND eav.value != ''
              ORDER BY ea.sort_order ASC, ea.id ASC
              LIMIT 50"
        );
        $atStmt->execute([$lang, $_eid]);
        $attrs = $atStmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Throwable $_) {
        $attrs = [];
    }
}

if (empty($attrs)):
?>
<!-- No attributes configured for this entity -->
<?php else: ?>

<div class="pub-entity-section-content pub-attributes" id="sectionAttributes">
    <div class="pub-attributes-grid">
        <?php foreach ($attrs as $attr):
            $attrName  = trim($attr['attribute_name'] ?? '');
            $attrValue = trim($attr['value'] ?? '');
            $attrType  = $attr['attribute_type'] ?? 'text';
            if ($attrName === '' || $attrValue === '') continue;

            // Format boolean values
            if ($attrType === 'boolean') {
                $attrValue = ($attrValue === '1' || strtolower($attrValue) === 'true')
                    ? '✅ ' . t('common.yes', 'Yes')
                    : '❌ ' . t('common.no', 'No');
            }
        ?>
        <div class="pub-attribute-item">
            <span class="pub-attribute-name"><?= e($attrName) ?></span>
            <span class="pub-attribute-value"><?= e($attrValue) ?></span>
        </div>
        <?php endforeach; ?>
    </div>
</div>

<style>
.pub-attributes-grid { display:grid; grid-template-columns:repeat(auto-fill, minmax(280px, 1fr)); gap:12px; }
.pub-attribute-item { display:flex; justify-content:space-between; align-items:center; gap:12px; padding:12px 16px; background:var(--pub-surface,#fff); border:1px solid var(--pub-border,#e5e7eb); border-radius:var(--pub-radius,8px); }
.pub-attribute-name { font-weight:600; font-size:0.9rem; color:var(--pub-text,#1f2937); flex-shrink:0; }
.pub-attribute-value { font-size:0.9rem; color:var(--pub-muted,#4b5563); text-align:end; word-break:break-word; }
@media (max-width:600px) {
    .pub-attributes-grid { grid-template-columns:1fr; }
}
</style>

<?php endif; ?>
