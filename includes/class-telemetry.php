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

		$token      = defined( 'SCRIPT_REPORT_TELEMETRY_TOKEN' ) ? SCRIPT_REPORT_TELEMETRY_TOKEN : '';
		$server_url = apply_filters( 'script_report_telemetry_server_url', 'https://feedio.sapayth.com/api/t/' . $token . '/' );

		TelemetryConfig::setServerBaseUrl( $server_url );
		TelemetryConfig::setPolicyUrl( 'https://feedio.sapayth.com/policy' );

		// Add plugin_name to telemetry data so feedio doesn't rely on slug mapping.
		add_filter( 'script_report_telemetry_data', [ __CLASS__, 'add_plugin_name_to_data' ] );
		// Add inactive plugins list to telemetry data.
		add_filter( 'script_report_telemetry_data', [ __CLASS__, 'add_inactive_plugins_to_data' ] );
		// Add additional system data (MySQL, language, themes).
		add_filter( 'script_report_telemetry_data', [ __CLASS__, 'add_additional_system_data' ] );
		add_filter( 'script_report_deactivate_reasons', [ __CLASS__, 'remove_pro_reason' ] );

		if ( self::is_past_activation_grace_period() ) {
			Telemetry::report()->addPluginData()->init();
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

	/**
	 * Add plugin_name to telemetry data.
	 *
	 * @since SCRIPT_REPORT_SINCE
	 *
	 * @param array $data The telemetry data array.
	 *
	 * @return array Modified data array with plugin_name added.
	 */
	public static function add_plugin_name_to_data( $data ) {
		$data['plugin_name'] = TelemetryConfig::getSlug();
		return $data;
	}

	/**
	 * Add inactive plugins list to telemetry data.
	 *
	 * @since SCRIPT_REPORT_SINCE
	 *
	 * @param array $data The telemetry data array.
	 *
	 * @return array Modified data array with inactive_plugins_list added.
	 */
	public static function add_inactive_plugins_to_data( $data ) {
		if ( ! function_exists( 'get_plugins' ) ) {
			require_once ABSPATH . '/wp-admin/includes/plugin.php';
		}

		$all_plugins         = get_plugins();
		$active_plugins_keys = get_option( 'active_plugins', [] );
		$inactive_plugins    = [];

		foreach ( $all_plugins as $plugin_key => $plugin_data ) {
			if ( in_array( $plugin_key, $active_plugins_keys, true ) ) {
				continue;
			}

			$inactive_plugins[ $plugin_key ] = [
				'name'    => wp_strip_all_tags( $plugin_data['Name'] ?? '' ),
				'version' => wp_strip_all_tags( $plugin_data['Version'] ?? '' ),
				'author'  => wp_strip_all_tags( $plugin_data['Author'] ?? '' ),
			];
		}

		if ( ! empty( $inactive_plugins ) ) {
			$data['inactive_plugins_list'] = $inactive_plugins;
		}

		return $data;
	}

	/**
	 * Add additional system data to telemetry.
	 *
	 * Adds MySQL version, site language, and all themes list.
	 *
	 * @since SCRIPT_REPORT_SINCE
	 *
	 * @param array $data The telemetry data array.
	 *
	 * @return array Modified data array with additional system data.
	 */
	public static function add_additional_system_data( $data ) {
		global $wpdb;

		if ( method_exists( $wpdb, 'db_version' ) ) {
			$data['mysql_version'] = $wpdb->db_version();
		} else {
			$mysql_version = $wpdb->get_var( 'SELECT VERSION()' ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			if ( $mysql_version ) {
				$data['mysql_version'] = $mysql_version;
			}
		}

		$data['site_language'] = get_locale();

		if ( ! function_exists( 'wp_get_themes' ) ) {
			require_once ABSPATH . '/wp-includes/theme.php';
		}

		$themes_list = [];
		if ( function_exists( 'wp_get_themes' ) ) {
			$all_themes = wp_get_themes();
			foreach ( $all_themes as $theme_slug => $theme ) {
				$themes_list[ $theme_slug ] = [
					'name'    => $theme->get( 'Name' ),
					'version' => $theme->get( 'Version' ),
					'author'  => $theme->get( 'Author' ),
				];
			}
		}

		if ( ! empty( $themes_list ) ) {
			$data['themes_list'] = $themes_list;
		}

		return $data;
	}

}
