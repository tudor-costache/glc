<?php
/**
 * Great Lake Cleaners — page-privacy-policy.php
 *
 * Auto-loaded by WordPress for any page with the slug 'privacy-policy'.
 * Content is baked into this template so it doesn't require manual
 * entry in the WordPress editor. Create a blank page with the slug
 * 'privacy-policy' and this template will handle the rest.
 */

get_header();

$site_name = get_bloginfo( 'name' );
$site_url  = home_url( '/' );
$contact   = 'info@greatlakecleaners.ca';
?>

<div class="glc-fp-wrapper">
<div class="glc-page-wrap glc-privacy-wrap">

    <header class="glc-page-header">
        <span class="glc-fp-label"><?php esc_html_e( 'Legal', 'great-lake-cleaners' ); ?></span>
        <h1 class="glc-page-h1"><?php esc_html_e( 'Privacy Policy', 'great-lake-cleaners' ); ?></h1>
        <p class="glc-privacy-updated">
            <?php esc_html_e( 'Last updated: ', 'great-lake-cleaners' ); ?>
            <time datetime="2025-04-01">April 1, 2025</time>
        </p>
    </header>

    <div class="glc-page-body glc-privacy-body">

        <p>
            Great Lake Cleaners (<a href="<?php echo esc_url( $site_url ); ?>">greatlakecleaners.ca</a>)
            is a Guelph, Ontario-based waterway cleanup initiative. This page explains what
            information we collect, why we collect it, and how we handle it. We collect very
            little — and we do not sell, rent, or share any of it.
        </p>

        <h2>What we collect and why</h2>

        <h3>Community cleanup submissions</h3>
        <p>
            When you submit a cleanup report through our website, we collect the following:
        </p>
        <ul>
            <li><strong>Your name</strong> — so we can credit you on the published cleanup record and thank you personally.</li>
            <li><strong>Your email address</strong> (optional) — only used to follow up with a thank-you or to ask a question about your submission. We do not send marketing email or add you to any mailing list.</li>
            <li><strong>Cleanup details</strong> — date, waterway, debris collected, and any notes you provide. This information is published publicly on this site as part of our cleanup record.</li>
            <li><strong>Location coordinates</strong> (optional) — the GPS coordinates of the cleanup site, which is a public waterway access point, not your personal location. These coordinates are used to place a pin on our cleanup map.</li>
            <li><strong>Photos</strong> (optional) — only published if you explicitly check the consent box in the submission form.</li>
        </ul>

        <h3>Server logs</h3>
        <p>
            Like all websites, our server automatically records basic access logs when you visit —
            including your IP address, browser type, and the pages you requested. These logs are
            used solely for diagnosing technical problems and are not linked to any personal profile.
            They are retained for a short period and then discarded.
        </p>

        <h2>What we do not collect</h2>
        <ul>
            <li>We do not use Google Analytics or any third-party tracking or analytics service.</li>
            <li>We do not use advertising cookies or tracking pixels.</li>
            <li>We do not collect payment information of any kind.</li>
        </ul>

        <h2>How we store your information</h2>
        <p>
            Submission data is stored in our WordPress database on a Canadian server. Email addresses
            submitted through the form are stored only in that database. We do not export or transfer
            personal information to third-party services.
        </p>

        <h2>Your rights under CASL and PIPEDA</h2>
        <p>
            You have the right to request access to the personal information we hold about you,
            ask us to correct it, or ask us to delete it. To do any of these things, contact us
            at the address below. We will respond within 30 days.
        </p>
        <p>
            We do not send commercial electronic messages. If that ever changes, we will obtain
            your explicit consent first.
        </p>

        <h2>Third-party services</h2>
        <p>
            Our cleanup map uses <a href="https://www.openstreetmap.org/" target="_blank" rel="noopener noreferrer">OpenStreetMap</a>
            data via <a href="https://carto.com/" target="_blank" rel="noopener noreferrer">CARTO</a> map tiles,
            and the <a href="https://leafletjs.com/" target="_blank" rel="noopener noreferrer">Leaflet</a> JavaScript library.
            These services may log tile requests from your browser. Their own privacy policies apply.
            No personal information about you is sent to these services.
        </p>
        <p>
            Our Instagram link points to
            <a href="https://www.instagram.com/greatlakecleaners" target="_blank" rel="noopener noreferrer">@greatlakecleaners</a>
            on Instagram. Instagram is operated by Meta Platforms, Inc. Visiting Instagram is subject
            to Meta's privacy policy.
        </p>

        <h2>Children</h2>
        <p>
            Our site is not directed at children under 13 and we do not knowingly collect personal
            information from children. Cleanup submissions from minors should be submitted by a parent
            or guardian.
        </p>

        <h2>Changes to this policy</h2>
        <p>
            If we make material changes to this policy, we will update the "Last updated" date at
            the top of this page. We encourage you to review it periodically.
        </p>

        <h2>Contact</h2>
        <p>
            Questions about this policy or your personal information:<br>
            <strong>Great Lake Cleaners</strong><br>
            Guelph, Ontario, Canada<br>
            <a href="mailto:<?php echo esc_attr( $contact ); ?>"><?php echo esc_html( $contact ); ?></a>
        </p>

    </div><!-- .glc-privacy-body -->

</div>
</div>

<?php get_footer(); ?>
