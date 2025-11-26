<?php
if (!defined('ABSPATH')) exit;

// ============================
//   CONSTANTS & HELPERS
// ============================

const CASTAAR_SEO_OPT   = 'castaar_seo_enabled_post_types';
const CASTAAR_SEO_ROLES = 'castaar_seo_allowed_roles'; // nieuw: wie mag SEO-metabox/kolom zien

/**
 * Huidige gebruiker: mag die de SEO-metabox/kolom zien en opslaan?
 * Bepaald via rollen (default: alleen administrator).
 */
function castaar_seo_user_can_edit(): bool
{
    if (!is_user_logged_in()) return false;
    $allowed = get_option(CASTAAR_SEO_ROLES, null);

    // Fallback: alleen administrators als er nog niets ingesteld is
    if (!is_array($allowed) || empty($allowed)) {
        $allowed = ['administrator'];
    }

    $user = wp_get_current_user();
    if (empty($user->roles)) return false;

    return (bool) array_intersect($user->roles, $allowed);
}

/**
 * Haal lijst van geactiveerde post types op. Valt terug op alle publieke post types.
 * Alleen laden voor gebruikers die de metabox mogen zien.
 */
function castaar_seo_get_enabled_post_types()
{
    if (!castaar_seo_user_can_edit()) {
        return [];
    }
    $enabled = get_option(CASTAAR_SEO_OPT);
    if (!is_array($enabled) || empty($enabled)) {
        // Fallback: alle publieke post types
        return get_post_types(['public' => true], 'names');
    }
    // Alleen nog bestaande post types toelaten
    $existing = get_post_types([], 'names');
    return array_values(array_intersect($enabled, array_keys($existing)));
}

/**
 * Fallback default: zet standaard alle publieke post types aan
 * En zet standaard toegestane rol op administrator
 */
add_action('admin_init', function () {
    if (get_option(CASTAAR_SEO_OPT, null) === null) {
        $all = get_post_types(['public' => true], 'names');
        update_option(CASTAAR_SEO_OPT, array_values($all));
    }
    if (get_option(CASTAAR_SEO_ROLES, null) === null) {
        update_option(CASTAAR_SEO_ROLES, ['administrator']);
    }
});

// ============================
//   INSTELLINGENPAGINA (onder CASTAAR)
// ============================

add_action('admin_menu', function () {
    // Alleen admins mogen de instellingen wijzigen
    if (!current_user_can('manage_options')) return;

    add_submenu_page(
        'castaar',                          // parent: CASTAAR hoofdmenu
        'Castaar SEO',                      // page title
        'SEO',                              // menu title
        'manage_options',                   // capability (admin-only)
        'castaar-seo-settings',             // slug
        'castaar_seo_render_settings_page'  // callback
    );
});

