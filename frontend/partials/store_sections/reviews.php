<?php
/**
 * frontend/partials/store_sections/reviews.php
 * Store Reviews Section — Rating summary, review cards, submit form
 *
 * Expected variables:
 *   $entity, $entityId
 *   $entityRatingAvg, $entityRatingTotal, $entityRatings
 *   $entityShowReviews, $_isLoggedIn
 *   $sectionSettings — Section JSON settings
 */

$showForm = ($sectionSettings['show_form'] ?? true);
$limit    = (int)($sectionSettings['limit'] ?? 5);

if (!$entityShowReviews) return;
?>

<div class="pub-entity-section-content" id="sectionReviews">
    <div>
        <?php if ($entityRatingAvg !== null): ?>
        <div style="display:flex;align-items:center;gap:14px;margin-bottom:20px;">
            <div style="font-size:2.8rem;font-weight:900;color:var(--pub-accent,#F59E0B);">
                <?= number_format((float)$entityRatingAvg, 1) ?>
            </div>
            <div>
                <div style="font-size:1.3rem;letter-spacing:2px;">
                    <?php for ($si=1; $si<=5; $si++): ?>
                        <?php if ($si <= $entityRatingAvg): ?>
                            <span style="color:var(--pub-accent, #F59E0B);">★</span>
                        <?php elseif ($si - 0.5 <= $entityRatingAvg): ?>
                            <span style="color:var(--pub-accent, #F59E0B);opacity:0.6;">★</span>
                        <?php else: ?>
                            <span style="color:var(--pub-border);">☆</span>
                        <?php endif; ?>
                    <?php endfor; ?>
                </div>
                <div style="font-size:0.82rem;color:var(--pub-muted);"><?= $entityRatingTotal ?> <?= e(t('entity.ratings_count')) ?></div>
            </div>
        </div>
        <?php endif; ?>

        <?php if (!empty($entityRatings)): ?>
        <div style="display:grid;gap:12px;">
            <?php foreach ($entityRatings as $r): ?>
            <div style="background:var(--pub-surface);border:1px solid var(--pub-border);border-radius:var(--pub-radius);padding:14px 16px;">
                <div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:6px;margin-bottom:8px;">
                    <span style="font-weight:700;font-size:0.9rem;"><?= e($r['reviewer_name']) ?></span>
                    <div style="display:flex;align-items:center;gap:6px;">
                        <span style="color:var(--pub-accent, #F59E0B);font-size:1rem;">
                            <?php for ($si=1; $si<=5; $si++): ?>
                                <?= $si <= (float)$r['rating'] ? '★' : '☆' ?>
                            <?php endfor; ?>
                        </span>
                        <span style="font-size:0.75rem;color:var(--pub-muted);"><?= e(substr($r['created_at'] ?? '', 0, 10)) ?></span>
                    </div>
                </div>
                <?php if (!empty($r['review'])): ?>
                    <p style="margin:0;font-size:0.88rem;color:var(--pub-text);"><?= e($r['review']) ?></p>
                <?php endif; ?>
            </div>
            <?php endforeach; ?>
        </div>
        <?php else: ?>
        <div class="pub-empty" style="padding:40px 0;">
            <div class="pub-empty-icon">⭐</div>
            <p class="pub-empty-msg"><?= e(t('entity.no_ratings')) ?></p>
        </div>
        <?php endif; ?>

        <!-- Rate this entity (login-gated) -->
        <?php if ($showForm && $_isLoggedIn): ?>
        <div style="margin-top:24px;background:var(--pub-surface);border:1px solid var(--pub-border);border-radius:var(--pub-radius);padding:20px;">
            <h3 style="margin:0 0 14px;font-size:1rem;font-weight:700;"><?= e(t('entity.rate_title')) ?></h3>
            <form id="pubEntityRateForm" onsubmit="pubSubmitEntityRating(event)">
                <div style="margin-bottom:12px;">
                    <label style="font-size:0.85rem;font-weight:600;display:block;margin-bottom:6px;"><?= e(t('entity.your_rating')) ?></label>
                    <div id="pubEntityStarPicker" style="display:flex;gap:6px;font-size:1.8rem;cursor:pointer;" role="group" aria-label="<?= e(t('entity.your_rating')) ?>">
                        <?php for ($si=1; $si<=5; $si++): ?>
                            <span class="pub-star-pick" data-val="<?= $si ?>" onclick="pubPickEntityStar(<?= $si ?>)"
                                  style="color:var(--pub-border);transition:color 0.15s;user-select:none;">★</span>
                        <?php endfor; ?>
                    </div>
                    <input type="hidden" id="pubEntityRating" name="rating" value="0">
                </div>
                <div style="margin-bottom:12px;">
                    <textarea id="pubEntityReview" name="review" rows="3"
                              placeholder="<?= e(t('entity.write_review')) ?>"
                              class="pub-input" style="width:100%;padding:8px 12px;border-radius:var(--pub-radius);border:1px solid var(--pub-border);background:var(--pub-bg);color:var(--pub-text);font-size:0.88rem;resize:vertical;"></textarea>
                </div>
                <button type="submit" class="pub-btn pub-btn--primary">
                    ⭐ <?= e(t('entity.submit_rating')) ?>
                </button>
                <p id="pubEntityRateMsg" style="margin:8px 0 0;font-size:0.85rem;display:none;"></p>
            </form>
        </div>
        <?php elseif ($showForm): ?>
        <p style="text-align:center;margin-top:20px;font-size:0.88rem;color:var(--pub-muted);">
            <a href="/frontend/login.php?redirect=<?= urlencode($_SERVER['REQUEST_URI'] ?? '') ?>" class="pub-link"><?= e(t('common.login')) ?></a>
            <?= e(t('entity.login_to_rate')) ?>
        </p>
        <?php endif; ?>
    </div>
</div>