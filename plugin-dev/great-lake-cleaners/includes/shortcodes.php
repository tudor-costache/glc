<?php
/**
 * Shortcodes
 *
 * [glc_stats]   — cumulative totals banner
 * [glc_map]     — Leaflet map of all cleanup sites
 * [glc_archive] — recent cleanups list (fallback if theme doesn't handle CPT archive)
 */
defined( 'ABSPATH' ) || exit;

// ── Helpers ──────────────────────────────────────────────────────────────────

function glc_get_all_events() {
    return get_posts( [
        'post_type'      => 'cleanup_event',
        'post_status'    => 'publish',
        'posts_per_page' => -1,
        'orderby'        => 'meta_value',
        'meta_key'       => 'cleanup_date',
        'order'          => 'DESC',
    ] );
}

// Returns cleanup_event + published glc_submission posts merged and sorted by date desc.
function glc_get_all_cleanups() {
    $events = get_posts( [
        'post_type'      => 'cleanup_event',
        'post_status'    => 'publish',
        'posts_per_page' => -1,
    ] );
    $subs = get_posts( [
        'post_type'      => 'glc_submission',
        'post_status'    => 'publish',
        'posts_per_page' => -1,
    ] );
    $all = array_merge( $events, $subs );
    usort( $all, function( $a, $b ) {
        $da = $a->post_type === 'glc_submission'
            ? get_post_meta( $a->ID, 'glc_cleanup_date', true )
            : get_post_meta( $a->ID, 'cleanup_date',     true );
        $db = $b->post_type === 'glc_submission'
            ? get_post_meta( $b->ID, 'glc_cleanup_date', true )
            : get_post_meta( $b->ID, 'cleanup_date',     true );
        return strcmp( $db ?: '0000-00-00', $da ?: '0000-00-00' );
    } );
    return $all;
}

// Post-type-aware field accessor. Abstracts cleanup_event vs glc_submission meta keys.
function glc_cleanup_field( $post, $field, $default = '' ) {
    $id     = is_object( $post ) ? $post->ID : (int) $post;
    $is_sub = ( is_object( $post ) ? $post->post_type : get_post_type( $id ) ) === 'glc_submission';

    if ( $is_sub ) {
        switch ( $field ) {
            case 'cleanup_date':   return get_post_meta( $id, 'glc_cleanup_date', true ) ?: $default;
            case 'site_name':      $w = get_post_meta( $id, 'glc_waterway', true );
                                   return $w ?: get_the_title( $id );
            case 'gps_lat':        return (float) get_post_meta( $id, 'glc_gps_lat', true );
            case 'gps_lon':        return (float) get_post_meta( $id, 'glc_gps_lon', true );
            case 'bags':           return get_post_meta( $id, 'glc_bags',        true ) ?: $default;
            case 'weight_kg':      return get_post_meta( $id, 'weight_kg',       true ) ?: $default;
            case 'hours':          return get_post_meta( $id, 'glc_hours',       true ) ?: $default;
            case 'items_recycled': return get_post_meta( $id, 'items_recycled',  true ) ?: $default;
            case 'wildlife_obs':   return get_post_meta( $id, 'wildlife_obs',    true ) ?: $default;
            default:               return get_post_meta( $id, 'glc_' . $field,   true ) ?: $default;
        }
    }
    return glc_meta( $id, $field, $default );
}

function glc_meta( $post_id, $key, $default = 0 ) {
    $val = get_post_meta( $post_id, $key, true );
    return ( $val !== '' && $val !== false ) ? $val : $default;
}

// ── [glc_stats] ──────────────────────────────────────────────────────────────

add_shortcode( 'glc_stats', 'glc_shortcode_stats' );
function glc_shortcode_stats( $atts ) {
    $events = glc_get_all_events();
    if ( empty( $events ) ) return '';

    $totals = [
        'events'     => count( $events ),
        'volunteers' => 0,
        'hours'      => 0.0,
        'bags'       => 0,
        'weight_kg'  => 0.0,
        'planted'    => 0,
    ];

    foreach ( $events as $e ) {
        $id = $e->ID;
        $totals['volunteers'] += (int)   glc_meta( $id, 'volunteers' );
        $totals['hours']      += (float) glc_meta( $id, 'hours' );
        $totals['bags']       += (int)   glc_meta( $id, 'bags' );
        $totals['weight_kg']  += (float) glc_meta( $id, 'weight_kg' );
        $totals['planted']    += (int)   glc_meta( $id, 'species_planted' );
    }

    $stats = [
        [ 'value' => $totals['events'],                   'label' => 'Cleanups' ],
        [ 'value' => $totals['volunteers'],               'label' => 'Volunteers' ],
        [ 'value' => number_format( $totals['hours'], 1 ),'label' => 'Hours' ],
        [ 'value' => $totals['bags'],                     'label' => 'Bags Out' ],
        [ 'value' => number_format( $totals['weight_kg'], 0 ) . ' kg', 'label' => 'Debris Removed' ],
    ];
    if ( $totals['planted'] > 0 ) {
        $stats[] = [ 'value' => $totals['planted'], 'label' => 'Plants In' ];
    }

    ob_start(); ?>
    <div class="glc-stats-banner">
        <?php foreach ( $stats as $s ) : ?>
        <div class="glc-stat">
            <span class="glc-stat-value"><?php echo esc_html( $s['value'] ); ?></span>
            <span class="glc-stat-label"><?php echo esc_html( $s['label'] ); ?></span>
        </div>
        <?php endforeach; ?>
    </div>
    <?php
    return ob_get_clean();
}

// ── [glc_map] ────────────────────────────────────────────────────────────────

