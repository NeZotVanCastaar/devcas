<?php
// sitemap-controller.php

// ➤ Admin menu item toevoegen
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

// ➤ Instellingenpagina HTML
function render_sitemap_settings_page()
{
    $orderby = get_option('custom_sitemap_orderby', 'modified');
    $order   = get_option('custom_sitemap_order', 'DESC');
    ?>
    <div class="wrap">
        <h1>Sitemap-instellingen</h1>

        <p>
            <a href="<?php echo esc_url(home_url('/wp-sitemap.xml')); ?>" class="button button-primary" target="_blank">
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
                                printf('<option value="%s"%s>%s</option>', esc_attr($k), selected($orderby, $k, false), esc_html($label));
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
                            <option value="ASC"  <?php selected($order, 'ASC'); ?>>ASC (oudste/eerst alfabetisch)</option>
                        </select>
                    </td>
                </tr>
            </table>
            <?php submit_button(); ?>
        </form>
    </div>
    <?php
}

// ➤ Register instellingen en velden
add_action('admin_init', function () {
    register_setting('custom_sitemap_settings', 'custom_sitemap_enabled_post_types', [
        'sanitize_callback' => function ($input) {
            return is_array($input) ? array_map('sanitize_text_field', $input) : [];
        }
    ]);

    register_setting('custom_sitemap_settings', 'custom_sitemap_enabled_taxonomies', [
        'sanitize_callback' => function ($input) {
            return is_array($input) ? array_map('sanitize_text_field', $input) : [];
        }
    ]);

    register_setting('custom_sitemap_settings', 'custom_sitemap_orderby', [
        'sanitize_callback' => function ($input) {
            $allowed = ['modified','date','title','menu_order'];
            return in_array($input, $allowed, true) ? $input : 'modified';
        },
        'default' => 'modified',
    ]);

    register_setting('custom_sitemap_settings', 'custom_sitemap_order', [
        'sanitize_callback' => function ($input) {
            $allowed = ['ASC','DESC'];
            return in_array($input, $allowed, true) ? $input : 'DESC';
        },
        'default' => 'DESC',
    ]);

    add_settings_section(
        'sitemap_section',
        'Wat wil je tonen in de sitemap?',
        function () {
            echo '<p>Selecteer de post types en taxonomieën die je wil opnemen in de XML-sitemap van WordPress.</p>';
        },
        'custom-sitemap-settings'
    );

    // ➤ Post types veld
    add_settings_field('sitemap_post_types', 'Post types', function () {
        $enabled = (array) get_option('custom_sitemap_enabled_post_types', []);
        foreach (get_post_types(['public' => true], 'objects') as $slug => $post_type) {
            echo '<label>
                    <input type="checkbox" name="custom_sitemap_enabled_post_types[]" value="' . esc_attr($slug) . '" ' . checked(in_array($slug, $enabled, true), true, false) . '>
                    ' . esc_html($post_type->labels->name) . '
                  </label><br>';
        }
    }, 'custom-sitemap-settings', 'sitemap_section');

    // ➤ Taxonomieën veld
    add_settings_field('sitemap_taxonomies', 'Taxonomieën', function () {
        $enabled = (array) get_option('custom_sitemap_enabled_taxonomies', []);
        foreach (get_taxonomies(['public' => true], 'objects') as $slug => $tax) {
            echo '<label>
                    <input type="checkbox" name="custom_sitemap_enabled_taxonomies[]" value="' . esc_attr($slug) . '" ' . checked(in_array($slug, $enabled, true), true, false) . '>
                    ' . esc_html($tax->labels->name) . '
                  </label><br>';
        }
    }, 'custom-sitemap-settings', 'sitemap_section');
});

// ➤ Post types filteren voor de sitemap
add_filter('wp_sitemaps_post_types', function ($post_types) {
    $allowed = (array) get_option('custom_sitemap_enabled_post_types', []);
    return array_intersect_key($post_types, array_flip($allowed));
});

// ➤ Taxonomieën filteren voor de sitemap
add_filter('wp_sitemaps_taxonomies', function ($taxonomies) {
    $allowed = (array) get_option('custom_sitemap_enabled_taxonomies', []);
    return array_intersect_key($taxonomies, array_flip($allowed));
});

