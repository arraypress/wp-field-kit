<?php
/**
 * Object Storage Context
 *
 * @package     ArrayPress\FieldKit
 * @copyright   Copyright (c) 2026, ArrayPress Limited
 * @license     GPL2+
 * @since       1.0.0
 */

declare( strict_types=1 );

namespace ArrayPress\FieldKit\Context;

use ArrayPress\FieldKit\Contracts\Context;
use ArrayPress\FieldKit\Field;

/**
 * A set of fields read straight off an object.
 *
 * The shape a record-editing screen actually has: something hands you a row —
 * a BerlinDB object, a WP_Post, a plain stdClass out of $wpdb — and each
 * field's value is on it somewhere. Not in meta, not in an option; on the
 * object.
 *
 * "Somewhere" is the point. The same value might be a public property, a
 * getter, or a magic one, and which of those it is depends on whose object it
 * is. So all three are tried, in the order that lets an object override the
 * default: an explicit `{key}_data()` first, then a `get_{key}()` getter,
 * then a property. A getter is a deliberate statement about what the value
 * means; a property is just where it happens to be stored.
 *
 * Writes collect rather than persist. An object of someone else's is not
 * something to set properties on — the setter might be a getter's opposite,
 * might not exist, might have side effects — so what the field set produced
 * is gathered here and handed back through values() for the caller's own save
 * to deal with. That is also what makes this safe to use for a form that may
 * be cancelled.
 */
final class ObjectContext implements Context {

	/**
	 * The object being read.
	 *
	 * @var object|null
	 */
	private ?object $subject;

	/**
	 * What the field set wrote.
	 *
	 * @var array<string, mixed>
	 */
	private array $written = [];

	/**
	 * Construct.
	 *
	 * @param object|null $subject The object to read from, or null for a new
	 *                             record, where every field takes its default.
	 */
	public function __construct( ?object $subject = null ) {
		$this->subject = $subject;
	}

	/**
	 * Read one field's value off the object.
	 *
	 * @param int|string $object_id Unused: the object is the subject.
	 * @param Field      $field     The field.
	 *
	 * @return mixed
	 */
	public function read( int|string $object_id, Field $field ): mixed {
		$key = $this->key( $field );

		// What was written this request wins, so a re-render after a failed
		// save shows what the person typed rather than what is stored.
		if ( array_key_exists( $key, $this->written ) ) {
			return $this->written[ $key ];
		}

		if ( null === $this->subject ) {
			return null;
		}

		$data = $key . '_data';

		if ( method_exists( $this->subject, $data ) ) {
			return $this->subject->$data();
		}

		$getter = 'get_' . $key;

		if ( method_exists( $this->subject, $getter ) ) {
			return $this->subject->$getter();
		}

		// isset() rather than property_exists(), so an object with a magic
		// __isset/__get pair — WP_Post has both — answers for itself.
		return isset( $this->subject->$key ) ? $this->subject->$key : null;
	}

	/**
	 * Collect a value rather than setting it on the object.
	 *
	 * @param int|string $object_id Unused.
	 * @param Field      $field     The field.
	 * @param mixed      $value     Sanitized value.
	 *
	 * @return void
	 */
	public function write( int|string $object_id, Field $field, mixed $value ): void {
		$this->written[ $this->key( $field ) ] = $value;
	}

	/**
	 * Record that a field was cleared.
	 *
	 * Null rather than forgotten, which is the difference between a
	 * collecting context and a store. A store deletes an empty value because
	 * an empty row is worth nothing; a caller about to write a record needs
	 * to be told the field was emptied, or the old value survives a save that
	 * was meant to clear it.
	 *
	 * @param int|string $object_id Unused.
	 * @param Field      $field     The field.
	 *
	 * @return void
	 */
	public function delete( int|string $object_id, Field $field ): void {
		$this->written[ $this->key( $field ) ] = null;
	}

	/**
	 * Everything the field set wrote.
	 *
	 * @return array<string, mixed>
	 */
	public function values(): array {
		return $this->written;
	}

	/**
	 * The object being read.
	 *
	 * @return object|null
	 */
	public function subject(): ?object {
		return $this->subject;
	}

	/**
	 * Which name to look for on the object.
	 *
	 * A field may store under a different name than it is keyed by — a
	 * `meta_key` on a metabox, a column name on a row — and the object knows
	 * it by the stored one.
	 *
	 * @param Field $field The field.
	 *
	 * @return string
	 */
	private function key( Field $field ): string {
		return (string) $field->get( 'meta_key', $field->key() );
	}
}