add_shortcode( 'glc_map', 'glc_shortcode_map' );
function glc_shortcode_map( $atts ) {
    $atts = shortcode_atts( [
        'height'  => '480px',
        'post_id' => 0,          // if set, render a single-event map
        'limit'          => 0,   // max markers per geographic cluster (0 = no limit)
        'cluster_radius' => 0,   // km radius for grouping nearby markers (0 = no clustering)
    ], $atts );

    // Single-event mode (used on single-cleanup_event.php and single-glc_submission.php)
    if ( (int) $atts['post_id'] > 0 ) {
        $pid = (int) $atts['post_id'];
        // Try tracker meta keys first, then glc_ prefixed keys (community submissions)
        $lat = (float) glc_meta( $pid, 'gps_lat', 0 );
        $lon = (float) glc_meta( $pid, 'gps_lon', 0 );
        if ( ! $lat || ! $lon ) {
            $lat = (float) glc_meta( $pid, 'glc_gps_lat', 0 );
            $lon = (float) glc_meta( $pid, 'glc_gps_lon', 0 );
        }
        if ( ! $lat || ! $lon ) return ''; // no coords — skip map
        $markers = [ [
            'lat'   => $lat,
            'lon'   => $lon,
            'title' => get_the_title( $pid ),
            'date'  => glc_meta( $pid, 'cleanup_date', '' ),
            'bags'  => (int) glc_meta( $pid, 'bags' ),
            'url'   => get_permalink( $pid ),
        ] ];
    } else {
        // All-events mode — both cleanup_event and glc_submission, deduped by location
        $events      = glc_get_all_cleanups();
        $by_location = [];
        foreach ( $events as $e ) {
            $lat = (float) glc_cleanup_field( $e, 'gps_lat' );
            $lon = (float) glc_cleanup_field( $e, 'gps_lon' );
            if ( ! $lat || ! $lon ) continue;

            $key   = round( $lat, 5 ) . ',' . round( $lon, 5 );
            $score = (float) glc_cleanup_field( $e, 'weight_kg' )
                   + (int)   glc_cleanup_field( $e, 'bags' ) * 2;

            if ( ! isset( $by_location[ $key ] ) || $score > $by_location[ $key ]['score'] ) {
                $by_location[ $key ] = [
                    'score' => $score,
                    'lat'   => $lat,
                    'lon'   => $lon,
                    'title' => glc_cleanup_field( $e, 'site_name' ),
                    'date'  => glc_cleanup_field( $e, 'cleanup_date' ),
                    'bags'  => (int) glc_cleanup_field( $e, 'bags' ),
                    'url'   => get_permalink( $e->ID ),
                ];
            }
        }
        // Sort by score descending so each cluster always retains its highest-impact sites
        usort( $by_location, fn( $a, $b ) => $b['score'] <=> $a['score'] );

        $limit          = (int)   $atts['limit'];
        $cluster_radius = (float) $atts['cluster_radius'];

        if ( $cluster_radius > 0 && $limit > 0 ) {
            // Greedy geographic clustering: each marker joins the first existing cluster
            // whose anchor is within cluster_radius km, or starts a new cluster.
            // Because markers are pre-sorted by score, the anchor of each cluster is
            // always its highest-impact site — array_slice then gives the top N cheaply.
            $clusters = [];
            foreach ( $by_location as $marker ) {
                $placed = false;
                foreach ( $clusters as &$cl ) {
                    $dlat = deg2rad( $marker['lat'] - $cl['lat'] );
                    $dlon = deg2rad( $marker['lon'] - $cl['lon'] );
                    $a    = sin( $dlat / 2 ) ** 2
                          + cos( deg2rad( $cl['lat'] ) ) * cos( deg2rad( $marker['lat'] ) ) * sin( $dlon / 2 ) ** 2;
                    if ( 6371 * 2 * asin( sqrt( $a ) ) <= $cluster_radius ) {
                        $cl['members'][] = $marker;
                        $placed = true;
                        break;
                    }
                }
                unset( $cl );
                if ( ! $placed ) {
                    $clusters[] = [ 'lat' => $marker['lat'], 'lon' => $marker['lon'], 'members' => [ $marker ] ];
                }
            }
            $markers = [];
            foreach ( $clusters as $cl ) {
                foreach ( array_slice( $cl['members'], 0, $limit ) as $m ) {
                    $markers[] = $m;
                }
            }
        } else {
            $markers = array_values( $by_location );
        }
    }

    // Enqueue Leaflet — self-hosted to eliminate unpkg.com CDN dependency and tighten CSP
    wp_enqueue_style(
        'leaflet',
        GLC_PLUGIN_URL . 'assets/leaflet.css',
        [], '1.9.4'
    );
    wp_enqueue_script(
        'leaflet',
        GLC_PLUGIN_URL . 'assets/leaflet.js',
        [], '1.9.4', true
    );

    $map_id = 'glc-map-' . wp_rand( 1000, 9999 );
    ob_start(); ?>
    <div id="<?php echo esc_attr( $map_id ); ?>"
         class="glc-map"
         role="application"
         aria-label="<?php esc_attr_e( 'Cleanup locations map', 'great-lake-cleaners' ); ?>"
         style="height:<?php echo esc_attr( $atts['height'] ); ?>; width:100%; border-radius:8px;">
    </div>
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        var markers = <?php echo wp_json_encode( $markers ); ?>;
        var map = L.map(<?php echo wp_json_encode( $map_id ); ?>, { zoomControl: false }).setView([43.545, -80.248], 12);
        L.tileLayer('https://{s}.basemaps.cartocdn.com/light_all/{z}/{x}/{y}{r}.png', {
            attribution: '© <a href="https://openstreetmap.org/copyright">OpenStreetMap</a> contributors © <a href="https://carto.com/attributions">CARTO</a>',
            subdomains: 'abcd',
            maxZoom: 19
        }).addTo(map);

        var icon = L.divIcon({
            className: 'glc-marker',
            html: '<svg width="20" height="26" viewBox="0 0 20 26" xmlns="http://www.w3.org/2000/svg"><path d="M10 0C4.48 0 0 4.48 0 10c0 7.5 10 16 10 16s10-8.5 10-16C20 4.48 15.52 0 10 0z" fill="#1a4a6b"/><circle cx="10" cy="10" r="4" fill="#ffffff"/></svg>',
            iconSize: [20, 26],
            iconAnchor: [10, 26],
            popupAnchor: [0, -28],
        });

        markers.forEach(function(m) {
            var popup = '<strong>' + m.title + '</strong><br>'
                      + m.date + '<br>'
                      + m.bags + (m.bags === 1 ? ' bag' : ' bags') + ' collected<br>'
                      + '<a href="' + m.url + '">View details →</a>';
            L.marker([m.lat, m.lon], {icon: icon})
             .addTo(map)
             .bindPopup(popup);
        });

        // Fit bounds or zoom to single pin
        if (markers.length === 1) {
            map.setView([markers[0].lat, markers[0].lon], 15);
        } else if (markers.length > 1) {
            var latlngs = markers.map(function(m){ return [m.lat, m.lon]; });
            map.fitBounds(latlngs, {padding: [40, 40]});
        }
    });
    </script>
    <?php
    return ob_get_clean();
}

