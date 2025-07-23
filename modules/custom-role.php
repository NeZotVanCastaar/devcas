<?php
/**
 * Plugin Name: Pagina Editor Rol
 * Description: Voegt een aangepaste rol toe die pagina's, berichten en CPT's kan bewerken in Elementor, inclusief toegang tot media en Elementor inzendingen.
 * Version: 1.0
 * Author: Alec Meganck
 */

add_action('init', function () {
    // Voeg rol toe als die nog niet bestaat
    if (!get_role('pagina_editor')) {
        add_role('pagina_editor', 'Pagina Editor', [
            'read' => true,
            'upload_files' => true,
        ]);
    }

    $role = get_role('pagina_editor');

    if ($role) {
        // ===== PAGINA'S =====
        $role->add_cap('edit_pages');
        $role->add_cap('edit_others_pages');
        $role->add_cap('edit_published_pages');
        $role->add_cap('publish_pages');
        $role->add_cap('delete_pages');
        $role->add_cap('delete_others_pages');
        $role->add_cap('delete_published_pages');

        // ===== BERICHTEN (POSTS) =====
        $role->add_cap('edit_posts');
        $role->add_cap('edit_others_posts');
        $role->add_cap('edit_published_posts');
        $role->add_cap('publish_posts');
        $role->add_cap('delete_posts');
        $role->add_cap('delete_others_posts');
        $role->add_cap('delete_published_posts');

        // ===== CPT's =====
        $cpts = get_post_types(['public' => true], 'names');
        foreach ($cpts as $cpt) {
            $role->add_cap("edit_{$cpt}");
            $role->add_cap("edit_{$cpt}s");
            $role->add_cap("edit_others_{$cpt}s");
            $role->add_cap("edit_published_{$cpt}s");
            $role->add_cap("publish_{$cpt}s");

            $role->add_cap("delete_{$cpt}");
            $role->add_cap("delete_{$cpt}s");
            $role->add_cap("delete_others_{$cpt}s");
            $role->add_cap("delete_published_{$cpt}s");

            $role->add_cap("read_{$cpt}");
            $role->add_cap("read_private_{$cpt}s");
        }

        // ===== ELEMENTOR =====
        $role->add_cap('edit_elementor_library');

        // ===== ELEMENTOR FORM INZENDINGEN (PRO) =====
        $role->add_cap('read_elementor_pro_forms');

        // ===== GOOGLE SITE KIT DASHBOARD =====
        $role->add_cap('googlesitekit_view_dashboard');
    }
});
