<?php
/**
 * Standalone uitslag tabel shortcode.
 *
 * @package ZWGR26
 */

namespace ZWGR26;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Renders [zw_gr26_uitslag_tabel] — a lightweight, standalone HTML table
 * of election results that can be used in regular articles without the
 * page wrapper shortcodes or plugin JS/CSS.
 */
class Shortcode_Uitslag_Tabel {

	/**
	 * Whether the inline <style> block has already been rendered.
	 *
	 * @var bool
	 */
	private static bool $style_rendered = false;

	/**
	 * Data provider.
	 *
	 * @var Data_Provider
	 */
	private Data_Provider $data;

	/**
	 * Constructor.
	 *
	 * @param Data_Provider $data Data provider.
	 */
	public function __construct( Data_Provider $data ) {
		$this->data = $data;
	}

	/**
	 * Renders the shortcode.
	 *
	 * @param array|string $atts Shortcode attributes.
	 * @return string Shortcode HTML output.
	 */
	public function render( $atts ): string {
		$atts = shortcode_atts(
			[
				'gemeente' => '',
			],
			$atts,
			'zw_gr26_uitslag_tabel'
		);

		$slug = sanitize_title( $atts['gemeente'] );
		if ( '' === $slug ) {
			return '<!-- zw_gr26_uitslag_tabel: geen gemeente opgegeven -->';
		}

		$results = $this->data->get_election_results();
		if ( ! isset( $results[ $slug ] ) ) {
			return '<!-- zw_gr26_uitslag_tabel: gemeente "' . esc_html( $slug ) . '" niet gevonden -->';
		}

		$entry = $results[ $slug ];

		if ( empty( $entry['has_2026'] ) ) {
			return '<p><em>Hier verschijnen de uitslagen binnenkort.</em></p>';
		}

		if ( empty( $entry['partijen'] ) ) {
			return '<!-- zw_gr26_uitslag_tabel: geen partijdata voor "' . esc_html( $slug ) . '" -->';
		}

		$cell = 'padding:8px 12px';
		$html = '';

		if ( ! self::$style_rendered ) {
			$html                .= '<style>'
				. '.zw-gr26-uitslag-tabel thead th{background:#1b3f94;color:#fff}'
				. '.dark .zw-gr26-uitslag-tabel thead th{background:#243f7a;color:#fff}'
				. '.zw-gr26-uitslag-tabel tr.zw-gr26-stripe{background:rgba(0,0,0,.04)}'
				. '.dark .zw-gr26-uitslag-tabel tr.zw-gr26-stripe{background:rgba(255,255,255,.05)}'
				. '</style>';
			self::$style_rendered = true;
		}

		$html .= $this->render_opkomst( $entry );

		$html .= '<table class="zw-gr26-uitslag-tabel" style="border-collapse:collapse">';
		$html .= '<thead><tr>';
		$html .= '<th style="' . $cell . ';text-align:left">Partij</th>';
		$html .= '<th style="' . $cell . '">Zetels</th>';
		$html .= '<th style="' . $cell . '">+/−</th>';
		$html .= '</tr></thead>';
		$html .= '<tbody>';

		$row_index = 0;
		$verdwenen = [];
		foreach ( $entry['partijen'] as $partij ) {
			// Collect disappeared parties for the text line below the table.
			if ( 0 === (int) $partij['zetels'] && null !== $partij['zetels_2022'] && $partij['zetels_2022'] > 0 ) {
				$verdwenen[] = $partij['naam'] . ' (' . $partij['zetels_2022'] . "\u{2009}\u{2192}\u{2009}0)";
				continue;
			}

			$stripe = 0 === $row_index % 2 ? ' class="zw-gr26-stripe"' : '';
			$html  .= '<tr' . $stripe . '>';
			++$row_index;
			$html .= '<td style="' . $cell . '"><span style="display:inline-block;width:10px;height:10px;border-radius:50%;background:'
				. esc_attr( $partij['kleur'] ) . ';margin-right:8px;vertical-align:middle"></span>'
				. esc_html( $partij['naam'] ) . '</td>';
			$html .= '<td style="' . $cell . ';text-align:center">' . (int) $partij['zetels'] . '</td>';
			$diff  = $this->format_difference( $partij['zetels'], $partij['zetels_2022'] );
			$html .= '<td style="' . $cell . ';text-align:center">' . esc_html( $diff ) . '</td>';
			$html .= '</tr>';
		}

		$html .= '</tbody></table>';

		if ( ! empty( $verdwenen ) ) {
			$html .= '<p style="font-size:.8em;color:#888;margin-top:10px">Niet teruggekeerd: '
				. esc_html( implode( ', ', $verdwenen ) ) . '</p>';
		}

		$cta_url = $this->get_gemeente_page_url( $slug );
		if ( '' !== $cta_url ) {
			$anchor  = sanitize_title( 'Uitslag ' . $entry['naam'] );
			$cta_url = $cta_url . '#' . $anchor;
		} else {
			$cta_url = $this->data->get_main_page_url();
		}

		if ( '' !== $cta_url ) {
			$html .= '<style>.zw-gr26-uitslag-cta{border:2px solid #1b3f94;border-radius:8px}'
				. '.zw-gr26-uitslag-cta a{color:#1b3f94}'
				. '.dark .zw-gr26-uitslag-cta{border-color:#5c7cca}'
				. '.dark .zw-gr26-uitslag-cta a{color:#8ea8e8}</style>'
				. '<div class="zw-gr26-uitslag-cta" style="margin-top:12px;padding:10px 16px;font-weight:700;font-size:.9em">'
				. '<a href="' . esc_url( $cta_url )
				. '" style="text-decoration:none;display:flex;align-items:center;gap:10px">'
				. '<span style="width:8px;height:8px;border-radius:50%;background:#cc2229;flex-shrink:0"></span>'
				. 'Bouw zelf een coalitie met onze coalitiebouwer'
				. '<span style="font-size:1.2em;margin-left:auto">&rarr;</span></a></div>';
		}

		return $html;
	}

