<?php
if (!defined('ABSPATH')) exit;

/**
 * Maak/werk de rol 'pagina_editor' bij.
 * - Roep dit bij activatie aan
 * - En fallback 1x op init als de rol ontbreekt
 */
function castaar_register_pagina_editor_role() {
    // Rol aanmaken indien niet bestaat
    if (!get_role('pagina_editor')) {
        add_role('pagina_editor', 'Pagina Editor', [
            'read'         => true,
            'upload_files' => true,
        ]);
    }

    $role = get_role('pagina_editor');
    if (!$role) return;

    // ===== Basiscaps: PAGINA'S =====
    $page_caps = [
        'edit_pages',
        'edit_others_pages',
        'edit_published_pages',
        'publish_pages',
        'delete_pages',
        'delete_others_pages',
        'delete_published_pages',
        'read', // al gezet, maar kan geen kwaad
        'upload_files',
    ];
    foreach ($page_caps as $cap) { $role->add_cap($cap); }

    // ===== BERICHTEN =====
    $post_caps = [
        'edit_posts',
        'edit_others_posts',
        'edit_published_posts',
        'publish_posts',
        'delete_posts',
        'delete_others_posts',
        'delete_published_posts',
    ];
    foreach ($post_caps as $cap) { $role->add_cap($cap); }

    // ===== CPT's: gebruik de echte caps van het post type =====
    $cpts = get_post_types(['public' => true, 'show_ui' => true], 'objects');
    foreach ($cpts as $cpt => $obj) {
        // Sla 'attachment' over
        if ($cpt === 'attachment') continue;
        if (empty($obj->cap) || !is_object($obj->cap)) continue;

        // Typische relevante caps
        $maybe_caps = [
            'edit_post', 'read_post', 'delete_post',        // meta caps (map_meta_cap true)
            'edit_posts', 'edit_others_posts', 'edit_published_posts',
            'publish_posts', 'delete_posts', 'delete_others_posts', 'delete_published_posts',
            'read', 'read_private_posts',
        ];

        foreach ($maybe_caps as $key) {
            if (!empty($obj->cap->$key)) {
                $role->add_cap($obj->cap->$key);
            }
        }
    }

    // ===== Elementor =====
    $role->add_cap('edit_elementor_library');

    // ===== Elementor Pro (forms lezen) – alleen als plugin aanwezig =====
    // (cap bestaat enkel met Elementor Pro)
    $role->add_cap('read_elementor_pro_forms');

    // ===== Google Site Kit (dashboard view) =====
    $role->add_cap('googlesitekit_view_dashboard');
}

/** Eenmalig bij activatie */
register_activation_hook(__FILE__, 'castaar_register_pagina_editor_role');

/** Fallback: als de rol ontbreekt (na bv. import), herstel op init */
add_action('init', function () {
    if (!get_role('pagina_editor')) {
        castaar_register_pagina_editor_role();
    }
});
