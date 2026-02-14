<?php
/**
 * Plugin Name: Script Report
 * Description: Audit and visualize JS/CSS script dependencies. Add ?script_report=true to any admin or frontend URL (when allowed) to see the dependency report.
 * Version: 1.0.0
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
define( 'SCRIPT_REPORT_VERSION', '1.0.0' );

require_once __DIR__ . '/includes/class-script-report.php';

new Script_Report();
