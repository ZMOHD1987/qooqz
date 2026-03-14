<?php
declare(strict_types=1);
/**
 * frontend/public/job.php
 * QOOQZ — Job Detail Page + Application Form
 *
 * Shows full job information (title, salary, deadline, description,
 * requirements, benefits) and an inline application form that POSTs
 * to /api/public/job_applications (no auth required).
 */

require_once dirname(__DIR__) . '/includes/public_context.php';

$ctx      = $GLOBALS['PUB_CONTEXT'];
$lang     = $ctx['lang'];
$dir      = $ctx['dir'];
$tenantId = $ctx['tenant_id'];

$jobId = isset($_GET['id']) && ctype_digit((string)$_GET['id']) ? (int)$_GET['id'] : 0;

if (!$jobId) {
    header('Location: /frontend/public/jobs.php');
    exit;
}

$resp = pub_fetch(pub_api_url('public/jobs/' . $jobId) . '?lang=' . urlencode($lang));
$job  = $resp['data']['job'] ?? null;

if (!$job) {
    header('Location: /frontend/public/jobs.php');
    exit;
}

$jobTitle   = $job['title']          ?? $job['slug'] ?? '';
$jobType    = $job['job_type']       ?? '';
$empType    = $job['employment_type']?? $jobType;
$salMin     = $job['salary_min']     ?? null;
$salMax     = $job['salary_max']     ?? null;
$salCur     = $job['salary_currency']?? '';
$deadline   = $job['application_deadline'] ?? $job['deadline'] ?? null;
$isRemote   = !empty($job['is_remote']);
$isFeatured = !empty($job['is_featured']);
$isUrgent   = !empty($job['is_urgent']);
$desc       = $job['description']    ?? '';
$reqs       = $job['requirements']   ?? '';
$benefits   = $job['benefits']       ?? '';
$experience = $job['experience_level'] ?? '';
$positions  = (int)($job['positions_available'] ?? 0);

$empLabels = [
    'full_time'  => t('jobs.type_full_time'),
    'part_time'  => t('jobs.type_part_time'),
    'contract'   => t('jobs.type_contract'),
    'freelance'  => t('jobs.type_freelance'),
    'internship' => t('jobs.type_internship'),
];

$GLOBALS['PUB_APP_NAME']   = 'QOOQZ';
$GLOBALS['PUB_BASE_PATH']  = '/frontend/public';
$GLOBALS['PUB_PAGE_TITLE'] = e($jobTitle) . ' — QOOQZ';

include dirname(__DIR__) . '/partials/header.php';

// Resolve job card style from DB card_styles (card_type='job')
$_jobDetailCardStyle = pub_card_inline_style('jobs');
$_jobDetailCardClass = pub_card_css_class('jobs');
?>

<!-- Breadcrumb -->
<div style="background:var(--pub-surface);padding:10px 0;border-bottom:1px solid var(--pub-border);">
    <div class="pub-container">
        <nav aria-label="breadcrumb" style="font-size:0.85rem;color:var(--pub-muted);">
            <a href="/frontend/public/index.php"><?= e(t('nav.home')) ?></a>
            <span style="margin:0 6px;">›</span>
            <a href="/frontend/public/jobs.php"><?= e(t('nav.jobs')) ?></a>
            <span style="margin:0 6px;">›</span>
            <span><?= e($jobTitle) ?></span>
        </nav>
    </div>
</div>

