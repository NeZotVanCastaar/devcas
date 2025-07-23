<?php
/**
 * Plugin Name: SEO Noindex Toggle
 * Description: Voeg een optie toe om per pagina of bericht 'noindex' in te schakelen.
 * Author: Alec Meganck
 * Version: 1.0
 */

add_action('add_meta_boxes', function() {
    add_meta_box(
        'seo_noindex_toggle',
        'Indexeerbaarheid',
        function($post) {
            $noindex = get_post_meta($post->ID, '_seo_noindex', true);
            ?>
            <label>
                <input type="checkbox" name="seo_noindex" value="1" <?php checked($noindex, '1'); ?> />
                <strong>Voorkom indexatie</strong> door zoekmachines (voegt <code>noindex</code> toe)
            </label>
            <?php
        },
        ['post', 'page'], // eventueel uitbreiden met je eigen CPT's
        'side',
        'default'
    );
});

add_action('save_post', function($post_id) {
    if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;

    if (isset($_POST['seo_noindex'])) {
        update_post_meta($post_id, '_seo_noindex', '1');
    } else {
        delete_post_meta($post_id, '_seo_noindex');
    }
});

add_action('wp_head', function() {
    if (is_singular()) {
        $noindex = get_post_meta(get_the_ID(), '_seo_noindex', true);
        if ($noindex === '1') {
            echo '<meta name="robots" content="noindex, follow">' . "\n";
        }
    }
});