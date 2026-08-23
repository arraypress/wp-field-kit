<?php
/**
 * Option Context
 *
 * @package ArrayPress\FieldKit
 */

declare( strict_types=1 );

namespace ArrayPress\FieldKit\Context;

use ArrayPress\FieldKit\Contracts\Context;
use ArrayPress\FieldKit\Contracts\Flushable;
use ArrayPress\FieldKit\Field;

/**
 * Fields stored together in one option, as a settings page does.
 *
 * Unlike the meta API, update_option() does not unslash what it is given, so
 * values are written exactly as sanitized. The whole set is read once and
 * written once rather than per field: a settings page writing twenty options
 * separately is twenty autoloaded rows and twenty cache invalidations.
 */
final class OptionContext implements Context, Flushable {

	/**
	 * The option name every field lives under.
	 *
	 * @var string
	 */
	private string $option;

	/**
	 * Pending writes, flushed by save().
	 *
	 * @var array<string, mixed>|null
	 */
	private ?array $pending = null;

	/**
	 * Construct.
	 *
	 * @param string $option The option name.
	 */
	public function __construct( string $option ) {
		$this->option = $option;
	}

	/**
	 * Read a field's stored value.
	 *
	 * The object id is unused: a settings page has one set of values.
	 *
	 * @param int|string $object_id Ignored.
	 * @param Field      $field     The field.
	 *
	 * @return mixed
	 */
	public function read( int|string $object_id, Field $field ): mixed {
		$values = $this->values();

		return $values[ $field->key() ] ?? null;
	}

	/**
	 * Stage a field's sanitized value.
	 *
	 * @param int|string $object_id Ignored.
	 * @param Field      $field     The field.
	 * @param mixed      $value     Sanitized, unslashed value.
	 *
	 * @return void
	 */
	public function write( int|string $object_id, Field $field, mixed $value ): void {
		$this->stage();

		$this->pending[ $field->key() ] = $value;
	}

	/**
	 * Stage a field's removal.
	 *
	 * @param int|string $object_id Ignored.
	 * @param Field      $field     The field.
	 *
	 * @return void
	 */
	public function delete( int|string $object_id, Field $field ): void {
		$this->stage();

		unset( $this->pending[ $field->key() ] );
	}

	/**
	 * Write the staged values.
	 *
	 * Called by the field set once every field has been handled, so the
	 * option is written once whatever the page holds.
	 *
	 * @return void
	 */
	public function save(): void {
		if ( null === $this->pending ) {
			return;
		}

		// Not slashed: update_option() stores what it is given, unlike the
		// meta API, which unslashes first.
		update_option( $this->option, $this->pending );

		$this->pending = null;
	}

	/**
	 * The current values.
	 *
	 * @return array<string, mixed>
	 */
	public function values(): array {
		if ( null !== $this->pending ) {
			return $this->pending;
		}

		$stored = get_option( $this->option, [] );

		return is_array( $stored ) ? $stored : [];
	}

	/**
	 * Begin staging from the stored values.
	 *
	 * @return void
	 */
	private function stage(): void {
		if ( null === $this->pending ) {
			$this->pending = $this->values();
		}
	}
}