<main class="pub-container" style="padding-top:28px;padding-bottom:48px;">

    <div style="display:grid;grid-template-columns:1fr;gap:28px;max-width:900px;margin:0 auto;">

        <!-- =============================================
             JOB HEADER CARD
        ============================================= -->
        <div class="pub-card<?= $_jobDetailCardClass ? ' ' . $_jobDetailCardClass : '' ?>" style="padding:28px;<?= e($_jobDetailCardStyle) ?>">
            <div style="display:flex;flex-wrap:wrap;align-items:flex-start;gap:16px;margin-bottom:18px;">
                <div style="flex:1;min-width:0;">
                    <h1 style="font-size:1.5rem;font-weight:700;color:var(--pub-text);margin:0 0 10px;">
                        <?= e($jobTitle) ?>
                    </h1>
                    <div style="display:flex;flex-wrap:wrap;gap:8px;align-items:center;">
                        <?php if ($isFeatured): ?>
                            <span class="pub-tag pub-tag--featured"><?= e(t('jobs.featured')) ?></span>
                        <?php endif; ?>
                        <?php if ($isUrgent): ?>
                            <span class="pub-tag pub-tag--urgent"><?= e(t('jobs.urgent')) ?></span>
                        <?php endif; ?>
                        <?php if ($isRemote): ?>
                            <span class="pub-tag pub-tag--remote"><?= e(t('jobs.remote')) ?></span>
                        <?php endif; ?>
                        <?php if ($empType && isset($empLabels[$empType])): ?>
                            <span class="pub-tag" style="background:var(--pub-surface);color:var(--pub-muted);">
                                🕐 <?= e($empLabels[$empType]) ?>
                            </span>
                        <?php endif; ?>
                    </div>
                </div>
                <div>
                    <a href="#pub-apply-form" class="pub-btn pub-btn--primary"
                       style="white-space:nowrap;text-decoration:none;">
                        📝 <?= e(t('jobs.apply')) ?>
                    </a>
                </div>
            </div>

            <!-- Job meta grid -->
            <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:14px;
                        border-top:1px solid var(--pub-border);padding-top:18px;">
                <?php if ($salMin || $salMax): ?>
                <div>
                    <p style="font-size:0.78rem;color:var(--pub-muted);margin:0 0 3px;"><?= e(t('jobs.salary')) ?></p>
                    <p style="font-weight:600;color:var(--pub-text);margin:0;">
                        <?php if ($salMin && $salMax): ?>
                            <?= number_format((float)$salMin) ?> <?= e(t('jobs.salary_to')) ?> <?= number_format((float)$salMax) ?>
                            <?= e($salCur) ?> <?= e(t('jobs.per_month')) ?>
                        <?php elseif ($salMax): ?>
                            <?= e(t('jobs.salary_to')) ?> <?= number_format((float)$salMax) ?> <?= e($salCur) ?>
                        <?php else: ?>
                            <?= number_format((float)$salMin) ?>+ <?= e($salCur) ?>
                        <?php endif; ?>
                    </p>
                </div>
                <?php endif; ?>

                <?php if ($deadline): ?>
                <div>
                    <p style="font-size:0.78rem;color:var(--pub-muted);margin:0 0 3px;"><?= e(t('jobs.deadline')) ?></p>
                    <p style="font-weight:600;color:var(--pub-text);margin:0;">
                        📅 <?= e(substr((string)$deadline, 0, 10)) ?>
                    </p>
                </div>
                <?php endif; ?>

                <?php if ($experience): ?>
                <div>
                    <p style="font-size:0.78rem;color:var(--pub-muted);margin:0 0 3px;"><?= e(t('jobs.experience')) ?></p>
                    <p style="font-weight:600;color:var(--pub-text);margin:0;"><?= e(ucfirst($experience)) ?></p>
                </div>
                <?php endif; ?>

                <?php if ($positions > 0): ?>
                <div>
                    <p style="font-size:0.78rem;color:var(--pub-muted);margin:0 0 3px;"><?= e(t('jobs.positions')) ?></p>
                    <p style="font-weight:600;color:var(--pub-text);margin:0;"><?= $positions ?></p>
                </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- =============================================
             DESCRIPTION / REQUIREMENTS / BENEFITS
        ============================================= -->
        <?php if ($desc): ?>
        <div class="pub-card<?= $_jobDetailCardClass ? ' ' . $_jobDetailCardClass : '' ?>" style="padding:24px;<?= e($_jobDetailCardStyle) ?>">
            <h2 style="font-size:1.1rem;font-weight:600;margin:0 0 14px;color:var(--pub-text);">
                📋 <?= e(t('products.description')) ?>
            </h2>
            <div style="line-height:1.8;color:var(--pub-text);"><?= nl2br(e($desc)) ?></div>
        </div>
        <?php endif; ?>

        <?php if ($reqs): ?>
        <div class="pub-card<?= $_jobDetailCardClass ? ' ' . $_jobDetailCardClass : '' ?>" style="padding:24px;<?= e($_jobDetailCardStyle) ?>">
            <h2 style="font-size:1.1rem;font-weight:600;margin:0 0 14px;color:var(--pub-text);">
                ✅ <?= e(t('jobs.requirements')) ?>
            </h2>
            <div style="line-height:1.8;color:var(--pub-text);"><?= nl2br(e($reqs)) ?></div>
        </div>
        <?php endif; ?>

        <?php if ($benefits): ?>
        <div class="pub-card<?= $_jobDetailCardClass ? ' ' . $_jobDetailCardClass : '' ?>" style="padding:24px;<?= e($_jobDetailCardStyle) ?>">
            <h2 style="font-size:1.1rem;font-weight:600;margin:0 0 14px;color:var(--pub-text);">
                🎁 <?= e(t('jobs.benefits')) ?>
            </h2>
            <div style="line-height:1.8;color:var(--pub-text);"><?= nl2br(e($benefits)) ?></div>
        </div>
        <?php endif; ?>

        <!-- =============================================
             APPLICATION FORM
        ============================================= -->
        <div class="pub-card<?= $_jobDetailCardClass ? ' ' . $_jobDetailCardClass : '' ?>" id="pub-apply-form" style="padding:28px;<?= e($_jobDetailCardStyle) ?>">
            <h2 style="font-size:1.2rem;font-weight:700;margin:0 0 20px;color:var(--pub-text);">
                📝 <?= e(t('jobs.apply_title')) ?>
            </h2>

            <!-- Success / Error messages -->
            <div id="pubApplySuccess" style="display:none;padding:14px 18px;border-radius:8px;
                 background:#10b98122;border:1px solid #10b981;color:#10b981;margin-bottom:18px;">
                ✅ <?= e(t('jobs.application_success')) ?>
            </div>
            <div id="pubApplyError" style="display:none;padding:14px 18px;border-radius:8px;
                 background:#ef444422;border:1px solid #ef4444;color:#ef4444;margin-bottom:18px;">
                ❌ <span id="pubApplyErrorMsg"><?= e(t('jobs.application_error')) ?></span>
            </div>

            <form id="pubApplyForm" novalidate>
                <input type="hidden" name="job_id" value="<?= $jobId ?>">

                <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:14px;margin-bottom:14px;">
                    <div>
                        <label style="display:block;font-size:0.86rem;font-weight:600;color:var(--pub-muted);margin-bottom:5px;">
                            <?= e(t('jobs.full_name')) ?> <span style="color:#ef4444;">*</span>
                        </label>
                        <input type="text" name="full_name" required
                               class="pub-search-input" style="width:100%;box-sizing:border-box;"
                               placeholder="<?= e(t('jobs.full_name')) ?>">
                    </div>
                    <div>
                        <label style="display:block;font-size:0.86rem;font-weight:600;color:var(--pub-muted);margin-bottom:5px;">
                            <?= e(t('jobs.email')) ?> <span style="color:#ef4444;">*</span>
                        </label>
                        <input type="email" name="email" required
                               class="pub-search-input" style="width:100%;box-sizing:border-box;"
                               placeholder="you@example.com">
                    </div>
                    <div>
                        <label style="display:block;font-size:0.86rem;font-weight:600;color:var(--pub-muted);margin-bottom:5px;">
                            <?= e(t('jobs.phone')) ?>
                        </label>
                        <input type="tel" name="phone"
                               class="pub-search-input" style="width:100%;box-sizing:border-box;"
                               placeholder="+971 50 000 0000">
                    </div>
                </div>

                <div style="margin-bottom:14px;">
                    <label style="display:block;font-size:0.86rem;font-weight:600;color:var(--pub-muted);margin-bottom:5px;">
                        <?= e(t('jobs.cover_letter')) ?>
                    </label>
                    <textarea name="cover_letter" rows="5"
                              class="pub-search-input" style="width:100%;box-sizing:border-box;resize:vertical;"
                              placeholder="<?= e(t('jobs.cover_letter')) ?>..."></textarea>
                </div>

                <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:14px;margin-bottom:20px;">
                    <div>
                        <label style="display:block;font-size:0.86rem;font-weight:600;color:var(--pub-muted);margin-bottom:5px;">
                            <?= e(t('jobs.cv_url')) ?>
                        </label>
                        <input type="url" name="cv_file_url"
                               class="pub-search-input" style="width:100%;box-sizing:border-box;"
                               placeholder="https://...">
                    </div>
                    <div>
                        <label style="display:block;font-size:0.86rem;font-weight:600;color:var(--pub-muted);margin-bottom:5px;">
                            <?= e(t('jobs.portfolio')) ?>
                        </label>
                        <input type="url" name="portfolio_url"
                               class="pub-search-input" style="width:100%;box-sizing:border-box;"
                               placeholder="https://...">
                    </div>
                    <div>
                        <label style="display:block;font-size:0.86rem;font-weight:600;color:var(--pub-muted);margin-bottom:5px;">
                            <?= e(t('jobs.linkedin')) ?>
                        </label>
                        <input type="url" name="linkedin_url"
                               class="pub-search-input" style="width:100%;box-sizing:border-box;"
                               placeholder="https://linkedin.com/in/...">
                    </div>
                </div>

                <button type="submit" id="pubApplyBtn" class="pub-btn pub-btn--primary"
                        style="min-width:200px;justify-content:center;">
                    📝 <?= e(t('jobs.submit_application')) ?>
                </button>
            </form>
        </div>

    </div><!-- /grid -->
