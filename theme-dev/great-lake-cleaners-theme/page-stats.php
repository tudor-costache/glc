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

// Wildlife sightings (cleanup_event + approved glc_submission), newest first
$_wildlife_events = get_posts( [
	'post_type'      => [ 'cleanup_event', 'glc_submission' ],
	'post_status'    => 'publish',
	'posts_per_page' => -1,
	'meta_query'     => [ [ 'key' => 'wildlife_obs', 'value' => '', 'compare' => '!=' ] ],
] );
usort( $_wildlife_events, function( $a, $b ) {
	return strcmp(
		glc_cleanup_field( $b, 'cleanup_date' ),
		glc_cleanup_field( $a, 'cleanup_date' )
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
// glc_stats_smooth_path() and glc_stats_area_chart() are defined in functions.php
// and available globally. Only glc_stats_wildlife_img() lives here.

// Image mapper for wildlife observations — returns filename relative to assets/images, or null.
function glc_stats_wildlife_img( $obs ) {
	$obs = strtolower( $obs );
	if ( strpos( $obs, 'mink' ) !== false ) return 'mink.png';
	if ( strpos( $obs, 'swallow' ) !== false ) return 'swallow.png';
	if ( strpos( $obs, 'snapping' ) !== false ) return 'snapping-turtle.png';
	if ( strpos( $obs, 'painted' )  !== false ) return 'painted-turtle.png';
	if ( strpos( $obs, 'egg' )    !== false ) return 'nest.png';
	if ( strpos( $obs, 'duck' )    !== false ) return 'duck.png';
	if ( strpos( $obs, 'goose' )    !== false
	  || strpos( $obs, 'geese' )    !== false ) return 'canada-goose.png';
	if ( strpos( $obs, 'snake' )    !== false ) return 'snake.png';
	if ( strpos( $obs, 'sandpiper' )    !== false ) return 'sandpiper.png';
	return null;
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
					by hand and by paddle. We intercept debris upstream, before it fragments into
					<b class="mp">microplastics</b> and enters our lakes. Help keep our waters clean:
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

	<div class="dirCL-wave" aria-hidden="true">
		<svg viewBox="0 0 1200 22" preserveAspectRatio="none" xmlns="http://www.w3.org/2000/svg">
			<path d="M0,11 C150,3 300,19 450,11 C600,3 750,19 900,11 C1050,3 1200,19 1200,11" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
			<path d="M0,16 C150,8 300,24 450,16 C600,8 750,24 900,16 C1050,8 1200,24 1200,16" fill="none" stroke="currentColor" stroke-width="1.4" stroke-linecap="round" opacity="0.45"/>
		</svg>
	</div>

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
