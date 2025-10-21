<?php
defined('ABSPATH') || exit;

// Onder CASTAAR submenu (admin-only)
add_action('admin_menu', function () {
    if (!current_user_can('manage_options')) return; // 🔒 enkel admins
    add_submenu_page(
        'castaar',                         // parent: CASTAAR
        'SEO Import (RM, Yoast, AIO)',     // page title
        'Importeer SEO Data',              // menu title
        'manage_options',                  // capability
        'import-seo-data',                 // slug
        'devcas_import_seo_page'           // callback
    );
});

function devcas_import_seo_page() {
    if (!current_user_can('manage_options')) {
        wp_die('Geen toegang.');
    }

    if (!empty($_POST['seo_import']) && check_admin_referer('import_seo_action')) {
        $updated = 0;

        $posts = get_posts([
            'post_type'   => get_post_types(['public' => true]),
            'post_status' => ['publish', 'draft'],
            'numberposts' => -1,
        ]);

        foreach ($posts as $post) {
            $id       = (int) $post->ID;
            $imported = false;
            $aioseo   = null; // ✅ reset per iteratie

            // Meta title
            $meta_title = '';
            $rank_title = get_post_meta($id, 'rank_math_title', true);

            if ($rank_title) {
                $post_title = get_the_title($id);
                $site_name  = get_bloginfo('name');
                $site_desc  = get_bloginfo('description');

                $meta_title = str_replace(
                    ['%title%', '%page%', '%sep%', '%sitename%', '%sitedesc%'],
                    [$post_title, '', '|', $site_name, $site_desc],
                    $rank_title
                );
            } else {
                $meta_title = get_post_meta($id, '_yoast_wpseo_title', true);
                if (!$meta_title) {
                    $aioseo = get_post_meta($id, '_aioseo_meta', true);
                    if (is_array($aioseo) && !empty($aioseo['title'])) {
                        $meta_title = $aioseo['title'];
                    }
                }
            }

            if (!empty($meta_title)) {
                update_post_meta($id, '_custom_meta_title', $meta_title);
                $imported = true;
            }

            // Meta description
            $meta_desc = get_post_meta($id, 'rank_math_description', true);
            if (!$meta_desc) {
                $meta_desc = get_post_meta($id, '_yoast_wpseo_metadesc', true);
            }
            if (!$meta_desc) {
                $aioseo = $aioseo ?? get_post_meta($id, '_aioseo_meta', true);
                if (is_array($aioseo) && !empty($aioseo['description'])) {
                    $meta_desc = $aioseo['description'];
                }
            }
            if (!empty($meta_desc)) {
                update_post_meta($id, '_custom_meta_description', $meta_desc);
                $imported = true;
            }

            // Focus keyword(s)
            $rank_kw = get_post_meta($id, 'rank_math_focus_keyword', true);
            $yoast_kw = get_post_meta($id, '_yoast_wpseo_focuskw', true);
            if (!isset($aioseo)) {
                $aioseo = get_post_meta($id, '_aioseo_meta', true);
            }
            $aio_kw = (is_array($aioseo) && !empty($aioseo['focuskw'])) ? $aioseo['focuskw'] : null;

            $keywords = $rank_kw ?: $yoast_kw ?: $aio_kw;
            $kw_array = [];

            if (is_array($keywords)) {
                $kw_array = $keywords;
            } elseif (is_string($keywords)) {
                $kw_array = array_map('trim', explode(',', $keywords));
            }

            if (!empty($kw_array)) {
                update_post_meta($id, '_custom_main_keyword', $kw_array[0]);
                for ($i = 1; $i <= 4; $i++) {
                    update_post_meta($id, "_custom_extra_keyword_$i", $kw_array[$i] ?? '');
                }
                $imported = true;
            }

            if ($imported) $updated++;
        }

        echo '<div class="updated notice"><p><strong>' . esc_html($updated) . ' posts/pagina’s zijn geüpdatet met SEO data.</strong></p></div>';
    }

    echo '<div class="wrap"><h1>Importeer SEO Data (Rank Math, Yoast, AIO)</h1>';
    echo '<p>Klik op onderstaande knop om SEO titles, descriptions en keywords over te nemen uit Rank Math, Yoast SEO of All in One SEO.</p>';
    echo '<form method="post">';
    wp_nonce_field('import_seo_action');
    echo '<p><input type="submit" name="seo_import" class="button button-primary" value="Start Import"></p>';
    echo '</form></div>';
}
