<?php


if (!defined('ABSPATH')) exit;

// ───── CPT ─────
function mhsm_register_snippet_post_type() {
     if (!current_user_can('administrator')) return;
    register_post_type('mhsm_snippet', [
        'labels' => [
            'name' => 'Snippets',
            'singular_name' => 'Snippet',
            'add_new' => 'Snippet toevoegen',
            'add_new_item' => 'Nieuwe snippet toevoegen',
            'edit_item' => 'Snippet bewerken',
            'new_item' => 'Nieuwe snippet',
            'view_item' => 'Bekijk snippet',
            'search_items' => 'Zoek snippets',
            'not_found' => 'Geen snippets gevonden',
            'not_found_in_trash' => 'Geen snippets in prullenbak',
        ],
        'public' => false,
        'show_ui' => true,
        'menu_icon' => 'dashicons-editor-code',
        'supports' => ['title'],
    ]);
}
add_action('init', 'mhsm_register_snippet_post_type');

// ───── Kolommen voor overzicht ─────
function mhsm_add_custom_columns($columns) {
    $columns['mhsm_active'] = 'Actief';
    return $columns;
}
add_filter('manage_mhsm_snippet_posts_columns', 'mhsm_add_custom_columns');

function mhsm_render_custom_columns($column, $post_id) {
    if ($column === 'mhsm_active') {
        $active = get_post_meta($post_id, '_mhsm_active', true);
        $url = admin_url('admin-post.php?action=mhsm_toggle_active&post_id=' . $post_id . '&_wpnonce=' . wp_create_nonce('mhsm_toggle_' . $post_id));
        $label = $active === '1' ? 'Ja' : 'Nee';
        $color = $active === '1' ? 'green' : 'red';
        echo '<a href="' . esc_url($url) . '" style="color:' . $color . '; font-weight:bold">' . esc_html($label) . '</a>';
    }
}
add_action('manage_mhsm_snippet_posts_custom_column', 'mhsm_render_custom_columns', 10, 2);

add_action('admin_post_mhsm_toggle_active', function() {
    $post_id = intval($_GET['post_id'] ?? 0);
    if (!$post_id || !current_user_can('edit_post', $post_id)) wp_die('Geen toegang');
    check_admin_referer('mhsm_toggle_' . $post_id);
    $current = get_post_meta($post_id, '_mhsm_active', true);
    update_post_meta($post_id, '_mhsm_active', $current === '1' ? '0' : '1');
    wp_redirect(admin_url('edit.php?post_type=mhsm_snippet'));
    exit;
});

// ───── Metaboxes ─────
function mhsm_add_metaboxes() {
    add_meta_box('mhsm_code', 'Snippet Codevelden', 'mhsm_render_code_boxes', 'mhsm_snippet', 'normal', 'high');
    add_meta_box('mhsm_settings', 'Instellingen', 'mhsm_render_settings_box', 'mhsm_snippet', 'side');
}
add_action('add_meta_boxes', 'mhsm_add_metaboxes');

