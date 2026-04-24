<?php
defined( 'ABSPATH' ) || exit;

add_action( 'init', 'glc_register_post_type' );
function glc_register_post_type() {
    register_post_type( 'cleanup_event', [
        'labels' => [
            'name'               => 'Cleanup Events',
            'singular_name'      => 'Cleanup Event',
            'add_new'            => 'Log New Cleanup',
            'add_new_item'       => 'Log New Cleanup Event',
            'edit_item'          => 'Edit Cleanup Event',
            'view_item'          => 'View Cleanup Event',
            'all_items'          => 'All Cleanups',
            'search_items'       => 'Search Cleanups',
            'not_found'          => 'No cleanups found.',
            'not_found_in_trash' => 'No cleanups in trash.',
        ],
        'public'        => true,
        'show_in_menu'  => true,
        'menu_position' => 5,
        'menu_icon'     => 'dashicons-trash',
        'supports'      => [ 'title', 'editor', 'thumbnail' ],
        'has_archive'   => 'cleanups',
        'rewrite'       => [ 'slug' => 'cleanups' ],
        'show_in_rest'  => true,
    ] );
}
