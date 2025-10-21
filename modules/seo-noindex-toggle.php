<?php
defined('ABSPATH') || exit;

// =============== METABOX (alleen voor admins) ===================
add_action('add_meta_boxes', function () {
    if (!current_user_can('manage_options')) return; // 🔒 admin-only

    $post_types = get_post_types(['public' => true]);
    foreach ($post_types as $post_type) {
        add_meta_box(
            'seo_noindex_toggle',
            'Indexeerbaarheid',
            function ($post) {
                if (!current_user_can('manage_options')) {
                    echo '<p>Geen toegang.</p>';
                    return;
                }
                $noindex = get_post_meta($post->ID, '_seo_noindex', true);
                // Nonce
                wp_nonce_field('seo_noindex_save_' . $post->ID, 'seo_noindex_nonce');
                ?>
                <label>
                    <input type="checkbox" name="seo_noindex" value="1" <?php checked($noindex, '1'); ?> />
                    <strong>Voorkom indexatie</strong> door zoekmachines (voegt <code>noindex</code> toe)
                </label>
                <?php
            },
            $post_type,
            'side',
            'default'
        );
    }
});

// =============== OPSLAAN METADATA ===================
add_action('save_post', function ($post_id) {
    // Autosave / revision skip
    if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;
    if (wp_is_post_revision($post_id)) return;

    // Capability check
    if (!current_user_can('manage_options')) return;

    // Nonce check (alleen op schermen met de metabox; Quick Edit heeft geen nonce van ons — we gate’en op capability)
    if (isset($_POST['seo_noindex_nonce'])) {
        if (!wp_verify_nonce($_POST['seo_noindex_nonce'], 'seo_noindex_save_' . $post_id)) {
            return;
        }
    }

    // Opslaan
    if (isset($_POST['seo_noindex']) && $_POST['seo_noindex'] == '1') {
        update_post_meta($post_id, '_seo_noindex', '1');
    } else {
        delete_post_meta($post_id, '_seo_noindex');
    }
});

// =============== FRONTEND OUTPUT ===================
add_action('wp_head', function () {
    if (is_singular()) {
        $noindex = get_post_meta(get_queried_object_id(), '_seo_noindex', true);
        if ($noindex === '1') {
            echo '<meta name="robots" content="noindex, follow">' . "\n";
        }
    }
});

// =============== SITEMAP FILTER ===================
add_filter('wp_sitemaps_posts_query_args', function ($args, $post_type) {
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

// =============== ADMIN KOLOMMEN (alleen voor admins) ===================
add_action('admin_init', function () {
    if (!current_user_can('manage_options')) return; // 🔒

    $post_types = get_post_types(['public' => true]);

    foreach ($post_types as $post_type) {
        add_filter("manage_{$post_type}_posts_columns", function ($columns) {
            $columns['seo_noindex'] = 'Noindex';
            return $columns;
        });

        add_action("manage_{$post_type}_posts_custom_column", function ($column, $post_id) {
            if ($column === 'seo_noindex') {
                $value = get_post_meta($post_id, '_seo_noindex', true);
                echo $value === '1' ? '🚫' : '✅';
            }
        }, 10, 2);
    }
});

// =============== QUICK EDIT UI (alleen voor admins) ===================
add_action('quick_edit_custom_box', function ($column_name, $post_type) {
    if (!current_user_can('manage_options')) return; // 🔒
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

// =============== QUICK EDIT JS: CHECKBOX VULLEN (alleen voor admins) ===================
add_action('admin_footer-edit.php', function () {
    if (!current_user_can('manage_options')) return; // 🔒

    global $typenow;
    if (!$typenow || !post_type_supports($typenow, 'title')) return;
    ?>
    <script>
    jQuery(function($) {
        const seoNoindexData = {};
        $('#the-list tr').each(function() {
            const $row = $(this);
            const id = $row.attr('id');
            if (!id) return;
            const postId = id.replace('post-', '');
            const isNoindex = $row.find('.column-seo_noindex').text().trim() === '🚫';
            seoNoindexData[postId] = isNoindex;
        });

        const originalEdit = inlineEditPost.edit;
        inlineEditPost.edit = function(postId) {
            originalEdit.apply(this, arguments);
            if (typeof postId === 'object') postId = this.getId(postId);
            const $editRow = $('#edit-' + postId);
            const checked = !!seoNoindexData[postId];
            $editRow.find('input[name="seo_noindex"]').prop('checked', checked);
        };
    });
    </script>
    <?php
});
