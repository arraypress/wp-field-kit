<?php
/**
 * Admin Page Header
 *
 * @package     ArrayPress\FieldKit
 * @copyright   Copyright (c) 2026, ArrayPress Limited
 * @license     GPL2+
 * @since       1.0.0
 */

declare( strict_types=1 );

namespace ArrayPress\FieldKit\Support;

use ArrayPress\FieldKit\Attributes;

/**
 * The header WordPress uses on its own tabbed settings screens.
 *
 * `options-privacy.php` and `site-health.php` both render the same shape, and
 * core styles them from one shared rule in `wp-admin/css/edit.css` — which is
 * a dependency of the `wp-admin` bundle, so it is present on every admin
 * page. Reusing the class means the header matches core exactly, follows the
 * user's colour scheme and costs nothing in CSS.
 *
 * Two things do not carry over, both checked rather than assumed:
 *
 * - `.privacy-settings-tabs-wrapper` is `grid-template-columns: 1fr 1fr`,
 *   hardcoded to the two tabs that screen has. Site Health declares its own
 *   at four. Neither generalises, so the wrapper here is the kit's own and is
 *   the one place this costs a few lines of CSS. The individual
 *   `.privacy-settings-tab` links carry no such assumption and are reused.
 * - `<hr class="wp-header-end">` is not decoration. `common.js` looks for it
 *   and moves admin notices to sit directly after it; without one, notices
 *   are appended after the first `h1` or `h2` on the page, which on a tabbed
 *   screen means somewhere arbitrary.
 */
final class PageHeader {

	/**
	 * Render the header.
	 *
	 * @param string                                                   $title    Page title.
	 * @param array<string, array{label: string, url: string}>          $tabs     Tabs, keyed by slug.
	 * @param string                                                   $current  Slug of the active tab.
	 * @param string                                                   $actions  Optional markup for the right-hand side.
	 *
	 * @return string
	 */
	public static function render( string $title, array $tabs = [], string $current = '', string $actions = '' ): string {
		$markup = sprintf(
			'<div class="privacy-settings-header field-kit__page-header">' .
			'<div class="privacy-settings-title-section"><h1>%s</h1></div>',
			esc_html( $title )
		);

		if ( '' !== $actions ) {
			$markup .= sprintf( '<div class="field-kit__page-actions">%s</div>', $actions );
		}

		if ( [] !== $tabs ) {
			$markup .= self::tabs( $tabs, $current );
		}

		// Where admin notices land. Not optional.
		return $markup . '</div><hr class="wp-header-end">';
	}

	/**
	 * Render the tab navigation.
	 *
	 * @param array<string, array{label: string, url: string}> $tabs    Tabs, keyed by slug.
	 * @param string                                           $current Slug of the active tab.
	 *
	 * @return string
	 */
	private static function tabs( array $tabs, string $current ): string {
		$current = '' === $current ? (string) array_key_first( $tabs ) : $current;
		$links   = '';

		foreach ( $tabs as $slug => $tab ) {
			$active = (string) $slug === $current;

			$link = new Attributes();
			$link->set( 'href', (string) ( $tab['url'] ?? '#' ) );
			$link->add_class( 'privacy-settings-tab', 'field-kit__page-tab' );
			$link->set_if( $active, 'class', 'active' );

			// aria-current is what conveys the selection; the class is only
			// how it is drawn.
			$link->set_if( $active, 'aria-current', 'true' );

			$links .= sprintf( '<a%s>%s</a>', $link->render(), esc_html( (string) ( $tab['label'] ?? $slug ) ) );
		}

		return sprintf(
			// Labelled, because a page can hold more than one nav and an
			// unlabelled one is announced only as "navigation".
			'<nav class="field-kit__page-tabs hide-if-no-js" aria-label="%s">%s</nav>',
			esc_attr__( 'Secondary menu', 'arraypress' ),
			$links
		);
	}
}
