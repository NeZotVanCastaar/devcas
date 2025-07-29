<?php

add_action('admin_menu', function () {
    add_options_page(
        'Sitemap-instellingen',
        'Sitemap-instellingen',
        'manage_options',
        'custom-sitemap-settings',
        'render_sitemap_settings_page'
    );
});

function render_sitemap_settings_page() {
    ?>
    <div class="wrap">
        <h1>Sitemap-instellingen</h1>
        <form method="post" action="options.php">
            <?php
            settings_fields('custom_sitemap_settings');
            do_settings_sections('custom-sitemap-settings');
            submit_button();
            ?>
        </form>
    </div>
    <?php
}

add_action('admin_init', function () {
    register_setting('custom_sitemap_settings', 'custom_sitemap_enabled_post_types');
    register_setting('custom_sitemap_settings', 'custom_sitemap_enabled_taxonomies');

    add_settings_section('sitemap_section', 'Wat wil je tonen in de sitemap?', null, 'custom-sitemap-settings');

    add_settings_field('sitemap_post_types', 'Post types', function () {
        $enabled = get_option('custom_sitemap_enabled_post_types', []);
        foreach (get_post_types(['public' => true], 'objects') as $slug => $post_type) {
            echo '<label><input type="checkbox" name="custom_sitemap_enabled_post_types[]" value="' . esc_attr($slug) . '" ' . checked(in_array($slug, $enabled), true, false) . '> ' . esc_html($post_type->labels->name) . '</label><br>';
        }
    }, 'custom-sitemap-settings', 'sitemap_section');

    add_settings_field('sitemap_taxonomies', 'Taxonomieën', function () {
        $enabled = get_option('custom_sitemap_enabled_taxonomies', []);
        foreach (get_taxonomies(['public' => true], 'objects') as $slug => $tax) {
            echo '<label><input type="checkbox" name="custom_sitemap_enabled_taxonomies[]" value="' . esc_attr($slug) . '" ' . checked(in_array($slug, $enabled), true, false) . '> ' . esc_html($tax->labels->name) . '</label><br>';
        }
    }, 'custom-sitemap-settings', 'sitemap_section');
});

add_filter('wp_sitemaps_post_types', function ($post_types) {
    $allowed = get_option('custom_sitemap_enabled_post_types', []);
    return array_intersect_key($post_types, array_flip($allowed));
});

add_filter('wp_sitemaps_taxonomies', function ($taxonomies) {
    $allowed = get_option('custom_sitemap_enabled_taxonomies', []);
    return array_intersect_key($taxonomies, array_flip($allowed));
});

add_filter('wp_sitemaps_post_types', function ($post_types) {
    $allowed = get_option('custom_sitemap_enabled_post_types', []);
    return empty($allowed) ? $post_types : array_intersect_key($post_types, array_flip($allowed));
});

add_filter('wp_sitemaps_taxonomies', function ($taxonomies) {
    $allowed = get_option('custom_sitemap_enabled_taxonomies', []);
    return empty($allowed) ? $taxonomies : array_intersect_key($taxonomies, array_flip($allowed));
});