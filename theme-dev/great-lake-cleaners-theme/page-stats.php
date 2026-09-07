<?php
/**
 * Template Name: Stats
 * Template for /stats/ — redesigned impact stats page with hero.
 */

// ── 1. Collect & compute all stats data ─────────────────────────────────────

$_events = get_posts( [
	'post_type'      => 'cleanup_event',
	'post_status'    => 'publish',
	'posts_per_page' => -1,
] );
$_subs = get_posts( [
	'post_type'      => 'glc_submission',
	'post_status'    => 'publish',
	'posts_per_page' => -1,
] );

// Build unified points list: [ date, weight_kg, recycled, hours ]
$_raw = [];
foreach ( $_events as $_e ) {
	$_d = get_post_meta( $_e->ID, 'cleanup_date', true );
	if ( ! $_d ) continue;
	$_raw[] = [
		'date'     => $_d,
		'weight'   => (float) get_post_meta( $_e->ID, 'weight_kg',      true ),
		'recycled' => (int)   get_post_meta( $_e->ID, 'items_recycled', true ),
		'hours'    => (float) get_post_meta( $_e->ID, 'hours',          true ),
	];
}
foreach ( $_subs as $_s ) {
	$_d = get_post_meta( $_s->ID, 'glc_cleanup_date', true );
	if ( ! $_d ) continue;
	$_raw[] = [
		'date'     => $_d,
		'weight'   => (float) get_post_meta( $_s->ID, 'weight_kg',     true ),
		'recycled' => (int)   get_post_meta( $_s->ID, 'items_recycled', true ),
		'hours'    => (float) get_post_meta( $_s->ID, 'glc_hours',      true ),
	];
}

// Sort ascending by date
usort( $_raw, fn( $a, $b ) => strcmp( $a['date'], $b['date'] ) );

// Group same-date totals
$_grouped = [];
foreach ( $_raw as $_p ) {
	$_dk = $_p['date'];
	if ( ! isset( $_grouped[ $_dk ] ) ) {
		$_grouped[ $_dk ] = [ 'weight' => 0.0, 'recycled' => 0, 'hours' => 0.0 ];
	}
	$_grouped[ $_dk ]['weight']   += $_p['weight'];
	$_grouped[ $_dk ]['recycled'] += $_p['recycled'];
	$_grouped[ $_dk ]['hours']    += $_p['hours'];
}

// Compute cumulative series and day offsets
$_first_ts   = 0;
$_max_day    = 0;
$_data_days  = [];
$_data_debris = [];
$_data_recyc  = [];
$_data_hours  = [];
$_run_kg   = 0.0;
$_run_rec  = 0;
$_run_hrs  = 0.0;

$_dates = array_keys( $_grouped );
if ( ! empty( $_dates ) ) {
	$_first_ts = strtotime( $_dates[0] );
	foreach ( $_grouped as $_date => $_totals ) {
		$_day_off = (int) round( ( strtotime( $_date ) - $_first_ts ) / 86400 );
		$_run_kg  += $_totals['weight'];
		$_run_rec += $_totals['recycled'];
		$_run_hrs += $_totals['hours'];
		$_data_days[]   = $_day_off;
		$_data_debris[] = round( $_run_kg,  1 );
		$_data_recyc[]  = $_run_rec;
		$_data_hours[]  = round( $_run_hrs, 1 );
	}
	$_max_day = end( $_data_days );
}

// Timeframe label for the hero lede — "in the last N months", switching to
// years once we've been active 12+ months. Based on the first cleanup date.
$_timeframe_label = '';
if ( $_first_ts ) {
	$_days_active   = ( current_time( 'timestamp' ) - $_first_ts ) / DAY_IN_SECONDS;
	$_months_active = max( 1, (int) round( $_days_active / 30.44 ) );
	if ( $_months_active >= 12 ) {
		$_years_active    = max( 1, (int) round( $_months_active / 12 ) );
		$_timeframe_label = ( $_years_active === 1 ) ? 'in the last year' : 'in the last ' . $_years_active . ' years';
	} else {
		$_timeframe_label = ( $_months_active === 1 ) ? 'in the last month' : 'in the last ' . $_months_active . ' months';
	}
}

// Totals
$_total_debris = empty( $_data_debris ) ? 0 : end( $_data_debris );
$_total_recyc  = empty( $_data_recyc )  ? 0 : end( $_data_recyc );
$_total_hours  = empty( $_data_hours )  ? 0 : end( $_data_hours );

