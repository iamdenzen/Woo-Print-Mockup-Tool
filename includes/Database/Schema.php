<?php

namespace WooPrintMockupTool\Database;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Schema {
	public static function create_tables(): void {
		global $wpdb;

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$charset_collate = $wpdb->get_charset_collate();
		$configs = $wpdb->prefix . 'wpmt_product_configs';
		$sessions = $wpdb->prefix . 'wpmt_artwork_sessions';
		$jobs = $wpdb->prefix . 'wpmt_render_jobs';
		$results = $wpdb->prefix . 'wpmt_render_results';

		dbDelta( "CREATE TABLE {$configs} (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			product_id BIGINT UNSIGNED NOT NULL,
			enabled TINYINT(1) NOT NULL DEFAULT 0,
			mockup_image_id BIGINT UNSIGNED NULL,
			placement_type VARCHAR(20) NOT NULL DEFAULT 'rectangle',
			placement_data LONGTEXT NULL,
			render_mode VARCHAR(30) NOT NULL DEFAULT 'color',
			created_at DATETIME NOT NULL,
			updated_at DATETIME NOT NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY product_id (product_id),
			KEY enabled (enabled)
		) {$charset_collate};" );

		dbDelta( "CREATE TABLE {$sessions} (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			session_key VARCHAR(64) NOT NULL,
			artwork_path TEXT NOT NULL,
			artwork_hash VARCHAR(64) NOT NULL,
			created_at DATETIME NOT NULL,
			expires_at DATETIME NOT NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY session_key (session_key),
			KEY artwork_hash (artwork_hash),
			KEY expires_at (expires_at)
		) {$charset_collate};" );

		dbDelta( "CREATE TABLE {$jobs} (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			job_id VARCHAR(100) NOT NULL,
			source VARCHAR(20) NOT NULL DEFAULT 'api',
			status VARCHAR(20) NOT NULL DEFAULT 'pending',
			artwork_path TEXT NULL,
			webhook_url TEXT NULL,
			webhook_status varchar(20) NOT NULL DEFAULT 'not_requested',
			webhook_attempts int unsigned NOT NULL DEFAULT 0,
			webhook_last_error text NULL,
			webhook_delivered_at datetime NULL,
			webhook_next_attempt_at datetime NULL,
			created_at DATETIME NOT NULL,
			expires_at DATETIME NOT NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY job_id (job_id),
			KEY status (status),
			KEY expires_at (expires_at)
		) {$charset_collate};" );

		dbDelta( "CREATE TABLE {$results} (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			job_id VARCHAR(100) NULL,
			session_key VARCHAR(64) NULL,
			product_id BIGINT UNSIGNED NOT NULL,
			image_path TEXT NULL,
			image_url TEXT NULL,
			status VARCHAR(20) NOT NULL DEFAULT 'success',
			error_message TEXT NULL,
			created_at DATETIME NOT NULL,
			expires_at DATETIME NOT NULL,
			PRIMARY KEY  (id),
			KEY job_id (job_id),
			KEY session_key (session_key),
			KEY product_id (product_id),
			KEY expires_at (expires_at)
		) {$charset_collate};" );
	}
}
