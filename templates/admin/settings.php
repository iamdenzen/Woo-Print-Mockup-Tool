<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$api_key_service = $api_key_service ?? null;
$new_api_key     = is_string( $new_api_key ?? null ) ? $new_api_key : '';

$api_key_exists = $api_key_service
	&& $api_key_service->exists();

$masked_api_key = $api_key_exists
	? $api_key_service->get_masked_key()
	: '';

$api_key_created_at = $api_key_exists
	? $api_key_service->get_created_at()
	: '';
?>

<div class="wrap wpmt-settings-page">
	<h1>
		<?php esc_html_e( 'Woo Print Mockup Tool', 'woo-print-mockup-tool' ); ?>
	</h1>

	<p>
		<?php
		esc_html_e(
			'Configure rendering, external API access, and renderer services.',
			'woo-print-mockup-tool'
		);
		?>
	</p>

	<?php settings_errors(); ?>

	<?php if ( isset( $_GET['wpmt_api_key_revoked'] ) ) : ?>
		<div class="notice notice-success is-dismissible">
			<p>
				<?php esc_html_e( 'API key revoked.', 'woo-print-mockup-tool' ); ?>
			</p>
		</div>
	<?php endif; ?>

	<?php if ( '' !== $new_api_key ) : ?>
		<div class="notice notice-warning wpmt-api-key-notice">
			<p>
				<strong>
					<?php esc_html_e( 'Your new API key:', 'woo-print-mockup-tool' ); ?>
				</strong>
			</p>

			<p>
				<code class="wpmt-generated-api-key">
					<?php echo esc_html( $new_api_key ); ?>
				</code>
			</p>

			<p>
				<strong>
					<?php
					esc_html_e(
						'Copy this key now. It will not be shown again.',
						'woo-print-mockup-tool'
					);
					?>
				</strong>
			</p>
		</div>
	<?php endif; ?>

	<form method="post" action="options.php">
		<?php
		settings_fields( 'wpmt_settings' );
		do_settings_sections( 'wpmt-settings' );
		submit_button();
		?>
	</form>

	<hr />

	<div class="wpmt-api-key-management">
		<h2>
			<?php esc_html_e( 'API Key', 'woo-print-mockup-tool' ); ?>
		</h2>

		<p>
			<?php
			esc_html_e(
				'Use this API key to authenticate external render-job requests.',
				'woo-print-mockup-tool'
			);
			?>
		</p>

		<?php if ( $api_key_exists ) : ?>

			<table class="form-table" role="presentation">
				<tbody>
					<tr>
						<th scope="row">
							<?php esc_html_e( 'Current key', 'woo-print-mockup-tool' ); ?>
						</th>

						<td>
							<code>
								<?php echo esc_html( $masked_api_key ); ?>
							</code>
						</td>
					</tr>

					<?php if ( '' !== $api_key_created_at ) : ?>
						<tr>
							<th scope="row">
								<?php esc_html_e( 'Created', 'woo-print-mockup-tool' ); ?>
							</th>

							<td>
								<?php echo esc_html( $api_key_created_at ); ?>
							</td>
						</tr>
					<?php endif; ?>
				</tbody>
			</table>

			<div class="wpmt-api-key-actions">
				<form
					method="post"
					action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>"
					class="wpmt-inline-form"
				>
					<input
						type="hidden"
						name="action"
						value="wpmt_generate_api_key"
					/>

					<?php wp_nonce_field( 'wpmt_generate_api_key' ); ?>

					<?php
					submit_button(
						__( 'Regenerate API Key', 'woo-print-mockup-tool' ),
						'secondary',
						'submit',
						false
					);
					?>
				</form>

				<form
					method="post"
					action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>"
					class="wpmt-inline-form"
				>
					<input
						type="hidden"
						name="action"
						value="wpmt_revoke_api_key"
					/>

					<?php wp_nonce_field( 'wpmt_revoke_api_key' ); ?>

					<?php
					submit_button(
						__( 'Revoke API Key', 'woo-print-mockup-tool' ),
						'delete',
						'submit',
						false
					);
					?>
				</form>
			</div>

		<?php else : ?>

			<p>
				<?php
				esc_html_e(
					'No API key has been generated yet.',
					'woo-print-mockup-tool'
				);
				?>
			</p>

			<form
				method="post"
				action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>"
			>
				<input
					type="hidden"
					name="action"
					value="wpmt_generate_api_key"
				/>

				<?php wp_nonce_field( 'wpmt_generate_api_key' ); ?>

				<?php
				submit_button(
					__( 'Generate API Key', 'woo-print-mockup-tool' ),
					'primary',
					'submit',
					false
				);
				?>
			</form>

		<?php endif; ?>
	</div>
</div>