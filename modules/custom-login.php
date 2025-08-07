<?php

// Configuratie
$new_login_slug = 'develop';

// Init hook voor loginbescherming
add_action('init', function () use ($new_login_slug) {
    $request_uri = trim(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH), '/');

    // Geblokkeerde routes voor niet-ingelogde gebruikers
    $blocked_slugs = [
        'wp-login.php',
        'wp-login.php?action=login',
        'login',
        'admin',
        'wp-admin',
    ];

    // Blokkeer login-urls voor niet-ingelogden
  if (in_array($request_uri, $blocked_slugs) && $request_uri !== $new_login_slug) {
    // Sta login-flow toe bij POST of bij ingelogde gebruikers
    if (!is_user_logged_in() && $_SERVER['REQUEST_METHOD'] !== 'POST') {
        wp_redirect(home_url());
        exit;
    }
}


    // Redirect /login → /develop (optioneel)
    if ($request_uri === 'login') {
        wp_redirect(home_url('/' . $new_login_slug));
        exit;
    }

    // Verwerk aangepaste loginpagina (/develop)
    if ($request_uri === $new_login_slug) {
        if (is_user_logged_in()) {
            wp_redirect(admin_url());
            exit;
        }

        require_once ABSPATH . 'wp-login.php';
        exit;
    }
});

// Redirect na logout terug naar /develop
add_filter('site_url', function ($url, $path, $orig_scheme, $blog_id) use ($new_login_slug) {
    if ($path === 'wp-login.php' || $path === '/wp-login.php') {
        if (strpos($_SERVER['REQUEST_URI'], $new_login_slug) !== false) {
            return home_url('/' . $new_login_slug);
        }
    }
    return $url;
}, 10, 4);

// Correcte redirect NA login
add_filter('login_redirect', function ($redirect_to, $request, $user) {
    if (is_wp_error($user)) {
        return home_url(); // Bij login error
    }

    return admin_url(); // Naar dashboard
}, 10, 3);

// Kleine veiligheid: verwijder WP versie uit de <head>
remove_action('wp_head', 'wp_generator');
add_filter('the_generator', '__return_empty_string');