function castaar_seo_render_settings_page()
{
    if (!current_user_can('manage_options')) return;

    // Opslag
    if (isset($_POST['castaar_seo_nonce']) && wp_verify_nonce($_POST['castaar_seo_nonce'], 'castaar_seo_save')) {
        // Post types
        $postedPT  = isset($_POST['castaar_seo_post_types']) ? (array) $_POST['castaar_seo_post_types'] : [];
        $sanitized = [];
        $public_pt = get_post_types(['public' => true], 'names');
        foreach ($postedPT as $pt) {
            $pt = sanitize_key($pt);
            if (isset($public_pt[$pt]) || in_array($pt, $public_pt, true)) {
                $sanitized[] = $pt;
            }
        }
        if (empty($sanitized)) {
            // Leeg laten = fallback naar "alles aan"
            delete_option(CASTAAR_SEO_OPT);
        } else {
            update_option(CASTAAR_SEO_OPT, array_values(array_unique($sanitized)));
        }

        // Rollen
        $postedRoles  = isset($_POST['castaar_seo_roles']) ? (array) $_POST['castaar_seo_roles'] : [];
        $editable     = get_editable_roles();
        $roles_clean  = [];
        foreach ($postedRoles as $r) {
            $r = sanitize_key($r);
            if (isset($editable[$r])) $roles_clean[] = $r;
        }
        if (empty($roles_clean)) {
            // Fallback: administrator
            update_option(CASTAAR_SEO_ROLES, ['administrator']);
        } else {
            update_option(CASTAAR_SEO_ROLES, array_values(array_unique($roles_clean)));
        }

        echo '<div class="notice notice-success is-dismissible"><p>Instellingen bewaard.</p></div>';
    }

    $current_enabled = get_option(CASTAAR_SEO_OPT, []);
    if (empty($current_enabled)) {
        $current_enabled = get_post_types(['public' => true], 'names'); // fallback UI
    }
    $public_types   = get_post_types(['public' => true], 'objects');
    $allowed_roles  = (array) get_option(CASTAAR_SEO_ROLES, ['administrator']);
    $editable_roles = get_editable_roles();
?>
    <div class="wrap">
        <h1>Castaar SEO – Instellingen</h1>
        <p>Kies voor welke <strong>post types</strong> de Castaar SEO-metabox en SEO-kolom zichtbaar mogen zijn.</p>

        <form method="post">
            <?php wp_nonce_field('castaar_seo_save', 'castaar_seo_nonce'); ?>

            <h2 class="title">Zichtbare post types</h2>
            <table class="widefat striped" style="max-width:820px;margin-top:10px;">
                <thead>
                    <tr>
                        <th style="width:80px;">Actief</th>
                        <th>Post type</th>
                        <th>Beschrijving</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($public_types as $pt => $obj): ?>
                        <?php $checked = in_array($pt, $current_enabled, true); ?>
                        <tr>
                            <td>
                                <label>
                                    <input type="checkbox" name="castaar_seo_post_types[]" value="<?php echo esc_attr($pt); ?>" <?php checked($checked); ?> />
                                </label>
                            </td>
                            <td><strong><?php echo esc_html($obj->labels->name ?? $pt); ?></strong> <code><?php echo esc_html($pt); ?></code></td>
                            <td><?php echo esc_html($obj->description ?? ''); ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>

            <h2 class="title" style="margin-top:24px;">Toegang: wie mag de SEO-velden zien/bewerken?</h2>
            <p>Selecteer één of meerdere <strong>rollen</strong>. Deze gebruikers zien de metabox, checklist/score en kolom, en mogen de waarden opslaan.</p>
            <div style="display:flex;gap:24px;flex-wrap:wrap;max-width:820px;">
                <?php foreach ($editable_roles as $role_key => $role_obj): ?>
                    <label style="display:inline-block;min-width:220px;">
                        <input type="checkbox" name="castaar_seo_roles[]" value="<?php echo esc_attr($role_key); ?>"
                            <?php checked(in_array($role_key, $allowed_roles, true)); ?>>
                        <?php echo esc_html($role_obj['name']); ?> <code><?php echo esc_html($role_key); ?></code>
                    </label>
                <?php endforeach; ?>
            </div>

            <p style="margin-top:18px;">
                <button type="submit" class="button button-primary">Bewaar</button>
            </p>
        </form>
    </div>
<?php
}

// ============================
//   METABOX + ANALYSE + OPSLAG
// ============================

add_action('add_meta_boxes', function () {
    if (!castaar_seo_user_can_edit()) return;

    $post_types = castaar_seo_get_enabled_post_types();
    foreach ($post_types as $post_type) {
        add_meta_box(
            'page_meta_tags_box',
            'SEO Meta gegevens & SEO Score',
            'render_page_meta_tags_box',
            $post_type,
            'normal',
            'high'
        );
    }
});

