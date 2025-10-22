<?php
defined('ABSPATH') || exit;

/**
 * Noindex Toggle met instelbare post types & rollen
 * - Instellingenpagina: CASTAAR → Noindex
 * - Rollen bepalen wie de metabox/kolom ziet en mag opslaan
 */

// ============================
//   CONSTANTS & HELPERS
// ============================

const CASTAAR_NOINDEX_PT_OPT   = 'castaar_noindex_enabled_post_types';
const CASTAAR_NOINDEX_ROLES_OPT= 'castaar_noindex_allowed_roles';

/** Mag de huidige gebruiker de noindex-metabox/kolom zien & opslaan? */
function castaar_noindex_user_can(): bool {
    if (!is_user_logged_in()) return false;
    $allowed = get_option(CASTAAR_NOINDEX_ROLES_OPT, null);
    if (!is_array($allowed) || empty($allowed)) {
        $allowed = ['administrator']; // fallback
    }
    $user = wp_get_current_user();
    return (bool) array_intersect($user->roles ?? [], $allowed);
}

/** Geactiveerde post types (fallback = alle publieke) */
function castaar_noindex_get_enabled_post_types(): array {
    $enabled = get_option(CASTAAR_NOINDEX_PT_OPT);
    if (!is_array($enabled) || empty($enabled)) {
        return get_post_types(['public' => true], 'names');
    }
    $existing = get_post_types([], 'names');
    return array_values(array_intersect($enabled, array_keys($existing)));
}

/** Defaults zetten (loopt in admin) */
add_action('admin_init', function () {
    if (get_option(CASTAAR_NOINDEX_PT_OPT, null) === null) {
        update_option(CASTAAR_NOINDEX_PT_OPT, array_values(get_post_types(['public' => true], 'names')));
    }
    if (get_option(CASTAAR_NOINDEX_ROLES_OPT, null) === null) {
        update_option(CASTAAR_NOINDEX_ROLES_OPT, ['administrator']);
    }
});

// ============================
//   INSTELLINGENPAGINA (onder CASTAAR)
// ============================

add_action('admin_menu', function () {
    if (!current_user_can('manage_options')) return;
    add_submenu_page(
        'castaar',
        'Noindex-instellingen',
        'Noindex',
        'manage_options',
        'castaar-noindex-settings',
        'castaar_noindex_render_settings_page'
    );
});

function castaar_noindex_render_settings_page() {
    if (!current_user_can('manage_options')) return;

    if (isset($_POST['castaar_noindex_nonce']) && wp_verify_nonce($_POST['castaar_noindex_nonce'], 'castaar_noindex_save')) {
        // Post types
        $postedPT  = (array) ($_POST['castaar_noindex_post_types'] ?? []);
        $public_pt = get_post_types(['public' => true], 'names');
        $keepPT    = [];
        foreach ($postedPT as $pt) {
            $pt = sanitize_key($pt);
            if (isset($public_pt[$pt]) || in_array($pt, $public_pt, true)) $keepPT[] = $pt;
        }
        if (empty($keepPT)) {
            // leeg = fallback naar alles aan
            delete_option(CASTAAR_NOINDEX_PT_OPT);
        } else {
            update_option(CASTAAR_NOINDEX_PT_OPT, array_values(array_unique($keepPT)));
        }

        // Rollen
        $editable   = get_editable_roles();
        $postedRole = (array) ($_POST['castaar_noindex_roles'] ?? []);
        $keepRoles  = [];
        foreach ($postedRole as $r) {
            $r = sanitize_key($r);
            if (isset($editable[$r])) $keepRoles[] = $r;
        }
        update_option(CASTAAR_NOINDEX_ROLES_OPT, !empty($keepRoles) ? array_values(array_unique($keepRoles)) : ['administrator']);

        echo '<div class="notice notice-success is-dismissible"><p>Instellingen bewaard.</p></div>';
    }

    $enabled_pts   = get_option(CASTAAR_NOINDEX_PT_OPT, []);
    if (empty($enabled_pts)) $enabled_pts = get_post_types(['public' => true], 'names');
    $public_types  = get_post_types(['public' => true], 'objects');
    $allowed_roles = (array) get_option(CASTAAR_NOINDEX_ROLES_OPT, ['administrator']);
    $editable_roles= get_editable_roles();
    ?>
    <div class="wrap">
        <h1>Noindex – Instellingen</h1>
        <form method="post">
            <?php wp_nonce_field('castaar_noindex_save', 'castaar_noindex_nonce'); ?>

            <h2 class="title">Zichtbare post types (metabox & kolom)</h2>
            <table class="widefat striped" style="max-width:820px;margin-top:10px;">
                <thead><tr><th style="width:80px;">Actief</th><th>Post type</th><th>Beschrijving</th></tr></thead>
                <tbody>
                <?php foreach ($public_types as $pt => $obj): ?>
                    <tr>
                        <td>
                            <label>
                                <input type="checkbox" name="castaar_noindex_post_types[]" value="<?php echo esc_attr($pt); ?>"
                                    <?php checked(in_array($pt, $enabled_pts, true)); ?>>
                            </label>
                        </td>
                        <td><strong><?php echo esc_html($obj->labels->name ?? $pt); ?></strong> <code><?php echo esc_html($pt); ?></code></td>
                        <td><?php echo esc_html($obj->description ?? ''); ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>

            <h2 class="title" style="margin-top:24px;">Toegang (rollen)</h2>
            <p>Deze rollen zien de metabox & kolom en mogen de waarde opslaan.</p>
            <div style="display:flex;gap:24px;flex-wrap:wrap;max-width:820px;">
                <?php foreach ($editable_roles as $role_key => $role_obj): ?>
                    <label style="display:inline-block;min-width:220px;">
                        <input type="checkbox" name="castaar_noindex_roles[]" value="<?php echo esc_attr($role_key); ?>"
                            <?php checked(in_array($role_key, $allowed_roles, true)); ?>>
                        <?php echo esc_html($role_obj['name']); ?> <code><?php echo esc_html($role_key); ?></code>
                    </label>
                <?php endforeach; ?>
            </div>

            <p style="margin-top:18px;">
                <button type="submit" class="button button-primary">Bewaar</button>
            </p>
        </form>
    </div>
    <?php
}

