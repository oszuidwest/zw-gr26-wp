<?php
/**
 * Resultaten shortcode.
 *
 * @package ZWGR26
 */

namespace ZWGR26;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Renders [zw_gr26_resultaten] — election result tiles and a modal with seat visualization.
 */
class Shortcode_Resultaten {

	/**
	 * Whether the results modal has already been added to the footer.
	 *
	 * @var bool
	 */
	private static bool $modal_added = false;

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
	 * @param array|string $atts Shortcode attributes.
	 * @return string Shortcode HTML output.
	 */
	public function render( $atts ): string {
		$this->assets->enqueue();

		$atts = shortcode_atts(
			[
				'titel' => 'Uitslagen per gemeente',
			],
			$atts,
			'zw_gr26_resultaten'
		);

		$municipalities = $this->data->get_all_municipalities();
		if ( empty( $municipalities ) ) {
			return '<!-- zw_gr26_resultaten: geen gemeenten gevonden -->';
		}

		$results = $this->data->get_election_results();

		// Pass published election data to the front-end script.
		// Use wp_add_inline_script instead of wp_localize_script to preserve
		// numeric types (wp_localize_script casts everything to strings).
		$json = wp_json_encode( $results, JSON_UNESCAPED_UNICODE );
		wp_add_inline_script( 'zw-gr26', 'var zwGr26Resultaten=' . $json . ';', 'before' );

		$html = $this->renderer->section_open( $atts['titel'] );

		// Municipality tiles — always show all 10, always clickable.
		$html .= '<div class="zw-gr26-tiles">';
		foreach ( $municipalities as $slug => $naam ) {
			$entry    = $results[ $slug ] ?? null;
			$has_2026 = $entry && ! empty( $entry['has_2026'] );
			$status   = $has_2026 ? 'Bekijk uitslagen' : 'Nog geen uitslagen';

			$html .= '<div class="zw-gr26-tile" data-gemeente="' . esc_attr( $slug ) . '" tabindex="0" role="button">';
			$html .= '<h3 class="zw-gr26-tile__name">' . esc_html( $naam ) . '</h3>';
			$html .= '<div class="zw-gr26-tile__status">' . esc_html( $status ) . '</div>';
			$html .= '</div>';
		}
		$html .= '</div>';

		$html .= $this->renderer->section_close();

		// Render modal in wp_footer so it sits outside .zw-gr26-wrapper.
		// The wrapper's CSS transform creates a new containing block, breaking position:fixed.
		if ( self::$modal_added ) {
			return $html;
		}
		self::$modal_added = true;
		add_action(
			'wp_footer',
			function () {
				echo '<div class="zw-gr26-modal-backdrop" id="zwgr26Modal" role="dialog" aria-modal="true" aria-labelledby="zwgr26ModalTitle">';
				echo '<div class="zw-gr26-modal">';

				// Header.
				echo '<div class="zw-gr26-modal__header">';
				echo '<button class="zw-gr26-modal__refresh" id="zwgr26ModalRefresh" type="button" aria-label="Ververs uitslagen">'
					// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- SVG from Icons registry.
					. Icons::get( 'refresh' ) . '</button>';
				echo '<button class="zw-gr26-modal__close" id="zwgr26ModalClose">&times;</button>';
				echo '<div class="zw-gr26-modal__title" id="zwgr26ModalTitle"></div>';
				echo '<div class="zw-gr26-modal__subtitle" id="zwgr26ModalSubtitle"></div>';
				echo '</div>';

				// Scrollable body.
				echo '<div class="zw-gr26-modal__body">';

				// Wacht-state banner (hidden by default, shown via JS .is-wacht class).
				echo '<div class="zw-gr26-modal__wacht-banner">Hier verschijnen de uitslagen op 18 maart</div>';

				// Donut section.
				echo '<div class="zw-gr26-modal__section">';
				echo '<div class="zw-gr26-modal__section-label" id="zwgr26DonutLabel">Zetelverdeling</div>';
				echo '<div class="zw-gr26-modal__donut-area">';
				echo '<div class="zw-gr26-modal__donut" id="zwgr26Donut">';
				echo '<div class="zw-gr26-modal__donut-center">';
				echo '<div class="zw-gr26-modal__donut-total" id="zwgr26DonutTotal"></div>';
				echo '<div class="zw-gr26-modal__donut-label">zetels</div>';
				echo '<div class="zw-gr26-modal__donut-coal-label" id="zwgr26DonutCoalLabel"></div>';
				echo '<div class="zw-gr26-modal__donut-majority-label">Meerderheid!</div>';
				echo '</div></div>';
				echo '<div class="zw-gr26-modal__opkomst" id="zwgr26Opkomst"></div>';
				echo '</div></div>';

				// Table section.
				echo '<div class="zw-gr26-modal__section">';
				echo '<div class="zw-gr26-modal__section-label" id="zwgr26TableLabel">Resultaten</div>';
				echo '<table class="zw-gr26-tbl">';
				echo '<thead><tr><th colspan="2">Partij</th><th>Zetels</th><th>+/−</th></tr></thead>';
				echo '<tbody id="zwgr26Tbody"></tbody>';
				echo '</table></div>';

				echo '</div>'; // end body.

				// Coalition toggle.
				echo '<button class="zw-gr26-coal-toggle" id="zwgr26CoalToggle" type="button">Bouw coalitie</button>';

				// Coalition status bar.
				echo '<div class="zw-gr26-coal-status" id="zwgr26CoalStatus">';
				echo '<span class="zw-gr26-coal-status__text" id="zwgr26CoalStatusText">Klik op partijen om een coalitie te vormen</span>';
				echo '<button class="zw-gr26-coal-status__reset" id="zwgr26CoalReset" type="button">Wissen</button>';
				echo '</div>';

				echo '</div></div>'; // end modal + backdrop.
			}
		);

		return $html;
	}
}
