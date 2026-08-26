<?php
/**
 * File field layout tests.
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
 * A file field draws itself one of two ways, and the row is what decides.
 *
 * Standalone -- on a settings screen, a term form, a metabox -- it is what
 * every other media field is: the current selection, then a labelled
 * "Choose file" and a labelled "Remove" beneath it. There is a column of
 * space for that, and two words are easier to act on than two glyphs.
 *
 * Inside a repeater there is one line and no column. The labelled pair
 * wrapped below the input and doubled the height of every row, and in the
 * table layout -- which is what a download's file list is -- it did that in
 * a cell a few characters wide. There the picker moves inside the input,
 * the way EDD draws a download's file row.
 *
 * The switch is the `inline` flag a repeater puts on every child it builds,
 * so nothing has to be configured per field and a group -- which is a stack
 * of fields, not a row -- is unaffected.
 */
final class MediaFieldTest extends TestCase {

	/**
	 * Render a standalone field.
	 *
	 * @param string               $type   Type id.
	 * @param array<string, mixed> $config Field configuration.
	 * @param mixed                $value  Stored value.
	 *
	 * @return string
	 */
	private function render( string $type, array $config = [], $value = null ): string {
		$field = new Field(
			'demo',
			( new Registry() )->get( $type ),
			array_merge( [ 'label' => 'Manual', 'input_name' => 'demo' ], $config ),
			$value
		);

		return ( new Renderer() )->render( $field );
	}

	/**
	 * Render the same field as one column of a repeater row.
	 *
	 * Through the repeater rather than by setting `inline` directly, so this
	 * also pins that a repeater is what supplies it.
	 *
	 * @param string $type  Sub-field type.
	 * @param mixed  $value Stored value for the one row.
	 *
	 * @return string
	 */
	private function render_in_row( string $type, $value = null ): string {
		$field = new Field(
			'files',
			( new Registry() )->get( 'repeater' ),
			[
				'label'      => 'Files',
				'input_name' => 'files',
				'fields'     => [
					'name' => [ 'type' => 'text', 'label' => 'Name' ],
					'file' => [ 'type' => $type, 'label' => 'Manual' ],
				],
			],
			null
		);

		return ( new Renderer() )->render( $field->with_value( [ [ 'file' => $value ] ] ) );
	}

	/**
	 * Standalone, a URL field keeps its labelled buttons.
	 */
	public function test_a_standalone_file_url_uses_labelled_buttons(): void {
		$html = $this->render( 'file_url', [], 'https://example.com/manual.pdf' );

		$this->assertStringContainsString( 'field-kit__media-actions', $html );
		$this->assertStringContainsString( '>Choose file</button>', $html );
		$this->assertStringContainsString( '>Remove</button>', $html );
		$this->assertStringNotContainsString( 'field-kit__media-input', $html );
		$this->assertStringNotContainsString( 'dashicons', $html );
	}

	/**
	 * Standalone, a file field shows the name above its buttons.
	 */
	public function test_a_standalone_file_shows_its_name_above_the_buttons(): void {
		$html = $this->render( 'file', [], 42 );

		$this->assertStringContainsString( 'field-kit__media-preview', $html );
		$this->assertStringContainsString( '>file.pdf</span>', $html );
		$this->assertStringContainsString( '>Choose file</button>', $html );
		$this->assertStringNotContainsString( 'field-kit__media-input', $html );
	}

	/**
	 * In a row, the picker sits inside the input.
	 */
	public function test_a_file_url_in_a_row_puts_its_picker_inside_the_input(): void {
		$html = $this->render_in_row( 'file_url', 'https://example.com/manual.pdf' );

		$this->assertStringContainsString( 'field-kit__media-input', $html );
		$this->assertStringContainsString( 'field-kit__media-buttons', $html );

		// Inside the group, not on a line of its own after it.
		$this->assertMatchesRegularExpression(
			'/<div class="field-kit__media-input">.*field-kit__media-choose.*<\/div>/s',
			$html
		);

		// And not one of core's bordered buttons.
		$this->assertStringNotContainsString( 'field-kit__media-actions', $html );
		$this->assertStringNotContainsString( '>Choose file</button>', $html );
	}

