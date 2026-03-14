<?php
/**
 * Plugin Name: Script Report
 * Description: Audit and visualize JS/CSS script dependencies. Use the admin bar "Script Report" menu to see the dependency report.
 * Version: 1.1.0
 * Requires at least: 5.0
 * Requires PHP: 7.4
 * Author: Sapayth H.
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: script-report
 *
 * @package Script_Report
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'SCRIPT_REPORT_FILE', __FILE__ );
define( 'SCRIPT_REPORT_PATH', plugin_dir_path( SCRIPT_REPORT_FILE ) );
define( 'SCRIPT_REPORT_VERSION', '1.1.0' );

if ( file_exists( __DIR__ . '/vendor/autoload.php' ) ) {
	require_once __DIR__ . '/vendor/autoload.php';
}

if ( file_exists( __DIR__ . '/lib/autoload.php' ) ) {
	require_once __DIR__ . '/lib/autoload.php';
}

require_once __DIR__ . '/includes/class-script-report.php';
require_once __DIR__ . '/includes/class-telemetry.php';

register_activation_hook( SCRIPT_REPORT_FILE, function () {
	$user_id = get_current_user_id();
	if ( $user_id ) {
		update_user_meta( $user_id, 'script_report_highlight', '1' );
	}

	if ( ! get_option( 'script_report_activated_at' ) ) {
		update_option( 'script_report_activated_at', time() );
	}
} );

register_deactivation_hook( SCRIPT_REPORT_FILE, function () {
	do_action( 'script_report_deactivate' );
} );

add_action( 'init', [ 'Script_Report_Telemetry', 'init' ] );

new Script_Report();
