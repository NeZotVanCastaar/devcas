<?php

add_action('login_head', 'castaar_custom_login_styles');
add_action('admin_head', 'castaar_custom_admin_styles');
add_action('admin_bar_menu', 'castaar_adminbar_logo', 11);
add_filter('login_headerurl', 'castaar_login_logo_url');
add_filter('login_headertext', 'castaar_login_logo_target');
add_filter('admin_footer_text', 'castaar_custom_admin_footer');
add_filter('update_footer', '__return_empty_string', 999); // verwijdert versie

// 🖼️ Externe logo
function castaar_logo_url() {
    return 'https://castaar.com/dev/castaar.gif';
}

// 🔐 LOGIN: logo + knop
function castaar_custom_login_styles() {
    $logo = esc_url(castaar_logo_url());
    echo "
    <style>
        body.login #login h1 a {
            background-image: url('$logo');
            background-size: contain;
            background-repeat: no-repeat;
            background-position: center;
            width: 100%;
            height: 120px;
            display: block;
        }

        .login form input[type='submit'] {
            background-color: #C1A360 !important;
            border: none !important;
            color: #fff !important;
   
     
            cursor: pointer;
        }

        .login form input[type='submit']:hover {
            background-color: #b6974e !important;
        }
    </style>";
}

// 🔗 Login logo link
function castaar_login_logo_url() {
    return 'https://castaar.com';
}
function castaar_login_logo_target($text) {
    echo '<script>
        document.addEventListener("DOMContentLoaded", function() {
            const logoLink = document.querySelector("#login h1 a");
            if (logoLink) {
                logoLink.setAttribute("target", "_blank");
                logoLink.setAttribute("rel", "noopener");
            }
        });
    </script>';
    return $text;
}

// 🖥️ ADMIN: knoppen, actieve tab, logo injectie
function castaar_custom_admin_styles() {
    $logo = esc_url(castaar_logo_url());
    echo "
    <style>
        /* Gouden knoppen */
        .wp-core-ui .button-primary,
        .wp-core-ui .button-primary:hover,
        .wp-core-ui .button-primary:focus {
            background-color: #C1A360 !important;
            border-color: #C1A360 !important;
            color: #fff !important;
            box-shadow: none !important;
        }

        /* Actieve menu tab goud */
        #adminmenu .wp-has-current-submenu > a,
        #adminmenu .current a.menu-top {
            background-color: #C1A360 !important;
            color: #fff !important;
        }

    </style>";
}

// 🔝 Adminbar Castaar-logo
function castaar_adminbar_logo($wp_admin_bar) {
    $logo = esc_url(castaar_logo_url());
    $wp_admin_bar->remove_node('wp-logo');
    $wp_admin_bar->add_node(array(
        'id'    => 'custom-logo',
        'title' => '<img src="' . $logo . '" style="height:20px; vertical-align:middle;" alt="Castaar" />',
        'href'  => 'https://castaar.com',
        'meta'  => array('title' => 'Website by Castaar', 'target' => '_blank'),
    ));
}

// 🔻 Footertekst aanpassen
function castaar_custom_admin_footer() {
    $logo = esc_url(castaar_logo_url());
    return '<a href="https://castaar.com" target="_blank" style="text-decoration: none;">
	<span style="color: #C1A360; font-weight: bold;">Website by </span>
                <img src="' . $logo . '" alt="Castaar" style="height: 14px; vertical-align: middle; margin-right: 5px;">
                
            </a>';
}

// ➕ Voeg je eigen widget toe
add_action('wp_dashboard_setup', 'castaar_dashboard_widget');
function castaar_dashboard_widget() {
    wp_add_dashboard_widget(
        'castaar_info_widget',
        'Castaar',
        'castaar_render_dashboard_widget'
    );
}
function castaar_render_dashboard_widget() {
    $logo = esc_url('https://castaar.com/dev/castaar.gif');
    echo '
    <div style="position: relative; min-height: 120px; font-size: 14px; line-height: 1.6; padding-right: 100px;">
        <div>
            <p>Heb je vragen of hulp nodig bij je site? Contacteer ons gerust.</p>
            <p>
                <strong>📞</strong> <a href="tel:+3254255178">+32 (0)54 255 178</a><br>
                <strong>✉️</strong> <a href="mailto:info@castaar.com">info@castaar.com</a><br>
                <strong>🌐</strong> <a href="https://castaar.com" target="_blank">castaar.com</a><br>
            </p>
        </div>
        <a href="https://castaar.com" target="_blank" style="position: absolute; bottom: 0; right: 0;">
            <img src="' . $logo . '" alt="Castaar" style="height: 30px;" />
        </a>
    </div>';
}

// ✅ Verwijder alle andere dashboard-widgets behalve Castaar + Site Kit
add_action('wp_dashboard_setup', 'castaar_exclusive_dashboard_widgets', 999);
function castaar_exclusive_dashboard_widgets() {
    global $wp_meta_boxes;

    // Widgets behouden (Castaar + Google Site Kit)
    $castaar_widget = $wp_meta_boxes['dashboard']['normal']['core']['castaar_info_widget'] ?? [];
    $sitekit_widget = $wp_meta_boxes['dashboard']['normal']['core']['google_dashboard_widget'] ?? [];

    // Reset alle widgets
    $wp_meta_boxes['dashboard'] = [
        'normal' => ['core' => []],
        'side'   => ['core' => []],
    ];

    // Zet gewenste widgets terug
    if (!empty($castaar_widget)) {
        $wp_meta_boxes['dashboard']['normal']['core']['castaar_info_widget'] = $castaar_widget;
    }
    if (!empty($sitekit_widget)) {
        $wp_meta_boxes['dashboard']['normal']['core']['google_dashboard_widget'] = $sitekit_widget;
    }
}