	/**
	 * A URL in a row has no clear button.
	 *
	 * The input is a real, editable URL field, so a control that empties it
	 * costs a row's width to duplicate the keyboard.
	 */
	public function test_a_file_url_in_a_row_has_no_clear_button(): void {
		$html = $this->render_in_row( 'file_url', 'https://example.com/manual.pdf' );

		$this->assertStringNotContainsString( 'field-kit__media-clear', $html );
	}

	/**
	 * The inline picker is icon-only, so it carries its own name.
	 */
	public function test_the_inline_picker_names_the_field_it_acts_on(): void {
		$html = $this->render_in_row( 'file_url' );

		$this->assertStringContainsString( 'aria-label="Choose a file for Manual"', $html );
		$this->assertMatchesRegularExpression( '/dashicons-upload[^>]*aria-hidden="true"/', $html );
	}

	/**
	 * A file field in a row shows the file's name, not its attachment id.
	 */
	public function test_a_file_in_a_row_shows_a_name_rather_than_an_id(): void {
		$html = $this->render_in_row( 'file', 42 );

		// The id is still what gets submitted.
		$this->assertMatchesRegularExpression(
			'/<input class="field-kit__media-value"[^>]*type="hidden"[^>]*value="42"/',
			$html
		);

		// And the visible input is readonly, so the name cannot be edited
		// into something that no longer matches the id behind it.
		$this->assertMatchesRegularExpression(
			'/<input[^>]*class="[^"]*field-kit__media-filename[^"]*"[^>]*readonly/',
			$html
		);
	}

	/**
	 * Two controls, and the markup says so for the padding rule.
	 *
	 * The input's right-hand padding has to clear whatever sits on top of
	 * it, and that depends on how many controls there are.
	 */
	public function test_a_file_in_a_row_marks_itself_as_having_two_controls(): void {
		$html = $this->render_in_row( 'file', 42 );

		$this->assertStringContainsString( 'field-kit__media-input--pair', $html );
		$this->assertStringContainsString( 'field-kit__media-choose', $html );
		$this->assertStringContainsString( 'field-kit__media-clear', $html );
	}

	/**
	 * Nothing chosen means no clear button to press.
	 */
	public function test_an_empty_file_in_a_row_hides_its_clear_button(): void {
		$html = $this->render_in_row( 'file' );

		$this->assertMatchesRegularExpression(
			'/<button[^>]*field-kit__media-clear[^>]*hidden/',
			$html
		);
	}

	/**
	 * A group is a stack of fields, not a row, so it changes nothing.
	 */
	public function test_a_group_does_not_make_its_children_inline(): void {
		$field = new Field(
			'block',
			( new Registry() )->get( 'group' ),
			[
				'label'      => 'Block',
				'input_name' => 'block',
				'fields'     => [ 'file' => [ 'type' => 'file_url', 'label' => 'Manual' ] ],
			],
			null
		);

		$html = ( new Renderer() )->render( $field );

		$this->assertStringContainsString( '>Choose file</button>', $html );
		$this->assertStringNotContainsString( 'field-kit__media-input', $html );
	}

	/**
	 * A readonly input is still an input, and must not read as disabled.
	 *
	 * Core paints readonly inputs on the same grey it uses for disabled
	 * ones, which made a field the picker had just filled in look broken.
	 */
	public function test_the_readonly_name_is_not_painted_as_disabled(): void {
		$css = (string) file_get_contents( dirname( __DIR__ ) . '/assets/css/field-kit.css' );

		$this->assertMatchesRegularExpression(
			'/\.field-kit__media-filename\[readonly\]\s*\{[^}]*background:\s*#fff/',
			$css
		);
	}

	/**
	 * The script fills the name input in when something is chosen.
	 *
	 * Without it the field showed the previous file's name until the page
	 * was saved and reloaded.
	 */
	public function test_the_script_updates_the_name_input(): void {
		$js = (string) file_get_contents( dirname( __DIR__ ) . '/assets/js/field-kit.js' );

		$this->assertStringContainsString( 'input.field-kit__media-filename', $js );
		$this->assertMatchesRegularExpression( '/filenameInput\.value = attachment\.filename/', $js );
	}
}
