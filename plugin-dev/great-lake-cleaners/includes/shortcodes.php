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

// Corridor name -> slug lookup. Free-text `corridor` / `glc_corridor` meta is
// matched against this table (trim/case/apostrophe-normalized) so cumulative
// totals and the corridors.geojson line data can be joined on the same key.
// Grand River is deliberately absent -- it's a context line only (see
// prepare_corridors_geojson.py), never a cleanup corridor, so it never
// accumulates totals or gets a pin.
function glc_corridor_table() {
    return [
        'speed-river'     => 'Speed River',
        'eramosa-river'   => 'Eramosa River',
        'hanlon-creek'    => 'Hanlon Creek',
        'laurel-creek'    => 'Laurel Creek',
        'big-creek'       => 'Big Creek',
        'avon-river'      => 'Avon River',
        'black-ash-creek' => 'Black Ash Creek',
        'conestogo-river' => 'Conestogo River',
        'duchesnay-creek' => 'Duchesnay Creek',
        'bayfield-river'  => 'Bayfield River',
        'maitland-river'  => 'Maitland River',
        'hadati-creek'    => 'Hadati Creek',
        'ausable-river'   => 'Ausable River',
        'nine-mile-river' => 'Nine Mile River',
    ];
}

function glc_corridor_normalize( $name ) {
    $name = strtolower( trim( (string) $name ) );
    $name = str_replace( [ "'", "\xE2\x80\x99" ], '', $name ); // straight + curly apostrophes
    return preg_replace( '/\s+/', ' ', $name );
}

function glc_corridor_slug( $raw ) {
    $raw = trim( (string) $raw );
    if ( $raw === '' ) return '';
    static $lookup = null;
    if ( $lookup === null ) {
        $lookup = [];
        foreach ( glc_corridor_table() as $slug => $name ) {
            $lookup[ glc_corridor_normalize( $name ) ] = $slug;
        }
    }
    return $lookup[ glc_corridor_normalize( $raw ) ] ?? '';
}

function glc_haversine_km( $lat1, $lon1, $lat2, $lon2 ) {
    $dlat = deg2rad( $lat2 - $lat1 );
    $dlon = deg2rad( $lon2 - $lon1 );
    $a = sin( $dlat / 2 ) ** 2 + cos( deg2rad( $lat1 ) ) * cos( deg2rad( $lat2 ) ) * sin( $dlon / 2 ) ** 2;
    return 6371 * 2 * asin( sqrt( $a ) );
}

// Arc-length-weighted midpoint across one or more (possibly disjoint) line
// segments belonging to the same corridor, so the marker sits on the river
// rather than at an off-line centroid. Good enough as a "somewhere central
// along this corridor" anchor even when segments aren't end-to-end ordered.
function glc_corridor_midpoint( $lines ) {
    $segments = [];
    $total    = 0.0;
    foreach ( $lines as $line ) {
        for ( $i = 0, $n = count( $line ) - 1; $i < $n; $i++ ) {
            [ $lon1, $lat1 ] = $line[ $i ];
            [ $lon2, $lat2 ] = $line[ $i + 1 ];
            $len = glc_haversine_km( $lat1, $lon1, $lat2, $lon2 );
            $segments[] = [ $lat1, $lon1, $lat2, $lon2, $len ];
            $total      += $len;
        }
    }
    if ( empty( $segments ) ) {
        return [ $lines[0][0][1], $lines[0][0][0] ];
    }
    if ( $total <= 0 ) {
        return [ $segments[0][0], $segments[0][1] ];
    }
    $target = $total / 2;
    $walked = 0.0;
    foreach ( $segments as [ $lat1, $lon1, $lat2, $lon2, $len ] ) {
        if ( $walked + $len >= $target ) {
            $t = $len > 0 ? ( $target - $walked ) / $len : 0;
            return [ $lat1 + ( $lat2 - $lat1 ) * $t, $lon1 + ( $lon2 - $lon1 ) * $t ];
        }
        $walked += $len;
    }
    $last = end( $segments );
    return [ $last[2], $last[3] ];
}