function analyze_seo($post_id, $verbose = false)
{
    $results = [];
    $score = 0;
    $max_score = 20;

    $meta_title = get_post_meta($post_id, '_custom_meta_title', true);
    $meta_description = get_post_meta($post_id, '_custom_meta_description', true);
    $main_kw = get_post_meta($post_id, '_custom_main_keyword', true);
    $extra_keywords = [];
    for ($i = 1; $i <= 4; $i++) {
        $extra_keywords[] = get_post_meta($post_id, "_custom_extra_keyword_$i", true);
    }

    $post = get_post($post_id);
    if (!$post) {
        return ['score' => 0, 'checks' => [['status' => 'fail', 'text' => 'Post niet gevonden']]];
    }
    $content = $post ? $post->post_content : '';
    $content_text = wp_strip_all_tags($content);
    $site_url = home_url();
    $url = get_permalink($post_id);

    // Check helpers
    $add_result = function ($passed, $message_pass, $message_fail) use (&$results, &$score, $verbose) {
        if ($passed) {
            $score++;
            if ($verbose) $results[] = ['status' => 'pass', 'text' => $message_pass];
        } elseif ($verbose) {
            $results[] = ['status' => 'fail', 'text' => $message_fail];
        }
    };

    // 1. Meta Title lengte
    $add_result(strlen($meta_title) >= 30 && strlen($meta_title) <= 60, 'Meta Title lengte is goed', 'Meta Title moet tussen 30-60 tekens zijn');

    // 2. Meta Description lengte
    $add_result(strlen($meta_description) >= 70 && strlen($meta_description) <= 160, 'Meta Description lengte is goed', 'Meta Description moet tussen 70-160 tekens zijn');

    // 3. Hoofd keyword ingevuld
    $add_result(!empty($main_kw), 'Hoofd keyword is ingevuld', 'Geen hoofd keyword');

    // 4. Minstens 2 extra keywords
    $add_result(count(array_filter($extra_keywords)) >= 2, 'Minstens 2 extra keywords zijn ingevuld', 'Te weinig extra keywords (min. 2 aanbevolen)');

    // 5. Keyword density
    if (!empty($main_kw)) {
        $words = str_word_count(strtolower($content_text));
        $keyword_count = substr_count(strtolower($content_text), strtolower($main_kw));
        $density = $words > 0 ? ($keyword_count / $words) * 100 : 0;
        $add_result($density >= 0.5 && $density <= 3, "Keyword density is goed ({$density}%)", "Keyword density is {$density}%. Aanbevolen is 0.5–3%");
    } elseif ($verbose) {
        $results[] = ['status' => 'fail', 'text' => 'Kan keyword density niet berekenen zonder hoofdkeyword'];
    }

    // 6. Keyword in eerste 10% content
    if (!empty($main_kw)) {
        $first_10 = substr($content_text, 0, intval(strlen($content_text) * 0.1));
        $add_result(stripos($first_10, $main_kw) !== false, 'Keyword staat in eerste 10% van de content', 'Keyword ontbreekt in eerste 10%');
    }

    // 7. Keyword in H1
    if (!empty($main_kw)) {
        preg_match_all('/<h1[^>]*>(.*?)<\/h1>/i', $content, $matches);
        $found = false;
        foreach ($matches[1] as $h1) {
            if (stripos($h1, $main_kw) !== false) {
                $found = true;
                break;
            }
        }
        $add_result($found, 'Keyword staat in H1 tag', 'Keyword staat niet in H1 tag');
    }

    // 8. Keyword in H2/H3
    if (!empty($main_kw)) {
        preg_match_all('/<(h2|h3)[^>]*>(.*?)<\/\1>/i', $content, $matches);
        $found = false;
        foreach ($matches[2] as $heading) {
            if (stripos($heading, $main_kw) !== false) {
                $found = true;
                break;
            }
        }
        $add_result($found, 'Keyword staat in H2/H3 tag', 'Keyword staat niet in H2/H3');
    }

    // 9. Lijsten aanwezig
    $add_result(preg_match('/<(ul|ol)[^>]*>/', $content), 'Content bevat lijsten', 'Geen lijsten gevonden');

    // 10. Gemiddelde paragraaflengte
    preg_match_all('/<p[^>]*>(.*?)<\/p>/i', $content, $matches);
    $par_lengths = array_map(function ($p) {
        return str_word_count(wp_strip_all_tags($p));
    }, $matches[1] ?? []);
    if ($par_lengths) {
        $avg = array_sum($par_lengths) / count($par_lengths);
        $add_result($avg <= 150, "Gem. paragraaflengte is goed ({$avg} woorden)", "Paragraaflengte is te lang ({$avg} woorden)");
    } elseif ($verbose) {
        $results[] = ['status' => 'fail', 'text' => 'Geen paragrafen gevonden'];
    }

    // 11. Interne links
    preg_match_all('/<a[^>]+href=["\']([^"\']+)["\']/', $content, $matches);
    $internal_found = false;
    foreach ($matches[1] as $link) {
        if (strpos($link, $site_url) === 0) {
            $internal_found = true;
            break;
        }
    }
    $add_result($internal_found, 'Interne link aanwezig', 'Geen interne link gevonden');

    // 12. Externe links
    $external_found = false;
    foreach ($matches[1] as $link) {
        if (strpos($link, $site_url) !== 0 && preg_match('#^https?://#', $link)) {
            $external_found = true;
            break;
        }
    }
    $add_result($external_found, 'Externe link aanwezig', 'Geen externe link gevonden');

    // 13. Afbeeldingen met alt en keyword
    preg_match_all('/<img[^>]+>/i', $content, $img_tags);
    if (!empty($img_tags[0])) {
        $score++; // aanwezigheid afbeelding
        $found_alt = false;
        foreach ($img_tags[0] as $img) {
            if (preg_match('/alt=["\']([^"\']*)["\']/', $img, $alt) && stripos($alt[1], $main_kw) !== false) {
                $found_alt = true;
                break;
            }
        }
        $add_result($found_alt, 'Afbeelding met keyword in alt-tag', 'Geen afbeelding met keyword in alt-tag');
    } elseif ($verbose) {
        $results[] = ['status' => 'fail', 'text' => 'Geen afbeeldingen gevonden'];
    }

    // 14. Duplicate title
    if (!empty($meta_title)) {
        global $wpdb;
        $count = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->postmeta} pm
             INNER JOIN {$wpdb->posts} p ON p.ID = pm.post_id
             WHERE pm.meta_key = '_custom_meta_title' AND pm.meta_value = %s AND p.ID != %d AND p.post_status = 'publish'",
            $meta_title,
            $post_id
        ));
        $add_result($count == 0, 'Meta Title is uniek', 'Meta Title komt op andere pagina(s) voor');
    }

    // 15. Duplicate description
    if (!empty($meta_description)) {
        global $wpdb;
        $count = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->postmeta} pm
             INNER JOIN {$wpdb->posts} p ON p.ID = pm.post_id
             WHERE pm.meta_key = '_custom_meta_description' AND pm.meta_value = %s AND p.ID != %d AND p.post_status = 'publish'",
            $meta_description,
            $post_id
        ));
        $add_result($count == 0, 'Meta Description is uniek', 'Meta Description komt op andere pagina(s) voor');
    }

    // 16. Keyword in title
    $add_result(!empty($main_kw) && stripos($meta_title, $main_kw) !== false, 'Keyword in Meta Title', 'Keyword ontbreekt in Meta Title');

    // 17. Keyword in description
    $add_result(!empty($main_kw) && stripos($meta_description, $main_kw) !== false, 'Keyword in Meta Description', 'Keyword ontbreekt in Meta Description');

    // 18. Keyword in URL
    $add_result(!empty($main_kw) && stripos($url, sanitize_title($main_kw)) !== false, 'Keyword in URL', 'Keyword ontbreekt in URL');

    // 19. Minstens 300 woorden
    $add_result(str_word_count($content_text) >= 300, 'Minstens 300 woorden', 'Content is te kort (< 300 woorden)');

    // 20. Uniek keyword
    if (!empty($main_kw)) {
        global $wpdb;
        $count_kw = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->postmeta} pm
             INNER JOIN {$wpdb->posts} p ON p.ID = pm.post_id
             WHERE pm.meta_key = '_custom_main_keyword' AND LOWER(pm.meta_value) = LOWER(%s) AND p.ID != %d AND p.post_status = 'publish'",
            $main_kw,
            $post_id
        ));
        $add_result($count_kw == 0, 'Focus keyword is uniek', 'Focus keyword komt elders voor');
    }

    return [
        'score'  => round(($score / $max_score) * 100),
        'checks' => $results
    ];
}

