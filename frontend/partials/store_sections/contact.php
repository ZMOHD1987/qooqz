<?php
/**
 * frontend/partials/store_sections/contact.php
 * Store Contact Section — Phone, email, website, social links, share button
 *
 * Expected variables:
 *   $entity              — Entity data array
 *   $entityShowContactInfo — Whether to show contact info (bool)
 *   $sectionSettings     — Section JSON settings (optional)
 */

$showPhone   = ($sectionSettings['show_phone']   ?? true);
$showEmail   = ($sectionSettings['show_email']   ?? true);
$showWebsite = ($sectionSettings['show_website'] ?? true);
$showShare   = ($sectionSettings['show_share']   ?? true);
$showSocial  = ($sectionSettings['show_social']  ?? true);
?>

<div class="pub-container">
    <!-- Contact info -->
    <?php if ($entityShowContactInfo): ?>
    <div class="pub-entity-contacts">
        <?php if ($showPhone && !empty($entity['phone'])): ?>
            <a href="tel:<?= e($entity['phone']) ?>" class="pub-contact-item">
                📞 <?= e($entity['phone']) ?>
            </a>
        <?php endif; ?>
        <?php if ($showEmail && !empty($entity['email'])): ?>
            <a href="mailto:<?= e($entity['email']) ?>" class="pub-contact-item">
                📧 <?= e($entity['email']) ?>
            </a>
        <?php endif; ?>
        <?php if ($showWebsite && !empty($entity['website'])): ?>
            <a href="<?= e($entity['website']) ?>" target="_blank" rel="noopener" class="pub-contact-item">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" style="width:1.2em;height:1.2em;vertical-align:middle;display:inline-block;"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 12a9 9 0 01-9 9m9-9a9 9 0 00-9-9m9 9H3m9 4.01V8m0 8v-4m0 0V8c-1.11 0-2.08.402-2.599 1M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2" /></svg> <?= e(parse_url($entity['website'], PHP_URL_HOST) ?: $entity['website']) ?>
            </a>
        <?php endif; ?>
    </div>
    <?php endif; ?>

    <!-- Social links -->
    <?php if ($showSocial): ?>
    <div class="pub-entity-social">
        <?php
        $waNum = ltrim($entity['whatsapp'] ?? '', '+');
        
        $socialIcons = [
            'whatsapp'  => '<svg viewBox="0 0 24 24" width="16" height="16" fill="currentColor"><path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.47-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51a12.8 12.8 0 0 0-.57-.01c-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 0 1-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 0 1-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 0 1 2.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0 0 12.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 0 0 5.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 0 0-3.48-8.413Z"/></svg>',
            'facebook'  => '<svg viewBox="0 0 24 24" width="16" height="16" fill="currentColor"><path d="M24 12.073c0-6.627-5.373-12-12-12s-12 5.373-12 12c0 5.99 4.388 10.954 10.125 11.854v-8.385H7.078v-3.469h3.047V9.43c0-3.007 1.792-4.669 4.533-4.669 1.312 0 2.686.235 2.686.235v2.953H15.83c-1.491 0-1.956.925-1.956 1.874v2.25h3.328l-.532 3.469h-2.796v8.385C19.612 23.027 24 18.062 24 12.073z"/></svg>',
            'instagram' => '<svg viewBox="0 0 24 24" width="16" height="16" fill="currentColor"><path d="M12 2.163c3.204 0 3.584.012 4.85.07 3.252.148 4.771 1.691 4.919 4.919.058 1.265.069 1.645.069 4.849 0 3.205-.012 3.584-.069 4.849-.149 3.225-1.664 4.771-4.919 4.919-1.266.058-1.644.07-4.85.07-3.204 0-3.584-.012-4.849-.07-3.26-.149-4.771-1.699-4.919-4.92-.058-1.265-.07-1.644-.07-4.849 0-3.204.013-3.583.07-4.849.149-3.227 1.664-4.771 4.919-4.919 1.266-.057 1.645-.069 4.849-.069zM12 0C8.741 0 8.333.014 7.053.072 2.695.272.273 2.69.073 7.052.014 8.333 0 8.741 0 12c0 3.259.014 3.668.072 4.948.2 4.358 2.618 6.78 6.98 6.98C8.333 23.986 8.741 24 12 24c3.259 0 3.668-.014 4.948-.072 4.354-.2 6.782-2.618 6.979-6.98.059-1.28.073-1.689.073-4.948 0-3.259-.014-3.667-.072-4.947-.196-4.354-2.617-6.78-6.979-6.98C15.668.014 15.259 0 12 0zm0 5.838a6.162 6.162 0 100 12.324 6.162 6.162 0 000-12.324zM12 16a4 4 0 110-8 4 4 0 010 8zm6.406-11.845a1.44 1.44 0 100 2.881 1.44 1.44 0 000-2.881z"/></svg>',
            'twitter'   => '<svg viewBox="0 0 24 24" width="16" height="16" fill="currentColor"><path d="M18.244 2.25h3.308l-7.227 8.26 8.502 11.24H16.17l-5.214-6.817L4.99 21.75H1.68l7.73-8.835L1.254 2.25H8.08l4.713 6.231zm-1.161 17.52h1.833L7.084 4.126H5.117z"/></svg>',
            'snapchat'  => '<svg viewBox="0 0 24 24" width="16" height="16" fill="currentColor"><path d="M12.115 1.564c-.318 0-.698.026-1.107.078-2.628.328-4.148 1.837-4.57 2.686-.346.685-.453 1.953-.453 1.953s-.305-.008-.433-.031c-.347-.058-1-.184-1.428-.438-.135-.083-.341-.129-.536-.089a.637.637 0 00-.479.467c-.024.116-.013.332.124.526.4.567 1.259.957 1.815 1.155.228.081.422.13.565.158-.027.351-.08 1.168.04 1.884.098.583.33 1.24.819 1.848.169.21.36.4.578.572a5.453 5.453 0 01-1.32.181c-1.391-.044-2.585-.4-2.924-.51a.634.634 0 00-.319-.015.541.541 0 00-.324.225.596.596 0 00-.083.376c.032.25.178.435.328.552.793.618 2.378.966 3.655 1.127.3.037.616.06.942.062 1.343.007 2.384.58 2.632 1.096a.434.434 0 01.045.242 1.166 1.166 0 01-.157.472c-.179.351-.497.625-.945.815-1.02.434-2.83.692-4.045.748-.096.004-.207.013-.309.027a.64.64 0 00-.503.41c-.053.136-.056.326.06.51.134.215.357.348.601.362h.004c.15 0 .957.067 2.453.69.967.404 1.884.852 2.308 1.053.303.143.614.238.924.281l.142.016a9.54 9.54 0 002.327-.087c.307-.058.625-.138.944-.24a3.179 3.179 0 00.706-.31c.218-.13.385-.295.453-.466.071-.178.077-.424-.131-.699a.576.576 0 00-.476-.231h-.005c-.173 0-.398.05-.623.08-1.503.208-2.671 0-3.344-.199-.815-.246-.994-.652-1.077-1.002a2.02 2.02 0 01.196-1.391c.279-.58 1.488-1.128 3.161-1.137h.034c.73 0 1.402-.064 1.944-.176a6.837 6.837 0 00.56-.145c-.22-.16-.407-.337-.565-.529-.504-.617-.741-1.319-.844-1.933a6.792 6.792 0 01-.061-2.02l.006-.051c.143-.028.337-.076.565-.157.556-.198 1.415-.588 1.815-1.155a.692.692 0 00.124-.526.637.637 0 00-.479-.467c-.195-.04-.4-.006-.536.089-.428.254-1.08.38-1.428.438-.128.023-.433.031-.433.031s-.107-1.268-.453-1.953c-.422-.849-1.942-2.358-4.57-2.686a11.951 11.951 0 00-1.107-.078z"/></svg>',
            'telegram'  => '<svg viewBox="0 0 24 24" width="16" height="16" fill="currentColor"><path d="M12 0c-6.627 0-12 5.373-12 12s5.373 12 12 12 12-5.373 12-12-5.373-12-12-12zm5.894 8.221l-1.97 9.28c-.145.658-.537.818-1.084.508l-3-2.21-1.446 1.394c-.14.18-.357.295-.548.295l.189-2.663 4.86-4.359c.211-.188-.046-.293-.327-.105l-6.006 3.777-2.584-.813c-.562-.175-.572-.513.117-.828l10.09-3.896c.467-.179.88.106.709.62z"/></svg>',
        ];

        // Awesome brand colors
        $socialColors = [
            'whatsapp'  => '#25D366',
            'facebook'  => '#1877F2',
            'instagram' => '#E4405F',
            'twitter'   => '#000000',
            'snapchat'  => '#FFFC00',
            'telegram'  => '#2AABEE'
        ];

        $socials = [
            'whatsapp'  => [$waNum ? 'https://wa.me/' . $waNum : '', 'WhatsApp'],
            'facebook'  => [$entity['facebook']  ?? '', 'Facebook'],
            'instagram' => [$entity['instagram'] ?? '', 'Instagram'],
            'snapchat'  => [$entity['snapchat']  ?? '', 'Snapchat'],
            'twitter'   => [$entity['twitter']   ?? '', 'X'],
            'telegram'  => [$entity['telegram']  ?? '', 'Telegram'],
        ];
        
        foreach ($socials as $net => [$url, $label]):
            if (empty($url) || $url === 'https://wa.me/') continue;
            $textColor = ($net === 'snapchat') ? '#000' : '#fff';
        ?>
            <a href="<?= e($url) ?>"
               target="_blank" rel="noopener" class="pub-social-btn pub-social-btn--<?= e($net) ?>"
               style="background-color:<?= $socialColors[$net] ?>; color:<?= $textColor ?>; border-color:<?= $socialColors[$net] ?>; display:inline-flex; align-items:center; gap:6px;">
                <?= $socialIcons[$net] ?> <?= e($label) ?>
            </a>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <!-- Share button -->
    <?php if ($showShare): ?>
    <div style="margin-top:12px; position: relative;">
        <button class="pub-btn pub-btn--ghost pub-btn--sm" id="pubShareBtn"
                onclick="pubShareEntity()" style="display:inline-flex;align-items:center;gap:6px;font-weight:600;padding:8px 16px;border-radius:24px;border:1px solid var(--pub-border);background:var(--pub-surface);">
            <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 12v8a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2v-8M16 6l-4-4-4 4M12 2v13"/></svg>
            <?= e(t('entity.share')) ?>
        </button>
        <div id="pubSharePanel" style="display:none;margin-top:10px;padding:16px;
             background:var(--pub-surface);border:1px solid var(--pub-border);
             border-radius:12px;max-width:320px;box-shadow:0 8px 16px rgba(0,0,0,0.1);z-index:50;">
            <p style="margin:0 0 12px;font-size:0.95rem;font-weight:700;color:var(--pub-text);"><?= e(t('entity.share_via', 'Share via')) ?></p>
            <div style="display:flex;gap:8px;flex-wrap:wrap;">
                <!-- Social Share Links injected into DOM by JS -->
                <a href="#" id="pubShareWA" target="_blank" rel="noopener" class="pub-social-btn" style="background-color:#25D366;color:#fff;border-color:#25D366;display:inline-flex;align-items:center;gap:6px;">
                    <?= $socialIcons['whatsapp'] ?> WhatsApp
                </a>
                <a href="#" id="pubShareTW" target="_blank" rel="noopener" class="pub-social-btn" style="background-color:#000;color:#fff;border-color:#000;display:inline-flex;align-items:center;gap:6px;">
                    <?= $socialIcons['twitter'] ?> X
                </a>
                <a href="#" id="pubShareFB" target="_blank" rel="noopener" class="pub-social-btn" style="background-color:#1877F2;color:#fff;border-color:#1877F2;display:inline-flex;align-items:center;gap:6px;">
                    <?= $socialIcons['facebook'] ?> Facebook
                </a>
                <a href="#" id="pubShareTG" target="_blank" rel="noopener" class="pub-social-btn" style="background-color:#2AABEE;color:#fff;border-color:#2AABEE;display:inline-flex;align-items:center;gap:6px;">
                    <?= $socialIcons['telegram'] ?> Telegram
                </a>
                <button class="pub-social-btn" onclick="pubCopyLink()" style="display:inline-flex;align-items:center;gap:6px;background:var(--pub-bg);color:var(--pub-text);border:1px solid var(--pub-border);">
                    <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="9" y="9" width="13" height="13" rx="2" ry="2"></rect><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"></path></svg>
                    <?= e(t('entity.copy_link')) ?>
                </button>
            </div>
        </div>
    </div>
    <?php endif; ?>
</div>
