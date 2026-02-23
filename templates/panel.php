<?php
/**
 * Main panel template.
 *
 * @package Script_Report
 * @var string   $title            Panel title.
 * @var object   $script_report    Script_Report instance.
 * @var object   $wp_scripts       WP_Scripts instance.
 * @var object   $wp_styles        WP_Styles instance.
 * @var object   $script_report_modules WP_Script_Modules instance.
 * @var array    $script_sources        Script sources mapping.
 * @var array    $style_sources         Style sources mapping.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$script_report_scripts_data = $wp_scripts ? $script_report->get_deps_report_data( $wp_scripts ) : null;
$script_report_styles_data  = $wp_styles ? $script_report->get_deps_report_data( $wp_styles ) : null;

$script_report_module_count = 0;
if ( $script_report_modules && method_exists( $script_report_modules, 'get_enqueued' ) ) {
	$script_report_module_count = count( $script_report_modules->get_enqueued() );
}

$script_report_template_dir = SCRIPT_REPORT_PATH . 'templates/';
?>
<!-- Begin Script Report panel -->
<div id="script-report-main" class="sr-main script-report" aria-hidden="true">
	<div class="sr-resize-handle" aria-label="<?php echo esc_attr__( 'Drag to resize panel', 'script-report' ); ?>"></div>

	<?php require $script_report_template_dir . 'panel-header.php'; ?>

	<div class="sr-wrapper">
		<?php require $script_report_template_dir . 'panel-menu.php'; ?>

		<div id="sr-panels" class="sr-panels">
			<?php
			require $script_report_template_dir . 'panel-overview.php';
			require $script_report_template_dir . 'panel-scripts.php';
			require $script_report_template_dir . 'panel-styles.php';
			?>
		</div>
	</div>
</div>
<!-- End Script Report panel -->
