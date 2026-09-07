<?php
/**
 * Great Lake Cleaners — glc-profile.php
 *
 * Public cleaner profile: /cleaners/{slug}/
 *
 * Not part of the WordPress template hierarchy — the plugin's accounts.php
 * routes here through template_include after resolving the glc_cleaner query
 * var, and locate_template() is what finds this file. The plugin carries a
 * deliberately plain fallback (templates/profile-fallback.php) for the case
 * where it runs under another theme; the design lives here.
 *
 * By the time this file runs, accounts.php has already established that the
 * profile exists and is visible to this visitor — a miss, or a hidden profile
 * seen by anyone but its owner, 404s before template_include. It never
 * redirects and never explains itself, because either would confirm that the
 * slug exists.
 *
 * Reuses existing components rather than inventing a visual language: the
 * .glc-ih-card stat shape from [glc_impact_highlights], the archive's cleanup
 * cards and grid, and the site's wave divider.
 */

get_header();

$p_user  = glc_profile_queried_user();
$p_owner = get_current_user_id() === (int) $p_user->ID;
$p_stats = glc_user_impact_stats( $p_user->ID );

// The owner sees their own pending rows, marked and excluded from every total.
// Everyone else sees published cleanups only: the site's rule is that a pending
// submission counts for nothing, and a personal page is not the place to start
// diverging from the public numbers.
$p_published = glc_user_cleanups( $p_user->ID, [ 'publish' ] );
$p_pending   = $p_owner ? glc_user_cleanups( $p_user->ID, [ 'pending' ] ) : [];

$p_since = (string) get_user_meta( $p_user->ID, 'glc_joined_date', true );
$p_idir  = esc_url( get_template_directory_uri() ) . '/assets/images';

// Same icon-left / label+value-right card as [glc_impact_highlights], same
// icon box — not a tall icon-over-number stack.
$p_card = function ( $icon, $label, $value ) use ( $p_idir ) {
    ob_start(); ?>
    <div class="glc-ih-card">
        <span class="glc-ih-icon-box"><img src="<?php echo esc_url( $p_idir . '/' . $icon ); ?>" alt="" aria-hidden="true"></span>
        <span class="glc-ih-text">
            <span class="glc-ih-label"><?php echo esc_html( $label ); ?></span>
            <span class="glc-ih-value"><?php echo esc_html( $value ); ?></span>
        </span>
    </div>
    <?php return ob_get_clean();
};

// Hours read as minutes below 1, the same way front-page and archive cards do.
$p_hours = (float) $p_stats['hours'];
if ( $p_hours > 0 && $p_hours < 1 ) {
    $p_hours_display = round( $p_hours * 60 ) . ' min';
} else {
    $p_hours_display = ( $p_hours == floor( $p_hours ) )
        ? number_format( $p_hours )
        : number_format( $p_hours, 1 );
}
?>

