<?php
/**
 * Shared HTML rendering helpers.
 *
 * @package ZWGR26
 */

namespace ZWGR26;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Builds reusable HTML fragments for sections, cards, and the hero header.
 */
class Renderer {

	/**
	 * Open a content section with a title and optional link.
	 *
	 * @param string $title     Section heading.
	 * @param string $link      Optional URL for the "more" link.
	 * @param string $link_text Optional label for the "more" link.
	 * @return string
	 */
	public function section_open( string $title, string $link = '', string $link_text = '' ): string {
		$html  = '<section class="zwv-section">';
		$html .= '<div class="zwv-section__header">';
		$html .= '<h2 class="zwv-section__title">' . esc_html( $title ) . '</h2>';

		if ( $link ) {
			$html .= '<a href="' . esc_url( $link ) . '" class="zwv-section__link">'
				. esc_html( $link_text ? $link_text : 'Bekijk meer' ) . ' &rarr;</a>';
		}

		$html .= '</div>';

		return $html;
	}

	/**
	 * Close a content section.
	 *
	 * @return string
	 */
	public function section_close(): string {
		return '</section>';
	}

	/**
	 * Render the hero header with background image.
	 *
	 * @param string $title    Main heading.
	 * @param string $subtitle Subtitle text.
	 * @param string $bg_image Background image URL.
	 * @return string
	 */
	public function hero( string $title, string $subtitle, string $bg_image ): string {
		$html  = '<header class="zwv-hero">';
		$html .= '<div class="zwv-hero__bg" style="background-image:url(' . esc_url( $bg_image ) . ')"></div>';
		$html .= '<div class="zwv-hero__content">';
		$html .= '<h1 class="zwv-hero__title">' . esc_html( $title ) . '</h1>';

		if ( $subtitle ) {
			$html .= '<p class="zwv-hero__subtitle">' . esc_html( $subtitle ) . '</p>';
		}

		$html .= '</div></header>';

		return $html;
	}

	/**
	 * Render the red-white-blue decorative stripe.
	 *
	 * @return string
	 */
	public function stripe(): string {
		return '<div class="zwv-stripe">'
			. '<span class="zwv-stripe--red"></span>'
			. '<span class="zwv-stripe--white"></span>'
			. '<span class="zwv-stripe--blue"></span>'
			. '</div>';
	}

	/**
	 * Render a video card with thumbnail, title, and optional "coming soon" state.
	 *
	 * @param array $video       Video data with titel, thumbnail, url, and optional meta.
	 * @param bool  $coming_soon Whether the video is not yet available.
	 * @return string
	 */
	public function video_card( array $video, bool $coming_soon = false ): string {
		$tag  = $coming_soon ? 'div' : 'a';
		$href = $coming_soon ? '' : ' href="' . esc_url( $video['url'] ) . '"';

		$has_thumb = ! empty( $video['thumbnail'] );

		$html  = '<article class="zwv-vcard' . ( $coming_soon ? ' zwv-binnenkort' : '' ) . '">';
		$html .= '<' . $tag . $href . ' class="zwv-vcard__link">';

		if ( $has_thumb ) {
			$html .= '<img src="' . esc_url( $video['thumbnail'] ) . '" alt="' . esc_attr( $video['titel'] ) . '" loading="lazy">';
		}

		if ( $coming_soon ) {
			$html .= '<span class="zwv-binnenkort__badge">Binnenkort</span>';
		} else {
			$html .= '<div class="zwv-vcard__play"><span>&#9654;&#xFE0E;</span> Bekijk</div>';
		}

		$html .= '<div class="zwv-vcard__overlay">';
		$html .= '<h3 class="zwv-vcard__title">' . esc_html( $video['titel'] ) . '</h3>';

		if ( ! empty( $video['meta'] ) ) {
			$html .= '<div class="zwv-vcard__meta">' . esc_html( $video['meta'] ) . '</div>';
		}

		$html .= '</div>';
		$html .= '</' . $tag . '>';
		$html .= '</article>';

		return $html;
	}

	/**
	 * Render an explainer card (9:16 aspect ratio).
	 *
	 * @param array $video Video data with titel, thumbnail, url, and optional binnenkort flag.
	 * @return string
	 */
	public function explainer_card( array $video ): string {
		$coming_soon = ! empty( $video['binnenkort'] );
		$has_thumb   = ! empty( $video['thumbnail'] );
		$tag         = $coming_soon ? 'div' : 'a';
		$href        = $coming_soon ? '' : ' href="' . esc_url( $video['url'] ) . '"';

		$html  = '<article class="zwv-ecard' . ( $coming_soon ? ' zwv-ecard--binnenkort' : '' ) . '">';
		$html .= '<' . $tag . $href . ' class="zwv-ecard__link">';

		if ( $has_thumb ) {
			$html .= '<img src="' . esc_url( $video['thumbnail'] ) . '" alt="' . esc_attr( $video['titel'] ) . '" loading="lazy">';
		}

		if ( $coming_soon ) {
			$html .= '<span class="zwv-binnenkort__badge">Binnenkort</span>';
		}

		$html .= '<div class="zwv-ecard__info">';
		$html .= '<h3 class="zwv-ecard__title">' . esc_html( $video['titel'] ) . '</h3>';
		$html .= '</div>';
		$html .= '</' . $tag . '>';
		$html .= '</article>';

		return $html;
	}

	/**
	 * Render a news article card.
	 *
	 * @param array $item Article data with url, titel, datum, datum_iso, afbeelding, and regio.
	 * @return string
	 */
	public function article_card( array $item ): string {
		$html  = '<article class="zwv-acard">';
		$html .= '<a href="' . esc_url( $item['url'] ) . '" class="zwv-acard__link">';
		$html .= '<div class="zwv-acard__thumb">';

		if ( ! empty( $item['afbeelding'] ) ) {
			$html .= '<img src="' . esc_url( $item['afbeelding'] ) . '" alt="' . esc_attr( $item['titel'] ) . '" loading="lazy">';
		}

		$html .= '</div>';
		$html .= '<div class="zwv-acard__info">';

		if ( ! empty( $item['regio'] ) ) {
			$html .= '<span class="zwv-acard__tag">' . esc_html( $item['regio'] ) . '</span>';
		}

		$html .= '<h3 class="zwv-acard__title">' . esc_html( $item['titel'] ) . '</h3>';
		$html .= '<time class="zwv-acard__meta" datetime="' . esc_attr( $item['datum_iso'] ) . '">'
			. esc_html( $item['datum'] ) . '</time>';
		$html .= '</div>';
		$html .= '</a></article>';

		return $html;
	}
}
