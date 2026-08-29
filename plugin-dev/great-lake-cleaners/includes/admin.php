<?php
/**
 * Admin UX improvements:
 * - Custom columns in the cleanup list screen
 * - Sortable date column
 * - Admin styles
 */
defined( 'ABSPATH' ) || exit;

// ── List table columns ────────────────────────────────────────────────────────

add_filter( 'manage_cleanup_event_posts_columns', function( $cols ) {
    unset( $cols['date'] );
    return array_merge( $cols, [
        'cleanup_date' => 'Date',
        'site_name'    => 'Site',
        'corridor'     => 'Corridor',
        'volunteers'   => 'Volunteers',
        'bags'         => 'Bags',
        'weight_kg'    => 'kg',
        'tires'        => 'Tires',
        'bikes'        => 'Bikes',
        'carts'        => 'Carts',
        'hazards'      => 'Haz. Waste',
        'planted'      => 'Plants',
    ] );
} );

add_action( 'manage_cleanup_event_posts_custom_column', function( $col, $post_id ) {
    switch ( $col ) {
        case 'cleanup_date':
            $d = get_post_meta( $post_id, 'cleanup_date', true );
            echo $d ? esc_html( date( 'M j, Y', strtotime( $d ) ) ) : '—';
            break;
        case 'site_name':
            echo esc_html( get_post_meta( $post_id, 'site_name', true ) ?: get_the_title( $post_id ) );
            break;
        case 'volunteers':
            echo esc_html( get_post_meta( $post_id, 'volunteers', true ) ?: '—' );
            break;
        case 'bags':
            echo esc_html( get_post_meta( $post_id, 'bags', true ) ?: '—' );
            break;
        case 'weight_kg':
            $w = get_post_meta( $post_id, 'weight_kg', true );
            echo $w ? esc_html( number_format( $w, 0 ) ) . ' kg' : '—';
            break;
        case 'corridor':
            echo esc_html( get_post_meta( $post_id, 'corridor', true ) ?: '—' );
            break;
        case 'tires':
            $t = get_post_meta( $post_id, 'tires_removed', true );
            echo $t ? esc_html( $t ) : '—';
            break;
        case 'bikes':
            $bk = get_post_meta( $post_id, 'bikes_removed', true );
            echo $bk ? esc_html( $bk ) : '—';
            break;
        case 'carts':
            $c = get_post_meta( $post_id, 'carts_removed', true );
            echo $c ? esc_html( $c ) : '—';
            break;
        case 'hazards':
            $h = get_post_meta( $post_id, 'hazards_removed', true );
            echo $h ? esc_html( $h ) : '—';
            break;
        case 'planted':
            $p = get_post_meta( $post_id, 'species_planted', true );
            echo $p ? esc_html( $p ) : '—';
            break;
    }
}, 10, 2 );

// Make cleanup_date column sortable
add_filter( 'manage_edit-cleanup_event_sortable_columns', function( $cols ) {
    $cols['cleanup_date'] = 'cleanup_date';
    return $cols;
} );

add_action( 'pre_get_posts', function( $query ) {
    if ( ! is_admin() || ! $query->is_main_query() ) return;
    if ( $query->get( 'post_type' ) !== 'cleanup_event' ) return;
    if ( $query->get( 'orderby' ) === 'cleanup_date' ) {
        $query->set( 'meta_key', 'cleanup_date' );
        $query->set( 'orderby', 'meta_value' );
    }
    // Default order: newest cleanup first
    if ( ! $query->get( 'orderby' ) ) {
        $query->set( 'meta_key', 'cleanup_date' );
        $query->set( 'orderby', 'meta_value' );
        $query->set( 'order', 'DESC' );
    }
} );

// ── Event list table columns ──────────────────────────────────────────────────

add_filter( 'manage_glc_event_posts_columns', function( $cols ) {
    unset( $cols['date'] );
    return array_merge( $cols, [
        'event_date' => 'Event Date',
        'event_time' => 'Time',
        'site_name'  => 'Site',
        'linked'     => 'Cleanup Report',
        'rsvps'      => 'RSVPs',
    ] );
} );

