<?php
/**
 * Great Lake Cleaners — header.php
 *
 * Used by: all page templates (index, archive, single, page, front-page)
 * Outputs: <head>, site header, nav bar, and opening <main> tag.
 * Close with footer.php which outputs </main> and </body></html>.
 */
?><!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
    <meta charset="<?php bloginfo( 'charset' ); ?>">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <?php
    // ── Meta description ─────────────────────────────────────────────────────
    // Build a contextual description for each page type.
    // Falls back to the site tagline for anything not specifically handled.
    $glc_meta_desc = '';

    if ( is_singular( 'cleanup_event' ) ) {
        // Single cleanup event — summarise key stats
        $glc_id      = get_the_ID();
        $glc_site    = get_post_meta( $glc_id, 'site_name',  true ) ?: get_the_title();
        $glc_date    = get_post_meta( $glc_id, 'cleanup_date', true );
        $glc_bags    = (int) get_post_meta( $glc_id, 'bags',      true );
        $glc_weight  = (float) get_post_meta( $glc_id, 'weight_kg', true );
        $glc_notable = get_post_meta( $glc_id, 'notable_finds', true );
        $glc_dfmt    = $glc_date ? date( 'F j, Y', strtotime( $glc_date ) ) : '';

        if ( $glc_dfmt && $glc_bags ) {
            $glc_meta_desc = sprintf(
                'Cleanup at %s on %s — %d %s collected, %.0f kg of debris removed from Guelph\'s waterways.',
                $glc_site, $glc_dfmt, $glc_bags, 1 === $glc_bags ? 'bag' : 'bags', $glc_weight
            );
        } elseif ( $glc_notable ) {
            $glc_meta_desc = sprintf( 'Cleanup at %s. Notable finds: %s', $glc_site, $glc_notable );
        } else {
            $glc_meta_desc = sprintf( 'Cleanup at %s — logged by Great Lake Cleaners, Guelph, Ontario.', $glc_site );
        }

    } elseif ( is_singular( 'glc_submission' ) ) {
        // Community submission
        $glc_id   = get_the_ID();
        $glc_site = get_post_meta( $glc_id, 'glc_site_name', true )
                  ?: get_post_meta( $glc_id, 'glc_waterway', true )
                  ?: get_the_title();
        $glc_name = get_post_meta( $glc_id, 'glc_submitter_name', true );
        $glc_meta_desc = $glc_name
            ? sprintf( 'Community cleanup at %s, submitted by %s. Logged by Great Lake Cleaners, Guelph, Ontario.', $glc_site, $glc_name )
            : sprintf( 'Community cleanup at %s — logged by Great Lake Cleaners, Guelph, Ontario.', $glc_site );

    } elseif ( is_post_type_archive( 'cleanup_event' ) ) {
        $glc_meta_desc = 'Every Great Lake Cleaners outing logged — shore and paddle cleanups along the Speed River, Eramosa River, and Hanlon Creek in Guelph, Ontario.';

    } elseif ( is_front_page() ) {
        $glc_meta_desc = get_bloginfo( 'description' )
            ?: 'Great Lake Cleaners — cleaning Guelph\'s rivers and shores by foot and paddle. What enters our waterways reaches the Great Lakes.';

    } elseif ( is_page() ) {
        // Standard page — use manual excerpt if set, otherwise tagline
        $glc_excerpt   = get_the_excerpt();
        $glc_meta_desc = $glc_excerpt ?: get_bloginfo( 'description' );

    } else {
        $glc_meta_desc = get_bloginfo( 'description' );
    }

    if ( $glc_meta_desc ) :
        $glc_meta_desc = wp_strip_all_tags( $glc_meta_desc );
        // Trim to ~160 chars without cutting mid-word
        if ( strlen( $glc_meta_desc ) > 160 ) {
            $glc_meta_desc = substr( $glc_meta_desc, 0, 157 );
            $glc_meta_desc = substr( $glc_meta_desc, 0, strrpos( $glc_meta_desc, ' ' ) ) . '…';
        }
    ?>
    <meta name="description" content="<?php echo esc_attr( $glc_meta_desc ); ?>">
    <?php endif; ?>
    <?php
    // ── JSON-LD: Organization (site-wide) ────────────────────────────────────
    $glc_jsonld_org = [
        '@context'   => 'https://schema.org',
        '@type'      => 'NGO',
        'name'       => get_bloginfo( 'name' ),
        'url'        => home_url( '/' ),
        'logo'       => get_stylesheet_directory_uri() . '/assets/images/glc-badge.png',
        'description' => 'Regular cleanups of Guelph\'s local waterways — by foot and paddle — that flow into the Great Lakes system via the Grand River and Lake Erie.',
        'email'      => 'info@greatlakecleaners.ca',
        'areaServed' => [ '@type' => 'City', 'name' => 'Guelph', 'containedInPlace' => [ '@type' => 'Province', 'name' => 'Ontario' ] ],
        'sameAs'     => [ 'https://www.instagram.com/greatlakecleaners' ],
    ];
    echo '<script type="application/ld+json">' . wp_json_encode( $glc_jsonld_org, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ) . "</script>\n";

    // ── JSON-LD: Event (cleanup_event single pages) ───────────────────────────
    if ( is_singular( 'cleanup_event' ) ) {
        $glc_jid  = get_the_ID();
        $glc_jdat = get_post_meta( $glc_jid, 'cleanup_date', true );
        $glc_jsit = get_post_meta( $glc_jid, 'site_name',    true ) ?: get_the_title( $glc_jid );
        $glc_jlat = get_post_meta( $glc_jid, 'gps_lat',      true );
        $glc_jlon = get_post_meta( $glc_jid, 'gps_lon',      true );

        $glc_jsonld_event = [
            '@context'            => 'https://schema.org',
            '@type'               => 'Event',
            'name'                => get_the_title( $glc_jid ),
            'description'         => $glc_meta_desc ?: '',
            'startDate'           => $glc_jdat,
            'endDate'             => $glc_jdat,
            'eventStatus'         => 'https://schema.org/EventScheduled',
            'eventAttendanceMode' => 'https://schema.org/OfflineEventAttendanceMode',
            'url'                 => get_permalink( $glc_jid ),
            'organizer'           => [ '@type' => 'NGO', 'name' => 'Great Lake Cleaners', 'url' => home_url( '/' ) ],
            'location'            => [ '@type' => 'Place', 'name' => $glc_jsit ],
        ];
        if ( $glc_jlat && $glc_jlon ) {
            $glc_jsonld_event['location']['geo'] = [
                '@type'     => 'GeoCoordinates',
                'latitude'  => (float) $glc_jlat,
                'longitude' => (float) $glc_jlon,
            ];
        }
        echo '<script type="application/ld+json">' . wp_json_encode( $glc_jsonld_event, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ) . "</script>\n";
    }
    ?>
    <?php wp_head(); ?>
