<?php
/**
 * Field Type Registry
 *
 * @package     ArrayPress\FieldKit
 * @copyright   Copyright (c) 2026, ArrayPress Limited
 * @license     GPL2+
 * @since       1.0.0
 */

declare( strict_types=1 );

namespace ArrayPress\FieldKit;

use ArrayPress\FieldKit\Contracts\FieldType;
use ArrayPress\FieldKit\Types;
use InvalidArgumentException;

/**
 * Resolves a type id to the object that renders and sanitizes it.
 *
 * One registry rather than a match arm per library is what stops the same
 * type existing twice with different behaviour — the state the predecessor
 * libraries reached, where `link` stored `text` in one and `title` in the
 * other, and `sanitize_number` had two incompatible implementations.
 */
final class Registry {

	/**
	 * Type id => class name.
	 *
	 * @var array<string, class-string<FieldType>>
	 */
	private const TYPES = [
		// Text and numeric inputs.
		'text'            => Types\TextType::class,
		'email'           => Types\EmailType::class,
		'url'             => Types\UrlType::class,
		'tel'             => Types\TelType::class,
		'password'        => Types\PasswordType::class,
		'hidden'          => Types\HiddenType::class,
		'number'          => Types\NumberType::class,
		'range'           => Types\RangeType::class,
		'textarea'        => Types\TextareaType::class,
		'code'            => Types\CodeType::class,
		'wysiwyg'         => Types\WysiwygType::class,

		// Date and colour.
		'date'            => Types\DateType::class,
		'time'            => Types\TimeType::class,
		'datetime'        => Types\DatetimeType::class,
		'color'           => Types\ColorType::class,

		// Choice.
		'select'          => Types\SelectType::class,
		'select_multiple' => Types\SelectMultipleType::class,
		'checkbox'        => Types\CheckboxType::class,
		'toggle'          => Types\ToggleType::class,
		'radio'           => Types\RadioType::class,
		'checkbox_group'  => Types\CheckboxGroupType::class,
		'button_group'    => Types\ButtonGroupType::class,
	];

	/**
	 * Alternative spellings, mapped to the canonical id.
	 *
	 * The predecessor libraries named the same thing differently. Rather than
	 * pick a winner and break existing config, both resolve — and the
	 * canonical name is the one written in new code.
	 *
	 * @var array<string, string>
	 */
	private const ALIASES = [
		'select2'  => 'select',           // setting-fields' name for a searchable select.
		'switch'   => 'toggle',
		'colour'   => 'color',
	];

	/**
	 * Resolved instances, one per type.
	 *
	 * @var array<string, FieldType>
	 */
	private array $instances = [];

	/**
	 * Types registered at runtime by a consumer.
	 *
	 * @var array<string, FieldType>
	 */
	private array $custom = [];

	/**
	 * Register a type, or replace a built-in one.
	 *
	 * @param FieldType $type The type.
	 *
	 * @return self
	 */
	public function register( FieldType $type ): self {
		$this->custom[ $type->id() ] = $type;

		unset( $this->instances[ $type->id() ] );

		return $this;
	}

	/**
	 * Whether a type id resolves.
	 *
	 * @param string $id Type id.
	 *
	 * @return bool
	 */
	public function has( string $id ): bool {
		$id = $this->canonical( $id );

		return isset( $this->custom[ $id ] ) || isset( self::TYPES[ $id ] );
	}

	/**
	 * Resolve a type id.
	 *
	 * @param string $id Type id.
	 *
	 * @return FieldType
	 * @throws InvalidArgumentException When the id is not registered.
	 */
	public function get( string $id ): FieldType {
		$id = $this->canonical( $id );

		if ( isset( $this->custom[ $id ] ) ) {
			return $this->custom[ $id ];
		}

		if ( ! isset( self::TYPES[ $id ] ) ) {
			throw new InvalidArgumentException(
				sprintf(
					/* translators: 1: field type, 2: comma-separated list of known types */
					esc_html__( 'Unknown field type "%1$s". Known types: %2$s', 'arraypress' ),
					esc_html( $id ),
					esc_html( implode( ', ', $this->ids() ) )
				)
			);
		}

		if ( ! isset( $this->instances[ $id ] ) ) {
			$class                  = self::TYPES[ $id ];
			$this->instances[ $id ] = new $class();
		}

		return $this->instances[ $id ];
	}

	/**
	 * Every registered type id, canonical names only.
	 *
	 * @return string[]
	 */
	public function ids(): array {
		$ids = array_merge( array_keys( self::TYPES ), array_keys( $this->custom ) );

		sort( $ids );

		return array_values( array_unique( $ids ) );
	}

	/**
	 * Every accepted spelling, including aliases.
	 *
	 * @return string[]
	 */
	public function accepted_ids(): array {
		$ids = array_merge( $this->ids(), array_keys( self::ALIASES ) );

		sort( $ids );

		return array_values( array_unique( $ids ) );
	}

	/**
	 * Resolve an alias to its canonical id.
	 *
	 * @param string $id Type id, possibly an alias.
	 *
	 * @return string
	 */
	public function canonical( string $id ): string {
		$id = strtolower( trim( $id ) );

		return self::ALIASES[ $id ] ?? $id;
	}
}
