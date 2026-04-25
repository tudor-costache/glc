<?php
/**
 * Great Lake Cleaners — archive-cleanup_event.php
 *
 * Fetches ALL cleanup events and ALL approved community submissions,
 * merges and sorts globally by date descending, then paginates manually.
 */

get_header();

// ── Fetch all cleanup_event posts ─────────────────────────────────────────────
$event_posts = get_posts( [
    'post_type'      => 'cleanup_event',
    'post_status'    => 'publish',
    'posts_per_page' => -1,
] );

$all_cleanups = [];
foreach ( $event_posts as $e ) {
    $all_cleanups[] = [
        'type'     => 'event',
        'date'     => get_post_meta( $e->ID, 'cleanup_date',   true ),
        'site'     => get_post_meta( $e->ID, 'site_name',      true ) ?: $e->post_title,
        'bags'     => get_post_meta( $e->ID, 'bags',           true ),
        'weight'   => get_post_meta( $e->ID, 'weight_kg',      true ),
        'recycled' => get_post_meta( $e->ID, 'items_recycled', true ),
        'hours'    => get_post_meta( $e->ID, 'hours',          true ),
        'notable'  => get_post_meta( $e->ID, 'notable_finds',  true ),
        'insta'    => get_post_meta( $e->ID, 'instagram_url',  true ),
        'url'      => get_permalink( $e->ID ),
        'name'     => '',
    ];
}

// ── Fetch all approved community submissions ──────────────────────────────────
$sub_posts = get_posts( [
    'post_type'      => 'glc_submission',
    'post_status'    => 'publish',
    'posts_per_page' => -1,
] );

foreach ( $sub_posts as $s ) {
    $all_cleanups[] = [
        'type'     => 'community',
        'date'     => get_post_meta( $s->ID, 'glc_cleanup_date',   true ),
        'site'     => get_post_meta( $s->ID, 'glc_site_name',      true )
                      ?: get_post_meta( $s->ID, 'glc_waterway',    true )
                      ?: $s->post_title,
        'bags'     => get_post_meta( $s->ID, 'glc_bags',           true ),
        'weight'   => get_post_meta( $s->ID, 'glc_weight_kg',      true ),
        'recycled' => get_post_meta( $s->ID, 'items_recycled',     true ),
        'hours'    => get_post_meta( $s->ID, 'glc_hours',          true ),
        'notable'  => get_post_meta( $s->ID, 'glc_notable_finds',  true ),
        'insta'    => get_post_meta( $s->ID, 'glc_instagram_url',  true ),
        'url'      => get_permalink( $s->ID ),
        'name'     => get_post_meta( $s->ID, 'glc_submitter_name', true ),
    ];
}

// ── Sort globally by date descending ─────────────────────────────────────────
// Normalise any legacy display-format dates (e.g. "Mar 30") to YYYY-MM-DD so
// strcmp works correctly across both event types.
foreach ( $all_cleanups as &$c ) {
    $d = $c['date'];
    if ( $d && ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', $d ) ) {
        // Try to parse display formats like "Mar 30", "March 30, 2026", etc.
        $ts = strtotime( $d );
        $c['date'] = $ts ? date( 'Y-m-d', $ts ) : $d;
    }
}
unset( $c );

usort( $all_cleanups, function( $a, $b ) {
    return strcmp(
        $b['date'] ?: '0000-00-00',
        $a['date'] ?: '0000-00-00'
    );
} );

// ── Manual pagination ─────────────────────────────────────────────────────────
$per_page    = 12;
$total       = count( $all_cleanups );
$total_pages = max( 1, (int) ceil( $total / $per_page ) );
$paged       = max( 1, (int) ( get_query_var( 'paged' ) ?: 1 ) );
$offset      = ( $paged - 1 ) * $per_page;
$page_items  = array_slice( $all_cleanups, $offset, $per_page );
?>

