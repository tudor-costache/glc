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

// Corridors count: distinct known corridor names found in site names
$_known_corridors = [ 'Speed River', 'Eramosa River', 'Hanlon Creek', 'Grand River', 'Guelph Lake' ];
$_found_corridors = 0;
foreach ( $_known_corridors as $_corridor ) {
	foreach ( $_site_names as $_sn ) {
		if ( stripos( $_sn, $_corridor ) !== false ) {
			$_found_corridors++;
			break;
		}
	}
}
$_total_corridors = max( 1, $_found_corridors );

// Moose metaphor (1 moose ≈ 350 kg)
$_MOOSE_KG    = 350;
$_MAX_MOOSE   = 12;
$_moose_count = max( 1, (int) round( $_total_debris / $_MOOSE_KG ) );
$_moose_shown = min( $_moose_count, $_MAX_MOOSE );
$_moose_label = $_moose_count === 1 ? 'one moose' : number_format( $_moose_count ) . ' moose';

// Recent haul: last 3 calendar months with data (1 bag ≈ 20 kg)
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
		'bags' => max( 1, (int) round( $_m['kg'] / 20 ) ),
	];
}

// Item dot pictograph (self-scaling grid of 28 columns)
$_MAX_DOTS = 28 * 24;
$_steps    = [ 2, 5, 10, 20, 25, 50, 100, 200, 500 ];
$_per_dot  = 2;
foreach ( $_steps as $_s ) {
	if ( (int) ceil( $_total_recyc / $_s ) <= $_MAX_DOTS ) {
		$_per_dot = $_s;
		break;
	}
}
$_dot_count = (int) ceil( $_total_recyc / $_per_dot );
$_dot_label = 'Every dot = ' . $_per_dot . ( $_per_dot === 1 ? ' item' : ' items' );

// Wildlife sightings (cleanup_event only, newest first)
$_wildlife_events = get_posts( [
	'post_type'      => 'cleanup_event',
	'post_status'    => 'publish',
	'posts_per_page' => -1,
	'meta_query'     => [ [ 'key' => 'wildlife_obs', 'value' => '', 'compare' => '!=' ] ],
] );
usort( $_wildlife_events, function( $a, $b ) {
	return strcmp(
		get_post_meta( $b->ID, 'cleanup_date', true ),
		get_post_meta( $a->ID, 'cleanup_date', true )
	);
} );

// Format debris display (no decimal if whole number)
$_debris_display = ( $_total_debris == floor( $_total_debris ) )
	? number_format( (int) $_total_debris )
	: number_format( $_total_debris, 1 );

// Format hours display
$_hours_display = ( $_total_hours == floor( $_total_hours ) )
	? number_format( (int) $_total_hours )
	: number_format( $_total_hours, 1 );

// ── 2. SVG helper functions ──────────────────────────────────────────────────

// Catmull-Rom → cubic bezier smooth path (port of stats-data.jsx smoothPath)
function glc_stats_smooth_path( $pts ) {
	if ( count( $pts ) < 2 ) return '';
	$t = 0.16;
	$n = count( $pts );
	$d = sprintf( 'M%.2f %.2f', $pts[0][0], $pts[0][1] );
	for ( $i = 0; $i < $n - 1; $i++ ) {
		$p0  = ( $i > 0 ) ? $pts[ $i - 1 ] : $pts[ $i ];
		$p1  = $pts[ $i ];
		$p2  = $pts[ $i + 1 ];
		$p3  = ( $i + 2 < $n ) ? $pts[ $i + 2 ] : $p2;
		$c1x = $p1[0] + ( $p2[0] - $p0[0] ) * $t;
		$c1y = $p1[1] + ( $p2[1] - $p0[1] ) * $t;
		$c2x = $p2[0] - ( $p3[0] - $p1[0] ) * $t;
		$c2y = $p2[1] - ( $p3[1] - $p1[1] ) * $t;
		$d  .= sprintf( ' C%.2f %.2f %.2f %.2f %.2f %.2f', $c1x, $c1y, $c2x, $c2y, $p2[0], $p2[1] );
	}
	return $d;
}

/**
 * Render a multi-series cumulative area chart as inline SVG.
 *
 * @param array  $series    [ [ 'color', 'values', 'max', 'fill_opacity', 'key', 'gid_suffix' ], ... ]
 * @param int    $height    SVG logical height
 * @param array  $days      Day offsets (same index as values)
 * @param int    $max_day   Day offset of the last point
 * @param int    $first_ts  Unix timestamp of day 0 (for x-axis labels)
 * @return string SVG markup
 */
