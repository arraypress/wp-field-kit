<?php
/**
 * Resolve
 *
 * @package     ArrayPress\FieldKit
 * @copyright   Copyright (c) 2026, ArrayPress Limited
 * @license     GPL2+
 * @since       1.0.0
 */

declare( strict_types=1 );

namespace ArrayPress\FieldKit\Support;

/**
 * Finding a named value on whatever a caller handed over.
 *
 * The shape a record-editing screen actually has: something returns a row — a
 * BerlinDB object, a WP_Post, a plain stdClass out of $wpdb, an associative
 * array — and each field's value is on it somewhere.
 *
 * "Somewhere" is the point, and the order matters:
 *
 * 1. `{key}_data()` — an explicit statement of what this field needs, which is
 *    how an object supplies something assembled rather than stored.
 * 2. An array key, for the plainest case.
 * 3. `get_{key}()` — a getter is a deliberate statement about what the value
 *    means, so it beats the property it probably wraps.
 * 4. A public property, which is where WP_Post and WP_Term actually keep
 *    things: `post_title` and `name` are properties, not getters.
 *
 * The last one is not optional. Without it a `load` callback returning
 * `get_post( $id )` populated nothing at all and the panel opened with every
 * field empty — which is what this library's own README example does.
 */
final class Resolve {

	/**
	 * A value from an object or an array.
	 *
	 * @param mixed  $source The object or array to read from.
	 * @param string $key    What to look for.
	 *
	 * @return mixed Null when it is not there.
	 */
	public static function value( mixed $source, string $key ): mixed {
		if ( ! $source ) {
			return null;
		}

		if ( is_object( $source ) ) {
			$data = $key . '_data';

			if ( method_exists( $source, $data ) ) {
				return $source->$data();
			}
		}

		if ( is_array( $source ) && array_key_exists( $key, $source ) ) {
			return $source[ $key ];
		}

		if ( ! is_object( $source ) ) {
			return null;
		}

		$getter = 'get_' . $key;

		if ( method_exists( $source, $getter ) ) {
			return $source->$getter();
		}

		// isset() rather than property_exists(), so an object with a magic
		// __isset/__get pair — WP_Post has both — answers for itself.
		return isset( $source->$key ) ? $source->$key : null;
	}
}