// ============================
//   METABOX
// ============================

add_action('add_meta_boxes', function () {
    if (!castaar_noindex_user_can()) return;

    $post_types = castaar_noindex_get_enabled_post_types();
    foreach ($post_types as $post_type) {
        add_meta_box(
            'seo_noindex_toggle',
            'Indexeerbaarheid',
            function ($post) {
                if (!castaar_noindex_user_can()) {
                    echo '<p>Geen toegang.</p>';
                    return;
                }
                $noindex = get_post_meta($post->ID, '_seo_noindex', true);
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

// ============================
//   OPSLAAN METADATA
// ============================

add_action('save_post', function ($post_id) {
    if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;
    if (wp_is_post_revision($post_id)) return;
    if (!castaar_noindex_user_can()) return;

    // Nonce op metabox-scherm
    if (isset($_POST['seo_noindex_nonce'])
        && !wp_verify_nonce($_POST['seo_noindex_nonce'], 'seo_noindex_save_' . $post_id)) {
        return;
    }

    if (isset($_POST['seo_noindex']) && $_POST['seo_noindex'] == '1') {
        update_post_meta($post_id, '_seo_noindex', '1');
    } else {
        delete_post_meta($post_id, '_seo_noindex');
    }
});

// ============================
//   FRONTEND OUTPUT
// ============================

add_action('wp_head', function () {
    if (!is_singular()) return;
    $noindex = get_post_meta(get_queried_object_id(), '_seo_noindex', true);
    if ($noindex === '1') {
        echo '<meta name="robots" content="noindex, follow">' . "\n";
    }
});

// ============================
//   SITEMAP FILTER
// ============================

add_filter('wp_sitemaps_posts_query_args', function ($args, $post_type) {
    $args['meta_query'] = [
        'relation' => 'OR',
        ['key' => '_seo_noindex', 'compare' => 'NOT EXISTS'],
        ['key' => '_seo_noindex', 'value' => '1', 'compare' => '!='],
    ];
    return $args;
}, 10, 2);

// ============================
//   ADMIN KOLOMMEN
// ============================

add_action('admin_init', function () {
    if (!castaar_noindex_user_can()) return;

    $post_types = castaar_noindex_get_enabled_post_types();
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

// ============================
//   QUICK EDIT UI + PREFILL
// ============================

add_action('quick_edit_custom_box', function ($column_name, $post_type) {
    if (!castaar_noindex_user_can()) return;
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

add_action('admin_footer-edit.php', function () {
    if (!castaar_noindex_user_can()) return;

    global $typenow;
    if (!$typenow || !post_type_supports($typenow, 'title')) return;
    ?>
    <script>
    jQuery(function($) {
        const seoNoindexData = {};
        $('#the-list tr').each(function() {
            const id = $(this).attr('id');
            if (!id) return;
            const postId = id.replace('post-', '');
            const isNoindex = $(this).find('.column-seo_noindex').text().trim() === '🚫';
            seoNoindexData[postId] = isNoindex;
        });

        const originalEdit = inlineEditPost.edit;
        inlineEditPost.edit = function(postId) {
            originalEdit.apply(this, arguments);
            if (typeof postId === 'object') postId = this.getId(postId);
            const $editRow = $('#edit-' + postId);
            $editRow.find('input[name="seo_noindex"]').prop('checked', !!seoNoindexData[postId]);
        };
    });
    </script>
    <?php
});
