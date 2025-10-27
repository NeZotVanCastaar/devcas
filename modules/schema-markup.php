<?php
/* -----------------------------------------------------
 *  ADMIN UI – SCHEMA MARKUP MODULE (onder parent menu)
 * ---------------------------------------------------*/

add_action('admin_menu', function () {
    if (!current_user_can('administrator')) return;

    // Zet de pagina onder je bestaande hoofdmenu, pas 'castaar' aan indien nodig
    add_submenu_page(
        'castaar',                              // parent slug (aanpassen indien ander hoofdmenu)
        'Schema Markup',                        // page title
        'Schema Markup',                        // menu title
        'manage_options',                       // capability
        'schema-markup-settings',               // menu slug
        'schema_module_render_settings_page'    // callback
    );
});

function schema_module_render_settings_page()
{
    if (!current_user_can('manage_options')) return;

    $options = get_option('schema_module_settings', [
        'roles' => ['administrator'],
        'post_types' => ['post', 'page'],
    ]);

    if (isset($_POST['schema_module_save'])) {
        check_admin_referer('schema_module_save_action', 'schema_module_nonce');

        $roles = isset($_POST['schema_module_roles']) ? array_map('sanitize_text_field', $_POST['schema_module_roles']) : [];
        $post_types = isset($_POST['schema_module_post_types']) ? array_map('sanitize_text_field', $_POST['schema_module_post_types']) : [];

        $options = [
            'roles' => $roles ?: ['administrator'],
            'post_types' => $post_types ?: ['post', 'page'],
        ];

        update_option('schema_module_settings', $options);

        echo '<div class="updated"><p>Instellingen opgeslagen.</p></div>';
    }

    $all_roles  = wp_roles()->roles;
    $post_types = get_post_types(['public' => true], 'objects');
?>
    <div class="wrap">
        <h1>Schema Markup Instellingen</h1>
        <form method="post">
            <?php wp_nonce_field('schema_module_save_action', 'schema_module_nonce'); ?>

            <h2 class="title">Toegestane rollen</h2>
            <p>Selecteer welke gebruikersrollen de schema markup mogen invullen.</p>
            <?php foreach ($all_roles as $key => $role): ?>
                <label>
                    <input type="checkbox" name="schema_module_roles[]" value="<?php echo esc_attr($key); ?>"
                        <?php checked(in_array($key, $options['roles'], true)); ?>>
                    <?php echo esc_html($role['name']); ?>
                </label><br>
            <?php endforeach; ?>

            <h2 class="title" style="margin-top:25px;">Toegestane post types</h2>
            <p>Bij welke post types wil je het schema-veld tonen in de editor?</p>
            <?php foreach ($post_types as $slug => $obj): ?>
                <label>
                    <input type="checkbox" name="schema_module_post_types[]" value="<?php echo esc_attr($slug); ?>"
                        <?php checked(in_array($slug, $options['post_types'], true)); ?>>
                    <?php echo esc_html($obj->labels->singular_name); ?>
                </label><br>
            <?php endforeach; ?>

            <p><input type="submit" name="schema_module_save" class="button button-primary" value="Opslaan"></p>
        </form>
    </div>
<?php
}


/* -----------------------------------------------------
 *  META BOX IN DE RECHTERBALK
 * ---------------------------------------------------*/

add_action('add_meta_boxes', function () {
    $settings = get_option('schema_module_settings', [
        'roles' => ['administrator'],
        'post_types' => ['post', 'page']
    ]);

    global $post;
    if (!$post) return;

    if (!in_array($post->post_type, $settings['post_types'], true)) return;

    $user = wp_get_current_user();
    if (!array_intersect($settings['roles'], $user->roles)) return;

    add_meta_box(
        'schema_module_box',
        __('Schema Markup (JSON-LD)', 'schema-module'),
        'schema_module_render_metabox',
        $post->post_type,
        'side',
        'default'
    );
});

function schema_module_render_metabox($post)
{
    $value = get_post_meta($post->ID, '_schema_module_json', true);
    wp_nonce_field('schema_module_nonce_action', 'schema_module_nonce_field');

    $placeholder = "{\n  \"@context\": \"https://schema.org\",\n  \"@type\": \"Organization\",\n  \"name\": \"Voorbeeld\"\n}\n\n---\n\n{\n  \"@context\": \"https://schema.org\",\n  \"@type\": \"BreadcrumbList\",\n  \"itemListElement\": []\n}\n\nOF als array:\n[\n  { ... },\n  { ... }\n]";

    echo '<textarea style="width:100%;height:220px;" name="schema_module_field" placeholder="' . esc_attr($placeholder) . '">'
        . esc_textarea($value) . '</textarea>';
    echo '<p class="description">Meerdere schema’s? Gebruik <strong>---</strong> op een aparte lijn als scheiding, of plak een <strong>JSON array</strong> <code>[{...},{...}]</code>.</p>';
}

