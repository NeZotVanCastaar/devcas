<?php
/**
 * Plugin Name: Bulk Image SEO Editor
 * Description: Quickly edit SEO fields (title and alt text) of images from the media library.
 * Version: 1.0
 * Author: Alec Meganck
 */

defined('ABSPATH') || exit;

add_action('admin_menu', function() {
    add_media_page(
        'Bulk SEO Editor',
        'Bulk SEO Editor',
        'upload_files',
        'bulk-seo-editor',
        'bulk_seo_editor_page'
    );
});

function bulk_seo_editor_page() {
    if (!current_user_can('upload_files')) {
        wp_die(__('You do not have sufficient permissions to access this page.'));
    }

    if (!empty($_POST['bulk_seo_update']) && check_admin_referer('bulk_seo_update_action')) {
        foreach ($_POST['attachments'] as $attachment_id => $data) {
            $attachment_id = (int) $attachment_id;

            if (isset($data['title'])) {
                wp_update_post([
                    'ID' => $attachment_id,
                    'post_title' => sanitize_text_field($data['title']),
                ]);
            }

            if (isset($data['alt'])) {
                update_post_meta($attachment_id, '_wp_attachment_image_alt', sanitize_text_field($data['alt']));
            }
        }

        echo '<div class="notice notice-success is-dismissible"><p>Updates saved.</p></div>';
    }

    $images = get_posts([
        'post_type' => 'attachment',
        'post_mime_type' => 'image',
        'numberposts' => -1,
        'post_status' => 'inherit',
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
        $alt = get_post_meta($image->ID, '_wp_attachment_image_alt', true);
        $thumb = wp_get_attachment_image($image->ID, 'thumbnail');

        echo '<tr>
            <td>' . $thumb . '</td>
            <td><input type="text" name="attachments[' . $image->ID . '][title]" value="' . esc_attr($image->post_title) . '" class="regular-text"></td>
            <td><input type="text" name="attachments[' . $image->ID . '][alt]" value="' . esc_attr($alt) . '" class="regular-text"></td>
        </tr>';
    }

    echo '</tbody></table>';
    echo '<p><input type="submit" name="bulk_seo_update" class="button button-primary" value="Save Changes"></p>';
    echo '</form></div>';
}