// Unique sites count (events + subs)
$_site_names = [];
foreach ( $_events as $_e ) {
	$_sn = get_post_meta( $_e->ID, 'site_name', true );
	if ( $_sn ) $_site_names[] = $_sn;
}
foreach ( $_subs as $_s ) {
	$_sn = get_post_meta( $_s->ID, 'glc_site_name', true );
	if ( $_sn ) $_site_names[] = $_sn;
}
$_unique_sites = count( array_unique( array_filter( $_site_names ) ) );

// Corridors count: mirrors glc_get_impact_stats() — distinct 'corridor' meta values (lowercased).
$_corridors_map = [];
foreach ( $_events as $_e ) {
	$_c = trim( (string) get_post_meta( $_e->ID, 'corridor', true ) );
	if ( $_c !== '' ) $_corridors_map[ strtolower( $_c ) ] = true;
}
foreach ( $_subs as $_s ) {
	$_c = trim( (string) get_post_meta( $_s->ID, 'glc_corridor', true ) );
	if ( $_c !== '' ) $_corridors_map[ strtolower( $_c ) ] = true;
}
$_total_corridors = count( $_corridors_map );

// Moose metaphor (1 moose ≈ 400 kg)
$_moose_count = max( 1, (int) round( $_total_debris / 400 ) );
$_moose_label = $_moose_count === 1 ? 'one moose' : number_format( $_moose_count ) . ' moose';

// Recent haul: last 3 calendar months with data (1 bag ≈ 15 kg)
$_month_haul = [];
foreach ( $_grouped as $_date => $_totals ) {
	$_mk = substr( $_date, 0, 7 );  // YYYY-MM
	$_mn = date( 'F', strtotime( $_date ) );
	if ( ! isset( $_month_haul[ $_mk ] ) ) {
		$_month_haul[ $_mk ] = [ 'name' => $_mn, 'kg' => 0.0 ];
	}
	$_month_haul[ $_mk ]['kg'] += $_totals['weight'];
}
$_recent_months = array_slice( $_month_haul, -3, 3, true );
$_haul_rows = [];
foreach ( $_recent_months as $_m ) {
	$_haul_rows[] = [
		'name' => $_m['name'],
		'bags' => max( 1, (int) round( $_m['kg'] / 15 ) ),
	];
}

// Item dot pictograph (self-scaling grid of 28 columns)
$_MAX_DOTS = 28 * 20;
$_steps    = [ 2, 3, 5, 8, 10, 15, 20, 25, 30, 50 ];
$_per_dot  = 3;
foreach ( $_steps as $_s ) {
	if ( (int) ceil( $_total_recyc / $_s ) <= $_MAX_DOTS ) {
		$_per_dot = $_s;
		break;
	}
}
$_dot_count = (int) ceil( $_total_recyc / $_per_dot );
$_dot_label = 'Every dot = ' . $_per_dot . ( $_per_dot === 1 ? ' item' : ' items' );

// Large items pictograph: one icon per tire / bike / shopping cart, each linking to its source post
$_tire_items = [];
$_bike_items = [];
$_cart_items = [];
foreach ( array_merge( $_events, $_subs ) as $_p ) {
	$_p_tires = (int) glc_cleanup_field( $_p, 'tires_removed' );
	$_p_bikes = (int) glc_cleanup_field( $_p, 'bikes_removed' );
	$_p_carts = (int) glc_cleanup_field( $_p, 'carts_removed' );
	if ( ! $_p_tires && ! $_p_bikes && ! $_p_carts ) continue;

	$_p_url   = get_permalink( $_p->ID );
	$_p_site  = glc_cleanup_field( $_p, 'site_name' );
	$_p_date  = glc_cleanup_field( $_p, 'cleanup_date' );
	$_p_label = trim( $_p_site . ( $_p_date ? ', ' . date( 'M j, Y', strtotime( $_p_date ) ) : '' ), ', ' );

	for ( $_i = 0; $_i < $_p_tires; $_i++ ) $_tire_items[] = [ 'url' => $_p_url, 'label' => $_p_label ];
	for ( $_i = 0; $_i < $_p_bikes; $_i++ ) $_bike_items[] = [ 'url' => $_p_url, 'label' => $_p_label ];
	for ( $_i = 0; $_i < $_p_carts; $_i++ ) $_cart_items[] = [ 'url' => $_p_url, 'label' => $_p_label ];
}
$_total_tires = count( $_tire_items );
$_total_bikes = count( $_bike_items );
$_total_carts = count( $_cart_items );