// ── [glc_archive] ────────────────────────────────────────────────────────────

add_shortcode( 'glc_archive', 'glc_shortcode_archive' );
function glc_shortcode_archive( $atts ) {
    $atts   = shortcode_atts( [ 'limit' => 20 ], $atts );
    $events = get_posts( [
        'post_type'      => 'cleanup_event',
        'post_status'    => 'publish',
        'posts_per_page' => (int) $atts['limit'],
        'orderby'        => 'meta_value',
        'meta_key'       => 'cleanup_date',
        'order'          => 'DESC',
    ] );

    if ( empty( $events ) ) {
        return '<p>No cleanups logged yet. Check back soon!</p>';
    }

    ob_start(); ?>
    <div class="glc-archive">
        <?php foreach ( $events as $e ) :
            $id       = $e->ID;
            $date     = glc_meta( $id, 'cleanup_date', '' );
            $site     = glc_meta( $id, 'site_name', get_the_title( $id ) );
            $vols     = (int)   glc_meta( $id, 'volunteers' );
            $bags     = (int)   glc_meta( $id, 'bags' );
            $weight   = (float) glc_meta( $id, 'weight_kg' );
            $planted  = (int)   glc_meta( $id, 'species_planted' );
            $wildlife = glc_meta( $id, 'wildlife_obs', '' );
            $date_fmt = $date ? date( 'F j, Y', strtotime( $date ) ) : '';
        ?>
        <article class="glc-event-card">
            <div class="glc-event-meta">
                <time class="glc-event-date"><?php echo esc_html( $date_fmt ); ?></time>
            </div>
            <h3 class="glc-event-title">
                <a href="<?php echo esc_url( get_permalink( $id ) ); ?>">
                    <?php echo esc_html( $site ); ?>
                </a>
            </h3>
            <div class="glc-event-stats">
                <span>👥 <?php echo $vols; ?> volunteers</span>
                <span>🛍 <?php echo $bags; ?> <?php echo 1 === $bags ? 'bag' : 'bags'; ?></span>
                <span>⚖ <?php echo number_format( $weight, 0 ); ?> kg</span>
                <?php if ( $planted ) : ?>
                <span>🌿 <?php echo $planted; ?> plants</span>
                <?php endif; ?>
            </div>
            <?php if ( $wildlife ) : ?>
            <p class="glc-event-wildlife">👀 <?php echo esc_html( $wildlife ); ?></p>
            <?php endif; ?>
        </article>
        <?php endforeach; ?>
    </div>
    <?php
    return ob_get_clean();
}

// ── [glc_gallery] ────────────────────────────────────────────────────────────
// Renders a year-tabbed photo grid with a vanilla JS lightbox.
// Sources:
//   - Images attached to cleanup_event posts (uploaded while editing)
//   - Images attached to published glc_submission posts where repost consent = '1'
// Year is derived from the cleanup_date meta (events) or glc_cleanup_date meta (submissions).

