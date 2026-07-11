<?php

namespace WooPrintMockupTool\Services;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class WebhookRetryService {
	public function register(): void {
		add_action(
			'wpmt_retry_webhook',
			[ $this, 'retry' ]
		);
	}

	public function retry( string $job_id ): void {
		( new WebhookService() )->deliver(
			sanitize_text_field( $job_id )
		);
	}
}