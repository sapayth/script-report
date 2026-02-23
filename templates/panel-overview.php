<?php
/**
 * Overview panel template.
 *
 * @package Script_Report
 * @var array    $script_report_scripts_data     Scripts report data.
 * @var array    $script_report_styles_data      Styles report data.
 * @var int      $script_report_module_count     Number of modules enqueued.
 * @var object   $wp_scripts       WP_Scripts instance.
 * @var object   $wp_styles        WP_Styles instance.
 * @var array    $script_sources   Script sources mapping.
 * @var array    $style_sources    Style sources mapping.
 * @var object   $script_report    Script_Report instance.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$script_report_count = $script_report_scripts_data ? count( $script_report_scripts_data['needed'] ) : 0;
$script_report_size  = $script_report_scripts_data ? $script_report_scripts_data['total_size'] : 0;
$script_report_style_count = $script_report_styles_data ? count( $script_report_styles_data['needed'] ) : 0;
$script_report_style_size  = $script_report_styles_data ? $script_report_styles_data['total_size'] : 0;
?>
<div id="sr-overview" class="sr-panel sr-panel-show" role="tabpanel">
	<div class="sr-overview-layout">
		<!-- JavaScript Column -->
		<div class="sr-overview-column">
			<h3><?php echo esc_html__( 'JavaScript', 'script-report' ); ?></h3>
			<div class="sr-overview-stats">
				<?php echo (int) $script_report_count; ?> <?php echo esc_html__( 'loaded', 'script-report' ); ?>, <?php echo esc_html( $script_report->format_bytes( $script_report_size ) ); ?>
			</div>
			<?php if ( $wp_scripts && $script_report_scripts_data ) : ?>
				<?php $script_report->render_abbr_list( $wp_scripts, $script_report_scripts_data['print_order'], $script_sources, true, $script_report_scripts_data ); ?>
			<?php endif; ?>
		</div>

		<!-- CSS Column -->
		<div class="sr-overview-column">
			<h3><?php echo esc_html__( 'CSS', 'script-report' ); ?></h3>
			<div class="sr-overview-stats">
				<?php echo (int) $script_report_style_count; ?> <?php echo esc_html__( 'loaded', 'script-report' ); ?>, <?php echo esc_html( $script_report->format_bytes( $script_report_style_size ) ); ?>
			</div>
			<?php if ( $wp_styles && $script_report_styles_data ) : ?>
				<?php $script_report->render_abbr_list( $wp_styles, $script_report_styles_data['print_order'], $style_sources, false, $script_report_styles_data ); ?>
			<?php endif; ?>
		</div>
	</div>

	<?php if ( $script_report_module_count > 0 ) : ?>
		<div class="sr-modules-summary">
			<?php echo esc_html__( 'Modules', 'script-report' ); ?>: <?php echo (int) $script_report_module_count; ?> <?php echo esc_html__( 'enqueued', 'script-report' ); ?>
		</div>
	<?php endif; ?>
</div>
