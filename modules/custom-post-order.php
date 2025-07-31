<?php

// ➤ Activatie: standaard post types toevoegen
function cpoe_activate() {
    add_option('cpoe_post_types', ['post', 'page']);
}
register_activation_hook(__FILE__, 'cpoe_activate');

// ➤ Scripts & styles injecteren in admin
function cpoe_admin_assets($hook) {
    if ($hook !== 'edit.php') return;

    // jQuery UI laden
    wp_enqueue_script('jquery-ui-sortable');

    // JS inline toevoegen
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
                    order.push($(this).attr('id').replace('post-', ''));
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

    // CSS inline toevoegen
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

// ➤ AJAX: volgorde opslaan
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

// ➤ Standaard sortering wijzigen naar menu_order
function cpoe_force_menu_order($query) {
    if (!is_admin() || !$query->is_main_query() || $query->get('orderby') || $query->get('post_type') === 'attachment') return;

    $types = get_option('cpoe_post_types', []);
    $post_type = $query->get('post_type') ?: 'post';

    if (in_array($post_type, $types)) {
        $query->set('orderby', 'menu_order');
        $query->set('order', 'ASC');
    }
}
add_action('pre_get_posts', 'cpoe_force_menu_order');
