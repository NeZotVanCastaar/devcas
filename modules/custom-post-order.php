<?php
if (!defined('ABSPATH')) exit;

// ➤ Haal alle post types op waarvoor sortering moet werken
function cpoe_get_all_supported_post_types() {
    $args = [
        'public'  => true,
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

/**
 * ➤ Injecteer JS + CSS in de admin (🔒 enkel admins)
 */
function cpoe_admin_assets($hook) {
    // Alleen op lijstschermen en enkel voor admins
    if (!current_user_can('manage_options')) return;

    // Alleen als we ons op een ondersteund posttype bevinden
    global $typenow;
    $types = cpoe_get_all_supported_post_types();
    if (!in_array($typenow, $types, true)) return;

    // jQuery UI Sortable
    wp_enqueue_script('jquery-ui-sortable');

    // Nonce voor AJAX
    $nonce = wp_create_nonce('cpoe_save_order');

    // Inline JS
    $js = <<<JS
    jQuery(document).ready(function($) {
        var \$tbody = $('table.wp-list-table tbody');

        if (!\$tbody.length) return;

        \$tbody.sortable({
            items: 'tr',
            cursor: 'move',
            axis: 'y',
            update: function() {
                var order = [];
                \$tbody.children('tr').each(function() {
                    var id = $(this).attr('id');
                    if (id && id.indexOf('post-') === 0) {
                        order.push(id.replace('post-', ''));
                    }
                });

                $.post(ajaxurl, {
                    action: 'cpoe_save_order',
                    nonce: '{$nonce}',
                    order: order
                });
            }
        });
    });
JS;
    wp_add_inline_script('jquery-ui-sortable', $js);

    // Inline CSS via eigen handle (wp_add_inline_style vereist een enqueued handle)
    if (!wp_style_is('cpoe-admin-inline', 'enqueued')) {
        wp_register_style('cpoe-admin-inline', false);
        wp_enqueue_style('cpoe-admin-inline');
    }
    $css = <<<CSS
    tbody tr { cursor: move; }
    tbody tr:hover { background-color: #f0f8ff; }
CSS;
    wp_add_inline_style('cpoe-admin-inline', $css);
}
add_action('admin_enqueue_scripts', 'cpoe_admin_assets');

/**
 * ➤ AJAX-handler om nieuwe volgorde op te slaan (🔒 enkel admins + nonce)
 */
function cpoe_save_order() {
    if (!current_user_can('manage_options')) {
        wp_send_json_error(['message' => 'Forbidden'], 403);
    }

    check_ajax_referer('cpoe_save_order', 'nonce');

    $order = isset($_POST['order']) ? (array) $_POST['order'] : [];
    if (empty($order)) {
        wp_send_json_error(['message' => 'No order provided'], 400);
    }

    // Zet menu_order volgens de positie in de array
    foreach (array_values($order) as $menu_order => $post_id) {
        $pid = (int) $post_id;
        if ($pid > 0) {
            wp_update_post([
                'ID'         => $pid,
                'menu_order' => (int) $menu_order
            ]);
        }
    }

    wp_send_json_success();
}
add_action('wp_ajax_cpoe_save_order', 'cpoe_save_order');

/**
 * ➤ Sorteer in de admin standaard op menu_order
 */
function cpoe_force_menu_order($query) {
    if (!is_admin() || !$query->is_main_query() || $query->get('orderby')) return;

    $types = cpoe_get_all_supported_post_types();
    $post_type = $query->get('post_type') ?: 'post';

    if (in_array($post_type, $types, true)) {
        $query->set('orderby', 'menu_order');
        $query->set('order', 'ASC');
    }
}
add_action('pre_get_posts', 'cpoe_force_menu_order');