function glc_stats_area_chart( $series, $height, $days, $max_day, $first_ts = 0 ) {
	if ( empty( $days ) || $max_day <= 0 ) return '';
	$W    = 1000;
	$H    = $height;
	$padL = 14; $padR = 14; $padT = 26; $padB = 34;
	$plotH = $H - $padT - $padB;
	$baseY = $H - $padB;

	$X = function( $d ) use ( $padL, $padR, $W, $max_day ) {
		return $padL + ( $d / $max_day ) * ( $W - $padL - $padR );
	};

	// Build each series
	$built = [];
	foreach ( $series as $idx => $s ) {
		$pts = [];
		foreach ( $s['values'] as $i => $v ) {
			$d   = isset( $days[ $i ] ) ? $days[ $i ] : 0;
			$pts[] = [ $X( $d ), $baseY - ( $v / $s['max'] ) * $plotH ];
		}
		$line = glc_stats_smooth_path( $pts );
		$last = end( $pts );
		$area = $line . sprintf( ' L%.2f %.2f L%.2f %.2f Z', $last[0], $baseY, $pts[0][0], $baseY );
		$gid  = 'glcg-' . $idx . '-' . preg_replace( '/[^a-z0-9]/', '', $s['key'] );
		$built[] = array_merge( $s, [
			'pts'  => $pts,
			'line' => $line,
			'area' => $area,
			'last' => $last,
			'gid'  => $gid,
		] );
	}

	// Horizontal grid lines at 25 / 50 / 75 / 100 %
	$grid_y = [];
	foreach ( [ 0.25, 0.5, 0.75, 1.0 ] as $f ) {
		$grid_y[] = round( $baseY - $f * $plotH, 2 );
	}

	// X-axis tick positions: first date, each month start, "Now"
	$ticks = [];
	if ( $first_ts ) {
		$ticks[] = [
			'x'      => $X( 0 ),
			'label'  => date( 'M j', $first_ts ),
			'anchor' => 'start',
		];
		// month boundaries
		$fY = (int) date( 'Y', $first_ts );
		$fM = (int) date( 'n', $first_ts );
		$last_ts = $first_ts + $max_day * 86400;
		for ( $mo = 1; $mo <= 18; $mo++ ) {
			$cy = $fY + (int) floor( ( $fM - 1 + $mo ) / 12 );
			$cm = ( ( $fM - 1 + $mo ) % 12 ) + 1;
			$ts = mktime( 0, 0, 0, $cm, 1, $cy );
			if ( $ts >= $last_ts ) break;
			$day_off = (int) round( ( $ts - $first_ts ) / 86400 );
			if ( $day_off <= 2 || $day_off >= $max_day - 2 ) continue;
			$ticks[] = [
				'x'      => $X( $day_off ),
				'label'  => date( 'M', $ts ),
				'anchor' => 'middle',
			];
		}
		$ticks[] = [
			'x'      => $X( $max_day ),
			'label'  => 'Now',
			'anchor' => 'end',
		];
	}

	ob_start();
	?>
	<svg viewBox="0 0 <?php echo $W; ?> <?php echo $H; ?>" width="100%" preserveAspectRatio="none"
	     style="display:block;overflow:visible" role="img" aria-hidden="true">
		<defs>
			<?php foreach ( $built as $b ) : ?>
			<linearGradient id="<?php echo esc_attr( $b['gid'] ); ?>" x1="0" y1="0" x2="0" y2="1">
				<stop offset="0%"   stop-color="<?php echo esc_attr( $b['color'] ); ?>" stop-opacity="<?php echo esc_attr( $b['fill_opacity'] ); ?>"/>
				<stop offset="100%" stop-color="<?php echo esc_attr( $b['color'] ); ?>" stop-opacity="0.02"/>
			</linearGradient>
			<?php endforeach; ?>
		</defs>

		<?php foreach ( $grid_y as $gy ) : ?>
		<line x1="<?php echo $padL; ?>" x2="<?php echo $W - $padR; ?>"
		      y1="<?php echo $gy; ?>" y2="<?php echo $gy; ?>"
		      stroke="#1a4a6b" stroke-opacity="0.07" stroke-width="1"/>
		<?php endforeach; ?>

		<?php foreach ( $built as $b ) : ?>
		<path d="<?php echo esc_attr( $b['area'] ); ?>"
		      fill="url(#<?php echo esc_attr( $b['gid'] ); ?>)"
		      class="glc-area-in"/>
		<path d="<?php echo esc_attr( $b['line'] ); ?>"
		      fill="none" stroke="<?php echo esc_attr( $b['color'] ); ?>"
		      stroke-width="2.6" stroke-linecap="round" stroke-linejoin="round"
		      pathLength="2600"
		      class="glc-line-draw"/>
		<circle cx="<?php echo round( $b['last'][0], 2 ); ?>"
		        cy="<?php echo round( $b['last'][1], 2 ); ?>"
		        r="5" fill="#fff"
		        stroke="<?php echo esc_attr( $b['color'] ); ?>" stroke-width="3"
		        class="glc-dot-in"/>
		<?php endforeach; ?>

		<?php foreach ( $ticks as $tk ) : ?>
		<text x="<?php echo round( $tk['x'], 2 ); ?>" y="<?php echo $H - 10; ?>"
		      text-anchor="<?php echo esc_attr( $tk['anchor'] ); ?>"
		      font-family="'Lato',sans-serif" font-size="15" fill="#7d8893" font-weight="700">
			<?php echo esc_html( $tk['label'] ); ?>
		</text>
		<?php endforeach; ?>
	</svg>
	<?php
	return ob_get_clean();
}