add_shortcode( 'glc_gallery', 'glc_shortcode_gallery' );
function glc_shortcode_gallery( $atts ) {

    // ── 1. Query ALL gallery-flagged images globally ──────────────────────────
    // Using post_parent to scope queries misses images inserted from the Media
    // Library into a post — those keep post_parent = 0. One global meta query
    // finds everything the admin has explicitly flagged, then we resolve
    // parent metadata (date, label, url) per attachment.

    $all_atts = get_posts( [
        'post_type'      => 'attachment',
        'post_status'    => 'inherit',
        'post_mime_type' => 'image',
        'posts_per_page' => -1,
        'orderby'        => 'menu_order date',
        'order'          => 'ASC',
        'meta_query'     => [ [
            'key'   => '_glc_gallery',
            'value' => '1',
        ] ],
    ] );

    // Pre-load published glc_submission posts with repost consent into a lookup
    // so we can skip submission photos where the submitter didn't consent.
    $consented_sub_ids = [];
    $sub_posts = get_posts( [
        'post_type'      => 'glc_submission',
        'post_status'    => 'publish',
        'posts_per_page' => -1,
        'fields'         => 'ids',
        'meta_query'     => [ [ 'key' => 'glc_photo_repost_ok', 'value' => '1' ] ],
    ] );
    foreach ( $sub_posts as $id ) {
        $consented_sub_ids[ $id ] = true;
    }

    // ── 2. Build photo list grouped by year ──────────────────────────────────

    $by_year = [];  // [ year => [ [ src, thumb, title, date, url, alt ] ] ]
    $seen    = [];  // dedup by attachment ID

    foreach ( $all_atts as $att ) {
        if ( isset( $seen[ $att->ID ] ) ) continue;

        $src   = wp_get_attachment_image_url( $att->ID, 'large' );
        $thumb = wp_get_attachment_image_url( $att->ID, 'medium' );
        if ( ! $src || ! $thumb ) continue;

        $label = '';
        $date  = '';
        $url   = '';

        if ( $att->post_parent ) {
            $parent = get_post( $att->post_parent );
            if ( $parent && $parent->post_status === 'publish' ) {
                if ( $parent->post_type === 'cleanup_event' ) {
                    $date  = get_post_meta( $parent->ID, 'cleanup_date', true );
                    $label = get_post_meta( $parent->ID, 'site_name', true ) ?: get_the_title( $parent->ID );
                    $url   = get_permalink( $parent->ID );
                } elseif ( $parent->post_type === 'glc_submission' ) {
                    if ( ! isset( $consented_sub_ids[ $parent->ID ] ) ) continue;
                    $date  = get_post_meta( $parent->ID, 'glc_cleanup_date', true );
                    $label = get_post_meta( $parent->ID, 'glc_site_name', true ) ?: get_the_title( $parent->ID );
                    $url   = get_permalink( $parent->ID );
                }
            }
        }

        // Derive year: prefer parent cleanup date, fall back to upload date
        $year = intval( date( 'Y', strtotime( $att->post_date ) ) );
        if ( $date ) {
            $yr = intval( substr( $date, 0, 4 ) );
            if ( $yr >= 2000 && $yr <= 2100 ) $year = $yr;
        }

        if ( ! $label ) $label = $att->post_title;
        $alt = get_post_meta( $att->ID, '_wp_attachment_image_alt', true ) ?: $label;

        // sort_date: cleanup date when available, upload date as fallback so
        // media-library images don't all sink to the bottom of the year tab.
        $sort_date = $date ?: substr( $att->post_date, 0, 10 );

        $by_year[ $year ][] = [
            'src'       => $src,
            'thumb'     => $thumb,
            'alt'       => $alt,
            'label'     => $label,
            'title'     => $att->post_title,
            'date'      => $date,
            'sort_date' => $sort_date,
            'url'       => $url,
        ];
        $seen[ $att->ID ] = true;
    }

    if ( empty( $by_year ) ) {
        return '<p class="glc-gallery-empty">No photos yet — check back after our next outing!</p>';
    }

    // Sort years descending, photos within each year by sort_date descending
    krsort( $by_year );
    foreach ( $by_year as &$photos ) {
        usort( $photos, fn( $a, $b ) => strcmp( $b['sort_date'], $a['sort_date'] ) );
    }
    unset( $photos );
    $years = array_keys( $by_year );
    $first = $years[0];

    // Flatten all photos into a single indexed array for lightbox navigation
    $all_photos = [];
    foreach ( $by_year as $yr => $photos ) {
        foreach ( $photos as $p ) {
            $p['year'] = $yr;
            $all_photos[] = $p;
        }
    }

    // Build per-year offset map so lightbox can navigate within year or globally
    $year_offsets = [];
    $offset = 0;
    foreach ( $by_year as $yr => $photos ) {
        $year_offsets[ $yr ] = $offset;
        $offset += count( $photos );
    }

    $gallery_id = 'glc-gallery-' . wp_rand( 1000, 9999 );

    ob_start(); ?>
    <div class="glc-gallery-wrap" id="<?php echo esc_attr( $gallery_id ); ?>">

        <?php if ( count( $years ) > 1 ) : ?>
        <!-- Year tabs -->
        <div class="glc-gallery-tabs" role="tablist" aria-label="Filter photos by year">
            <?php foreach ( $years as $yr ) : ?>
            <button
                class="glc-gallery-tab<?php echo $yr === $first ? ' glc-tab-active' : ''; ?>"
                role="tab"
                aria-selected="<?php echo $yr === $first ? 'true' : 'false'; ?>"
                data-year="<?php echo esc_attr( $yr ); ?>">
                <?php echo esc_html( $yr ); ?>
                <span class="glc-tab-count"><?php echo count( $by_year[ $yr ] ); ?></span>
            </button>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>

        <!-- Photo grids — one per year, only active one visible -->
        <?php foreach ( $by_year as $yr => $photos ) :
            $global_start = $year_offsets[ $yr ];
        ?>
        <div class="glc-gallery-grid<?php echo $yr === $first ? ' glc-grid-active' : ''; ?>"
             data-year="<?php echo esc_attr( $yr ); ?>"
             role="tabpanel">
            <?php foreach ( $photos as $i => $photo ) :
                $global_idx = $global_start + $i;
            ?>
            <button
                class="glc-gallery-thumb-btn"
                aria-label="<?php echo esc_attr( $photo['alt'] ); ?>"
                data-global-idx="<?php echo $global_idx; ?>">
                <img
                    src="<?php echo esc_url( $photo['thumb'] ); ?>"
                    alt="<?php echo esc_attr( $photo['alt'] ); ?>"
                    loading="lazy">
                <span class="glc-thumb-caption"><?php echo esc_html( $photo['label'] ); ?></span>
            </button>
            <?php endforeach; ?>
        </div>
        <?php endforeach; ?>

        <!-- Lightbox -->
        <div class="glc-lightbox" id="<?php echo esc_attr( $gallery_id ); ?>-lb"
             role="dialog" aria-modal="true" aria-label="Photo viewer" hidden>
            <button class="glc-lb-close" aria-label="Close photo viewer">&#x2715;</button>
            <button class="glc-lb-prev" aria-label="Previous photo">&#x2039;</button>
            <button class="glc-lb-next" aria-label="Next photo">&#x203a;</button>
            <div class="glc-lb-inner">
                <img class="glc-lb-img" src="" alt="">
                <div class="glc-lb-meta">
                    <span class="glc-lb-label"></span>
                    <a class="glc-lb-link" href="" target="_blank" rel="noopener">View outing →<span class="screen-reader-text"> (opens in new tab)</span></a>
                </div>
            </div>
        </div>
    </div>

    <script>
    (function() {
        var wrap   = document.getElementById(<?php echo wp_json_encode( $gallery_id ); ?>);
        var lb     = document.getElementById(<?php echo wp_json_encode( $gallery_id . '-lb' ); ?>);
        var photos = <?php echo wp_json_encode( array_values( $all_photos ) ); ?>;
        var currentIdx  = 0;
        var lastTrigger = null;
        var focusables  = [
            lb.querySelector('.glc-lb-close'),
            lb.querySelector('.glc-lb-prev'),
            lb.querySelector('.glc-lb-next'),
            lb.querySelector('.glc-lb-link'),
        ];

        // ── Tab switching ──────────────────────────────────────────────────
        var tabs  = wrap.querySelectorAll('.glc-gallery-tab');
        var grids = wrap.querySelectorAll('.glc-gallery-grid');

        tabs.forEach(function(tab) {
            tab.addEventListener('click', function() {
                var yr = tab.dataset.year;
                tabs.forEach(function(t) {
                    t.classList.remove('glc-tab-active');
                    t.setAttribute('aria-selected', 'false');
                });
                grids.forEach(function(g) { g.classList.remove('glc-grid-active'); });
                tab.classList.add('glc-tab-active');
                tab.setAttribute('aria-selected', 'true');
                wrap.querySelector('.glc-gallery-grid[data-year="' + yr + '"]')
                    .classList.add('glc-grid-active');
            });
        });

        // ── Lightbox ───────────────────────────────────────────────────────
        var lbImg   = lb.querySelector('.glc-lb-img');
        var lbLabel = lb.querySelector('.glc-lb-label');
        var lbLink  = lb.querySelector('.glc-lb-link');

        function showPhoto(idx) {
            if ( idx < 0 ) idx = photos.length - 1;
            if ( idx >= photos.length ) idx = 0;
            currentIdx = idx;
            var p = photos[idx];
            lbImg.src       = p.src;
            lbImg.alt       = p.alt;
            lbLabel.textContent = p.title || p.label;
            lbLink.href     = p.url;
            lb.hidden = false;
            document.body.style.overflow = 'hidden';
            lb.querySelector('.glc-lb-close').focus();
        }

        function closeLightbox() {
            lb.hidden = true;
            document.body.style.overflow = '';
            if (lastTrigger) lastTrigger.focus();
        }

        wrap.querySelectorAll('.glc-gallery-thumb-btn').forEach(function(btn) {
            btn.addEventListener('click', function() {
                lastTrigger = btn;
                showPhoto( parseInt(btn.dataset.globalIdx, 10) );
            });
        });

        lb.querySelector('.glc-lb-close').addEventListener('click', closeLightbox);
        lb.querySelector('.glc-lb-prev').addEventListener('click', function() { showPhoto(currentIdx - 1); });
        lb.querySelector('.glc-lb-next').addEventListener('click', function() { showPhoto(currentIdx + 1); });

        lb.addEventListener('click', function(e) {
            if ( e.target === lb ) closeLightbox();
        });

        document.addEventListener('keydown', function(e) {
            if ( lb.hidden ) return;
            if ( e.key === 'Escape' )     { closeLightbox(); return; }
            if ( e.key === 'ArrowLeft' )  { showPhoto(currentIdx - 1); return; }
            if ( e.key === 'ArrowRight' ) { showPhoto(currentIdx + 1); return; }
            if ( e.key === 'Tab' ) {
                e.preventDefault();
                var fi = focusables.indexOf(document.activeElement);
                if (e.shiftKey) {
                    focusables[(fi <= 0 ? focusables.length : fi) - 1].focus();
                } else {
                    focusables[(fi + 1) % focusables.length].focus();
                }
            }
        });
    })();
    </script>
    <?php
    return ob_get_clean();
}