add_action( 'manage_glc_event_posts_custom_column', function( $col, $post_id ) {
    switch ( $col ) {
        case 'event_date':
            $d = get_post_meta( $post_id, 'event_date', true );
            echo $d ? esc_html( date( 'M j, Y', strtotime( $d ) ) ) : '—';
            if ( $d && function_exists( 'glc_event_is_past' ) && glc_event_is_past( $post_id ) ) {
                echo ' <span style="color:#999;">(past)</span>';
            }
            break;
        case 'event_time':
            echo function_exists( 'glc_event_time_range' )
                ? esc_html( glc_event_time_range( $post_id ) ?: '—' )
                : '—';
            break;
        case 'site_name':
            echo esc_html( get_post_meta( $post_id, 'site_name', true ) ?: '—' );
            break;
        case 'linked':
            $linked = (int) get_post_meta( $post_id, 'linked_cleanup_id', true );
            if ( $linked && get_post_status( $linked ) ) {
                echo '<a href="' . esc_url( get_edit_post_link( $linked ) ) . '">'
                    . esc_html( get_the_title( $linked ) ) . '</a>';
            } else {
                echo '—';
            }
            break;
        case 'rsvps':
            $parties = (int) get_post_meta( $post_id, 'rsvp_parties', true );
            $count   = (int) get_post_meta( $post_id, 'rsvp_count',   true );
            echo $parties ? esc_html( $count . ' (' . $parties . ')' ) : '—';
            break;
    }
}, 10, 2 );

add_filter( 'manage_edit-glc_event_sortable_columns', function( $cols ) {
    $cols['event_date'] = 'event_date';
    return $cols;
} );

add_action( 'pre_get_posts', function( $query ) {
    if ( ! is_admin() || ! $query->is_main_query() ) return;
    if ( $query->get( 'post_type' ) !== 'glc_event' ) return;
    if ( $query->get( 'orderby' ) === 'event_date' ) {
        $query->set( 'meta_key', 'event_date' );
        $query->set( 'orderby', 'meta_value' );
    }
    // Default order: newest event date first
    if ( ! $query->get( 'orderby' ) ) {
        $query->set( 'meta_key', 'event_date' );
        $query->set( 'orderby', 'meta_value' );
        $query->set( 'order', 'DESC' );
    }
} );

// ── Gallery feature flags on attachments ─────────────────────────────────────
// Adds a "Feature in ... gallery" checkbox to the attachment edit modal.
// Images  -> _glc_gallery       = '1'  feeds [glc_gallery]       (/photos/)
// Videos  -> _glc_video_gallery = '1'  feeds [glc_video_gallery] (/videos/)
//
// Each checkbox is scoped to its own mime family so a video never shows the
// photo checkbox (and vice versa) — the two flags are separate meta keys and a
// clip flagged for one gallery must never leak into the other.

/**
 * Which gallery flag, if any, applies to an attachment's mime type.
 *
 * @param  int $att_id Attachment post ID.
 * @return array|null  [ field, meta_key, label, help ] or null when the file is
 *                     neither an image nor a video.
 */
function glc_gallery_flag_for_attachment( $att_id ) {
    $mime = (string) get_post_mime_type( $att_id );

    if ( strpos( $mime, 'image/' ) === 0 ) {
        return [
            'field' => 'glc_gallery',
            'key'   => '_glc_gallery',
            'label' => __( 'Feature in photo gallery', 'great-lake-cleaners' ),
            'help'  => __( 'Show this photo on the Photos page.', 'great-lake-cleaners' ),
        ];
    }

    if ( strpos( $mime, 'video/' ) === 0 ) {
        return [
            'field' => 'glc_video_gallery',
            'key'   => '_glc_video_gallery',
            'label' => __( 'Feature in video gallery', 'great-lake-cleaners' ),
            'help'  => __( 'Show this clip on the Videos page.', 'great-lake-cleaners' ),
        ];
    }

    return null;
}