</main>

<script>
(function () {
  var form    = document.getElementById('pubApplyForm');
  var btn     = document.getElementById('pubApplyBtn');
  var success = document.getElementById('pubApplySuccess');
  var errBox  = document.getElementById('pubApplyError');
  var errMsg  = document.getElementById('pubApplyErrorMsg');

  if (!form) return;

  // Require login — check localStorage first, then window.pubSessionUser (PHP session)
  (function () {
    try {
      var pubU = JSON.parse(localStorage.getItem('pubUser') || 'null');
      if (!pubU || !pubU.id) {
        if (typeof window.pubSessionUser !== 'undefined' && window.pubSessionUser && window.pubSessionUser.id) {
          pubU = window.pubSessionUser;
          try { localStorage.setItem('pubUser', JSON.stringify(pubU)); } catch (e) {}
        }
      }
      if (!pubU || !pubU.id) {
        form.innerHTML = '<div style="padding:20px;text-align:center;">'
          + '<p style="color:var(--pub-muted);margin-bottom:12px;"><?= e(t('auth.login_required', ['default'=>'Please login to apply'])) ?></p>'
          + '<a href="/frontend/login.php?redirect=' + encodeURIComponent(window.location.href) + '" class="pub-btn pub-btn--primary">Login</a>'
          + '</div>';
        return;
      }
    } catch (e2) {}
  })();

  form.addEventListener('submit', function (e) {
    e.preventDefault();

    // Re-check login on submit
    try {
      var pubU2 = JSON.parse(localStorage.getItem('pubUser') || 'null');
      if (!pubU2 || !pubU2.id) {
        if (typeof window.pubSessionUser !== 'undefined' && window.pubSessionUser && window.pubSessionUser.id) {
          pubU2 = window.pubSessionUser;
        }
      }
      if (!pubU2 || !pubU2.id) {
        window.location.href = '/frontend/login.php?redirect=' + encodeURIComponent(window.location.href);
        return;
      }
    } catch (e3) {}

    success.style.display = 'none';
    errBox.style.display  = 'none';

    var fd   = new FormData(form);
    var data = {};
    fd.forEach(function (v, k) { data[k] = v; });

    if (!data.full_name || !data.full_name.trim()) {
      errMsg.textContent = '<?= e(t('checkout.error_fields_required')) ?>';
      errBox.style.display = 'block';
      return;
    }
    if (!data.email || !data.email.trim()) {
      errMsg.textContent = '<?= e(t('checkout.error_fields_required')) ?>';
      errBox.style.display = 'block';
      return;
    }

    btn.disabled = true;
    btn.textContent = '⏳ ...';

    fetch('/api/public/job_applications', {
      method:  'POST',
      credentials: 'include',
      headers: { 'Content-Type': 'application/json' },
      body:    JSON.stringify(data),
    })
    .then(function (r) { return r.json(); })
    .then(function (json) {
      if (json.success) {
        form.reset();
        success.style.display = 'block';
        success.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
      } else {
        errMsg.textContent = json.message || '<?= e(t('jobs.application_error')) ?>';
        errBox.style.display = 'block';
      }
    })
    .catch(function () {
      errMsg.textContent = '<?= e(t('jobs.application_error')) ?>';
      errBox.style.display = 'block';
    })
    .finally(function () {
      btn.disabled = false;
      btn.textContent = '📝 <?= e(t('jobs.submit_application')) ?>';
    });
  });
}());
</script>

<?php include dirname(__DIR__) . '/partials/footer.php'; ?>