// Simple emoji mapper for wildlife observations
function glc_stats_wildlife_emoji( $obs ) {
	$obs = strtolower( $obs );
	if ( strpos( $obs, 'turtle' )  !== false ) return '🐢';
	if ( strpos( $obs, 'goose' )   !== false
	  || strpos( $obs, 'geese' )   !== false ) return '🪿';
	if ( strpos( $obs, 'duck' )    !== false ) return '🦆';
	if ( strpos( $obs, 'heron' )   !== false ) return '🪶';
	if ( strpos( $obs, 'deer' )    !== false ) return '🦌';
	if ( strpos( $obs, 'moose' )   !== false ) return '🫎';
	if ( strpos( $obs, 'beaver' )  !== false ) return '🦫';
	if ( strpos( $obs, 'otter' )   !== false ) return '🦦';
	if ( strpos( $obs, 'frog' )    !== false
	  || strpos( $obs, 'toad' )    !== false ) return '🐸';
	if ( strpos( $obs, 'snake' )   !== false ) return '🐍';
	if ( strpos( $obs, 'hawk' )    !== false
	  || strpos( $obs, 'eagle' )   !== false ) return '🦅';
	if ( strpos( $obs, 'crane' )   !== false ) return '🦩';
	if ( strpos( $obs, 'fish' )    !== false ) return '🐟';
	if ( strpos( $obs, 'fox' )     !== false ) return '🦊';
	return '🌿';
}

