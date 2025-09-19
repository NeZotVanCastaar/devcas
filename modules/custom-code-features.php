<?php


defined('ABSPATH') || exit;

// Verberg conceptpagina's uit het WP menu
add_filter('wp_get_nav_menu_items', function($items) {
    return array_filter($items, function($item) {
        return get_post_status($item->object_id) !== 'draft';
    });
});

// Voeg meta box toe om hoofdpagina te selecteren
add_action('add_meta_boxes', function() {
    add_meta_box('cpt_head_page', 'Koppel hoofdpagina', 'render_cpt_head_page_box', 'your_cpt_slug', 'side', 'default');
});

function render_cpt_head_page_box($post) {
    $selected = get_post_meta($post->ID, '_linked_head_page', true);
    $pages = get_pages(['post_status' => ['publish']]);
    echo '<select name="linked_head_page" style="width:100%">';
    echo '<option value="">— Geen —</option>';
    foreach ($pages as $page) {
        echo '<option value="' . esc_attr($page->ID) . '" ' . selected($selected, $page->ID, false) . '>' . esc_html($page->post_title) . '</option>';
    }
    echo '</select>';
}

// Opslaan gekoppelde hoofdpagina
add_action('save_post', function($post_id) {
    if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;
    if (isset($_POST['linked_head_page'])) {
        update_post_meta($post_id, '_linked_head_page', intval($_POST['linked_head_page']));
    }
});

// Menu-item actief maken als gekoppelde hoofdpagina actief is
add_filter('nav_menu_css_class', function($classes, $item) {
    if (!is_singular('your_cpt_slug')) return $classes;

    $linked = get_post_meta(get_the_ID(), '_linked_head_page', true);
    if ($linked && $item->object_id == $linked) {
        $classes[] = 'current-menu-item';
    }

    return $classes;
}, 10, 2);

// CSS laden
add_action('wp_enqueue_scripts', function() {
    wp_enqueue_style('devcas-default-style', plugin_dir_url(__FILE__) . '../assets/style.css');
});

// Duplicate post link in admin lijst
add_filter('post_row_actions', 'devcas_duplicate_link', 10, 2);
add_filter('page_row_actions', 'devcas_duplicate_link', 10, 2);

function devcas_duplicate_link($actions, $post) {
    if (current_user_can('edit_posts') && $post->post_status === 'publish') {
        $url = wp_nonce_url(
            admin_url('admin.php?action=devcas_duplicate_post&post=' . $post->ID),
            basename(__FILE__),
            'duplicate_nonce'
        );
        $actions['duplicate'] = '<a href="' . esc_url($url) . '" title="Dupliceer deze post">Dupliceren</a>';
    }
    return $actions;
}

<?php
// ===============================
//  DUPLICATE POST/LINK + ACTION
//  (DB-level meta copy; Elementor-proof)
// ===============================

// "Dupliceren" in de lijstacties (Posts & Pagina's)
add_filter('post_row_actions', 'devcas_duplicate_link', 10, 2);
add_filter('page_row_actions', 'devcas_duplicate_link', 10, 2);
function devcas_duplicate_link($actions, $post) {
    if (current_user_can('edit_posts') && in_array($post->post_status, ['publish','private','pending','draft'], true)) {
        $url = wp_nonce_url(
            admin_url('admin.php?action=devcas_duplicate_post&post=' . $post->ID),
            basename(__FILE__),
            'duplicate_nonce'
        );
        $actions['duplicate'] = '<a href="' . esc_url($url) . '" title="Dupliceer deze post">Dupliceren</a>';
    }
    return $actions;
}

