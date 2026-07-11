<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>

<div class="wrap wpmt-settings-page">
	<h1>
		<?php esc_html_e( 'Woo Print Mockup Tool', 'woo-print-mockup-tool' ); ?>
	</h1>

	<p>
		<?php esc_html_e(
			'Configure rendering, external API access, and renderer services.',
			'woo-print-mockup-tool'
		); ?>
	</p>

	<?php settings_errors(); ?>

	<form method="post" action="options.php">
		<?php
		settings_fields( 'wpmt_settings' );
		do_settings_sections( 'wpmt-settings' );
		submit_button();
		?>
	</form>
</div>