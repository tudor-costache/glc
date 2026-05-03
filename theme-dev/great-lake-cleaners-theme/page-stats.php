<?php
/**
 * Template Name: Stats
 * Template for /stats/ — cumulative stats charts.
 */
get_header(); ?>

<div class="glc-stats-page-wrap">

    <header class="glc-stats-page-header">
        <span class="glc-fp-label"><?php esc_html_e( 'By the Numbers', 'great-lake-cleaners' ); ?></span>
        <h1 class="glc-stats-page-h1"><?php esc_html_e( 'Our Stats', 'great-lake-cleaners' ); ?></h1>
        <p class="glc-stats-page-intro"><?php esc_html_e( 'How our cleanups add up over time.', 'great-lake-cleaners' ); ?></p>
    </header>

    <section class="glc-stats-section" id="debris">
        <h2 class="glc-stats-section-h2"><?php esc_html_e( 'Debris & Recycling Over Time', 'great-lake-cleaners' ); ?></h2>
        <?php echo do_shortcode( '[glc_timeline]' ); ?>
    </section>

    <section class="glc-stats-section" id="hours">
        <h2 class="glc-stats-section-h2"><?php esc_html_e( 'Volunteer Hours & Milestones', 'great-lake-cleaners' ); ?></h2>
        <?php echo do_shortcode( '[glc_impact_highlights]' ); ?>
    </section>

</div>

<?php get_footer();
