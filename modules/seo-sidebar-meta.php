<?php


add_action('add_meta_boxes', function() {
    $post_types = get_post_types(['public' => true], 'names');
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

function analyze_seo($post_id, $verbose = false) {
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
    $add_result = function($passed, $message_pass, $message_fail) use (&$results, &$score, $verbose) {
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
    $par_lengths = array_map(function($p) {
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
            $meta_title, $post_id
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
            $meta_description, $post_id
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
            $main_kw, $post_id
        ));
        $add_result($count_kw == 0, 'Focus keyword is uniek', 'Focus keyword komt elders voor');
    }

    return [
        'score' => round(($score / $max_score) * 100),
        'checks' => $results
    ];
}


function render_page_meta_tags_box($post) {
    $meta_title = get_post_meta($post->ID, '_custom_meta_title', true);
    $meta_description = get_post_meta($post->ID, '_custom_meta_description', true);
    $main_kw = get_post_meta($post->ID, '_custom_main_keyword', true);
    $extra_keywords = [];
    for ($i = 1; $i <= 4; $i++) {
        $extra_keywords[$i] = get_post_meta($post->ID, "_custom_extra_keyword_$i", true);
    }

    $content = $post->post_content;
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
                    $icon = $check['status'] === 'pass' ? '✅' : '❌';
                    echo '<li style="color:' . $color . ';">' . $icon . ' ' . esc_html($check['text']) . '</li>';
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
                    echo '🔍 <strong>' . esc_html($kw) . '</strong>: ' . esc_html($label) . ' (' . $occurrences . 'x)';
                    echo '</li>';
                }
                ?>
            </ul>
        </div>
    </div>

    <!-- Scorebalk helemaal onderaan -->
    <style>
        .seo-score-wrap { margin-top: 30px; }
        .seo-score-bar {
            width: 100%;
            height: 24px;
            background: #ddd;
            border-radius: 5px;
            overflow: hidden;
            box-shadow: inset 0 1px 2px rgba(0,0,0,0.15);
        }
        .seo-score-bar-inner {
            height: 100%;
            transition: width 0.4s ease;
        }
    </style>
    <div class="seo-score-wrap">
        <p><strong>Totale SEO Score:</strong> <?php echo $percentage; ?>%</p>
        <div class="seo-score-bar">
            <div class="seo-score-bar-inner" style="width:<?php echo $percentage; ?>%; background:<?php echo $bar_color; ?>;"></div>
        </div>
    </div>
    <?php
}


add_action('save_post', function($post_id) {
    if (!isset($_POST['page_meta_tags_nonce']) || !wp_verify_nonce($_POST['page_meta_tags_nonce'], 'save_page_meta_tags')) {
        return;
    }
    if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;

    update_post_meta($post_id, '_custom_meta_title', sanitize_text_field($_POST['custom_meta_title'] ?? ''));
    update_post_meta($post_id, '_custom_meta_description', sanitize_textarea_field($_POST['custom_meta_description'] ?? ''));
    update_post_meta($post_id, '_custom_main_keyword', sanitize_text_field($_POST['custom_main_keyword'] ?? ''));

    for ($i = 1; $i <= 4; $i++) {
        $field = "custom_extra_keyword_$i";
        update_post_meta($post_id, "_$field", sanitize_text_field($_POST[$field] ?? ''));
    }

    // 🔁 Cache verversen
    delete_transient('seo_score_' . $post_id);
});


add_action('wp_head', function() {
    if (!is_singular()) return;

    global $post;

    // 🔹 Meta description
    $desc = get_post_meta($post->ID, '_custom_meta_description', true);
    if ($desc) {
        echo '<meta name="description" content="' . esc_attr(strip_tags($desc)) . '">' . "\n";
    }

    // 🔹 Meta keywords
    $keywords = [];
    $main_kw = get_post_meta($post->ID, '_custom_main_keyword', true);
    if (!empty($main_kw)) $keywords[] = $main_kw;
    for ($i = 1; $i <= 4; $i++) {
        $val = get_post_meta($post->ID, "_custom_extra_keyword_$i", true);
        if (!empty($val)) $keywords[] = $val;
    }
    if (!empty($keywords)) {
        echo '<meta name="keywords" content="' . esc_attr(implode(', ', array_unique($keywords))) . '">' . "\n";
    }

    // 🔸 Publisher naam & logo ophalen
    $publisher_name = get_bloginfo('name');
    $publisher_url  = home_url();
    $logo_id        = get_theme_mod('custom_logo');
    $logo_url       = $logo_id ? wp_get_attachment_image_url($logo_id, 'full') : null;

    // 🔸 Fallback logo (indien nodig)
    if (!$logo_url) {
        $logo_url = 'https://castaar.com/dev/Castaar.png';
    }

    // 🔸 Structuurdata opstellen
    $title     = get_post_meta($post->ID, '_custom_meta_title', true) ?: get_the_title($post);
    $desc_ld   = $desc ?: wp_trim_words(strip_tags($post->post_content), 25);
    $author    = get_the_author_meta('display_name', $post->post_author);
    $published = get_the_date('c', $post);
    $modified  = get_the_modified_date('c', $post);
    $url       = get_permalink($post);

    $schema = [
        "@context" => "https://schema.org",
        "@type" => "Article",
        "mainEntityOfPage" => [
            "@type" => "WebPage",
            "@id" => $url
        ],
        "headline" => $title,
        "description" => $desc_ld,
        "author" => [
            "@type" => "Person",
            "name" => $author
        ],
        "publisher" => [
            "@type" => "Organization",
            "name" => $publisher_name,
            "url"  => $publisher_url,
            "logo" => [
                "@type" => "ImageObject",
                "url" => $logo_url,
                "width" => 112,
                "height" => 112
            ]
        ],
        "datePublished" => $published,
        "dateModified" => $modified
    ];

    echo '<script type="application/ld+json">' . json_encode($schema, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . '</script>' . "\n";
});

add_filter('pre_get_document_title', function($title) {
    if (is_singular()) {
        $custom_title = get_post_meta(get_the_ID(), '_custom_meta_title', true);
        if (!empty($custom_title)) {
            return $custom_title;
        }
    }
    return $title;
});

function calculate_seo_score_for_post($post_id) {
    $cache_key = 'seo_score_' . $post_id;
    $cached = get_transient($cache_key);

    if ($cached !== false) {
        return $cached;
    }

    $seo = analyze_seo($post_id);
    set_transient($cache_key, $seo['score'], HOUR_IN_SECONDS);

    return $seo['score'];
}



add_action('admin_init', function() {
    $post_types = get_post_types(['public' => true], 'names');
    foreach ($post_types as $post_type) {
        add_filter("manage_{$post_type}_posts_columns", function($columns) {
            $columns['seo_score'] = 'SEO Score';
            return $columns;
        });

       add_action("manage_{$post_type}_posts_custom_column", function($column_name, $post_id) {
    if ($column_name === 'seo_score') {
        $score = calculate_seo_score_for_post($post_id);
        $main_kw = get_post_meta($post_id, '_custom_main_keyword', true);
        $content = get_post_field('post_content', $post_id);
        $site_url = home_url();

        // kleur bepalen
        $bg = '#f44336'; // rood
        if ($score >= 75) $bg = '#4caf50'; // groen
        elseif ($score >= 25) $bg = '#ffc107'; // geel

        // score badge
        echo '<div style="display:inline-block;padding:4px 8px;border-radius:6px;font-weight:bold;font-size:13px;background:' . $bg . ';color:#fff;margin-bottom:4px;">' . $score . ' / 100</div>';

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
        echo '<strong>Links:</strong> 🔗 ' . $internal_links . ' intern | 🌐 ' . $external_links . ' extern';
        echo '</div>';
    }
}, 10, 2);

    }
});
