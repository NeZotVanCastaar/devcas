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

function render_page_meta_tags_box($post) {
    $meta_title = get_post_meta($post->ID, '_custom_meta_title', true);
    $meta_description = get_post_meta($post->ID, '_custom_meta_description', true);
    $main_kw = get_post_meta($post->ID, '_custom_main_keyword', true);
    $extra_keywords = [];
    for ($i = 1; $i <= 4; $i++) {
        $extra_keywords[$i] = get_post_meta($post->ID, "_custom_extra_keyword_$i", true);
    }

    // Content for analysis
    $content = $post->post_content;

    wp_nonce_field('save_page_meta_tags', 'page_meta_tags_nonce');

    ?>
    <p>
        <label for="custom_meta_title"><strong>Meta Title</strong></label><br>
        <input type="text" id="custom_meta_title" name="custom_meta_title" value="<?php echo esc_attr($meta_title); ?>" style="width:100%;" />
    </p>
    <p>
        <label for="custom_meta_description"><strong>Meta Description</strong></label><br>
        <textarea id="custom_meta_description" name="custom_meta_description" rows="3" style="width:100%;"><?php echo esc_textarea($meta_description); ?></textarea>
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
    <hr>
    <h4>SEO Checklist & Score</h4>
    <ul>
    <?php
    $score = 0;
    $max_score = 12; // aantal checks nu uitgebreid

    // 1. Meta Title lengte
    if (strlen($meta_title) >= 30 && strlen($meta_title) <= 60) {
        echo '<li style="color:green;">✅ Meta Title lengte is goed</li>';
        $score++;
    } else {
        echo '<li style="color:red;">❌ Meta Title moet tussen 30-60 tekens zijn</li>';
    }

    // 2. Meta Description lengte
    if (strlen($meta_description) >= 70 && strlen($meta_description) <= 160) {
        echo '<li style="color:green;">✅ Meta Description lengte is goed</li>';
        $score++;
    } else {
        echo '<li style="color:red;">❌ Meta Description moet tussen 70-160 tekens zijn</li>';
    }

    // 3. Hoofd keyword ingevuld
    if (!empty($main_kw)) {
        echo '<li style="color:green;">✅ Hoofd keyword is ingevuld</li>';
        $score++;
    } else {
        echo '<li style="color:red;">❌ Geen hoofd keyword</li>';
    }

    // 4. Minstens 2 extra keywords ingevuld
    $filled_keywords = array_filter($extra_keywords);
    if (count($filled_keywords) >= 2) {
        echo '<li style="color:green;">✅ Minstens 2 extra keywords zijn ingevuld</li>';
        $score++;
    } else {
        echo '<li style="color:red;">❌ Te weinig extra keywords (min. 2 aanbevolen)</li>';
    }

    // --- Extra checks ---

    // 5. Keyword density (frequentie) (we tellen aantal woorden, aantal keyword)
    if (!empty($main_kw)) {
        $content_text = wp_strip_all_tags($content);
        $words = str_word_count(strtolower($content_text));
        $keyword_count = substr_count(strtolower($content_text), strtolower($main_kw));
        $density = $words > 0 ? ($keyword_count / $words) * 100 : 0;

        if ($density >= 0.5 && $density <= 3) { // 0.5% - 3% is redelijk
            echo '<li style="color:green;">✅ Keyword density is goed (' . round($density, 2) . '%)</li>';
            $score++;
        } else {
            echo '<li style="color:red;">❌ Keyword density is ' . round($density, 2) . '%. Tussen 0.5% en 3% aanbevolen</li>';
        }
    } else {
        echo '<li style="color:red;">❌ Kan keyword density niet checken zonder hoofdkeyword</li>';
    }

    // 6. Keyword in eerste 10% content
    if (!empty($main_kw)) {
        $first_10_percent_length = intval(strlen($content_text) * 0.1);
        $first_10_percent = substr($content_text, 0, $first_10_percent_length);
        if (stripos($first_10_percent, $main_kw) !== false) {
            echo '<li style="color:green;">✅ Keyword staat in eerste 10% van de content</li>';
            $score++;
        } else {
            echo '<li style="color:red;">❌ Keyword niet in eerste 10% van de content gevonden</li>';
        }
    }

    // 7. Keyword in H1 tag
    if (!empty($main_kw)) {
        preg_match_all('/<h1[^>]*>(.*?)<\/h1>/i', $content, $matches);
        $h1_contains_kw = false;
        if (!empty($matches[1])) {
            foreach ($matches[1] as $h1) {
                if (stripos($h1, $main_kw) !== false) {
                    $h1_contains_kw = true;
                    break;
                }
            }
        }
        if ($h1_contains_kw) {
            echo '<li style="color:green;">✅ Keyword staat in H1 tag</li>';
            $score++;
        } else {
            echo '<li style="color:red;">❌ Keyword staat niet in H1 tag</li>';
        }
    }

    // 8. Keyword in H2 or H3 tags
    if (!empty($main_kw)) {
        preg_match_all('/<(h2|h3)[^>]*>(.*?)<\/\1>/i', $content, $matches);
        $header_contains_kw = false;
        if (!empty($matches[2])) {
            foreach ($matches[2] as $header) {
                if (stripos($header, $main_kw) !== false) {
                    $header_contains_kw = true;
                    break;
                }
            }
        }
        if ($header_contains_kw) {
            echo '<li style="color:green;">✅ Keyword staat in H2 of H3 tag</li>';
            $score++;
        } else {
            echo '<li style="color:red;">❌ Keyword staat niet in H2 of H3 tags</li>';
        }
    }

    // 9. Content bevat minstens 1 lijst (ul of ol)
    if (preg_match('/<(ul|ol)[^>]*>/', $content)) {
        echo '<li style="color:green;">✅ Content bevat minstens 1 lijst (ul of ol)</li>';
        $score++;
    } else {
        echo '<li style="color:red;">❌ Content bevat geen lijsten (ul of ol)</li>';
    }

    // 10. Gemiddelde paragraaf lengte < 150 woorden
    preg_match_all('/<p[^>]*>(.*?)<\/p>/i', $content, $matches);
    $par_lengths = [];
    if (!empty($matches[1])) {
        foreach ($matches[1] as $para) {
            $text = wp_strip_all_tags($para);
            $par_lengths[] = str_word_count($text);
        }
        $avg_par_length = array_sum($par_lengths) / count($par_lengths);
        if ($avg_par_length <= 150) {
            echo '<li style="color:green;">✅ Gemiddelde paragraaflengte is goed (' . round($avg_par_length) . ' woorden)</li>';
            $score++;
        } else {
            echo '<li style="color:red;">❌ Gemiddelde paragraaflengte is te lang (' . round($avg_par_length) . ' woorden), idealiter ≤ 150</li>';
        }
    } else {
        echo '<li style="color:red;">❌ Geen paragrafen gevonden om lengte te checken</li>';
    }

    // 11. Minstens 1 interne link (<a href> naar eigen domein)
    $site_url = home_url();
    preg_match_all('/<a[^>]+href=["\']([^"\']+)["\'][^>]*>/i', $content, $links);
    $internal_link_found = false;
    if (!empty($links[1])) {
        foreach ($links[1] as $link) {
            if (strpos($link, $site_url) === 0) {
                $internal_link_found = true;
                break;
            }
        }
    }
    if ($internal_link_found) {
        echo '<li style="color:green;">✅ Minstens 1 interne link aanwezig</li>';
        $score++;
    } else {
        echo '<li style="color:red;">❌ Geen interne links gevonden</li>';
    }

    // 12. Minstens 1 externe link (link niet naar eigen domein)
    $external_link_found = false;
    if (!empty($links[1])) {
        foreach ($links[1] as $link) {
            if (strpos($link, $site_url) !== 0 && (strpos($link, 'http') === 0 || strpos($link, '//') === 0)) {
                $external_link_found = true;
                break;
            }
        }
    }
    if ($external_link_found) {
        echo '<li style="color:green;">✅ Minstens 1 externe link aanwezig</li>';
        $score++;
    } else {
        echo '<li style="color:red;">❌ Geen externe links gevonden</li>';
    }

    // 13. Content bevat afbeeldingen
    preg_match_all('/<img[^>]+>/i', $content, $images);
    if (!empty($images[0])) {
        echo '<li style="color:green;">✅ Content bevat afbeeldingen</li>';
        // Check of minstens 1 afbeelding alt bevat hoofdkeyword
        $alt_with_keyword = false;
        foreach ($images[0] as $img_tag) {
            preg_match('/alt=["\']([^"\']*)["\']/', $img_tag, $alt_match);
            if (!empty($alt_match[1]) && !empty($main_kw) && stripos($alt_match[1], $main_kw) !== false) {
                $alt_with_keyword = true;
                break;
            }
        }
        if ($alt_with_keyword) {
            echo '<li style="color:green;">✅ Minstens 1 afbeelding heeft alt-tag met hoofdkeyword</li>';
            $score++;
        } else {
            echo '<li style="color:red;">❌ Geen afbeelding met alt-tag die hoofdkeyword bevat</li>';
        }
    } else {
        echo '<li style="color:red;">❌ Geen afbeeldingen in content</li>';
    }

    // 14. Duplicate title tag waarschuwing binnen site
    if (!empty($meta_title)) {
        global $wpdb;
        $count = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->postmeta} pm
            INNER JOIN {$wpdb->posts} p ON p.ID = pm.post_id
            WHERE pm.meta_key = '_custom_meta_title' AND pm.meta_value = %s AND p.ID != %d AND p.post_status = 'publish'",
            $meta_title, $post->ID
        ));
        if ($count > 0) {
            echo '<li style="color:red;">❌ Meta Title komt ook op ' . $count . ' andere pagina(s) voor</li>';
        } else {
            echo '<li style="color:green;">✅ Meta Title is uniek binnen site</li>';
            $score++;
        }
    } else {
        echo '<li style="color:red;">❌ Geen Meta Title ingevuld om duplicate te controleren</li>';
    }

    // 15. Duplicate meta description waarschuwing binnen site
    if (!empty($meta_description)) {
        global $wpdb;
        $count = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->postmeta} pm
            INNER JOIN {$wpdb->posts} p ON p.ID = pm.post_id
            WHERE pm.meta_key = '_custom_meta_description' AND pm.meta_value = %s AND p.ID != %d AND p.post_status = 'publish'",
            $meta_description, $post->ID
        ));
        if ($count > 0) {
            echo '<li style="color:red;">❌ Meta Description komt ook op ' . $count . ' andere pagina(s) voor</li>';
        } else {
            echo '<li style="color:green;">✅ Meta Description is uniek binnen site</li>';
            $score++;
        }
    } else {
        echo '<li style="color:red;">❌ Geen Meta Description ingevuld om duplicate te controleren</li>';
    }

    $percentage = round(($score / $max_score) * 100);
    $color = '#f44336';
    if ($percentage > 75) $color = '#4caf50';
    elseif ($percentage > 25) $color = '#ffc107';
    ?>
    </ul>
    <p><strong>SEO Score:</strong> <?php echo $percentage; ?>%</p>
    <div style="background:#ddd;width:100%;height:20px;">
        <div style="width:<?php echo $percentage; ?>%;background:<?php echo $color; ?>;height:100%;"></div>
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
});

