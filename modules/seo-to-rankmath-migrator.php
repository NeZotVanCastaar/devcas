<?php
if (!defined('ABSPATH')) exit;

// ============================
//   SEO naar Rank Math Migrator
// ============================

function devcas_is_rank_math_active(): bool {
    return defined('RANK_MATH_VERSION') && class_exists('\RankMath\Helper');
}

function devcas_get_posts_with_seo_meta(): array {
    global $wpdb;

    $meta_keys = [
        '_custom_meta_title',
        '_custom_meta_description',
        '_custom_main_keyword',
        '_custom_extra_keyword_1',
        '_custom_extra_keyword_2',
        '_custom_extra_keyword_3',
        '_custom_extra_keyword_4',
        '_seo_noindex'
    ];

    $query = "
        SELECT DISTINCT post_id
        FROM {$wpdb->postmeta}
        WHERE meta_key IN ('" . implode("','", $meta_keys) . "')
    ";

    return array_map('intval', $wpdb->get_col($query));
}

/**
 * Migreer SEO data + FORCEER Rank Math score
 */
function devcas_migrate_seo_to_rankmath(int $post_id) {

    if (!devcas_is_rank_math_active()) {
        return new WP_Error('rank_math_not_active', 'Rank Math plugin is niet actief.');
    }

    // Eigen meta ophalen
    $title       = get_post_meta($post_id, '_custom_meta_title', true);
    $description = get_post_meta($post_id, '_custom_meta_description', true);
    $main_kw     = get_post_meta($post_id, '_custom_main_keyword', true);

    $extra_keywords = [];
    for ($i = 1; $i <= 4; $i++) {
        $kw = get_post_meta($post_id, "_custom_extra_keyword_$i", true);
        if ($kw) {
            $extra_keywords[] = $kw;
        }
    }

    $noindex = get_post_meta($post_id, '_seo_noindex', true);

    // Rank Math meta instellen
    if ($title) {
        update_post_meta($post_id, 'rank_math_title', $title);
    }

    if ($description) {
        update_post_meta($post_id, 'rank_math_description', $description);
    }

    $keywords = array_filter(array_merge([$main_kw], $extra_keywords));
    if ($keywords) {
        update_post_meta(
            $post_id,
            'rank_math_focus_keyword',
            implode(', ', $keywords)
        );
    }

    if ($noindex === '1') {
        update_post_meta($post_id, 'rank_math_robots', ['noindex' => 'noindex']);
    }

    /**
     * 🔥 KRITISCH DEEL 🔥
     * Forceer Rank Math analyse + score
     */

    // Simuleer échte post save
    remove_action('save_post', 'rank_math_save_post');
    wp_update_post([
        'ID' => $post_id,
        'post_modified'     => current_time('mysql'),
        'post_modified_gmt' => current_time('mysql', 1),
    ]);
    add_action('save_post', 'rank_math_save_post');

    // Rank Math Analyzer forceren
    if (class_exists('\RankMath\SEO\Analyzer')) {
        $analyzer = new \RankMath\SEO\Analyzer();
        $analyzer->analyze_post($post_id);
    }

    // Score expliciet opslaan (correcte manier)
    if (class_exists('\RankMath\SEO\Score')) {
        $score = \RankMath\SEO\Score::get($post_id);
        update_post_meta($post_id, 'rank_math_seo_score', (int) $score);
        update_post_meta($post_id, 'rank_math_seo_score_readonly', (int) $score);
    }

    // Cache flushen
    clean_post_cache($post_id);
    wp_cache_delete($post_id, 'posts');

    return true;
}

/**
 * Bulk migratie
 */
function devcas_bulk_migrate_seo_to_rankmath() {

    if (!current_user_can('manage_options')) {
        wp_die('Onvoldoende rechten.');
    }

    check_admin_referer('devcas_migrate_seo', 'devcas_migrate_nonce');

    $posts = devcas_get_posts_with_seo_meta();
    $count = 0;

    foreach ($posts as $post_id) {
        if (!is_wp_error(devcas_migrate_seo_to_rankmath($post_id))) {
            $count++;
        }
    }

    wp_redirect(
        add_query_arg(
            'message',
            urlencode("Migratie voltooid. {$count} posts volledig geanalyseerd."),
            wp_get_referer()
        )
    );
    exit;
}
add_action('admin_post_devcas_migrate_seo', 'devcas_bulk_migrate_seo_to_rankmath');

/**
 * Admin pagina
 */
function devcas_seo_migrator_page() {

    if (!current_user_can('manage_options')) return;

    $count = count(devcas_get_posts_with_seo_meta());
    ?>

    <div class="wrap">
        <h1>SEO → Rank Math Migrator</h1>

        <p>Posts met SEO-data: <strong><?= esc_html($count); ?></strong></p>

        <form method="post" action="<?= admin_url('admin-post.php'); ?>">
            <?php wp_nonce_field('devcas_migrate_seo', 'devcas_migrate_nonce'); ?>
            <input type="hidden" name="action" value="devcas_migrate_seo">

            <p>
                <input
                    type="submit"
                    class="button button-primary"
                    value="Start migratie + analyse"
                    onclick="return confirm('Dit overschrijft Rank Math SEO-gegevens. Doorgaan?');"
                >
            </p>
        </form>
    </div>
    <?php
}

add_action('admin_menu', function () {
    add_submenu_page(
        'castaar',
        'SEO naar Rank Math',
        'SEO Migrator',
        'manage_options',
        'devcas-seo-migrator',
        'devcas_seo_migrator_page'
    );
});