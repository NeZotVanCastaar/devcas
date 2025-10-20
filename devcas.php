<?php
/*
    Plugin Name: DevCas
    Plugin URI: https://github.com/NeZotVanCastaar/devcas
    Description: Een verzameling handige SEO-tools, bulk editors en custom functionaliteit.
    Version: 1.1.1
    Author: Castaar – Alec Meganck & Robbe Cooman
    Author URI: https://castaar.com
*/

defined('ABSPATH') || exit;

// ------------- Config -------------
const DEVCAS_OPTION = 'devcas_enabled_modules';

// ------------- Plugin Update Checker -------------
require plugin_dir_path(__FILE__) . 'plugin-update-checker/plugin-update-checker.php';

use YahnisElsts\PluginUpdateChecker\v5\PucFactory;

$updateChecker = PucFactory::buildUpdateChecker(
    'https://github.com/NeZotVanCastaar/devcas/',
    __FILE__,
    'devcas'
);
$updateChecker->setBranch('main');

// ------------- Helpers -------------
/**
 * Vind alle modulebestanden in /modules en geef [slug => [label, file]] terug.
 */
function devcas_get_modules(): array
{
    $dir   = plugin_dir_path(__FILE__) . 'modules/';
    $files = glob($dir . '*.php') ?: [];
    $mods  = [];

    foreach ($files as $file) {
        $slug  = basename($file, '.php'); // bijv. "custom-login"
        $label = ucwords(str_replace(['-', '_'], ' ', $slug));
        $mods[$slug] = [
            'label' => $label,
            'file'  => $file,
        ];
    }
    ksort($mods);
    return $mods;
}

/**
 * Geef de huidige geselecteerde modules (slugs) terug.
 * Als er geen optie is opgeslagen, interpreteren we dat als "alles actief" (backwards compatible).
 */
function devcas_selected_modules(): array
{
    $saved = get_option(DEVCAS_OPTION, null);
    if ($saved === null) {
        // Geen keuze gemaakt → alles actief
        return array_keys(devcas_get_modules());
    }
    return array_values(array_filter(array_map('sanitize_text_field', (array)$saved)));
}

// ------------- Modules laden -------------
add_action('plugins_loaded', function () {
    $modules = devcas_get_modules();
    $active  = devcas_selected_modules();

    foreach ($modules as $slug => $meta) {
        if (in_array($slug, $active, true)) {
            include_once $meta['file'];
        }
    }
});

// ------------- Admin: instellingenpagina -------------
add_action('admin_menu', function () {
    add_options_page(
        'DEVCAS Modules',
        'DEVCAS Modules',
        'manage_options',
        'devcas-modules',
        'devcas_render_settings_page'
    );
});

add_action('admin_init', function () {
    register_setting('devcas_settings_group', DEVCAS_OPTION, [
        'type' => 'array',
        'sanitize_callback' => function ($input) {
            $allowed = array_keys(devcas_get_modules());
            $input   = is_array($input) ? array_values($input) : [];
            $input   = array_map('sanitize_text_field', $input);
            // Sta alleen bekende slugs toe
            return array_values(array_intersect($input, $allowed));
        },
        'default' => null, // null = geen keuze opgeslagen (zie devcas_selected_modules)
        'show_in_rest' => false,
    ]);

    add_settings_section('devcas_section', 'Schakel modules in', '__return_false', 'devcas-modules');

    add_settings_field('devcas_enabled_modules_field', 'Beschikbare modules', function () {
        $modules = devcas_get_modules();
        $active  = devcas_selected_modules();

        // Toelichting
        echo '<p class="description" style="max-width:720px">';
        echo 'Vink de modules aan die je op <strong>deze site</strong> wil activeren. Laat alle vinkjes uit om niets te laden.';
        echo '</p>';

        echo '<div style="margin-top:8px">';
        foreach ($modules as $slug => $meta) {
            $checked = checked(in_array($slug, $active, true), true, false);
            echo '<label style="display:block; margin:6px 0;">';
            echo '<input type="checkbox" name="' . esc_attr(DEVCAS_OPTION) . '[]" value="' . esc_attr($slug) . '" ' . $checked . ' />';
            echo ' ' . esc_html($meta['label']) . ' <code style="opacity:.7;">' . esc_html($slug) . '.php</code>';
            echo '</label>';
        }
        echo '</div>';
    }, 'devcas-modules', 'devcas_section');
});

function devcas_render_settings_page()
{
    if (!current_user_can('manage_options')) return;
?>
    <div class="wrap">
        <h1>DEVCAS Modules</h1>
        <form method="post" action="options.php">
            <?php
            settings_fields('devcas_settings_group');
            do_settings_sections('devcas-modules');
            submit_button('Opslaan');
            ?>
        </form>
    </div>
<?php
}


register_activation_hook(__FILE__, function () {
    if (get_option(DEVCAS_OPTION, null) === null) {
        update_option(DEVCAS_OPTION, array_keys(devcas_get_modules()));
    }
});
