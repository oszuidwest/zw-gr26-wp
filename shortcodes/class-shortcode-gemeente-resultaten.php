<?php
declare( strict_types = 1 );
/**
 * Gemeente resultaten shortcode.
 *
 * @package ZWGR26
 */

namespace ZWGR26;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Renders [zw_gr26_gemeente_resultaten] — inline donut + table + coalition builder for one municipality.
 *
 * Reads the active gemeente from Shortcode_Pagina::$active_gemeente.
 */
class Shortcode_Gemeente_Resultaten {

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
	 * @param array|string $atts Shortcode attributes (unused, kept for WordPress compatibility).
	 * @return string Shortcode HTML output.
	 */
	public function render( $atts ): string { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.Found
		$this->assets->enqueue();

		$slug = Shortcode_Pagina::$active_gemeente;
		if ( ! $slug ) {
			return '<!-- zw_gr26_gemeente_resultaten: geen gemeente context -->';
		}

		$results = $this->data->get_election_results();
		$entry   = $results[ $slug ] ?? null;

		if ( ! $entry || empty( $entry['partijen'] ) ) {
			return '<!-- zw_gr26_gemeente_resultaten: geen data voor ' . esc_html( $slug ) . ' -->';
		}

		// Pass single-gemeente data to JS.
		$json = wp_json_encode( $entry, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG );
		wp_add_inline_script( 'zw-gr26', 'var zwGr26GemeenteResultaten=' . $json . ';', 'before' );

		$is_2026       = ! empty( $entry['has_2026'] );
		$totaal_zetels = $entry['totaal_zetels'];
		$titel         = 'Uitslag ' . $entry['naam'];

		$html  = $this->renderer->section_open( $titel );
		$html .= '<div class="zw-gr26-gem-resultaten' . ( $is_2026 ? '' : ' is-wacht' )
			. '" id="zwgr26GemResultaten">';

		// Wacht-state banner (visible only when no 2026 results yet).
		$html .= '<div class="zw-gr26-wacht-banner">'
			. 'Hier verschijnen de uitslagen binnenkort</div>';

		// Grid: donut left, table right.
		$html .= '<div class="zw-gr26-gem-resultaten__grid">';

		// Donut column.
		$html .= '<div class="zw-gr26-gem-resultaten__donut-col">';
		$html .= '<div class="zw-gr26-gem-resultaten__section-label" id="zwgr26GemDonutLabel">';
		$html .= 'Zetelverdeling';
		$html .= '</div>';
		$html .= '<div class="zw-gr26-donut-area">';
		$html .= Renderer::donut_chart( 'zwgr26Gem', esc_html( (string) $totaal_zetels ) );

		// Opkomst.
		$html .= '<div class="zw-gr26-opkomst" id="zwgr26GemOpkomst">';
		if ( $is_2026 && null !== $entry['opkomst_2026'] ) {
			$html .= 'Opkomst: ' . esc_html( (string) $entry['opkomst_2026'] ) . '%';
			if ( null !== $entry['opkomst_2022'] ) {
				$html .= '<span class="zw-gr26-opkomst-ref"> (2022: '
					. esc_html( (string) $entry['opkomst_2022'] ) . '%)</span>';
			}
		} elseif ( null !== $entry['opkomst_2022'] ) {
			$html .= '<span class="zw-gr26-opkomst-ref">Opkomst 2022: '
				. esc_html( (string) $entry['opkomst_2022'] ) . '%</span>';
		}
		$html .= '</div>';

		$html .= '</div>'; // End donut-area.
		$html .= '</div>'; // End donut-col.

		// Table column.
		$html .= '<div class="zw-gr26-gem-resultaten__table-col">';
		$html .= '<div class="zw-gr26-gem-resultaten__section-label" id="zwgr26GemTableLabel">';
		$html .= $is_2026 ? 'Resultaten' : 'Huidig college';
		$html .= '</div>';
		$html .= Renderer::results_table( 'zwgr26GemTbody' );
		$html .= '</div>'; // End table-col.

		$html .= '</div>'; // End grid.

		// Coalition toggle.
		if ( $is_2026 ) {
			$html .= Renderer::coalition_builder( 'zwgr26Gem' );
		}

		$html .= '</div>'; // End gem-resultaten.
		$html .= $this->renderer->section_close();

		return $html;
	}
}
