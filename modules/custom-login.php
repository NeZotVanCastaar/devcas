<?php
add_action('init', function () {
    add_rewrite_rule('^develop/?$', 'index.php?custom_login=1', 'top');
});

add_filter('query_vars', function ($vars) {
    $vars[] = 'custom_login';
    return $vars;
});

add_action('template_redirect', function () {
    if (intval(get_query_var('custom_login')) === 1) {
        require_once ABSPATH . 'wp-login.php';
        exit;
    }
});

// ❌ Blokkeer directe toegang tot wp-login.php behalve via /develop
add_action('login_init', function () {
    $expected = '/develop';
    $actual   = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);

    if (stripos($actual, 'wp-login.php') !== false && $actual !== $expected) {
        wp_redirect(home_url());
        exit;
    }
});


// ❌ Blokkeer toegang tot /wp-admin tenzij ingelogd
add_action('admin_init', function () {
    if (!is_user_logged_in()) {
        wp_redirect(home_url());
        exit;
    }
});


// ✔️ Flush permalinks bij activeren/deactiveren
register_activation_hook(__FILE__, function () {
    flush_rewrite_rules();
});
register_deactivation_hook(__FILE__, function () {
    flush_rewrite_rules();
});

remove_action('wp_head', 'wp_generator'); 
add_filter('the_generator', '__return_empty_string');