<div class="glc-fp-wrapper">
<div class="glc-profile-wrap">

    <header class="glc-profile-header">
        <span class="glc-fp-label"><?php esc_html_e( 'Cleaner', 'great-lake-cleaners' ); ?></span>
        <h1 class="glc-profile-h1"><?php echo esc_html( $p_user->display_name ); ?></h1>

        <div class="glc-profile-meta">
            <?php if ( $p_since && strtotime( $p_since ) ) : ?>
            <span class="glc-profile-since">
                <?php echo esc_html( sprintf(
                    /* translators: %s: month and year, e.g. "April 2026" */
                    __( 'Cleaner since %s', 'great-lake-cleaners' ),
                    date_i18n( 'F Y', strtotime( $p_since ) )
                ) ); ?>
            </span>
            <?php endif; ?>

            <?php foreach ( $p_stats['corridor_names'] as $p_corridor ) : ?>
            <span class="glc-corridor-badge"><?php echo esc_html( $p_corridor ); ?></span>
            <?php endforeach; ?>
        </div>

        <?php if ( $p_owner ) :
            $p_account = get_page_by_path( 'account' ); ?>
        <p class="glc-profile-owner-note">
            <?php if ( ! glc_profile_is_public( $p_user->ID ) ) : ?>
            <span class="glc-community-badge"><?php esc_html_e( 'Hidden', 'great-lake-cleaners' ); ?></span>
            <?php esc_html_e( 'Only you can see this page.', 'great-lake-cleaners' ); ?>
            <?php else : ?>
            <?php esc_html_e( 'This is your public profile.', 'great-lake-cleaners' ); ?>
            <?php endif; ?>
            <?php if ( $p_account ) : ?>
            <a href="<?php echo esc_url( get_permalink( $p_account ) ); ?>"><?php esc_html_e( 'Manage your account', 'great-lake-cleaners' ); ?></a>
            <?php endif; ?>
        </p>
        <?php endif; ?>
    </header>

    <?php if ( empty( $p_published ) && empty( $p_pending ) ) : ?>

    <?php // Empty state: header and one line. No map, no stats strip. ?>
    <p class="glc-archive-empty"><?php esc_html_e( 'No cleanups published yet.', 'great-lake-cleaners' ); ?></p>

    <?php else : ?>

    <?php if ( ! empty( $p_published ) ) : ?>
    <div class="glc-profile-stats">
        <?php
        echo $p_card( 'garbage-bag.png',  __( 'Cleanups', 'great-lake-cleaners' ),       number_format( (int) $p_stats['cleanups'] ) );
        echo $p_card( 'icon-scale.svg',   __( 'Debris Removed', 'great-lake-cleaners' ), number_format( (float) $p_stats['weight_kg'], 1 ) . ' kg' );
        echo $p_card( 'icon-timer.svg',   __( 'Volunteer Hours', 'great-lake-cleaners' ), $p_hours_display );
        echo $p_card( 'icon-recycle.svg', __( 'Items Recycled', 'great-lake-cleaners' ), number_format( (int) $p_stats['recycled'] ) );
        ?>
    </div>

    <?php
    // Corridor LINES stay on for context; corridor PINS go off, for the same
    // reason the front-page hero turns them off — a gold cumulative-impact pin
    // on a personal page summarises somebody else's work. No clustering
    // either: one person's sites are few.
    ?>
    <div class="glc-profile-map">
        <?php echo do_shortcode( sprintf(
            '[glc_map author="%d" height="360px" corridors="1" corridor_pins="0"]',
            (int) $p_user->ID
        ) ); ?>
    </div>
    <?php endif; ?>

    <section class="glc-profile-cleanups">
        <h2 class="glc-impact-heading"><?php esc_html_e( 'Cleanups logged', 'great-lake-cleaners' ); ?></h2>

        <div class="glc-archive-grid">
            <?php foreach ( array_merge( $p_pending, $p_published ) as $p_post ) :
                $p_id      = $p_post->ID;
                $p_is_pend = 'publish' !== $p_post->post_status;
                $p_date    = glc_cleanup_field( $p_post, 'cleanup_date' );
                $p_bags    = (int)   glc_cleanup_field( $p_post, 'bags' );
                $p_weight  = (float) glc_cleanup_field( $p_post, 'weight_kg' );
                $p_recyc   = (int)   glc_cleanup_field( $p_post, 'items_recycled' );
                $p_hrs     = (float) glc_cleanup_field( $p_post, 'hours' );
                $p_notable = glc_cleanup_field( $p_post, 'notable_finds' );

                $p_thumb = has_post_thumbnail( $p_id )
                    ? get_the_post_thumbnail_url( $p_id, 'medium_large' )
                    : '';
                if ( ! $p_thumb ) {
                    $p_photo_ids = get_post_meta( $p_id, 'glc_photo_ids', true );
                    if ( $p_photo_ids ) {
                        $p_ids = is_array( $p_photo_ids )
                            ? $p_photo_ids
                            : array_filter( array_map( 'intval', explode( ',', $p_photo_ids ) ) );
                        if ( $p_ids ) {
                            $p_thumb = wp_get_attachment_image_url( (int) reset( $p_ids ), 'medium_large' );
                        }
                    }
                }
            ?>
            <div class="glc-fp-cleanup-card glc-fp-cleanup-card--community<?php echo $p_is_pend ? ' glc-profile-card--pending' : ''; ?>">

                <div class="glc-archive-card-thumb">
                    <?php if ( $p_thumb ) : ?>
                    <img src="<?php echo esc_url( $p_thumb ); ?>" alt="" loading="lazy">
                    <?php endif; ?>
                </div>

                <div class="glc-archive-card-body">

                    <div class="glc-archive-card-top">
                        <?php if ( $p_date && strtotime( $p_date ) ) : ?>
                        <div class="glc-fp-card-date"><?php echo esc_html( date_i18n( 'F j, Y', strtotime( $p_date ) ) ); ?></div>
                        <?php endif; ?>
                        <?php if ( $p_is_pend ) : ?>
                        <span class="glc-community-badge"><?php esc_html_e( 'Awaiting review', 'great-lake-cleaners' ); ?></span>
                        <?php endif; ?>
                    </div>

                    <?php if ( $p_is_pend ) : ?>
                    <span class="glc-fp-card-title glc-fp-card-title--plain">
                        <?php echo esc_html( glc_cleanup_field( $p_post, 'site_name' ) ); ?>
                    </span>
                    <?php else : ?>
                    <a class="glc-fp-card-title" href="<?php echo esc_url( get_permalink( $p_id ) ); ?>">
                        <?php echo esc_html( glc_cleanup_field( $p_post, 'site_name' ) ); ?>
                    </a>
                    <?php endif; ?>

                    <div class="glc-fp-card-stats">
                        <?php
                        $p_ic = function ( $icon, $val, $suffix = '' ) use ( $p_idir ) {
                            return '<span class="glc-cs"><img src="' . $p_idir . '/' . $icon
                                . '" alt="" width="18" height="18" aria-hidden="true">'
                                . esc_html( $val ) . ( $suffix ? ' ' . $suffix : '' ) . '</span>';
                        };
                        if ( $p_bags )   echo $p_ic( 'icon-bag.svg',     $p_bags,   1 === $p_bags ? 'bag' : 'bags' );
                        if ( $p_weight ) echo $p_ic( 'icon-scale.svg',   $p_weight, 'kg' );
                        if ( $p_recyc )  echo $p_ic( 'icon-recycle.svg', $p_recyc,  'items' );
                        if ( $p_hrs ) {
                            echo $p_hrs < 1
                                ? $p_ic( 'icon-timer.svg', round( $p_hrs * 60 ), 'min' )
                                : $p_ic( 'icon-timer.svg', number_format( $p_hrs, 1 ), 'h' );
                        }
                        ?>
                    </div>

                    <?php if ( $p_notable ) : ?>
                    <p class="glc-archive-card-notable"><?php echo esc_html( $p_notable ); ?></p>
                    <?php endif; ?>

                </div><!-- .glc-archive-card-body -->
            </div>
            <?php endforeach; ?>
        </div>
    </section>

    <?php endif; ?>

    <div class="glc-wave-divider" aria-hidden="true">
        <svg viewBox="0 0 1200 22" preserveAspectRatio="none" xmlns="http://www.w3.org/2000/svg">
            <path d="M0,11 C150,3 300,19 450,11 C600,3 750,19 900,11 C1050,3 1200,19 1200,11" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
            <path d="M0,16 C150,8 300,24 450,16 C600,8 750,24 900,16 C1050,8 1200,24 1200,16" fill="none" stroke="currentColor" stroke-width="1.4" stroke-linecap="round" opacity="0.45"/>
        </svg>
    </div>

    <p class="glc-profile-foot">
        <a href="<?php echo esc_url( get_post_type_archive_link( 'cleanup_event' ) ); ?>">
            <?php esc_html_e( 'See every cleanup on the site', 'great-lake-cleaners' ); ?> &rarr;
        </a>
    </p>

</div><!-- .glc-profile-wrap -->
</div><!-- .glc-fp-wrapper -->

<?php get_footer(); ?>