add_action('save_post', function ($post_id) {
    if (!isset($_POST['schema_module_nonce_field'])
        || !wp_verify_nonce($_POST['schema_module_nonce_field'], 'schema_module_nonce_action')) {
        return;
    }

    if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;

    if (isset($_POST['schema_module_field'])) {
        update_post_meta($post_id, '_schema_module_json', sanitize_textarea_field($_POST['schema_module_field']));
    }
});


/* -----------------------------------------------------
 *  MULTI-SCHEMA PARSER
 * ---------------------------------------------------*/

/**
 * Haalt 1..n JSON-LD blokken uit ruwe input:
 * - Ondersteunt 1 object, array [{...},{...}], meerdere blokken met '---',
 * - Stript eventueel meegeplakte <script type="application/ld+json">...</script>.
 * Retour: array van JSON strings (elk 1 object), netjes ge-encodeerd waar mogelijk.
 */
function schema_module_extract_json_blocks($raw) {
    $raw = trim((string) $raw);
    if ($raw === '') return [];

    $candidates = [];

    // A) Als er <script type="application/ld+json"> blokken in staan, pak de innerHTML
    if (preg_match_all('#<script[^>]*application/ld\+json[^>]*>(.*?)</script>#is', $raw, $m) && !empty($m[1])) {
        foreach ($m[1] as $inner) {
            $inner = trim($inner);
            if ($inner !== '') $candidates[] = $inner;
        }
    } else {
        $candidates[] = $raw;
    }

    $out = [];

    foreach ($candidates as $block) {
        $block = trim($block);

        // 1) Eenvoudig geval: geldig JSON
        $decoded = json_decode($block, true);
        if (json_last_error() === JSON_ERROR_NONE) {
            // Array? Dan elk item apart uitspuwen
            if (is_array($decoded) && array_keys($decoded) === range(0, count($decoded)-1)) {
                foreach ($decoded as $item) {
                    if (is_array($item)) {
                        $out[] = wp_json_encode($item, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
                    }
                }
            } else {
                // Enkel object
                $out[] = wp_json_encode($decoded, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
            }
            continue;
        }

        // 2) Splitsen op delimiter '---' (op een aparte lijn)
        $parts = preg_split('/^\s*---\s*$/m', $block);
        if ($parts && count($parts) > 1) {
            foreach ($parts as $p) {
                $p = trim($p);
                if ($p === '') continue;
                $d = json_decode($p, true);
                if (json_last_error() === JSON_ERROR_NONE) {
                    $out[] = wp_json_encode($d, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
                } else {
                    // laatste redmiddel: raw (voor gevorderd gebruik, bv. vooraf al geldig en bewust niet decodeerbaar)
                    $out[] = $p;
                }
            }
            continue;
        }

        // 3) Heuristiek: "}{" -> "},{", en dan in array wrappen
        $fixed   = preg_replace('/}\s*{\s*/', '},{', $block);
        $wrapped = '[' . $fixed . ']';
        $arr = json_decode($wrapped, true);
        if (json_last_error() === JSON_ERROR_NONE && is_array($arr)) {
            foreach ($arr as $item) {
                if (is_array($item)) {
                    $out[] = wp_json_encode($item, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
                }
            }
            continue;
        }

        // 4) Echt fallback: raw block
        $out[] = $block;
    }

    // Filter lege strings
    return array_values(array_filter($out, function($x){ return trim($x) !== ''; }));
}


/* -----------------------------------------------------
 *  FRONTEND: JSON-LD IN DE HEAD PLAATSEN (MEERDERE)
 * ---------------------------------------------------*/

add_action('wp_head', function () {
    // Respecteer ingestelde post types
    $settings = get_option('schema_module_settings', [
        'roles' => ['administrator'],
        'post_types' => ['post', 'page']
    ]);
    $allowed = isset($settings['post_types']) ? (array) $settings['post_types'] : ['post','page'];
    if (!is_singular($allowed)) return;

    $raw = get_post_meta(get_the_ID(), '_schema_module_json', true);
    if (!$raw) return;

    $blocks = schema_module_extract_json_blocks($raw);
    if (!$blocks) return;

    foreach ($blocks as $json) {
        echo "\n<script type=\"application/ld+json\">{$json}</script>\n";
    }
}, 1); // vroeg inladen om minifiers/cachers minder kans te geven dit te verwijderen