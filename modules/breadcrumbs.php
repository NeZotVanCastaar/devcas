<?php
/**
 * DevCas – Breadcrumbs module
 *
 * Bestandsnaam:
 *   /wp-content/plugins/devcas/modules/breadcrumbs.php
 */

defined('ABSPATH') || exit;

/**
 * Genereer HTML voor breadcrumbs.
 *
 * @param array $args {
 *     @type int    $back       Hoeveel ancestor-niveaus terug te gaan (0 = alle).
 *     @type string $separator  HTML-separator tussen items.
 *     @type string $class      CSS-class op de <nav>.
 *     @type string $home_label Label voor de home-link.
 * }
 *
 * @return string
 */
function devcas_get_breadcrumbs_html( $args = array() ) {
    global $post;

    $defaults = array(
        'back'       => 0, // 0 = alle ancestors tonen
        'separator'  => ' &raquo; ',
        'class'      => 'devcas-breadcrumbs',
        'home_label' => 'Home',
    );

    $args = wp_parse_args( $args, $defaults );

    // Op de front page meestal geen breadcrumbs tonen
    if ( is_front_page() ) {
        return '';
    }

    $crumbs = array();

    // Altijd beginnen met Home
    $crumbs[] = array(
        'url'        => home_url( '/' ),
        'label'      => $args['home_label'],
        'is_current' => false,
    );

    /**
     * PAGINA’S – hiërarchisch (typische Elementor site)
     */
    if ( is_page() && $post instanceof WP_Post ) {

        // Alle ancestors ophalen (array IDs)
        $ancestors = get_post_ancestors( $post->ID );
        $ancestors = array_reverse( $ancestors ); // root → parent → ...

        // BACK-limit toepassen: toon alleen de laatste X ancestors
        if ( ! empty( $args['back'] ) && $args['back'] > 0 ) {
            $ancestors_count = count( $ancestors );
            if ( $ancestors_count > $args['back'] ) {
                $ancestors = array_slice( $ancestors, $ancestors_count - $args['back'] );
            }
        }

        // Ancestors toevoegen
        foreach ( $ancestors as $ancestor_id ) {
            $crumbs[] = array(
                'url'        => get_permalink( $ancestor_id ),
                'label'      => get_the_title( $ancestor_id ),
                'is_current' => false,
            );
        }

        // Huidige pagina (zonder link)
        $crumbs[] = array(
            'url'        => '',
            'label'      => get_the_title( $post->ID ),
            'is_current' => true,
        );

    /**
     * BLOGPOSTS / CUSTOM POSTS
     * (compact gehouden maar makkelijk uitbreidbaar)
     */
    } elseif ( is_single() && $post instanceof WP_Post ) {

        $post_type = get_post_type( $post );

        if ( $post_type === 'post' ) {
            // Eventuele "blogpagina" als posts page
            $posts_page_id = (int) get_option( 'page_for_posts' );
            if ( $posts_page_id ) {
                $crumbs[] = array(
                    'url'        => get_permalink( $posts_page_id ),
                    'label'      => get_the_title( $posts_page_id ),
                    'is_current' => false,
                );
            }

            // Eerste categorie als extra crumb (optioneel)
            $cats = get_the_category( $post->ID );
            if ( ! empty( $cats ) ) {
                $primary_cat = $cats[0];
                $crumbs[] = array(
                    'url'        => get_category_link( $primary_cat->term_id ),
                    'label'      => $primary_cat->name,
                    'is_current' => false,
                );
            }

        } else {
            // Custom post type archief, indien beschikbaar
            $pt_obj = get_post_type_object( $post_type );
            if ( $pt_obj && ! empty( $pt_obj->has_archive ) ) {
                $crumbs[] = array(
                    'url'        => get_post_type_archive_link( $post_type ),
                    'label'      => $pt_obj->labels->name,
                    'is_current' => false,
                );
            }
        }

        // Huidige post-titel
        $crumbs[] = array(
            'url'        => '',
            'label'      => get_the_title( $post->ID ),
            'is_current' => true,
        );

    /**
     * ARCHIVES / SEARCH / 404
     */
    } elseif ( is_archive() ) {

        $crumbs[] = array(
            'url'        => '',
            'label'      => get_the_archive_title(),
            'is_current' => true,
        );

    } elseif ( is_search() ) {

        $crumbs[] = array(
            'url'        => '',
            'label'      => sprintf( 'Zoekresultaten voor: %s', get_search_query() ),
            'is_current' => true,
        );

    } elseif ( is_404() ) {

        $crumbs[] = array(
            'url'        => '',
            'label'      => 'Pagina niet gevonden',
            'is_current' => true,
        );
    }

    // HTML opbouwen
    $html  = '<nav class="' . esc_attr( $args['class'] ) . '" aria-label="Breadcrumbs">';
    $html .= '<ol>';

    $total = count( $crumbs );
    $index = 0;

    foreach ( $crumbs as $crumb ) {
        $index++;
        $is_last = ( $index === $total );

        $html .= '<li>';

        if ( ! empty( $crumb['url'] ) && ! $crumb['is_current'] ) {
            $html .= '<a href="' . esc_url( $crumb['url'] ) . '">'
                  . esc_html( $crumb['label'] ) . '</a>';
        } else {
            $html .= '<span class="current">' . esc_html( $crumb['label'] ) . '</span>';
        }

        if ( ! $is_last ) {
            $html .= '<span class="separator">' . $args['separator'] . '</span>';
        }

        $html .= '</li>';
    }

    $html .= '</ol>';
    $html .= '</nav>';

    return $html;
}

/**
 * Shortcode callback
 *
 * Gebruik:
 *   [devcas_breadcrumbs]
 *   [devcas_breadcrumbs back="2"]
 *   [devcas_breadcrumbs back="1" separator=" / " class="castaar-breadcrumbs"]
 *
 * @param array $atts
 * @return string
 */
function devcas_breadcrumbs_shortcode( $atts ) {
    $atts = shortcode_atts(
        array(
            'back'       => 0,                // 0 = alle ancestors tonen
            'separator'  => ' &raquo; ',
            'class'      => 'devcas-breadcrumbs',
            'home_label' => 'Home',
        ),
        $atts,
        'devcas_breadcrumbs'
    );

    $atts['back'] = (int) $atts['back'];

    return devcas_get_breadcrumbs_html( $atts );
}

// Shortcode registreren
add_shortcode( 'devcas_breadcrumbs', 'devcas_breadcrumbs_shortcode' );