// Wildlife sightings (cleanup_event + approved glc_submission), newest first
$_wildlife_events = get_posts( [
	'post_type'      => [ 'cleanup_event', 'glc_submission' ],
	'post_status'    => 'publish',
	'posts_per_page' => -1,
	'meta_query'     => [ [ 'key' => 'wildlife_obs', 'value' => '', 'compare' => '!=' ] ],
] );

// Only observations that match a known illustrated species become a card/pin —
// free text that doesn't match glc_stats_wildlife_img() (typos, unrecognised
// species, test data) is excluded here rather than shown as a text-only entry.
$_wildlife_events = array_filter( $_wildlife_events, function( $post ) {
	return (bool) glc_stats_wildlife_img( glc_cleanup_field( $post, 'wildlife_obs' ) );
} );

usort( $_wildlife_events, function( $a, $b ) {
	return strcmp(
		glc_cleanup_field( $b, 'cleanup_date' ),
		glc_cleanup_field( $a, 'cleanup_date' )
	);
} );
$_wildlife_all = $_wildlife_events; // all matched sightings pre-dedup — used by the map to show every location

// Deduplicate: keep only the latest sighting of each animal type (newest-first sort above).
$_seen_wildlife = [];
$_wildlife_events = array_filter( $_wildlife_events, function( $post ) use ( &$_seen_wildlife ) {
	// Every remaining post has a matched image (filtered above), so the image
	// filename alone is a safe dedup key — "Snapping Turtle" and "snapping turtle"
	// both map to snapping-turtle.png and share one slot.
	$img = glc_stats_wildlife_img( glc_cleanup_field( $post, 'wildlife_obs' ) );
	if ( isset( $_seen_wildlife[ $img ] ) ) return false;
	$_seen_wildlife[ $img ] = true;
	return true;
} );
$_wildlife_events = array_values( $_wildlife_events );

// Format debris display (no decimal if whole number)
$_debris_display = ( $_total_debris == floor( $_total_debris ) )
	? number_format( (int) $_total_debris )
	: number_format( $_total_debris, 1 );

// Format hours display
$_hours_display = ( $_total_hours == floor( $_total_hours ) )
	? number_format( (int) $_total_hours )
	: number_format( $_total_hours, 1 );

// ── 2. SVG helper functions ──────────────────────────────────────────────────
// glc_stats_smooth_path(), glc_stats_area_chart(), and glc_stats_wildlife_img()
// are defined in functions.php and available globally.

// Asset paths
$_idir   = esc_url( get_template_directory_uri() ) . '/assets/images';
$_bag_url = $_idir . '/garbage-bag.png';

// Wildlife map markers: all sightings with valid GPS (not deduplicated so every location appears)
$_wl_markers = [];
if ( ! empty( $_wildlife_all ) ) {
	$__img_dir = get_template_directory() . '/assets/images/';
	$__img_uri = get_template_directory_uri() . '/assets/images/';
	foreach ( $_wildlife_all as $_wme ) {
		$__lat = (float) glc_cleanup_field( $_wme, 'gps_lat' );
		$__lon = (float) glc_cleanup_field( $_wme, 'gps_lon' );
		if ( ! $__lat || ! $__lon ) continue;
		$__obs  = glc_cleanup_field( $_wme, 'wildlife_obs' );
		$__img  = glc_stats_wildlife_img( $__obs );
		$__d    = glc_cleanup_field( $_wme, 'cleanup_date' );
		$__iurl = null;
		if ( $__img ) {
			// Prefer _s.png pin crop if it exists, else fall back to the card image
			$__stem  = pathinfo( $__img, PATHINFO_FILENAME );
			$__pin   = $__stem . '_s.png';
			$__iurl  = $__img_uri . ( file_exists( $__img_dir . $__pin ) ? $__pin : $__img );
		}
		$_wl_markers[] = [
			'lat'  => $__lat,
			'lon'  => $__lon,
			'obs'  => $__obs,
			'img'  => $__iurl,
			'site' => (string) glc_cleanup_field( $_wme, 'site_name' ),
			'date' => $__d ? date( 'M j, Y', strtotime( $__d ) ) : '',
			'url'  => get_permalink( $_wme->ID ),
		];
	}
}
if ( ! empty( $_wl_markers ) && defined( 'GLC_PLUGIN_URL' ) ) {
	wp_enqueue_style(  'leaflet', GLC_PLUGIN_URL . 'assets/leaflet.css', [], '1.9.4' );
	wp_enqueue_script( 'leaflet', GLC_PLUGIN_URL . 'assets/leaflet.js',  [], '1.9.4', true );
}

