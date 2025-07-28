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
 

    <!-- Checklist kolom -->
    <div style="flex:1;">
        <h4>📋 SEO Checklist & Score</h4>
     <ul>


    <?php
    $score = 0;
    $max_score = 20;

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

// 16. Hoofd keyword in Meta Title
if (!empty($main_kw) && stripos($meta_title, $main_kw) !== false) {
    echo '<li style="color:green;">✅ Hoofd keyword staat in Meta Title</li>';
    $score++;
} else {
    echo '<li style="color:red;">❌ Hoofd keyword ontbreekt in Meta Title</li>';
}

// 17. Hoofd keyword in Meta Description
if (!empty($main_kw) && stripos($meta_description, $main_kw) !== false) {
    echo '<li style="color:green;">✅ Hoofd keyword staat in Meta Description</li>';
    $score++;
} else {
    echo '<li style="color:red;">❌ Hoofd keyword ontbreekt in Meta Description</li>';
}

// 18. Hoofd keyword in URL
$post_url = get_permalink($post);
if (!empty($main_kw) && stripos($post_url, sanitize_title($main_kw)) !== false) {
    echo '<li style="color:green;">✅ Hoofd keyword staat in de URL</li>';
    $score++;
} else {
    echo '<li style="color:red;">❌ Hoofd keyword staat niet in de URL</li>';
}

// 19. Inhoud bevat minstens 300 woorden
$word_count = str_word_count($content_text);
if ($word_count >= 300) {
    echo '<li style="color:green;">✅ Inhoud bevat minstens 300 woorden (' . $word_count . ')</li>';
    $score++;
} else {
    echo '<li style="color:red;">❌ Inhoud bevat slechts ' . $word_count . ' woorden, minimum is 300</li>';
}

// 20. Uniek hoofd keyword
if (!empty($main_kw)) {
    global $wpdb;
$count_kw = $wpdb->get_var($wpdb->prepare(
    "SELECT COUNT(*) FROM {$wpdb->postmeta} pm
     INNER JOIN {$wpdb->posts} p ON p.ID = pm.post_id
     WHERE pm.meta_key = '_custom_main_keyword' AND LOWER(pm.meta_value) = LOWER(%s) AND p.ID != %d AND p.post_status = 'publish'",
    $main_kw, $post->ID
));

    if ($count_kw > 0) {
        echo '<li style="color:red;">❌ Focus keyword komt ook voor op ' . $count_kw . ' andere pagina(s)</li>';
    } else {
        echo '<li style="color:green;">✅ Focus keyword is uniek binnen de site</li>';
        $score++;
    }
} else {
    echo '<li style="color:red;">❌ Geen hoofd keyword ingevuld om te controleren op duplicaat</li>';
}



$percentage = round(($score / $max_score) * 100);
$color = '#f44336';
if ($percentage > 75) $color = '#4caf50';
elseif ($percentage > 25) $color = '#ffc107';
?>
</ul>

</div>
   <!-- Keyword-analyse kolom -->
    <div style="flex:2;">
        <h4>🔎 Keyword Sterkte Analyse</h4>
        <ul>
        <?php
        foreach (array_filter(array_merge([$main_kw], $extra_keywords)) as $kw) {
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
echo "<li style='color:{$kw_color}; margin-bottom:5px;'>🔍 <strong>" . esc_html($kw) . "</strong>: {$label} ({$occurrences}x)</li>";

        }
        ?>
        </ul>
    </div>

</div> 

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
        <div class="seo-score-bar-inner" style="width:<?php echo $percentage; ?>%; background:<?php echo $color; ?>;"></div>
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


add_action('manage_posts_custom_column', function($column_name, $post_id) {
    if ($column_name === 'seo_score') {
        $score = calculate_seo_score_for_post($post_id);
        $main_kw = get_post_meta($post_id, '_custom_main_keyword', true);
        $content = get_post_field('post_content', $post_id);
        $site_url = home_url();

        // kleuren
        $bg = '#f44336'; // rood
        if ($score >= 75) $bg = '#4caf50'; // groen
        elseif ($score >= 25) $bg = '#ffc107'; // geel

        // badge
        echo '<div style="display:inline-block;padding:4px 8px;border-radius:6px;font-weight:bold;font-size:13px;background:' . $bg . ';color:#fff;margin-bottom:5px;">' . $score . ' / 100</div>';

        // hoofdkeyword
        if (!empty($main_kw)) {
            echo '<div style="margin-top:4px;font-size:12px;color:#555;"><strong>Keyword:</strong> ' . esc_html($main_kw) . '</div>';
        }

        // link-analyse
        $internal_links = 0;
        $external_links = 0;
        $media_links = 0;
        $total_links = 0;

        preg_match_all('/<a[^>]+href=["\']([^"\']+)["\']/', $content, $matches);
        if (!empty($matches[1])) {
            foreach ($matches[1] as $url) {
                $total_links++;
                if (strpos($url, $site_url) === 0) {
                    $internal_links++;
                } elseif (preg_match('#\.(jpg|jpeg|png|gif|webp|pdf|docx?|mp4|mp3)#i', $url)) {
                    $media_links++;
                } elseif (strpos($url, 'http') === 0 || strpos($url, '//') === 0) {
                    $external_links++;
                }
            }
        }

        echo '<div style="margin-top:4px;font-size:12px;color:#555;">';
        echo '<strong>Links:</strong> 🔗 ' . $internal_links . ' | 🌐 ' . $external_links . ' | 🖼️ ' . $media_links . ' | 📊 ' . $total_links;
        echo '</div>';
    }
}, 10, 2);



