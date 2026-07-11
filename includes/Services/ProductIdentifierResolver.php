<?php

namespace WooPrintMockupTool\Services;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class ProductIdentifierResolver {
	public function resolve_many( array $identifiers ): array {
		$product_ids = [];
		$errors      = [];

		foreach ( $identifiers as $identifier ) {
			$result = $this->resolve( $identifier );

			if ( ! empty( $result['success'] ) ) {
				$product_ids[] = (int) $result['product_id'];
				continue;
			}

			$errors[] = [
				'identifier' => is_scalar( $identifier )
					? (string) $identifier
					: '',
				'error'      => $result['error'],
			];
		}

		return [
			'product_ids' => array_values(
				array_unique( $product_ids )
			),
			'errors'      => $errors,
		];
	}

	public function resolve( mixed $identifier ): array {
		if ( is_int( $identifier ) ) {
			return $this->resolve_product_id( $identifier );
		}

		if ( ! is_scalar( $identifier ) ) {
			return $this->not_found();
		}

		$identifier = trim( (string) $identifier );

		if ( '' === $identifier ) {
			return $this->not_found();
		}

		/*
		 * Strings are treated as SKUs first.
		 *
		 * This is intentional because WooCommerce SKUs may be numeric.
		 */
		$product_id = wc_get_product_id_by_sku( $identifier );

		if ( $product_id ) {
			return [
				'success'    => true,
				'product_id' => $product_id,
			];
		}

		if ( ctype_digit( $identifier ) ) {
			return $this->resolve_product_id(
				(int) $identifier
			);
		}

		return $this->not_found();
	}

	private function resolve_product_id( int $product_id ): array {
		if ( $product_id <= 0 ) {
			return $this->not_found();
		}

		$product = wc_get_product( $product_id );

		if ( ! $product ) {
			return $this->not_found();
		}

		return [
			'success'    => true,
			'product_id' => $product_id,
		];
	}

	private function not_found(): array {
		return [
			'success' => false,
			'error'   => __(
				'WooCommerce product could not be found.',
				'woo-print-mockup-tool'
			),
		];
	}
}