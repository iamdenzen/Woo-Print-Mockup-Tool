<?php

namespace WooPrintMockupTool\Services;

use WP_REST_Request;
use WP_Error;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class ApiKeyAuthenticator {
	private ApiKeyService $api_keys;

	public function __construct() {
		$this->api_keys = new ApiKeyService();
	}

	public function authenticate( WP_REST_Request $request ): true|WP_Error {
		if ( ! get_option( 'wpmt_api_enabled', false ) ) {
			return new WP_Error(
				'wpmt_api_disabled',
				__( 'The external render API is disabled.', 'woo-print-mockup-tool' ),
				[
					'status' => 403,
				]
			);
		}

		if ( ! $this->api_keys->exists() ) {
			return new WP_Error(
				'wpmt_api_not_configured',
				__( 'API authentication is not configured.', 'woo-print-mockup-tool' ),
				[
					'status' => 503,
				]
			);
		}

		$authorization = $this->get_authorization_header( $request );

		if ( '' === $authorization ) {
			return new WP_Error(
				'wpmt_missing_api_key',
				__( 'Authorization header is required.', 'woo-print-mockup-tool' ),
				[
					'status' => 401,
				]
			);
		}

		if ( ! preg_match( '/^Bearer\s+(.+)$/i', $authorization, $matches ) ) {
			return new WP_Error(
				'wpmt_invalid_authorization',
				__( 'A Bearer API key is required.', 'woo-print-mockup-tool' ),
				[
					'status' => 401,
				]
			);
		}

		$api_key = trim( $matches[1] );

		if ( ! $this->api_keys->verify( $api_key ) ) {
			return new WP_Error(
				'wpmt_invalid_api_key',
				__( 'The supplied API key is invalid.', 'woo-print-mockup-tool' ),
				[
					'status' => 401,
				]
			);
		}

		return true;
	}

	private function get_authorization_header( WP_REST_Request $request ): string {
		$header = $request->get_header( 'authorization' );

		if ( is_string( $header ) && '' !== trim( $header ) ) {
			return trim( $header );
		}

		if ( ! empty( $_SERVER['HTTP_AUTHORIZATION'] ) ) {
			return trim(
				sanitize_text_field(
					wp_unslash( $_SERVER['HTTP_AUTHORIZATION'] )
				)
			);
		}

		if ( ! empty( $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ) ) {
			return trim(
				sanitize_text_field(
					wp_unslash( $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] )
				)
			);
		}

		return '';
	}
}