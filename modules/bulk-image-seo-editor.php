<?php

defined('ABSPATH') || exit;

/**
 * HANG ONDER CASTAAR + ADMIN-ONLY
 */
add_action('admin_menu', function () {
    if (!current_user_can('manage_options')) return; // 🔒 enkel admins
    add_submenu_page(
        'castaar',                 // parent slug (top-level CASTAAR)
        'Bulk SEO Editor',         // page title
        'Bulk SEO Editor',         // menu title
        'manage_options',          // capability (admin-only)
        'bulk-seo-editor',         // menu slug (ongewijzigd)
        'bulk_seo_editor_page'     // callback
    );
});

/**
 * PAGINA CALLBACK
 */
function bulk_seo_editor_page() {
    if (!current_user_can('manage_options')) {
        wp_die(__('Geen toegang.'));
    }

    // Verwerking van updates
    if (!empty($_POST['bulk_seo_update']) && check_admin_referer('bulk_seo_update_action')) {
        $attachments = $_POST['attachments'] ?? [];
        if (is_array($attachments)) {
            foreach ($attachments as $attachment_id => $data) {
                $attachment_id = (int) $attachment_id;

                if (isset($data['title'])) {
                    wp_update_post([
                        'ID'         => $attachment_id,
                        'post_title' => sanitize_text_field(wp_unslash($data['title'])),
                    ]);
                }

                if (isset($data['alt'])) {
                    update_post_meta(
                        $attachment_id,
                        '_wp_attachment_image_alt',
                        sanitize_text_field(wp_unslash($data['alt']))
                    );
                }
            }
            echo '<div class="notice notice-success is-dismissible"><p>Updates saved.</p></div>';
        }
    }

    // Alle afbeeldingen ophalen
    $images = get_posts([
        'post_type'      => 'attachment',
        'post_mime_type' => 'image',
        'numberposts'    => -1,
        'post_status'    => 'inherit',
        'orderby'        => 'ID',
        'order'          => 'DESC',
    ]);

    echo '<div class="wrap"><h1>Bulk SEO Editor</h1>';
    echo '<form method="post">';
    wp_nonce_field('bulk_seo_update_action');

    echo '<table class="widefat fixed striped"><thead><tr>
        <th>Preview</th>
        <th>Title</th>
        <th>Alt Text</th>
    </tr></thead><tbody>';

    foreach ($images as $image) {
        $alt   = get_post_meta($image->ID, '_wp_attachment_image_alt', true);
        $thumb = wp_get_attachment_image($image->ID, 'thumbnail');

        echo '<tr>
            <td>' . $thumb . '</td>
            <td><input type="text" name="attachments[' . (int)$image->ID . '][title]" value="' . esc_attr($image->post_title) . '" class="regular-text"></td>
            <td><input type="text" name="attachments[' . (int)$image->ID . '][alt]" value="' . esc_attr($alt) . '" class="regular-text"></td>
        </tr>';
    }

    echo '</tbody></table>';
    echo '<p><input type="submit" name="bulk_seo_update" class="button button-primary" value="Save Changes"></p>';
    echo '</form></div>';
}
