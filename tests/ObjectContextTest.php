<?php
/**
 * Object context tests.
 *
 * @package ArrayPress\FieldKit
 */

declare( strict_types=1 );

namespace ArrayPress\FieldKit\Tests;

use ArrayPress\FieldKit\Context\ObjectContext;
use ArrayPress\FieldKit\FieldSet;
use PHPUnit\Framework\TestCase;

/**
 * The shape a record-editing screen actually has: something hands you a row —
 * a BerlinDB object, a WP_Post, a plain stdClass out of $wpdb — and each
 * field's value is on it somewhere.
 *
 * "Somewhere" is what this exists for. The same value might be a property, a
 * getter or a magic one, and which it is depends on whose object it is. A
 * flyout populated nothing for months because it looked for two of the three.
 */
final class ObjectContextTest extends TestCase {

	/**
	 * A field set over an object.
	 *
	 * @param object|null $subject The object.
	 *
	 * @return array{0: FieldSet, 1: ObjectContext}
	 */
	private function set( ?object $subject ): array {
		$context = new ObjectContext( $subject );

		$set = new FieldSet(
			[
				'name'   => [ 'type' => 'text', 'label' => 'Name' ],
				'amount' => [ 'type' => 'number', 'label' => 'Amount' ],
				'colour' => [ 'type' => 'text', 'label' => 'Colour', 'default' => 'blue' ],
			],
			$context,
			''
		);

		return [ $set, $context ];
	}

	/**
	 * A plain object's properties are read.
	 */
	public function test_a_property_is_read(): void {
		[ $set ] = $this->set( (object) [ 'name' => 'Ada Lovelace', 'amount' => 0 ] );

		$this->assertSame( 'Ada Lovelace', $set->field( 'name' )->value() );

		// Zero is a value, not an absence.
		$this->assertSame( 0, $set->field( 'amount' )->value() );
	}

	/**
	 * A getter is preferred to a property.
	 *
	 * A getter is a deliberate statement about what the value means; a
	 * property is just where it happens to be stored.
	 */
	public function test_a_getter_wins_over_a_property(): void {
		$subject = new class {
			public $name = 'from the property';

			public function get_name(): string {
				return 'from the getter';
			}
		};

		[ $set ] = $this->set( $subject );

		$this->assertSame( 'from the getter', $set->field( 'name' )->value() );
	}

	/**
	 * And an explicit data method wins over both.
	 */
	public function test_a_data_method_wins_over_everything(): void {
		$subject = new class {
			public $name = 'from the property';

			public function get_name(): string {
				return 'from the getter';
			}

			public function name_data(): string {
				return 'from the data method';
			}
		};

		[ $set ] = $this->set( $subject );

		$this->assertSame( 'from the data method', $set->field( 'name' )->value() );
	}

	/**
	 * A magic property answers for itself.
	 *
	 * WP_Post has an __isset/__get pair, which property_exists() would miss.
	 */
	public function test_a_magic_property_is_read(): void {
		$subject = new class {
			private array $data = [ 'name' => 'from the magic getter' ];

			public function __isset( $key ): bool {
				return isset( $this->data[ $key ] );
			}

			public function __get( $key ) {
				return $this->data[ $key ] ?? null;
			}
		};

		[ $set ] = $this->set( $subject );

		$this->assertSame( 'from the magic getter', $set->field( 'name' )->value() );
	}

	/**
	 * No object at all is a new record: every field takes its default.
	 */
	public function test_no_object_means_defaults(): void {
		[ $set ] = $this->set( null );

		// No default configured and nothing to read: null, which is the kit's
		// "this has never been set" rather than an empty string, which is a
		// value someone typed.
		$this->assertNull( $set->field( 'name' )->value() );
		$this->assertSame( 'blue', $set->field( 'colour' )->value() );
	}

	/**
	 * Saving collects rather than writing to the object.
	 *
	 * An object of someone else's is not something to set properties on — the
	 * setter may not exist, may not be the getter's opposite, may have side
	 * effects. What the field set produced is handed back for the caller's
	 * own save to deal with.
	 */
	public function test_saving_collects_rather_than_setting(): void {
		$subject = (object) [ 'name' => 'Ada Lovelace', 'amount' => 10 ];

		[ $set, $context ] = $this->set( $subject );

		$set->save( [ 'name' => 'Grace Hopper', 'amount' => '42', 'colour' => 'green' ] );

		$this->assertSame(
			[ 'name' => 'Grace Hopper', 'amount' => 42, 'colour' => 'green' ],
			$context->values()
		);

		// The object is untouched.
		$this->assertSame( 'Ada Lovelace', $subject->name );
		$this->assertSame( 10, $subject->amount );
	}

	/**
	 * A value written this request is what a re-render shows.
	 *
	 * So a form that failed to save comes back with what the person typed
	 * rather than with what is stored.
	 */
	public function test_a_written_value_is_read_back(): void {
		[ $set, $context ] = $this->set( (object) [ 'name' => 'Ada Lovelace' ] );

		$set->save( [ 'name' => 'Grace Hopper' ] );

		$this->assertSame( 'Grace Hopper', $set->field( 'name' )->value() );
		$this->assertSame( 'Grace Hopper', $context->values()['name'] );
	}

	/**
	 * A field stored under another name is looked up by that name.
	 */
	public function test_a_stored_name_is_honoured(): void {
		$context = new ObjectContext( (object) [ 'display_name' => 'Ada Lovelace' ] );

		$set = new FieldSet(
			[ 'name' => [ 'type' => 'text', 'meta_key' => 'display_name' ] ],
			$context,
			''
		);

		$this->assertSame( 'Ada Lovelace', $set->field( 'name' )->value() );
	}

	/**
	 * A cleared field is reported as cleared, not omitted.
	 *
	 * The difference between a collecting context and a store. A store drops
	 * an empty value because an empty row is worth nothing; a caller about to
	 * write a record has to be told the field was emptied, or the old value
	 * survives a save that was meant to clear it.
	 */
	public function test_a_cleared_field_is_reported_rather_than_omitted(): void {
		[ $set, $context ] = $this->set( (object) [ 'name' => 'Ada Lovelace' ] );

		$set->save( [ 'name' => '' ] );

		$this->assertArrayHasKey( 'name', $context->values() );
		$this->assertNull( $context->values()['name'] );
	}

}