// River corridor lines under the wildlife pins — same static asset as the
// /cleanups/#cleanups-map overlay, no cumulative-impact pins here (this
// map is about sightings, not debris totals), just the lines for context.
$_corridor_lines_url = '';
if ( ! empty( $_wl_markers ) && defined( 'GLC_PLUGIN_DIR' ) && defined( 'GLC_PLUGIN_URL' ) ) {
	$__geojson_path = GLC_PLUGIN_DIR . 'assets/corridors.geojson';
	if ( file_exists( $__geojson_path ) ) {
		// mtime query string -- see shortcodes.php's glc_shortcode_map() for why.
		$_corridor_lines_url = GLC_PLUGIN_URL . 'assets/corridors.geojson?v=' . filemtime( $__geojson_path );
	}
}

get_header();
?>

<div class="glc-page dirCL">

	<?php if ( empty( $_dates ) ) : ?>
	<div class="dirCL-hero" style="text-align:center;padding:80px 40px;">
		<p>No cleanup data yet — check back after our next outing!</p>
	</div>
	<?php else : ?>

	<!-- ═══ HERO ════════════════════════════════════════════════════════════ -->
	<div class="dirCL-hero">
		<div class="dirCL-eyebrow">
			<span class="dot" aria-hidden="true"></span>
			Stopping plastic at the source
		</div>

		<div class="dirCL-herostage is-plain">

			<!-- LEFT: big number + flowing lede + CTAs -->
			<div class="dirCL-herostage-text">
				<div class="dirCL-hl">
					<span class="dirCL-num" aria-label="<?php echo esc_attr( $_debris_display . ' kilograms' ); ?>">
						<?php echo esc_html( $_debris_display ); ?><small>kg</small>
					</span>
				</div>
				<p class="dirCL-lede">
					<b class="lede-h">of plastics and debris hauled out from local waterways</b>
					<?php echo $_timeframe_label ? esc_html( $_timeframe_label ) . ' ' : ''; ?>by hand and by paddle. We intercept debris upstream, before it fragments into
					<b class="mp">microplastics</b> or leaches chemicals into our lakes. Help keep our waters clean:
				</p>
				<div class="dirCL-hero-cta">
					<a class="glc-btn-primary" href="<?php echo esc_url( home_url( '/join-crew/' ) ); ?>">Join the Crew</a>
					<a class="glc-btn-outline" href="<?php echo esc_url( home_url( '/submit-cleanup/' ) ); ?>">Submit a Cleanup</a>
				</div>
			</div>

			<!-- RIGHT: moose illustration -->
			<div class="dirCL-herostage-moose">
				<div class="moose-q">How much debris did we remove?</div>
				<div class="dirCL-moosewrap">
					<img class="dirCL-mooseimg"
					     src="<?php echo esc_url( get_template_directory_uri() . '/assets/images/moose-scene.png' ); ?>"
					     alt="<?php echo esc_attr( '≈ ' . $_moose_label . ', lifted out of the river' ); ?>"
					     draggable="false" />
					<div class="moose-label" aria-hidden="true"><?php echo esc_html( $_moose_label ); ?></div>
				</div>
				<div class="moose-cap">lifted out of the river</div>
			</div>

		</div><!-- .dirCL-herostage -->
	</div><!-- .dirCL-hero -->

	<div class="dirCL-wave" aria-hidden="true">
		<svg viewBox="0 0 1200 22" preserveAspectRatio="none" xmlns="http://www.w3.org/2000/svg">
			<path d="M0,11 C150,3 300,19 450,11 C600,3 750,19 900,11 C1050,3 1200,19 1200,11" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
			<path d="M0,16 C150,8 300,24 450,16 C600,8 750,24 900,16 C1050,8 1200,24 1200,16" fill="none" stroke="currentColor" stroke-width="1.4" stroke-linecap="round" opacity="0.45"/>
		</svg>
	</div>

	<!-- ═══ DEBRIS & RECYCLING ══════════════════════════════════════════════ -->
	<div class="dirCL-sec" id="debris">
		<h3 class="dirCL-sec-h">Debris &amp; Recycling <b>diverted</b> over time</h3>
		<p class="dirCL-sec-note dirCL-sec-note--measure">Everything we collect on shore or on the water helps reduce downstream pollution in our Great Lakes.  Debris (kg) is landfill, tires and metal. Recyclables are counted as items, not weight, and are diverted to the blue bin program.</p>

		<div class="dirCL-chartwrap">
			<!-- Legend -->
			<div class="dirCL-legend">
				<div class="dirCL-leg">
					<span class="sw" style="background:#1a4a6b"></span>
					Debris
					<span class="num" style="color:#1a4a6b"><?php echo esc_html( $_debris_display ); ?> kg</span>
				</div>
				<div class="dirCL-leg">
					<span class="sw" style="background:#f5a623"></span>
					Items recycled
					<span class="num" style="color:#e08e12"><?php echo esc_html( number_format( $_total_recyc ) ); ?></span>
				</div>
			</div>

			<!-- Chart -->
			<?php
			echo glc_stats_area_chart(
				[
					[
						'key'          => 'debris',
						'color'        => '#1a4a6b',
						'values'       => $_data_debris,
						'max'          => max( (float) end( $_data_debris ) * 1.1, 10 ),
						'fill_opacity' => '0.18',
					],
					[
						'key'          => 'recyc',
						'color'        => '#f5a623',
						'values'       => $_data_recyc,
						'max'          => max( (float) end( $_data_recyc ) * 1.1, 10 ),
						'fill_opacity' => '0.14',
					],
				],
				300,
				$_data_days,
				$_max_day,
				$_first_ts
			);
			?>

			<!-- Item pictograph -->
			<?php if ( $_total_recyc > 0 ) : ?>
			<div class="dirCL-itemblock">
				<div class="lead">
					<span class="big"><?php echo esc_html( number_format( $_total_recyc ) ); ?> items</span>
					<span class="txt">removed from our watershed.</span>
					<span class="scale-note"><?php echo esc_html( strtoupper( $_dot_label ) ); ?></span>
				</div>
				<div class="dirCL-dots" aria-label="<?php echo esc_attr( number_format( $_total_recyc ) . ' recycled items represented as dots' ); ?>" role="img">
					<?php for ( $i = 0; $i < $_dot_count; $i++ ) :
						$_wave = ( $i % 28 + (int) floor( $i / 28 ) ) * 16;
					?>
					<i style="animation-delay:<?php echo 500 + $_wave; ?>ms" aria-hidden="true"></i>
					<?php endfor; ?>
				</div>
			</div>
			<?php endif; ?>
		</div>
	</div>

	<div class="dirCL-wave" aria-hidden="true">
		<svg viewBox="0 0 1200 22" preserveAspectRatio="none" xmlns="http://www.w3.org/2000/svg">
			<path d="M0,11 C150,3 300,19 450,11 C600,3 750,19 900,11 C1050,3 1200,19 1200,11" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
			<path d="M0,16 C150,8 300,24 450,16 C600,8 750,24 900,16 C1050,8 1200,24 1200,16" fill="none" stroke="currentColor" stroke-width="1.4" stroke-linecap="round" opacity="0.45"/>
		</svg>
	</div>

	<!-- ═══ HOURS ON THE WATER ══════════════════════════════════════════════ -->
	<div class="dirCL-sec" id="hours">
		<h3 class="dirCL-sec-h"><?php echo esc_html( $_hours_display ); ?> hours <b>on the water</b></h3>
		<p class="dirCL-sec-note">
			We cleaned <?php echo esc_html( $_unique_sites ); ?> unique site<?php echo $_unique_sites !== 1 ? 's' : ''; ?>
			and <?php echo esc_html( $_total_corridors ); ?> river corridor<?php echo $_total_corridors !== 1 ? 's' : ''; ?> since March 28, 2026.
		</p>

		<div class="dirCL-chartwrap">
			<div class="dirCL-legend">
				<div class="dirCL-leg">
					<span class="sw" style="background:#2e8b57"></span>
					Volunteer hours
					<span class="num" style="color:#1a5e35"><?php echo esc_html( $_hours_display ); ?></span>
				</div>
			</div>
			<?php
			echo glc_stats_area_chart(
				[ [
					'key'          => 'hours',
					'color'        => '#2e8b57',
					'values'       => $_data_hours,
					'max'          => max( (float) end( $_data_hours ) * 1.1, 10 ),
					'fill_opacity' => '0.20',
				] ],
				230,
				$_data_days,
				$_max_day,
				$_first_ts
			);
			?>
		</div>
	</div>

	<div class="dirCL-wave" aria-hidden="true">
		<svg viewBox="0 0 1200 22" preserveAspectRatio="none" xmlns="http://www.w3.org/2000/svg">
			<path d="M0,11 C150,3 300,19 450,11 C600,3 750,19 900,11 C1050,3 1200,19 1200,11" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
			<path d="M0,16 C150,8 300,24 450,16 C600,8 750,24 900,16 C1050,8 1200,24 1200,16" fill="none" stroke="currentColor" stroke-width="1.4" stroke-linecap="round" opacity="0.45"/>
		</svg>
	</div>

	<!-- ═══ LARGE ITEMS ═════════════════════════════════════════════════════ -->
	<?php if ( $_total_tires > 0 || $_total_bikes > 0 || $_total_carts > 0 ) : ?>
	<div class="dirCL-sec">
		<h3 class="dirCL-sec-h">Tires, bikes &amp; shopping carts, <b>pulled from the water</b></h3>
		<p class="dirCL-sec-note">The heaviest, most stubborn debris in our waterways — tap any icon to see that cleanup.</p>

		<div class="dirCL-chartwrap">
			<?php if ( $_total_tires > 0 ) : ?>
			<div class="dirCL-picto-row">
				<div class="dirCL-picto-lbl">
					<span class="n"><?php echo esc_html( $_total_tires ); ?></span>
					<span class="t">tire<?php echo $_total_tires !== 1 ? 's' : ''; ?></span>
				</div>
				<div class="dirCL-picto-icons">
					<?php foreach ( $_tire_items as $_ti_i => $_ti ) : ?>
					<a href="<?php echo esc_url( $_ti['url'] ); ?>" class="dirCL-picto-item"
					   style="animation-delay:<?php echo esc_attr( 300 + $_ti_i * 30 ); ?>ms"
					   aria-label="<?php echo esc_attr( 'Tire removed' . ( $_ti['label'] ? ' — ' . $_ti['label'] : '' ) ); ?>"
					   title="<?php echo esc_attr( $_ti['label'] ); ?>">
						<img src="<?php echo esc_url( $_idir . '/tire-icon.png' ); ?>" alt="" draggable="false">
					</a>
					<?php endforeach; ?>
				</div>
			</div>
			<?php endif; ?>

			<?php if ( $_total_bikes > 0 ) : ?>
			<div class="dirCL-picto-row">
				<div class="dirCL-picto-lbl">
					<span class="n"><?php echo esc_html( $_total_bikes ); ?></span>
					<span class="t">bike<?php echo $_total_bikes !== 1 ? 's' : ''; ?></span>
				</div>
				<div class="dirCL-picto-icons">
					<?php foreach ( $_bike_items as $_bi_i => $_bi ) : ?>
					<a href="<?php echo esc_url( $_bi['url'] ); ?>" class="dirCL-picto-item"
					   style="animation-delay:<?php echo esc_attr( 300 + $_bi_i * 30 ); ?>ms"
					   aria-label="<?php echo esc_attr( 'Bike removed' . ( $_bi['label'] ? ' — ' . $_bi['label'] : '' ) ); ?>"
					   title="<?php echo esc_attr( $_bi['label'] ); ?>">
						<img src="<?php echo esc_url( $_idir . '/bike-icon.png' ); ?>" alt="" draggable="false">
					</a>
					<?php endforeach; ?>
				</div>
			</div>
			<?php endif; ?>

			<?php if ( $_total_carts > 0 ) : ?>
			<div class="dirCL-picto-row">
				<div class="dirCL-picto-lbl">
					<span class="n"><?php echo esc_html( $_total_carts ); ?></span>
					<span class="t">shopping cart<?php echo $_total_carts !== 1 ? 's' : ''; ?></span>
				</div>
				<div class="dirCL-picto-icons">
					<?php foreach ( $_cart_items as $_ci_i => $_ci ) : ?>
					<a href="<?php echo esc_url( $_ci['url'] ); ?>" class="dirCL-picto-item"
					   style="animation-delay:<?php echo esc_attr( 300 + $_ci_i * 30 ); ?>ms"
					   aria-label="<?php echo esc_attr( 'Shopping cart removed' . ( $_ci['label'] ? ' — ' . $_ci['label'] : '' ) ); ?>"
					   title="<?php echo esc_attr( $_ci['label'] ); ?>">
						<img src="<?php echo esc_url( $_idir . '/cart-icon.png' ); ?>" alt="" draggable="false">
					</a>
					<?php endforeach; ?>
				</div>
			</div>
			<?php endif; ?>
		</div>
	</div>

	<div class="dirCL-wave" aria-hidden="true">
		<svg viewBox="0 0 1200 22" preserveAspectRatio="none" xmlns="http://www.w3.org/2000/svg">
			<path d="M0,11 C150,3 300,19 450,11 C600,3 750,19 900,11 C1050,3 1200,19 1200,11" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
			<path d="M0,16 C150,8 300,24 450,16 C600,8 750,24 900,16 C1050,8 1200,24 1200,16" fill="none" stroke="currentColor" stroke-width="1.4" stroke-linecap="round" opacity="0.45"/>
		</svg>
	</div>
	<?php endif; ?>

	<!-- ═══ WILDLIFE ════════════════════════════════════════════════════════ -->
	<?php if ( ! empty( $_wildlife_events ) ) : ?>
	<div class="dirCL-sec" id="wildlife">
		<h3 class="dirCL-sec-h">Who we met <b>along the way</b></h3>
		<p class="dirCL-sec-note">We're not just removing debris — we're protecting habitat.</p>

		<div class="dirCL-wild">
			<?php
			$_wi = 0;
			foreach ( $_wildlife_events as $_we ) :
				$_wobs      = glc_cleanup_field( $_we, 'wildlife_obs' );
				$_wsite     = glc_cleanup_field( $_we, 'site_name' );
				$_wdate     = glc_cleanup_field( $_we, 'cleanup_date' );
				$_wdate_fmt = $_wdate ? date( 'M j, Y', strtotime( $_wdate ) ) : '';
				$_wimg     = glc_stats_wildlife_img( $_wobs );
				$_wdelay   = number_format( 0.15 + $_wi * 0.12, 2 ) . 's';
				$_wi++;
			?>
			<a class="dirCL-wchip" href="<?php echo esc_url( get_permalink( $_we->ID ) ); ?>"
			   style="--d:<?php echo esc_attr( $_wdelay ); ?>">
				<?php if ( $_wimg ) : ?>
				<div class="wfig">
					<img src="<?php echo esc_url( get_template_directory_uri() . '/assets/images/' . $_wimg ); ?>"
					     alt="<?php echo esc_attr( $_wobs ); ?>" draggable="false" />
				</div>
				<?php endif; ?>
				<div class="wbody">
					<?php if ( $_wdate_fmt ) : ?>
					<div class="wdate"><?php echo esc_html( $_wdate_fmt ); ?></div>
					<?php endif; ?>
					<div class="obs"><?php echo esc_html( $_wobs ); ?></div>
					<?php if ( $_wsite ) : ?>
					<div class="wsite"><?php echo esc_html( $_wsite ); ?></div>
					<?php endif; ?>
				</div>
			</a>
			<?php endforeach; ?>
		</div>

		<?php if ( ! empty( $_wl_markers ) ) : ?>
		<div class="dirCL-wl-mapwrap">
			<p class="dirCL-wl-maplbl">Where we spotted them</p>
			<div id="glc-wl-map" class="glc-wl-map"
			     role="application"
			     aria-label="Wildlife sighting locations map"></div>
		</div>
		<script>
		(function () {
		    function eh(s) { var d = document.createElement('div'); d.textContent = String(s); return d.innerHTML; }
		    document.addEventListener('DOMContentLoaded', function () {
		        if (typeof L === 'undefined') return;
		        var markers = <?php echo wp_json_encode( $_wl_markers ); ?>;
		        if (!markers.length) return;
		        var corridorLinesUrl = <?php echo wp_json_encode( $_corridor_lines_url ); ?>;
		        // Guelph is home base — the fit at the end sets zoom only, not centre.
		        var GLC_HOME = [43.545, -80.248];
		        var map = L.map('glc-wl-map', { zoomControl: true }).setView(GLC_HOME, 12);
		        L.tileLayer('https://{s}.basemaps.cartocdn.com/light_all/{z}/{x}/{y}{r}.png?key=cb1_29e5_1_17f74f1d3418f4c313616f46', {
		            attribution: '&copy; <a href="https://openstreetmap.org/copyright">OpenStreetMap</a> contributors &copy; <a href="https://carto.com/attributions">CARTO</a>',
		            subdomains: 'abcd',
		            maxZoom: 19
		        }).addTo(map);
		        // River corridor lines, for context under the wildlife pins — same
		        // static asset the archive map uses; fetched separately since it's
		        // too large to inline (see /cleanups/#cleanups-map for the full setup).
		        if (corridorLinesUrl) {
		            fetch(corridorLinesUrl)
		                .then(function (r) { return r.json(); })
		                .then(function (geo) {
		                    L.geoJSON(geo, {
		                        style: { color: '#5a9fc0', weight: 3, opacity: 0.55 }
		                    }).addTo(map).bringToBack();
		                    map.attributionControl.addAttribution(
		                        'River corridors: <a href="https://geohub.lio.gov.on.ca/datasets/mnrf::ontario-hydro-network-ohn-watercourse" target="_blank" rel="noopener">Ontario Hydro Network</a>, MNRF, Open Government Licence – Ontario'
		                    );
		                })
		                .catch(function () { /* corridor lines are decorative context — fail silently */ });
		        }
		        var pin = '<svg width="20" height="26" viewBox="0 0 20 26" xmlns="http://www.w3.org/2000/svg"><path d="M10 0C4.48 0 0 4.48 0 10c0 7.5 10 16 10 16s10-8.5 10-16C20 4.48 15.52 0 10 0z" fill="#1a4a6b"/><circle cx="10" cy="10" r="4" fill="#fff"/></svg>';
		        markers.forEach(function (m) {
		            var html, sz, anch, po;
		            if (m.img) {
		                html = '<div class="glc-wl-pin"><img src="' + m.img + '" alt=""></div>';
		                sz   = [48, 48]; anch = [24, 24]; po = [0, -26];
		            } else {
		                html = pin;
		                sz   = [20, 26]; anch = [10, 26]; po = [0, -28];
		            }
		            var icon = L.divIcon({ className: '', html: html, iconSize: sz, iconAnchor: anch, popupAnchor: po });
		            var pop  = '<strong>' + eh(m.obs) + '</strong>';
		            if (m.site) pop += '<br><span style="color:#555;font-size:.88em">' + eh(m.site) + '</span>';
		            if (m.date) pop += '<br><span style="color:#888;font-size:.82em">' + eh(m.date) + '</span>';
		            pop += '<br><a href="' + eh(m.url) + '" style="color:#1a4a6b;font-size:.85em">View report →</a>';
		            L.marker([m.lat, m.lon], { icon: icon }).addTo(map).bindPopup(pop);
		        });
		        var ll = markers.map(function (m) { return [m.lat, m.lon]; });
		        if (ll.length === 1) { map.setView(ll[0], 15); }
		        else {
		            // Zoom from the bounds, centre always on Guelph — a lone far-north
		            // sighting would otherwise drag the map off home base. Same rule and
		            // same reasoning as [glc_map]; see shortcodes.php for why this uses
		            // getBoundsZoom() rather than fitBounds(). Padding is the total, so
		            // the old { padding: [50, 50] } per side is [100, 100] here.
		            var fitZoom = map.getBoundsZoom(L.latLngBounds(ll), false, L.point(100, 100));
		            // One level tighter than guaranteed-to-fit; the taller .glc-wl-map
		            // box gives it room.
		            map.setView(GLC_HOME, fitZoom + 1);
		        }
		    });
		})();
		</script>
		<?php endif; ?>
	</div>
	<?php endif; ?>

	<?php endif; // no data fallback ?>

</div><!-- .glc-page.dirCL -->

<script>
(function () {
    var itemblock = document.querySelector('.dirCL-itemblock');
    if (!itemblock) return;

    // Immediately show dots in browsers without IntersectionObserver
    if (!window.IntersectionObserver) {
        itemblock.classList.add('glc-dots-visible');
        return;
    }

    var obs = new IntersectionObserver(function (entries) {
        entries.forEach(function (entry) {
            if (entry.isIntersecting) {
                entry.target.classList.add('glc-dots-visible');
                obs.unobserve(entry.target);
            }
        });
    }, { threshold: 0.1 });

    obs.observe(itemblock);
})();
</script>

<?php get_footer();
