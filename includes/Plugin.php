<?php

namespace WooPrintMockupTool;

use WooPrintMockupTool\Admin\AdminServiceProvider;
use WooPrintMockupTool\Api\ApiServiceProvider;
use WooPrintMockupTool\Assets\AssetsServiceProvider;
use WooPrintMockupTool\Frontend\FrontendServiceProvider;
use WooPrintMockupTool\Storage\CleanupService;
use WooPrintMockupTool\Services\WebhookRetryService;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Plugin {
	private static ?self $instance = null;

	public static function instance(): self {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	public function boot(): void {
		if ( ! class_exists( 'WooCommerce' ) ) {
			add_action( 'admin_notices', [ $this, 'woocommerce_missing_notice' ] );
			return;
		}

		$providers = [
			AssetsServiceProvider::class,
			AdminServiceProvider::class,
			FrontendServiceProvider::class,
			ApiServiceProvider::class,
			CleanupService::class,
			WebhookRetryService::class,
		];

		foreach ( $providers as $provider ) {
			( new $provider() )->register();
		}
	}

	public function woocommerce_missing_notice(): void {
		printf(
			'<div class="notice notice-error"><p>%s</p></div>',
			esc_html__( 'Woo Print Mockup Tool requires WooCommerce to be installed and active.', 'woo-print-mockup-tool' )
		);
	}
}
