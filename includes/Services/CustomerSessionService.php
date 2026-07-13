<?php

namespace WooPrintMockupTool\Services;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class CustomerSessionService {
	public function get_session_key(): string {
		if (
			! function_exists( 'WC' )
			|| ! WC()->session
		) {
			return '';
		}

		$customer_id = WC()->session->get_customer_id();

		if (
			! is_string( $customer_id )
			&& ! is_int( $customer_id )
		) {
			return '';
		}

		$customer_id = trim(
			(string) $customer_id
		);

		if ( '' === $customer_id ) {
			return '';
		}

		return hash(
			'sha256',
			'wpmt|' . $customer_id
		);
	}
}