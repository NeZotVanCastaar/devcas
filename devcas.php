<?php
/*
    Plugin Name: DevCas
    Plugin URI: https://github.com/NeZotVanCastaar/devcas
    Description: Een verzameling handige SEO-tools, bulk editors en custom functionaliteit.
    Version: 1.1.0
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
function devcas_get_modules(): array {
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
 * Als er geen optie is opgeslagen, interpreteren we dat als "alles actief".
 */
function devcas_selected_modules(): array {
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

// ------------- CASTAAR assets & logo helpers -------------
if (!function_exists('castaar_logo_url')) {
    function castaar_logo_url() {
        // Dit is je header/brand gif; mag blijven.
        return 'https://castaar.com/dev/castaar.gif';
    }
}
function devcas_icon_url_svg() {
    // Lokaal icoon in de plugin: /assets/icon.svg
    return plugin_dir_url(__FILE__) . 'assets/icon.svg';
}

// ------------- Overzichtspagina (zonder witte card) -------------
function devcas_castaar_dashboard() {
    if (!current_user_can('manage_options')) return;

    $logo = esc_url(castaar_logo_url());
    ?>
    <div class="wrap">
    
            <img src="<?php echo $logo; ?>" alt="Castaar" style="height:50px;"> 
  

        <p>Welkom! Beheer hieronder welke <strong>modules</strong> actief zijn op deze site.</p>

        <form method="post" action="options.php" style="margin-top:14px;">
            <?php
            // De sectietitel "Schakel modules in" komt van de add_settings_section hieronder.
            settings_fields('devcas_settings_group');
            do_settings_sections('devcas-modules'); // hergebruik van dezelfde settings "page"
            submit_button('Opslaan');
            ?>
        </form>
    </div>
    <?php
}

// ------------- Admin menu -------------
add_action('admin_menu', function () {
    if (!current_user_can('manage_options')) return;

    // Top-level CASTAAR (icoon tonen als masked SVG die currentColor volgt)
    add_menu_page(
        'CASTAAR',
        'CASTAAR',
        'manage_options',
        'castaar',
        'devcas_castaar_dashboard',
        devcas_icon_url_svg(), // we overschrijven de render via CSS/mask zodat kleur = currentColor
        59
    );

    // Overzicht submenu
    add_submenu_page(
        'castaar',
        'Overzicht',
        'Overzicht',
        'manage_options',
        'castaar',
        'devcas_castaar_dashboard'
    );
}, 5);

// ------------- Icoon styling: currentColor + perfecte uitlijning -------------
add_action('admin_head', function () {
    if (!current_user_can('manage_options')) return;

    $icon_url = esc_url( devcas_icon_url_svg() );
    echo '<style>
        /* verberg het <img> in het menu; we tekenen het icoon via een mask die currentColor volgt */
        #adminmenu #toplevel_page_castaar .wp-menu-image img { display:none !important; }

        /* zelfde box als dashicons en mooi gecentreerd */
        #adminmenu #toplevel_page_castaar .wp-menu-image{
            width: 36px;
            display:flex; align-items:center; justify-content:center;
        }

        /* icoon tekent in de tekstkleur (currentColor).
           WP zet bij actief/hover automatisch color:#fff; het icoon volgt dus mee. */
        #adminmenu #toplevel_page_castaar .wp-menu-image::before{
            content:"";
            display:block;
            width:18px; height:18px;            /* schaal gelijk aan WP-icoontjes */
            background-color: currentColor;
            -webkit-mask-image: url(' . $icon_url . ');
                    mask-image: url(' . $icon_url . ');
            -webkit-mask-repeat:no-repeat;        mask-repeat:no-repeat;
            -webkit-mask-position:center;         mask-position:center;
            -webkit-mask-size:contain;            mask-size:contain;
        }

        /* Actief item: gouden achtergrond, witte tekst + icoon (icon volgt color) */
        #adminmenu #toplevel_page_castaar.current > a.menu-top,
        #adminmenu #toplevel_page_castaar.wp-has-current-submenu > a.menu-top{
            background:#C1A360 !important; color:#fff !important;
        }
    </style>';
});

// ------------- Settings voor modules (zelfde secties, maar getoond op de overzichtspagina) -------------
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

    // Sectietitel die jij wil behouden
    add_settings_section('devcas_section', 'Modules inschakelen', '__return_false', 'devcas-modules');

    add_settings_field('devcas_enabled_modules_field', 'Beschikbare modules', function () {
        $modules = devcas_get_modules();
        $active  = devcas_selected_modules();

        echo '<p class="description" style="max-width:720px">
                Vink de modules aan die je op <strong>deze site</strong> wil activeren. Laat alle vinkjes uit om niets te laden.
              </p>';

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

// ------------- Activatie: standaard alles actief -------------
register_activation_hook(__FILE__, function () {
    if (get_option(DEVCAS_OPTION, null) === null) {
        update_option(DEVCAS_OPTION, array_keys(devcas_get_modules()));
    }
});
