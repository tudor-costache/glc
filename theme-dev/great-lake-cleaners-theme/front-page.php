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
    <section class="glc-fp-section" aria-labelledby="glc-hero-heading">

        <div class="glc-fp-text">
            <span class="glc-fp-label">The Lake Starts Here</span>
            <h1 class="glc-fp-h2" id="glc-hero-heading">
                What gets left on the riverbank <em class="glc-hero-em">flows into the lake.</em>
            </h1>
            <div class="glc-fp-body">
                <p>We are cleaning local rivers and shores by foot and paddle because
                what enters our water reaches our Great Lakes and pollutes all along the way.</p>
            </div>
            <div class="glc-cta-row glc-cta-row--section">
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
                $submit_page = get_page_by_path( 'submit-cleanup' );
                $submit_url  = $submit_page ? get_permalink( $submit_page ) : '#';
                ?>
                <a href="<?php echo esc_url( $submit_url ); ?>" class="glc-btn-outline">
                    <?php esc_html_e( 'Submit a Cleanup', 'great-lake-cleaners' ); ?>
                </a>
            </div>
        </div>

        <div class="glc-fp-visual glc-fp-map" role="region" aria-label="<?php esc_attr_e( 'Cleanup locations map', 'great-lake-cleaners' ); ?>">
            <?php echo do_shortcode( '[glc_map height="340px" limit="5" cluster_radius="10"]' ); ?>
        </div>

    </section>

    <!-- ── Recent Cleanups strip (social proof — active org signal) ─────── -->
    <?php
    $recent = glc_get_all_cleanups();
    $recent = array_slice( $recent, 0, 3 );
    if ( ! empty( $recent ) ) :
    ?>
    <section class="glc-fp-recent-strip" aria-labelledby="glc-recent-heading">
        <div class="glc-fp-recent-grid glc-fp-recent-grid--slim">
            <?php foreach ( $recent as $event ) :
                $date     = glc_cleanup_field( $event, 'cleanup_date' );
                $site     = glc_cleanup_field( $event, 'site_name' );
                $bags     = glc_cleanup_field( $event, 'bags' );
                $weight   = glc_cleanup_field( $event, 'weight_kg' );
                $recycled = glc_cleanup_field( $event, 'items_recycled' );
                $hours    = glc_cleanup_field( $event, 'hours' );

                // Build a text label for assistive tech so the full-card link is descriptive
                $card_label_parts = [];
                if ( $site ) $card_label_parts[] = $site;
                if ( $date ) $card_label_parts[] = date( 'F j, Y', strtotime( $date ) );
                if ( $bags ) $card_label_parts[] = $bags . ' ' . ( 1 === (int) $bags ? 'bag' : 'bags' );
                if ( $weight ) $card_label_parts[] = $weight . ' kg';
                if ( $recycled ) $card_label_parts[] = $recycled . ' items recycled';
                if ( $hours ) {
                    $card_label_parts[] = $hours < 1
                        ? round( $hours * 60 ) . ' min'
                        : number_format( $hours, 1 ) . ' h';
                }
                $card_label = implode( ', ', $card_label_parts );
            ?>
            <a class="glc-fp-slim-card"
               href="<?php echo esc_url( get_permalink( $event->ID ) ); ?>"
               aria-label="<?php echo esc_attr( $card_label ); ?>">
                <?php if ( $date ) : ?>
                <span class="glc-fp-slim-date">
                    <?php echo esc_html( date( 'M j, Y', strtotime( $date ) ) ); ?>
                </span>
                <?php endif; ?>
                <span class="glc-fp-slim-title"><?php echo esc_html( $site ); ?></span>
                <span class="glc-fp-slim-stats">
                    <?php
                    $idir = esc_url( get_template_directory_uri() ) . '/assets/images';
                    $ic   = function( $icon, $val, $suffix = '' ) use ( $idir ) {
                        return '<span class="glc-cs"><img src="' . $idir . '/' . $icon . '" alt="" width="18" height="18" aria-hidden="true">' . esc_html( $val ) . ( $suffix ? ' ' . $suffix : '' ) . '</span>';
                    };
                    if ( $bags )     echo $ic( 'icon-bag.svg',     $bags,                       1 === (int)$bags ? 'bag' : 'bags' );
                    if ( $weight )   echo $ic( 'icon-scale.svg',   $weight,                     'kg' );
                    if ( $recycled ) echo $ic( 'icon-recycle.svg', $recycled,                   '' );
                    if ( $hours ) {
                        if ( $hours < 1 ) {
                            echo $ic( 'icon-timer.svg', round( $hours * 60 ), 'min' );
                        } else {
                            echo $ic( 'icon-timer.svg', number_format( $hours, 1 ), 'h' );
                        }
                    }
                    ?>
                </span>
            </a>
            <?php endforeach; wp_reset_postdata(); ?>
        </div>
    </section>

    <?php endif; ?>

    <hr class="glc-fp-divider">

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
            <img src="<?php echo esc_url( get_stylesheet_directory_uri() . '/assets/images/stylized-map-rivers-lake.jpg' ); ?>"
                 alt="Stylized map showing Ontario rivers flowing into the Grand River and Lake Erie"
                 class="glc-fp-img">
        </div>

    </section>

    <hr class="glc-fp-divider">

    <!-- ── 2. Get Involved ────────────────────────────────────────────────── -->
    <section class="glc-fp-section glc-fp-reverse" aria-labelledby="glc-involved-heading">

        <div class="glc-fp-text">
            <span class="glc-fp-label">Get Involved</span>
            <h2 class="glc-fp-h2" id="glc-involved-heading">
                Clean your local waterway.
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
            <img src="<?php echo esc_url( get_stylesheet_directory_uri() . '/assets/images/stylized-paddler.jpg' ); ?>"
                 alt="Illustration of a paddler cleaning up a river, with a great blue heron on the bank"
                 class="glc-fp-img">
        </div>

    </section>

    <hr class="glc-fp-divider">

    <!-- ── 3. Submit a Cleanup ────────────────────────────────────────────── -->
    <section class="glc-fp-section" aria-labelledby="glc-submit-heading">

        <div class="glc-fp-text">
            <span class="glc-fp-label">Submit a Cleanup</span>
            <h2 class="glc-fp-h2" id="glc-submit-heading">
                Did a cleanup? We want to count it.
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
            <img src="<?php echo esc_url( get_stylesheet_directory_uri() . '/assets/images/cleanup_stylized.jpg' ); ?>"
                 alt="Illustration of a litter picker collecting cans and recyclables on a riverbank"
                 class="glc-fp-img">
        </div>

    </section>

</div><!-- .glc-fp-sections -->
</div><!-- .glc-fp-wrapper -->

<?php get_footer(); ?>
