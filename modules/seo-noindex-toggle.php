<?php

// =============== METABOX ===================
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
        ['post', 'page'],
        'side',
        'default'
    );
});

// =============== OPSLAAN ===================
add_action('save_post', function($post_id) {
    if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;

    if (isset($_POST['seo_noindex'])) {
        update_post_meta($post_id, '_seo_noindex', '1');
    } else {
        delete_post_meta($post_id, '_seo_noindex');
    }
});

// =============== FRONTEND OUTPUT ===================
add_action('wp_head', function() {
    if (is_singular()) {
        $noindex = get_post_meta(get_the_ID(), '_seo_noindex', true);
        if ($noindex === '1') {
            echo '<meta name="robots" content="noindex, follow">' . "\n";
        }
    }
});

// =============== SITEMAP FILTER ===================
add_filter('wp_sitemaps_posts_query_args', function($args, $post_type) {
    $args['meta_query'] = [
        'relation' => 'OR',
        [
            'key'     => '_seo_noindex',
            'compare' => 'NOT EXISTS',
        ],
        [
            'key'     => '_seo_noindex',
            'value'   => '1',
            'compare' => '!=',
        ],
    ];
    return $args;
}, 10, 2);

// =============== ADMIN KOLOM ===================
add_filter('manage_post_posts_columns', function($columns) {
    $columns['seo_noindex'] = 'Noindex';
    return $columns;
});
add_filter('manage_page_posts_columns', function($columns) {
    $columns['seo_noindex'] = 'Noindex';
    return $columns;
});

add_action('manage_post_posts_custom_column', function($column, $post_id) {
    if ($column === 'seo_noindex') {
        $value = get_post_meta($post_id, '_seo_noindex', true);
        echo $value === '1' ? '🚫' : '✅';
    }
}, 10, 2);
add_action('manage_page_posts_custom_column', function($column, $post_id) {
    if ($column === 'seo_noindex') {
        $value = get_post_meta($post_id, '_seo_noindex', true);
        echo $value === '1' ? '🚫' : '✅';
    }
}, 10, 2);

// =============== QUICK EDIT UI ===================
add_action('quick_edit_custom_box', function($column_name, $post_type) {
    if ($column_name !== 'seo_noindex') return;
    ?>
    <fieldset class="inline-edit-col-right">
        <div class="inline-edit-col">
            <label class="alignleft">
                <input type="checkbox" name="seo_noindex" value="1">
                <span class="checkbox-title">Noindex (voorkom indexatie)</span>
            </label>
        </div>
    </fieldset>
    <?php
}, 10, 2);

// =============== QUICK EDIT JS: CHECKBOX VULLEN ===================
// Zet checkbox correct aan of uit in Quick Edit bij openen
add_action('admin_footer-edit.php', function() {
    global $typenow;
    if (!in_array($typenow, ['post', 'page'])) return;

    ?>
    <script>
    jQuery(function($) {
        const seoNoindexData = {};

        // Verzamel data uit kolom
        $('#the-list tr').each(function() {
            const $row = $(this);
            const postId = $row.attr('id')?.replace('post-', '');
            if (!postId) return;

            const isNoindex = $row.find('.column-seo_noindex').text().trim() === '🚫';
            seoNoindexData[postId] = isNoindex;
        });

        // Hook op het openen van Quick Edit
        const $inlineEditor = inlineEditPost;
        const originalEdit = $inlineEditor.edit;

        $inlineEditor.edit = function(postId) {
            originalEdit.apply(this, arguments);

            if (typeof(postId) === 'object') {
                postId = this.getId(postId);
            }

            const $editRow = $('#edit-' + postId);
            const checked = seoNoindexData[postId] || false;

            $editRow.find('input[name="seo_noindex"]').prop('checked', checked);
        };
    });
    </script>
    <?php
});

