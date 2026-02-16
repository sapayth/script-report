<?php
/**
 * Panel header partial template.
 *
 * @package Script_Report
 * @var string $title Panel title.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<div class="sr-title">
	<h2 class="sr-title-heading"><?php echo esc_html( $title ); ?></h2>
	<button type="button" class="sr-close" aria-label="<?php echo esc_attr__( 'Close panel', 'script-report' ); ?>">&times;</button>
</div>
