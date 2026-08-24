<?php
/**
 * Panel Tabs
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
 * A list of tabs down one side and a panel beside them.
 *
 * The shape EDD's download metabox uses and the one flyouts wants: a handful
 * of named sections with an icon each — Files, Notes, Prices — where only one
 * is on screen and the rest are one click away. It is the right answer when a
 * metabox holds four unrelated groups of fields and the wrong one when it
 * holds six related ones, which is why it is a layout a caller chooses rather
 * than the only one on offer.
 *
 * It is here rather than in a metabox library because two callers already
 * want it, and because getting the accessible half right is the part nobody
 * wants to write twice.
 *
 * That half is the ARIA tabs pattern, and it is not decoration. A `<div>` and
 * a click handler gives someone using a keyboard a list of things that look
 * like buttons, announce as nothing, and cannot be moved between with the
 * arrow keys every other tab list in the admin responds to. What is here:
 *
 * - `role="tablist"`, `aria-orientation="vertical"`, each tab `role="tab"`
 *   with `aria-selected` and `aria-controls`.
 * - One tab in the tab order at a time — the selected one — so tabbing out of
 *   the list goes to the panel rather than through every other tab first.
 * - Each panel `role="tabpanel"`, named by its tab, and focusable so moving
 *   into it has somewhere to land.
 *
 * The colours are core's: `--wp-admin-theme-color` for the selection, so the
 * component follows whatever scheme the user picked rather than being blue
 * because blue is what core happened to ship with.
 */
final class PanelTabs {

	/**
	 * Render a tabbed panel.
	 *
	 * @param string                                                            $id     Base id for the tabs and panels.
	 * @param array<string, array{label?: string, icon?: string, content?: string}> $panels Panels, keyed by slug.
	 *
	 * @return string
	 */
	public static function render( string $id, array $panels ): string {
		if ( [] === $panels ) {
			return '';
		}

		$first = (string) array_key_first( $panels );
		$tabs  = '';
		$body  = '';

		foreach ( $panels as $slug => $panel ) {
			$slug     = (string) $slug;
			$selected = $slug === $first;
			$tab_id   = sprintf( '%s-tab-%s', $id, $slug );
			$panel_id = sprintf( '%s-panel-%s', $id, $slug );

			$tabs .= self::tab( $tab_id, $panel_id, (array) $panel, $selected );
			$body .= self::panel( $tab_id, $panel_id, (string) ( $panel['content'] ?? '' ), $selected );
		}

		return sprintf(
			'<div class="field-kit__panel-tabs" data-tabs="%s">' .
			'<div class="field-kit__panel-tablist" role="tablist" aria-orientation="vertical">%s</div>' .
			'<div class="field-kit__panel-bodies">%s</div>' .
			'</div>',
			esc_attr( $id ),
			$tabs,
			$body
		);
	}

	/**
	 * One tab.
	 *
	 * @param string               $tab_id   The tab's id.
	 * @param string               $panel_id The panel it controls.
	 * @param array<string, mixed> $panel    Panel configuration.
	 * @param bool                 $selected Whether it is the selected tab.
	 *
	 * @return string
	 */
	private static function tab( string $tab_id, string $panel_id, array $panel, bool $selected ): string {
		$button = new Attributes();
		$button->set( 'type', 'button' );
		$button->set( 'id', $tab_id );
		$button->add_class( 'field-kit__panel-tab' );
		$button->set_if( $selected, 'class', 'is-active' );
		$button->set( 'role', 'tab' );
		$button->set( 'aria-selected', $selected ? 'true' : 'false' );
		$button->set( 'aria-controls', $panel_id );

		// Only the selected tab is in the tab order. Arrow keys move between
		// them, which is what a tab list is for — tabbing through all six to
		// reach the panel is what happens without this.
		$button->set( 'tabindex', $selected ? '0' : '-1' );

		$icon = (string) ( $panel['icon'] ?? '' );

		return sprintf(
			'<button%s>%s<span class="field-kit__panel-tab-label">%s</span></button>',
			$button->render(),
			'' === $icon
				? ''
				: sprintf( '<span class="dashicons dashicons-%s" aria-hidden="true"></span>', esc_attr( $icon ) ),
			esc_html( (string) ( $panel['label'] ?? '' ) )
		);
	}

	/**
	 * One panel.
	 *
	 * @param string $tab_id   The tab that names it.
	 * @param string $panel_id The panel's id.
	 * @param string $content  Already-built markup.
	 * @param bool   $selected Whether it is the selected panel.
	 *
	 * @return string
	 */
	private static function panel( string $tab_id, string $panel_id, string $content, bool $selected ): string {
		$region = new Attributes();
		$region->set( 'id', $panel_id );
		$region->add_class( 'field-kit__panel-body' );
		$region->set( 'role', 'tabpanel' );
		$region->set( 'aria-labelledby', $tab_id );

		// Focusable, so moving out of the tab list has somewhere to land —
		// a panel whose first control is halfway down is otherwise reached
		// only by tabbing past everything above it.
		$region->set( 'tabindex', '0' );
		$region->set_if( ! $selected, 'hidden', true );

		return sprintf( '<div%s>%s</div>', $region->render(), $content );
	}
}
