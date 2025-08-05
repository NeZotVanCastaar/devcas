<?php


///////////////////////////////////////////////////
// FUNCTIE: Alt-tag genereren vanuit bestandsnaam
///////////////////////////////////////////////////

function castaar_generate_clean_alt($filename) {
    $name = pathinfo($filename, PATHINFO_FILENAME);
    $name = urldecode($name);

    // Strip veelvoorkomende WordPress suffixes zoals "-scaled"
    $name = preg_replace('/-(scaled|edited|cropped|resized|compressed)$/i', '', $name);

    // Verwijder speciale tekens
    $name = preg_replace('/[^a-zA-Z0-9\-\_\.\s]/', '', $name);

    // Vervang verbindingssymbolen door spatie
    $name = str_replace(['-', '_', '.', '%20'], ' ', $name);

    // Verwijder ruiswoorden
    $noise = ['img', 'image', 'foto', 'copy', 'final', 'nieuw', 'edit', 'v1', 'v2'];
    foreach ($noise as $word) {
        $name = preg_replace('/\b' . preg_quote($word, '/') . '\b/i', '', $name);
    }

    // Verwijder dubbele spaties
    $name = preg_replace('/\s+/', ' ', trim($name));

    return strtolower($name);
}


///////////////////////////////////////////////////
// 1. Automatische alt-tag bij upload
///////////////////////////////////////////////////

add_action('add_attachment', 'castaar_auto_alt_on_upload');

function castaar_auto_alt_on_upload($attachment_id) {
    if (wp_attachment_is_image($attachment_id)) {
        $alt = get_post_meta($attachment_id, '_wp_attachment_image_alt', true);
        if (empty($alt)) {
            $attachment = get_post($attachment_id);
            $filename = basename($attachment->guid);
            $alt_text = castaar_generate_clean_alt($filename);
            update_post_meta($attachment_id, '_wp_attachment_image_alt', $alt_text);
        }
    }
}

///////////////////////////////////////////////////
// 2. Bulk ALT-tags genereren via admin
///////////////////////////////////////////////////

add_action('admin_menu', 'castaar_alt_generator_menu');

function castaar_alt_generator_menu() {
     if (!current_user_can('administrator')) return;
    add_media_page(
        'ALT-tags genereren',
        'ALT-tags genereren',
        'manage_options',
        'castaar-alt-generator',
        'castaar_bulk_alt_page'
    );
}

function castaar_bulk_alt_page() {
    if (isset($_POST['castaar_generate_alt_tags'])) {
        $updated = castaar_generate_alt_for_existing_images();
        echo "<div class='updated'><p><strong>$updated afbeeldingen bijgewerkt.</strong></p></div>";
    }

    ?>
    <div class="wrap">
        <h1>Castaar ALT Generator</h1>
        <p>Klik op onderstaande knop om automatisch alt-tags toe te voegen aan alle bestaande afbeeldingen zonder alt-tag.</p>
        <form method="post">
            <input type="submit" name="castaar_generate_alt_tags" class="button-primary" value="Start ALT-generatie">
        </form>
    </div>
    <?php
}

function castaar_generate_alt_for_existing_images() {
    $args = [
        'post_type' => 'attachment',
        'post_mime_type' => 'image',
        'post_status' => 'inherit',
        'posts_per_page' => -1,
    ];
    $query = new WP_Query($args);
    $count = 0;

    foreach ($query->posts as $image) {
        $alt = get_post_meta($image->ID, '_wp_attachment_image_alt', true);
        if (empty($alt)) {
            $filename = basename($image->guid);
            $alt_text = castaar_generate_clean_alt($filename);
            update_post_meta($image->ID, '_wp_attachment_image_alt', $alt_text);
            $count++;
        }
    }

    return $count;
}
