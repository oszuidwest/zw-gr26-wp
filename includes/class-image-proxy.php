<?php
/**
 * Image proxy (imgproxy) integration.
 *
 * Rewrites image URLs through an imgproxy service for on-the-fly resizing.
 * Uses the same WordPress options as the streekomroep-wp theme:
 * imgproxy_url, imgproxy_key, imgproxy_salt.
 *
 * @package ZWGR26
 */

namespace ZWGR26;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Rewrites image URLs through an imgproxy service with HMAC signing.
 */
class Image_Proxy {

	/**
	 * Whether the proxy is configured and available.
	 *
	 * @var bool
	 */
	private bool $enabled;

	/**
	 * Imgproxy base URL.
	 *
	 * @var string
	 */
	private string $host;

	/**
	 * Binary signing key.
	 *
	 * @var string
	 */
	private string $key_bin;

	/**
	 * Binary salt.
	 *
	 * @var string
	 */
	private string $salt_bin;

	/**
	 * Constructor — reads imgproxy credentials from WordPress options.
	 */
	public function __construct() {
		$this->host = get_option( 'imgproxy_url', '' );
		$key        = get_option( 'imgproxy_key', '' );
		$salt       = get_option( 'imgproxy_salt', '' );

		$this->enabled = ! empty( $this->host ) && ! empty( $key ) && ! empty( $salt );

		if ( $this->enabled ) {
			$this->key_bin  = pack( 'H*', $key );
			$this->salt_bin = pack( 'H*', $salt );
		}
	}

	/**
	 * Determines whether imgproxy is configured.
	 *
	 * @return bool
	 */
	public function is_enabled(): bool {
		return $this->enabled;
	}

	/**
	 * Rewrites an image URL through imgproxy.
	 *
	 * Returns the original URL unchanged when imgproxy is not configured
	 * or when the source URL is empty.
	 *
	 * @param string $src    Original image URL.
	 * @param int    $width  Target width in pixels.
	 * @param int    $height Target height in pixels.
	 * @return string Proxied URL or original.
	 */
	public function url( string $src, int $width, int $height ): string {
		if ( ! $this->enabled || empty( $src ) ) {
			return $src;
		}

		// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode -- URL-safe encoding for imgproxy.
		$encoded_url = rtrim( strtr( base64_encode( $src ), '+/', '-_' ), '=' );
		$path        = "/rs:fill:{$width}:{$height}:1/g:ce/{$encoded_url}.jpeg";

		// phpcs:disable WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode -- HMAC signature for imgproxy.
		$signature = rtrim(
			strtr(
				base64_encode(
					hash_hmac( 'sha256', $this->salt_bin . $path, $this->key_bin, true )
				),
				'+/',
				'-_'
			),
			'='
		);
		// phpcs:enable WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode

		return $this->host . $signature . $path;
	}
}
