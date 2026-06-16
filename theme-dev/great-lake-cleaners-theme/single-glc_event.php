<?php
/**
 * Great Lake Cleaners — single-glc_event.php
 *
 * Single view for community events (glc_event CPT). Mirrors the
 * single-cleanup_event.php layout: back link → header → featured image →
 * blog body → details → map → RSVP.
 *
 * Past events get a banner instead of the RSVP form, linking to the
 * resulting cleanup report when linked_cleanup_id is set.
 *
 * Meta keys (includes/events.php): event_date, event_start_time,
 * event_end_time, site_name, meeting_point, gps_lat, gps_lon,
 * what_to_bring, linked_cleanup_id, rsvp_count, rsvp_parties
 */

get_header();

if ( have_posts() ) :
    the_post();

    $id      = get_the_ID();
    $date    = glc_event_date( $id );
    $time    = glc_event_time_range( $id );
    $site    = get_post_meta( $id, 'site_name',     true );
    $meet    = get_post_meta( $id, 'meeting_point', true );
    $bring   = get_post_meta( $id, 'what_to_bring', true );
    $coming  = (int) get_post_meta( $id, 'rsvp_count', true );
    $is_past = glc_event_is_past( $id );

    $linked  = (int) get_post_meta( $id, 'linked_cleanup_id', true );
    $report  = ( $linked && 'publish' === get_post_status( $linked ) ) ? get_permalink( $linked ) : '';

    $when = $date ? date( 'l, F j, Y', strtotime( $date ) ) : '';
    if ( $when && $time ) $when .= ' · ' . $time;
?>

<div class="glc-fp-wrapper">
<div class="glc-single-sub-wrap">

    <a class="glc-single-sub-back" href="<?php echo esc_url( get_post_type_archive_link( 'glc_event' ) ); ?>">
        ← <?php esc_html_e( 'All Events', 'great-lake-cleaners' ); ?>
    </a>

    <article class="glc-single-sub glc-ev-single">

        <?php if ( $is_past ) : ?>
        <div class="glc-ev-past-banner">
            <p>
                <?php esc_html_e( 'This event has already happened.', 'great-lake-cleaners' ); ?>
                <?php if ( $report ) : ?>
                <a href="<?php echo esc_url( $report ); ?>"><?php esc_html_e( 'See the results →', 'great-lake-cleaners' ); ?></a>
                <?php else : ?>
                <a href="<?php echo esc_url( get_post_type_archive_link( 'glc_event' ) ); ?>"><?php esc_html_e( 'See all upcoming events →', 'great-lake-cleaners' ); ?></a>
                <?php endif; ?>
            </p>
        </div>
        <?php endif; ?>

        <header class="glc-single-sub-header">
            <div class="glc-single-sub-meta-row">
                <?php if ( $when ) : ?>
                <span class="glc-fp-card-date"><?php echo esc_html( $when ); ?></span>
                <?php endif; ?>
            </div>
            <h1 class="glc-single-sub-h1"><?php the_title(); ?></h1>
            <?php if ( $site ) : ?>
            <p class="glc-ev-single-site"><?php echo esc_html( $site ); ?></p>
            <?php endif; ?>
        </header>

        <!-- ── Blog body (free prose; photos inserted via block editor) ──────── -->
        <?php if ( get_the_content() ) : ?>
        <div class="glc-single-body">
            <?php the_content(); ?>
        </div>
        <?php endif; ?>

        <!-- ── Event details ─────────────────────────────────────────────────── -->
        <?php if ( $meet || $bring ) : ?>
        <div class="glc-ev-details">
            <?php if ( $meet ) : ?>
            <div class="glc-ev-detail">
                <h2><?php esc_html_e( 'Meeting Point', 'great-lake-cleaners' ); ?></h2>
                <p><?php echo nl2br( esc_html( $meet ) ); ?></p>
            </div>
            <?php endif; ?>
            <?php if ( $bring ) : ?>
            <div class="glc-ev-detail">
                <h2><?php esc_html_e( 'What to Bring', 'great-lake-cleaners' ); ?></h2>
                <p><?php echo nl2br( esc_html( $bring ) ); ?></p>
            </div>
            <?php endif; ?>
        </div>
        <?php endif; ?>

        <!-- ── Where to meet — map ───────────────────────────────────────────── -->
        <?php
        $gps_lat = get_post_meta( $id, 'gps_lat', true );
        $gps_lon = get_post_meta( $id, 'gps_lon', true );
        if ( $gps_lat && $gps_lon ) :
        ?>
        <div class="glc-single-event-map">
            <h2><?php esc_html_e( 'Where to Meet', 'great-lake-cleaners' ); ?></h2>
            <?php echo do_shortcode( '[glc_map height="320px" post_id="' . $id . '"]' ); ?>
        </div>
        <?php endif; ?>

        <!-- ── RSVP ──────────────────────────────────────────────────────────── -->
        <?php if ( ! $is_past ) : ?>
        <div class="glc-ev-rsvp-section">
            <h2><?php esc_html_e( "Let us know you're coming", 'great-lake-cleaners' ); ?></h2>
            <?php if ( $coming >= 1 ) : ?>
            <p class="glc-ev-headcount">
                <?php printf(
                    esc_html( 1 === $coming
                        ? __( '%s person coming so far', 'great-lake-cleaners' )
                        : __( '%s people coming so far', 'great-lake-cleaners' ) ),
                    (int) $coming
                ); ?>
            </p>
            <?php endif; ?>
            <?php echo do_shortcode( '[glc_event_rsvp post_id="' . $id . '"]' ); ?>
        </div>
        <?php endif; ?>

    </article>

</div>
</div>

<?php endif; ?>
<?php get_footer(); ?>
