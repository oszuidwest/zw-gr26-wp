<?php
/**
 * Livestream shortcode.
 *
 * @package ZWGR26
 */

namespace ZWGR26;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Renders [zw_gr26_livestream] — the election night livestream player card.
 */
class Shortcode_Livestream {

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
	 * @param array|string $atts Shortcode attributes.
	 * @return string Shortcode HTML output.
	 */
	public function render( $atts ): string {
		$this->assets->enqueue();

		$atts = shortcode_atts(
			[
				'titel'       => 'De uitslagenavond',
				'thumbnail'   => '',
				'badge'       => 'Live op 18 maart',
				'naam'        => 'ZuidWest Kiest: De Uitslag',
				'datum_tekst' => 'Woensdag 18 maart &bull; Vanaf 21:00 &bull; Live',
				'tijd'        => '',
				'url'         => '',
			],
			$atts,
			'zw_gr26_livestream'
		);

		$html = $this->renderer->section_open( $atts['titel'] );

		$html .= '<div class="zw-gr26-uitslagen">';

		if ( $atts['url'] ) {
			$html .= '<a href="' . esc_url( $atts['url'] ) . '" class="zw-gr26-uitslagen__player" target="_blank" rel="noopener">';
		} else {
			$html .= '<div class="zw-gr26-uitslagen__player">';
		}

		if ( $atts['thumbnail'] ) {
			$html .= $this->renderer->img_tag(
				$atts['thumbnail'],
				$atts['naam'],
				960,
				540,
				'',
				'min(920px, calc(100vw - 40px))'
			);
		}

		$html .= '<div class="zw-gr26-uitslagen__play">&#9654;</div>';
		$html .= '<div class="zw-gr26-uitslagen__overlay">';

		if ( $atts['badge'] ) {
			$html .= '<span class="zw-gr26-uitslagen__badge">' . esc_html( $atts['badge'] ) . '</span>';
		}

		$html .= '<div class="zw-gr26-uitslagen__title">' . esc_html( $atts['naam'] ) . '</div>';
		$html .= '<div class="zw-gr26-uitslagen__sub">' . wp_kses_post( $atts['datum_tekst'] ) . '</div>';
		$html .= '</div>';

		$html .= $atts['url'] ? '</a>' : '</div>';
		$html .= '</div>';

		$html .= $this->renderer->section_close();

		return $html;
	}
}
