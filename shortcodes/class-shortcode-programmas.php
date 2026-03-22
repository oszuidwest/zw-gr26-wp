<?php
declare( strict_types = 1 );
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
	 * Renders the [zw_gr26_programmas] shortcode.
	 *
	 * @param array|string $atts Shortcode attributes.
	 * @return string Shortcode HTML output.
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
		$html .= '<nav class="zw-gr26-programma" aria-label="' . esc_attr( $atts['titel'] ) . '">';

		// Municipality dropdown.
		$dropdown_items = [];
		foreach ( $gemeenten as $gemeente ) {
			$dropdown_items[ 'zw-gr26-prog-' . sanitize_title( $gemeente['naam'] ) ] = $gemeente['naam'];
		}
		$html .= Renderer::municipality_dropdown( $dropdown_items );

		// Party lists per municipality.
		foreach ( $gemeenten as $gemeente ) {
			$id    = 'zw-gr26-prog-' . sanitize_title( $gemeente['naam'] );
			$html .= '<div class="zw-gr26-programma__list" id="' . esc_attr( $id ) . '">';

			foreach ( $gemeente['partijen'] as $partij ) {
				$html .= Renderer::programma_row( $partij );
			}

			$html .= '</div>';
		}

		$html .= '</nav>';
		$html .= $this->renderer->section_close();

		return $html;
	}
}
