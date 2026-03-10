<?php
/**
 * PHPStan bootstrap file — defines constants and stubs so PHPStan
 * can analyse the plugin without WordPress loaded.
 *
 * @package ZWGR26
 */

if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', __DIR__ . '/' );
}

define( 'ZWGR26_VERSION', '0.0.0-dev' );
define( 'ZWGR26_PATH', __DIR__ . '/' );
define( 'ZWGR26_URL', 'https://example.com/wp-content/plugins/zw-gr26/' );

// ACF stubs.
if ( ! function_exists( 'get_field' ) ) {
	/**
	 * ACF get_field stub.
	 *
	 * @param string $selector Field name or key.
	 * @param mixed  $post_id  Post ID or options string.
	 * @return mixed
	 */
	function get_field( string $selector, mixed $post_id = false ) {
		return null;
	}
}

if ( ! function_exists( 'update_field' ) ) {
	/**
	 * ACF update_field stub.
	 *
	 * @param string $selector Field name or key.
	 * @param mixed  $value    The value to save.
	 * @param mixed  $post_id  Post ID or options string.
	 * @return mixed
	 */
	function update_field( string $selector, mixed $value, mixed $post_id = false ) {
		return null;
	}
}

if ( ! function_exists( 'acf_add_local_field_group' ) ) {
	/**
	 * ACF acf_add_local_field_group stub.
	 *
	 * @param array $field_group Field group config.
	 * @return void
	 */
	function acf_add_local_field_group( array $field_group ): void {}
}
