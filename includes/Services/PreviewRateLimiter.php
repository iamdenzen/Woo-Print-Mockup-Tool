<?php

namespace WooPrintMockupTool\Services;

use WP_Error;
use WP_REST_Request;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class PreviewRateLimiter {
	private const IP_LIMIT       = 10;
	private const IP_WINDOW      = 10 * MINUTE_IN_SECONDS;

	private const SESSION_LIMIT  = 5;
	private const SESSION_WINDOW = 5 * MINUTE_IN_SECONDS;

	public function consume(
		WP_REST_Request $request
	): true|WP_Error {
		$ip_result = $this->consume_key(
			$this->get_ip_key( $request ),
			self::IP_LIMIT,
			self::IP_WINDOW
		);

		if ( is_wp_error( $ip_result ) ) {
			return $ip_result;
		}

		$session_key = $this->get_session_key();

		if ( '' === $session_key ) {
			return true;
		}

		return $this->consume_key(
			'wpmt_rate_session_'
				. hash( 'sha256', $session_key ),
			self::SESSION_LIMIT,
			self::SESSION_WINDOW
		);
	}

	private function consume_key(
		string $key,
		int $limit,
		int $window
	): true|WP_Error {
		$data = get_transient( $key );

		if (
			! is_array( $data )
			|| ! isset( $data['count'] )
		) {
			set_transient(
				$key,
				[
					'count' => 1,
				],
				$window
			);

			return true;
		}

		$count = absint( $data['count'] );

		if ( $count >= $limit ) {
			return new WP_Error(
				'wpmt_preview_rate_limited',
				__(
					'Too many preview requests. Please try again shortly.',
					'woo-print-mockup-tool'
				),
				[
					'status' => 429,
				]
			);
		}

		$data['count'] = $count + 1;

		/*
		 * We reset the transient expiry here.
		 *
		 * This effectively creates a rolling inactivity window.
		 */
		set_transient(
			$key,
			$data,
			$window
		);

		return true;
	}

	private function get_ip_key(
	WP_REST_Request $request
    ): string {
        unset( $request );

        $ip = isset( $_SERVER['REMOTE_ADDR'] )
            ? sanitize_text_field(
                wp_unslash(
                    $_SERVER['REMOTE_ADDR']
                )
            )
            : 'unknown';

        if ( ! filter_var( $ip, FILTER_VALIDATE_IP ) ) {
            $ip = 'unknown';
        }

        return 'wpmt_rate_ip_'
            . hash( 'sha256', $ip );
    }

	private function get_session_key(): string {
		if (
			! function_exists( 'WC' )
			|| ! WC()->session
		) {
			return '';
		}

		$customer_id = WC()->session->get_customer_id();

		return is_string( $customer_id )
			? $customer_id
			: '';
	}
}