add_shortcode( 'glc_map', 'glc_shortcode_map' );
function glc_shortcode_map( $atts ) {
    $atts = shortcode_atts( [
        'height'  => '480px',
        'post_id' => 0,          // if set, render a single-event map
        'limit'          => 0,   // max markers per geographic cluster (0 = no limit)
        'cluster_radius' => 0,   // km radius for grouping nearby markers (0 = no clustering)
        'corridors'      => 0,   // render river corridor lines (and, unless corridor_pins="0", cumulative-impact pins)
        'corridor_pins'  => 1,   // 0 = lines only, no gold corridor pins -- for a view that wants river context without another layer of markers
        'corridor_bounds' => 1,  // let corridor pins expand the map's fit-to-bounds zoom (0 = pins render but never zoom out to reach them -- for curated views that still show pins). No effect when corridor_pins="0" -- nothing to include either way.
        'markers'        => 1,   // render individual site pins (0 = corridor pins only -- less noisy once corridors carry the summary)
        'author'         => 0,   // restrict to one account's cleanups (a cleaner profile map). All-events mode only
        'zoom_offset'    => 1,   // levels tighter than the guaranteed-fit zoom for the multi-marker view (front-page hero passes 2 -- reads less zoomed-out and keeps Guelph visually centred; outliers just sit off the edge)
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
        $events = glc_get_all_cleanups();

        // Profile mode: one account's cleanups only. Filtered here, before the
        // dedup loop, so clustering and the fit-to-bounds see the same set the
        // pins do. author="0" is not "everyone's" -- it is the anonymous author
        // id, so an unset attribute has to mean no filter at all.
        $author = (int) $atts['author'];
        if ( $author > 0 ) {
            $events = array_values( array_filter( $events, function ( $e ) use ( $author ) {
                return (int) $e->post_author === $author;
            } ) );
        }

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

    // Corridor lines + cumulative-impact pins (archive map only, opt-in via `corridors="1"`)
    $corridor_lines_url = '';
    $corridor_totals    = [];

    if ( (int) $atts['corridors'] && (int) $atts['post_id'] === 0 ) {
        $geojson_path = GLC_PLUGIN_DIR . 'assets/corridors.geojson';
        // corridor_pins="0" (front-page hero, cleaner profiles) wants the lines
        // and nothing else, so the cumulative walk over every cleanup below is
        // skipped entirely rather than computed and thrown away.
        $want_pins = (bool) (int) $atts['corridor_pins'];
        $lines_by_slug = [];

        if ( file_exists( $geojson_path ) ) {
            $corridor_geo = json_decode( (string) file_get_contents( $geojson_path ), true );
            foreach ( $corridor_geo['features'] ?? [] as $feat ) {
                $slug = $feat['properties']['slug'] ?? '';
                if ( ! $slug ) continue;
                $geom = $feat['geometry'] ?? [];
                if ( ( $geom['type'] ?? '' ) === 'LineString' ) {
                    $lines_by_slug[ $slug ][] = $geom['coordinates'];
                } elseif ( ( $geom['type'] ?? '' ) === 'MultiLineString' ) {
                    foreach ( $geom['coordinates'] as $line ) $lines_by_slug[ $slug ][] = $line;
                }
            }
            if ( $lines_by_slug ) {
                // mtime query string lets the browser cache this aggressively
                // (see assets/.htaccess) without ever serving a stale file --
                // any update to corridors.geojson changes the URL.
                $corridor_lines_url = GLC_PLUGIN_URL . 'assets/corridors.geojson?v=' . filemtime( $geojson_path );
            }
        }

        $corridor_table = glc_corridor_table();
        $corridor_cleanups = $want_pins ? glc_get_all_cleanups() : [];
        foreach ( $corridor_cleanups as $e ) {
            $slug = glc_corridor_slug( glc_cleanup_field( $e, 'corridor' ) );
            if ( ! $slug ) continue;

            if ( ! isset( $corridor_totals[ $slug ] ) ) {
                $corridor_totals[ $slug ] = [
                    'slug' => $slug,
                    'name' => $corridor_table[ $slug ],
                    'weight_kg' => 0.0, 'bags' => 0, 'items_recycled' => 0,
                    'lat_sum' => 0.0, 'lon_sum' => 0.0, 'gps_count' => 0,
                ];
            }
            $corridor_totals[ $slug ]['weight_kg']      += (float) glc_cleanup_field( $e, 'weight_kg' );
            $corridor_totals[ $slug ]['bags']           += (int)   glc_cleanup_field( $e, 'bags' );
            $corridor_totals[ $slug ]['items_recycled'] += (int)   glc_cleanup_field( $e, 'items_recycled' );

            $lat = (float) glc_cleanup_field( $e, 'gps_lat' );
            $lon = (float) glc_cleanup_field( $e, 'gps_lon' );
            if ( $lat && $lon ) {
                $corridor_totals[ $slug ]['lat_sum'] += $lat;
                $corridor_totals[ $slug ]['lon_sum'] += $lon;
                $corridor_totals[ $slug ]['gps_count']++;
            }
        }

        foreach ( $corridor_totals as $slug => &$t ) {
            if ( isset( $lines_by_slug[ $slug ] ) ) {
                [ $t['lat'], $t['lon'] ] = glc_corridor_midpoint( $lines_by_slug[ $slug ] );
            } elseif ( $t['gps_count'] > 0 ) {
                $t['lat'] = $t['lat_sum'] / $t['gps_count'];
                $t['lon'] = $t['lon_sum'] / $t['gps_count'];
            } else {
                unset( $corridor_totals[ $slug ] ); // no line and no GPS -- nowhere to place a pin
                continue;
            }
            unset( $t['lat_sum'], $t['lon_sum'], $t['gps_count'] );
        }
        unset( $t );
        $corridor_totals = array_values( $corridor_totals );
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
        // Popups are built as HTML strings and handed to bindPopup(), so every
        // interpolated value is escaped first. Site names and cleanup titles
        // reach here from community submissions and from post titles, neither of
        // which is guaranteed to be markup-free. Same helper as page-stats.php.
        function esc(s) { var d = document.createElement('div'); d.textContent = String(s == null ? '' : s); return d.innerHTML; }

        var markers = <?php echo wp_json_encode( $markers ); ?>;
        var corridorLinesUrl = <?php echo wp_json_encode( $corridor_lines_url ); ?>;
        var corridorTotals = <?php echo wp_json_encode( $corridor_totals ); ?>;
        var corridorBounds = <?php echo wp_json_encode( (bool) $atts['corridor_bounds'] ); ?>;
        var showCorridorPins = <?php echo wp_json_encode( (bool) $atts['corridor_pins'] ); ?>;
        var showMarkers = <?php echo wp_json_encode( (bool) $atts['markers'] ); ?>;
        var zoomOffset = <?php echo wp_json_encode( (int) $atts['zoom_offset'] ); ?>;
        var archiveUrl = <?php echo wp_json_encode( get_post_type_archive_link( 'cleanup_event' ) ?: home_url( '/cleanups/' ) ); ?>;
        // Guelph is home base. Every map centres here; a fit-to-bounds only ever
        // sets the zoom level, never the centre (see the fit block at the end).
        var GLC_HOME = [43.545, -80.248];
        var map = L.map(<?php echo wp_json_encode( $map_id ); ?>, { zoomControl: false }).setView(GLC_HOME, 12);
        L.tileLayer('https://{s}.basemaps.cartocdn.com/light_all/{z}/{x}/{y}{r}.png?key=cb1_29e5_1_17f74f1d3418f4c313616f46', {
            attribution: '© <a href="https://openstreetmap.org/copyright">OpenStreetMap</a> contributors © <a href="https://carto.com/attributions">CARTO</a>',
            subdomains: 'abcd',
            maxZoom: 19
        }).addTo(map);

        // River corridor lines — fetched separately (not inlined) since the
        // geometry is large; it's a static, cacheable asset either way.
        if (corridorLinesUrl) {
            fetch(corridorLinesUrl)
                .then(function(r) { return r.json(); })
                .then(function(geo) {
                    L.geoJSON(geo, {
                        style: { color: '#5a9fc0', weight: 3, opacity: 0.55 }
                    }).addTo(map).bringToBack();
                    map.attributionControl.addAttribution(
                        'River corridors: <a href="https://geohub.lio.gov.on.ca/datasets/mnrf::ontario-hydro-network-ohn-watercourse" target="_blank" rel="noopener">Ontario Hydro Network</a>, MNRF, Open Government Licence – Ontario'
                    );
                })
                .catch(function() { /* corridor lines are decorative context — fail silently */ });
        }

        if (showMarkers) {
            var icon = L.divIcon({
                className: 'glc-marker',
                html: '<svg width="20" height="26" viewBox="0 0 20 26" xmlns="http://www.w3.org/2000/svg"><path d="M10 0C4.48 0 0 4.48 0 10c0 7.5 10 16 10 16s10-8.5 10-16C20 4.48 15.52 0 10 0z" fill="#1a4a6b"/><circle cx="10" cy="10" r="4" fill="#ffffff"/></svg>',
                iconSize: [20, 26],
                iconAnchor: [10, 26],
                popupAnchor: [0, -28],
            });

            markers.forEach(function(m) {
                var popup = '<strong>' + esc(m.title) + '</strong><br>'
                          + esc(m.date) + '<br>'
                          + esc(m.bags) + (m.bags === 1 ? ' bag' : ' bags') + ' collected<br>'
                          + '<a href="' + esc(m.url) + '">View details →</a>';
                L.marker([m.lat, m.lon], {icon: icon})
                 .addTo(map)
                 .bindPopup(popup);
            });
        }

        // Corridor cumulative-impact pins — one per corridor with published totals.
        if (showCorridorPins) {
            var corridorIcon = L.divIcon({
                className: 'glc-corridor-marker',
                html: '<svg width="22" height="22" viewBox="0 0 22 22" xmlns="http://www.w3.org/2000/svg"><rect x="3" y="3" width="16" height="16" rx="2" fill="#f5a623" stroke="#ffffff" stroke-width="2" transform="rotate(45 11 11)"/></svg>',
                iconSize: [22, 22],
                iconAnchor: [11, 11],
                popupAnchor: [0, -14],
            });

            corridorTotals.forEach(function(c) {
                var popup = '<strong>' + esc(c.name) + '</strong><br>'
                          + Math.round(c.weight_kg) + ' kg removed<br>'
                          + c.bags + (c.bags === 1 ? ' bag' : ' bags') + ' collected<br>'
                          + c.items_recycled + ' items recycled<br>'
                          + '<a href="' + esc(archiveUrl) + '?corridor=' + encodeURIComponent(c.slug) + '">View cleanups on the ' + esc(c.name) + ' →</a>';
                L.marker([c.lat, c.lon], {icon: corridorIcon})
                 .addTo(map)
                 .bindPopup(popup);
            });
        }

        // Fit bounds or zoom to a single pin. Corridor pins only widen the fit
        // when shown *and* corridorBounds is on -- a curated view (the front-page
        // hero) can show corridor lines without letting a far-off pin (Bayfield,
        // Duchesnay Creek, ...) zoom the whole map out to reach it.
        var allPoints = showMarkers ? markers.map(function(m){ return [m.lat, m.lon]; }) : [];
        if (showCorridorPins && corridorBounds) {
            allPoints = allPoints.concat(corridorTotals.map(function(c){ return [c.lat, c.lon]; }));
        }

        if (allPoints.length === 1) {
            // One pin means a single-event map, where the pin *is* the subject.
            map.setView(allPoints[0], 15);
        } else if (allPoints.length > 1) {
            // Take the zoom from the bounds but never the centre. One far-off site
            // (Duchesnay Creek up in North Bay, Bayfield out on Huron) drags the
            // bounds centre halfway to Georgian Bay, and the map opens with every
            // real cleanup crowded into a corner. Guelph stays centred instead;
            // outliers sit off the edge, and the map still pans and zooms.
            //
            // getBoundsZoom() is the same calculation fitBounds() runs internally,
            // but it only computes — it never moves the map. That matters twice
            // over: there's no fit-then-pan-back (which lands ~a pixel off Guelph,
            // since panBy rounds its offset), and no reading back a zoom that
            // fitBounds may not have applied yet — it defers to requestAnimationFrame
            // whenever the zoom delta is small enough to animate.
            // Padding is the *total*, so fitBounds' [40, 40] per side is [80, 80].
            var fitZoom = map.getBoundsZoom(L.latLngBounds(allPoints), false, L.point(80, 80));
            // zoomOffset levels tighter than the zoom guaranteed to fit (default 1
            // — still comfortably inside the padding, reads far less zoomed-out).
            // The front-page hero passes 2: at +1 its wide spread (North Bay down to
            // Long Point) still opens too far out to read as a Guelph map.
            map.setView(GLC_HOME, fitZoom + zoomOffset);
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

// ── Gallery helpers (shared by [glc_gallery] and [glc_video_gallery]) ────────
// Both galleries are the same object with a different medium: a year-tabbed
// grid of admin-flagged attachments backed by a keyboard-navigable lightbox.
// Collection, year grouping, tab markup, and lightbox behaviour live here once
// so the photo and video galleries cannot drift apart.
//
// Sources for both:
//   - Media attached to cleanup_event posts (uploaded while editing)
//   - Media attached to published glc_submission posts where repost consent = '1'
//   - Media Library uploads with no parent at all (post_parent = 0)
// Year comes from the parent's cleanup_date / glc_cleanup_date meta, falling
// back to the upload date.

/**
 * Collects gallery-flagged attachments of one mime family, resolving each one's
 * parent cleanup metadata (date, site label, permalink).
 *
 * Queries by feature flag alone rather than by post_parent: media inserted from
 * the Media Library keeps post_parent = 0, so a parent-scoped query would miss
 * it. One global meta query finds everything the admin explicitly flagged, then
 * parent metadata is resolved per attachment.
 *
 * @param  string $mime_type Mime family to query, e.g. 'image' or 'video'.
 * @param  string $flag_key  Attachment meta key holding the '1' feature flag.
 * @return array             Flat list of items, each with id, year, alt, label,
 *                           title, date, sort_date, url.
 */
function glc_gallery_collect( $mime_type, $flag_key ) {

    $all_atts = get_posts( [
        'post_type'      => 'attachment',
        'post_status'    => 'inherit',
        'post_mime_type' => $mime_type,
        'posts_per_page' => -1,
        'orderby'        => 'menu_order date',
        'order'          => 'ASC',
        'meta_query'     => [ [
            'key'   => $flag_key,
            'value' => '1',
        ] ],
    ] );

    if ( empty( $all_atts ) ) return [];

    // Pre-load published glc_submission posts with repost consent into a lookup
    // so we can skip submission media where the submitter didn't consent.
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

    $items = [];
    $seen  = [];  // dedup by attachment ID

    foreach ( $all_atts as $att ) {
        if ( isset( $seen[ $att->ID ] ) ) continue;
        $seen[ $att->ID ] = true;

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
        // media-library items don't all sink to the bottom of the year tab.
        $sort_date = $date ?: substr( $att->post_date, 0, 10 );

        $items[] = [
            'id'        => $att->ID,
            'year'      => $year,
            'alt'       => $alt,
            'label'     => $label,
            'title'     => $att->post_title,
            'date'      => $date,
            'sort_date' => $sort_date,
            'url'       => $url,
        ];
    }

    return $items;
}

/**
 * Groups collected items into [ year => items ], years descending and items
 * within each year by sort_date descending.
 */
function glc_gallery_group_by_year( $items ) {
    $by_year = [];
    foreach ( $items as $item ) {
        $by_year[ $item['year'] ][] = $item;
    }
    krsort( $by_year );
    foreach ( $by_year as &$list ) {
        usort( $list, fn( $a, $b ) => strcmp( $b['sort_date'], $a['sort_date'] ) );
    }
    unset( $list );
    return $by_year;
}

/**
 * Alternative grouping for the combined "See Us In Action" media wall
 * (page-see-us-in-action.php): no year buckets, just the most recent $limit
 * items in one list sorted by sort_date descending.
 *
 * Returns the same [ key => items ] shape as glc_gallery_group_by_year() — a
 * single bucket keyed 0 — so every downstream caller works unchanged:
 * glc_gallery_flatten() flattens it, glc_gallery_render_tabs() renders nothing
 * for a one-bucket set, and the per-bucket grid loop emits one always-active
 * grid. $limit <= 0 means "no cap" (still one flat, tab-less bucket).
 */
function glc_gallery_recent_grouping( $items, $limit ) {
    usort( $items, fn( $a, $b ) => strcmp( $b['sort_date'], $a['sort_date'] ) );
    if ( $limit > 0 ) {
        $items = array_slice( $items, 0, $limit );
    }
    return [ 0 => $items ];
}

/**
 * Flattens grouped items into one indexed list for lightbox navigation, and
 * returns the per-year start offsets so each thumbnail knows its global index.
 *
 * @return array [ $flat_items, [ year => offset ] ]
 */
function glc_gallery_flatten( $by_year ) {
    $flat    = [];
    $offsets = [];
    $offset  = 0;
    foreach ( $by_year as $yr => $list ) {
        $offsets[ $yr ] = $offset;
        foreach ( $list as $item ) {
            $item['year'] = $yr;
            $flat[]       = $item;
        }
        $offset += count( $list );
    }
    return [ $flat, $offsets ];
}

/**
 * Year tab strip. Returns '' when there is only one year — a lone tab is noise.
 */
function glc_gallery_render_tabs( $by_year, $aria_label ) {
    $years = array_keys( $by_year );
    if ( count( $years ) < 2 ) return '';
    $first = $years[0];

    ob_start(); ?>
    <div class="glc-gallery-tabs" role="tablist" aria-label="<?php echo esc_attr( $aria_label ); ?>">
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
    <?php
    return ob_get_clean();
}

/**
 * Tab switching + lightbox behaviour, shared by both galleries.
 *
 * $is_video swaps the lightbox <img> for a <video>: navigating pauses and
 * reloads the element rather than just swapping a src, and arrow keys are
 * handed back to the player when it holds focus so they seek instead of
 * moving to the next clip.
 *
 * @param string $gallery_id Wrapper element ID (lightbox is "{id}-lb").
 * @param array  $items      Flattened list, indexed to match data-global-idx.
 * @param bool   $is_video   Whether the lightbox holds a <video>.
 */
function glc_gallery_render_script( $gallery_id, $items, $is_video ) {
    ob_start(); ?>
    <script>
    (function() {
        var wrap    = document.getElementById(<?php echo wp_json_encode( $gallery_id ); ?>);
        var lb      = document.getElementById(<?php echo wp_json_encode( $gallery_id . '-lb' ); ?>);
        var items   = <?php echo wp_json_encode( array_values( $items ) ); ?>;
        var isVideo = <?php echo $is_video ? 'true' : 'false'; ?>;
        var currentIdx  = 0;
        var lastTrigger = null;

        // ── Tab switching ──────────────────────────────────────────────
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

        // ── Lightbox ──────────────────────────────────────────────────
        var media   = lb.querySelector(isVideo ? '.glc-lb-video' : '.glc-lb-img');
        var lbLabel = lb.querySelector('.glc-lb-label');
        var lbLink  = lb.querySelector('.glc-lb-link');

        // Tab order inside the trap. The <video> joins it so keyboard users can
        // reach the native controls — without it, Tab is trapped among the
        // close/prev/next buttons and the player is unreachable. An <img> has
        // nothing to operate, so the photo lightbox leaves it out.
        var focusables = [
            lb.querySelector('.glc-lb-close'),
            lb.querySelector('.glc-lb-prev'),
            lb.querySelector('.glc-lb-next'),
        ];
        if ( isVideo ) focusables.push(media);
        focusables.push(lbLink);

        function showItem(idx) {
            if ( idx < 0 ) idx = items.length - 1;
            if ( idx >= items.length ) idx = 0;
            currentIdx = idx;
            var p = items[idx];

            if ( isVideo ) {
                media.pause();
                media.src = p.src;
                if ( p.poster ) { media.poster = p.poster; }
                else            { media.removeAttribute('poster'); }
                media.load();
                // Autoplay is allowed here because opening the lightbox is
                // always a user gesture; swallow a rejection if a browser
                // disagrees and leave the controls to the visitor.
                var played = media.play();
                if ( played && played.catch ) { played.catch(function() {}); }
            } else {
                media.src = p.src;
                media.alt = p.alt;
            }

            lbLabel.textContent = p.title || p.label;
            // Unparented media has no outing to link to — hide the link rather
            // than render a dead href.
            lbLink.href   = p.url || '';
            lbLink.hidden = ! p.url;

            lb.hidden = false;
            document.body.style.overflow = 'hidden';
            lb.querySelector('.glc-lb-close').focus();
        }

        function closeLightbox() {
            if ( isVideo ) media.pause();
            lb.hidden = true;
            document.body.style.overflow = '';
            if (lastTrigger) lastTrigger.focus();
        }

        wrap.querySelectorAll('.glc-gallery-thumb-btn').forEach(function(btn) {
            btn.addEventListener('click', function() {
                lastTrigger = btn;
                showItem( parseInt(btn.dataset.globalIdx, 10) );
            });
        });

        lb.querySelector('.glc-lb-close').addEventListener('click', closeLightbox);
        lb.querySelector('.glc-lb-prev').addEventListener('click', function() { showItem(currentIdx - 1); });
        lb.querySelector('.glc-lb-next').addEventListener('click', function() { showItem(currentIdx + 1); });

        lb.addEventListener('click', function(e) {
            if ( e.target === lb ) closeLightbox();
        });

        document.addEventListener('keydown', function(e) {
            if ( lb.hidden ) return;
            if ( e.key === 'Escape' ) { closeLightbox(); return; }
            // While the player has focus, arrows belong to it for seeking.
            var mediaFocused = isVideo && e.target === media;
            if ( e.key === 'ArrowLeft'  && ! mediaFocused ) { showItem(currentIdx - 1); return; }
            if ( e.key === 'ArrowRight' && ! mediaFocused ) { showItem(currentIdx + 1); return; }
            if ( e.key === 'Tab' ) {
                e.preventDefault();
                // The "View outing" link is hidden for unparented media, so the
                // focus ring is rebuilt from what is actually visible.
                var visible = focusables.filter(function(el) { return ! el.hidden; });
                var fi = visible.indexOf(document.activeElement);
                if (e.shiftKey) {
                    visible[(fi <= 0 ? visible.length : fi) - 1].focus();
                } else {
                    visible[(fi + 1) % visible.length].focus();
                }
            }
        });
    })();
    </script>
    <?php
    return ob_get_clean();
}

// ── [glc_gallery] ─────────────────────────────────────────────────────────
// Renders a year-tabbed photo grid with a vanilla JS lightbox.

add_shortcode( 'glc_gallery', 'glc_shortcode_gallery' );
function glc_shortcode_gallery( $atts ) {

    // limit > 0 → drop the year tabs and show only the most recent N photos in
    // one grid (the See Us In Action wall). Default 0 → full year-tabbed gallery.
    $a     = shortcode_atts( [ 'limit' => 0 ], $atts, 'glc_gallery' );
    $limit = max( 0, (int) $a['limit'] );

    $photos = [];
    foreach ( glc_gallery_collect( 'image', '_glc_gallery' ) as $item ) {
        $src   = wp_get_attachment_image_url( $item['id'], 'large' );
        $thumb = wp_get_attachment_image_url( $item['id'], 'medium' );
        if ( ! $src || ! $thumb ) continue;

        $item['src']   = $src;
        $item['thumb'] = $thumb;
        $photos[]      = $item;
    }

    if ( empty( $photos ) ) {
        return '<p class="glc-gallery-empty">No photos yet — check back after our next outing!</p>';
    }

    $by_year = $limit
        ? glc_gallery_recent_grouping( $photos, $limit )
        : glc_gallery_group_by_year( $photos );
    $years   = array_keys( $by_year );
    $first   = $years[0];

    list( $all_photos, $year_offsets ) = glc_gallery_flatten( $by_year );

    $gallery_id = 'glc-gallery-' . wp_rand( 1000, 9999 );

    ob_start(); ?>
    <div class="glc-gallery-wrap" id="<?php echo esc_attr( $gallery_id ); ?>">

        <?php echo glc_gallery_render_tabs( $by_year, 'Filter photos by year' ); ?>

        <!-- Photo grids — one per year, only active one visible -->
        <?php foreach ( $by_year as $yr => $photos_in_year ) :
            $global_start = $year_offsets[ $yr ];
        ?>
        <div class="glc-gallery-grid<?php echo $yr === $first ? ' glc-grid-active' : ''; ?>"
             data-year="<?php echo esc_attr( $yr ); ?>"
             role="tabpanel">
            <?php foreach ( $photos_in_year as $i => $photo ) :
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

    <?php echo glc_gallery_render_script( $gallery_id, $all_photos, false ); ?>
    <?php
    return ob_get_clean();
}

// ── [glc_video_gallery] ───────────────────────────────────────────────────
// Year-tabbed video grid with a lightbox player — the [glc_gallery] structure
// with a <video> in place of the <img>, reusing its grid/tab/lightbox CSS. Only
// attachments flagged _glc_video_gallery = '1' appear (Media Library → tick
// "Feature in video gallery" → Save).
//
// Thumbnails: WordPress does not generate poster frames for uploaded video
// (that needs ffmpeg on the host), so each tile embeds the clip itself with
// preload="metadata" and a "#t=0.5" media fragment — browsers seek to that
// timestamp and paint the frame, which costs the file header rather than the
// whole clip. A featured image set on the attachment wins over that frame.

add_shortcode( 'glc_video_gallery', 'glc_shortcode_video_gallery' );
function glc_shortcode_video_gallery( $atts ) {

    // limit > 0 → drop the year tabs and show only the most recent N clips in
    // one grid (the See Us In Action wall). Default 0 → full year-tabbed gallery.
    $a     = shortcode_atts( [ 'limit' => 0 ], $atts, 'glc_video_gallery' );
    $limit = max( 0, (int) $a['limit'] );

    $videos = [];
    foreach ( glc_gallery_collect( 'video', '_glc_video_gallery' ) as $item ) {
        $src = wp_get_attachment_url( $item['id'] );
        if ( ! $src ) continue;

        // Optional hand-picked poster frame, when one has been attached.
        $poster    = '';
        $poster_id = (int) get_post_thumbnail_id( $item['id'] );
        if ( $poster_id ) {
            $poster = wp_get_attachment_image_url( $poster_id, 'large' ) ?: '';
        }

        $meta = wp_get_attachment_metadata( $item['id'] );

        $item['src']      = $src;
        $item['poster']   = $poster;
        $item['duration'] = ! empty( $meta['length_formatted'] ) ? $meta['length_formatted'] : '';
        $videos[]         = $item;
    }

    if ( empty( $videos ) ) {
        return '<p class="glc-gallery-empty">No videos yet — check back after our next outing!</p>';
    }

    $by_year = $limit
        ? glc_gallery_recent_grouping( $videos, $limit )
        : glc_gallery_group_by_year( $videos );
    $years   = array_keys( $by_year );
    $first   = $years[0];

    list( $all_videos, $year_offsets ) = glc_gallery_flatten( $by_year );

    $gallery_id = 'glc-video-gallery-' . wp_rand( 1000, 9999 );

    ob_start(); ?>
    <div class="glc-gallery-wrap glc-video-gallery" id="<?php echo esc_attr( $gallery_id ); ?>">

        <?php echo glc_gallery_render_tabs( $by_year, 'Filter videos by year' ); ?>

        <!-- Video grids — one per year, only active one visible -->
        <?php foreach ( $by_year as $yr => $videos_in_year ) :
            $global_start = $year_offsets[ $yr ];
        ?>
        <div class="glc-gallery-grid<?php echo $yr === $first ? ' glc-grid-active' : ''; ?>"
             data-year="<?php echo esc_attr( $yr ); ?>"
             role="tabpanel">
            <?php foreach ( $videos_in_year as $i => $video ) :
                $global_idx = $global_start + $i;
                $aria = 'Play video: ' . ( $video['label'] ?: $video['title'] );
                if ( $video['duration'] ) $aria .= ' (' . $video['duration'] . ')';
            ?>
            <button
                class="glc-gallery-thumb-btn glc-video-thumb-btn"
                aria-label="<?php echo esc_attr( $aria ); ?>"
                data-global-idx="<?php echo $global_idx; ?>">
                <?php if ( $video['poster'] ) : ?>
                <img src="<?php echo esc_url( $video['poster'] ); ?>" alt="" loading="lazy">
                <?php else : ?>
                <video
                    src="<?php echo esc_url( $video['src'] ); ?>#t=0.5"
                    preload="metadata"
                    muted
                    playsinline
                    tabindex="-1"
                    aria-hidden="true"></video>
                <?php endif; ?>
                <span class="glc-vid-play" aria-hidden="true">
                    <svg viewBox="0 0 24 24" width="26" height="26" focusable="false" aria-hidden="true">
                        <path d="M8 5.5v13l11-6.5z" fill="currentColor"/>
                    </svg>
                </span>
                <?php if ( $video['duration'] ) : ?>
                <span class="glc-vid-dur" aria-hidden="true"><?php echo esc_html( $video['duration'] ); ?></span>
                <?php endif; ?>
                <span class="glc-thumb-caption"><?php echo esc_html( $video['label'] ); ?></span>
            </button>
            <?php endforeach; ?>
        </div>
        <?php endforeach; ?>

        <!-- Lightbox -->
        <div class="glc-lightbox glc-lightbox-video" id="<?php echo esc_attr( $gallery_id ); ?>-lb"
             role="dialog" aria-modal="true" aria-label="Video player" hidden>
            <button class="glc-lb-close" aria-label="Close video player">&#x2715;</button>
            <button class="glc-lb-prev" aria-label="Previous video">&#x2039;</button>
            <button class="glc-lb-next" aria-label="Next video">&#x203a;</button>
            <div class="glc-lb-inner">
                <video class="glc-lb-video" controls playsinline preload="metadata"></video>
                <div class="glc-lb-meta">
                    <span class="glc-lb-label"></span>
                    <a class="glc-lb-link" href="" target="_blank" rel="noopener">View outing →<span class="screen-reader-text"> (opens in new tab)</span></a>
                </div>
            </div>
        </div>
    </div>

    <?php echo glc_gallery_render_script( $gallery_id, $all_videos, true ); ?>
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
