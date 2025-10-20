<?php

/**
 * Plugin Name: Custom Sitemap & Breadcrumb Controller
 * Description: Beheer sitemap-inhoud, sorteervolgorde, noindex-logica en nette JSON-LD breadcrumbs. Taxonomie-sitemaps zijn standaard uitgeschakeld.
 * Author: Alec Meganck
 * Version: 1.0.1
 */

/* -----------------------------------------------------
 *  ADMIN UI – SITEMAP INSTELLINGEN
 * ---------------------------------------------------*/

add_action('admin_menu', function () {
    if (!current_user_can('administrator')) return;
    add_options_page(
        'Sitemap-instellingen',
        'Sitemap-instellingen',
        'manage_options',
        'custom-sitemap-settings',
        'render_sitemap_settings_page'
    );
});

function render_sitemap_settings_page()
{
    if (!current_user_can('manage_options')) return;

    $orderby = get_option('custom_sitemap_orderby', 'modified');
    $order   = get_option('custom_sitemap_order', 'DESC');
?>
    <div class="wrap">
        <h1>Sitemap-instellingen</h1>

        <p>
            <a href="<?php echo esc_url(home_url('/wp-sitemap.xml')); ?>" class="button button-primary" target="_blank" rel="noopener">
                Sitemap openen
            </a>
        </p>

        <form method="post" action="options.php">
            <?php
            settings_fields('custom_sitemap_settings');
            do_settings_sections('custom-sitemap-settings');
            ?>
            <h2 class="title">Volgorde in sitemap</h2>
            <table class="form-table" role="presentation">
                <tr>
                    <th scope="row"><label for="custom_sitemap_orderby">Order by</label></th>
                    <td>
                        <select name="custom_sitemap_orderby" id="custom_sitemap_orderby">
                            <?php
                            $opts = [
                                'modified'   => 'Laatst aangepast',
                                'date'       => 'Publicatiedatum',
                                'title'      => 'Titel (A–Z)',
                                'menu_order' => 'Menu volgorde (Pages)',
                            ];
                            foreach ($opts as $k => $label) {
                                printf(
                                    '<option value="%s"%s>%s</option>',
                                    esc_attr($k),
                                    selected($orderby, $k, false),
                                    esc_html($label)
                                );
                            }
                            ?>
                        </select>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><label for="custom_sitemap_order">Order</label></th>
                    <td>
                        <select name="custom_sitemap_order" id="custom_sitemap_order">
                            <option value="DESC" <?php selected($order, 'DESC'); ?>>DESC (nieuwste eerst)</option>
                            <option value="ASC" <?php selected($order, 'ASC');  ?>>ASC (oudste/eerst alfabetisch)</option>
                        </select>
                    </td>
                </tr>
            </table>
            <?php submit_button('Opslaan'); ?>
        </form>
    </div>
<?php
}

add_action('admin_init', function () {
    register_setting('custom_sitemap_settings', 'custom_sitemap_enabled_post_types', [
        'sanitize_callback' => function ($input) {
            return is_array($input) ? array_map('sanitize_text_field', $input) : [];
        }
    ]);

    register_setting('custom_sitemap_settings', 'custom_sitemap_orderby', [
        'sanitize_callback' => function ($input) {
            $allowed = ['modified', 'date', 'title', 'menu_order'];
            return in_array($input, $allowed, true) ? $input : 'modified';
        },
        'default' => 'modified',
    ]);

    register_setting('custom_sitemap_settings', 'custom_sitemap_order', [
        'sanitize_callback' => function ($input) {
            $allowed = ['ASC', 'DESC'];
            return in_array($input, $allowed, true) ? $input : 'DESC';
        },
        'default' => 'DESC',
    ]);

    add_settings_section(
        'sitemap_section',
        'Wat wil je tonen in de sitemap?',
        function () {
            echo '<p>Selecteer de post types die je wil opnemen in de XML-sitemap van WordPress. (Taxonomieën zijn standaard uitgeschakeld)</p>';
        },
        'custom-sitemap-settings'
    );

    add_settings_field('sitemap_post_types', 'Post types', function () {
        $enabled = (array) get_option('custom_sitemap_enabled_post_types', []);
        foreach (get_post_types(['public' => true], 'objects') as $slug => $post_type) {
            echo '<label style="display:inline-block;margin:2px 0;">
                    <input type="checkbox" name="custom_sitemap_enabled_post_types[]" value="' . esc_attr($slug) . '" ' . checked(in_array($slug, $enabled, true), true, false) . '>
                    ' . esc_html($post_type->labels->name) . '
                  </label><br>';
        }
    }, 'custom-sitemap-settings', 'sitemap_section');
});


/* -----------------------------------------------------
 *  SITEMAP FILTERS
 * ---------------------------------------------------*/

// Alleen tonen wat geselecteerd is
add_filter('wp_sitemaps_post_types', function ($post_types) {
    $allowed = array_filter((array) get_option('custom_sitemap_enabled_post_types', []));
    return $allowed ? array_intersect_key($post_types, array_flip($allowed)) : $post_types;
});

