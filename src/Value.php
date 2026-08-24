<?php
/**
 * Stored Value Helpers
 *
 * @package     ArrayPress\FieldKit
 * @copyright   Copyright (c) 2026, ArrayPress Limited
 * @license     GPL2+
 * @since       1.0.0
 */

declare( strict_types=1 );

namespace ArrayPress\FieldKit;

/**
 * What a stored value means.
 *
 * A toggle stores 1 or 0, or "1" or "" if something wrote it by hand, or true
 * if a filter got to it. A checkbox group stores a list. A page field stores
 * an id that a template wants as a URL. Every consumer ends up writing the
 * same three or four lines to turn one into the other, and each of them draws
 * the line somewhere slightly different — one treats "0" as off, the next as
 * a value.
 *
 * These are here rather than in a library because none of them is about
 * where the value came from. `is_on()` gives the same answer for a term's
 * meta, a user's meta, a post's meta and a settings page, and the four
 * libraries were on their way to four copies of it: setting-fields had
 * eleven such helpers and the other three had none at all.
 *
 * Nothing here reads or writes storage. Read the value first — through the
 * library's own accessor, so decryption and defaults apply — then ask what it
 * means.
 */
final class Value {

	/**
	 * Whether a checkbox or toggle is on.
	 *
	 * FILTER_VALIDATE_BOOLEAN rather than a cast, so the string "0" and the
	 * string "false" are both off. A plain (bool) cast reads "0" as false but
	 * "false" as true, which is the wrong way round for anything that has
	 * been through a form, an import or a filter.
	 *
	 * @param mixed $value Stored value.
	 *
	 * @return bool
	 */
	public static function is_on( mixed $value ): bool {
		return (bool) filter_var( $value, FILTER_VALIDATE_BOOLEAN );
	}

	/**
	 * Whether a multi-value field holds a given option.
	 *
	 * Compared as strings: a checkbox group's options are keyed by string and
	 * a stored list of ids may come back as integers or as numeric strings
	 * depending on how it was written. A strict in_array() against the wrong
	 * one of those is a silent false.
	 *
	 * @param mixed  $value  Stored value.
	 * @param string $option The option to look for.
	 *
	 * @return bool
	 */
	public static function includes( mixed $value, string $option ): bool {
		if ( ! is_array( $value ) ) {
			return false;
		}

		return in_array( $option, array_map( 'strval', $value ), true );
	}

	/**
	 * A single object id.
	 *
	 * @param mixed $value Stored value.
	 *
	 * @return int Zero when nothing is selected.
	 */
	public static function id( mixed $value ): int {
		if ( is_array( $value ) ) {
			$value = reset( $value );
		}

		return absint( $value );
	}

	/**
	 * A list of object ids.
	 *
	 * Empty entries are dropped: a relational field stores nothing rather
	 * than 0 for "not selected", but a value written by hand may not have.
	 *
	 * @param mixed $value Stored value.
	 *
	 * @return int[]
	 */
	public static function ids( mixed $value ): array {
		if ( '' === $value || null === $value ) {
			return [];
		}

		$value = is_array( $value ) ? $value : explode( ',', (string) $value );

		return array_values( array_filter( array_map( 'absint', $value ) ) );
	}

	/**
	 * The permalink of the post a field points at.
	 *
	 * @param mixed  $value    Stored value.
	 * @param string $fallback Returned when there is no post, or no permalink
	 *                         for it. Empty means the site's home page.
	 *
	 * @return string
	 */
	public static function url( mixed $value, string $fallback = '' ): string {
		$id = self::id( $value );

		if ( $id > 0 ) {
			$url = get_permalink( $id );

			if ( is_string( $url ) && '' !== $url ) {
				return $url;
			}
		}

		return '' !== $fallback ? $fallback : home_url( '/' );
	}

	/**
	 * Whether the thing a field points at is the one being viewed.
	 *
	 * Deliberately not is_page(): a field can point at any post type — the
	 * kit's `post`, `page` and `ajax` types all store an id the same way —
	 * and is_page() answers false for every one that is not a page. Comparing
	 * the queried object is the question actually being asked.
	 *
	 * The kind has to be given, because an id cannot say what it is. Post 42,
	 * term 42 and user 42 are three different things, and a check that
	 * accepted whichever archive happened to be showing would answer true for
	 * a post field on the term archive that shares its id. It defaults to
	 * `post`, which is what a field storing a bare id usually holds.
	 *
	 * This used to be `is_singular()` alone, which is false on a term or an
	 * author archive — so a `taxonomy` or `user` field pointing at the very
	 * thing on screen answered no.
	 *
	 * There is no `object`: a row in a custom table has no front-end query to
	 * compare against, so the question does not arise.
	 *
	 * @param mixed  $value Stored value.
	 * @param string $type  What the id refers to: post, term or user.
	 *
	 * @return bool
	 */
	public static function is_viewing( mixed $value, string $type = 'post' ): bool {
		$id = self::id( $value );

		if ( $id < 1 ) {
			return false;
		}

		$viewing = match ( $type ) {
			'term'  => is_category() || is_tag() || is_tax(),
			'user'  => is_author(),
			default => is_singular(),
		};

		return $viewing && get_queried_object_id() === $id;
	}

	/**
	 * A stored value as a list of strings.
	 *
	 * What a tags field stores, and what a multiple select stores, arriving
	 * as either an array or the separated string one was typed as.
	 *
	 * @param mixed  $value     Stored value.
	 * @param string $separator Separator, when the value is a string.
	 *
	 * @return string[]
	 */
	public static function list( mixed $value, string $separator = ',' ): array {
		if ( '' === $value || null === $value ) {
			return [];
		}

		$parts = is_array( $value ) ? $value : explode( $separator, (string) $value );
		$parts = array_map( static fn( $part ) => trim( (string) $part ), $parts );

		return array_values( array_filter( $parts, static fn( $part ) => '' !== $part ) );
	}
}