function mhsm_render_code_boxes($post) {
    $positions = ['header' => 'Header', 'body' => 'Body', 'footer' => 'Footer'];
    $types = ['html' => 'HTML', 'css' => 'CSS', 'js' => 'JavaScript', 'php' => 'PHP'];
    $placeholders = [
        'html' => "<!-- HTML voorbeeld -->\n<div>Hallo wereld</div>",
        'css' => "/* CSS voorbeeld */\nbody { background: #f0f0f0; }",
        'js' => "// JavaScript voorbeeld\nconsole.log('Hallo wereld');",
        'php' => "// PHP voorbeeld\necho 'Hallo wereld';"
    ];

    echo '<script>document.addEventListener("DOMContentLoaded", function() {
        const positions = ["header", "body", "footer"];
        const placeholders = ' . json_encode($placeholders) . ';
        positions.forEach(function(pos) {
            const select = document.getElementById("mhsm_type_" + pos);
            const textarea = document.getElementById("mhsm_code_" + pos);
            if (select && textarea) {
                select.addEventListener("change", function() {
                    textarea.placeholder = placeholders[this.value] || "";
                });
            }
        });
    });</script>';

    foreach ($positions as $key => $label) {
        $code = get_post_meta($post->ID, "_mhsm_code_{$key}", true);
        $type = get_post_meta($post->ID, "_mhsm_type_{$key}", true) ?: 'html';
        $condition = get_post_meta($post->ID, "_mhsm_condition_{$key}", true);
        $placeholder = $placeholders[$type] ?? '';

        echo "<h4>{$label} code</h4>";
        echo "<p><select name='mhsm_type_{$key}' id='mhsm_type_{$key}'>";
        foreach ($types as $val => $labelType) {
            echo "<option value='{$val}'" . selected($type, $val, false) . ">{$labelType}</option>";
        }
        echo "</select></p>";

        echo "<textarea name='mhsm_code_{$key}' id='mhsm_code_{$key}' style='width:100%;height:150px;' placeholder='" . esc_attr($placeholder) . "'>" . esc_textarea($code) . "</textarea>";

        // Conditieveld met autocomplete
        echo "<p><label>Conditie (optioneel):</label><br>";
        echo "<input type='text' class='mhsm-condition-field' name='mhsm_condition_{$key}' id='mhsm_condition_{$key}' 
            value='" . esc_attr($condition) . "' 
            placeholder='Bijv: get_the_title() === \"Contact\"' 
            style='width:100%;' autocomplete='off' data-position='{$key}'></p>";
        echo "<div class='mhsm-suggestions' id='mhsm_suggestions_{$key}' style='border:1px solid #ccc; display:none; background:#fff; max-height:150px; overflow:auto; z-index:1000; position:relative;'></div>";
    }

    // JavaScript voor autocomplete
    echo "<script>
    document.addEventListener('DOMContentLoaded', function() {
        document.querySelectorAll('.mhsm-condition-field').forEach(function(field) {
            field.addEventListener('input', function() {
                let query = this.value.trim();
                let position = this.dataset.position;
                let suggestionBox = document.getElementById('mhsm_suggestions_' + position);
                if (query.length < 2) {
                    suggestionBox.style.display = 'none';
                    return;
                }
                fetch(ajaxurl + '?action=mhsm_get_page_titles&term=' + encodeURIComponent(query))
                    .then(res => res.json())
                    .then(data => {
                        suggestionBox.innerHTML = '';
                        data.forEach(title => {
                            let div = document.createElement('div');
                            div.textContent = title;
                            div.style.padding = '4px';
                            div.style.cursor = 'pointer';
                            div.addEventListener('click', function() {
                                field.value = 'get_the_title() === \"' + title.replace(/\"/g, '\\\"') + '\"';
                                suggestionBox.style.display = 'none';
                            });
                            suggestionBox.appendChild(div);
                        });
                        suggestionBox.style.display = data.length ? 'block' : 'none';
                    });
            });

            field.addEventListener('blur', function() {
                setTimeout(() => {
                    let box = document.getElementById('mhsm_suggestions_' + this.dataset.position);
                    if (box) box.style.display = 'none';
                }, 250);
            });
        });
    });
    </script>";
}


function mhsm_render_settings_box($post) {
    $active = get_post_meta($post->ID, '_mhsm_active', true);
    echo '<p><label><input type="checkbox" name="mhsm_active" value="1" ' . checked($active, '1', false) . '> Actief</label></p>';
}

// ───── Save ─────
function mhsm_save_snippet_meta($post_id) {
    if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;

    $positions = ['header', 'body', 'footer'];
    foreach ($positions as $pos) {
        update_post_meta($post_id, "_mhsm_code_{$pos}", wp_unslash($_POST["mhsm_code_{$pos}"] ?? ''));
        update_post_meta($post_id, "_mhsm_type_{$pos}", sanitize_text_field($_POST["mhsm_type_{$pos}"] ?? 'html'));
        update_post_meta($post_id, "_mhsm_condition_{$pos}", wp_unslash($_POST["mhsm_condition_{$pos}"] ?? ''));
    }
    update_post_meta($post_id, '_mhsm_active', isset($_POST['mhsm_active']) ? '1' : '0');
}
add_action('save_post_mhsm_snippet', 'mhsm_save_snippet_meta');

