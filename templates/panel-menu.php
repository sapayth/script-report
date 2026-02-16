<?php
/**
 * Panel menu/navigation partial template.
 *
 * @package Script_Report
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<nav id="sr-panel-menu" class="sr-panel-menu" aria-label="<?php echo esc_attr__( 'Script Report sections', 'script-report' ); ?>">
	<ul role="tablist">
		<li role="presentation">
			<button type="button" role="tab" class="sr-tab" data-sr-panel="#sr-overview" aria-selected="true">
				<?php echo esc_html__( 'Overview', 'script-report' ); ?>
			</button>
		</li>
		<li role="presentation">
			<button type="button" role="tab" class="sr-tab" data-sr-panel="#sr-scripts" aria-selected="false">
				<?php echo esc_html__( 'JavaScript', 'script-report' ); ?>
			</button>
		</li>
		<li role="presentation">
			<button type="button" role="tab" class="sr-tab" data-sr-panel="#sr-styles" aria-selected="false">
				<?php echo esc_html__( 'CSS', 'script-report' ); ?>
			</button>
		</li>
	</ul>
</nav>
