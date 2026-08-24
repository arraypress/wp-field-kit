<?php
/**
 * Resolve tests.
 *
 * @package ArrayPress\FieldKit
 */

declare( strict_types=1 );

namespace ArrayPress\FieldKit\Tests;

use ArrayPress\FieldKit\Support\Resolve;
use PHPUnit\Framework\TestCase;

/**
 * Finding a named value on whatever a caller handed over.
 *
 * The order is the whole content of this class, and getting it wrong is not a
 * crash — it is a panel that opens with every field empty, which reads as a
 * loading problem rather than a lookup one.
 */
final class ResolveTest extends TestCase {

	/**
	 * A value is found on a plain object's property.
	 *
	 * Which is what a `load` callback usually returns — a row cast to an
	 * object, or a WP_Post, whose post_title is a property and not a getter.
	 * Without this the panel opened with every field empty, and the flyouts
	 * README's own example — `'load' => fn( $id ) => get_post( $id )` — could
	 * not have worked.
	 */
	public function test_a_property_is_found(): void {
		$row = (object) [ 'name' => 'Ada Lovelace', 'amount' => 0 ];

		$this->assertSame( 'Ada Lovelace', Resolve::value( $row, 'name' ) );

		// Zero is a value.
		$this->assertSame( 0, Resolve::value( $row, 'amount' ) );

		$this->assertNull( Resolve::value( $row, 'nothing' ) );
	}

	/**
	 * A getter beats the property it probably wraps.
	 *
	 * A getter is a deliberate statement about what the value means; a
	 * property is just where it happens to be stored.
	 */
	public function test_a_getter_beats_a_property(): void {
		$object = new class {
			public $name = 'from the property';

			public function get_name(): string {
				return 'from the getter';
			}
		};

		$this->assertSame( 'from the getter', Resolve::value( $object, 'name' ) );
	}

	/**
	 * An explicit data method beats everything.
	 *
	 * How an object supplies something assembled rather than stored — a
	 * component's whole configuration, not one column.
	 */
	public function test_a_data_method_beats_a_getter(): void {
		$object = new class {
			public function get_items(): array {
				return [ 'from the getter' ];
			}

			public function items_data(): array {
				return [ 'from the data method' ];
			}
		};

		$this->assertSame( [ 'from the data method' ], Resolve::value( $object, 'items' ) );
	}

	/**
	 * An array resolves by key, and a missing key is null.
	 */
	public function test_an_array_resolves_by_key(): void {
		$this->assertSame( 'blue', Resolve::value( [ 'colour' => 'blue' ], 'colour' ) );
		$this->assertNull( Resolve::value( [ 'colour' => 'blue' ], 'missing' ) );
	}

	/**
	 * A key holding null is still a key that is there.
	 *
	 * array_key_exists rather than isset: a column that is NULL in the
	 * database is a value the row has, and falling through to "not found"
	 * would be indistinguishable from a typo in the key.
	 */
	public function test_a_null_array_value_is_still_found(): void {
		$this->assertNull( Resolve::value( [ 'colour' => null ], 'colour' ) );
	}

	/**
	 * A magic property answers for itself.
	 *
	 * WP_Post has an __isset/__get pair, and property_exists() says no to
	 * both — which is why the check is isset().
	 */
	public function test_a_magic_property_is_found(): void {
		$object = new class {
			public function __isset( $name ): bool {
				return 'magic' === $name;
			}

			public function __get( $name ): string {
				return 'from __get';
			}
		};

		$this->assertSame( 'from __get', Resolve::value( $object, 'magic' ) );
		$this->assertNull( Resolve::value( $object, 'other' ) );
	}

	/**
	 * Nothing to read from is null rather than a fatal.
	 *
	 * A new record has no row behind it, which is the ordinary case on an
	 * "add" screen rather than an error.
	 */
	public function test_nothing_to_read_from_is_null(): void {
		$this->assertNull( Resolve::value( null, 'anything' ) );
		$this->assertNull( Resolve::value( [], 'anything' ) );
	}
}