// ❌ Taxonomie-sitemaps volledig uitschakelen
add_filter('wp_sitemaps_add_provider', function ($provider, $name) {
    if ($name === 'taxonomies') return false;
    return $provider;
}, 10, 2);

// Sorteer per type
add_filter('wp_sitemaps_posts_query_args', function ($args, $post_type) {
    $opt_orderby = get_option('custom_sitemap_orderby', 'modified');
    $opt_order   = get_option('custom_sitemap_order', 'DESC');

    if ($post_type === 'page') {
        $args['orderby'] = 'menu_order title';
        $args['order']   = 'ASC';
    } else {
        $args['orderby'] = $args['orderby'] ?? $opt_orderby;
        $args['order']   = $args['order']   ?? $opt_order;
    }

    // WPML / Polylang compatibiliteit
    if (function_exists('apply_filters') && has_filter('wpml_current_language')) {
        $current_lang = apply_filters('wpml_current_language', null);
        if (!$current_lang && function_exists('wpml_get_default_language')) {
            $current_lang = wpml_get_default_language();
        }
        if ($current_lang) $args['lang'] = $current_lang;
    } elseif (function_exists('pll_current_language')) {
        $args['lang'] = pll_current_language('slug');
    }

    // Noindex uitsluiten
    $meta_query   = isset($args['meta_query']) ? (array) $args['meta_query'] : [];
    $meta_query[] = [
        'relation' => 'OR',
        ['key' => '_seo_noindex', 'compare' => 'NOT EXISTS'],
        ['key' => '_seo_noindex', 'value' => '1', 'compare' => '!='],
    ];
    $args['meta_query'] = $meta_query;

    return $args;
}, 10, 2);


/* -----------------------------------------------------
 *  NOINDEX META – VOOR UITGESLOTEN PAGINA'S
 * ---------------------------------------------------*/

add_action('wp_head', function () {
    if (is_search() || is_404()) {
        echo '<meta name="robots" content="noindex, follow">' . "\n";
        return;
    }

    if (is_singular()) {
        global $post;
        if (!$post) return;

        $post_type          = get_post_type($post);
        $allowed_post_types = (array) get_option('custom_sitemap_enabled_post_types', []);

        if ($allowed_post_types && !in_array($post_type, $allowed_post_types, true)) {
            echo '<meta name="robots" content="noindex, follow">' . "\n";
            return;
        }

        $manual_noindex = get_post_meta($post->ID, '_seo_noindex', true);
        if ($manual_noindex === '1') {
            echo '<meta name="robots" content="noindex, follow">' . "\n";
            return;
        }

        // WPML check
        if (function_exists('apply_filters') && has_filter('wpml_current_language')) {
            $current_lang = apply_filters('wpml_current_language', null);
            if (!$current_lang && function_exists('wpml_get_default_language')) {
                $current_lang = wpml_get_default_language();
            }
            if ($current_lang && function_exists('icl_object_id')) {
                $translated_id = icl_object_id($post->ID, get_post_type($post), false, $current_lang);
                if ((int) $translated_id !== (int) $post->ID) {
                    echo '<meta name="robots" content="noindex, follow">' . "\n";
                    return;
                }
            }
        }
    }
}, 5);


/* -----------------------------------------------------
 *  JSON-LD BREADCRUMBS – NETTE STRUCTUUR
 * ---------------------------------------------------*/

add_action('wp_head', function () {
    if (!is_page() && !is_singular()) return;

    $items = [[
        '@type'    => 'ListItem',
        'position' => 1,
        'name'     => 'Home',
        'item'     => ['@type' => 'WebPage', '@id' => trailingslashit(home_url('/'))],
    ]];

    if (is_page()) {
        global $post;
        $ancestors = array_reverse(get_post_ancestors($post->ID));
        $pos = 2;
        foreach ($ancestors as $aid) {
            $items[] = [
                '@type'    => 'ListItem',
                'position' => $pos++,
                'name'     => get_the_title($aid),
                'item'     => ['@type' => 'WebPage', '@id' => get_permalink($aid)],
            ];
        }
        $items[] = [
            '@type'    => 'ListItem',
            'position' => count($items) + 1,
            'name'     => get_the_title($post->ID),
            'item'     => ['@type' => 'WebPage', '@id' => get_permalink($post->ID)],
        ];
    } elseif (is_singular()) {
        $pos = 2;
        $pt  = get_post_type_object(get_post_type());
        if ($pt && !is_post_type_hierarchical($pt->name)) {
            if (!empty($pt->has_archive)) {
                $items[] = [
                    '@type'    => 'ListItem',
                    'position' => $pos++,
                    'name'     => $pt->labels->name,
                    'item'     => ['@type' => 'WebPage', '@id' => get_post_type_archive_link($pt->name)],
                ];
            }
        }
        $items[] = [
            '@type'    => 'ListItem',
            'position' => $pos++,
            'name'     => get_the_title(),
            'item'     => ['@type' => 'WebPage', '@id' => get_permalink()],
        ];
    }

    $data = [
        '@context'        => 'https://schema.org',
        '@type'           => 'BreadcrumbList',
        'itemListElement' => $items,
    ];

    echo '<script type="application/ld+json">' .
        wp_json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) .
        '</script>' . "\n";
}, 20);
