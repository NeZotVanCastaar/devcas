<?php
defined('ABSPATH') || exit;

// Config
$new_login_slug = 'develop';

/**
 * Helper: veilig redirecten met no-cache
 */
function castaar_safe_redirect($url) {
    nocache_headers();
    wp_safe_redirect($url);
    exit;
}

/**
 * Login-bescherming + custom slug
 */
add_action('init', function () use ($new_login_slug) {
    // Laat cron, ajax en CLI met rust
    if (defined('DOING_CRON') && DOING_CRON) return;
    if (defined('DOING_AJAX') && DOING_AJAX) return;
    if (defined('WP_CLI') && WP_CLI) return;

    // Normaliseer path zonder leading/trailing slashes
    $path = trim(parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?? '/', '/');
    $method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');

    // Geblokkeerde routes voor niet-ingelogden
    // - volledige wp-admin/* (behalve admin-ajax.php)
    // - alle varianten van wp-login.php
    $is_login_php = ($path === 'wp-login.php' || str_starts_with($path, 'wp-login.php?'));
    $is_admin_area = (str_starts_with($path, 'wp-admin') && $path !== 'wp-admin/admin-ajax.php');

    // 1) Niet ingelogd en probeert wp-admin/* of wp-login.php (maar niet onze slug)
    if (!is_user_logged_in() && ($is_admin_area || $is_login_php) && $path !== $new_login_slug) {
        // POST naar wp-login.php (login submit) laten we door
        if ($is_login_php && $method === 'POST') {
            return;
        }
        // Stuur naar custom login slug (behoud eventueel bestaande querystring)
        $query = $_SERVER['QUERY_STRING'] ?? '';
        $target = home_url('/' . $new_login_slug . (!empty($query) ? '?' . $query : ''));
        castaar_safe_redirect($target);
    }

    // 2) /login → /<custom>
    if ($path === 'login') {
        castaar_safe_redirect(home_url('/' . $new_login_slug));
    }

    // 3) Render onze custom login pagina op /<custom>
    if ($path === $new_login_slug) {
        if (is_user_logged_in()) {
            castaar_safe_redirect(admin_url());
        }
        // Laat wp-login.php de rest afhandelen (lostpassword, register, etc.)
        require_once ABSPATH . 'wp-login.php';
        exit; // safety
    }
});

/**
 * Herschrijf wp-login.php links naar custom slug (incl. acties)
 * Zodat WordPress zelf overal correcte URL’s toont.
 */
add_filter('site_url', function ($url, $path, $orig_scheme, $blog_id) use ($new_login_slug) {
    // Alleen ingrijpen op wp-login.php targets
    if ($path === 'wp-login.php' || $path === '/wp-login.php') {
        // Plak querystring (bv. ?action=lostpassword) door naar /develop
        $qs = '';
        if (isset($_SERVER['QUERY_STRING']) && $_SERVER['QUERY_STRING'] !== '') {
            // Let op: dit is de huidige request QS; beter is de QS uit $url halen
            // daarom gebruiken we parse_url op $url:
            $parts = wp_parse_url($url);
            $qs = isset($parts['query']) ? '?' . $parts['query'] : '';
        } else {
            $parts = wp_parse_url($url);
            $qs = isset($parts['query']) ? '?' . $parts['query'] : '';
        }
        return home_url('/' . $new_login_slug . $qs);
    }
    return $url;
}, 10, 4);

/**
 * Correcte redirect NA login
 */
add_filter('login_redirect', function ($redirect_to, $request, $user) {
    if (is_wp_error($user)) {
        return home_url(); // bij login error
    }
    return admin_url(); // naar dashboard
}, 10, 3);

/**
 * Kleine veiligheid: verwijder WP versie uit <head>
 */
remove_action('wp_head', 'wp_generator');
add_filter('the_generator', '__return_empty_string');

/**
 * Extra: zorg dat directe /wp-admin/ zonder login ook naar /develop gaat,
 * (WordPress redirect standaard naar wp-login.php?…; wij veranderen dat.)
 */
add_action('login_init', function () use ($new_login_slug) {
    // Als WP ons toch op wp-login.php zet (zonder POST), stuur naar onze slug
    if (!is_user_logged_in() && $_SERVER['REQUEST_METHOD'] !== 'POST') {
        // voorkom loop als we al op /develop zijn
        $path = trim(parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?? '/', '/');
        if ($path !== $new_login_slug) {
            castaar_safe_redirect(home_url('/' . $new_login_slug));
        }
    }
});
