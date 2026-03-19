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
				. '.zw-gr26-uitslag-diff{font-size:.8em;font-weight:600;padding:2px 6px;border-radius:4px}'
				. '.zw-gr26-uitslag-diff--plus{color:#2e7d32;background:#e8f5e9}'
				. '.zw-gr26-uitslag-diff--min{color:#c62828;background:#ffebee}'
				. '.zw-gr26-uitslag-diff--nw{color:#1565c0;background:#e3f2fd}'
				. '.zw-gr26-uitslag-opkomst{display:flex;gap:8px;align-items:center;margin-bottom:8px;flex-wrap:wrap}'
				. '@media(max-width:480px){.zw-gr26-uitslag-opkomst span{font-size:.72em!important;padding:4px 9px!important}}'
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
			// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- HTML from format_difference() with safe values.
			$html .= '<td style="' . $cell . ';text-align:center">' . $diff . '</td>';
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
	 * Returns an HTML badge showing the seat difference between 2026 and 2022.
	 *
	 * @param int      $zetels_2026 Seats in 2026.
	 * @param int|null $zetels_2022 Seats in 2022, or null if the party is new.
	 * @return string HTML span element, or empty string if no change.
	 */
	private function format_difference( int $zetels_2026, ?int $zetels_2022 ): string {
		if ( null === $zetels_2022 ) {
			return '<span class="zw-gr26-uitslag-diff zw-gr26-uitslag-diff--nw">NW</span>';
		}

		$diff = $zetels_2026 - $zetels_2022;

		if ( $diff > 0 ) {
			return '<span class="zw-gr26-uitslag-diff zw-gr26-uitslag-diff--plus">+' . $diff . '</span>';
		}
		if ( $diff < 0 ) {
			// U+2212 MINUS SIGN.
			return '<span class="zw-gr26-uitslag-diff zw-gr26-uitslag-diff--min">' . "\u{2212}" . abs( $diff ) . '</span>';
		}
		return '';
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
	 * Renders turnout as pill badges above the table.
	 *
	 * @param array<string, mixed> $entry Municipality election data.
	 * @return string HTML pill badges, or empty string if no data.
	 */
	private function render_opkomst( array $entry ): string {
		$val_2026 = $entry['opkomst_2026'];
		$val_2022 = $entry['opkomst_2022'];

		if ( null === $val_2026 && null === $val_2022 ) {
			return '';
		}

		$html = '<div class="zw-gr26-uitslag-opkomst">';

		if ( null !== $val_2026 ) {
			$html .= '<span style="background:#1b3f94;color:#fff;padding:5px 12px;border-radius:20px;font-size:.82em;font-weight:700">'
				. 'Opkomst 2026: ' . esc_html( number_format( $val_2026, 1, ',', '.' ) ) . '%</span>';
		} else {
			$html .= '<span style="background:#1b3f94;color:#fff;padding:5px 12px;border-radius:20px;font-size:.82em;font-weight:700">'
				. 'Opkomst 2026: nog niet bekend</span>';
		}

		if ( null !== $val_2022 ) {
			$html .= '<span style="background:#e8e8e8;color:#555;padding:5px 12px;border-radius:20px;font-size:.82em;font-weight:600">'
				. 'Opkomst 2022: ' . esc_html( number_format( $val_2022, 1, ',', '.' ) ) . '%</span>';
		}

		$html .= '</div>';

		return $html;
	}
}