// ── [glc_impact_highlights] ──────────────────────────────────────────────────
// Layout groups five icon cards by meaning rather than one flat grid: the hours chart sits
// beside two "headline" cards (unique sites, total cleanups), then a second row below holds
// the three "removed from the water" cards (tires, bikes, shopping carts). All five cards
// draw on both cleanup_event and glc_submission data. Cards use the same compact icon-left /
// label+value-right shape as the /stats wildlife cards, rather than a tall icon-over-number
// stack. Card icons are theme-hosted PNGs reused from the /stats large-items pictograph
// (tire/bike/cart) plus sites-icon.png and garbage-bag.png.

add_shortcode( 'glc_impact_highlights', 'glc_shortcode_impact_highlights' );
function glc_shortcode_impact_highlights() {

    $events = get_posts( [
        'post_type'      => 'cleanup_event',
        'post_status'    => 'publish',
        'posts_per_page' => -1,
    ] );

    $subs = get_posts( [
        'post_type'      => 'glc_submission',
        'post_status'    => 'publish',
        'posts_per_page' => -1,
    ] );

    if ( empty( $events ) && empty( $subs ) ) {
        return '<p class="glc-timeline-empty">No cleanup data yet — check back after our next outing!</p>';
    }

    $site_names  = [];
    $total_tires = 0;
    $total_bikes = 0;
    $total_carts = 0;
    $pts         = [];

    foreach ( $events as $e ) {
        $id   = $e->ID;
        $date = get_post_meta( $id, 'cleanup_date', true );

        $site = get_post_meta( $id, 'site_name', true );
        if ( $site ) $site_names[] = $site;

        $total_tires   += (int) get_post_meta( $id, 'tires_removed', true );
        $total_bikes   += (int) get_post_meta( $id, 'bikes_removed', true );
        $total_carts   += (int) get_post_meta( $id, 'carts_removed', true );

        if ( $date ) {
            $pts[] = [
                'date'  => $date,
                'hours' => (float) get_post_meta( $id, 'hours', true ),
            ];
        }
    }

    foreach ( $subs as $s ) {
        $id   = $s->ID;
        $date = get_post_meta( $id, 'glc_cleanup_date', true );

        $site = get_post_meta( $id, 'glc_site_name', true );
        if ( $site ) $site_names[] = $site;

        $total_tires += (int) get_post_meta( $id, 'tires_removed', true );
        $total_bikes += (int) get_post_meta( $id, 'bikes_removed', true );
        $total_carts += (int) get_post_meta( $id, 'carts_removed', true );

        if ( $date ) {
            $pts[] = [
                'date'  => $date,
                'hours' => (float) get_post_meta( $id, 'glc_hours', true ),
            ];
        }
    }

    $unique_sites   = count( array_unique( array_filter( $site_names ) ) );
    $total_cleanups = count( $events ) + count( $subs );

    usort( $pts, fn( $a, $b ) => strcmp( $a['date'], $b['date'] ) );

    $first_ts  = ! empty( $pts ) ? strtotime( $pts[0]['date'] ) : 0;
    $data_days = [];
    $data_hrs  = [];
    $run_hrs   = 0.0;
    foreach ( $pts as $p ) {
        $day_off     = (int) round( ( strtotime( $p['date'] ) - $first_ts ) / 86400 );
        $run_hrs    += $p['hours'];
        $data_days[] = $day_off;
        $data_hrs[]  = round( $run_hrs, 1 );
    }

    $max_day     = ! empty( $data_days ) ? end( $data_days ) : 0;
    $total_hours = ! empty( $data_hrs )  ? end( $data_hrs )  : 0;
    $hours_display = ( $total_hours == floor( $total_hours ) )
        ? number_format( (int) $total_hours )
        : number_format( $total_hours, 1 );

    // Cards link into /cleanups/ — tires/carts deep-link to the filtered view
    // handled by archive-cleanup_event.php's ?impact= query arg.
    $cleanups_page = get_page_by_path( 'cleanups' );
    $cleanups_url  = $cleanups_page
        ? get_permalink( $cleanups_page )
        : get_post_type_archive_link( 'cleanup_event' );
    $tires_url     = $cleanups_url ? add_query_arg( 'impact', 'tires', $cleanups_url ) : '';
    $bikes_url     = $cleanups_url ? add_query_arg( 'impact', 'bikes', $cleanups_url ) : '';
    $carts_url     = $cleanups_url ? add_query_arg( 'impact', 'carts', $cleanups_url ) : '';
    $ih_tag        = $cleanups_url ? 'a' : 'div';

    // Icons reuse the same asset files as the /stats large-items pictograph and moose section —
    // theme-hosted, so the plugin reaches into the theme's assets dir (same pattern submission.php
    // uses for the submit-form thank-you image).
    $ih_idir = esc_url( get_stylesheet_directory_uri() ) . '/assets/images';

    ob_start();
    // Card markup helper — icon-left, label+value-right, same compact shape as the
    // /stats wildlife cards and the single-event "Wildlife Observed" block, so this
    // shortcode doesn't sprawl into its own oversized visual language.
    $ih_card = function ( $icon, $label, $value, $url ) use ( $ih_tag, $ih_idir ) {
        ob_start(); ?>
        <<?php echo $ih_tag; ?> class="glc-ih-card"<?php echo $url ? ' href="' . esc_url( $url ) . '"' : ''; ?>>
            <span class="glc-ih-icon-box"><img src="<?php echo esc_url( $ih_idir . '/' . $icon ); ?>" alt="" aria-hidden="true"></span>
            <span class="glc-ih-text">
                <span class="glc-ih-label"><?php echo esc_html( $label ); ?></span>
                <span class="glc-ih-value"><?php echo esc_html( $value ); ?></span>
            </span>
        </<?php echo $ih_tag; ?>>
        <?php return ob_get_clean();
    };
    ?>
    <div class="glc-impact-highlights">

        <div class="glc-ih-top">
            <?php if ( ! empty( $data_hrs ) && $max_day > 0 ) : ?>
            <div class="dirCL-chartwrap glc-ih-chart">
                <div class="dirCL-legend">
                    <div class="dirCL-leg">
                        <span class="sw" style="background:#2e8b57"></span>
                        Volunteer hours
                        <span class="num" style="color:#1a5e35"><?php echo esc_html( $hours_display ); ?></span>
                    </div>
                </div>
                <?php
                echo glc_stats_area_chart(
                    [ [
                        'key'          => 'hours',
                        'color'        => '#2e8b57',
                        'values'       => $data_hrs,
                        'max'          => max( $total_hours * 1.1, 10 ),
                        'fill_opacity' => '0.20',
                    ] ],
                    230,
                    $data_days,
                    $max_day,
                    $first_ts
                );
                ?>
            </div>
            <?php endif; ?>

            <div class="glc-ih-headline">
                <?php
                echo $ih_card( 'sites-icon.png', 'Sites Cleaned', $unique_sites, $cleanups_url );
                echo $ih_card( 'garbage-bag.png', 'Total Cleanups', $total_cleanups, $cleanups_url );
                ?>
            </div>
        </div>

        <div class="glc-ih-removed">
            <?php
            echo $ih_card( 'tire-icon.png', 'Tires Removed', $total_tires, $tires_url );
            echo $ih_card( 'bike-icon.png', 'Bikes Removed', $total_bikes, $bikes_url );
            echo $ih_card( 'cart-icon.png', 'Carts Removed', $total_carts, $carts_url );
            ?>
        </div>

    </div>
    <?php
    return ob_get_clean();
}

