<?php
/**
 * JavaScript scripts panel template.
 *
 * @package Script_Report
 * @var object   $wp_scripts       WP_Scripts instance.
 * @var array    $scripts_data     Scripts report data.
 * @var array    $script_sources   Script sources mapping.
 * @var object   $script_report    Script_Report instance.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<div id="sr-scripts" class="sr-panel" role="tabpanel">
	<?php if ( $wp_scripts && $scripts_data ) : ?>
		<?php $script_report->render_deps_stats( $wp_scripts, count( $scripts_data['needed'] ), $scripts_data['total_size'], __( 'Scripts', 'script-report' ) ); ?>
		<?php $script_report->render_deps_list( $wp_scripts, $scripts_data['print_order'], $script_sources, true, $scripts_data ); ?>
	<?php else : ?>
		<p><?php echo esc_html__( 'No scripts data.', 'script-report' ); ?></p>
	<?php endif; ?>
</div>
