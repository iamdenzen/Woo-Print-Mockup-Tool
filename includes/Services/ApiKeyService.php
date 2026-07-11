<?php

namespace WooPrintMockupTool\Services;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class ApiKeyService {
	private const HASH_OPTION         = 'wpmt_api_key_hash';
	private const FINGERPRINT_OPTION  = 'wpmt_api_key_fingerprint';
	private const CREATED_AT_OPTION   = 'wpmt_api_key_created_at';
	private const KEY_PREFIX          = 'wpmt_live_';

	public function generate(): string {
		$key = self::KEY_PREFIX . bin2hex( random_bytes( 32 ) );

		update_option(
			self::HASH_OPTION,
			$this->hash( $key ),
			false
		);

		update_option(
			self::FINGERPRINT_OPTION,
			substr( $key, -8 ),
			false
		);

		update_option(
			self::CREATED_AT_OPTION,
			current_time( 'mysql' ),
			false
		);

		return $key;
	}

	public function verify( string $key ): bool {
		$key = trim( $key );

		if ( '' === $key ) {
			return false;
		}

		$stored_hash = (string) get_option( self::HASH_OPTION, '' );

		if ( '' === $stored_hash ) {
			return false;
		}

		return hash_equals(
			$stored_hash,
			$this->hash( $key )
		);
	}

	public function revoke(): void {
		delete_option( self::HASH_OPTION );
		delete_option( self::FINGERPRINT_OPTION );
		delete_option( self::CREATED_AT_OPTION );
	}

	public function exists(): bool {
		return '' !== (string) get_option( self::HASH_OPTION, '' );
	}

	public function get_masked_key(): string {
		if ( ! $this->exists() ) {
			return '';
		}

		$fingerprint = (string) get_option(
			self::FINGERPRINT_OPTION,
			''
		);

		return self::KEY_PREFIX . '••••••••••••••••' . $fingerprint;
	}

	public function get_created_at(): string {
		return (string) get_option(
			self::CREATED_AT_OPTION,
			''
		);
	}

	private function hash( string $key ): string {
		return hash( 'sha256', $key );
	}
}