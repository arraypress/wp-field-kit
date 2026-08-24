<?php
/**
 * Field Tooltip
 *
 * @package     ArrayPress\FieldKit
 * @copyright   Copyright (c) 2026, ArrayPress Limited
 * @license     GPL2+
 * @since       1.0.0
 */

declare( strict_types=1 );

namespace ArrayPress\FieldKit\Support;

use ArrayPress\FieldKit\Field;

/**
 * A help icon beside a label, with a sentence or two behind it.
 *
 * The job a `description` cannot do. A description sits under the control and
 * is right when the explanation is worth reading every time. A tooltip is for
 * the explanation worth reading once — and for the places a description has
 * nowhere to go at all: a table repeater hides them inside cells, a quick
 * edit row has no room for them, and a column heading has never had one.
 *
 * EDD's is the shape being matched, and deliberately not its implementation.
 * There the content goes into a `title` attribute and jQuery UI Tooltip picks
 * it up, which has two costs: the browser shows the raw markup as a native
 * tooltip in the moment before the script runs, so `<span class='title'>` is
 * briefly visible as text; and a `title` is not reachable from a keyboard at
 * all, so the explanation exists only for people using a mouse.
 *
 * This is a button and a panel it describes. It is in the tab order, it opens
 * on focus as well as hover, Escape closes it, and the panel is associated
 * with the button through aria-describedby so it is announced rather than
 * merely displayed. No jQuery, and nothing to enqueue that is not already
 * here.
 */
final class Tooltip {

	/**
	 * Markup a tooltip's content may contain.
	 *
	 * Enough for emphasis and a link, and no more. The content comes from a
	 * field's configuration rather than from a request, but it is rendered
	 * into a panel on an admin screen and a plugin that builds it from a
	 * remote response should not be able to put a script there.
	 *
	 * @var array<string, array<string, array<string, bool>>>
	 */
	private const ALLOWED = [
		'strong' => [],
		'b'      => [],
		'em'     => [],
		'i'      => [],
		'code'   => [],
		'br'     => [],
		'a'      => [
			'href'   => [],
			'target' => [],
			'rel'    => [],
		],
	];

	/**
	 * Normalize a tooltip configuration.
	 *
	 * A string is the content, which is the common case. The array form adds
	 * a title, which is rendered bold on its own line above the content.
	 *
	 * @param mixed $tooltip String, array, or nothing.
	 *
	 * @return array{title: string, content: string}|null Null when there is
	 *         nothing to show.
	 */
	public static function resolve( mixed $tooltip ): ?array {
		if ( is_string( $tooltip ) ) {
			$tooltip = [ 'content' => $tooltip ];
		}

		if ( ! is_array( $tooltip ) ) {
			return null;
		}

		$content = trim( (string) ( $tooltip['content'] ?? '' ) );

		if ( '' === $content ) {
			return null;
		}

		return [
			'title'   => trim( (string) ( $tooltip['title'] ?? '' ) ),
			'content' => $content,
		];
	}

	/**
	 * Render a tooltip.
	 *
	 * @param mixed  $tooltip Tooltip configuration.
	 * @param string $id      A unique id stem, usually the field's input id.
	 *
	 * @return string Empty when there is nothing to show.
	 */
	public static function render( mixed $tooltip, string $id ): string {
		$resolved = self::resolve( $tooltip );

		if ( null === $resolved ) {
			return '';
		}

		$panel_id = sprintf( '%s-tip', $id );

		$body = '';

		if ( '' !== $resolved['title'] ) {
			$body .= sprintf( '<strong class="field-kit__tooltip-title">%s</strong>', wp_kses( $resolved['title'], self::ALLOWED ) );
		}

		$body .= wp_kses( $resolved['content'], self::ALLOWED );

		return sprintf(
			'<span class="field-kit__tooltip">' .
			'<button type="button" class="field-kit__tooltip-toggle" aria-expanded="false" aria-describedby="%1$s">' .
			'<span class="dashicons dashicons-editor-help" aria-hidden="true"></span>' .
			'<span class="screen-reader-text">%2$s</span>' .
			'</button>' .
			// Rendered rather than built on demand, and hidden with the
			// attribute rather than with a class: a panel that only exists
			// once the script has run is a panel that does not exist for
			// anyone the script failed for.
			'<span class="field-kit__tooltip-panel" id="%1$s" role="tooltip" hidden>%3$s</span>' .
			'</span>',
			esc_attr( $panel_id ),
			esc_html__( 'More information', 'arraypress' ),
			$body
		);
	}

	/**
	 * Render the tooltip a field carries, if it carries one.
	 *
	 * @param Field $field The field.
	 *
	 * @return string
	 */
	public static function for_field( Field $field ): string {
		return self::render( $field->get( 'tooltip' ), $field->input_id() );
	}
}
