<?php
/**
 * Verkiezingsprogramma shortcode.
 *
 * @package ZWGR26
 */

namespace ZWGR26;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Renders [zw_gr26_programmas] — program links per municipality from the gemeente_uitslag CPT.
 */
class Shortcode_Programmas {

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
	 * Render the [zw_gr26_programmas] shortcode.
	 *
	 * @param array|string $atts Shortcode attributes.
	 * @return string
	 */
	public function render( $atts ): string {
		$this->assets->enqueue();

		$atts = shortcode_atts(
			[
				'titel' => "Verkiezingsprogramma's",
			],
			$atts,
			'zw_gr26_programmas'
		);

		$gemeenten = $this->data->get_programmas();

		if ( empty( $gemeenten ) ) {
			return '<!-- zw_gr26_programmas: geen gemeenten met partijen gevonden -->';
		}

		$html  = $this->renderer->section_open( $atts['titel'] );
		$html .= '<nav class="zwv-programma" aria-label="' . esc_attr( $atts['titel'] ) . '">';

		// Municipality dropdown.
		$html .= '<div class="zwv-programma__select-wrap">';
		$html .= '<select class="zwv-programma__select" data-zw-gr26-programma-select>';
		$html .= '<option value="">Kies je gemeente...</option>';

		foreach ( $gemeenten as $gemeente ) {
			$id    = 'zwv-prog-' . sanitize_title( $gemeente['naam'] );
			$html .= '<option value="' . esc_attr( $id ) . '">' . esc_html( $gemeente['naam'] ) . '</option>';
		}

		$html .= '</select></div>';

		// Party lists per municipality.
		foreach ( $gemeenten as $gemeente ) {
			$id    = 'zwv-prog-' . sanitize_title( $gemeente['naam'] );
			$html .= '<div class="zwv-programma__list" id="' . esc_attr( $id ) . '">';

			foreach ( $gemeente['partijen'] as $partij ) {
				if ( $partij['url'] ) {
					$html .= '<a href="' . esc_url( $partij['url'] ) . '" class="zwv-prow">';
					$html .= '<span class="zwv-prow__partij">' . esc_html( $partij['naam'] ) . '</span>';
					$html .= '<span class="zwv-prow__link-text">Lees programma</span>';
					$html .= '</a>';
				} else {
					$html .= '<div class="zwv-prow zwv-prow--disabled">';
					$html .= '<span class="zwv-prow__partij">' . esc_html( $partij['naam'] ) . '</span>';
					$html .= '<span class="zwv-prow__link-text">Geen programma</span>';
					$html .= '</div>';
				}
			}

			$html .= '</div>';
		}

		$html .= '</nav>';
		$html .= $this->renderer->section_close();

		return $html;
	}
}
