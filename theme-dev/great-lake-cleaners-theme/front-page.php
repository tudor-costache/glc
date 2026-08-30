<?php
/**
 * Great Lake Cleaners — front-page.php
 *
 * WordPress loads this template whenever a static front page is set
 * (Settings → Reading → "A static page"). Takes priority over index.php.
 *
 * Structure (hero + map + stats come from header.php via get_header()):
 *   0. Hero (map + CTA)
 *   1. Recent Cleanups strip (social proof, slim cards)
 *   2. About / Mission
 *   3. Get Involved
 *   4. Submit a Cleanup
 */

get_header();
?>

<div class="glc-fp-wrapper">
<div class="glc-fp-sections">

    <!-- ── 0. Hero ─────────────────────────────────────────────────────────── -->
    <section class="glc-fp-section glc-fp-hero" aria-labelledby="glc-hero-heading">

        <div class="glc-fp-text">
            <span class="glc-fp-label">The Lake Starts Here</span>
            <h1 class="glc-fp-h2" id="glc-hero-heading">
                What gets left on the <em class="glc-hero-em">riverbank</em> flows into the lake.
            </h1>
            <div class="glc-fp-body">
                <p>We are cleaning local rivers and shores by foot and paddle because
                what enters our water reaches our Great Lakes and pollutes all along the way.</p>
            </div>
            <?php
            // Second CTA slot: the soonest upcoming event replaces "Submit a
            // Cleanup" in place, and reverts to it on its own once that event's
            // day has passed (glc_get_upcoming_events() is date-driven). When an
            // event is showing, --has-event lets mobile drop to just the chip.
            $next_event = function_exists( 'glc_get_upcoming_events' ) ? glc_get_upcoming_events( 1 ) : [];
            $next_event = $next_event ? $next_event[0] : null;
            ?>
            <div class="glc-cta-row glc-cta-row--section glc-cta-row--hero<?php echo $next_event ? ' glc-cta-row--has-event' : ''; ?>">
                <?php
                $cleanups_page = get_page_by_path( 'cleanups' );
                $cleanups_url  = $cleanups_page
                    ? get_permalink( $cleanups_page )
                    : get_post_type_archive_link( 'cleanup_event' );
                ?>
                <a href="<?php echo esc_url( $cleanups_url ?: '#' ); ?>" class="glc-btn-primary">
                    <?php esc_html_e( 'See Our Cleanups', 'great-lake-cleaners' ); ?>
                </a>
                <?php
                if ( $next_event ) :
                    $ev_id   = $next_event->ID;
                    $ev_date = glc_event_date( $ev_id );
                    $ev_ts   = $ev_date ? strtotime( $ev_date ) : 0;
                    $ev_time = glc_event_time_range( $ev_id );
                    $ev_site = get_post_meta( $ev_id, 'site_name', true );

                    $ev_aria = [ get_the_title( $ev_id ) ];
                    if ( $ev_ts )   $ev_aria[] = date( 'F j', $ev_ts );
                    if ( $ev_time ) $ev_aria[] = $ev_time;
                    if ( $ev_site ) $ev_aria[] = $ev_site;
                    $ev_aria[] = __( 'details and RSVP', 'great-lake-cleaners' );
                ?>
                <a class="glc-hero-event"
                   href="<?php echo esc_url( get_permalink( $ev_id ) ); ?>"
                   aria-label="<?php echo esc_attr( implode( ', ', $ev_aria ) ); ?>">
                    <?php if ( $ev_ts ) : ?>
                    <span class="glc-hero-event-date" aria-hidden="true">
                        <span class="glc-hero-event-month"><?php echo esc_html( strtoupper( date( 'M', $ev_ts ) ) ); ?></span>
                        <span class="glc-hero-event-day"><?php echo esc_html( date( 'j', $ev_ts ) ); ?></span>
                    </span>
                    <?php endif; ?>
                    <span class="glc-hero-event-text">
                        <span class="glc-hero-event-title"><?php echo esc_html( get_the_title( $ev_id ) ); ?></span>
                        <span class="glc-hero-event-rsvp"><?php esc_html_e( 'RSVP', 'great-lake-cleaners' ); ?> &rarr;</span>
                    </span>
                </a>
                <?php else :
                    $submit_page = get_page_by_path( 'submit-cleanup' );
                    $submit_url  = $submit_page ? get_permalink( $submit_page ) : '#';
                ?>
                <a href="<?php echo esc_url( $submit_url ); ?>" class="glc-btn-outline">
                    <?php esc_html_e( 'Submit a Cleanup', 'great-lake-cleaners' ); ?>
                </a>
                <?php endif; ?>
            </div>
        </div>

        <div class="glc-fp-visual glc-fp-map" role="region" aria-label="<?php esc_attr_e( 'Cleanup locations map', 'great-lake-cleaners' ); ?>">
            <?php echo do_shortcode( '[glc_map height="340px" limit="5" cluster_radius="10" corridors="1" corridor_pins="0"]' ); ?>
        </div>

    </section>

    <!-- ── Recent Cleanups spotlight (featured latest + slim prior row) ──── -->
    <?php
    $recent  = glc_get_all_cleanups();
    $recent  = array_slice( $recent, 0, 4 );
    $featured = ! empty( $recent ) ? array_shift( $recent ) : null;
    $idir     = esc_url( get_template_directory_uri() ) . '/assets/images';
    $ic       = function( $icon, $val, $suffix = '' ) use ( $idir ) {
        return '<span class="glc-cs"><img src="' . $idir . '/' . $icon . '" alt="" width="18" height="18" aria-hidden="true">' . esc_html( $val ) . ( $suffix ? ' ' . $suffix : '' ) . '</span>';
    };

    if ( $featured ) :
        $f_date     = glc_cleanup_field( $featured, 'cleanup_date' );
        $f_site     = glc_cleanup_field( $featured, 'site_name' );
        $f_bags     = glc_cleanup_field( $featured, 'bags' );
        $f_weight   = glc_cleanup_field( $featured, 'weight_kg' );
        $f_recycled = glc_cleanup_field( $featured, 'items_recycled' );
        $f_hours    = glc_cleanup_field( $featured, 'hours' );
        $f_blurb    = '';
        if ( $featured->post_excerpt ) {
            $f_blurb = wp_strip_all_tags( $featured->post_excerpt );
        } elseif ( $featured->post_content ) {
            $f_blurb = wp_trim_words( wp_strip_all_tags( $featured->post_content ), 25, '…' );
        }

        $cleanups_page = get_page_by_path( 'cleanups' );
        $cleanups_url  = $cleanups_page
            ? get_permalink( $cleanups_page )
            : get_post_type_archive_link( 'cleanup_event' );
    ?>
    <section class="glc-fp-spotlight" aria-labelledby="glc-recent-heading">

        <div class="glc-spot-header">
            <h2 id="glc-recent-heading" class="glc-spot-h2">Recent cleanups</h2>
        </div>

        <a class="glc-spot-featured"
           href="<?php echo esc_url( get_permalink( $featured->ID ) ); ?>"
           aria-label="<?php echo esc_attr( sprintf(
               'Latest cleanup%s%s — read more',
               $f_date ? ', ' . date( 'F j, Y', strtotime( $f_date ) ) : '',
               $f_site ? ' at ' . $f_site : ''
           ) ); ?>">

            <div class="glc-spot-photo">
                <?php
                $thumb = get_the_post_thumbnail( $featured->ID, 'medium_large' );
                if ( $thumb ) echo $thumb;
                ?>
                <span class="glc-spot-pill" aria-hidden="true">
                    <svg width="11" height="11" viewBox="0 0 16 16" fill="currentColor" aria-hidden="true"><path d="M8 1l2 4.5 5 .6-3.7 3.3 1.1 4.9L8 11.8 3.6 14.3l1.1-4.9L1 6.1l5-.6z"/></svg>
                    Latest<?php if ( $f_date ) : ?> &middot; <?php echo esc_html( date( 'M j, Y', strtotime( $f_date ) ) ); ?><?php endif; ?>
                </span>
            </div>

            <div class="glc-spot-body">
                <?php if ( $f_site ) : ?>
                <div class="glc-spot-meta"><?php echo esc_html( $f_site ); ?></div>
                <?php endif; ?>
                <h3 class="glc-spot-title"><?php echo esc_html( get_the_title( $featured->ID ) ); ?></h3>
                <?php if ( $f_blurb ) : ?>
                <p class="glc-spot-note"><?php echo esc_html( $f_blurb ); ?></p>
                <?php endif; ?>
                <ul class="glc-spot-stats">
                    <?php if ( $f_bags ) : ?>
                    <li>
                        <span class="glc-spot-val"><?php echo esc_html( $f_bags ); ?></span>
                        <span class="glc-spot-lbl"><?php echo 1 == (float) $f_bags ? 'Bag' : 'Bags'; ?></span>
                    </li>
                    <?php endif; ?>
                    <?php if ( $f_weight ) : ?>
                    <li>
                        <span class="glc-spot-val"><?php echo esc_html( $f_weight ); ?> kg</span>
                        <span class="glc-spot-lbl">Debris</span>
                    </li>
                    <?php endif; ?>
                    <?php if ( $f_recycled ) : ?>
                    <li>
                        <span class="glc-spot-val"><?php echo esc_html( $f_recycled ); ?></span>
                        <span class="glc-spot-lbl">Recycled</span>
                    </li>
                    <?php endif; ?>
                    <?php if ( $f_hours ) : ?>
                    <li>
                        <span class="glc-spot-val"><?php echo $f_hours < 1 ? esc_html( round( $f_hours * 60 ) ) . ' min' : esc_html( number_format( $f_hours, 1 ) ) . ' h'; ?></span>
                        <span class="glc-spot-lbl">On site</span>
                    </li>
                    <?php endif; ?>
                </ul>
            </div>
        </a>

        <?php if ( ! empty( $recent ) ) : ?>
        <div class="glc-spot-prior" aria-label="Prior cleanups">
            <?php foreach ( $recent as $event ) :
                $date     = glc_cleanup_field( $event, 'cleanup_date' );
                $site     = glc_cleanup_field( $event, 'site_name' );
                $bags     = glc_cleanup_field( $event, 'bags' );
                $weight   = glc_cleanup_field( $event, 'weight_kg' );
                $recycled = glc_cleanup_field( $event, 'items_recycled' );
                $hours    = glc_cleanup_field( $event, 'hours' );

                $card_label_parts = [];
                if ( $site ) $card_label_parts[] = $site;
                if ( $date ) $card_label_parts[] = date( 'F j, Y', strtotime( $date ) );
                if ( $bags ) $card_label_parts[] = $bags . ' ' . ( 1 === (int) $bags ? 'bag' : 'bags' );
                if ( $weight ) $card_label_parts[] = $weight . ' kg';
                if ( $recycled ) $card_label_parts[] = $recycled . ' items recycled';
                if ( $hours ) $card_label_parts[] = ( $hours < 1 ? round( $hours * 60 ) . ' min' : number_format( $hours, 1 ) . ' h' );
            ?>
            <a class="glc-fp-slim-card"
               href="<?php echo esc_url( get_permalink( $event->ID ) ); ?>"
               aria-label="<?php echo esc_attr( implode( ', ', $card_label_parts ) ); ?>">
                <?php if ( $date ) : ?>
                <span class="glc-fp-slim-date"><?php echo esc_html( date( 'M j, Y', strtotime( $date ) ) ); ?></span>
                <?php endif; ?>
                <span class="glc-fp-slim-title"><?php echo esc_html( $site ); ?></span>
                <span class="glc-fp-slim-stats">
                    <?php
                    if ( $bags )     echo $ic( 'icon-bag.svg',     $bags,     1 === (int)$bags ? 'bag' : 'bags' );
                    if ( $weight )   echo $ic( 'icon-scale.svg',   $weight,   'kg' );
                    if ( $recycled ) echo $ic( 'icon-recycle.svg', $recycled, 'items' );
                    if ( $hours ) {
                        echo $ic( 'icon-timer.svg',
                            $hours < 1 ? round( $hours * 60 ) : number_format( $hours, 1 ),
                            $hours < 1 ? 'min' : 'h'
                        );
                    }
                    ?>
                </span>
            </a>
            <?php endforeach; wp_reset_postdata(); ?>
        </div>
        <?php endif; ?>

        <div class="glc-spot-footer">
            <a class="glc-spot-view-all" href="<?php echo esc_url( $cleanups_url ?: '#' ); ?>">
                View all cleanups &rarr;
            </a>
        </div>

    </section>

    <?php endif; ?>

    <!-- ── Upcoming events now surface in the hero CTA row (.glc-hero-event) ── -->

    <div class="glc-wave-divider" aria-hidden="true">
        <svg viewBox="0 0 1200 22" preserveAspectRatio="none" xmlns="http://www.w3.org/2000/svg">
            <path d="M0,11 C150,3 300,19 450,11 C600,3 750,19 900,11 C1050,3 1200,19 1200,11" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
            <path d="M0,16 C150,8 300,24 450,16 C600,8 750,24 900,16 C1050,8 1200,24 1200,16" fill="none" stroke="currentColor" stroke-width="1.4" stroke-linecap="round" opacity="0.45"/>
        </svg>
    </div>

    <!-- ── 1. About / Mission ──────────────────────────────────────────────── -->
    <section class="glc-fp-section" aria-labelledby="glc-about-heading">

        <div class="glc-fp-text">
            <span class="glc-fp-label">About Us</span>
            <h2 class="glc-fp-h2" id="glc-about-heading">
                We're Making an Impact
            </h2>
            <div class="glc-fp-body">
                <p>From where we are in Southern Ontario, water flows into the Great Lakes.
				What gets left on our riverbanks doesn't stay here. Plastic and other
				contaminants are carried downstream polluting local waterways,
				aquifers, and the Great Lakes, which hold a fifth of the world's
				fresh surface water.</p>
                <p>
                Great Lake Cleaners wants to make a difference — we are a Guelph-based group
				doing regular cleanups along our waterways by foot on the shores and by paddle
				on the water. Our passion is clean water.</p>
            </div>
            <?php
            $about_page = get_page_by_path( 'about' );
            $about_url  = $about_page ? get_permalink( $about_page ) : home_url( '/about/' );
            ?>
            <a href="<?php echo esc_url( $about_url ); ?>" class="glc-btn-outline">
                <?php esc_html_e( 'Our Impact', 'great-lake-cleaners' ); ?>
            </a>
        </div>

        <div class="glc-fp-visual">
            <img src="<?php echo esc_url( get_stylesheet_directory_uri() . '/assets/images/photo-impact.jpg' ); ?>"
                 alt="A pile of debris including metal pipes, broken boards, and bottles collected from a riverbank"
                 class="glc-fp-img">
        </div>

    </section>

    <div class="glc-wave-divider" aria-hidden="true">
        <svg viewBox="0 0 1200 22" preserveAspectRatio="none" xmlns="http://www.w3.org/2000/svg">
            <path d="M0,11 C150,3 300,19 450,11 C600,3 750,19 900,11 C1050,3 1200,19 1200,11" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
            <path d="M0,16 C150,8 300,24 450,16 C600,8 750,24 900,16 C1050,8 1200,24 1200,16" fill="none" stroke="currentColor" stroke-width="1.4" stroke-linecap="round" opacity="0.45"/>
        </svg>
    </div>

    <!-- ── 2. Get Involved ────────────────────────────────────────────────── -->
    <section class="glc-fp-section glc-fp-reverse" aria-labelledby="glc-involved-heading">

        <div class="glc-fp-text">
            <span class="glc-fp-label">Get Involved</span>
            <h2 class="glc-fp-h2" id="glc-involved-heading">
                Clean your local waterway
            </h2>
            <div class="glc-fp-body">
                <p>We run regular cleanups along local rivers and shorelines.
                No experience required — just show up with gloves and a bag.
                Dog walkers, paddlers, and families welcome.</p>
				<p>Small local effort. Watershed-scale impact.</p>
                <p>Follow us on Instagram to see when and where we're heading out next,
                or sign up to join our cleanup crew.</p>
            </div>

            <div class="glc-cta-row glc-cta-row--section">
                <a href="https://instagram.com/greatlakecleaners"
                   class="glc-btn-primary"
                   target="_blank" rel="noopener noreferrer">
                    Follow on Instagram<span class="screen-reader-text"> (opens in new tab)</span>
                </a>
                <?php
                $crew_page = get_page_by_path( 'join-crew' );
                $crew_url  = $crew_page ? get_permalink( $crew_page ) : home_url( '/join-crew/' );
                ?>
                <a href="<?php echo esc_url( $crew_url ); ?>" class="glc-btn-outline">
                    <?php esc_html_e( 'Join our Crew', 'great-lake-cleaners' ); ?>
                </a>
            </div>
        </div>

        <div class="glc-fp-visual">
            <img src="<?php echo esc_url( get_stylesheet_directory_uri() . '/assets/images/photo-get-involved.jpg' ); ?>"
                 alt="A volunteer in a high-visibility vest pushing a shopping cart loaded with collected litter during a cleanup"
                 class="glc-fp-img"
                 style="object-position: center top">
        </div>

    </section>

    <div class="glc-wave-divider" aria-hidden="true">
        <svg viewBox="0 0 1200 22" preserveAspectRatio="none" xmlns="http://www.w3.org/2000/svg">
            <path d="M0,11 C150,3 300,19 450,11 C600,3 750,19 900,11 C1050,3 1200,19 1200,11" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
            <path d="M0,16 C150,8 300,24 450,16 C600,8 750,24 900,16 C1050,8 1200,24 1200,16" fill="none" stroke="currentColor" stroke-width="1.4" stroke-linecap="round" opacity="0.45"/>
        </svg>
    </div>

    <!-- ── 3. Submit a Cleanup ────────────────────────────────────────────── -->
    <section class="glc-fp-section" aria-labelledby="glc-submit-heading">

        <div class="glc-fp-text">
            <span class="glc-fp-label">Submit a Cleanup</span>
            <h2 class="glc-fp-h2" id="glc-submit-heading">
                Did a cleanup? We want to count it
            </h2>
            <div class="glc-fp-body">
                <p>Every cleanup on a local waterway matters, whether it's a solo
                litter pick on your lunch break, a family outing, or a paddle with
                friends. Submit yours and we'll add it to the community total.</p>
            </div>

            <div class="glc-steps">
                <div class="glc-step">
                    <div class="glc-step-num">1</div>
                    <div class="glc-step-text">
                        <strong>Do the cleanup</strong>
                        <span>Any local waterway, every little bit helps.</span>
                    </div>
                </div>
                <div class="glc-step">
                    <div class="glc-step-num">2</div>
                    <div class="glc-step-text">
                        <strong>Fill out the form: </strong>
                        <span>Date, location, what you collected. Photos welcome.</span>
                    </div>
                </div>
                <div class="glc-step">
                    <div class="glc-step-num">3</div>
                    <div class="glc-step-text">
                        <strong>We add it to the count: </strong>
                        <span>Your cleanup gets reviewed and added to the community total, and your waterway and effort shows up on our map.</span>
                    </div>
                </div>
            </div>

            <?php
            $submit_page = get_page_by_path( 'submit-cleanup' );
            $submit_url  = $submit_page ? get_permalink( $submit_page ) : '#';
            ?>
            <a href="<?php echo esc_url( $submit_url ); ?>" class="glc-btn-primary">
                Submit a Cleanup
            </a>
        </div>

        <div class="glc-fp-visual">
            <img src="<?php echo esc_url( get_stylesheet_directory_uri() . '/assets/images/photo-submit.jpg' ); ?>"
                 alt="A golden doodle dog standing beside a green bag of collected litter on the bank of the Speed River"
                 class="glc-fp-img">
        </div>

    </section>

</div><!-- .glc-fp-sections -->
</div><!-- .glc-fp-wrapper -->

<?php get_footer(); ?>
