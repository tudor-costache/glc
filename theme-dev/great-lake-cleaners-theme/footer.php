<?php
/**
 * Great Lake Cleaners — footer.php
 * Closes <main> opened in header.php, outputs site footer.
 */
?>

</main><!-- #main-content -->
</div><!-- .glc-main-outer -->

<!-- Wave into footer: lightest at top, deepens to navy at bottom -->
<div class="glc-wave-footer" aria-hidden="true">
    <svg viewBox="0 0 1200 80" xmlns="http://www.w3.org/2000/svg"
         preserveAspectRatio="none" width="100%" height="80">
        <!-- Top layer: lightest, highest crest, meets page content -->
        <path d="M0,28 C220,48 440,12 660,30 C860,48 1060,16 1200,36 L1200,80 L0,80 Z"
              fill="#5a9fc0" fill-opacity="0.45"/>
        <!-- Mid layer: medium blue, lower crest -->
        <path d="M0,42 C200,62 420,26 640,44 C840,62 1040,30 1200,50 L1200,80 L0,80 Z"
              fill="#2d6a96"/>
        <!-- Bottom layer: exact footer navy, lowest crest, zero-gap join -->
        <path d="M0,58 C180,44 400,70 620,56 C820,42 1040,66 1200,54 L1200,80 L0,80 Z"
              fill="#1a4a6b"/>
    </svg>
</div>

<!-- Stats strip — appears on every page above the footer -->
<div class="glc-stats-strip" aria-label="<?php esc_attr_e( 'Cumulative impact', 'great-lake-cleaners' ); ?>">
    <?php $s = glc_get_impact_stats(); ?>
    <div class="glc-stat">
        <span class="glc-stat-val"><?php echo esc_html( $s['cleanups'] ); ?><sup>+</sup></span>
        <?php
        $cleanups_page = get_page_by_path( 'cleanups' );
        $cleanups_url  = $cleanups_page
            ? get_permalink( $cleanups_page )
            : get_post_type_archive_link( 'cleanup_event' );
        ?>
        <span class="glc-stat-lbl">
            <?php if ( $cleanups_url ) : ?>
            <a href="<?php echo esc_url( $cleanups_url ); ?>" class="glc-stat-lbl-link">
                <?php esc_html_e( 'Cleanups', 'great-lake-cleaners' ); ?>
            </a>
            <?php else : ?>
            <?php esc_html_e( 'Cleanups', 'great-lake-cleaners' ); ?>
            <?php endif; ?>
        </span>
    </div>
    <div class="glc-stat">
        <span class="glc-stat-val"><?php echo esc_html( number_format( $s['weight_kg'], 0 ) ); ?><sup>+ kg</sup></span>
        <span class="glc-stat-lbl"><?php esc_html_e( 'Debris Removed', 'great-lake-cleaners' ); ?></span>
    </div>
    <div class="glc-stat">
        <span class="glc-stat-val"><?php echo esc_html( number_format( $s['hours'], 0 ) ); ?><sup>+</sup></span>
        <span class="glc-stat-lbl"><?php esc_html_e( 'Volunteer Hours', 'great-lake-cleaners' ); ?></span>
    </div>
    <?php if ( $s['recycled'] > 0 ) : ?>
    <div class="glc-stat">
        <span class="glc-stat-val"><?php echo esc_html( number_format( $s['recycled'] ) ); ?><sup>+</sup></span>
        <span class="glc-stat-lbl"><?php esc_html_e( 'Items Recycled', 'great-lake-cleaners' ); ?></span>
    </div>
    <?php endif; ?>
    <div class="glc-stat">
        <span class="glc-stat-val"><?php echo esc_html( $s['corridors'] ); ?><sup>+</sup></span>
        <span class="glc-stat-lbl"><?php esc_html_e( 'River Corridors', 'great-lake-cleaners' ); ?></span>
    </div>
</div>

<footer id="glc-site-footer" class="glc-site-footer" role="contentinfo">
    <div class="glc-footer-inner">

        <nav class="glc-footer-nav" aria-label="<?php esc_attr_e( 'Footer navigation', 'great-lake-cleaners' ); ?>">
            <?php
            wp_nav_menu( [
                'theme_location' => 'footer',
                'menu_class'     => 'glc-footer-menu',
                'container'      => false,
                'depth'          => 1,
                'fallback_cb'    => false,
            ] );
            ?>
        </nav>

    </div>
    <div class="glc-footer-base">
        <p>&copy; <?php echo esc_html( date( 'Y' ) ); ?>
            <?php bloginfo( 'name' ); ?> &nbsp;·&nbsp;
            <a href="<?php echo esc_url( home_url( '/privacy-policy/' ) ); ?>">
                <?php esc_html_e( 'Privacy Policy', 'great-lake-cleaners' ); ?>
            </a>
            &nbsp;·&nbsp;
            <a href="https://instagram.com/greatlakecleaners"
               target="_blank" rel="noopener noreferrer"
               aria-label="<?php esc_attr_e( 'Instagram', 'great-lake-cleaners' ); ?>"
               class="glc-footer-insta">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none"
                     stroke="currentColor" stroke-width="1.8" stroke-linecap="round"
                     style="vertical-align: middle;">
                    <rect x="2" y="2" width="20" height="20" rx="5"/>
                    <circle cx="12" cy="12" r="5"/>
                    <circle cx="17.5" cy="6.5" r="1.5" fill="currentColor" stroke="none"/>
                </svg>
            </a>
        </p>
    </div>
</footer>

<?php wp_footer(); ?>
</body>
</html>
