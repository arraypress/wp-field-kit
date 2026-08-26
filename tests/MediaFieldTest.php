<?php
/**
 * File and image field layout tests.
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
 * The picker lives inside the input it fills in.
 *
 * It used to be a labelled "Choose file" button and a labelled "Remove"
 * button on a line of their own beneath the input. One file therefore took
 * three lines, and in the table layout — where this field is a cell, and
 * where the shape is most used, because that is what a download's file list
 * is — the two buttons wrapped below the input and doubled the height of
 * every row.
 *
 * EDD draws the same field with the control at the right-hand end of the
 * input, and core draws its own search boxes that way. That is what this is.
 */
final class MediaFieldTest extends TestCase {

	/**
	 * Render a field and return the markup.
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
	 * A URL field has one control, and it sits inside the input.
	 */
	public function test_a_file_url_puts_its_picker_inside_the_input(): void {
		$html = $this->render( 'file_url', [], 'https://example.com/manual.pdf' );

		$this->assertStringContainsString( 'field-kit__media-input', $html );
		$this->assertStringContainsString( 'field-kit__media-buttons', $html );
		$this->assertStringContainsString( 'field-kit__media-choose', $html );

		// The button is inside the group, not on a line of its own after it.
		$this->assertMatchesRegularExpression(
			'/<div class="field-kit__media-input">.*field-kit__media-choose.*<\/div>/s',
			$html
		);

		// And it is not one of core's bordered buttons.
		$this->assertStringNotContainsString( 'class="button field-kit__media-choose"', $html );
	}

	/**
	 * A URL field has no clear button.
	 *
	 * The input is a real, editable URL field, so a control that empties it
	 * only duplicates the keyboard.
	 */
	public function test_a_file_url_has_no_clear_button(): void {
		$html = $this->render( 'file_url', [], 'https://example.com/manual.pdf' );

		$this->assertStringNotContainsString( 'field-kit__media-clear', $html );
		$this->assertStringNotContainsString( 'field-kit__media-actions', $html );
	}

	/**
	 * The picker is icon-only, so it carries its own name.
	 *
	 * A screen with several file fields would otherwise present a list of
	 * buttons whose accessible name is empty.
	 */
	public function test_the_picker_names_the_field_it_acts_on(): void {
		$html = $this->render( 'file_url', [ 'label' => 'Manual' ] );

		$this->assertStringContainsString( 'aria-label="Choose a file for Manual"', $html );
		$this->assertMatchesRegularExpression( '/dashicons-upload[^>]*aria-hidden="true"/', $html );
	}

	/**
	 * A file field shows the file's name, not its attachment id.
	 */
	public function test_a_file_field_shows_a_name_rather_than_an_id(): void {
		$html = $this->render( 'file', [], 42 );

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
	 * A file field has both controls, and says so for the padding rule.
	 *
	 * The input's right-hand padding has to clear whatever sits on top of
	 * it, and that depends on how many controls there are.
	 */
	public function test_a_file_field_marks_itself_as_having_two_controls(): void {
		$html = $this->render( 'file', [], 42 );

		$this->assertStringContainsString( 'field-kit__media-input--pair', $html );
		$this->assertStringContainsString( 'field-kit__media-choose', $html );
		$this->assertStringContainsString( 'field-kit__media-clear', $html );
	}

	/**
	 * Nothing chosen means no clear button to press.
	 */
	public function test_an_empty_file_field_hides_its_clear_button(): void {
		$html = $this->render( 'file' );

		$this->assertMatchesRegularExpression(
			'/<button[^>]*field-kit__media-clear[^>]*hidden/',
			$html
		);
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

		$this->assertStringContainsString( "input.field-kit__media-filename", $js );
		$this->assertMatchesRegularExpression( '/filenameInput\.value = attachment\.filename/', $js );
	}
}
