<?php
/**
 * Plugin Name: Great Lake Cleaners
 * Plugin URI:  https://greatlakecleaners.ca
 * Description: Cleanup event log, public archive, cumulative stats, and site map for Great Lake Cleaners. No external plugin dependencies.
 * Version:     1.5.1
 * Author:      Great Lake Cleaners
 * License:     GPL-2.0+
 */

defined( 'ABSPATH' ) || exit;

define( 'GLC_VERSION',    '1.5.1' );
define( 'GLC_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'GLC_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

require_once GLC_PLUGIN_DIR . 'includes/security.php';  // shared input guards — load first, the rest depend on it
require_once GLC_PLUGIN_DIR . 'includes/post-type.php';
require_once GLC_PLUGIN_DIR . 'includes/acf-fields.php'; // native meta box — no ACF needed
require_once GLC_PLUGIN_DIR . 'includes/shortcodes.php';
require_once GLC_PLUGIN_DIR . 'includes/import.php';
require_once GLC_PLUGIN_DIR . 'includes/admin.php';
require_once GLC_PLUGIN_DIR . 'includes/submission.php';
require_once GLC_PLUGIN_DIR . 'includes/report.php';
require_once GLC_PLUGIN_DIR . 'includes/crew-signup.php';
require_once GLC_PLUGIN_DIR . 'includes/events.php';
require_once GLC_PLUGIN_DIR . 'includes/accounts.php';  // community accounts + public cleaner profiles

register_activation_hook( __FILE__, 'glc_activate' );
function glc_activate() {
    // Set a flag — actual flush happens on next init after all CPTs are registered
    set_transient( 'glc_flush_rewrite_rules', true, 60 );

    // Anything else that has to happen once, at activation, hangs off this.
    // (It was already being listened for in submission.php but never fired —
    // the register_activation_hook callback runs on `activate_<plugin>`, not on
    // a hook named after the callback.)
    do_action( 'glc_activate' );
}

register_deactivation_hook( __FILE__, function() {
    flush_rewrite_rules();
} );

// Flush rewrite rules once after activation, at priority 99 so all CPTs are registered first
add_action( 'init', function() {
    if ( get_transient( 'glc_flush_rewrite_rules' ) ) {
        delete_transient( 'glc_flush_rewrite_rules' );
        flush_rewrite_rules();
    }
}, 99 );
