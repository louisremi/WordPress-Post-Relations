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

function register_post_relation( $from_type, $to_type, $args ) {
    global $post_relations;

    // retrieve the post_type to reuse the labels
    $to_post_type = get_post_type_object( $to_type );

    // merge user provided arguments with defaults
    $post_relations[] = array_merge_recursive(array(
        'from_type' => $from_type,
        'to_type' => $to_type,
        'taxonomy_name' => $from_type . '-to-' . $to_type,
        'labels' => (array) $to_post_type->labels,
        'capabilities' => array(
            // remove the taxonomy from the menus
            'manage_terms' => 'manage_relations',
            // remove the 'create new ...' from the meta-box
            'edit_terms' => 'edit_relations',
        ),
        'hierarchical' => true,
        'show_in_nav_menus' => false,
        'show_tagcloud' => false,
        'single_choice' => array_key_exists( '1_to_1', $args ) ? $args['1_to_1'] : false
    ), $args);
}

add_action('init',  function() {
    global $post_relations;
    $watched_types = array();

    // create one taxonomy per relation
    foreach ( $post_relations as $relation ) {
        $from_type = $relation['from_type'];
        $to_type = $relation['to_type'];
        $taxonomy_name = $relation['taxonomy_name'];

        // watch the 'to type'
        $watched_types[ $to_type ][] = $taxonomy_name;

        // register the taxonomy
        register_taxonomy( $taxonomy_name, $from_type, $relation );
        register_taxonomy_for_object_type( $taxonomy_name, $from_type );
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

// populate relations by triggering 'save_post' action
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

// taxonomies with arg 'single_choice' will have radio-buttons in their meta-boxes
add_filter('wp_terms_checklist_args', function( $args ) {
    $taxonomy = get_taxonomy( $args['taxonomy'] );

    if ( property_exists( $taxonomy, 'single_choice' ) && $taxonomy->single_choice ) {

        // Create the walker before first use
        if ( !class_exists( 'Walker_Single_Category_Checklist' ) ) {
            // copy the Walker_Category_Checklist, just change the option type
            class Walker_Single_Category_Checklist extends Walker_Category_Checklist {
                function start_el( &$output, $category, $depth, $args, $id = 0 ) {
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

        $args['walker'] = new Walker_Single_Category_Checklist;
    }

    return $args;
});

// make the inline-edit compatible with our radio-buttons
add_action('admin_print_footer_scripts', function() {
?><script>
jQuery.expr[':'].checkbox = function( elem ) {
    var name = elem.nodeName.toLowerCase(),
        type = elem.type;
    return name === "input" && ( type === "checkbox" || ( type === "radio" && elem.getAttribute("data-type") === "checkbox" ) );
};
</script><?php
}, 99);

?>