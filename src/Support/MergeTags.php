<?php
/**
 * Merge Tags
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
 * The tags an editor can insert, and the dialog for choosing one.
 *
 * The kit does not own them. It has no idea what `{customer_name}` resolves
 * to, when it is substituted, or whether the plugin doing the substituting
 * spells it with braces at all — that is the consumer's business entirely.
 * What the kit owns is presenting a registered list and putting a chosen one
 * into the editor, which is the part every plugin would otherwise rewrite.
 *
 * Three shapes are accepted, because two of them already exist in
 * configuration written against the predecessor libraries:
 *
 *     [ [ 'name' => 'Customer name', 'tag' => '{customer_name}', 'description' => '…' ] ]
 *     [ '{customer_name}' => 'The buyer’s name' ]
 *     [ '{customer_name}', '{order_total}' ]
 *
 * The first is the one to write. A name that reads as a name is what makes a
 * list of forty tags searchable — nobody scans `{order_item_subtotal_ex_tax}`
 * looking for "subtotal".
 */
final class MergeTags {

	/**
	 * Normalize a tag configuration.
	 *
	 * @param mixed $tags Configured tags in any accepted shape.
	 *
	 * @return array<int, array{name: string, tag: string, description: string}>
	 */
	public static function resolve( mixed $tags ): array {
		if ( ! is_array( $tags ) ) {
			return [];
		}

		$resolved = [];

		foreach ( $tags as $key => $value ) {
			$entry = self::entry( $key, $value );

			if ( null !== $entry ) {
				$resolved[] = $entry;
			}
		}

		return $resolved;
	}

	/**
	 * Normalize one entry.
	 *
	 * @param int|string $key   Array key.
	 * @param mixed      $value Array value.
	 *
	 * @return array{name: string, tag: string, description: string}|null
	 */
	private static function entry( int|string $key, mixed $value ): ?array {
		if ( is_array( $value ) ) {
			$tag = (string) ( $value['tag'] ?? $value['name'] ?? '' );

			if ( '' === $tag ) {
				return null;
			}

			return [
				// The tag itself is the fallback name, so an entry that gives
				// only a tag still has something to search on.
				'name'        => (string) ( $value['name'] ?? $tag ),
				'tag'         => $tag,
				'description' => (string) ( $value['description'] ?? '' ),
			];
		}

		// '{tag}' => 'description', or a bare list of tags.
		$tag = is_string( $key ) ? $key : (string) $value;

		if ( '' === $tag ) {
			return null;
		}

		return [
			'name'        => $tag,
			'tag'         => $tag,
			'description' => is_string( $key ) ? (string) $value : '',
		];
	}

	/**
	 * The button that opens the dialog.
	 *
	 * Rendered into the editor's media-button row, beside Add Media, because
	 * that is where someone writing the body is already looking.
	 *
	 * @param string $editor_id The editor this button inserts into.
	 * @param string $modal_id  The dialog it opens.
	 *
	 * @return string
	 */
	public static function button( string $editor_id, string $modal_id ): string {
		$button = new Attributes();
		$button->set( 'type', 'button' );
		$button->add_class( 'button', 'field-kit__tag-button' );
		$button->set( 'data-editor', $editor_id );
		$button->set( 'data-modal', $modal_id );
		$button->set( 'aria-haspopup', 'dialog' );

		return sprintf(
			'<button%s><span class="dashicons dashicons-shortcode" aria-hidden="true"></span> %s</button>',
			$button->render(),
			esc_html__( 'Add Tag', 'arraypress' )
		);
	}

	/**
	 * The dialog itself.
	 *
	 * core's media-modal shape, which is what the media library and the block
	 * editor's own dialogs use — so it matches the admin without a stylesheet
	 * of its own beyond the list inside it.
	 *
	 * @param string                                                    $modal_id Dialog id.
	 * @param array<int, array{name: string, tag: string, description: string}> $tags     Resolved tags.
	 *
	 * @return string
	 */
	public static function modal( string $modal_id, array $tags ): string {
		if ( [] === $tags ) {
			return '';
		}

		$rows = '';

		foreach ( $tags as $tag ) {
			$row = new Attributes();
			$row->set( 'type', 'button' );
			$row->add_class( 'field-kit__tag-item' );
			$row->set( 'data-tag', $tag['tag'] );

			// Searched against rather than the visible text, so a match on a
			// description does not depend on how it happens to be marked up.
			$row->set(
				'data-search',
				strtolower( $tag['name'] . ' ' . $tag['tag'] . ' ' . $tag['description'] )
			);

			$rows .= sprintf(
				'<li><button%s>' .
				'<span class="field-kit__tag-name">%s</span>' .
				'<code class="field-kit__tag-code">%s</code>' .
				'%s</button></li>',
				$row->render(),
				esc_html( $tag['name'] ),
				esc_html( $tag['tag'] ),
				'' === $tag['description']
					? ''
					: sprintf( '<span class="description">%s</span>', esc_html( $tag['description'] ) )
			);
		}

		return sprintf(
			'<div id="%1$s" class="field-kit__tag-modal media-modal wp-core-ui" role="dialog" aria-modal="true" aria-labelledby="%1$s-title" hidden>' .
			'<button type="button" class="media-modal-close"><span class="media-modal-icon">' .
			'<span class="screen-reader-text">%2$s</span></span></button>' .
			'<div class="media-modal-content">' .
			'<div class="media-frame-title"><h1 id="%1$s-title">%3$s</h1></div>' .
			'<div class="media-frame-content field-kit__tag-frame">' .
			'<p class="field-kit__tag-search">' .
			'<label class="screen-reader-text" for="%1$s-search">%4$s</label>' .
			'<input type="search" id="%1$s-search" class="field-kit__tag-search-input" placeholder="%4$s" autocomplete="off" />' .
			'</p>' .
			'<ul class="field-kit__tag-items">%5$s</ul>' .
			'<p class="field-kit__tag-empty" hidden>%6$s</p>' .
			'</div></div></div>' .
			'<div class="field-kit__tag-backdrop media-modal-backdrop" hidden></div>',
			esc_attr( $modal_id ),
			esc_html__( 'Close dialog', 'arraypress' ),
			esc_html__( 'Insert a merge tag', 'arraypress' ),
			esc_attr__( 'Search tags', 'arraypress' ),
			$rows,
			esc_html__( 'No tags match that search.', 'arraypress' )
		);
	}
}
