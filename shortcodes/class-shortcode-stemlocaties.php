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
	 * Renders the [zw_gr26_stemlocaties] shortcode.
	 *
	 * @param array|string $atts Shortcode attributes.
	 * @return string Shortcode HTML output.
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
		$html .= '<div class="zw-gr26-programma" aria-label="' . esc_attr( $atts['titel'] ) . '">';

		// Municipality dropdown.
		$html .= '<div class="zw-gr26-programma__select-wrap">';
		$html .= '<select class="zw-gr26-programma__select" data-zw-gr26-programma-select>';
		$html .= '<option value="">Kies je gemeente...</option>';

		foreach ( $alle_data as $gem_slug => $gemeente_data ) {
			$naam  = $municipalities[ $gem_slug ] ?? ucfirst( $gem_slug );
			$id    = 'zw-gr26-stem-' . sanitize_title( $gem_slug );
			$html .= '<option value="' . esc_attr( $id ) . '">' . esc_html( $naam ) . '</option>';
		}

		$html .= '</select></div>';

		// Location lists per municipality.
		foreach ( $alle_data as $gem_slug => $gemeente_data ) {
			$id       = 'zw-gr26-stem-' . sanitize_title( $gem_slug );
			$locaties = $gemeente_data['locaties'] ?? [];
			$contact  = $gemeente_data['contact'] ?? '';
			$website  = $gemeente_data['website'] ?? '';

			$html .= '<div class="zw-gr26-programma__list zw-gr26-stem__list" id="' . esc_attr( $id ) . '">';

			$html .= $this->render_location_rows( $locaties );
			$html .= $this->render_contact_footer( $contact, $website );

			$html .= '</div>';
		}

		$html .= '</div>';
		$html .= $this->renderer->section_close();

		return $html;
	}

	/**
	 * Renders accordion rows for a list of polling stations.
	 *
	 * @param array $locaties Location data.
	 * @return string Accordion rows HTML.
	 */
	private function render_location_rows( array $locaties ): string {
		$count = count( $locaties );
		$html  = '<div class="zw-gr26-stem__count">'
			. esc_html( $count . ' stemlocatie' . ( 1 !== $count ? 's' : '' ) )
			. '</div>';

		foreach ( $locaties as $loc ) {
			$html .= '<div class="zw-gr26-stem__row">';
			$html .= $this->render_row_header( $loc );
			$html .= $this->render_row_body( $loc );
			$html .= '</div>';
		}

		return $html;
	}

	/**
	 * Renders the always-visible header of an accordion row.
	 *
	 * @param array $loc Single location data.
	 * @return string Row header HTML.
	 */
	private function render_row_header( array $loc ): string {
		$html = '<div class="zw-gr26-stem__header">';

		// Name + time + address.
		$html .= '<div class="zw-gr26-stem__info">';
		$html .= '<div class="zw-gr26-stem__name">' . esc_html( $loc['naam'] );
		if ( $loc['open'] && $loc['sluit'] ) {
			$html .= ' <span class="zw-gr26-stem__time">'
				. '(' . esc_html( $loc['open'] . ' – ' . $loc['sluit'] ) . ')'
				. '</span>';
		}
		$html .= '</div>';
		$html .= '<div class="zw-gr26-stem__adres">' . esc_html( $loc['adres'] ) . '</div>';
		$html .= '</div>';

		// Accessibility indicator.
		$icon  = '<span class="zw-gr26-stem__accessible-icon">'
			. '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 512 512"'
			. ' fill="currentColor" aria-hidden="true">'
			. '<path d="M256 112a56 56 0 1 1 56-56 56.06 56.06 0 0 1-56 56z"/>'
			. '<path d="M432 112.8l-.45.12-.42.13c-1 .28-2 .58-3 .89'
			. '-18.61 5.46-108.93 30.92-172.56 30.92-59.13'
			. ' 0-141.28-22-167.56-29.47a73.79 73.79 0 0 0-8-2.58c-19-5-32'
			. ' 14.3-32 31.94 0 17.47 15.7 25.79 31.55 31.76v.28l95.22'
			. ' 29.74c9.73 3.73 12.33 7.54 13.6 10.84 4.13 10.59.83'
			. ' 31.56-.34 38.88l-5.8 45L150.05 477.44q-.15.72-.27'
			. ' 1.47l-.23 1.27c-2.32 16.15 9.54 31.82 32 31.82 19.6 0'
			. ' 28.25-13.53 32-31.94s28-157.57 42-157.57 42.84 157.57'
			. ' 42.84 157.57c3.75 18.41 12.4 31.94 32 31.94 22.52 0'
			. ' 34.38-15.74 32-31.94-.21-1.38-.46-2.74-.76-4.06L329'
			. ' 301.27l-5.79-45c-4.19-26.21-.82-34.87.32-36.9a1.09 1.09'
			. ' 0 0 0 .08-.15c1.08-2 6-6.48 17.48-10.79l89.28-31.21a16.9'
			. ' 16.9 0 0 0 1.62-.52c16-6 32-14.3 32-31.93s-19-37.99-38'
			. '-33z"/></svg></span>';
		$html .= '<div class="zw-gr26-stem__summary">';
		if ( 'nee' !== strtolower( $loc['toegankelijk'] ) && $loc['toegankelijk'] ) {
			$html .= '<span class="zw-gr26-stem__accessible zw-gr26-stem__accessible--ja"'
				. ' title="Rolstoeltoegankelijk">'
				. $icon
				. '<span class="zw-gr26-stem__accessible-label">Rolstoeltoegankelijk</span>'
				. '</span>';
		} else {
			$html .= '<span class="zw-gr26-stem__accessible zw-gr26-stem__accessible--nee"'
				. ' title="Niet rolstoeltoegankelijk">'
				. $icon
				. '<span class="zw-gr26-stem__accessible-label">Niet rolstoeltoegankelijk</span>'
				. '</span>';
		}
		$html .= '</div>';

		// Chevron.
		$html .= '<svg class="zw-gr26-stem__chevron" viewBox="0 0 20 20" fill="none"'
			. ' stroke="currentColor" stroke-width="2" stroke-linecap="round">'
			. '<path d="M5 8l5 5 5-5"/></svg>';

		$html .= '</div>';

		return $html;
	}

	/**
	 * Renders the expandable body of an accordion row.
	 *
	 * @param array $loc Single location data.
	 * @return string Row body HTML.
	 */
	private function render_row_body( array $loc ): string {
		$features = $this->collect_features( $loc );

		$html = '<div class="zw-gr26-stem__body"><div class="zw-gr26-stem__details">';

		if ( ! empty( $features ) ) {
			$html .= '<div class="zw-gr26-stem__features">';
			foreach ( $features as $label ) {
				$html .= '<span class="zw-gr26-stem__feature">' . esc_html( $label ) . '</span>';
			}
			$html .= '</div>';
		} else {
			$html .= '<p class="zw-gr26-stem__no-features">Geen extra voorzieningen bekend</p>';
		}

		$html .= '</div></div>';

		return $html;
	}

	/**
	 * Collects non-empty accessibility features into a label list.
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
	 * Renders the municipality contact footer.
	 *
	 * @param string $contact Contact info text.
	 * @param string $website Municipality website URL.
	 * @return string Footer HTML.
	 */
	private function render_contact_footer( string $contact, string $website ): string {
		if ( ! $contact && ! $website ) {
			return '';
		}

		$html = '<div class="zw-gr26-stem__footer">';
		if ( $website ) {
			$html .= '<a href="' . esc_url( $website ) . '" class="zw-gr26-stem__website"'
				. ' target="_blank" rel="noopener noreferrer">Verkiezingsinfo gemeente &rarr;</a>';
		}
		if ( $contact ) {
			$html .= '<span class="zw-gr26-stem__contact">' . esc_html( $contact ) . '</span>';
		}
		$html .= '</div>';

		return $html;
	}
}
