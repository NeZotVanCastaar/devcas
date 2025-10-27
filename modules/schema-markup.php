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
    echo '<textarea style="width:100%;height:200px;" name="schema_module_field">'
        . esc_textarea($value) . '</textarea>';
    echo '<p class="description">Plak hier je JSON-LD schema code (volledig, inclusief { }).</p>';
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
 *  FRONTEND: JSON-LD IN DE HEAD PLAATSEN
 * ---------------------------------------------------*/

add_action('wp_head', function () {
    if (is_singular()) {
        $schema = get_post_meta(get_the_ID(), '_schema_module_json', true);
        if ($schema) {
            echo '<script type="application/ld+json">' . $schema . '</script>';
        }
    }
});