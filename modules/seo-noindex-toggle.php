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
const CASTAAR_NOINDEX_ROLES_OPT = 'castaar_noindex_allowed_roles';

/** Mag de huidige gebruiker de noindex-metabox/kolom zien & opslaan? */
function castaar_noindex_user_can(): bool
{
    if (!is_user_logged_in()) return false;
    $allowed = get_option(CASTAAR_NOINDEX_ROLES_OPT, null);
    if (!is_array($allowed) || empty($allowed)) {
        $allowed = ['administrator']; // fallback
    }
    $user = wp_get_current_user();
    return (bool) array_intersect($user->roles ?? [], $allowed);
}

/** Geactiveerde post types (fallback = alle publieke) */
function castaar_noindex_get_enabled_post_types(): array
{
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

function castaar_noindex_render_settings_page()
{
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
    $editable_roles = get_editable_roles();
?>
    <div class="wrap">
        <h1>Noindex – Instellingen</h1>
        <form method="post">
            <?php wp_nonce_field('castaar_noindex_save', 'castaar_noindex_nonce'); ?>

            <h2 class="title">Zichtbare post types (metabox & kolom)</h2>
            <table class="widefat striped" style="max-width:820px;margin-top:10px;">
                <thead>
                    <tr>
                        <th style="width:80px;">Actief</th>
                        <th>Post type</th>
                        <th>Beschrijving</th>
                    </tr>
                </thead>
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
            <input type="hidden" name="seo_noindex_submitted" value="1">
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

    // Check if this post type is enabled for noindex
    $enabled_post_types = castaar_noindex_get_enabled_post_types();
    $post_type = get_post_type($post_id);
    if (!in_array($post_type, $enabled_post_types, true)) return;

    // Check capability
    $post_type_obj = get_post_type_object($post_type);
    if (!current_user_can($post_type_obj->cap->edit_post, $post_id)) return;

    // CRITICAL: Only process if this save came from our metabox or quick edit
    // Check for metabox nonce OR quick edit action OR hidden field
    $is_metabox_save = isset($_POST['seo_noindex_nonce']) || isset($_POST['seo_noindex_submitted']);
    $is_quick_edit = isset($_POST['action']) && $_POST['action'] === 'inline-save';

    // If neither, this is some other save (block editor, REST API, etc.) - don't touch the meta
    if (!$is_metabox_save && !$is_quick_edit) {
        return;
    }

    // Validate metabox nonce if present
    if (isset($_POST['seo_noindex_nonce'])) {
        if (!wp_verify_nonce($_POST['seo_noindex_nonce'], 'seo_noindex_save_' . $post_id)) {
            return;
        }
    }

    // Update or delete the meta based on checkbox state
    if (isset($_POST['seo_noindex']) && $_POST['seo_noindex'] == '1') {
        update_post_meta($post_id, '_seo_noindex', '1');
    } else {
        delete_post_meta($post_id, '_seo_noindex');
    }
}, 10, 1);

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

