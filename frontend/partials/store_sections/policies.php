<?php
/**
 * frontend/partials/store_sections/policies.php
 * Store Policies Section — Refund, Privacy, Shipping, Terms
 *
 * Expected variables:
 *   $entity, $lang, $pdo, $entityId (or $entity['id'])
 *   $sectionSettings — Section JSON settings
 */

// Which policy types to show (configurable via section settings)
$policyTypes = $sectionSettings['types'] ?? ['refund', 'privacy', 'shipping', 'terms'];

// Default policy titles per type + language
$defaultPolicyTitles = [
    'refund'   => t('entity.policy_refund',   'Return & Refund Policy'),
    'privacy'  => t('entity.policy_privacy',  'Privacy Policy'),
    'shipping' => t('entity.policy_shipping', 'Shipping & Delivery Policy'),
    'terms'    => t('entity.policy_terms',    'Terms & Conditions'),
];

// Default policy icons
$policyIcons = [
    'refund'   => '↩️',
    'privacy'  => '🔒',
    'shipping' => '🚚',
    'terms'    => '📋',
];

// Fetch policies from DB
$policies = [];
$_eid = (int)($entity['id'] ?? $entityId ?? 0);

if ($pdo && $_eid) {
    try {
        $placeholders = implode(',', array_fill(0, count($policyTypes), '?'));
        $params = array_merge([$_eid, $lang], $policyTypes);
        $polStmt = $pdo->prepare(
            "SELECT type, title, content
               FROM entity_policies
              WHERE entity_id = ? AND language_code = ? AND is_active = 1
                AND type IN ($placeholders)
              ORDER BY sort_order ASC, type ASC"
        );
        $polStmt->execute($params);
        $rows = $polStmt->fetchAll(PDO::FETCH_ASSOC);
        foreach ($rows as $row) {
            $policies[$row['type']] = $row;
        }
    } catch (Throwable $_) {
        // Table may not exist yet — no policies to show
        $policies = [];
    }
}

// Nothing to show if no policies configured
if (empty($policies)):
?>
<!-- No policies configured for this entity -->
<?php else: ?>

<div class="pub-entity-section-content pub-policies" id="sectionPolicies">
    <div class="pub-policies-accordion">
        <?php foreach ($policyTypes as $pType):
            if (!isset($policies[$pType])) continue;
            $pol = $policies[$pType];
            $polTitle   = trim($pol['title'] ?? '') ?: ($defaultPolicyTitles[$pType] ?? ucfirst($pType));
            $polContent = trim($pol['content'] ?? '');
            $polIcon    = $policyIcons[$pType] ?? '📄';
            if ($polContent === '') continue;
        ?>
        <details class="pub-policy-item" id="policy-<?= e($pType) ?>">
            <summary class="pub-policy-header">
                <span class="pub-policy-icon"><?= $polIcon ?></span>
                <span class="pub-policy-title"><?= e($polTitle) ?></span>
                <span class="pub-policy-chevron">▾</span>
            </summary>
            <div class="pub-policy-body">
                <?= nl2br(e($polContent)) ?>
            </div>
        </details>
        <?php endforeach; ?>
    </div>
</div>

<style>
.pub-policies-accordion { display:flex; flex-direction:column; gap:8px; }
.pub-policy-item { background:var(--pub-surface,#fff); border:1px solid var(--pub-border,#e5e7eb); border-radius:var(--pub-radius,8px); overflow:hidden; }
.pub-policy-header { display:flex; align-items:center; gap:10px; padding:14px 16px; cursor:pointer; font-weight:600; font-size:0.95rem; color:var(--pub-text,#1f2937); list-style:none; user-select:none; }
.pub-policy-header::-webkit-details-marker { display:none; }
.pub-policy-icon { font-size:1.1rem; flex-shrink:0; }
.pub-policy-title { flex:1; }
.pub-policy-chevron { font-size:0.8rem; color:var(--pub-muted,#6b7280); transition:transform 0.2s; }
details[open] .pub-policy-chevron { transform:rotate(180deg); }
.pub-policy-body { padding:0 16px 16px; font-size:0.9rem; line-height:1.7; color:var(--pub-muted,#4b5563); border-top:1px solid var(--pub-border,#e5e7eb); padding-top:12px; margin-top:0; }
</style>

<?php endif; ?>
