<?php
/**
 * Great Lake Cleaners — page-join-crew.php
 *
 * Template Name: Join our Crew
 *
 * Loaded automatically for the page with slug 'join-crew'.
 * Renders the [glc_join_crew] shortcode inside a designed page shell.
 */

get_header();
?>

<div class="glc-fp-wrapper">
<div class="glc-submit-page-wrap">

    <!-- Page header -->
    <header class="glc-submit-page-header">
        <span class="glc-fp-label"><?php esc_html_e( 'Get Involved', 'great-lake-cleaners' ); ?></span>
        <h1 class="glc-submit-page-h1"><?php esc_html_e( 'Join our Crew', 'great-lake-cleaners' ); ?></h1>
        <p class="glc-submit-page-intro">
            <?php esc_html_e( 'By sharing your email you\'ll be notified the next time we plan a cleanup in your area — so you can show up, join the crew, and make a real difference. No experience needed, no commitment required. Come out when it works for you.', 'great-lake-cleaners' ); ?>
        </p>
    </header>

    <!-- Two-column layout: form left, sidebar right -->
    <div class="glc-submit-layout">

        <!-- Form column -->
        <div class="glc-submit-form-col">
            <?php echo do_shortcode( '[glc_join_crew]' ); ?>
        </div>

        <!-- Sidebar column -->
        <aside class="glc-submit-sidebar" aria-label="<?php esc_attr_e( 'Crew information', 'great-lake-cleaners' ); ?>">

            <div class="glc-sidebar-card">
                <h2 class="glc-sidebar-heading"><?php esc_html_e( 'What to expect', 'great-lake-cleaners' ); ?></h2>
                <ol class="glc-sidebar-steps">
                    <li>
                        <strong><?php esc_html_e( 'We plan an outing', 'great-lake-cleaners' ); ?></strong>
                        <span><?php esc_html_e( 'Shore walk or paddle — we pick a waterway, a date, and a meeting spot.', 'great-lake-cleaners' ); ?></span>
                    </li>
                    <li>
                        <strong><?php esc_html_e( 'You get a heads-up', 'great-lake-cleaners' ); ?></strong>
                        <span><?php esc_html_e( 'We send a short email with the details — where, when, and what to bring.', 'great-lake-cleaners' ); ?></span>
                    </li>
                    <li>
                        <strong><?php esc_html_e( 'Show up and clean up', 'great-lake-cleaners' ); ?></strong>
                        <span><?php esc_html_e( 'Come when you can. Every pair of hands helps.', 'great-lake-cleaners' ); ?></span>
                    </li>
                </ol>
            </div>

            <div class="glc-sidebar-card glc-sidebar-card--tips">
                <h2 class="glc-sidebar-heading"><?php esc_html_e( 'What to bring', 'great-lake-cleaners' ); ?></h2>
                <ul class="glc-sidebar-tips">
                    <li>
                        <span class="glc-tip-icon">🧤</span>
                        <span><?php esc_html_e( 'Work gloves — we handle a lot of wet and sharp material.', 'great-lake-cleaners' ); ?></span>
                    </li>
                    <li>
                        <span class="glc-tip-icon">👟</span>
                        <span><?php esc_html_e( 'Sturdy footwear you don\'t mind getting muddy.', 'great-lake-cleaners' ); ?></span>
                    </li>
                    <li>
                        <span class="glc-tip-icon">💧</span>
                        <span><?php esc_html_e( 'Water bottle and sunscreen for longer outings.', 'great-lake-cleaners' ); ?></span>
                    </li>
                    <li>
                        <span class="glc-tip-icon">🛶</span>
                        <span><?php esc_html_e( 'Joining a paddle cleanup? Bring your own board or canoe, and an inflatable or foam PFD. We\'ll confirm gear details in the outing email.', 'great-lake-cleaners' ); ?></span>
                    </li>
                </ul>
            </div>

        </aside><!-- .glc-submit-sidebar -->
    </div><!-- .glc-submit-layout -->

</div><!-- .glc-submit-page-wrap -->
</div><!-- .glc-fp-wrapper -->

<?php get_footer(); ?>