// Asset paths
$_idir   = esc_url( get_template_directory_uri() ) . '/assets/images';
$_bag_url = $_idir . '/garbage-bag.png';

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

		<div class="dirCL-hero-grid">

			<!-- LEFT: big number + copy + CTAs -->
			<div class="dirCL-hero-left">
				<div class="dirCL-hl">
					<span class="dirCL-num" aria-label="<?php echo esc_attr( $_debris_display . ' kilograms' ); ?>">
						<?php echo esc_html( $_debris_display ); ?><small>kg</small>
					</span>
					<span class="cap2">of plastics and debris hauled out</span>
				</div>
				<p class="dirCL-sub">
					from local waterways by hand and by paddle.
					We intercept debris upstream, before it fragments into
					<b>microplastics</b> and enters our lakes.
					Help keep our waters clean:
				</p>
				<div class="dirCL-hero-cta">
					<a class="glc-btn-primary" href="<?php echo esc_url( home_url( '/join-crew/' ) ); ?>">Join the Crew</a>
					<a class="glc-btn-outline" href="<?php echo esc_url( home_url( '/submit-cleanup/' ) ); ?>">Submit a Cleanup</a>
				</div>
			</div>

			<!-- RIGHT: moose factoid + recent haul -->
			<div class="dirCL-hero-right">

				<!-- Moose weight factoid -->
				<div class="dirCL-weighcard">
					<div class="cap">What <?php echo esc_html( $_debris_display ); ?> kg weighs</div>
					<div class="dirCL-moose" aria-label="<?php echo esc_attr( 'Approximately ' . $_moose_label ); ?>">
						<?php for ( $i = 0; $i < $_moose_shown; $i++ ) : ?>
						<span class="m" aria-hidden="true">🫎</span>
						<?php endfor; ?>
						<?php if ( $_moose_count > $_MAX_MOOSE ) : ?>
						<span class="more">+<?php echo $_moose_count - $_MAX_MOOSE; ?></span>
						<?php endif; ?>
					</div>
					<div class="eqline">≈ <b><?php echo esc_html( $_moose_label ); ?></b>, lifted out of the river</div>
				</div>

				<!-- Recent haul bag pictograph -->
				<?php if ( ! empty( $_haul_rows ) ) : ?>
				<div class="dirCL-haul">
					<div class="dirCL-haul-head">
						<h4>Our recent hauls</h4>
						<span class="dirCL-haul-scale">
							<img src="<?php echo $_bag_url; ?>" alt="" width="13" aria-hidden="true">
							Each bag = <b>~20 kg</b> of debris
						</span>
					</div>
					<div class="dirCL-haul-rows">
						<?php foreach ( $_haul_rows as $_row ) : ?>
						<div class="dirCL-haul-row">
							<div class="ml"><?php echo esc_html( $_row['name'] ); ?></div>
							<div class="dirCL-haul-bags" aria-label="<?php echo esc_attr( $_row['bags'] . ' ' . ( $_row['bags'] === 1 ? 'bag' : 'bags' ) ); ?>">
								<?php for ( $i = 0; $i < $_row['bags']; $i++ ) : ?>
								<img src="<?php echo $_bag_url; ?>" alt=""
								     style="animation-delay:<?php echo 300 + $i * 55; ?>ms"
								     aria-hidden="true">
								<?php endfor; ?>
							</div>
						</div>
						<?php endforeach; ?>
					</div>
				</div>
				<?php endif; ?>

			</div><!-- .dirCL-hero-right -->
		</div><!-- .dirCL-hero-grid -->
	</div><!-- .dirCL-hero -->

	<!-- ═══ DEBRIS & RECYCLING ══════════════════════════════════════════════ -->
	<div class="dirCL-sec dirCL-sec--alt" id="debris">
		<h3 class="dirCL-sec-h">Debris &amp; Recycling <b>diverted</b> over time</h3>
		<p class="dirCL-sec-note">Everything we collect by hand and by paddle helps reduce the microplastic load in our Great Lakes.</p>

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

	<!-- ═══ HOURS ON THE WATER ══════════════════════════════════════════════ -->
	<div class="dirCL-sec" id="hours">
		<h3 class="dirCL-sec-h"><?php echo esc_html( $_hours_display ); ?> hours <b>on the water</b></h3>
		<p class="dirCL-sec-note">
			We cleaned <?php echo esc_html( $_unique_sites ); ?> unique site<?php echo $_unique_sites !== 1 ? 's' : ''; ?>
			and <?php echo esc_html( $_total_corridors ); ?> river corridor<?php echo $_total_corridors !== 1 ? 's' : ''; ?>.
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

	<!-- ═══ WILDLIFE ════════════════════════════════════════════════════════ -->
	<?php if ( ! empty( $_wildlife_events ) ) : ?>
	<div class="dirCL-sec dirCL-sec--alt" id="wildlife">
		<h3 class="dirCL-sec-h">Who we met <b>along the way</b></h3>
		<p class="dirCL-sec-note">We're not just removing debris — we're protecting habitat.</p>

		<div class="dirCL-wild">
			<?php foreach ( $_wildlife_events as $_we ) :
				$_wobs  = get_post_meta( $_we->ID, 'wildlife_obs',  true );
				$_wsite = get_post_meta( $_we->ID, 'site_name',     true );
				$_wdate = get_post_meta( $_we->ID, 'cleanup_date',  true );
				$_wdate_fmt = $_wdate ? date( 'M j, Y', strtotime( $_wdate ) ) : '';
				$_wemoji = glc_stats_wildlife_emoji( $_wobs );
			?>
			<a class="dirCL-wchip" href="<?php echo esc_url( get_permalink( $_we->ID ) ); ?>">
				<div class="e" aria-hidden="true"><?php echo $_wemoji; ?></div>
				<div>
					<div class="obs"><?php echo esc_html( $_wobs ); ?></div>
					<div class="meta"><?php echo esc_html( trim( $_wsite . ( $_wsite && $_wdate_fmt ? ' · ' : '' ) . $_wdate_fmt ) ); ?></div>
				</div>
			</a>
			<?php endforeach; ?>
		</div>
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