	/**
	 * Formats the seat difference between 2026 and 2022.
	 *
	 * @param int      $zetels_2026 Seats in 2026.
	 * @param int|null $zetels_2022 Seats in 2022, or null if the party is new.
	 * @return string Formatted difference string.
	 */
	private function format_difference( int $zetels_2026, ?int $zetels_2022 ): string {
		if ( null === $zetels_2022 ) {
			return 'NW';
		}

		$diff = $zetels_2026 - $zetels_2022;

		if ( $diff > 0 ) {
			return '+' . $diff;
		}
		if ( $diff < 0 ) {
			// U+2212 MINUS SIGN.
			return "\u{2212}" . abs( $diff );
		}
		return '0';
	}

	/**
	 * Looks up the gemeente page URL for a given slug.
	 *
	 * @param string $slug Municipality slug.
	 * @return string Page URL, or empty string if not found.
	 */
	private function get_gemeente_page_url( string $slug ): string {
		foreach ( $this->data->get_gemeente_pages() as $page ) {
			if ( $page['slug'] === $slug ) {
				return (string) $page['url'];
			}
		}

		return '';
	}

	/**
	 * Renders turnout as a progress bar with 2022 reference marker.
	 *
	 * @param array<string, mixed> $entry Municipality election data.
	 * @return string HTML turnout bar, or empty string if no data.
	 */
	private function render_opkomst( array $entry ): string {
		$val_2026 = $entry['opkomst_2026'];
		$val_2022 = $entry['opkomst_2022'];

		if ( null === $val_2026 && null === $val_2022 ) {
			return '';
		}

		$label_2026 = null !== $val_2026
			? '<strong style="color:#1a1a1a">Opkomst 2026:</strong> ' . number_format( $val_2026, 1, ',', '.' ) . '%'
			: '<strong style="color:#1a1a1a">Opkomst 2026:</strong> nog niet bekend';

		$label_2022 = null !== $val_2022
			? '2022: ' . number_format( $val_2022, 1, ',', '.' ) . '%'
			: '';

		$html  = '<div style="margin-bottom:14px">';
		$html .= '<div style="display:flex;justify-content:space-between;font-size:.8em;color:#666;margin-bottom:4px">';
		$html .= '<span>' . $label_2026 . '</span>';

		if ( '' !== $label_2022 ) {
			$html .= '<span>' . esc_html( $label_2022 ) . '</span>';
		}

		$html .= '</div>';

		// Progress bar track.
		$html .= '<div style="background:#e8e8e8;border-radius:6px;height:10px;position:relative;overflow:hidden">';

		// 2026 fill.
		$width_2026 = null !== $val_2026 ? min( (float) $val_2026, 100.0 ) : 0.0;
		$html      .= '<div style="background:linear-gradient(90deg,#1b3f94,#3a6fd8);width:'
			. esc_attr( number_format( $width_2026, 1 ) ) . '%;height:100%;border-radius:6px"></div>';

		// 2022 reference marker.
		if ( null !== $val_2022 ) {
			$left  = min( (float) $val_2022, 100.0 );
			$html .= '<div style="position:absolute;left:' . esc_attr( number_format( $left, 1 ) )
				. '%;top:0;width:2px;height:100%;background:#cc2229" title="'
				. esc_attr( '2022: ' . number_format( $val_2022, 1, ',', '.' ) . '%' ) . '"></div>';
		}

		$html .= '</div></div>';

		return $html;
	}
}