</head>

<body <?php body_class(); ?>>
<?php wp_body_open(); ?>

<a class="glc-skip-link screen-reader-text" href="#main-content">
    <?php esc_html_e( 'Skip to content', 'great-lake-cleaners' ); ?>
</a>

<!-- ═══════════════════════════════════════════════════════
     SITE HEADER
════════════════════════════════════════════════════════ -->
<header id="glc-site-header" class="glc-site-header" role="banner">

    <div class="glc-header-top">
        <div class="glc-header-inner">

            <!-- Badge / Logo -->
            <div class="glc-logo-wrap">
                <a href="<?php echo esc_url( home_url( '/' ) ); ?>" rel="home" aria-label="<?php bloginfo( 'name' ); ?> — Home">
                    <?php
                    $logo_id = get_theme_mod( 'custom_logo' );
                    if ( $logo_id ) :
                        $logo_url = wp_get_attachment_image_url( $logo_id, 'full' );
                        ?>
                        <img src="<?php echo esc_url( $logo_url ); ?>"
                             alt="<?php bloginfo( 'name' ); ?>"
                             class="glc-badge-img">
                    <?php else : ?>
                        <img src="<?php echo esc_url( get_stylesheet_directory_uri() . '/assets/images/glc-badge.png' ); ?>"
                             alt="<?php bloginfo( 'name' ); ?>"
                             class="glc-badge-img">
                    <?php endif; ?>
                </a>
            </div>

            <!-- Brand text -->
            <div class="glc-brand-text">
                <a href="<?php echo esc_url( home_url( '/' ) ); ?>" class="glc-brand-name" rel="home">
                    <?php esc_html_e( 'Great Lake ', 'great-lake-cleaners' ); ?>
                    <span><?php esc_html_e( 'Cleaners', 'great-lake-cleaners' ); ?></span>
                </a>
                <p class="glc-brand-tag">
                    <?php bloginfo( 'description' ); ?>
                </p>
            </div>

            <!-- Header actions -->
            <div class="glc-header-actions">
                <a href="https://instagram.com/greatlakecleaners"
                   class="glc-insta-link"
                   target="_blank"
                   rel="noopener noreferrer"
                   aria-label="<?php esc_attr_e( 'Follow us on Instagram (opens in new tab)', 'great-lake-cleaners' ); ?>">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none"
                         stroke="currentColor" stroke-width="1.8" stroke-linecap="round"
                         aria-hidden="true" focusable="false">
                        <rect x="2" y="2" width="20" height="20" rx="5"/>
                        <circle cx="12" cy="12" r="5"/>
                        <circle cx="17.5" cy="6.5" r="1.5" fill="currentColor" stroke="none"/>
                    </svg>
                </a>

                <?php
                // "Submit a Cleanup" — links to a page with slug 'submit-cleanup'.
                $support_page = get_page_by_path( 'submit-cleanup' );
                $support_url  = $support_page ? get_permalink( $support_page ) : '#';
                ?>
                <a href="<?php echo esc_url( $support_url ); ?>" class="glc-submit-btn">
                    <?php esc_html_e( 'Submit a Cleanup', 'great-lake-cleaners' ); ?>
                </a>
            </div>

            <!-- Mobile menu toggle -->
            <button class="glc-menu-toggle"
                    aria-controls="glc-primary-nav"
                    aria-expanded="false"
                    aria-label="<?php esc_attr_e( 'Toggle navigation menu', 'great-lake-cleaners' ); ?>">
                <span class="glc-hamburger" aria-hidden="true">
                    <span></span><span></span><span></span>
                </span>
            </button>

        </div><!-- .glc-header-inner -->
    </div><!-- .glc-header-top -->

    <!-- Navigation bar -->
    <nav id="glc-primary-nav" class="glc-nav-bar" role="navigation"
         aria-label="<?php esc_attr_e( 'Primary navigation', 'great-lake-cleaners' ); ?>">
        <div class="glc-nav-inner">
            <?php
            wp_nav_menu( [
                'theme_location' => 'primary',
                'menu_id'        => 'glc-primary-menu',
                'menu_class'     => 'glc-nav-menu',
                'container'      => false,
                'fallback_cb'    => 'glc_nav_fallback',
            ] );
            ?>
        </div>

</header><!-- #glc-site-header -->

<!-- ═══════════════════════════════════════════════════════
     HERO (front page only)
     On the front page template, this outputs the hero section
     and stats strip. On interior pages, the template handles
     its own page title / breadcrumb area instead.
════════════════════════════════════════════════════════ -->

<!-- ═══════════════════════════════════════════════════════
     MAIN CONTENT AREA OPENS HERE
     Closed in footer.php with </main>
════════════════════════════════════════════════════════ -->
<div class="glc-main-outer">
<main id="main-content" class="glc-main">
