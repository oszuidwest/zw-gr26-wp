<?php
/**
 * Wrapper shortcode for the election page.
 *
 * @package ZWGR26
 */

namespace ZWGR26;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Renders [zw_gr26_pagina] — the full-page wrapper with hero, stripe, and inner shortcodes.
 */
class Shortcode_Pagina {

	/**
	 * Asset manager.
	 *
	 * @var Assets
	 */
	private Assets $assets;

	/**
	 * Shared renderer.
	 *
	 * @var Renderer
	 */
	private Renderer $renderer;

	/**
	 * Constructor.
	 *
	 * @param Assets   $assets   Asset manager.
	 * @param Renderer $renderer Shared renderer.
	 */
	public function __construct( Assets $assets, Renderer $renderer ) {
		$this->assets   = $assets;
		$this->renderer = $renderer;
	}

	/**
	 * Renders the shortcode.
	 *
	 * @param array|string $atts    Shortcode attributes.
	 * @param string|null  $content Enclosed shortcode content.
	 * @return string Shortcode HTML output.
	 */
	public function render( $atts, ?string $content = null ): string {
		$this->assets->enqueue();

		$atts = shortcode_atts(
			[
				'titel'       => 'ZuidWest Kiest',
				'ondertitel'  => "Alles over de gemeente\xC2\xADraads\xC2\xADverkiezingen van 2026 in West-Brabant.",
				'achtergrond' => 'https://www.zuidwestupdate.nl/wp-content/uploads/2022/03/potlood.jpg',
			],
			$atts,
			'zw_gr26_pagina'
		);

		$inner = do_shortcode( $content );

		// Remove <br> and empty <p> tags injected by wpautop between shortcodes.
		$inner = preg_replace( '/<br\s*\/?>\s*/', '', $inner );
		$inner = preg_replace( '#<p>\s*</p>#', '', $inner );

		// Render video modal in wp_footer so it sits outside .zw-gr26-wrapper.
		add_action(
			'wp_footer',
			static function () {
				echo '<div class="zw-gr26-modal-backdrop" id="zwgr26VideoModal">';
				echo '<div class="zw-gr26-video-modal" role="dialog" aria-modal="true" aria-label="Video">';
				echo '<button class="zw-gr26-modal__close" type="button">&times;</button>';
				echo '<iframe class="zw-gr26-video-modal__iframe" allowfullscreen allow="autoplay; encrypted-media"></iframe>';
				echo '</div></div>';
			}
		);

		$html  = '<main class="zw-gr26-wrapper not-prose">';
		$html .= $this->renderer->hero(
			$atts['titel'],
			$atts['ondertitel'],
			$atts['achtergrond']
		);
		$html .= $this->renderer->stripe();
		$html .= '<div class="zw-gr26-container">';

		$html .= $inner;
		$html .= '</div></main>';

		return $html;
	}
}