function render_page_meta_tags_box($post)
{
    $meta_title       = get_post_meta($post->ID, '_custom_meta_title', true);
    $meta_description = get_post_meta($post->ID, '_custom_meta_description', true);
    $main_kw          = get_post_meta($post->ID, '_custom_main_keyword', true);
    $extra_keywords   = [];
    for ($i = 1; $i <= 4; $i++) {
        $extra_keywords[$i] = get_post_meta($post->ID, "_custom_extra_keyword_$i", true);
    }

    $content      = $post->post_content;
    $content_text = wp_strip_all_tags($content);

    wp_nonce_field('save_page_meta_tags', 'page_meta_tags_nonce');
?>
    <p>
        <label for="custom_meta_title"><strong>Meta Title</strong></label><br>
        <input type="text" id="custom_meta_title" name="custom_meta_title" value="<?php echo esc_attr($meta_title); ?>" style="width:100%;" />
        <small><?php echo strlen($meta_title); ?> tekens</small>
    </p>
    <p>
        <label for="custom_meta_description"><strong>Meta Description</strong></label><br>
        <textarea id="custom_meta_description" name="custom_meta_description" rows="3" style="width:100%;"><?php echo esc_textarea($meta_description); ?></textarea>
        <small><?php echo strlen($meta_description); ?> tekens</small>
    </p>
    <p>
        <label for="custom_main_keyword"><strong>Hoofd Keyword</strong></label><br>
        <input type="text" id="custom_main_keyword" name="custom_main_keyword" value="<?php echo esc_attr($main_kw); ?>" style="width:100%;" />
    </p>
    <?php for ($i = 1; $i <= 4; $i++): ?>
        <p>
            <label for="custom_extra_keyword_<?php echo $i; ?>"><strong>Keyword <?php echo $i; ?></strong></label><br>
            <input type="text" id="custom_extra_keyword_<?php echo $i; ?>" name="custom_extra_keyword_<?php echo $i; ?>" value="<?php echo esc_attr($extra_keywords[$i]); ?>" style="width:100%;" />
        </p>
    <?php endfor; ?>

    <div style="display:flex; gap:40px; align-items:flex-start; justify-content:space-between; margin-top:30px;">
        <!-- Checklist -->
        <div style="flex:1;">
            <h4>📋 SEO Checklist & Score</h4>
            <ul>
                <?php
                $seo_data = analyze_seo($post->ID, true);
                foreach ($seo_data['checks'] as $check) {
                    $color = $check['status'] === 'pass' ? 'green' : 'red';
                    $icon  = $check['status'] === 'pass' ? '✅' : '❌';
                    echo '<li style="color:' . esc_attr($color) . ';">' . $icon . ' ' . esc_html($check['text']) . '</li>';
                }
                $percentage = $seo_data['score'];
                $bar_color = '#f44336';
                if ($percentage >= 75) $bar_color = '#4caf50';
                elseif ($percentage >= 25) $bar_color = '#ffc107';
                ?>
            </ul>
        </div>

        <!-- Keyword analyse -->
        <div style="flex:2;">
            <h4>🔎 Keyword Sterkte Analyse</h4>
            <ul>
                <?php
                $keywords = array_unique(array_filter(array_merge([$main_kw], array_values($extra_keywords))));
                foreach ($keywords as $kw) {
                    if (!$kw) continue;
                    $occurrences = substr_count(strtolower($content_text), strtolower($kw));
                    if ($occurrences >= 5) {
                        $kw_color = 'green';
                        $label = 'Sterk';
                    } elseif ($occurrences >= 2) {
                        $kw_color = 'orange';
                        $label = 'Gemiddeld';
                    } else {
                        $kw_color = 'red';
                        $label = 'Zwak';
                    }
                    echo '<li style="color:' . esc_attr($kw_color) . '; margin-bottom:5px;">';
                    echo '🔍 <strong>' . esc_html($kw) . '</strong>: ' . esc_html($label) . ' (' . intval($occurrences) . 'x)';
                    echo '</li>';
                }
                ?>
            </ul>
        </div>
    </div>

    <!-- Scorebalk -->
    <style>
        .seo-score-wrap {
            margin-top: 30px;
        }

        .seo-score-bar {
            width: 100%;
            height: 24px;
            background: #ddd;
            border-radius: 5px;
            overflow: hidden;
            box-shadow: inset 0 1px 2px rgba(0, 0, 0, 0.15);
        }

        .seo-score-bar-inner {
            height: 100%;
            transition: width 0.4s ease;
        }
    </style>
    <div class="seo-score-wrap">
        <p><strong>Totale SEO Score:</strong> <?php echo intval($percentage); ?>%</p>
        <div class="seo-score-bar">
            <div class="seo-score-bar-inner" style="width:<?php echo intval($percentage); ?>%; background:<?php echo esc_attr($bar_color); ?>;"></div>
        </div>
    </div>
<?php
}