// ── [glc_timeline] ───────────────────────────────────────────────────────────
// Cumulative debris removed (kg) and items recycled over time — SVG area chart.
// Includes cleanup_event + glc_submission data. Matches the design of page-stats.php.

add_shortcode( 'glc_timeline', 'glc_shortcode_timeline' );
function glc_shortcode_timeline() {

    $events = get_posts( [
        'post_type'      => 'cleanup_event',
        'post_status'    => 'publish',
        'posts_per_page' => -1,
    ] );
    $subs = get_posts( [
        'post_type'      => 'glc_submission',
        'post_status'    => 'publish',
        'posts_per_page' => -1,
    ] );

    $raw = [];
    foreach ( $events as $e ) {
        $date = get_post_meta( $e->ID, 'cleanup_date', true );
        if ( ! $date ) continue;
        $raw[] = [
            'date'     => $date,
            'weight'   => (float) get_post_meta( $e->ID, 'weight_kg',      true ),
            'recycled' => (int)   get_post_meta( $e->ID, 'items_recycled', true ),
        ];
    }
    foreach ( $subs as $s ) {
        $date = get_post_meta( $s->ID, 'glc_cleanup_date', true );
        if ( ! $date ) continue;
        $raw[] = [
            'date'     => $date,
            'weight'   => (float) get_post_meta( $s->ID, 'weight_kg',      true ),
            'recycled' => (int)   get_post_meta( $s->ID, 'items_recycled', true ),
        ];
    }

    if ( empty( $raw ) ) {
        return '<p class="glc-timeline-empty">No cleanup data yet — check back after our next outing!</p>';
    }

    usort( $raw, fn( $a, $b ) => strcmp( $a['date'], $b['date'] ) );

    $grouped = [];
    foreach ( $raw as $p ) {
        $dk = $p['date'];
        if ( ! isset( $grouped[ $dk ] ) ) $grouped[ $dk ] = [ 'weight' => 0.0, 'recycled' => 0 ];
        $grouped[ $dk ]['weight']   += $p['weight'];
        $grouped[ $dk ]['recycled'] += $p['recycled'];
    }

    $dates     = array_keys( $grouped );
    $first_ts  = strtotime( $dates[0] );
    $data_days = [];
    $data_kg   = [];
    $data_rec  = [];
    $run_kg    = 0.0;
    $run_rec   = 0;

    foreach ( $grouped as $date => $totals ) {
        $day_off     = (int) round( ( strtotime( $date ) - $first_ts ) / 86400 );
        $run_kg     += $totals['weight'];
        $run_rec    += $totals['recycled'];
        $data_days[] = $day_off;
        $data_kg[]   = round( $run_kg, 1 );
        $data_rec[]  = $run_rec;
    }

    $max_day      = end( $data_days );
    $total_debris = end( $data_kg );
    $total_recyc  = end( $data_rec );

    $debris_display = ( $total_debris == floor( $total_debris ) )
        ? number_format( (int) $total_debris )
        : number_format( $total_debris, 1 );

    ob_start(); ?>
    <div class="dirCL-chartwrap">
        <div class="dirCL-legend">
            <div class="dirCL-leg">
                <span class="sw" style="background:#1a4a6b"></span>
                Debris
                <span class="num" style="color:#1a4a6b"><?php echo esc_html( $debris_display ); ?> kg</span>
            </div>
            <div class="dirCL-leg">
                <span class="sw" style="background:#f5a623"></span>
                Items recycled
                <span class="num" style="color:#e08e12"><?php echo esc_html( number_format( $total_recyc ) ); ?></span>
            </div>
        </div>
        <?php
        echo glc_stats_area_chart(
            [
                [
                    'key'          => 'debris',
                    'color'        => '#1a4a6b',
                    'values'       => $data_kg,
                    'max'          => max( $total_debris * 1.1, 10 ),
                    'fill_opacity' => '0.18',
                ],
                [
                    'key'          => 'recyc',
                    'color'        => '#f5a623',
                    'values'       => $data_rec,
                    'max'          => max( $total_recyc * 1.1, 10 ),
                    'fill_opacity' => '0.14',
                ],
            ],
            300,
            $data_days,
            $max_day,
            $first_ts
        );
        ?>
    </div>
    <?php
    return ob_get_clean();
}

