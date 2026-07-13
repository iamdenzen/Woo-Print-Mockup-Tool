<?php

namespace WooPrintMockupTool\Services;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class CustomerSessionService {
	private const COOKIE_NAME = 'wpmt_customer_session';

	public function get_session_key(): string {
		$session_id = $this->get_existing_session_id();

		if ( '' === $session_id ) {
			$session_id = $this->create_session_id();

			$this->set_session_cookie( $session_id );
		}

		return hash(
			'sha256',
			'wpmt|' . $session_id
		);
	}

	private function get_existing_session_id(): string {
		if ( empty( $_COOKIE[ self::COOKIE_NAME ] ) ) {
			return '';
		}

		$session_id = sanitize_text_field(
			wp_unslash(
				$_COOKIE[ self::COOKIE_NAME ]
			)
		);

		if (
			1 !== preg_match(
				'/^[a-f0-9]{64}$/',
				$session_id
			)
		) {
			return '';
		}

		return $session_id;
	}

	private function create_session_id(): string {
		return bin2hex(
			random_bytes( 32 )
		);
	}

	private function set_session_cookie(
		string $session_id
	): void {
		if ( headers_sent() ) {
			return;
		}

		setcookie(
			self::COOKIE_NAME,
			$session_id,
			[
				'expires'  => time() + YEAR_IN_SECONDS,
				'path'     => COOKIEPATH ?: '/',
				'domain'   => COOKIE_DOMAIN,
				'secure'   => is_ssl(),
				'httponly' => true,
				'samesite' => 'Lax',
			]
		);

		/*
		 * Make the newly created session available during
		 * the current PHP request as well.
		 */
		$_COOKIE[ self::COOKIE_NAME ] = $session_id;
	}
}