add_action('save_post', function ($post_id) {
    if (!isset($_POST['page_meta_tags_nonce']) || !wp_verify_nonce($_POST['page_meta_tags_nonce'], 'save_page_meta_tags')) {
        return;
    }
    if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;
    if (!castaar_seo_user_can_edit()) return;

    update_post_meta($post_id, '_custom_meta_title', sanitize_text_field($_POST['custom_meta_title'] ?? ''));
    update_post_meta($post_id, '_custom_meta_description', sanitize_textarea_field($_POST['custom_meta_description'] ?? ''));
    update_post_meta($post_id, '_custom_main_keyword', sanitize_text_field($_POST['custom_main_keyword'] ?? ''));

    for ($i = 1; $i <= 4; $i++) {
        $field = "custom_extra_keyword_$i";
        update_post_meta($post_id, "_$field", sanitize_text_field($_POST[$field] ?? ''));
    }

    delete_transient('seo_score_' . $post_id);
});

// ============================
//   ADMIN KOLOMMEN (SEO SCORE)
// ============================

add_action('admin_init', function () {
    if (!castaar_seo_user_can_edit()) return;

    $post_types = castaar_seo_get_enabled_post_types();
    foreach ($post_types as $post_type) {
        add_filter("manage_{$post_type}_posts_columns", function ($columns) {
            $columns['seo_score'] = 'SEO Score';
            return $columns;
        });

        add_action("manage_{$post_type}_posts_custom_column", function ($column_name, $post_id) {
            if ($column_name === 'seo_score') {
                $score   = calculate_seo_score_for_post($post_id);
                $main_kw = get_post_meta($post_id, '_custom_main_keyword', true);
                $content = get_post_field('post_content', $post_id);
                $site_url = home_url();

                // kleur bepalen
                $bg = '#f44336'; // rood
                if ($score >= 75) $bg = '#4caf50'; // groen
                elseif ($score >= 25) $bg = '#ffc107'; // geel

                // score badge
                echo '<div style="display:inline-block;padding:4px 8px;border-radius:6px;font-weight:bold;font-size:13px;background:' . esc_attr($bg) . ';color:#fff;margin-bottom:4px;">' . intval($score) . ' / 100</div>';

                // hoofdkeyword tonen
                if (!empty($main_kw)) {
                    echo '<div style="margin-top:3px;font-size:11px;color:#555;"><strong>Keyword:</strong> ' . esc_html($main_kw) . '</div>';
                }

                // interne en externe links tellen
                $internal_links = 0;
                $external_links = 0;

                preg_match_all('/<a[^>]+href=["\']([^"\']+)["\']/', $content, $matches);
                if (!empty($matches[1])) {
                    foreach ($matches[1] as $url) {
                        if (strpos($url, $site_url) === 0) {
                            $internal_links++;
                        } elseif (strpos($url, 'http') === 0 || strpos($url, '//') === 0) {
                            $external_links++;
                        }
                    }
                }

                // tonen
                echo '<div style="margin-top:3px;font-size:11px;color:#555;">';
                echo '<strong>Links:</strong> 🔗 ' . intval($internal_links) . ' intern | 🌐 ' . intval($external_links) . ' extern';
                echo '</div>';
            }
        }, 10, 2);
    }
});

