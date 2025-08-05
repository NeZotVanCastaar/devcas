<?php
// sitemap-controller.php

// ➤ Admin menu item toevoegen
add_action('admin_menu', function () {
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
                    <input type="checkbox" name="custom_sitemap_enabled_post_types[]" value="' . esc_attr($slug) . '" ' . checked(in_array($slug, $enabled), true, false) . '>
                    ' . esc_html($post_type->labels->name) . '
                  </label><br>';
        }
    }, 'custom-sitemap-settings', 'sitemap_section');

    // ➤ Taxonomieën veld
    add_settings_field('sitemap_taxonomies', 'Taxonomieën', function () {
        $enabled = (array) get_option('custom_sitemap_enabled_taxonomies', []);
        foreach (get_taxonomies(['public' => true], 'objects') as $slug => $tax) {
            echo '<label>
                    <input type="checkbox" name="custom_sitemap_enabled_taxonomies[]" value="' . esc_attr($slug) . '" ' . checked(in_array($slug, $enabled), true, false) . '>
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

// ➤ Posts filteren op basis van WPML-taal
add_filter('wp_sitemaps_posts_query_args', function ($args, $post_type) {
    // Probeer taal via WPML op te halen
    $current_lang = apply_filters('wpml_current_language', null);

    // Fallbacks indien taal niet beschikbaar is
    if (!$current_lang) {
        if (function_exists('wpml_get_default_language')) {
            $current_lang = wpml_get_default_language(); // meestal 'nl'
        } else {
            $current_lang = 'nl';
        }
    }

    if ($current_lang && function_exists('icl_object_id')) {
        global $wpdb;

        // Zoek vertaalde posts in juiste taal
        $translation_ids = $wpdb->get_col($wpdb->prepare(
            "SELECT element_id FROM {$wpdb->prefix}icl_translations WHERE language_code = %s AND element_type = %s",
            $current_lang,
            'post_' . $post_type
        ));

        // Beperk query op juiste taal-ID's
        if (!empty($translation_ids)) {
            $args['post__in'] = $translation_ids;
        } else {
            $args['post__in'] = [0]; // sitemap leeg als niets gevonden
        }
    }

    return $args;
}, 10, 2);
