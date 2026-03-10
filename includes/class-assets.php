<?php
/**
 * Asset registration and conditional enqueue.
 *
 * @package ZWGR26
 */

namespace ZWGR26;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Registers and conditionally enqueues CSS, JS, and web fonts.
 */
class Assets {

	/**
	 * Whether assets have already been enqueued on this request.
	 *
	 * @var bool
	 */
	private bool $enqueued = false;

	/**
	 * Hooks asset registration into WordPress.
	 *
	 * @return void
	 */
	public function register(): void {
		add_action( 'wp_enqueue_scripts', [ $this, 'register_assets' ] );
	}

	/**
	 * Registers all plugin assets without enqueuing them.
	 *
	 * @return void
	 */
	public function register_assets(): void {
		$css_ver = (string) filemtime( ZWGR26_PATH . 'assets/css/zw-gr26.css' );
		$js_ver  = (string) filemtime( ZWGR26_PATH . 'assets/js/zw-gr26.js' );

		wp_register_style(
			'zw-gr26',
			ZWGR26_URL . 'assets/css/zw-gr26.css',
			[],
			$css_ver
		);

		// External font — intentionally unversioned.
		wp_register_style(
			'zw-gr26-fonts',
			'https://fonts.bunny.net/css2?family=Nunito:wght@700;800;900&display=swap',
			[],
			null // phpcs:ignore WordPress.WP.EnqueuedResourceParameters.MissingVersion
		);

		wp_register_script(
			'zw-gr26',
			ZWGR26_URL . 'assets/js/zw-gr26.js',
			[],
			$js_ver,
			true
		);
	}

	/**
	 * Enqueues all plugin assets (de-duplicated).
	 *
	 * @return void
	 */
	public function enqueue(): void {
		if ( $this->enqueued ) {
			return;
		}

		wp_enqueue_style( 'zw-gr26-fonts' );
		wp_enqueue_style( 'zw-gr26' );
		wp_enqueue_script( 'zw-gr26' );

		$this->enqueued = true;
	}
}
