<?php
/**
 * Gemeente page wrapper shortcode.
 *
 * @package ZWGR26
 */

namespace ZWGR26;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Renders [zw_gr26_gemeente_pagina] — a full-page wrapper for a single municipality.
 *
 * Sets the active gemeente context so child shortcodes can scope their output.
 */
class Shortcode_Gemeente_Pagina {

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
	 * Data provider.
	 *
	 * @var Data_Provider
	 */
	private Data_Provider $data;

	/**
	 * Constructor.
	 *
	 * @param Assets        $assets   Asset manager.
	 * @param Renderer      $renderer Shared renderer.
	 * @param Data_Provider $data     Data provider.
	 */
	public function __construct( Assets $assets, Renderer $renderer, Data_Provider $data ) {
		$this->assets   = $assets;
		$this->renderer = $renderer;
		$this->data     = $data;
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
				'gemeente'    => '',
				'titel'       => '',
				'ondertitel'  => 'Gemeenteraadsverkiezingen 2026',
				'achtergrond' => 'https://www.zuidwestupdate.nl/wp-content/uploads/2022/03/potlood.jpg',
			],
			$atts,
			'zw_gr26_gemeente_pagina'
		);

		$slug = sanitize_title( $atts['gemeente'] );
		if ( ! $slug ) {
			return '<!-- zw_gr26_gemeente_pagina: gemeente niet opgegeven -->';
		}

		// Validate against known municipalities.
		$municipalities = $this->data->get_all_municipalities();
		if ( ! isset( $municipalities[ $slug ] ) ) {
			return '<!-- zw_gr26_gemeente_pagina: onbekende gemeente -->';
		}

		$naam = $municipalities[ $slug ];

		Shortcode_Pagina::$active          = true;
		Shortcode_Pagina::$active_gemeente = $slug;

		try {
			$inner = do_shortcode( $content );
		} finally {
			Shortcode_Pagina::$active          = false;
			Shortcode_Pagina::$active_gemeente = null;
		}

		// Remove <br> and empty <p> tags injected by wpautop between shortcodes.
		$inner = preg_replace( '/<br\s*\/?>\s*/', '', $inner );
		$inner = preg_replace( '#<p>\s*</p>#', '', $inner );

		// Render video modal in wp_footer so it sits outside .zw-gr26-wrapper.
		if ( ! Shortcode_Pagina::$video_modal_added ) {
			Shortcode_Pagina::$video_modal_added = true;
			add_action(
				'wp_footer',
				static function () {
					echo '<div class="zw-gr26-modal-backdrop" id="zwgr26VideoModal">';
					echo '<div class="zw-gr26-video-modal" role="dialog" aria-modal="true" aria-label="Video" tabindex="-1">';
					echo '<button class="zw-gr26-modal__close" type="button">&times;</button>';
					echo '<video class="video-js vjs-fill vjs-big-play-centered" id="zwgr26VideoPlayer" playsinline controls></video>';
					echo '</div></div>';
				}
			);
		}

		$html  = '<main class="zw-gr26-wrapper not-prose">';
		$titel = $atts['titel'] ? $atts['titel'] : $naam;

		$html .= $this->renderer->hero(
			$titel,
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