// ── [glc_wildlife_log] ───────────────────────────────────────────────────────
// All cleanup_event posts that have a wildlife_obs entry, sorted newest first.

add_shortcode( 'glc_wildlife_log', 'glc_shortcode_wildlife_log' );
function glc_shortcode_wildlife_log() {

    $events = get_posts( [
        'post_type'      => 'cleanup_event',
        'post_status'    => 'publish',
        'posts_per_page' => -1,
        'meta_query'     => [ [
            'key'     => 'wildlife_obs',
            'value'   => '',
            'compare' => '!=',
        ] ],
    ] );

    if ( empty( $events ) ) {
        return '<p class="glc-wildlife-empty">No wildlife sightings logged yet — check back after our next outing!</p>';
    }

    usort( $events, function( $a, $b ) {
        $da = get_post_meta( $a->ID, 'cleanup_date', true );
        $db = get_post_meta( $b->ID, 'cleanup_date', true );
        return strcmp( $db, $da );
    } );

    ob_start(); ?>
    <div class="glc-wildlife-table-wrap">
    <table class="glc-wildlife-table">
        <thead>
            <tr>
                <th scope="col">Date</th>
                <th scope="col">Site</th>
                <th scope="col">Wildlife Observed</th>
                <th scope="col"><span class="screen-reader-text">Link</span></th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ( $events as $event ) :
            $date     = get_post_meta( $event->ID, 'cleanup_date', true );
            $site     = get_post_meta( $event->ID, 'site_name', true );
            $wildlife = get_post_meta( $event->ID, 'wildlife_obs', true );
            if ( ! $wildlife ) continue;
            $date_fmt = $date ? date( 'M j, Y', strtotime( $date ) ) : '—';
        ?>
        <tr>
            <td class="glc-wl-date" data-label="Date"><?php echo esc_html( $date_fmt ); ?></td>
            <td class="glc-wl-site" data-label="Site"><?php echo esc_html( $site ?: '—' ); ?></td>
            <td class="glc-wl-obs"  data-label="Wildlife Observed"><?php echo esc_html( $wildlife ); ?></td>
            <td class="glc-wl-link"><a href="<?php echo esc_url( get_permalink( $event->ID ) ); ?>">View outing &#8594;</a></td>
        </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    </div>
    <?php
    return ob_get_clean();
}

