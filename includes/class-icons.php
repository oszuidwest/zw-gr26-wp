<?php
/**
 * Centralized SVG icon registry.
 *
 * @package ZWGR26
 */

namespace ZWGR26;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Provides inline SVG icons used throughout the plugin.
 */
class Icons {

	/**
	 * Returns the SVG markup for a named icon.
	 *
	 * @param string $name      Icon identifier.
	 * @param string $css_class Optional CSS class to add to the svg element.
	 * @return string SVG markup, or empty string if unknown.
	 */
	public static function get( string $name, string $css_class = '' ): string {
		// phpcs:disable Generic.Files.LineLength.TooLong
		$icons = [
			'wheelchair' => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 512 512" fill="currentColor" aria-hidden="true"><path d="M256 112a56 56 0 1 1 56-56 56.06 56.06 0 0 1-56 56z"/><path d="M432 112.8l-.45.12-.42.13c-1 .28-2 .58-3 .89-18.61 5.46-108.93 30.92-172.56 30.92-59.13 0-141.28-22-167.56-29.47a73.79 73.79 0 0 0-8-2.58c-19-5-32 14.3-32 31.94 0 17.47 15.7 25.79 31.55 31.76v.28l95.22 29.74c9.73 3.73 12.33 7.54 13.6 10.84 4.13 10.59.83 31.56-.34 38.88l-5.8 45L150.05 477.44q-.15.72-.27 1.47l-.23 1.27c-2.32 16.15 9.54 31.82 32 31.82 19.6 0 28.25-13.53 32-31.94s28-157.57 42-157.57 42.84 157.57 42.84 157.57c3.75 18.41 12.4 31.94 32 31.94 22.52 0 34.38-15.74 32-31.94-.21-1.38-.46-2.74-.76-4.06L329 301.27l-5.79-45c-4.19-26.21-.82-34.87.32-36.9a1.09 1.09 0 0 0 .08-.15c1.08-2 6-6.48 17.48-10.79l89.28-31.21a16.9 16.9 0 0 0 1.62-.52c16-6 32-14.3 32-31.93s-19-37.99-38-33z"/></svg>',
			'chevron'    => '<svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><path d="M5 8l5 5 5-5"/></svg>',
			'spotify'    => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 0C5.4 0 0 5.4 0 12s5.4 12 12 12 12-5.4 12-12S18.66 0 12 0zm5.521 17.34c-.24.359-.66.48-1.021.24-2.82-1.74-6.36-2.101-10.561-1.141-.418.122-.779-.179-.899-.539-.12-.421.18-.78.54-.9 4.56-1.021 8.52-.6 11.64 1.32.42.18.479.659.301 1.02zm1.44-3.3c-.301.42-.841.6-1.262.3-3.239-1.98-8.159-2.58-11.939-1.38-.479.12-1.02-.12-1.14-.6-.12-.48.12-1.021.6-1.141C9.6 9.9 15 10.561 18.72 12.84c.361.181.54.78.241 1.2zm.12-3.36C15.24 8.4 8.82 8.16 5.16 9.301c-.6.179-1.2-.181-1.38-.721-.18-.601.18-1.2.72-1.381 4.26-1.26 11.28-1.02 15.721 1.621.539.3.719 1.02.419 1.56-.299.421-1.02.599-1.559.3z"/></svg>',
			'apple'      => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M18.71 19.5c-.83 1.24-1.71 2.45-3.05 2.47-1.34.03-1.77-.79-3.29-.79-1.53 0-2 .77-3.27.82-1.31.05-2.3-1.32-3.14-2.53C4.25 17 2.94 12.45 4.7 9.39c.87-1.52 2.43-2.48 4.12-2.51 1.28-.02 2.5.87 3.29.87.78 0 2.26-1.07 3.8-.91.65.03 2.47.26 3.64 1.98-.09.06-2.17 1.28-2.15 3.81.03 3.02 2.65 4.03 2.68 4.04-.03.07-.42 1.44-1.38 2.83M13 3.5c.73-.83 1.94-1.46 2.94-1.5.13 1.17-.34 2.35-1.04 3.19-.69.85-1.83 1.51-2.95 1.42-.15-1.15.41-2.35 1.05-3.11z"/></svg>',
		];
		// phpcs:enable Generic.Files.LineLength.TooLong

		$svg = $icons[ $name ] ?? '';

		if ( '' !== $svg && '' !== $css_class ) {
			$svg = str_replace( '<svg ', '<svg class="' . esc_attr( $css_class ) . '" ', $svg );
		}

		return $svg;
	}
}
