<?php
/**
 * List and key/value type tests.
 *
 * @package ArrayPress\FieldKit
 */

declare( strict_types=1 );

namespace ArrayPress\FieldKit\Tests;

use ArrayPress\FieldKit\Context\ArrayContext;
use ArrayPress\FieldKit\FieldSet;
use PHPUnit\Framework\TestCase;

/**
 * Both are repeaters with their columns already decided, which is the cheap
 * half. The half worth testing is what they store: a list keeps a flat array
 * of strings, because a template reading a feature list wants strings and
 * asking every consumer to reach through a one-key array is how a repeater
 * gets reimplemented — three times, in the library this replaces.
 */
final class ListTypeTest extends TestCase {

	/**
	 * A set over one field.
	 *
	 * @param string $type   Type id.
	 * @param mixed  $stored What is stored.
	 *
	 * @return array{0: FieldSet, 1: ArrayContext}
	 */
	private function set( string $type, mixed $stored = null ): array {
		$context = new ArrayContext( null === $stored ? [] : [ 'x' => $stored ] );

		return [ new FieldSet( [ 'x' => [ 'type' => $type, 'label' => 'X' ] ], $context, '' ), $context ];
	}

	/**
	 * A list stores a flat array of strings.
	 */
	public function test_a_list_stores_strings(): void {
		[ $set, $context ] = $this->set( 'list' );

		$set->save( [ 'x' => [ [ 'item' => 'First' ], [ 'item' => 'Second' ] ] ] );

		$this->assertSame( [ 'First', 'Second' ], $context->values()['x'] );
	}

	/**
	 * An empty entry is not an entry.
	 */
	public function test_an_empty_entry_is_dropped(): void {
		[ $set, $context ] = $this->set( 'list' );

		$set->save( [ 'x' => [ [ 'item' => 'First' ], [ 'item' => '  ' ], [ 'item' => 'Second' ] ] ] );

		$this->assertSame( [ 'First', 'Second' ], $context->values()['x'] );
	}

	/**
	 * A stored flat array renders one row per entry.
	 */
	public function test_a_flat_array_renders_as_rows(): void {
		[ $set ] = $this->set( 'list', [ 'One year of updates', 'Priority support' ] );

		$html = $set->render();

		$this->assertStringContainsString( 'value="One year of updates"', $html );
		$this->assertStringContainsString( 'value="Priority support"', $html );
	}

	/**
	 * A value already in row shape is taken as it is.
	 *
	 * Someone may have stored it through a plain repeater before switching
	 * type; nesting it a second time would render a row of nothing.
	 */
	public function test_a_row_shaped_value_is_not_nested_again(): void {
		[ $set ] = $this->set( 'list', [ [ 'item' => 'Already a row' ] ] );

		$this->assertStringContainsString( 'value="Already a row"', $set->render() );
	}

	/**
	 * A key/value list is a table of two columns.
	 */
	public function test_key_value_is_a_two_column_table(): void {
		[ $set ] = $this->set( 'key_value', [ [ 'key' => 'source', 'value' => 'checkout' ] ] );

		$html = $set->render();

		$this->assertStringContainsString( 'field-kit__repeater--table', $html );
		$this->assertStringContainsString( 'value="source"', $html );
		$this->assertStringContainsString( 'value="checkout"', $html );
	}

	/**
	 * It keeps rows rather than collapsing to a map.
	 *
	 * A map cannot hold two entries with the same key, cannot keep an order,
	 * and loses a row the moment someone is halfway through typing a key that
	 * already exists — all three of which happen while a person is editing.
	 */
	public function test_key_value_keeps_rows_rather_than_a_map(): void {
		[ $set, $context ] = $this->set( 'key_value' );

		$set->save(
			[
				'x' => [
					[ 'key' => 'a', 'value' => 'one' ],
					[ 'key' => 'a', 'value' => 'two' ],
				],
			]
		);

		$this->assertCount( 2, $context->values()['x'] );
		$this->assertSame( 'two', $context->values()['x'][1]['value'] );
	}

	/**
	 * A list says it holds strings, so REST describes it correctly.
	 */
	public function test_a_list_declares_its_shape(): void {
		[ $set ] = $this->set( 'list' );

		$this->assertSame(
			[ 'type' => 'array', 'items' => [ 'type' => 'string' ] ],
			$set->field( 'x' )->type()->schema( $set->field( 'x' ) )
		);
	}
}
