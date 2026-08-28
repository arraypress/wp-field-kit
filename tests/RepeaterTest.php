<?php
/**
 * Repeater layout tests.
 *
 * @package ArrayPress\FieldKit
 */

declare( strict_types=1 );

namespace ArrayPress\FieldKit\Tests;

use ArrayPress\FieldKit\Context\ArrayContext;
use ArrayPress\FieldKit\Field;
use ArrayPress\FieldKit\FieldSet;
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

		// Header, two stored rows, the empty state and the template's row.
		$this->assertCount( 5, $rows );

		$expected = $this->cells( $rows[0] );

		foreach ( array_slice( $rows, 1 ) as $index => $row ) {
			// The empty state is deliberately one cell spanning the lot.
			if ( str_contains( $row, 'no-items' ) ) {
				continue;
			}

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
	 * An empty table says so inside itself.
	 *
	 * A message printed under the table leaves a row of column headings
	 * sitting over nothing, which reads as a table that failed to load rather
	 * than one with nothing in it yet. core writes `<tr class="no-items">`
	 * with a cell spanning every column, and so does this.
	 */
	public function test_an_empty_table_says_so_in_a_row_of_its_own(): void {
		$html = $this->render( 'table' );

		$this->assertMatchesRegularExpression(
			'/<table[^>]*>[\s\S]*<tr class="no-items">[\s\S]*<\/table>/',
			$html,
			'The empty state is outside the table.'
		);

		$this->assertStringContainsString(
			sprintf( 'colspan="%d"', count( self::SUB_FIELDS ) + 1 ),
			$html,
			'The empty state does not span every column.'
		);

		// Present but hidden once there are rows, so the script has something
		// to show again when the last one is removed.
		$this->assertStringContainsString(
			'field-kit__repeater-empty" hidden',
			$this->render( 'table', [ [ 'country' => 'IE' ] ] )
		);
	}

	/**
	 * The empty state can be worded by the caller.
	 */
	public function test_the_empty_state_can_be_worded(): void {
		$field = new Field(
			'rates',
			( new Registry() )->get( 'repeater' ),
			[
				'label'       => 'Rates',
				'input_name'  => 'rates',
				'layout'      => 'table',
				'empty_label' => 'No tax rates yet.',
				'fields'      => self::SUB_FIELDS,
			],
			null
		);

		$this->assertStringContainsString( 'No tax rates yet.', ( new Renderer() )->render( $field ) );
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

	/**
	 * The files type is a repeater with its columns already decided.
	 *
	 * Every consumer that wanted a list of downloadable files wrote the same
	 * two columns, so they are the defaults. The file column is `file_url`
	 * rather than `file`: the URL stays visible and editable beside the media
	 * button, which is the only way to express a file hosted somewhere the
	 * media library does not know about.
	 */
	public function test_the_files_type_brings_its_own_columns(): void {
		$html = $this->through_a_set( [ 'downloads' => [ 'type' => 'files', 'label' => 'Files' ] ] );

		$this->assertStringContainsString( 'field-kit__repeater--table', $html, 'A files field is not a table.' );
		$this->assertStringContainsString( '>Name</th>', $html );
		$this->assertStringContainsString( '>File</th>', $html );

		// The URL is a control, not just a picker result.
		$this->assertStringContainsString( 'field-kit__media--file_url', $html );
		$this->assertStringContainsString( 'field-kit__media-choose', $html );

		$this->assertStringContainsString( 'Add file', $html );
		$this->assertStringContainsString( 'No files yet.', $html );
	}

	/**
	 * Its columns are defaults, not fixtures.
	 *
	 * A caller wanting a third column — a download limit, an expiry — should
	 * not have to give up the layout and the wording to get it.
	 */
	public function test_the_files_columns_can_be_replaced(): void {
		$html = $this->through_a_set(
			[
				'downloads' => [
					'type'   => 'files',
					'label'  => 'Files',
					'fields' => [
						'file'  => [ 'type' => 'file_url', 'label' => 'File' ],
						'limit' => [ 'type' => 'number', 'label' => 'Downloads' ],
					],
				],
			]
		);

		$this->assertStringContainsString( '>Downloads</th>', $html );
		$this->assertStringNotContainsString( '>Name</th>', $html );

		// Still a table, still worded for files.
		$this->assertStringContainsString( 'field-kit__repeater--table', $html );
		$this->assertStringContainsString( 'Add file', $html );
	}

	/**
	 * Both layouts reorder from one handle rather than a pair of chevrons.
	 *
	 * The chevrons were two controls doing one job and cost a click per
	 * position; the handle is also what makes the row draggable at all.
	 *
	 * @return void
	 */
	public function test_a_row_reorders_from_a_drag_handle(): void {
		foreach ( [ 'stacked', 'table' ] as $layout ) {
			$html = $this->render( $layout, [ [ 'country' => 'IE' ], [ 'country' => 'GB' ] ] );

			$this->assertStringContainsString( 'field-kit__drag-handle', $html, $layout );
			$this->assertStringContainsString( 'dashicons-menu', $html, $layout );
			$this->assertStringNotContainsString( 'data-action="move-up"', $html, $layout );
			$this->assertStringNotContainsString( 'data-action="move-down"', $html, $layout );
		}
	}

	/**
	 * The handle is reachable and operable without a pointer.
	 *
	 * A span with a grab cursor would look identical and leave the list
	 * unorderable for anyone using a keyboard.
	 *
	 * @return void
	 */
	public function test_the_handle_is_a_button_that_announces_its_position(): void {
		$html = $this->render( 'stacked', [ [ 'country' => 'IE' ], [ 'country' => 'GB' ] ] );

		$this->assertMatchesRegularExpression(
			'/<button[^>]*class="[^"]*field-kit__drag-handle/',
			$html
		);
		$this->assertStringContainsString( 'aria-keyshortcuts="ArrowUp ArrowDown"', $html );
		$this->assertStringContainsString( '1 of 2', $html );
		$this->assertStringContainsString( '2 of 2', $html );
	}

	/**
	 * The script rewrites the position after a move, from this template.
	 *
	 * Without it the announced position goes stale the moment a row is
	 * dragged, and the handle reports where the row used to be.
	 *
	 * @return void
	 */
	public function test_the_handle_carries_a_template_for_the_script(): void {
		$html = $this->render( 'stacked', [ [ 'country' => 'IE' ] ] );

		$this->assertStringContainsString( 'data-label-template', $html );
		$this->assertStringContainsString( '{position}', $html );
		$this->assertStringContainsString( '{total}', $html );
	}

	/**
	 * Removing a row is still its own control.
	 *
	 * @return void
	 */
	public function test_a_row_can_still_be_removed(): void {
		$this->assertStringContainsString(
			'data-action="remove"',
			$this->render( 'stacked', [ [ 'country' => 'IE' ] ] )
		);
	}

	/**
	 * A width is a class on the wrapper, so two fields share a line.
	 *
	 * @return void
	 */
	public function test_a_width_reaches_the_field_wrapper(): void {
		$html = $this->through_a_set(
			[
				'amount'     => [ 'type' => 'number', 'label' => 'Amount', 'width' => 'half' ],
				'compare_at' => [ 'type' => 'number', 'label' => 'Compare at', 'width' => 'half' ],
			]
		);

		$this->assertSame( 2, substr_count( $html, 'field-kit__field--half' ) );
	}

	/**
	 * An unrecognised width is inert rather than emitting a dead class.
	 *
	 * A class no stylesheet defines renders full width, which reads as the
	 * key having been ignored -- so it is, explicitly.
	 *
	 * @return void
	 */
	public function test_an_unknown_width_emits_no_class(): void {
		$html = $this->through_a_set(
			[ 'amount' => [ 'type' => 'number', 'label' => 'Amount', 'width' => 'two-fifths' ] ]
		);

		$this->assertStringNotContainsString( 'two-fifths', $html );
		$this->assertStringContainsString( 'field-kit__field--number', $html );
	}

	/**
	 * The kit does not warn about configuration it put there itself.
	 *
	 * A repeater sets `inline` on every child it builds. It was declared by
	 * FileType alone, so a row of a text, a number and a select reported
	 * three keys nothing reads -- from the caller's side, three complaints
	 * about a key the caller never wrote.
	 *
	 * @return void
	 */
	public function test_a_row_reports_no_unknown_keys(): void {
		$registry = new Registry();
		$type     = $registry->get( 'repeater' );

		$owner = new Field(
			'rates',
			$type,
			[ 'label' => 'Rates', 'input_name' => 'rates', 'fields' => self::SUB_FIELDS ],
			null
		);

		// The children as the repeater builds them, which is where the extras
		// it adds to every row are merged in.
		$child = new \ReflectionMethod( $type, 'child' );

		$unknown = [];

		foreach ( $owner->sub_fields() as $key => $config ) {
			$built = $child->invoke( $type, $owner, (string) $key, (array) $config, null, 'rates[0]', 'row0' );

			$this->assertNotNull( $built, (string) $key );

			$unknown = array_merge( $unknown, $built->unknown_keys() );
		}

		$this->assertSame( [], array_values( array_unique( $unknown ) ) );
	}

	/**
	 * Render a field through a set, which is where type defaults are applied.
	 *
	 * Building a Field directly skips them — the merge happens in FieldSet —
	 * so a test that did that would assert on a files field with no columns
	 * and pass for the wrong reason.
	 *
	 * @param array<string, array<string, mixed>> $fields Field configuration.
	 *
	 * @return string
	 */
	private function through_a_set( array $fields ): string {
		return ( new FieldSet( $fields, new ArrayContext( [] ), '' ) )->render();
	}

}