<div class="glc-fp-wrapper">
<div class="glc-archive-wrap">

    <header class="glc-archive-header">
        <span class="glc-fp-label"><?php esc_html_e( 'Our Work', 'great-lake-cleaners' ); ?></span>
        <h1 class="glc-archive-h1"><?php esc_html_e( 'All Cleanups', 'great-lake-cleaners' ); ?></h1>
        <p class="glc-archive-intro">
            <?php esc_html_e( 'Every outing logged — on foot, by paddle, or both. Sorted most recent first.', 'great-lake-cleaners' ); ?>
        </p>
    </header>

    <?php if ( ! empty( $page_items ) ) : ?>
    <div class="glc-archive-grid">
        <?php foreach ( $page_items as $c ) : ?>
        <div class="glc-fp-cleanup-card<?php echo $c['type'] === 'community' ? ' glc-fp-cleanup-card--community' : ''; ?>">

            <div class="glc-archive-card-top">
                <?php if ( $c['date'] ) : ?>
                <div class="glc-fp-card-date">
                    <?php echo esc_html( date( 'F j, Y', strtotime( $c['date'] ) ) ); ?>
                </div>
                <?php endif; ?>
                <?php if ( $c['type'] === 'community' ) : ?>
                <span class="glc-community-badge"><?php esc_html_e( 'Community', 'great-lake-cleaners' ); ?></span>
                <?php endif; ?>
            </div>

            <?php if ( $c['url'] ) : ?>
            <a class="glc-fp-card-title" href="<?php echo esc_url( $c['url'] ); ?>">
                <?php echo esc_html( $c['site'] ); ?>
            </a>
            <?php else : ?>
            <span class="glc-fp-card-title glc-fp-card-title--plain">
                <?php echo esc_html( $c['site'] ); ?>
            </span>
            <?php endif; ?>

            <?php if ( $c['type'] === 'community' && $c['name'] ) : ?>
            <p class="glc-archive-card-submitter"><?php echo esc_html( $c['name'] ); ?></p>
            <?php endif; ?>

            <div class="glc-fp-card-stats">
                <?php
                $idir = esc_url( get_template_directory_uri() ) . '/assets/images';
                $ic   = function( $icon, $val, $suffix = '' ) use ( $idir ) {
                    return '<span class="glc-cs"><img src="' . $idir . '/' . $icon . '" alt="" width="18" height="18" aria-hidden="true">' . esc_html( $val ) . ( $suffix ? ' ' . $suffix : '' ) . '</span>';
                };
                if ( $c['bags'] )     echo $ic( 'icon-bag.svg',     $c['bags'],                           1 === (int)$c['bags'] ? 'bag' : 'bags' );
                if ( $c['weight'] )   echo $ic( 'icon-scale.svg',   $c['weight'],                         'kg' );
                if ( $c['recycled'] ) echo $ic( 'icon-recycle.svg', $c['recycled'],                       '' );
                if ( $c['hours'] ) {
                    if ( $c['hours'] < 1 ) {
                        echo $ic( 'icon-timer.svg', round( $c['hours'] * 60 ), 'min' );
                    } else {
                        echo $ic( 'icon-timer.svg', number_format( $c['hours'], 1 ), 'h' );
                    }
                }
                ?>
            </div>

            <?php if ( $c['notable'] ) : ?>
            <p class="glc-archive-card-notable"><?php echo esc_html( $c['notable'] ); ?></p>
            <?php endif; ?>

            <?php if ( $c['insta'] ) : ?>
            <a class="glc-archive-card-insta"
               href="<?php echo esc_url( $c['insta'] ); ?>"
               target="_blank" rel="noopener noreferrer">
                Field log →<span class="screen-reader-text"> (opens in new tab)</span>
            </a>
            <?php endif; ?>

        </div>
        <?php endforeach; ?>
    </div>

    <?php if ( $total_pages > 1 ) : ?>
    <nav class="glc-pagination" aria-label="<?php esc_attr_e( 'Cleanup pages', 'great-lake-cleaners' ); ?>">
        <div class="nav-links">
            <?php if ( $paged > 1 ) : ?>
            <a class="page-numbers prev" href="<?php echo esc_url( get_pagenum_link( $paged - 1 ) ); ?>">
                &larr; <?php esc_html_e( 'Newer cleanups', 'great-lake-cleaners' ); ?>
            </a>
            <?php endif; ?>
            <?php for ( $p = 1; $p <= $total_pages; $p++ ) : ?>
                <?php if ( $p === $paged ) : ?>
                <span class="page-numbers current"><?php echo esc_html( $p ); ?></span>
                <?php elseif ( abs( $p - $paged ) <= 2 || $p === 1 || $p === $total_pages ) : ?>
                <a class="page-numbers" href="<?php echo esc_url( get_pagenum_link( $p ) ); ?>"><?php echo esc_html( $p ); ?></a>
                <?php elseif ( abs( $p - $paged ) === 3 ) : ?>
                <span class="page-numbers dots">&hellip;</span>
                <?php endif; ?>
            <?php endfor; ?>
            <?php if ( $paged < $total_pages ) : ?>
            <a class="page-numbers next" href="<?php echo esc_url( get_pagenum_link( $paged + 1 ) ); ?>">
                <?php esc_html_e( 'Older cleanups', 'great-lake-cleaners' ); ?> &rarr;
            </a>
            <?php endif; ?>
        </div>
    </nav>
    <?php endif; ?>

    <?php else : ?>
    <p class="glc-archive-empty">
        <?php esc_html_e( 'No cleanups logged yet — check back soon.', 'great-lake-cleaners' ); ?>
    </p>
    <?php endif; ?>

    <!-- ── Where We've Made an Impact ───────────────────────────────────────── -->
    <div class="glc-impact-section" aria-label="<?php esc_attr_e( 'Cleanup locations map', 'great-lake-cleaners' ); ?>">
        <span class="glc-fp-label"><?php esc_html_e( 'Where We\'ve Made an Impact', 'great-lake-cleaners' ); ?></span>
        <h2 class="glc-impact-heading"><?php esc_html_e( 'Every site tells a story.', 'great-lake-cleaners' ); ?></h2>

        <?php if ( ! empty( $all_cleanups ) ) : ?>
        <div class="glc-archive-map">
            <?php echo do_shortcode( '[glc_map height="400px" limit="7" cluster_radius="10"]' ); ?>
        </div>
        <?php endif; ?>

    </div>

</div>
</div>

<?php get_footer(); ?>
