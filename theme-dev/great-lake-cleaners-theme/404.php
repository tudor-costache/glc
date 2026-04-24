<?php
/**
 * Great Lake Cleaners — 404.php
 *
 * Displayed whenever WordPress cannot find the requested URL.
 * Keeps the site header/footer intact and offers useful navigation.
 */

get_header(); ?>

<div class="glc-fp-wrapper">
<div class="glc-404-wrap">

    <div class="glc-404-inner">

        <span class="glc-404-icon" aria-hidden="true"><img src="<?php echo esc_url( get_template_directory_uri() ); ?>/assets/images/icon-wave.svg" alt="" width="20" height="20" style="vertical-align:-0.2em;flex-shrink:0;width:1.3em;height:1.3em" aria-hidden="true"></span>

        <h1 class="glc-404-h1">
            <?php esc_html_e( 'Page not found.', 'great-lake-cleaners' ); ?>
        </h1>

        <p class="glc-404-body">
            <?php esc_html_e(
                'Looks like this stretch of river has already been cleaned up — or it never existed. Either way, nothing to find here.',
                'great-lake-cleaners'
            ); ?>
        </p>

        <div class="glc-404-actions">
            <a href="<?php echo esc_url( home_url( '/' ) ); ?>" class="glc-btn-primary">
                <?php esc_html_e( '← Back to Home', 'great-lake-cleaners' ); ?>
            </a>
            <?php
            $cleanups_url = get_post_type_archive_link( 'cleanup_event' );
            if ( $cleanups_url ) : ?>
            <a href="<?php echo esc_url( $cleanups_url ); ?>" class="glc-btn-outline">
                <?php esc_html_e( 'See Our Cleanups', 'great-lake-cleaners' ); ?>
            </a>
            <?php endif; ?>
        </div>

    </div>

</div>
</div>

<?php get_footer(); ?>