// ============================
//   TITEL OVERRIDES & CACHING
// ============================

add_filter('pre_get_document_title', function ($title) {
    if (is_singular()) {
        $id = get_the_ID();
        if ($id) {
            $custom_title = get_post_meta($id, '_custom_meta_title', true);
            if (!empty($custom_title)) {
                return $custom_title;
            }
        }
    }
    return $title;
});

function calculate_seo_score_for_post($post_id)
{
    $cache_key = 'seo_score_' . $post_id;
    $cached = get_transient($cache_key);

    if ($cached !== false) {
        return $cached;
    }

    $seo = analyze_seo($post_id);
    set_transient($cache_key, $seo['score'], HOUR_IN_SECONDS);

    return $seo['score'];
}

// ============================
//   WPML TRANSLATION SUPPORT
// ============================

/**
 * WPML Integration for SEO Meta Fields
 * 
 * This section ensures all Castaar SEO meta fields are fully translatable in WPML:
 * - Meta Title (_custom_meta_title)
 * - Meta Description (_custom_meta_description)
 * - Main Keyword (_custom_main_keyword)
 * - Extra Keywords 1-4 (_custom_extra_keyword_1 through _custom_extra_keyword_4)
 * 
 * When translating a page in WPML, these fields will appear in the translation editor
 * allowing translators to provide language-specific SEO content for each translation.
 * 
 * The fields are automatically configured as "Translate" (not copy or ignore).
 */