add_filter( 'attachment_fields_to_edit', function( $fields, $post ) {
    $flag = glc_gallery_flag_for_attachment( $post->ID );
    if ( ! $flag ) return $fields;

    $checked = get_post_meta( $post->ID, $flag['key'], true ) === '1';

    $fields[ $flag['field'] ] = [
        'label' => __( 'Gallery', 'great-lake-cleaners' ),
        'input' => 'html',
        'html'  => '<label style="display:flex;align-items:center;gap:8px;cursor:pointer;">'
                 . '<input type="checkbox" name="attachments[' . esc_attr( $post->ID ) . '][' . esc_attr( $flag['field'] ) . ']" value="1"'
                 . ( $checked ? ' checked' : '' ) . '>'
                 . esc_html( $flag['label'] )
                 . '</label>',
        'helps' => esc_html( $flag['help'] ),
    ];
    return $fields;
}, 10, 2 );

add_filter( 'attachment_fields_to_save', function( $post, $attachment ) {
    $flag = glc_gallery_flag_for_attachment( $post['ID'] );
    if ( ! $flag ) return $post;

    update_post_meta( $post['ID'], $flag['key'], ! empty( $attachment[ $flag['field'] ] ) ? '1' : '0' );
    return $post;
}, 10, 2 );

// ── Admin styles ─────────────────────────────────────────────────────────────

add_action( 'admin_head', function() {
    $screen = get_current_screen();
    if ( ! $screen ) return;
    if ( strpos( $screen->id, 'cleanup_event' ) === false && strpos( $screen->id, 'glc_event' ) === false ) return;
    ?>
    <style>
    .column-volunteers, .column-bags, .column-weight_kg, .column-tires, .column-bikes, .column-carts, .column-hazards, .column-planted { width: 70px; text-align: center; }
    .column-cleanup_date { width: 110px; }
    .column-site_name { width: 220px; }
    .column-corridor { width: 140px; }
    .column-event_date { width: 130px; }
    .column-event_time { width: 140px; }
    .column-rsvps { width: 80px; text-align: center; }
    </style>
    <?php
} );

// ── Front-end styles (inline, keeps plugin self-contained) ───────────────────

add_action( 'wp_head', function() {
    if ( ! is_singular( 'cleanup_event' ) &&
         ! is_post_type_archive( 'cleanup_event' ) &&
         ! has_shortcode( get_post()->post_content ?? '', 'glc_stats' ) &&
         ! has_shortcode( get_post()->post_content ?? '', 'glc_map' ) &&
         ! has_shortcode( get_post()->post_content ?? '', 'glc_archive' ) ) {
        return;
    }
    ?>
    <style>
    /* Stats banner */
    .glc-stats-banner {
        display: flex;
        flex-wrap: wrap;
        gap: 16px;
        justify-content: center;
        padding: 24px;
        background: #124e4c;
        border-radius: 10px;
        margin: 24px 0;
    }
    .glc-stat {
        display: flex;
        flex-direction: column;
        align-items: center;
        min-width: 100px;
    }
    .glc-stat-value {
        font-size: 2rem;
        font-weight: 700;
        color: #a2d5ab;
        line-height: 1;
    }
    .glc-stat-label {
        font-size: 0.8rem;
        color: #a0c8c3;
        text-transform: uppercase;
        letter-spacing: 0.05em;
        margin-top: 4px;
    }

    /* Archive cards */
    .glc-archive { display: flex; flex-direction: column; gap: 16px; }
    .glc-event-card {
        border: 1px solid #e0e0e0;
        border-radius: 8px;
        padding: 16px 20px;
        background: #fafafa;
    }
    .glc-event-date { font-size: 0.85rem; color: #666; }
    .glc-event-title { margin: 4px 0 10px; font-size: 1.15rem; }
    .glc-event-title a { text-decoration: none; color: #124e4c; }
    .glc-event-title a:hover { text-decoration: underline; }
    .glc-event-stats { display: flex; flex-wrap: wrap; gap: 12px; font-size: 0.9rem; color: #444; }
    .glc-event-wildlife { font-size: 0.9rem; color: #555; margin: 8px 0 0; }

    /* Map */
    .glc-map { border: 1px solid #ccc; }
    </style>
    <?php
} );
