<?php
declare(strict_types=1);
/**
 * Component: ad_search
 * Renders the global search bar. Submits to search.php which searches across
 * products, categories, entities, tenants, jobs and auctions.
 */

?>
<div class="pub-search-bar">
    <div class="pub-container">
        <form class="pub-search-form" method="get" action="/frontend/public/search.php" id="pubSearchForm">
            <input type="search" 
                   name="q" 
                   class="pub-search-input"
                   placeholder="<?= e(t('search.placeholder')) ?>"
                   value="<?= e($_GET['q'] ?? '') ?>">
            <button type="submit" class="pub-search-btn"><?= e(t('search.button')) ?></button>
        </form>
    </div>
</div>