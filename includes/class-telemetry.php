<?php
// DESCRIPTION: Initializes and configures the wp-telemetry library for Script Report.
// DESCRIPTION: Handles deactivation feedback collection via the Feedio API.

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use ScriptReport\Vendor\BitApps\WPTelemetry\Telemetry\Telemetry;
use ScriptReport\Vendor\BitApps\WPTelemetry\Telemetry\TelemetryConfig;

/**
 * Manages telemetry configuration and deactivation feedback for Script Report.
 *
 * Configures the bitapps/wp-telemetry library to show a feedback modal when
 * the plugin is deactivated and sends the response to the Feedio API.
 *
 * @since 1.2.0
 */
class Script_Report_Telemetry {

	/**
	 * Initialize telemetry.
	 *
	 * Configures the wp-telemetry library with plugin-specific settings and
	 * initializes the deactivation feedback survey.
	 *
	 * @since 1.2.0
	 *
	 * @return void
	 */
	public static function init() {
		if ( ! class_exists( 'ScriptReport\\Vendor\\BitApps\\WPTelemetry\\Telemetry\\Telemetry' ) ) {
			return;
		}

		TelemetryConfig::setTitle( __( 'Script Report', 'script-report' ) );
		TelemetryConfig::setSlug( 'script-report' );
		TelemetryConfig::setPrefix( 'script_report_' );
		TelemetryConfig::setVersion( SCRIPT_REPORT_VERSION );
		TelemetryConfig::setServerBaseUrl( 'https://feedio.sapayth.com/api/' );
		TelemetryConfig::setPolicyUrl( 'https://feedio.sapayth.com/policy' );

		add_filter( 'script_report_deactivate_reasons', [ __CLASS__, 'remove_pro_reason' ] );

		if ( self::is_past_activation_grace_period() ) {
			Telemetry::report()->init();
		}

		Telemetry::feedback()->init();
	}

	/**
	 * Check if 7 days have passed since plugin activation.
	 *
	 * @since 1.2.0
	 *
	 * @return bool True if 7 days have elapsed since activation.
	 */
	public static function is_past_activation_grace_period() {
		$activated_at = get_option( 'script_report_activated_at' );

		if ( ! $activated_at ) {
			return false;
		}

		return ( time() - $activated_at ) >= 7 * DAY_IN_SECONDS;
	}

	/**
	 * Remove the "I have Script Report Pro" deactivation reason.
	 *
	 * Script Report does not have a Pro version, so this option is not relevant.
	 *
	 * @since 1.2.0
	 *
	 * @param array $reasons Deactivation reasons array.
	 *
	 * @return array Reasons array with the pro option removed.
	 */
	public static function remove_pro_reason( $reasons ) {
		unset( $reasons['script_report_pro'] );
		return $reasons;
	}

}
