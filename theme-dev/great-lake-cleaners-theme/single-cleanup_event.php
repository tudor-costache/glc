<?php
/**
 * Great Lake Cleaners — single-cleanup_event.php
 *
 * Single view for cleanup_event posts imported from the tracker CSV.
 * Mirrors the community submission design (single-glc_submission.php)
 * but reads from the tracker meta keys (no glc_ prefix).
 *
 * Meta keys written by the importer (import.php) and meta box (acf-fields.php):
 *   cleanup_date, site_name, corridor, bags, weight_kg, hours, items_recycled,
 *   notable_finds, wildlife_obs, instagram_url, volunteers,
 *   gps_lat, gps_lon, species_planted, meters_bank_cleared
 */

get_header();

if ( have_posts() ) :
    the_post();

    $id        = get_the_ID();
    $date      = get_post_meta( $id, 'cleanup_date',        true );
    $site      = get_post_meta( $id, 'site_name',           true );
    $bags      = get_post_meta( $id, 'bags',                true );
    $weight    = get_post_meta( $id, 'weight_kg',           true );
    $hours     = get_post_meta( $id, 'hours',               true );
    $recycled  = get_post_meta( $id, 'items_recycled',      true );
    $notable   = get_post_meta( $id, 'notable_finds',       true );
    $wildlife  = get_post_meta( $id, 'wildlife_obs',        true );
    $insta     = get_post_meta( $id, 'instagram_url',       true );
    $volunteers= get_post_meta( $id, 'volunteers',          true );
    $planted   = get_post_meta( $id, 'species_planted',     true );
    $bank      = get_post_meta( $id, 'meters_bank_cleared', true );
    $notes     = get_the_content(); // from post_content (tracker 'notes' column)

    // Normalise date: accept YYYY-MM-DD or legacy display formats
    if ( $date && ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', $date ) ) {
        $ts   = strtotime( $date );
        $date = $ts ? date( 'Y-m-d', $ts ) : $date;
    }
    $display_date = $date ? date( 'F j, Y', strtotime( $date ) ) : '';

    $title = get_the_title();

    // Corridor badge — use the explicit meta field; fall back to inferring from
    // site name for older posts that predate the corridor field.
    $corridor = trim( (string) get_post_meta( $id, 'corridor', true ) );
    if ( $corridor === '' ) {
        $known = [ 'Speed River', 'Eramosa River', 'Hanlon Creek', 'Guelph Lake', 'Grand River' ];
        foreach ( $known as $k ) {
            if ( stripos( $site, $k ) !== false || stripos( $title, $k ) !== false ) {
                $corridor = $k;
                break;
            }
        }
    }
?>

