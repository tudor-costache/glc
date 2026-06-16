<?php
/**
 * Great Lake Cleaners — archive-glc_event.php
 *
 * /events/ — upcoming community cleanup events (soonest first), followed by
 * a compact list of past events. Past events with a linked_cleanup_id show a
 * "See the results" link to the resulting cleanup report.
 *
 * Upcoming/past split and sorting come from the plugin helpers
 * glc_get_upcoming_events() / glc_get_past_events() (includes/events.php).
 */

get_header();

$upcoming = function_exists( 'glc_get_upcoming_events' ) ? glc_get_upcoming_events() : [];
$past     = function_exists( 'glc_get_past_events' )     ? glc_get_past_events()     : [];

// ── Manual pagination — past list only; upcoming always shows in full on p.1 ──
$per_page    = 24;
$total_pages = max( 1, (int) ceil( count( $past ) / $per_page ) );
$paged       = max( 1, (int) ( get_query_var( 'paged' ) ?: 1 ) );
$past_items  = array_slice( $past, ( $paged - 1 ) * $per_page, $per_page );
?>

<div class="glc-fp-wrapper">
<div class="glc-archive-wrap">

    <header class="glc-archive-header">
        <span class="glc-fp-label"><?php esc_html_e( 'Get Involved', 'great-lake-cleaners' ); ?></span>
        <h1 class="glc-archive-h1"><?php esc_html_e( 'Upcoming Events', 'great-lake-cleaners' ); ?></h1>
        <p class="glc-archive-intro">
            <?php esc_html_e( 'Join us at the next community cleanup — gloves, grabbers, and bags provided. Just bring yourself.', 'great-lake-cleaners' ); ?>
        </p>
    </header>

    <?php if ( $paged === 1 ) : ?>

        <?php if ( ! empty( $upcoming ) ) : ?>
        <div class="glc-archive-grid">
            <?php foreach ( $upcoming as $ev ) :
                $eid     = $ev->ID;
                $date    = glc_event_date( $eid );
                $time    = glc_event_time_range( $eid );
                $site    = get_post_meta( $eid, 'site_name',     true );
                $meet    = get_post_meta( $eid, 'meeting_point', true );
                $coming  = (int) get_post_meta( $eid, 'rsvp_count', true );

                $when = $date ? date( 'l, F j, Y', strtotime( $date ) ) : '';
                if ( $when && $time ) $when .= ' · ' . $time;
            ?>
            <div class="glc-fp-cleanup-card glc-ev-card">

                <div class="glc-archive-card-thumb">
                    <?php if ( has_post_thumbnail( $eid ) ) : ?>
                    <img src="<?php echo esc_url( get_the_post_thumbnail_url( $eid, 'medium_large' ) ); ?>" alt="" loading="lazy">
                    <?php endif; ?>
                </div>

                <div class="glc-archive-card-body">

                    <?php if ( $when ) : ?>
                    <div class="glc-fp-card-date"><?php echo esc_html( $when ); ?></div>
                    <?php endif; ?>

                    <a class="glc-fp-card-title" href="<?php echo esc_url( get_permalink( $eid ) ); ?>">
                        <?php echo esc_html( get_the_title( $eid ) ); ?>
                    </a>

                    <?php if ( $site ) : ?>
                    <p class="glc-ev-card-site"><?php echo esc_html( $site ); ?></p>
                    <?php endif; ?>

                    <?php if ( $meet ) : ?>
                    <p class="glc-ev-card-meet"><?php echo esc_html( wp_trim_words( $meet, 16, '…' ) ); ?></p>
                    <?php endif; ?>

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

                    <a class="glc-ev-rsvp-link" href="<?php echo esc_url( get_permalink( $eid ) ); ?>">
                        <?php esc_html_e( 'Details & RSVP →', 'great-lake-cleaners' ); ?>
                    </a>

                </div><!-- .glc-archive-card-body -->

            </div>
            <?php endforeach; ?>
        </div>
        <?php else : ?>
        <p class="glc-archive-empty">
            <?php printf(
                wp_kses(
                    __( 'No events on the calendar right now — follow us on <a href="%1$s" target="_blank" rel="noopener noreferrer">Instagram</a> or <a href="%2$s">join the crew</a> to hear about the next one.', 'great-lake-cleaners' ),
                    [ 'a' => [ 'href' => [], 'target' => [], 'rel' => [] ] ]
                ),
                'https://www.instagram.com/greatlakecleaners',
                esc_url( home_url( '/join-crew/' ) )
            ); ?>
        </p>
        <?php endif; ?>

    <?php endif; ?>

    <?php if ( ! empty( $past_items ) ) : ?>
    <section class="glc-ev-past" aria-labelledby="glc-past-events-heading">
        <h2 id="glc-past-events-heading" class="glc-ev-past-h2"><?php esc_html_e( 'Past Events', 'great-lake-cleaners' ); ?></h2>

        <ul class="glc-ev-past-list">
            <?php foreach ( $past_items as $ev ) :
                $eid    = $ev->ID;
                $date   = glc_event_date( $eid );
                $linked = (int) get_post_meta( $eid, 'linked_cleanup_id', true );
                $report = ( $linked && 'publish' === get_post_status( $linked ) ) ? get_permalink( $linked ) : '';
            ?>
            <li class="glc-ev-past-row">
                <?php if ( $date ) : ?>
                <span class="glc-ev-past-date"><?php echo esc_html( date( 'M j, Y', strtotime( $date ) ) ); ?></span>
                <?php endif; ?>
                <a class="glc-ev-past-title" href="<?php echo esc_url( get_permalink( $eid ) ); ?>">
                    <?php echo esc_html( get_the_title( $eid ) ); ?>
                </a>
                <?php if ( $report ) : ?>
                <a class="glc-ev-results-link" href="<?php echo esc_url( $report ); ?>">
                    <?php esc_html_e( 'See the results →', 'great-lake-cleaners' ); ?>
                </a>
                <?php endif; ?>
            </li>
            <?php endforeach; ?>
        </ul>

        <?php if ( $total_pages > 1 ) : ?>
        <nav class="glc-pagination" aria-label="<?php esc_attr_e( 'Past event pages', 'great-lake-cleaners' ); ?>">
            <div class="nav-links">
                <?php if ( $paged > 1 ) : ?>
                <a class="page-numbers prev" href="<?php echo esc_url( get_pagenum_link( $paged - 1 ) ); ?>">
                    &larr; <?php esc_html_e( 'Newer events', 'great-lake-cleaners' ); ?>
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
                    <?php esc_html_e( 'Older events', 'great-lake-cleaners' ); ?> &rarr;
                </a>
                <?php endif; ?>
            </div>
        </nav>
        <?php endif; ?>

    </section>
    <?php endif; ?>

</div>
</div>

<?php get_footer(); ?>