// ───── Injecties ─────
function mhsm_output_snippets($position = 'header') {
    $snippets = get_posts([
        'post_type' => 'mhsm_snippet',
        'meta_query' => [
            [
                'key' => '_mhsm_active',
                'value' => '1'
            ]
        ]
    ]);

    global $post; // Zorg dat $post beschikbaar is binnen eval()

    foreach ($snippets as $snippet) {
        $code = get_post_meta($snippet->ID, "_mhsm_code_{$position}", true);
        $type = get_post_meta($snippet->ID, "_mhsm_type_{$position}", true);
        $condition = get_post_meta($snippet->ID, "_mhsm_condition_{$position}", true);

        if (empty($code)) {
            continue;
        }

        // Conditie evaluatie (optioneel)
        if (!empty($condition)) {
            try {
                // Evalueer de PHP-conditie veilig
                if (!eval("return ({$condition});")) {
                    continue;
                }
            } catch (Throwable $e) {
                error_log("[MHSM] Fout in conditie van snippet #{$snippet->ID} ({$position}): " . $e->getMessage());
                continue;
            }
        }

        // Output startcommentaar
        echo "\n<!-- Snippet #{$snippet->ID} ({$type} in {$position}) -->\n";

        switch ($type) {
            case 'html':
                echo $code;
                break;

            case 'css':
                echo "<style>{$code}</style>";
                break;

            case 'js':
                echo "<script>{$code}</script>";
                break;

            case 'php':
                mhsm_execute_php($code, $snippet->ID, $position);
                break;
        }

        // Output eindcommentaar
        echo "\n<!-- Einde Snippet #{$snippet->ID} -->\n";
    }
}



// ───── Veilige PHP-executie met logging ─────
function mhsm_execute_php($code, $snippet_id, $position) {
    $upload_dir = wp_upload_dir();
    $tmp_dir = $upload_dir['basedir'] . '/mhsm-temp';
    if (!file_exists($tmp_dir)) wp_mkdir_p($tmp_dir);

    $tmp_file = $tmp_dir . "/snippet-{$snippet_id}-{$position}.php";
    file_put_contents($tmp_file, "<?php\n" . $code);

    $output = null;
    $return_var = null;
    exec("php -l " . escapeshellarg($tmp_file), $output, $return_var);

    if ($return_var !== 0) {
        error_log("❌ Fout in snippet #{$snippet_id} [{$position}]:\n" . implode("\n", $output));
        echo "<!-- PHP fout in snippet #{$snippet_id} (zie debug.log) -->";
        return;
    }

    try {
        include $tmp_file;
    } catch (Throwable $e) {
        error_log("❌ Runtime fout in snippet #{$snippet_id} ({$position}): " . $e->getMessage());
        echo "<!-- PHP runtime fout in snippet #{$snippet_id} -->";
    }
}

add_action('wp_head', function() { mhsm_output_snippets('header'); });
add_action('wp_body_open', function() { mhsm_output_snippets('body'); });
add_action('wp_footer', function() { mhsm_output_snippets('footer'); });

add_action('wp_ajax_mhsm_get_page_titles', function() {
    $term = sanitize_text_field($_GET['term'] ?? '');
    $pages = get_pages([
        'post_type' => 'page',
        'post_status' => 'publish',
        'suppress_filters' => false,
    ]);
    $matches = [];
    foreach ($pages as $page) {
        if (stripos($page->post_title, $term) !== false) {
            $matches[] = $page->post_title;
        }
    }
    wp_send_json(array_slice($matches, 0, 10));
});