function calculate_seo_score_for_post($post_id) {
    $score = 0;
    $max_score = 20;

    $meta_title = get_post_meta($post_id, '_custom_meta_title', true);
    $meta_description = get_post_meta($post_id, '_custom_meta_description', true);
    $main_kw = get_post_meta($post_id, '_custom_main_keyword', true);
    $extra_keywords = [];
    for ($i = 1; $i <= 4; $i++) {
        $extra_keywords[$i] = get_post_meta($post_id, "_custom_extra_keyword_$i", true);
    }

    $post = get_post($post_id);
    $content = $post ? $post->post_content : '';
    $content_text = wp_strip_all_tags($content);
    $site_url = home_url();

    // 1. Meta Title lengte
    if (strlen($meta_title) >= 30 && strlen($meta_title) <= 60) $score++;

    // 2. Meta Description lengte
    if (strlen($meta_description) >= 70 && strlen($meta_description) <= 160) $score++;

    // 3. Hoofd keyword ingevuld
    if (!empty($main_kw)) $score++;

    // 4. Minstens 2 extra keywords ingevuld
    if (count(array_filter($extra_keywords)) >= 2) $score++;

    // 5. Keyword density
    if (!empty($main_kw)) {
        $words = str_word_count(strtolower($content_text));
        $keyword_count = substr_count(strtolower($content_text), strtolower($main_kw));
        $density = $words > 0 ? ($keyword_count / $words) * 100 : 0;
        if ($density >= 0.5 && $density <= 3) $score++;
    }

    // 6. Keyword in eerste 10% content
    if (!empty($main_kw)) {
        $first_10_percent_length = intval(strlen($content_text) * 0.1);
        $first_10_percent = substr($content_text, 0, $first_10_percent_length);
        if (stripos($first_10_percent, $main_kw) !== false) $score++;
    }

    // 7. Keyword in H1 tag
    if (!empty($main_kw)) {
        preg_match_all('/<h1[^>]*>(.*?)<\/h1>/i', $content, $matches);
        foreach ($matches[1] as $h1) {
            if (stripos($h1, $main_kw) !== false) {
                $score++;
                break;
            }
        }
    }

    // 8. Keyword in H2 of H3 tags
    if (!empty($main_kw)) {
        preg_match_all('/<(h2|h3)[^>]*>(.*?)<\/\1>/i', $content, $matches);
        foreach ($matches[2] as $header) {
            if (stripos($header, $main_kw) !== false) {
                $score++;
                break;
            }
        }
    }

    // 9. Content bevat lijst
    if (preg_match('/<(ul|ol)[^>]*>/', $content)) $score++;

    // 10. Gemiddelde paragraaflengte
    preg_match_all('/<p[^>]*>(.*?)<\/p>/i', $content, $matches);
    $par_lengths = [];
    foreach ($matches[1] as $para) {
        $par_lengths[] = str_word_count(wp_strip_all_tags($para));
    }
    if ($par_lengths) {
        $avg_par_length = array_sum($par_lengths) / count($par_lengths);
        if ($avg_par_length <= 150) $score++;
    }

    // 11. Interne link
    preg_match_all('/<a[^>]+href=["\']([^"\']+)["\'][^>]*>/i', $content, $links);
    foreach ($links[1] as $link) {
        if (strpos($link, $site_url) === 0) {
            $score++;
            break;
        }
    }

    // 12. Externe link
    foreach ($links[1] as $link) {
        if (strpos($link, $site_url) !== 0 && preg_match('#^https?://#', $link)) {
            $score++;
            break;
        }
    }

    // 13. Afbeeldingen met alt en keyword
    preg_match_all('/<img[^>]+>/i', $content, $images);
    if (!empty($images[0])) {
        $score++;
        foreach ($images[0] as $img_tag) {
            preg_match('/alt=["\']([^"\']*)["\']/', $img_tag, $alt_match);
            if (!empty($alt_match[1]) && stripos($alt_match[1], $main_kw) !== false) {
                $score++;
                break;
            }
        }
    }

    // 14. Duplicate meta title
    if (!empty($meta_title)) {
        global $wpdb;
        $count = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->postmeta} pm
            INNER JOIN {$wpdb->posts} p ON p.ID = pm.post_id
            WHERE pm.meta_key = '_custom_meta_title' AND pm.meta_value = %s AND p.ID != %d AND p.post_status = 'publish'",
            $meta_title, $post_id
        ));
        if ($count == 0) $score++;
    }

    // 15. Duplicate meta description
    if (!empty($meta_description)) {
        global $wpdb;
        $count = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->postmeta} pm
            INNER JOIN {$wpdb->posts} p ON p.ID = pm.post_id
            WHERE pm.meta_key = '_custom_meta_description' AND pm.meta_value = %s AND p.ID != %d AND p.post_status = 'publish'",
            $meta_description, $post_id
        ));
        if ($count == 0) $score++;
    }

    // 16. Keyword in meta title
    if (!empty($main_kw) && stripos($meta_title, $main_kw) !== false) $score++;

    // 17. Keyword in meta description
    if (!empty($main_kw) && stripos($meta_description, $main_kw) !== false) $score++;

    // 18. Keyword in URL
    $url = get_permalink($post_id);
    if (!empty($main_kw) && stripos($url, sanitize_title($main_kw)) !== false) $score++;

    // 19. Minstens 300 woorden content
    if (str_word_count($content_text) >= 300) $score++;

    // 20. Uniek hoofd keyword
    if (!empty($main_kw)) {
        global $wpdb;
        $count_kw = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->postmeta} pm
             INNER JOIN {$wpdb->posts} p ON p.ID = pm.post_id
             WHERE pm.meta_key = '_custom_main_keyword' AND LOWER(pm.meta_value) = LOWER(%s) AND p.ID != %d AND p.post_status = 'publish'",
            $main_kw, $post_id
        ));
        if ($count_kw == 0) $score++;
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
