<?php
declare(strict_types=1);
/**
 * frontend/public/support.php
 * QOOQZ — Help Center / Support Page
 */
require_once dirname(__DIR__) . '/includes/public_context.php';

$GLOBALS['PUB_APP_NAME']   = 'QOOQZ';
$GLOBALS['PUB_BASE_PATH']  = '/frontend/public';
$GLOBALS['PUB_PAGE_TITLE'] = t('support.page_title', 'Help Center') . ' — QOOQZ';
$GLOBALS['PUB_PAGE_TYPE']  = 'support';

include dirname(__DIR__) . '/partials/header.php';
?>

<style>
.support-hero {
    background: linear-gradient(135deg, #0f172a 0%, var(--pub-primary, #2563eb) 100%);
    color: #fff;
    padding: 3.5rem 1.5rem;
    text-align: center;
}
.support-hero h1 { font-size: 2.1rem; margin-bottom: .6rem; }
.support-hero p  { opacity: .9; margin-bottom: 1.5rem; }

.support-search {
    display: flex;
    max-width: 540px;
    margin: 0 auto;
    background: #fff;
    border-radius: 50px;
    overflow: hidden;
    box-shadow: 0 4px 20px rgba(0,0,0,.2);
}
.support-search input {
    flex: 1;
    padding: .8rem 1.25rem;
    border: none;
    font-size: 1rem;
    color: #374151;
    outline: none;
    background: transparent;
}
.support-search button {
    padding: .8rem 1.5rem;
    background: var(--pub-primary, #2563eb);
    color: #fff;
    border: none;
    font-weight: 600;
    cursor: pointer;
}

.support-section {
    max-width: 960px;
    margin: 3rem auto;
    padding: 0 1.5rem;
}
.support-section h2 {
    font-size: 1.4rem;
    font-weight: 700;
    margin-bottom: 1.5rem;
    color: var(--pub-text, #111827);
}

.support-topics-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(260px, 1fr));
    gap: 1.25rem;
}
.support-topic-card {
    background: var(--pub-surface, #f9fafb);
    border: 1px solid var(--pub-border, #e5e7eb);
    border-radius: 12px;
    padding: 1.4rem;
    display: flex;
    gap: 1rem;
    align-items: flex-start;
    cursor: pointer;
    transition: box-shadow .2s, border-color .2s;
    text-decoration: none;
    color: inherit;
}
.support-topic-card:hover {
    border-color: var(--pub-primary, #2563eb);
    box-shadow: 0 4px 16px rgba(37,99,235,.1);
}
.support-topic-card .icon { font-size: 1.75rem; flex-shrink: 0; }
.support-topic-card h3 { font-size: 1rem; font-weight: 600; margin-bottom: .3rem; }
.support-topic-card p  { font-size: .875rem; color: var(--pub-text-muted, #6b7280); margin: 0; }

.support-ticket-band {
    background: var(--pub-primary, #2563eb);
    color: #fff;
    text-align: center;
    padding: 3rem 1.5rem;
    margin: 2rem 0;
}
.support-ticket-band h2 { font-size: 1.5rem; margin-bottom: .6rem; }
.support-ticket-band p  { opacity: .9; margin-bottom: 1.25rem; }
.support-ticket-band a  {
    display: inline-block;
    background: #fff;
    color: var(--pub-primary, #2563eb);
    padding: .7rem 2rem;
    border-radius: 8px;
    font-weight: 700;
    text-decoration: none;
}
.support-ticket-band a:hover { opacity: .9; }

.faq-list { list-style: none; padding: 0; margin: 0; }
.faq-item {
    border: 1px solid var(--pub-border, #e5e7eb);
    border-radius: 10px;
    margin-bottom: .75rem;
    overflow: hidden;
}
.faq-question {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 1rem 1.25rem;
    cursor: pointer;
    font-weight: 600;
    font-size: .97rem;
    color: var(--pub-text, #111827);
    background: var(--pub-surface, #f9fafb);
    user-select: none;
}
.faq-question .arrow { transition: transform .25s; }
.faq-item.open .faq-question .arrow { transform: rotate(180deg); }
.faq-answer {
    padding: 0 1.25rem;
    max-height: 0;
    overflow: hidden;
    transition: max-height .3s ease, padding .3s;
    font-size: .95rem;
    line-height: 1.7;
    color: var(--pub-text, #374151);
    background: var(--pub-bg, #fff);
}
.faq-item.open .faq-answer { max-height: 300px; padding: 1rem 1.25rem; }

.contact-cta-band {
    background: var(--pub-surface, #f9fafb);
    border-top: 1px solid var(--pub-border, #e5e7eb);
    text-align: center;
    padding: 3rem 1.5rem;
}
.contact-cta-band h2 { font-size: 1.4rem; margin-bottom: .5rem; }
.contact-cta-band p  { color: var(--pub-text-muted, #6b7280); margin-bottom: 1.25rem; }
.contact-cta-band a  {
    display: inline-block;
    background: var(--pub-primary, #2563eb);
    color: #fff;
    padding: .7rem 2rem;
    border-radius: 8px;
    text-decoration: none;
    font-weight: 600;
}
.contact-cta-band a:hover { opacity: .9; }
</style>

<!-- Hero -->
<section class="support-hero">
    <h1><?= e(t('support.hero_title', 'How Can We Help?')) ?></h1>
    <p><?= e(t('support.hero_subtitle', 'Browse our help topics or submit a support ticket.')) ?></p>
    <div class="support-search">
        <input type="text" id="supportSearch" placeholder="<?= e(t('support.search_placeholder', 'Search for help...')) ?>">
        <button type="button" onclick="document.getElementById('faqList').scrollIntoView({behavior:'smooth'})">🔍</button>
    </div>
</section>

<!-- Popular Topics -->
<section class="support-section">
    <h2><?= e(t('support.popular_title', 'Popular Topics')) ?></h2>
    <div class="support-topics-grid">
        <a href="#faqList" class="support-topic-card">
            <span class="icon">📦</span>
            <div>
                <h3><?= e(t('support.topic_orders', 'Orders & Shipping')) ?></h3>
                <p><?= e(t('support.topic_orders_desc')) ?></p>
            </div>
        </a>
        <a href="#faqList" class="support-topic-card">
            <span class="icon">💳</span>
            <div>
                <h3><?= e(t('support.topic_payments', 'Payments & Billing')) ?></h3>
                <p><?= e(t('support.topic_payments_desc')) ?></p>
            </div>
        </a>
        <a href="/frontend/public/returns.php" class="support-topic-card">
            <span class="icon">🔄</span>
            <div>
                <h3><?= e(t('support.topic_returns', 'Returns & Refunds')) ?></h3>
                <p><?= e(t('support.topic_returns_desc')) ?></p>
            </div>
        </a>
        <a href="/frontend/profile.php" class="support-topic-card">
            <span class="icon">👤</span>
            <div>
                <h3><?= e(t('support.topic_account', 'Account & Security')) ?></h3>
                <p><?= e(t('support.topic_account_desc')) ?></p>
            </div>
        </a>
        <a href="#faqList" class="support-topic-card">
            <span class="icon">🏪</span>
            <div>
                <h3><?= e(t('support.topic_vendors', 'Vendor Support')) ?></h3>
                <p><?= e(t('support.topic_vendors_desc')) ?></p>
            </div>
        </a>
        <a href="/frontend/public/tickets.php" class="support-topic-card">
            <span class="icon">🛠️</span>
            <div>
                <h3><?= e(t('support.topic_technical', 'Technical Issues')) ?></h3>
                <p><?= e(t('support.topic_technical_desc')) ?></p>
            </div>
        </a>
    </div>
</section>

<!-- Submit Ticket -->
<div class="support-ticket-band">
    <h2><?= e(t('support.ticket_title', 'Still Need Help?')) ?></h2>
    <p><?= e(t('support.ticket_text', 'Submit a support ticket and our team will respond within 24 hours.')) ?></p>
    <a href="/frontend/public/tickets.php"><?= e(t('support.ticket_cta', 'Submit a Ticket')) ?></a>
</div>

<!-- FAQ -->
<section class="support-section" id="faqList">
    <h2><?= e(t('support.faq_title', 'Frequently Asked Questions')) ?></h2>
    <ul class="faq-list" id="faqListEl">
        <?php
        $faqs = [
            ['q' => t('support.faq_1_q'), 'a' => t('support.faq_1_a')],
            ['q' => t('support.faq_2_q'), 'a' => t('support.faq_2_a')],
            ['q' => t('support.faq_3_q'), 'a' => t('support.faq_3_a')],
            ['q' => t('support.faq_4_q'), 'a' => t('support.faq_4_a')],
            ['q' => t('support.faq_5_q'), 'a' => t('support.faq_5_a')],
        ];
        foreach ($faqs as $i => $faq): ?>
        <li class="faq-item" id="faq-<?= $i ?>">
            <div class="faq-question" role="button" tabindex="0" aria-expanded="false" onclick="toggleFaq(this)">
                <span><?= e($faq['q']) ?></span>
                <span class="arrow">▼</span>
            </div>
            <div class="faq-answer"><?= e($faq['a']) ?></div>
        </li>
        <?php endforeach; ?>
    </ul>
</section>

<!-- Contact CTA -->
<div class="contact-cta-band">
    <h2><?= e(t('support.contact_cta_title', 'Contact Us Directly')) ?></h2>
    <p><?= e(t('support.contact_cta_text', 'Prefer to email? Reach our support team.')) ?></p>
    <a href="/frontend/public/contact.php"><?= e(t('support.contact_cta', 'Contact Support')) ?></a>
</div>

<script>
function toggleFaq(btn) {
    const item = btn.closest('.faq-item');
    const isOpen = item.classList.contains('open');
    document.querySelectorAll('.faq-item.open').forEach(el => {
        el.classList.remove('open');
        el.querySelector('.faq-question').setAttribute('aria-expanded', 'false');
    });
    if (!isOpen) {
        item.classList.add('open');
        btn.setAttribute('aria-expanded', 'true');
    }
}

document.querySelectorAll('.faq-question').forEach(btn => {
    btn.addEventListener('keydown', e => {
        if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); toggleFaq(btn); }
    });
});

// Live FAQ search
document.getElementById('supportSearch').addEventListener('input', function () {
    const q = this.value.toLowerCase();
    document.querySelectorAll('.faq-item').forEach(item => {
        const text = item.textContent.toLowerCase();
        item.style.display = text.includes(q) ? '' : 'none';
    });
});
</script>

<?php include dirname(__DIR__) . '/partials/footer.php'; ?>
