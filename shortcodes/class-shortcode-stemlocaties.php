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

		$active_gemeente = Shortcode_Pagina::$active_gemeente;

		// Single-gemeente mode: show only this municipality, no dropdown.
		if ( $active_gemeente && isset( $alle_data[ $active_gemeente ] ) ) {
			return $this->render_single( $atts['titel'], $active_gemeente, $alle_data[ $active_gemeente ] );
		}

		// No data for the active gemeente.
		if ( $active_gemeente ) {
			return '';
		}

		$municipalities = $this->data->get_all_municipalities();

		$html  = $this->renderer->section_open( $atts['titel'] );
		$html .= '<div class="zw-gr26-programma" aria-label="' . esc_attr( $atts['titel'] ) . '">';

		// Municipality dropdown.
		$html .= '<div class="zw-gr26-programma__select-wrap">';
		$html .= '<select class="zw-gr26-programma__select" data-zw-gr26-programma-select aria-label="Kies je gemeente">';
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
	 * Renders polling stations for a single municipality (no dropdown).
	 *
	 * @param string $titel         Section title.
	 * @param string $slug          Municipality slug.
	 * @param array  $gemeente_data Location data for the municipality.
	 * @return string Section HTML.
	 */
	private function render_single( string $titel, string $slug, array $gemeente_data ): string {
		$locaties = $gemeente_data['locaties'] ?? [];
		$contact  = $gemeente_data['contact'] ?? '';
		$website  = $gemeente_data['website'] ?? '';

		$html  = $this->renderer->section_open( $titel );
		$html .= '<div class="zw-gr26-programma" aria-label="' . esc_attr( $titel ) . '">';
		$html .= '<div class="zw-gr26-programma__list zw-gr26-programma__list--open zw-gr26-stem__list"'
			. ' id="zw-gr26-stem-' . esc_attr( sanitize_title( $slug ) ) . '">';

		$html .= $this->render_location_rows( $locaties );
		$html .= $this->render_contact_footer( $contact, $website );

		$html .= '</div>';
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
		$html = '<div class="zw-gr26-stem__header" tabindex="0" role="button" aria-expanded="false">';

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
			. Icons::get( 'wheelchair' ) . '</span>';
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
		$html .= Icons::get( 'chevron', 'zw-gr26-stem__chevron' );

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
			if ( ! empty( $loc[ $key ] ) && 'nee' !== strtolower( $loc[ $key ] ) ) {
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