add_action('wp_head', function() {
    if (is_singular()) {
        global $post;
        $description = get_post_meta($post->ID, '_custom_meta_description', true);
        $main_kw = get_post_meta($post->ID, '_custom_main_keyword', true);
        $keywords = [];

        if (!empty($main_kw)) $keywords[] = $main_kw;
        for ($i = 1; $i <= 4; $i++) {
            $val = get_post_meta($post->ID, "_custom_extra_keyword_$i", true);
            if (!empty($val)) $keywords[] = $val;
        }

        if ($description) echo '<meta name="description" content="' . esc_attr($description) . '">' . "\n";
        if (!empty($keywords)) echo '<meta name="keywords" content="' . esc_attr(implode(', ', array_unique($keywords))) . '">' . "\n";
    }
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

// Score tonen in pagina-overzicht (admin columns)
add_filter('manage_posts_columns', function($columns) {
    $columns['seo_score'] = 'SEO Score';
    return $columns;
});

add_action('manage_posts_custom_column', function($column_name, $post_id) {
    if ($column_name === 'seo_score') {
        $score = calculate_seo_score_for_post($post_id);
        $color = '#f44336';
        if ($score > 75) $color = '#4caf50';
        elseif ($score > 25) $color = '#ffc107';
        echo '<div style="background:#ddd;width:100%;height:15px;"><div style="width:' . $score . '%;background:' . $color . ';height:100%;"></div></div>';
        echo '<small>' . $score . '%</small>';
    }
}, 10, 2);

function calculate_seo_score_for_post($post_id) {
    $score = 0;
    $max_score = 12;

    $meta_title = get_post_meta($post_id, '_custom_meta_title', true);
    $meta_description = get_post_meta($post_id, '_custom_meta_description', true);
    $main_kw = get_post_meta($post_id, '_custom_main_keyword', true);
    $extra_keywords = [];
    for ($i = 1; $i <= 4; $i++) {
        $extra_keywords[$i] = get_post_meta($post_id, "_custom_extra_keyword_$i", true);
    }
    $post_obj = get_post($post_id);
    $content = $post_obj ? $post_obj->post_content : '';

    // zelfde checks als in metabox, maar kort gehouden voor performance

    if (strlen($meta_title) >= 30 && strlen($meta_title) <= 60) $score++;
    if (strlen($meta_description) >= 70 && strlen($meta_description) <= 160) $score++;
    if (!empty($main_kw)) $score++;
    if (count(array_filter($extra_keywords)) >= 2) $score++;

    if (!empty($main_kw)) {
        $content_text = wp_strip_all_tags($content);
        $words = str_word_count(strtolower($content_text));
        $keyword_count = substr_count(strtolower($content_text), strtolower($main_kw));
        $density = $words > 0 ? ($keyword_count / $words) * 100 : 0;
        if ($density >= 0.5 && $density <= 3) $score++;
        $first_10_percent_length = intval(strlen($content_text) * 0.1);
        $first_10_percent = substr($content_text, 0, $first_10_percent_length);
        if (stripos($first_10_percent, $main_kw) !== false) $score++;

        preg_match_all('/<h1[^>]*>(.*?)<\/h1>/i', $content, $matches);
        if (!empty($matches[1])) {
            foreach ($matches[1] as $h1) {
                if (stripos($h1, $main_kw) !== false) {
                    $score++;
                    break;
                }
            }
        }

        preg_match_all('/<(h2|h3)[^>]*>(.*?)<\/\1>/i', $content, $matches);
        if (!empty($matches[2])) {
            foreach ($matches[2] as $header) {
                if (stripos($header, $main_kw) !== false) {
                    $score++;
                    break;
                }
            }
        }
    }

    if (preg_match('/<(ul|ol)[^>]*>/', $content)) $score++;

    preg_match_all('/<p[^>]*>(.*?)<\/p>/i', $content, $matches);
    $par_lengths = [];
    if (!empty($matches[1])) {
        foreach ($matches[1] as $para) {
            $text = wp_strip_all_tags($para);
            $par_lengths[] = str_word_count($text);
        }
        $avg_par_length = array_sum($par_lengths) / count($par_lengths);
        if ($avg_par_length <= 150) $score++;
    }

    return round(($score / $max_score) * 100);
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
                $color = '#f44336';
                if ($score > 75) $color = '#4caf50';
                elseif ($score > 25) $color = '#ffc107';
                echo '<div style="background:#ddd;width:100%;height:15px;"><div style="width:' . $score . '%;background:' . $color . ';height:100%;"></div></div>';
                echo '<small>' . $score . '%</small>';
            }
        }, 10, 2);
    }
});