/**
 * Register SEO meta fields as translatable in WPML
 * This allows editors to translate SEO fields in the WPML translation editor
 */
add_action('init', function () {
    // Only register if WPML is active
    if (!function_exists('wpml_load_settings_helper')) {
        return;
    }

    // Register custom fields for translation
    add_filter('wpml_duplicate_generic_string', function ($value, $target_lang, $meta_data) {
        // Don't copy - let translator fill in
        return '';
    }, 10, 3);

    // Register meta fields for translation in WPML Translation Editor
    add_action('wpml_register_single_string_for_translation', function () {
        // This will be called when post is sent to translation
    });
});

/**
 * Register all SEO meta keys as translatable via WPML
 * This makes the fields appear in WPML's translation editor
 */
add_action('wpml_register_translation_options', function () {
    if (!function_exists('wpml_register_single_string')) {
        return;
    }

    $meta_keys = [
        '_custom_meta_title',
        '_custom_meta_description',
        '_custom_main_keyword',
        '_custom_extra_keyword_1',
        '_custom_extra_keyword_2',
        '_custom_extra_keyword_3',
        '_custom_extra_keyword_4'
    ];

    foreach ($meta_keys as $key) {
        do_action('wpml_register_single_string', 'castaar-seo', $key, '');
    }
});

/**
 * Make custom fields translatable in WPML
 * Add them to the list of fields that should be translated
 */
add_filter('wpml_custom_field_values_for_post_signature', function ($custom_fields_values, $post_id) {
    $seo_fields = [
        '_custom_meta_title',
        '_custom_meta_description',
        '_custom_main_keyword',
        '_custom_extra_keyword_1',
        '_custom_extra_keyword_2',
        '_custom_extra_keyword_3',
        '_custom_extra_keyword_4'
    ];

    foreach ($seo_fields as $field) {
        $value = get_post_meta($post_id, $field, true);
        if (!empty($value)) {
            $custom_fields_values[$field] = $value;
        }
    }

    return $custom_fields_values;
}, 10, 2);

/**
 * Tell WPML these fields should be copied to translation editor
 * WPML uses this to know which custom fields to include in translation jobs
 */
add_filter('wpml_tm_copy_custom_fields', function ($fields) {
    $seo_fields = [
        '_custom_meta_title',
        '_custom_meta_description',
        '_custom_main_keyword',
        '_custom_extra_keyword_1',
        '_custom_extra_keyword_2',
        '_custom_extra_keyword_3',
        '_custom_extra_keyword_4'
    ];

    return array_unique(array_merge($fields, $seo_fields));
});

/**
 * Configure WPML translation settings for SEO fields
 * Mark fields as "translate" instead of "copy" or "ignore"
 */
add_action('admin_init', function () {
    if (!function_exists('wpml_get_setting_filter')) {
        return;
    }

    $seo_fields = [
        '_custom_meta_title' => 2,          // 2 = translate
        '_custom_meta_description' => 2,
        '_custom_main_keyword' => 2,
        '_custom_extra_keyword_1' => 2,
        '_custom_extra_keyword_2' => 2,
        '_custom_extra_keyword_3' => 2,
        '_custom_extra_keyword_4' => 2
    ];

    add_filter('wpml_tm_custom_field_translation', function ($translate, $field) use ($seo_fields) {
        if (isset($seo_fields[$field])) {
            return $seo_fields[$field];
        }
        return $translate;
    }, 10, 2);

    // Auto-configure WPML settings for these fields
    if (function_exists('wpml_update_settings_helper')) {
        $current_settings = get_option('_icl_custom_field_translation', []);
        if (!is_array($current_settings)) {
            $current_settings = [];
        }

        $updated = false;
        foreach ($seo_fields as $field => $value) {
            // Only update if not already set or set to different value
            if (!isset($current_settings[$field]) || $current_settings[$field] != $value) {
                $current_settings[$field] = $value;
                $updated = true;
            }
        }

        if ($updated) {
            update_option('_icl_custom_field_translation', $current_settings);
        }
    }
}, 99);

