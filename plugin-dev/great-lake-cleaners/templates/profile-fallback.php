<?php
/**
 * Minimal cleaner-profile template.
 *
 * Only used when the active theme ships no glc-profile.php. The real design
 * lives in the theme, the same way every other CPT template on this site does;
 * this exists so /crew/{slug}/ still renders something correct if the
 * plugin is running under a different theme.
 *
 * Anything added here should stay deliberately plain — a change made only in
 * this file will never be seen on greatlakecleaners.ca.
 */

defined( 'ABSPATH' ) || exit;

$glc_user = glc_profile_queried_user();
if ( ! $glc_user ) {
    // Should be unreachable: template_include only routes here once the profile
    // has resolved. Belt and braces, because the alternative is a fatal.
    get_header();
    echo '<p>Profile not found.</p>';
    get_footer();
    return;
}

$glc_stats    = glc_user_impact_stats( $glc_user->ID );
$glc_cleanups = glc_user_cleanups( $glc_user->ID, [ 'publish' ] );

get_header();
?>

<div class="glc-profile-wrap">

    <header class="glc-profile-header">
        <h1><?php echo esc_html( $glc_user->display_name ); ?></h1>
        <?php
        $glc_since = (string) get_user_meta( $glc_user->ID, 'glc_joined_date', true );
        if ( $glc_since && strtotime( $glc_since ) ) : ?>
        <p><?php echo esc_html( sprintf(
            /* translators: %s: month and year */
            __( 'Cleaner since %s', 'great-lake-cleaners' ),
            date_i18n( 'F Y', strtotime( $glc_since ) )
        ) ); ?></p>
        <?php endif; ?>
    </header>

    <?php if ( empty( $glc_cleanups ) ) : ?>
    <p><?php esc_html_e( 'No cleanups published yet.', 'great-lake-cleaners' ); ?></p>
    <?php else : ?>

    <ul class="glc-profile-stats">
        <li><?php echo esc_html( sprintf( __( '%d cleanups', 'great-lake-cleaners' ), (int) $glc_stats['cleanups'] ) ); ?></li>
        <li><?php echo esc_html( sprintf( __( '%s kg removed', 'great-lake-cleaners' ), number_format( (float) $glc_stats['weight_kg'], 1 ) ) ); ?></li>
        <li><?php echo esc_html( sprintf( __( '%s volunteer hours', 'great-lake-cleaners' ), number_format( (float) $glc_stats['hours'], 1 ) ) ); ?></li>
        <li><?php echo esc_html( sprintf( __( '%d items recycled', 'great-lake-cleaners' ), (int) $glc_stats['recycled'] ) ); ?></li>
    </ul>

    <ul class="glc-profile-list">
        <?php foreach ( $glc_cleanups as $glc_p ) :
            $glc_d = glc_cleanup_field( $glc_p, 'cleanup_date' ); ?>
        <li>
            <a href="<?php echo esc_url( get_permalink( $glc_p->ID ) ); ?>">
                <?php echo esc_html( glc_cleanup_field( $glc_p, 'site_name' ) ); ?>
            </a>
            <?php if ( $glc_d && strtotime( $glc_d ) ) : ?>
            <span><?php echo esc_html( date_i18n( 'F j, Y', strtotime( $glc_d ) ) ); ?></span>
            <?php endif; ?>
        </li>
        <?php endforeach; ?>
    </ul>

    <?php endif; ?>

</div>

<?php get_footer(); ?>
