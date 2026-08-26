<?php
/**
 * Icon picker tests.
 *
 * @package ArrayPress\FieldKit
 */

declare( strict_types=1 );

namespace ArrayPress\FieldKit\Tests;

use ArrayPress\FieldKit\Context\ArrayContext;
use ArrayPress\FieldKit\FieldSet;
use PHPUnit\Framework\TestCase;

/**
 * The chosen icon is in the control, and every choice shows its own.
 *
 * The preview used to sit beside the select as a flex sibling, which read as
 * a stray glyph that had come loose from the field; and the dropdown was a
 * list of forty icon names with no icons in it, which is not an icon picker.
 */
final class IconPickerTest extends TestCase {

	/**
	 * Render an icon field.
	 *
	 * @param string $value Stored value.
	 *
	 * @return string
	 */
	private function render( string $value = '' ): string {
		// Through a field set, because the type's own defaults -- the icon
		// list among them -- are merged there rather than by Field.
		$set = new FieldSet(
			[ 'icon' => [ 'type' => 'icon', 'label' => 'Icon' ] ],
			new ArrayContext( '' === $value ? [] : [ 'icon' => $value ] )
		);

		return $set->render();
	}

	/**
	 * Every option says which glyph it means.
	 *
	 * The value is already the dashicon class; this is what makes it legible
	 * to the combobox, which reads its rows off the real options.
	 */
	public function test_each_option_carries_its_icon(): void {
		$html = $this->render();

		$this->assertStringContainsString(
			'<option value="dashicons-cart" data-icon="dashicons-cart">',
			$html
		);

		// Not the empty option: it is not a choice with an icon.
		$this->assertStringNotContainsString( '<option value="" data-icon', $html );
	}

	/**
	 * The preview is inside the wrapper the control is replaced within.
	 *
	 * The combobox script inserts its own element where the select was, so
	 * the wrapper is what the preview can be positioned against and still be
	 * in the right place afterwards.
	 */
	public function test_the_preview_is_positioned_against_the_wrapper(): void {
		$html = $this->render( 'dashicons-cart' );

		$this->assertMatchesRegularExpression(
			'/<span class="field-kit__icon">\s*<span class="field-kit__icon-preview[^"]*dashicons-cart/',
			$html
		);

		$css = (string) file_get_contents( dirname( __DIR__ ) . '/assets/css/field-kit.css' );

		$this->assertMatchesRegularExpression(
			'/\.field-kit__icon\s*\{[^}]*position:\s*relative/',
			$css,
			'The wrapper is not a positioning context.'
		);

		$this->assertMatchesRegularExpression(
			'/\.field-kit__icon-preview\s*\{[^}]*position:\s*absolute/',
			$css,
			'The preview is not laid over the control.'
		);
	}

	/**
	 * Both controls leave room for it.
	 *
	 * Which one is on the page depends on whether the script has run, and the
	 * text must not shift when it does.
	 */
	public function test_both_controls_leave_room_for_the_preview(): void {
		$css = (string) file_get_contents( dirname( __DIR__ ) . '/assets/css/field-kit.css' );

		preg_match(
			'/\.field-kit__icon \.field-kit__icon-select,\s*\.field-kit__icon \.field-kit__combobox-input\s*\{([^}]*)\}/',
			$css,
			$match
		);

		$this->assertNotEmpty( $match, 'Only one of the two controls is padded, or neither.' );
		$this->assertStringContainsString( 'padding-left', $match[1] );
	}

	/**
	 * Nothing chosen shows a muted placeholder rather than an empty box.
	 *
	 * `class="dashicons "` renders nothing at all, so the control came up
	 * with an invisible gap and read as broken before it was touched.
	 */
	public function test_an_empty_picker_shows_a_placeholder(): void {
		$html = $this->render();

		$this->assertStringContainsString( 'dashicons-art', $html );
		$this->assertStringContainsString( 'field-kit__icon-preview--empty', $html );
		$this->assertStringNotContainsString( 'class="field-kit__icon-preview dashicons "', $html );
	}

	/**
	 * The script draws the glyph beside each row.
	 */
	public function test_the_script_draws_an_icon_per_option(): void {
		$js = (string) file_get_contents( dirname( __DIR__ ) . '/assets/js/field-kit.js' );

		$this->assertStringContainsString( 'option.dataset.icon', $js );
		$this->assertStringContainsString( 'field-kit__combobox-option--icon', $js );
	}
}
