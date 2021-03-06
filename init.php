<?php
/*
Script Name:    WordPress Post Relations
author:         Louis-Rémi Babé / @louis_remi
Description:    Lightweight relations for WordPress post types
Version:        0.1.0

Using this in a paid job?
send me music: http://www.amazon.co.uk/wishlist/HNTU0468LQON
*/

/**
 * Released under the GPL license
 * http://www.opensource.org/licenses/gpl-license.php
 *
 * This is an add-on for WordPress
 * http://wordpress.org/
 *
 * **********************************************************************
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 2 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 * **********************************************************************
 */

global $post_relations;
$post_relations = array();

function register_post_relation( $from_type, $to_type, $taxonomy_name, $args = array() ) {
    global $post_relations;

    // retrieve the post_type to reuse the labels
    $to_post_type = get_post_type_object( $to_type );

    // merge user provided arguments with defaults
    $post_relations[ $taxonomy_name ] = wp_parse_args( $args, array(
        'from_type' => $from_type,
        'to_type' => $to_type,
        'taxonomy_name' => $taxonomy_name,
        'labels' => (array) $to_post_type->labels,
        'capabilities' => array(
            // remove the taxonomy from the menus
            'manage_terms' => 'manage_relations',
            // remove the 'create new ...' from the meta-box
            'edit_terms' => 'edit_relations',
        ),
        'hierarchical' => true,
        'show_in_nav_menus' => false,
        'show_tagcloud' => false
    ));
}

function build_related_posts_query_args( $post_ID, $taxonomy_name, $args = array() ) {
    global $post_relations;
    $related_posts = array();
    $defaults = array();
    $from_type = $post_relations[ $taxonomy_name ]['from_type'];
    $to_type = $post_relations[ $taxonomy_name ]['to_type'];

    // if no post_type filter is provided we need to deduce it
    if ( !array_key_exists( 'post_type', $args ) ) {
        $args['post_type'] = get_post_type( $post_ID ) === $from_type ? $to_type : $from_type;
    }

    // Search multiple 'to' using one 'from'
    if ( $to_type === $args['post_type'] ) {
        $related_IDs = array();
        foreach( get_the_terms( $post_ID, $taxonomy_name ) as $key => $term ) {
            $related_IDs[] = $term->slug;
        }

        $defaults['post__in'] = $related_IDs;

    // Search multple 'from' using one 'to'
    } else {
        $defaults['tax_query'] = array(array(
            'taxonomy' => $taxonomy_name,
            'field' => 'slug',
            'terms' => $post_ID
        ));
    }

    return wp_parse_args( $args, $defaults );
}

function get_related_posts( $post_ID, $taxonomy_name, $args = array() ) {
    return get_posts( build_related_posts_query_args( $post_ID, $taxonomy_name, $args ) );
}

function query_related_posts( $post_ID, $taxonomy_name, $args = array() ) {
    return query_posts( build_related_posts_query_args( $post_ID, $taxonomy_name, $args ) );
}

add_action('init',  function() {
    global $post_relations;
    $watched_types = array();

    // create one taxonomy per relation
    foreach ( $post_relations as $taxonomy_name => $relation ) {
        // watch the 'to type'
        $watched_types[ $relation['to_type'] ][] = $taxonomy_name;

        // register the taxonomy
        register_taxonomy( $taxonomy_name, $relation['from_type'], $relation );
        register_taxonomy_for_object_type( $taxonomy_name, $relation['from_type'] );
    }

    // keep the taxonomy in sync with the 'to type'
    add_action( 'save_post', function( $post_id ) use ( $watched_types ) {
        $post = get_post( $post_id );
        $post_type = $post->post_type;
        $post_status = $post->post_status;
        $post_title = $post->post_title;

        if (// is there a relation to this post_type?
            array_key_exists( $post_type, $watched_types ) &&
            // ignore auto-drafts and revisions
            $post_status !== 'auto-draft' && $post_status !== 'inherit' ) {

            // update each taxonomy involving this 'to type'
            foreach ( $watched_types[ $post_type ] as $taxonomy_name ) {

                if (( $term = get_term_by( 'slug', $post_id, $taxonomy_name ) )) {
                    // delete term
                    if ( $post_status === 'trash' ) {
                        return wp_delete_term( $term->term_id, $taxonomy_name );
                    }

                    // update term name if it changed
                    if ( $term->name != $post_title ) {
                        wp_update_term( $term->term_id, $taxonomy_name, array(
                            'name' => $post_title,
                            'slug' => $post_id
                        ));
                    }

                // Create term
                } else if ( $post_status !== 'trash' ) {
                    wp_insert_term( $post_title, $taxonomy_name, array(
                        'slug' => $post_id
                    ));
                }

            }
        }

    });
}, 999);

// taxonomies with arg 'radio' will have radio-buttons in their meta-boxes
add_filter('wp_terms_checklist_args', function( $args ) {
    $taxonomy = get_taxonomy( $args['taxonomy'] );

    if ( property_exists( $taxonomy, 'radio' ) && $taxonomy->radio ) {

        // Create the walker before first use
        if ( !class_exists( 'Walker_Radio_Category_Checklist' ) ) {
            // copy the Walker_Category_Checklist, just change the option type
            class Walker_Radio_Category_Checklist extends Walker_Category_Checklist {
                function start_el( &$output, $category, $depth = 0, $args = array(), $id = 0 ) {
                    extract($args);
                    if ( empty($taxonomy) )
                        $taxonomy = 'category';

                    if ( $taxonomy == 'category' )
                        $name = 'post_category';
                    else
                        $name = 'tax_input['.$taxonomy.']';

                    $class = in_array( $category->term_id, $popular_cats ) ? ' class="popular-category"' : '';
                    $output .= "\n<li id='{$taxonomy}-{$category->term_id}'$class>" . '<label class="selectit"><input value="' . $category->term_id . '" type="radio" data-type="checkbox" name="'.$name.'[]" id="in-'.$taxonomy.'-' . $category->term_id . '"' . checked( in_array( $category->term_id, $selected_cats ), true, false ) . disabled( empty( $args['disabled'] ), false, false ) . ' /> ' . esc_html( apply_filters('the_category', $category->name )) . '</label>';
                }
            }
        }

        $args['walker'] = new Walker_Radio_Category_Checklist;
    }

    return $args;
});

// Hack into jQuery's selector engine to make inline-edit compatible with radio-buttons
add_action('admin_print_footer_scripts', function() {
?><script>
jQuery.expr[':'].checkbox = function( elem ) {
    var name = elem.nodeName.toLowerCase(),
        type = elem.type;
    return name === "input" && ( type === "checkbox" || ( type === "radio" && elem.getAttribute("data-type") === "checkbox" ) );
};
</script><?php
}, 99);

// populate relations by triggering 'save_post' action
// needs some adaptation to work with WPML, see  http://wpml.org/forums/topic/504-gateway-time-out-translating-category/#post-122171
function post_relations_populate( $post_types, $display_notice = true ) {
    foreach ( $post_types as $post_type ) {
        $query = new WP_Query(array(
            'post_type' => $post_type,
            'post_status' => array(
                'publish',
                'pending',
                'draft',
                'future',
                'private'
            )
        ));

        while ( $query->have_posts() ) {
            $query->the_post();
            do_action( 'save_post', get_the_ID() );
        };
    }

    if ( $display_notice ) {
        add_action( 'admin_notices', function() {
            echo
                '<div class="updated">' .
                    '<p>' . __( 'Relations populated! Please remove the population code.', 'taxonomy_relations' ) . '</p>' .
                '</div>';
        });
    }
}

?>