<?php
/**
 * Constant Override Context Decorator
 *
 * @package     ArrayPress\FieldKit
 * @copyright   Copyright (c) 2026, ArrayPress Limited
 * @license     GPL2+
 * @since       1.0.0
 */

declare( strict_types=1 );

namespace ArrayPress\FieldKit\Context;

use ArrayPress\FieldKit\Contracts\Context;
use ArrayPress\FieldKit\Contracts\Flushable;
use ArrayPress\FieldKit\Contracts\Registrable;
use ArrayPress\FieldKit\Field;

/**
 * Lets a wp-config constant stand in for a stored value.
 *
 * The usual reason is a credential that should live in configuration rather
 * than the database — an API key set per environment, or one a client must
 * not be able to change from the admin.
 *
 * A field whose constant is defined becomes read-only, and a write to it is
 * dropped rather than stored. Storing it anyway would leave a shadow value
 * that reappears the day the constant is removed, with no indication of where
 * it came from.
 */
final class ConstantContext implements Context, Flushable, Registrable {

	/**
	 * The context being decorated.
	 *
	 * @var Context
	 */
	private Context $inner;

	/**
	 * Prefix for a derived constant name.
	 *
	 * @var string
	 */
	private string $prefix;

	/**
	 * Construct.
	 *
	 * @param Context $inner  The context to wrap.
	 * @param string  $prefix Prefix for derived constant names.
	 */
	public function __construct( Context $inner, string $prefix = '' ) {
		$this->inner  = $inner;
		$this->prefix = $prefix;
	}

	/**
	 * Read, preferring the constant when it is defined.
	 *
	 * @param int|string $object_id The object id.
	 * @param Field      $field     The field.
	 *
	 * @return mixed
	 */
	public function read( int|string $object_id, Field $field ): mixed {
		$constant = $this->constant_for( $field );

		if ( null !== $constant ) {
			return $constant;
		}

		return $this->inner->read( $object_id, $field );
	}

	/**
	 * Write, unless a constant is standing in for this field.
	 *
	 * @param int|string $object_id The object id.
	 * @param Field      $field     The field.
	 * @param mixed      $value     Sanitized value.
	 *
	 * @return void
	 */
	public function write( int|string $object_id, Field $field, mixed $value ): void {
		if ( null !== $this->constant_for( $field ) ) {
			return;
		}

		$this->inner->write( $object_id, $field, $value );
	}

	/**
	 * Remove a value, unless a constant is standing in for this field.
	 *
	 * @param int|string $object_id The object id.
	 * @param Field      $field     The field.
	 *
	 * @return void
	 */
	public function delete( int|string $object_id, Field $field ): void {
		if ( null !== $this->constant_for( $field ) ) {
			return;
		}

		$this->inner->delete( $object_id, $field );
	}

	/**
	 * Whether a constant stands in for a field.
	 *
	 * @param Field $field The field.
	 *
	 * @return bool
	 */
	public function overrides( Field $field ): bool {
		return null !== $this->constant_for( $field );
	}

	/**
	 * The constant's value, or null when none is defined.
	 *
	 * @param Field $field The field.
	 *
	 * @return string|null
	 */
	private function constant_for( Field $field ): ?string {
		$name = (string) $field->get( 'constant', '' );

		if ( '' === $name ) {
			// Only fields that opted in get a derived name. Deriving one for
			// every field would let an unrelated constant that happens to
			// match a field key silently take it over.
			if ( ! $field->get( 'use_constant', false ) ) {
				return null;
			}

			$name = strtoupper( $this->prefix . $field->key() );
		}

		return defined( $name ) && '' !== (string) constant( $name )
			? (string) constant( $name )
			: null;
	}

	/**
	 * Flush the wrapped store.
	 *
	 * A decorator is what the field set holds, so if it did not pass this on
	 * an option-backed set behind one would stage every value and write none.
	 *
	 * @return void
	 */
	public function save(): void {
		if ( $this->inner instanceof Flushable ) {
			$this->inner->save();
		}
	}

	/**
	 * The kind of meta the wrapped store holds.
	 *
	 * A decorated store is still the store it decorates. Without this a
	 * settings page's encrypted field set would look unregistrable, which is
	 * true, and a term screen's would too, which is not.
	 *
	 * @return string
	 */
	public function meta_type(): string {
		return $this->inner instanceof Registrable ? $this->inner->meta_type() : '';
	}
}
