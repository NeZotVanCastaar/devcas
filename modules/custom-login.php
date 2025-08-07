<?php

add_action('init', function () {
    $new_login_slug = 'develop';
    $request_uri = trim(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH), '/');

    // Lijst van geblokkeerde routes
    $blocked_slugs = [
        'wp-login.php',
        'wp-login.php?action=login',
        'login',
        'admin',
        'wp-admin',
    ];

    // Blokkeer bekende login-urls voor NIET-ingelogde gebruikers
    if (in_array($request_uri, $blocked_slugs) && $request_uri !== $new_login_slug) {
        if (!is_user_logged_in()) {
            wp_redirect(home_url());
            exit;
        }
    }

    // Toegang tot aangepaste loginpagina
    if ($request_uri === $new_login_slug) {
        require_once ABSPATH . 'wp-login.php';
        exit;
    }
});

// Zorg ervoor dat logout redirect teruggaat naar /develop
add_filter('site_url', function ($url, $path, $orig_scheme, $blog_id) {
    $new_login_slug = 'develop';
    if ($path === 'wp-login.php' || $path === '/wp-login.php') {
        if (strpos($_SERVER['REQUEST_URI'], $new_login_slug) !== false) {
            return home_url('/' . $new_login_slug);
        }
    }
    return $url;
}, 10, 4);

// Zorg voor correcte redirect NA login
add_filter('login_redirect', function ($redirect_to, $request, $user) {
    if (is_wp_error($user)) {
        return home_url(); // Bij login error
    }

    // Stuur naar admin-dashboard
    return admin_url();
}, 10, 3);



remove_action('wp_head', 'wp_generator'); 
add_filter('the_generator', '__return_empty_string');