<?php
/**
 * Repeater layout tests.
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
 * The table repeater is the one field in the kit whose markup has to satisfy
 * something other than the renderer: a table's own column algorithm. Every
 * row has to carry exactly as many cells as the header does, and so does the
 * template the script clones — a row one cell short does not fail, it shifts
 * every column after it and the table silently stops meaning anything.
 *
 * Two of these guard mistakes already made. The template is emitted inside
 * the <table> because a <tr> inside a <template> inside a <div> is discarded
 * by the HTML parser — template content parses in the context the template
 * appears in — so the row to clone was simply absent and "Add row" did
 * nothing. And the row cannot be laid out as a flex container, which is what
 * it inherited from the stacked layout: a <tr> set to display:flex leaves the
 * table's column geometry entirely and its cells stop lining up under the
 * headers at all.
 */
final class RepeaterTest extends TestCase {

	/**
	 * The sub-fields every repeater here repeats.
	 *
	 * @var array<string, array<string, mixed>>
	 */
	private const SUB_FIELDS = [
		'country' => [ 'type' => 'text', 'label' => 'Country' ],
		'rate'    => [ 'type' => 'number', 'label' => 'Rate' ],
		'kind'    => [
			'type'    => 'select',
			'label'   => 'Kind',
			'options' => [ 'flat' => 'Flat', 'percent' => 'Percent' ],
		],
	];

	/**
	 * Render a repeater.
	 *
	 * @param string                     $layout Layout to use.
	 * @param array<int, array<string, mixed>> $value  Stored rows.
	 *
	 * @return string
	 */
	private function render( string $layout, array $value = [] ): string {
		$field = new Field(
			'rates',
			( new Registry() )->get( 'repeater' ),
			[
				'label'      => 'Rates',
				'input_name' => 'rates',
				'layout'     => $layout,
				'fields'     => self::SUB_FIELDS,
			],
			null
		);

		return ( new Renderer() )->render( $field->with_value( $value ) );
	}

	/**
	 * Count the cells in one row of markup.
	 *
	 * @param string $row A single <tr>…</tr>.
	 *
	 * @return int
	 */
	private function cells( string $row ): int {
		return preg_match_all( '/<t[dh][\s>]/', $row );
	}

	/**
	 * Pull out every <tr> in the order it appears.
	 *
	 * @param string $html Rendered markup.
	 *
	 * @return string[]
	 */
	private function rows( string $html ): array {
		preg_match_all( '/<tr\b.*?<\/tr>/s', $html, $matches );

		return $matches[0];
	}

	/**
	 * A table repeater renders a list table.
	 */
	public function test_a_table_repeater_renders_a_list_table(): void {
		$html = $this->render( 'table' );

		$this->assertStringContainsString( 'field-kit__repeater--table', $html );
		$this->assertStringContainsString( 'wp-list-table widefat', $html );
		$this->assertStringContainsString( '<thead>', $html );
	}

	/**
	 * Every column has a header, and the actions column has one too.
	 *
	 * Not a <th> for the actions — it is not a column of data and naming it
	 * in every row announcement is noise — but a cell all the same, or the
	 * header is one short of the rows beneath it.
	 */
	public function test_the_header_names_every_sub_field(): void {
		$html   = $this->render( 'table' );
		$header = $this->rows( $html )[0];

		foreach ( [ 'Country', 'Rate', 'Kind' ] as $label ) {
			$this->assertStringContainsString(
				sprintf( '>%s</th>', $label ),
				$header,
				sprintf( '%s has no column header.', $label )
			);
		}

		$this->assertSame( count( self::SUB_FIELDS ) + 1, $this->cells( $header ) );
	}

	/**
	 * A stored row carries exactly as many cells as the header.
	 */
	public function test_a_row_has_one_cell_per_column(): void {
		$html = $this->render(
			'table',
			[
				[ 'country' => 'IE', 'rate' => 23, 'kind' => 'percent' ],
				[ 'country' => 'GB', 'rate' => 20, 'kind' => 'percent' ],
			]
		);

		$rows = $this->rows( $html );

		// Header, two stored rows, and the template's row.
		$this->assertCount( 4, $rows );

		$expected = $this->cells( $rows[0] );

		foreach ( array_slice( $rows, 1 ) as $index => $row ) {
			$this->assertSame(
				$expected,
				$this->cells( $row ),
				sprintf( 'Row %d does not have one cell per column.', $index + 1 )
			);
		}
	}

	/**
	 * The template's row matches too, since the script clones it.
	 *
	 * And it is inside the table: a <tr> in a <template> in a <div> is thrown
	 * away by the parser, so the row to clone would not be there at all.
	 */
	public function test_the_template_is_inside_the_table_and_matches(): void {
		$html = $this->render( 'table' );

		$this->assertMatchesRegularExpression(
			'/<table[^>]*>.*<template[^>]*field-kit__repeater-template/s',
			$html,
			'The template is outside the table; its row will be discarded.'
		);

		$rows = $this->rows( $html );

		$this->assertSame( $this->cells( $rows[0] ), $this->cells( end( $rows ) ) );
	}

	/**
	 * A sub-field draws no label of its own; the column header is the label.
	 *
	 * Three rows of "Country" down the left of a table is the thing the table
	 * layout exists to stop.
	 */
	public function test_a_cell_draws_no_visible_label(): void {
		$html = $this->render( 'table', [ [ 'country' => 'IE' ] ] );
		$body = $this->rows( $html )[1];

		$this->assertStringNotContainsString( '<label', $body );
		$this->assertStringContainsString( 'data-colname="Country"', $body );
	}

	/**
	 * The stacked layout is still a list of list items.
	 */
	public function test_the_stacked_layout_is_a_list(): void {
		$html = $this->render( 'stacked', [ [ 'country' => 'IE' ] ] );

		$this->assertStringContainsString( '<ol class="field-kit__repeater-rows"', $html );
		$this->assertStringContainsString( '<li class="field-kit__repeater-row"', $html );
		$this->assertStringNotContainsString( '<table', $html );
	}

	/**
	 * Both layouts share the class the script addresses rows by.
	 *
	 * Which is why the stacked layout's appearance cannot be attached to it —
	 * see the stylesheet test that holds that line.
	 */
	public function test_both_layouts_share_the_row_class(): void {
		foreach ( [ 'table', 'stacked' ] as $layout ) {
			$this->assertStringContainsString(
				'class="field-kit__repeater-row"',
				$this->render( $layout, [ [ 'country' => 'IE' ] ] ),
				sprintf( 'The %s layout does not mark its rows.', $layout )
			);
		}
	}
}
