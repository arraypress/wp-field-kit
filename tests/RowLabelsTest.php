<?php
/**
 * Repeater row label tests.
 *
 * @package ArrayPress\FieldKit
 */

declare( strict_types=1 );

namespace ArrayPress\FieldKit\Tests;

use ArrayPress\FieldKit\Field;
use ArrayPress\FieldKit\Registry;
use ArrayPress\FieldKit\Renderer;
use PHPUnit\Framework\TestCase;

/**
 * A label is drawn once, or not at all, but never on every row.
 *
 * A one-column repeater drew its single sub-field's label above every row:
 * a three-entry feature list headed "What is included" rendered the word
 * "Item" three times, each on its own line, above three identical inputs.
 * The label said nothing the field's own heading had not already said, and
 * it tripled the height of the control.
 *
 * Suppressing it is only half the job. A control with no visible label and
 * no aria-label has no accessible name at all, and that was already true of
 * every cell in a table repeater — a rates table was announced as "edit
 * text, edit text, edit text" — so the name moves to the control rather than
 * being dropped with the element.
 */
final class RowLabelsTest extends TestCase {

	/**
	 * Render a repeater.
	 *
	 * @param array<string, mixed> $fields Sub-fields.
	 * @param string               $layout Layout.
	 * @param array<int, mixed>    $value  Rows.
	 *
	 * @return string
	 */
	private function render( array $fields, string $layout = 'stacked', array $value = [] ): string {
		$field = new Field(
			'rows',
			( new Registry() )->get( 'repeater' ),
			[
				'label'      => 'Rows',
				'input_name' => 'rows',
				'layout'     => $layout,
				'fields'     => $fields,
			],
			null
		);

		return ( new Renderer() )->render( $field->with_value( $value ) );
	}

	/**
	 * One column: no per-row label, but the control is still named.
	 */
	public function test_a_single_column_row_draws_no_label(): void {
		$html = $this->render(
			[ 'item' => [ 'type' => 'text', 'label' => 'Item' ] ],
			'stacked',
			[ [ 'item' => 'One year of updates' ], [ 'item' => 'Priority support' ] ]
		);

		$this->assertStringNotContainsString( '>Item</label>', $html );
		$this->assertSame( 0, substr_count( $html, '<label' ) );

		// Named for assistive technology, once per row.
		$this->assertSame( 3, substr_count( $html, 'aria-label="Item"' ), 'Two rows and the template.' );
	}

	/**
	 * Several columns: each keeps its label, because they differ.
	 */
	public function test_a_multi_column_row_keeps_its_labels(): void {
		$html = $this->render(
			[
				'name'  => [ 'type' => 'text', 'label' => 'Name' ],
				'price' => [ 'type' => 'text', 'label' => 'Price' ],
			],
			'stacked',
			[ [ 'name' => 'A thing', 'price' => '9.99' ] ]
		);

		$this->assertStringContainsString( '>Name</label>', $html );
		$this->assertStringContainsString( '>Price</label>', $html );
	}

	/**
	 * A table cell's control is named by its column.
	 *
	 * The header is a <th>, not a <label>, so it names the column for a
	 * sighted reader and nothing at all for anyone else.
	 */
	public function test_a_table_cell_carries_its_columns_name(): void {
		$html = $this->render(
			[
				'country' => [ 'type' => 'text', 'label' => 'Country' ],
				'rate'    => [ 'type' => 'text', 'label' => 'Rate' ],
			],
			'table',
			[ [ 'country' => 'GB', 'rate' => '20' ] ]
		);

		$this->assertStringContainsString( '<th scope="col">Country</th>', $html );
		$this->assertStringContainsString( 'aria-label="Country"', $html );
		$this->assertStringContainsString( 'aria-label="Rate"', $html );

		// And no <label> duplicating the header inside the cell.
		$this->assertSame( 0, substr_count( $html, '<label' ) );
	}

}