// De eigenlijke duplicate-actie
add_action('admin_action_devcas_duplicate_post', function () {
    if (
        empty($_GET['post']) ||
        !current_user_can('edit_posts') ||
        empty($_GET['duplicate_nonce']) ||
        !wp_verify_nonce($_GET['duplicate_nonce'], basename(__FILE__))
    ) {
        wp_die('Geen toegang.');
    }

    $post_id = absint($_GET['post']);
    $post    = get_post($post_id);
    if (!$post) wp_die('Bericht niet gevonden.');

    // 1) Nieuwe post aanmaken (slug niet forceren → WP maakt uniek)
    $new_post_args = [
        'post_title'    => $post->post_title . ' (kopie)',
        'post_content'  => $post->post_content,   // laat staan; Elementor rendert uit meta, maar content kan shortcodes bevatten
        'post_status'   => 'draft',
        'post_type'     => $post->post_type,
        'post_excerpt'  => $post->post_excerpt,
        'post_author'   => get_current_user_id(),
        'post_parent'   => $post->post_parent,
        'menu_order'    => $post->menu_order,
        'post_password' => $post->post_password,
    ];
    $new_post_id = wp_insert_post($new_post_args);

    // 2) Featured image kopiëren
    if ($thumb_id = get_post_thumbnail_id($post_id)) {
        set_post_thumbnail($new_post_id, $thumb_id);
    }

    // 3) Taxonomieën kopiëren
    $taxonomies = get_object_taxonomies($post->post_type);
    foreach ($taxonomies as $taxonomy) {
        $terms = wp_get_object_terms($post_id, $taxonomy, ['fields' => 'ids']);
        if (!empty($terms) && !is_wp_error($terms)) {
            wp_set_object_terms($new_post_id, $terms, $taxonomy, false);
        }
    }

    // 4) ALLE meta 1-op-1 kopiëren op DB-niveau (voorkomt serialize/slash issues)
    global $wpdb;
    $meta_table = $wpdb->postmeta;

    // Keys die we bewust NIET klonen
    $skip_keys = [
        '_edit_lock',
        '_edit_last',
        '_wp_old_slug',
        // Eventueel: '_thumbnail_id' (maar we hebben die hierboven al gezet)
    ];

    // Haal alle meta-rows van de bron op (exact zoals in DB)
    $rows = $wpdb->get_results(
        $wpdb->prepare("SELECT meta_key, meta_value FROM {$meta_table} WHERE post_id = %d", $post_id),
        ARRAY_A
    );

    if ($rows) {
        foreach ($rows as $row) {
            $key = $row['meta_key'];
            if (in_array($key, $skip_keys, true)) {
                continue;
            }
            // _thumbnail_id laten we overslaan, want die is net al correct gezet
            if ($key === '_thumbnail_id') {
                continue;
            }

            // Insert exact zoals opgeslagen (geen (un)serialize/slash/encoding uitvoeren!)
            $wpdb->insert(
                $meta_table,
                [
                    'post_id'    => $new_post_id,
                    'meta_key'   => $key,
                    'meta_value' => $row['meta_value'],
                ],
                ['%d','%s','%s']
            );
        }
    }

    // Zorg dat Elementor edit mode er is als de bron Elementor gebruikte
    $src_elementor_data = get_post_meta($post_id, '_elementor_data', true);
    if (!empty($src_elementor_data)) {
        // Als _elementor_edit_mode ontbreekt, forceer 'builder'
        $edit_mode = get_post_meta($new_post_id, '_elementor_edit_mode', true);
        if (!$edit_mode) {
            update_post_meta($new_post_id, '_elementor_edit_mode', 'builder');
        }
    }

    // 5) GEEN attachments herparenten (anders problemen bij het origineel)
    // Media blijven via URLs refereren.

    // 6) Elementor CSS regenereren (als Elementor actief is)
    if (class_exists('\Elementor\Core\Files\CSS\Post')) {
        try { \Elementor\Core\Files\CSS\Post::create($new_post_id)->update(); } catch (\Throwable $e) {}
    }
    if (class_exists('\Elementor\Plugin')) {
        try { \Elementor\Plugin::$instance->files_manager->clear_cache(); } catch (\Throwable $e) {}
    }

    // 7) Naar de editor van de kopie
    wp_redirect(admin_url('post.php?action=edit&post=' . $new_post_id));
    exit;
});



// SVG's toelaten in mediabibliotheek
add_filter('upload_mimes', function($mimes) {
    $mimes['svg'] = 'image/svg+xml';
    return $mimes;
});

// SVG veilig tonen in media weergave
add_filter('wp_check_filetype_and_ext', function($data, $file, $filename, $mimes) {
    $ext = pathinfo($filename, PATHINFO_EXTENSION);
    if ($ext === 'svg') {
        $data['ext']  = 'svg';
        $data['type'] = 'image/svg+xml';
    }
    return $data;
}, 10, 4);


// AOS scripts en styles laden
add_action('wp_enqueue_scripts', function() {
    // CSS eerst
    wp_enqueue_style('aos-css', 'https://cdn.jsdelivr.net/npm/aos@2.3.4/dist/aos.css', [], '2.3.4');

    // JS met handle zodat we inline script erna kunnen injecteren
    wp_enqueue_script('aos-js', 'https://cdn.jsdelivr.net/npm/aos@2.3.4/dist/aos.js', [], '2.3.4', true);

    // Voeg AOS.init toe nadat het script geladen is
    wp_add_inline_script('aos-js', 'document.addEventListener("DOMContentLoaded", function() {
        AOS.init({
            duration: 1000 // animaties duren 1000ms
        });
    });');
});

/**
 * Remove "-scaled" from image filenames while keeping scaling active
 */
add_filter('wp_unique_filename', function ($filename, $ext, $dir) {
    // Remove '-scaled' from filenames
    $filename = str_replace('-scaled', '', $filename);
    return $filename;
}, 10, 3);

function shortcode_year() {
    return date('Y');
}
add_shortcode('year', 'shortcode_year');

function show_only_published_everywhere_in_admin($query) {
    if (
      is_admin() &&
      $query->is_main_query() &&
      !isset($_GET['post_status']) &&
      $query->get('post_type')
    ) {
      $query->set('post_status', 'publish');
    }
  }
  add_action('pre_get_posts', 'show_only_published_everywhere_in_admin');