add_filter('wp_sitemaps_posts_query_args', function ($args, $post_type) {
    // ➤ Sorteer pagina's zoals in Pagina-attributen (Menu order)
    if ($post_type === 'page') {
        $args['orderby'] = 'menu_order title';
        $args['order']   = 'ASC';
    } else {
        // Voor andere types mag je je huidige default houden
        $args['orderby'] = $args['orderby'] ?? 'modified';
        $args['order']   = $args['order']   ?? 'DESC';
    }

    // ➤ WPML: beperk naar huidige taal
    if (function_exists('apply_filters') && has_filter('wpml_current_language')) {
        $current_lang = apply_filters('wpml_current_language', null);
        if (!$current_lang && function_exists('wpml_get_default_language')) {
            $current_lang = wpml_get_default_language();
        }
        if ($current_lang) {
            $args['lang'] = $current_lang;
        }
    }

    // ➤ Noindex uitsluiten (_seo_noindex = 1)
    $meta_query   = isset($args['meta_query']) ? (array) $args['meta_query'] : [];
    $meta_query[] = [
        'relation' => 'OR',
        ['key' => '_seo_noindex', 'compare' => 'NOT EXISTS'],
        ['key' => '_seo_noindex', 'value' => '1', 'compare' => '!='],
    ];
    $args['meta_query'] = $meta_query;

    return $args;
}, 10, 2);


// ➤ Voeg automatisch noindex toe als pagina niet in sitemap mag
add_action('wp_head', function () {
    if (is_singular()) {
        global $post;
        if (!$post) return;

        $post_type = get_post_type($post);
        $allowed_post_types = (array) get_option('custom_sitemap_enabled_post_types', []);

        // 1) Post type niet toegestaan
        if (!in_array($post_type, $allowed_post_types, true)) {
            echo '<meta name="robots" content="noindex, follow">' . "\n";
            return;
        }

        // 2) Handmatig op noindex gezet
        $manual_noindex = get_post_meta($post->ID, '_seo_noindex', true);
        if ($manual_noindex === '1') {
            echo '<meta name="robots" content="noindex, follow">' . "\n";
            return;
        }

        // 3) Niet in huidige taal
        if (function_exists('apply_filters') && has_filter('wpml_current_language')) {
            $current_lang = apply_filters('wpml_current_language', null);
            if (!$current_lang && function_exists('wpml_get_default_language')) {
                $current_lang = wpml_get_default_language();
            }
            if ($current_lang && function_exists('icl_object_id')) {
                // Als deze post geen vertaling heeft in huidige taal => noindex
                $translated_id = icl_object_id($post->ID, get_post_type($post), false, $current_lang);
                if ((int)$translated_id !== (int)$post->ID) {
                    echo '<meta name="robots" content="noindex, follow">' . "\n";
                    return;
                }
            }
        }
    }

    // ➤ Voor taxonomie-archieven
    if (is_tax() || is_category() || is_tag()) {
        $taxonomy = get_queried_object()->taxonomy ?? null;
        $allowed_taxonomies = (array) get_option('custom_sitemap_enabled_taxonomies', []);
        if ($taxonomy && !in_array($taxonomy, $allowed_taxonomies, true)) {
            echo '<meta name="robots" content="noindex, follow">' . "\n";
        }
    }
});

add_action('wp_head', function () {
    if (!is_page()) return;

    global $post;
    $items   = [];
    $pos     = 1;

    // Home
    $items[] = [
        '@type'    => 'ListItem',
        'position' => $pos++,
        'name'     => get_bloginfo('name'),
        'item'     => trailingslashit(home_url('/')),
    ];

    // Voorouders volgens hiërarchie (root -> child)
    $ancestors = array_reverse(get_post_ancestors($post->ID));
    foreach ($ancestors as $aid) {
        $items[] = [
            '@type'    => 'ListItem',
            'position' => $pos++,
            'name'     => get_the_title($aid),
            'item'     => get_permalink($aid),
        ];
    }

    // Huidige pagina
    $items[] = [
        '@type'    => 'ListItem',
        'position' => $pos++,
        'name'     => get_the_title($post->ID),
        'item'     => get_permalink($post->ID),
    ];

    $data = [
        '@context'        => 'https://schema.org',
        '@type'           => 'BreadcrumbList',
        'itemListElement' => $items,
    ];

    echo '<script type="application/ld+json">' . wp_json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . '</script>' . "\n";
}, 20);
