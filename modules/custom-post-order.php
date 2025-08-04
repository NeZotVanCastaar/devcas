<?php
/**
 * Plugin Name: Custom Post Order Everywhere
 * Description: Maak alle post types sorteerbaar via drag & drop in de admin.
 * Author: Alec Meganck
 */

if (!defined('ABSPATH')) exit;

// ➤ Haal alle post types op waarvoor sortering moet werken
function cpoe_get_all_supported_post_types() {
    $args = [
        'public' => true,
        'show_ui' => true,
    ];
    $post_types = get_post_types($args, 'names');
    unset($post_types['attachment']); // 'attachment' uitsluiten
    return $post_types;
}

// ➤ Zorg dat menu_order beschikbaar is voor alle ondersteunde post types
add_action('init', function() {
    foreach (cpoe_get_all_supported_post_types() as $type) {
        add_post_type_support($type, 'page-attributes');
    }
});

// ➤ Injecteer JS + CSS in de admin
function cpoe_admin_assets($hook) {
    global $typenow;
    $types = cpoe_get_all_supported_post_types();

    if (!in_array($typenow, $types)) return;

    wp_enqueue_script('jquery-ui-sortable');

    $js = <<<JS
    jQuery(document).ready(function($) {
        var \$tbody = $('table.wp-list-table tbody');

        \$tbody.sortable({
            items: 'tr',
            cursor: 'move',
            axis: 'y',
            update: function() {
                var order = [];
                \$tbody.children('tr').each(function() {
                    var id = $(this).attr('id');
                    if (id && id.startsWith('post-')) {
                        order.push(id.replace('post-', ''));
                    }
                });

                $.post(ajaxurl, {
                    action: 'cpoe_save_order',
                    order: order
                });
            }
        });
    });
    JS;
    wp_add_inline_script('jquery-ui-sortable', $js);

    $css = <<<CSS
    tbody tr {
        cursor: move;
    }
    tbody tr:hover {
        background-color: #f0f8ff;
    }
    CSS;
    wp_add_inline_style('wp-admin', $css);
}
add_action('admin_enqueue_scripts', 'cpoe_admin_assets');

// ➤ AJAX-handler om nieuwe volgorde op te slaan
function cpoe_save_order() {
    if (!current_user_can('edit_posts') || !isset($_POST['order'])) {
        wp_send_json_error();
    }

    foreach ($_POST['order'] as $menu_order => $post_id) {
        wp_update_post([
            'ID' => intval($post_id),
            'menu_order' => intval($menu_order)
        ]);
    }

    wp_send_json_success();
}
add_action('wp_ajax_cpoe_save_order', 'cpoe_save_order');

// ➤ Sorteer in de admin standaard op menu_order
function cpoe_force_menu_order($query) {
    if (!is_admin() || !$query->is_main_query() || $query->get('orderby')) return;

    $types = cpoe_get_all_supported_post_types();
    $post_type = $query->get('post_type') ?: 'post';

    if (in_array($post_type, $types)) {
        $query->set('orderby', 'menu_order');
        $query->set('order', 'ASC');
    }
}
add_action('pre_get_posts', 'cpoe_force_menu_order');
