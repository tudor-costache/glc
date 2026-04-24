<?php
/**
 * Great Lake Cleaners — single-glc_submission.php
 *
 * Public view of an approved community cleanup submission.
 * Layout mirrors single-cleanup_event.php — keep both in sync.
 *
 * Meta keys (glc_ prefix, set by submission.php):
 *   glc_cleanup_date, glc_waterway, glc_corridor, glc_site_name, glc_bags, glc_weight_kg,
 *   glc_cans, glc_bottles, glc_hours, glc_notable_finds, glc_instagram_url,
 *   glc_submitter_name, glc_photo_ids, glc_photo_repost_ok,
 *   glc_gps_lat, glc_gps_lon
 */

get_header();

if ( have_posts() ) :
    the_post();

    $id        = get_the_ID();
    $date      = get_post_meta( $id, 'glc_cleanup_date',    true );
    $waterway  = get_post_meta( $id, 'glc_waterway',        true );
    $corridor  = get_post_meta( $id, 'glc_corridor',        true );
    $site      = get_post_meta( $id, 'glc_site_name',       true );
    $bags      = get_post_meta( $id, 'glc_bags',            true );
    $weight    = get_post_meta( $id, 'glc_weight_kg',       true );
    $cans      = (int) get_post_meta( $id, 'glc_cans',      true );
    $bottles   = (int) get_post_meta( $id, 'glc_bottles',   true );
    $recycled  = $cans + $bottles;
    $hours     = get_post_meta( $id, 'glc_hours',           true );
    $notable   = get_post_meta( $id, 'glc_notable_finds',   true );
    $insta     = get_post_meta( $id, 'glc_instagram_url',   true );
    $name      = get_post_meta( $id, 'glc_submitter_name',  true );
    $photo_ids = get_post_meta( $id, 'glc_photo_ids',       true );
    $repost_ok = get_post_meta( $id, 'glc_photo_repost_ok', true );
    $gps_lat   = get_post_meta( $id, 'glc_gps_lat',         true );
    $gps_lon   = get_post_meta( $id, 'glc_gps_lon',         true );

    // Normalise date
    if ( $date && ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', $date ) ) {
        $ts   = strtotime( $date );
        $date = $ts ? date( 'Y-m-d', $ts ) : $date;
    }
    $display_date = $date ? date( 'F j, Y', strtotime( $date ) ) : '';

    // Title: use post title (kept in sync by the admin meta box save handler)
    $title = get_the_title();
?>