// Exclude noindex posts from XML sitemap
add_filter('wp_sitemaps_posts_query_args', function ($args, $post_type) {
    // Initialize meta_query if it doesn't exist
    if (!isset($args['meta_query'])) {
        $args['meta_query'] = [];
    }

    // Add condition to exclude posts with noindex = 1
    $args['meta_query'][] = [
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

// ============================
//   ADMIN KOLOMMEN
// ============================

// Hook columns directly per post type instead of using admin_init
foreach (castaar_noindex_get_enabled_post_types() as $post_type) {
    add_filter("manage_{$post_type}_posts_columns", function ($columns) {
        if (!castaar_noindex_user_can()) return $columns;
        // Insert before date column if it exists
        $new_columns = [];
        foreach ($columns as $key => $value) {
            if ($key === 'date') {
                $new_columns['seo_noindex'] = 'Noindex';
            }
            $new_columns[$key] = $value;
        }
        // If date column doesn't exist, just append
        if (!isset($columns['date'])) {
            $new_columns['seo_noindex'] = 'Noindex';
        }
        return $new_columns;
    });

    add_action("manage_{$post_type}_posts_custom_column", function ($column, $post_id) {
        if (!castaar_noindex_user_can()) return;
        if ($column === 'seo_noindex') {
            $value = get_post_meta($post_id, '_seo_noindex', true);
            $status = $value === '1' ? '🚫' : '✅';
            $title = $value === '1' ? 'Niet indexeerbaar (noindex actief)' : 'Indexeerbaar';
            echo '<span title="' . esc_attr($title) . '" style="font-size:16px;cursor:help;">' . $status . '</span>';
        }
    }, 10, 2);

    // Make column sortable
    add_filter("manage_edit-{$post_type}_sortable_columns", function ($columns) {
        if (!castaar_noindex_user_can()) return $columns;
        $columns['seo_noindex'] = 'seo_noindex';
        return $columns;
    });
}

// Handle sorting
add_action('pre_get_posts', function ($query) {
    if (!is_admin() || !$query->is_main_query()) return;

    $orderby = $query->get('orderby');
    if ($orderby === 'seo_noindex') {
        $query->set('meta_key', '_seo_noindex');
        $query->set('orderby', 'meta_value');
    }
});

// ============================
//   QUICK EDIT UI + PREFILL
// ============================

add_action('quick_edit_custom_box', function ($column_name, $post_type) {
    if (!castaar_noindex_user_can()) return;
    if ($column_name !== 'seo_noindex') return;

    // Only show for enabled post types
    $enabled_post_types = castaar_noindex_get_enabled_post_types();
    if (!in_array($post_type, $enabled_post_types, true)) return;
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
    if (!$typenow) return;

    // Only for enabled post types
    $enabled_post_types = castaar_noindex_get_enabled_post_types();
    if (!in_array($typenow, $enabled_post_types, true)) return;
?>
    <script>
        jQuery(function($) {
            const seoNoindexData = {};

            // Collect noindex status from table rows
            $('#the-list tr').each(function() {
                const id = $(this).attr('id');
                if (!id) return;
                const postId = id.replace('post-', '');
                const isNoindex = $(this).find('.column-seo_noindex').text().trim() === '🚫';
                seoNoindexData[postId] = isNoindex;
            });

            // Override quick edit to populate checkbox
            const originalEdit = inlineEditPost.edit;
            inlineEditPost.edit = function(postId) {
                originalEdit.apply(this, arguments);

                if (typeof postId === 'object') {
                    postId = this.getId(postId);
                }

                const $editRow = $('#edit-' + postId);
                const $checkbox = $editRow.find('input[name="seo_noindex"]');

                if ($checkbox.length && seoNoindexData[postId] !== undefined) {
                    $checkbox.prop('checked', !!seoNoindexData[postId]);
                }
            };
        });
    </script>
<?php
});

// ============================
//   BULK EDIT (Optional enhancement)
// ============================

add_action('bulk_edit_custom_box', function ($column_name, $post_type) {
    if (!castaar_noindex_user_can()) return;
    if ($column_name !== 'seo_noindex') return;

    $enabled_post_types = castaar_noindex_get_enabled_post_types();
    if (!in_array($post_type, $enabled_post_types, true)) return;
?>
    <fieldset class="inline-edit-col-right">
        <div class="inline-edit-col">
            <label class="alignleft">
                <span class="title">Noindex</span>
                <select name="seo_noindex_bulk">
                    <option value="-1">— Niet wijzigen —</option>
                    <option value="0">Indexeerbaar (✅)</option>
                    <option value="1">Niet indexeerbaar (🚫)</option>
                </select>
            </label>
        </div>
    </fieldset>
<?php
}, 10, 2);

// Handle bulk edit save
add_action('save_post', function ($post_id) {
    // Only handle bulk edit requests
    if (!isset($_REQUEST['seo_noindex_bulk'])) return;
    if ($_REQUEST['seo_noindex_bulk'] === '-1') return;

    if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;
    if (wp_is_post_revision($post_id)) return;
    if (!castaar_noindex_user_can()) return;

    $enabled_post_types = castaar_noindex_get_enabled_post_types();
    $post_type = get_post_type($post_id);
    if (!in_array($post_type, $enabled_post_types, true)) return;

    $post_type_obj = get_post_type_object($post_type);
    if (!current_user_can($post_type_obj->cap->edit_post, $post_id)) return;

    $value = sanitize_text_field($_REQUEST['seo_noindex_bulk']);
    if ($value === '1') {
        update_post_meta($post_id, '_seo_noindex', '1');
    } elseif ($value === '0') {
        delete_post_meta($post_id, '_seo_noindex');
    }
}, 5, 1); // Priority 5 to run before main save_post handler
