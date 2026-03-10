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
	 * Render the shortcode.
	 *
	 * @param array|string $atts Shortcode attributes.
	 * @return string
	 */
	public function render( $atts ): string {
		$this->assets->enqueue();

		$atts = shortcode_atts(
			[
				'titel' => 'Uitslagen per gemeente',
				'noot'  => '',
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
		if ( ! empty( $results ) ) {
			$json = wp_json_encode( $results, JSON_UNESCAPED_UNICODE );
			wp_add_inline_script( 'zw-gr26', 'var zwGr26Resultaten=' . $json . ';', 'before' );
		}

		$html = $this->renderer->section_open( $atts['titel'] );

		// Municipality tiles — always show all 10, always clickable.
		$html .= '<div class="zwv-tiles">';
		foreach ( $municipalities as $slug => $naam ) {
			$entry    = $results[ $slug ] ?? null;
			$has_2026 = $entry && ! empty( $entry['has_2026'] );
			$status   = $has_2026 ? 'Bekijk uitslagen' : 'Nog geen uitslagen';

			$html .= '<div class="zwv-tile" data-gemeente="' . esc_attr( $slug ) . '">';
			$html .= '<h3 class="zwv-tile__name">' . esc_html( $naam ) . '</h3>';
			$html .= '<div class="zwv-tile__status">' . esc_html( $status ) . '</div>';
			$html .= '</div>';
		}
		$html .= '</div>';

		$html .= $this->renderer->section_close();

		// Render modal in wp_footer so it sits outside .zwv-wrapper.
		// The wrapper's CSS transform creates a new containing block, breaking position:fixed.
		$note_html = $atts['noot']
			? esc_html( $atts['noot'] )
			: 'Bron: gemeente';

		add_action(
			'wp_footer',
			function () {
				echo '<div class="zwv-modal-backdrop" id="zwvModal" role="dialog" aria-modal="true" aria-labelledby="zwvModalTitle">';
				echo '<div class="zwv-modal">';

				// Header.
				echo '<div class="zwv-modal__header">';
				echo '<button class="zwv-modal__close" id="zwvModalClose">&times;</button>';
				echo '<div class="zwv-modal__title" id="zwvModalTitle"></div>';
				echo '<div class="zwv-modal__subtitle" id="zwvModalSubtitle"></div>';
				echo '</div>';

				// Scrollable body.
				echo '<div class="zwv-modal__body">';

				// Wacht-state banner (hidden by default, shown via JS .is-wacht class).
				echo '<div class="zwv-modal__wacht-banner">Uitslagen volgen op verkiezingsavond</div>';

				// Donut section.
				echo '<div class="zwv-modal__section">';
				echo '<div class="zwv-modal__section-label" id="zwvDonutLabel">Zetelverdeling</div>';
				echo '<div class="zwv-modal__donut-area">';
				echo '<div class="zwv-modal__donut" id="zwvDonut">';
				echo '<div class="zwv-modal__donut-center">';
				echo '<div class="zwv-modal__donut-total" id="zwvDonutTotal"></div>';
				echo '<div class="zwv-modal__donut-label">zetels</div>';
				echo '<div class="zwv-modal__donut-coal-label" id="zwvDonutCoalLabel"></div>';
				echo '<div class="zwv-modal__donut-majority-label">Meerderheid!</div>';
				echo '</div></div>';
				echo '<div class="zwv-modal__opkomst" id="zwvOpkomst"></div>';
				echo '</div></div>';

				// Table section.
				echo '<div class="zwv-modal__section">';
				echo '<div class="zwv-modal__section-label" id="zwvTableLabel">Resultaten</div>';
				echo '<table class="zwv-tbl">';
				echo '<thead><tr><th colspan="2">Partij</th><th>Zetels</th><th>+/−</th></tr></thead>';
				echo '<tbody id="zwvTbody"></tbody>';
				echo '</table></div>';

				echo '</div>'; // end body.

				// Coalition toggle.
				echo '<button class="zwv-coal-toggle" id="zwvCoalToggle" type="button">Bouw coalitie</button>';

				// Coalition status bar.
				echo '<div class="zwv-coal-status" id="zwvCoalStatus">';
				echo '<span class="zwv-coal-status__text" id="zwvCoalStatusText">Klik op partijen om een coalitie te vormen</span>';
				echo '<button class="zwv-coal-status__reset" id="zwvCoalReset" type="button">Wissen</button>';
				echo '</div>';

				echo '</div></div>'; // end modal + backdrop.
			}
		);

		return $html;
	}
}
