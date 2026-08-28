<?php
/**
 * Runtime Key Derivation
 *
 * @package     ArrayPress\FieldKit
 * @copyright   Copyright (c) 2025, ArrayPress Limited
 * @license     GPL2+
 * @since       1.1.0
 */

declare( strict_types=1 );

namespace ArrayPress\FieldKit\Utils;

/**
 * Every runtime string this library registers, derived from its own namespace.
 *
 * Strauss rewrites class namespaces and leaves string literals alone. Two
 * plugins each bundling a prefixed copy of this library therefore get
 * distinct classes but would otherwise register the same REST routes and the
 * same script handles.
 *
 * That is not merely untidy, and neither failure announces itself:
 *
 * - `WP_REST_Server::register_route()` keys endpoints by route path and, with
 *   no `$override`, merges a second registration into the first with
 *   `array_merge()`. The handler list is numerically indexed, so handlers are
 *   appended rather than replaced and dispatch runs the first whose methods
 *   match. The plugin that registered first answers the other's requests --
 *   resolving the source or action against its own registry, and checking
 *   that registry's capability. The second plugin's own sources come back as
 *   404 "unknown source" on an endpoint that is plainly there.
 * - `wp_enqueue_script()` ignores a handle that is already registered, so the
 *   plugin that enqueued second gets the other plugin's JavaScript -- from a
 *   build whose field types may not be the ones its markup expects.
 *
 * The derivation exploits the one thing Strauss does rewrite: this file's
 * namespace. In a prefixed build `__NAMESPACE__` begins with the consumer's
 * prefix ("MyPlugin\ArrayPress\FieldKit\Utils"), unique per plugin by
 * construction, so every key comes out distinct with no configuration.
 */
final class Runtime {

	/**
	 * This library's own identifier, used when running unprefixed.
	 */
	private const LIBRARY = 'field-kit';

	/**
	 * The per-build prefix.
	 *
	 * "field-kit" for a plain Composer install -- development, or a single
	 * consumer that does not use Strauss -- and "{prefix}-field-kit" for a
	 * prefixed build.
	 *
	 * @return string
	 */
	public static function prefix(): string {
		return self::prefix_for( __NAMESPACE__ );
	}

	/**
	 * The prefix a given namespace would produce.
	 *
	 * Split out so the rule can be tested. prefix() reads __NAMESPACE__,
	 * which cannot be changed at runtime, so a test can only reach the
	 * prefixed case by asking the rule directly — this takes the namespace
	 * as an argument, and prefix() hands it __NAMESPACE__.
	 *
	 * The alternative, compiling a copy of this file under another namespace
	 * at runtime, was tried and abandoned: the interpreter that does it
	 * refuses the `declare( strict_types=1 )` at the top of this file on PHP
	 * 8.3 and 8.4, so the test passed on 8.5 alone and reported the local PHP
	 * version rather than the code. It is also a construct that has no place
	 * in a file that ships inside somebody's plugin.
	 *
	 * @param string $under The namespace this class is compiled under, prefixed or not.
	 *
	 * @return string
	 */
	public static function prefix_for( string $under ): string {
		$root = explode( '\\', $under )[0] ?? '';

		if ( '' === $root || 'ArrayPress' === $root ) {
			return self::LIBRARY;
		}

		return self::slug( $root ) . '-' . self::LIBRARY;
	}

	/**
	 * Get the REST namespace for this build.
	 *
	 * @return string
	 */
	public static function rest_namespace(): string {
		return self::prefix() . '/v1';
	}

	/**
	 * Get a script or style handle for this build.
	 *
	 * @param string $suffix Optional handle suffix.
	 *
	 * @return string
	 */
	public static function handle( string $suffix = '' ): string {
		return '' === $suffix ? self::prefix() : self::prefix() . '-' . $suffix;
	}

	/**
	 * Get an option, transient or nonce key for this build.
	 *
	 * @param string $suffix Optional key suffix.
	 *
	 * @return string
	 */
	public static function key( string $suffix = '' ): string {
		$base = str_replace( '-', '_', self::prefix() );

		return '' === $suffix ? $base : $base . '_' . $suffix;
	}

	/**
	 * Get the JavaScript object name for this build.
	 *
	 * `wp_localize_script()` writes `var <name> = {...}`, so this has to be a
	 * valid JS identifier — hyphens are not allowed.
	 *
	 * @param string $suffix Optional name suffix.
	 *
	 * @return string
	 */
	public static function js_object( string $suffix = '' ): string {
		$parts = preg_split( '/[^A-Za-z0-9]+/', self::prefix(), -1, PREG_SPLIT_NO_EMPTY ) ?: [];
		$name  = implode( '', array_map( 'ucfirst', $parts ) );

		return $name . $suffix;
	}

	/**
	 * Get a hook name for this build.
	 *
	 * @param string $suffix Hook suffix.
	 *
	 * @return string
	 */
	public static function hook( string $suffix ): string {
		return self::key() . '_' . $suffix;
	}

	/**
	 * Reduce a namespace segment to a lowercase slug.
	 *
	 * `sanitize_title()` is not used here: this runs from `__NAMESPACE__` at
	 * class-load time, which can precede WordPress being fully loaded.
	 *
	 * @param string $value Value to slug.
	 *
	 * @return string
	 */
	private static function slug( string $value ): string {
		$value = preg_replace( '/[^A-Za-z0-9]+/', '-', $value ) ?? '';

		return strtolower( trim( $value, '-' ) );
	}
}