/**
 * Ensure WPML shows these fields in the translation editor
 * This hook is called when WPML builds the translation editor
 */
add_filter('wpml_tm_translation_jobs_basket_post_meta', function ($meta_keys, $post_id) {
    $seo_fields = [
        '_custom_meta_title',
        '_custom_meta_description',
        '_custom_main_keyword',
        '_custom_extra_keyword_1',
        '_custom_extra_keyword_2',
        '_custom_extra_keyword_3',
        '_custom_extra_keyword_4'
    ];

    return array_unique(array_merge($meta_keys, $seo_fields));
}, 10, 2);

/**
 * Display admin notice confirming WPML integration is active
 * Only shows once after activation
 */
add_action('admin_notices', function () {
    // Only show on Castaar SEO settings page
    if (!isset($_GET['page']) || $_GET['page'] !== 'castaar-seo-settings') {
        return;
    }

    // Only show if WPML is active
    if (!function_exists('wpml_get_setting_filter')) {
        return;
    }

    // Check if notice was already dismissed
    if (get_option('castaar_seo_wpml_notice_dismissed')) {
        return;
    }

?>
    <div class="notice notice-info is-dismissible" data-notice="castaar-seo-wpml">
        <p><strong>✅ WPML Integratie Actief</strong></p>
        <p>Alle Castaar SEO meta-velden (Meta Title, Description, Keywords) zijn nu vertaalbaar in de WPML Translation Editor.</p>
        <p>Wanneer je een pagina naar een andere taal vertaalt, kun je voor elke taal unieke SEO-metadata opgeven.</p>
    </div>
    <script>
        jQuery(document).on('click', '[data-notice="castaar-seo-wpml"] .notice-dismiss', function() {
            jQuery.post(ajaxurl, {
                action: 'castaar_dismiss_wpml_notice',
                nonce: '<?php echo wp_create_nonce('castaar_wpml_notice'); ?>'
            });
        });
    </script>
<?php
});

/**
 * Handle WPML notice dismissal
 */
add_action('wp_ajax_castaar_dismiss_wpml_notice', function () {
    check_ajax_referer('castaar_wpml_notice', 'nonce');
    update_option('castaar_seo_wpml_notice_dismissed', true);
    wp_die();
});

// ============================
//   FRONT-END META TAGS
// ============================

add_action('wp_head', function () {
    if (!is_singular()) {
        return;
    }

    $post_id = get_queried_object_id();

    // 1) META DESCRIPTION
    $meta_description = get_post_meta($post_id, '_custom_meta_description', true);
    if (!empty($meta_description)) {
        echo '<meta name="description" content="' . esc_attr(wp_strip_all_tags($meta_description)) . '">' . "\n";
    }

    // 2) (Optioneel) META KEYWORDS
    $main_kw = get_post_meta($post_id, '_custom_main_keyword', true);
    $extra = [];
    for ($i = 1; $i <= 4; $i++) {
        $val = get_post_meta($post_id, "_custom_extra_keyword_$i", true);
        if (!empty($val)) $extra[] = $val;
    }
    $keywords = array_filter(array_map('trim', array_unique(array_merge([$main_kw], $extra))));
    if (!empty($keywords)) {
        echo '<meta name="keywords" content="' . esc_attr(implode(', ', $keywords)) . '">' . "\n";
    }

    // 3) OG/Twitter
    $custom_title = get_post_meta($post_id, '_custom_meta_title', true);
    if (!empty($custom_title)) {
        $t = esc_attr(wp_strip_all_tags($custom_title));
        echo '<meta property="og:title" content="' . $t . '">' . "\n";
        echo '<meta name="twitter:title" content="' . $t . '">' . "\n";
    }
    if (!empty($meta_description)) {
        $d = esc_attr(wp_strip_all_tags($meta_description));
        echo '<meta property="og:description" content="' . $d . '">' . "\n";
        echo '<meta name="twitter:description" content="' . $d . '">' . "\n";
    }
}, 5);
