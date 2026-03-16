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

		$entry   = $results[ $slug ];
		$is_2026 = ! empty( $entry['has_2026'] );

		if ( empty( $entry['partijen'] ) ) {
			return '<!-- zw_gr26_uitslag_tabel: geen partijdata voor "' . esc_html( $slug ) . '" -->';
		}

		$cell      = 'padding:8px 12px';
		$col_count = $is_2026 ? 3 : 2;
		$html      = '<style>.zwgr26-uitslag-tabel tr.zwgr26-stripe{background:rgba(0,0,0,.04)}'
			. '.dark .zwgr26-uitslag-tabel tr.zwgr26-stripe{background:rgba(255,255,255,.05)}</style>';
		$html     .= '<table class="zwgr26-uitslag-tabel" style="border-collapse:collapse">';
		$html     .= '<thead><tr>';
		$html     .= '<th style="' . $cell . ';text-align:left">Partij</th>';
		$html     .= $is_2026
			? '<th style="' . $cell . '">Zetels</th><th style="' . $cell . '">+/−</th>'
			: '<th style="' . $cell . '">Zetels 2022</th>';
		$html     .= '</tr></thead>';
		$html     .= '<tbody>';

		$row_index = 0;
		foreach ( $entry['partijen'] as $partij ) {
			$stripe = 0 === $row_index % 2 ? ' class="zwgr26-stripe"' : '';
			$html  .= '<tr' . $stripe . '>';
			++$row_index;
			$html .= '<td style="' . $cell . '"><span style="display:inline-block;width:10px;height:10px;border-radius:50%;background:'
				. esc_attr( $partij['kleur'] ) . ';margin-right:8px;vertical-align:middle"></span>'
				. esc_html( $partij['naam'] ) . '</td>';

			if ( $is_2026 ) {
				$html .= '<td style="' . $cell . ';text-align:center">' . (int) $partij['zetels'] . '</td>';
				$diff  = $this->format_difference( $partij['zetels'], $partij['zetels_2022'] );
				$html .= '<td style="' . $cell . ';text-align:center">' . esc_html( $diff ) . '</td>';
			} else {
				$html .= '<td style="' . $cell . ';text-align:center">' . (int) $partij['zetels_2022'] . '</td>';
			}

			$html .= '</tr>';
		}

		$html .= $this->render_opkomst( $entry, $is_2026, $cell, $col_count );
		$html .= '</tbody></table>';

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
	 * Renders turnout percentages as a table footer row.
	 *
	 * @param array<string, mixed> $entry     Municipality election data.
	 * @param bool                 $is_2026   Whether 2026 results are available.
	 * @param string               $cell      Shared inline padding style.
	 * @param int                  $col_count Number of columns to span.
	 * @return string HTML table row with turnout data, or empty string.
	 */
	private function render_opkomst( array $entry, bool $is_2026, string $cell, int $col_count ): string {
		$parts = [];

		if ( $is_2026 && null !== $entry['opkomst_2026'] ) {
			$parts[] = 'Opkomst 2026: ' . number_format( $entry['opkomst_2026'], 1, ',', '.' ) . '%';
		}

		if ( null !== $entry['opkomst_2022'] ) {
			$parts[] = 'Opkomst 2022: ' . number_format( $entry['opkomst_2022'], 1, ',', '.' ) . '%';
		}

		if ( empty( $parts ) ) {
			return '';
		}

		$border = 'border-top:1px solid rgba(128,128,128,.25)';

		return '<tr><td colspan="' . $col_count . '" style="' . $cell . ';' . $border
			. ';font-size:.9em;opacity:.7">' . esc_html( implode( ' · ', $parts ) ) . '</td></tr>';
	}
}
