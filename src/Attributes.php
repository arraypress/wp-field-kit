<?php
/**
 * HTML Attribute Builder
 *
 * @package     ArrayPress\FieldKit
 * @copyright   Copyright (c) 2026, ArrayPress Limited
 * @license     GPL2+
 * @since       1.0.0
 */

declare( strict_types=1 );

namespace ArrayPress\FieldKit;

use Stringable;

/**
 * Class Attributes
 *
 * A mutable set of HTML attributes that escapes itself exactly once, when it
 * is rendered.
 *
 * This exists because the previous libraries passed pre-built attribute
 * *strings* around, and every call site had to remember not to escape them
 * again. When one did, `data-conditions="[...]"` stopped being valid JSON,
 * jQuery handed the script a string instead of an array, and conditional
 * logic silently stopped working on every settings screen. A separate call
 * site printed ` min="0"` as visible text next to an input.
 *
 * Passing this object rather than a string removes the question: there is no
 * spelling of "escape it again" available to a caller, and `__toString()` is
 * the only way to get markup out.
 */
final class Attributes implements Stringable {

	/**
	 * Attribute name => value.
	 *
	 * A value of `true` renders as a boolean attribute; `false` and `null`
	 * are omitted entirely.
	 *
	 * @var array<string, string|int|float|bool|null>
	 */
	private array $attributes = [];

	/**
	 * Classes, kept apart so they can be added incrementally.
	 *
	 * @var string[]
	 */
	private array $classes = [];

	/**
	 * Construct from a name => value map.
	 *
	 * @param array<string, mixed> $attributes Initial attributes.
	 */
	public function __construct( array $attributes = [] ) {
		foreach ( $attributes as $name => $value ) {
			$this->set( (string) $name, $value );
		}
	}

	/**
	 * Set one attribute.
	 *
	 * `class` is routed to the class list so later calls add rather than
	 * replace, which is what every caller has always meant by it.
	 *
	 * @param string $name  Attribute name.
	 * @param mixed  $value Attribute value.
	 *
	 * @return self
	 */
	public function set( string $name, mixed $value ): self {
		$name = strtolower( trim( $name ) );

		if ( '' === $name ) {
			return $this;
		}

		if ( 'class' === $name ) {
			return $this->add_class( is_array( $value ) ? implode( ' ', $value ) : (string) $value );
		}

		// An array value is only meaningful as JSON — data-conditions and
		// friends. Encoding here means no call site hand-rolls it and gets
		// the escaping wrong.
		if ( is_array( $value ) ) {
			$value = (string) wp_json_encode( $value );
		}

		$this->attributes[ $name ] = $value;

		return $this;
	}

	/**
	 * Set several attributes at once.
	 *
	 * @param array<string, mixed> $attributes Attributes to merge in.
	 *
	 * @return self
	 */
	public function merge( array $attributes ): self {
		foreach ( $attributes as $name => $value ) {
			$this->set( (string) $name, $value );
		}

		return $this;
	}

	/**
	 * Set an attribute only when the condition holds.
	 *
	 * Saves the `if` around a one-line set, which is most of what field
	 * renderers do.
	 *
	 * @param bool   $condition Whether to set it.
	 * @param string $name      Attribute name.
	 * @param mixed  $value     Attribute value.
	 *
	 * @return self
	 */
	public function set_if( bool $condition, string $name, mixed $value ): self {
		return $condition ? $this->set( $name, $value ) : $this;
	}

	/**
	 * Add one or more classes.
	 *
	 * @param string ...$classes Class names, space-separated strings allowed.
	 *
	 * @return self
	 */
	public function add_class( string ...$classes ): self {
		foreach ( $classes as $class ) {
			foreach ( preg_split( '/\s+/', $class, -1, PREG_SPLIT_NO_EMPTY ) ?: [] as $one ) {
				if ( ! in_array( $one, $this->classes, true ) ) {
					$this->classes[] = $one;
				}
			}
		}

		return $this;
	}

	/**
	 * Get an attribute's raw, unescaped value.
	 *
	 * @param string $name    Attribute name.
	 * @param mixed  $fallback Returned when the attribute is not set.
	 *
	 * @return mixed
	 */
	public function get( string $name, mixed $fallback = null ): mixed {
		if ( 'class' === $name ) {
			return $this->classes ? implode( ' ', $this->classes ) : $fallback;
		}

		return $this->attributes[ strtolower( $name ) ] ?? $fallback;
	}

	/**
	 * Whether an attribute is set.
	 *
	 * @param string $name Attribute name.
	 *
	 * @return bool
	 */
	public function has( string $name ): bool {
		return 'class' === $name
			? [] !== $this->classes
			: array_key_exists( strtolower( $name ), $this->attributes );
	}

	/**
	 * Remove an attribute.
	 *
	 * @param string $name Attribute name.
	 *
	 * @return self
	 */
	public function remove( string $name ): self {
		if ( 'class' === $name ) {
			$this->classes = [];
		} else {
			unset( $this->attributes[ strtolower( $name ) ] );
		}

		return $this;
	}

	/**
	 * Render as a leading-space-prefixed attribute string.
	 *
	 * The leading space means `<input<?php echo $attrs; ?> />` is always
	 * well-formed, with or without attributes.
	 *
	 * @return string
	 */
	public function render(): string {
		$parts = [];
		$all   = $this->attributes;

		if ( $this->classes ) {
			$all = [ 'class' => implode( ' ', $this->classes ) ] + $all;
		}

		foreach ( $all as $name => $value ) {
			if ( false === $value || null === $value ) {
				continue;
			}

			if ( true === $value ) {
				$parts[] = esc_attr( $name );
				continue;
			}

			$parts[] = sprintf( '%s="%s"', esc_attr( $name ), esc_attr( (string) $value ) );
		}

		return $parts ? ' ' . implode( ' ', $parts ) : '';
	}

	/**
	 * Render on string conversion.
	 *
	 * @return string
	 */
	public function __toString(): string {
		return $this->render();
	}
}