<div class="glc-fp-wrapper">
<div class="glc-single-sub-wrap">

    <a class="glc-single-sub-back" href="<?php echo esc_url( get_post_type_archive_link( 'cleanup_event' ) ); ?>">
        ← <?php esc_html_e( 'All Cleanups', 'great-lake-cleaners' ); ?>
    </a>

    <article class="glc-single-sub glc-single-event">

        <header class="glc-single-sub-header">
            <div class="glc-single-sub-meta-row">
                <?php if ( $display_date ) : ?>
                <span class="glc-fp-card-date"><?php echo esc_html( $display_date ); ?></span>
                <?php endif; ?>
                <?php if ( $corridor ) : ?>
                <span class="glc-corridor-badge"><?php echo esc_html( $corridor ); ?></span>
                <?php endif; ?>
            </div>
            <h1 class="glc-single-sub-h1"><?php echo esc_html( $title ); ?></h1>

        </header>

        <!-- ── Blog body + featured image (free prose, no box) ─────────────── -->
        <?php if ( has_post_thumbnail() ) : ?>
        <div class="glc-single-event-thumb">
            <?php the_post_thumbnail( 'large', [ 'class' => 'glc-single-event-img' ] ); ?>
        </div>
        <?php endif; ?>

        <?php if ( $notes ) : ?>
        <div class="glc-single-body">
            <?php echo wp_kses_post( $notes ); ?>
        </div>
        <?php endif; ?>

        <!-- ── Stat tiles ─────────────────────────────────────────────────── -->
        <?php
        $has_stats = $bags || $weight || $recycled || $hours;
        if ( $has_stats ) : ?>
        <div class="glc-single-sub-stats">

            <?php if ( $bags ) : ?>
            <div class="glc-sub-stat">
                <span class="glc-sub-stat-icon"><img src="<?php echo esc_url( get_template_directory_uri() ); ?>/assets/images/icon-bag.svg" alt="" width="20" height="20" style="vertical-align:-0.2em;flex-shrink:0;width:1.3em;height:1.3em" aria-hidden="true"></span>
                <span class="glc-sub-stat-val"><?php echo esc_html( $bags ); ?></span>
                <span class="glc-sub-stat-lbl"><?php esc_html_e( 'Bags', 'great-lake-cleaners' ); ?></span>
            </div>
            <?php endif; ?>

            <?php if ( $weight ) : ?>
            <div class="glc-sub-stat">
                <span class="glc-sub-stat-icon"><img src="<?php echo esc_url( get_template_directory_uri() ); ?>/assets/images/icon-scale.svg" alt="" width="20" height="20" style="vertical-align:-0.2em;flex-shrink:0;width:1.3em;height:1.3em" aria-hidden="true"></span>
                <span class="glc-sub-stat-val"><?php echo esc_html( $weight ); ?><small> kg</small></span>
                <span class="glc-sub-stat-lbl"><?php esc_html_e( 'Debris', 'great-lake-cleaners' ); ?></span>
            </div>
            <?php endif; ?>

            <?php if ( $recycled && (int) $recycled > 0 ) : ?>
            <div class="glc-sub-stat">
                <span class="glc-sub-stat-icon"><img src="<?php echo esc_url( get_template_directory_uri() ); ?>/assets/images/icon-recycle.svg" alt="" width="20" height="20" style="vertical-align:-0.2em;flex-shrink:0;width:1.3em;height:1.3em" aria-hidden="true"></span>
                <span class="glc-sub-stat-val"><?php echo esc_html( $recycled ); ?></span>
                <span class="glc-sub-stat-lbl"><?php esc_html_e( 'Items Recycled', 'great-lake-cleaners' ); ?></span>
            </div>
            <?php endif; ?>

            <?php if ( $hours ) : ?>
            <div class="glc-sub-stat">
                <span class="glc-sub-stat-icon"><img src="<?php echo esc_url( get_template_directory_uri() ); ?>/assets/images/icon-timer.svg" alt="" width="20" height="20" style="vertical-align:-0.2em;flex-shrink:0;width:1.3em;height:1.3em" aria-hidden="true"></span>
                <span class="glc-sub-stat-val"><?php echo esc_html( $hours ); ?></span>
                <span class="glc-sub-stat-lbl"><?php esc_html_e( 'Hrs', 'great-lake-cleaners' ); ?></span>
            </div>
            <?php endif; ?>

            <?php if ( $bank ) :
                // Display in km if ≥ 1000 m, otherwise in m
                $bank_val = (float) $bank;
                if ( $bank_val >= 1000 ) {
                    $bank_display = rtrim( rtrim( number_format( $bank_val / 1000, 2 ), '0' ), '.' );
                    $bank_unit    = 'km';
                } else {
                    $bank_display = number_format( $bank_val, 0 );
                    $bank_unit    = 'm';
                }
            ?>
            <div class="glc-sub-stat">
                <span class="glc-sub-stat-icon"><img src="<?php echo esc_url( get_template_directory_uri() ); ?>/assets/images/icon-bank.svg" alt="" width="20" height="20" style="vertical-align:-0.2em;flex-shrink:0;width:1.3em;height:1.3em" aria-hidden="true"></span>
                <span class="glc-sub-stat-val"><?php echo esc_html( $bank_display ); ?><small> <?php echo esc_html( $bank_unit ); ?></small></span>
                <span class="glc-sub-stat-lbl"><?php esc_html_e( 'Bank Cleared', 'great-lake-cleaners' ); ?></span>
            </div>
            <?php endif; ?>

        </div>
        <?php endif; ?>

        <!-- ── Notable finds ─────────────────────────────────────────────── -->
        <?php if ( $notable ) : ?>
        <div class="glc-single-sub-notable">
            <h2><?php esc_html_e( 'Notable Finds', 'great-lake-cleaners' ); ?></h2>
            <p><?php echo esc_html( $notable ); ?></p>
        </div>
        <?php endif; ?>

        <!-- ── Wildlife observed ─────────────────────────────────────────── -->
        <?php if ( $wildlife ) : ?>
        <div class="glc-single-sub-notable glc-single-event-wildlife">
            <h2><?php esc_html_e( 'Wildlife Observed', 'great-lake-cleaners' ); ?></h2>
            <p><?php echo esc_html( $wildlife ); ?></p>
        </div>
        <?php endif; ?>

        <!-- ── Restoration extras (planted only — bank is now a stat tile) ── -->
        <?php if ( $planted ) : ?>
        <div class="glc-single-event-restoration">
            <div class="glc-event-extra">
                <span class="glc-event-extra-icon" aria-hidden="true">🌱</span>
                <span><?php printf(
                    esc_html__( '%s native species planted', 'great-lake-cleaners' ),
                    '<strong>' . esc_html( $planted ) . '</strong>'
                ); ?></span>
            </div>
        </div>
        <?php endif; ?>

        <!-- ── Instagram field log ───────────────────────────────────────── -->
        <?php if ( $insta ) : ?>
        <div class="glc-single-sub-insta">
            <a href="<?php echo esc_url( $insta ); ?>"
               target="_blank" rel="noopener noreferrer"
               class="glc-btn-outline">
                <?php esc_html_e( 'View Field Log on Instagram →', 'great-lake-cleaners' ); ?><span class="screen-reader-text"> (opens in new tab)</span>
            </a>
        </div>
        <?php endif; ?>

        <!-- ── Location map ──────────────────────────────────────────────── -->
        <?php
        $gps_lat = get_post_meta( $id, 'gps_lat', true );
        $gps_lon = get_post_meta( $id, 'gps_lon', true );
        if ( $gps_lat && $gps_lon ) :
        ?>
        <div class="glc-single-event-map">
            <h2><?php esc_html_e( 'Cleanup Location', 'great-lake-cleaners' ); ?></h2>
            <?php echo do_shortcode( '[glc_map height="320px" post_id="' . $id . '"]' ); ?>
        </div>
        <?php endif; ?>

    </article>

</div>
</div>

<?php endif; ?>
<?php get_footer(); ?>
