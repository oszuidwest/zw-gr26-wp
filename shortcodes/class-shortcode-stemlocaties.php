<?php
/**
 * Stemlocaties shortcode.
 *
 * @package ZWGR26
 */

namespace ZWGR26;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Renders [zw_gr26_stemlocaties] — polling station list with municipality dropdown.
 */
class Shortcode_Stemlocaties {

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
	 * Render the [zw_gr26_stemlocaties] shortcode.
	 *
	 * @param array|string $atts Shortcode attributes.
	 * @return string
	 */
	public function render( $atts ): string {
		$this->assets->enqueue();

		$atts = shortcode_atts(
			[
				'titel' => 'Stemlocaties',
			],
			$atts,
			'zw_gr26_stemlocaties'
		);

		$alle_data = $this->data->get_stemlocaties();

		if ( empty( $alle_data ) ) {
			return '';
		}

		$municipalities = $this->data->get_all_municipalities();

		$html  = $this->renderer->section_open( $atts['titel'] );
		$html .= '<div class="zwv-programma" aria-label="' . esc_attr( $atts['titel'] ) . '">';

		// Municipality dropdown.
		$html .= '<div class="zwv-programma__select-wrap">';
		$html .= '<select class="zwv-programma__select" data-zw-gr26-programma-select>';
		$html .= '<option value="">Kies je gemeente...</option>';

		foreach ( $alle_data as $gem_slug => $gemeente_data ) {
			$naam  = $municipalities[ $gem_slug ] ?? ucfirst( $gem_slug );
			$id    = 'zwv-stem-' . sanitize_title( $gem_slug );
			$html .= '<option value="' . esc_attr( $id ) . '">' . esc_html( $naam ) . '</option>';
		}

		$html .= '</select></div>';

		// Location lists per municipality.
		foreach ( $alle_data as $gem_slug => $gemeente_data ) {
			$id       = 'zwv-stem-' . sanitize_title( $gem_slug );
			$locaties = $gemeente_data['locaties'] ?? [];
			$contact  = $gemeente_data['contact'] ?? '';
			$website  = $gemeente_data['website'] ?? '';

			$html .= '<div class="zwv-programma__list zwv-stem__list" id="' . esc_attr( $id ) . '">';

			$html .= $this->render_location_rows( $locaties );
			$html .= $this->render_contact_footer( $contact, $website );

			$html .= '</div>';
		}

		$html .= '</div>';
		$html .= $this->renderer->section_close();

		return $html;
	}

	/**
	 * Render accordion rows for a list of polling stations.
	 *
	 * @param array $locaties Location data.
	 * @return string
	 */
	private function render_location_rows( array $locaties ): string {
		$count = count( $locaties );
		$html  = '<div class="zwv-stem__count">'
			. esc_html( $count . ' stemlocatie' . ( 1 !== $count ? 's' : '' ) )
			. '</div>';

		foreach ( $locaties as $loc ) {
			$html .= '<div class="zwv-stem__row">';
			$html .= $this->render_row_header( $loc );
			$html .= $this->render_row_body( $loc );
			$html .= '</div>';
		}

		return $html;
	}

	/**
	 * Render the always-visible header of an accordion row.
	 *
	 * @param array $loc Single location data.
	 * @return string
	 */
	private function render_row_header( array $loc ): string {
		$html = '<div class="zwv-stem__header">';

		// Name + time + address.
		$html .= '<div class="zwv-stem__info">';
		$html .= '<div class="zwv-stem__name">' . esc_html( $loc['naam'] );
		if ( $loc['open'] && $loc['sluit'] ) {
			$html .= ' <span class="zwv-stem__time">'
				. '(' . esc_html( $loc['open'] . ' – ' . $loc['sluit'] ) . ')'
				. '</span>';
		}
		$html .= '</div>';
		$html .= '<div class="zwv-stem__adres">' . esc_html( $loc['adres'] ) . '</div>';
		$html .= '</div>';

		// Accessibility indicator.
		$html .= '<div class="zwv-stem__summary">';
		if ( $loc['toegankelijk'] ) {
			$html .= '<span class="zwv-stem__accessible zwv-stem__accessible--ja"'
				. ' title="Rolstoeltoegankelijk">'
				. '<span class="zwv-stem__accessible-icon">&#9855;&#xFE0E;</span>'
				. '<span class="zwv-stem__accessible-label">Rolstoeltoegankelijk</span>'
				. '</span>';
		} else {
			$html .= '<span class="zwv-stem__accessible zwv-stem__accessible--nee"'
				. ' title="Niet rolstoeltoegankelijk">'
				. '<span class="zwv-stem__accessible-icon">&#9855;&#xFE0E;</span>'
				. '<span class="zwv-stem__accessible-label">Niet rolstoeltoegankelijk</span>'
				. '</span>';
		}
		$html .= '</div>';

		// Chevron.
		$html .= '<svg class="zwv-stem__chevron" viewBox="0 0 20 20" fill="none"'
			. ' stroke="currentColor" stroke-width="2" stroke-linecap="round">'
			. '<path d="M5 8l5 5 5-5"/></svg>';

		$html .= '</div>';

		return $html;
	}

	/**
	 * Render the expandable body of an accordion row.
	 *
	 * @param array $loc Single location data.
	 * @return string
	 */
	private function render_row_body( array $loc ): string {
		$features = $this->collect_features( $loc );

		$html = '<div class="zwv-stem__body"><div class="zwv-stem__details">';

		if ( ! empty( $features ) ) {
			$html .= '<div class="zwv-stem__features">';
			foreach ( $features as $label ) {
				$html .= '<span class="zwv-stem__feature">' . esc_html( $label ) . '</span>';
			}
			$html .= '</div>';
		} else {
			$html .= '<p class="zwv-stem__no-features">Geen extra voorzieningen bekend</p>';
		}

		$html .= '</div></div>';

		return $html;
	}

	/**
	 * Collect non-empty accessibility features into a label list.
	 *
	 * @param array $loc Single location data.
	 * @return string[]
	 */
	private function collect_features( array $loc ): array {
		$map = [
			'ov_halte'         => 'OV-halte',
			'toilet'           => 'Toilet',
			'geleidelijnen'    => 'Geleidelijnen',
			'audio'            => 'Audio',
			'braille'          => 'Braille',
			'grote_letters'    => 'Grote letters',
			'gebarentolk'      => 'Gebarentolk',
			'gebarentalig_lid' => 'Gebarentalig lid',
			'slechthorenden'   => 'Slechthorenden',
			'prikkelarm'       => 'Prikkelarm',
		];

		$features = [];
		foreach ( $map as $key => $label ) {
			if ( ! empty( $loc[ $key ] ) ) {
				$features[] = $label;
			}
		}

		return $features;
	}

	/**
	 * Render the municipality contact footer.
	 *
	 * @param string $contact Contact info text.
	 * @param string $website Municipality website URL.
	 * @return string
	 */
	private function render_contact_footer( string $contact, string $website ): string {
		if ( ! $contact && ! $website ) {
			return '';
		}

		$html = '<div class="zwv-stem__footer">';
		if ( $website ) {
			$html .= '<a href="' . esc_url( $website ) . '" class="zwv-stem__website"'
				. ' target="_blank" rel="noopener noreferrer">Verkiezingsinfo gemeente &rarr;</a>';
		}
		if ( $contact ) {
			$html .= '<span class="zwv-stem__contact">' . esc_html( $contact ) . '</span>';
		}
		$html .= '</div>';

		return $html;
	}
}
