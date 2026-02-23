<?php
/**
 * CSS styles panel template.
 *
 * @package Script_Report
 * @var object   $wp_styles        WP_Styles instance.
 * @var array    $script_report_styles_data      Styles report data.
 * @var array    $style_sources    Style sources mapping.
 * @var object   $script_report    Script_Report instance.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<div id="sr-styles" class="sr-panel" role="tabpanel">
	<?php if ( $wp_styles && $script_report_styles_data ) : ?>
		<?php $script_report->render_deps_stats( $wp_styles, count( $script_report_styles_data['needed'] ), $script_report_styles_data['total_size'], __( 'Styles', 'script-report' ) ); ?>
		<?php $script_report->render_deps_list( $wp_styles, $script_report_styles_data['print_order'], $style_sources, false, $script_report_styles_data ); ?>
	<?php else : ?>
		<p><?php echo esc_html__( 'No styles data.', 'script-report' ); ?></p>
	<?php endif; ?>
</div>