// ── [glc_references] ─────────────────────────────────────────────────────────
// Replaces an inline reference list with a slide-out side panel.
// Usage: [glc_references]<ol>...</ol>[/glc_references]

add_shortcode( 'glc_references', 'glc_shortcode_references' );
function glc_shortcode_references( $atts, $content = '' ) {
    if ( ! trim( $content ) ) return '';

    $count = substr_count( $content, '<li' );
    $label = $count > 0 ? "Sources &amp; References ({$count})" : "Sources &amp; References";
    $uid   = wp_rand( 1000, 9999 );

    static $styles_printed = false;
    $styles = '';
    if ( ! $styles_printed ) {
        $styles_printed = true;
        $styles = '
<style>
.glc-refs-trigger{display:inline-flex;align-items:center;gap:7px;margin-top:28px;padding:9px 18px;background:transparent;border:1.5px solid var(--glc-gold);border-radius:4px;color:var(--glc-navy);font:600 .875rem var(--glc-font-body);cursor:pointer;transition:background .15s,color .15s}
.glc-refs-trigger:hover,.glc-refs-trigger[aria-expanded="true"]{background:var(--glc-gold);color:var(--glc-navy)}
.glc-refs-backdrop{position:fixed;inset:0;background:rgba(0,0,0,.4);z-index:1200;opacity:0;visibility:hidden;pointer-events:none;transition:opacity .25s,visibility .25s}
.glc-refs-backdrop.glc-refs-open{opacity:1;visibility:visible;pointer-events:auto}
.glc-refs-panel{position:fixed;top:0;right:0;width:min(440px,100vw);height:100vh;height:100dvh;background:#fff;z-index:1201;display:flex;flex-direction:column;box-shadow:-4px 0 28px rgba(0,0,0,.18);transform:translateX(100%);visibility:hidden;transition:transform .3s cubic-bezier(.4,0,.2,1),visibility .3s}
.glc-refs-panel.glc-refs-open{transform:translateX(0);visibility:visible}
.glc-refs-panel-hd{display:flex;align-items:center;justify-content:space-between;padding:16px 20px;background:var(--glc-navy);color:#fff;flex-shrink:0}
.glc-refs-panel-hd span{font:700 1rem var(--glc-font-display)}
.glc-refs-close{background:none;border:none;color:#fff;font-size:1.5rem;line-height:1;padding:0 4px;cursor:pointer;opacity:.75;transition:opacity .15s}
.glc-refs-close:hover{opacity:1}
.glc-refs-panel-body{flex:1;overflow-y:auto;padding:20px 24px;font-size:.875rem;line-height:1.7;color:var(--glc-text)}
.glc-refs-panel-body ol,.glc-refs-panel-body ul{padding-left:20px}
.glc-refs-panel-body li{margin-bottom:14px}
.glc-refs-panel-body a{color:var(--glc-navy);word-break:break-word}
.glc-refs-panel-body a:hover{color:var(--glc-gold)}
</style>';
    }

    ob_start();
    echo $styles;
    ?>
    <div id="glc-refs-<?php echo $uid; ?>" class="glc-refs-wrap">
        <button class="glc-refs-trigger"
                aria-expanded="false"
                aria-controls="glc-refs-panel-<?php echo $uid; ?>">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/><line x1="10" y1="9" x2="8" y2="9"/></svg>
            <?php echo $label; ?>
        </button>
        <div class="glc-refs-backdrop" aria-hidden="true"></div>
        <aside id="glc-refs-panel-<?php echo $uid; ?>"
               class="glc-refs-panel"
               aria-label="Sources and references">
            <div class="glc-refs-panel-hd">
                <span>Sources &amp; References</span>
                <button class="glc-refs-close" aria-label="Close references panel">&#215;</button>
            </div>
            <div class="glc-refs-panel-body">
                <?php echo wp_kses_post( do_shortcode( $content ) ); ?>
            </div>
        </aside>
    </div>
    <script>
    (function(){
        var w   = document.getElementById('glc-refs-<?php echo $uid; ?>');
        var btn = w.querySelector('.glc-refs-trigger');
        var bd  = w.querySelector('.glc-refs-backdrop');
        var pnl = document.getElementById('glc-refs-panel-<?php echo $uid; ?>');
        var cls = pnl.querySelector('.glc-refs-close');
        function open(){
            bd.classList.add('glc-refs-open');
            pnl.classList.add('glc-refs-open');
            btn.setAttribute('aria-expanded','true');
            document.body.style.overflow='hidden';
            cls.focus();
        }
        function close(){
            bd.classList.remove('glc-refs-open');
            pnl.classList.remove('glc-refs-open');
            btn.setAttribute('aria-expanded','false');
            document.body.style.overflow='';
            btn.focus();
        }
        btn.addEventListener('click',open);
        cls.addEventListener('click',close);
        bd.addEventListener('click',close);
        document.addEventListener('keydown',function(e){
            if(e.key==='Escape'&&pnl.classList.contains('glc-refs-open'))close();
        });
    })();
    </script>
    <?php
    return ob_get_clean();
}