<div class="glc-fp-wrapper">
<div class="glc-single-sub-wrap">

    <a class="glc-single-sub-back" href="<?php echo esc_url( get_post_type_archive_link( 'cleanup_event' ) ); ?>">
        &larr; <?php esc_html_e( 'All Cleanups', 'great-lake-cleaners' ); ?>
    </a>

    <article class="glc-single-sub glc-single-submission">

        <header class="glc-single-sub-header">
            <div class="glc-single-sub-meta-row">
                <?php if ( $display_date ) : ?>
                <span class="glc-fp-card-date"><?php echo esc_html( $display_date ); ?></span>
                <?php endif; ?>
                <span class="glc-community-badge"><?php esc_html_e( 'Community', 'great-lake-cleaners' ); ?></span>
                <?php $badge = $corridor ?: $waterway; ?>
                <?php if ( $badge ) : ?>
                <span class="glc-corridor-badge"><?php echo esc_html( $badge ); ?></span>
                <?php endif; ?>
            </div>
            <h1 class="glc-single-sub-h1"><?php echo esc_html( $title ); ?></h1>
            <?php if ( $name ) : ?>
            <p class="glc-single-sub-byline">
                <?php printf( esc_html__( 'Submitted by %s', 'great-lake-cleaners' ), esc_html( $name ) ); ?>
            </p>
            <?php endif; ?>
        </header>

        <!-- Featured image -->
        <?php if ( has_post_thumbnail() ) : ?>
        <div class="glc-single-event-thumb">
            <?php the_post_thumbnail( 'large', [ 'class' => 'glc-single-event-img' ] ); ?>
        </div>
        <?php endif; ?>

        <!-- Blog body -->
        <?php
        $body = get_the_content();
        if ( $body ) : ?>
        <div class="glc-single-body">
            <?php echo wp_kses_post( apply_filters( 'the_content', $body ) ); ?>
        </div>
        <?php endif; ?>

        <!-- Stat tiles -->
        <?php $has_stats = $bags || $weight || $recycled || $hours;
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

            <?php if ( $recycled > 0 ) : ?>
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

        </div>
        <?php endif; ?>

        <!-- Submitted photos (consent required) -->
        <?php if ( ! empty( $photo_ids ) && $repost_ok === '1' ) : ?>
        <div class="glc-single-sub-photos">
            <?php foreach ( (array) $photo_ids as $att_id ) :
                $img  = wp_get_attachment_image( $att_id, 'large', false, [ 'class' => 'glc-single-sub-photo', 'loading' => 'lazy', 'style' => 'cursor:zoom-in;' ] );
                $full = wp_get_attachment_url( $att_id );
                if ( $img && $full ) : ?>
            <a href="<?php echo esc_url( $full ); ?>"
               class="glc-sub-photo-trigger"
               data-src="<?php echo esc_attr( $full ); ?>"
               onclick="glcSubLbOpen(this);return false;"
               aria-label="<?php esc_attr_e( 'View full size photo', 'great-lake-cleaners' ); ?>">
                <?php echo $img; ?>
            </a>
            <?php endif; endforeach; ?>
        </div>

        <div id="glc-sub-lb" role="dialog" aria-modal="true" aria-label="<?php esc_attr_e( 'Photo lightbox', 'great-lake-cleaners' ); ?>"
             style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.88);z-index:9999;
                    align-items:center;justify-content:center;cursor:zoom-out;"
             onclick="glcSubLbClose()">
            <img id="glc-sub-lb-img" src="" alt=""
                 style="max-width:92vw;max-height:92vh;border-radius:6px;
                        box-shadow:0 4px 40px rgba(0,0,0,.7);display:block;">
        </div>
        <script>
        (function(){
            function glcSubLbOpen(a) {
                var lb = document.getElementById('glc-sub-lb');
                document.getElementById('glc-sub-lb-img').src = a.dataset.src;
                lb.style.display = 'flex';
                document.body.style.overflow = 'hidden';
            }
            function glcSubLbClose() {
                document.getElementById('glc-sub-lb').style.display = 'none';
                document.getElementById('glc-sub-lb-img').src = '';
                document.body.style.overflow = '';
            }
            window.glcSubLbOpen  = glcSubLbOpen;
            window.glcSubLbClose = glcSubLbClose;
            document.addEventListener('keydown', function(e){
                if (e.key === 'Escape') glcSubLbClose();
            });
        })();
        </script>
        <?php endif; ?>

        <!-- Notable finds -->
        <?php if ( $notable ) : ?>
        <div class="glc-single-sub-notable">
            <h2><?php esc_html_e( 'Notable Finds', 'great-lake-cleaners' ); ?></h2>
            <p><?php echo esc_html( $notable ); ?></p>
        </div>
        <?php endif; ?>

        <!-- Instagram field log -->
        <?php if ( $insta ) : ?>
        <div class="glc-single-sub-insta">
            <a href="<?php echo esc_url( $insta ); ?>"
               target="_blank" rel="noopener noreferrer"
               class="glc-btn-outline">
                <?php esc_html_e( 'View Field Log on Instagram &rarr;', 'great-lake-cleaners' ); ?>
            </a>
        </div>
        <?php endif; ?>

        <!-- Location map -->
        <?php if ( $gps_lat && $gps_lon ) : ?>
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
