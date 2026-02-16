<?php
/**
 * Overview panel template.
 *
 * @package Script_Report
 * @var array    $scripts_data     Scripts report data.
 * @var array    $styles_data      Styles report data.
 * @var int      $module_count     Number of modules enqueued.
 * @var object   $wp_scripts       WP_Scripts instance.
 * @var object   $wp_styles        WP_Styles instance.
 * @var array    $script_sources   Script sources mapping.
 * @var array    $style_sources    Style sources mapping.
 * @var object   $script_report    Script_Report instance.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$script_count = $scripts_data ? count( $scripts_data['needed'] ) : 0;
$script_size  = $scripts_data ? $scripts_data['total_size'] : 0;
$style_count  = $styles_data ? count( $styles_data['needed'] ) : 0;
$style_size   = $styles_data ? $styles_data['total_size'] : 0;
?>
<div id="sr-overview" class="sr-panel sr-panel-show" role="tabpanel">
	<div class="sr-overview-layout">
		<!-- JavaScript Column -->
		<div class="sr-overview-column">
			<h3><?php echo esc_html__( 'JavaScript', 'script-report' ); ?></h3>
			<div class="sr-overview-stats">
				<?php echo (int) $script_count; ?> <?php echo esc_html__( 'loaded', 'script-report' ); ?>, <?php echo esc_html( $script_report->format_bytes( $script_size ) ); ?>
			</div>
			<?php if ( $wp_scripts && $scripts_data ) : ?>
				<?php $script_report->render_abbr_list( $wp_scripts, $scripts_data['print_order'], $script_sources, true, $scripts_data ); ?>
			<?php endif; ?>
		</div>

		<!-- CSS Column -->
		<div class="sr-overview-column">
			<h3><?php echo esc_html__( 'CSS', 'script-report' ); ?></h3>
			<div class="sr-overview-stats">
				<?php echo (int) $style_count; ?> <?php echo esc_html__( 'loaded', 'script-report' ); ?>, <?php echo esc_html( $script_report->format_bytes( $style_size ) ); ?>
			</div>
			<?php if ( $wp_styles && $styles_data ) : ?>
				<?php $script_report->render_abbr_list( $wp_styles, $styles_data['print_order'], $style_sources, false, $styles_data ); ?>
			<?php endif; ?>
		</div>
	</div>

	<?php if ( $module_count > 0 ) : ?>
		<div class="sr-modules-summary">
			<?php echo esc_html__( 'Modules', 'script-report' ); ?>: <?php echo (int) $module_count; ?> <?php echo esc_html__( 'enqueued', 'script-report' ); ?>
		</div>
	<?php endif; ?>
</div>
