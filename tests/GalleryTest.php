<?php
/**
 * Gallery limit tests.
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
 * A gallery's cap is the sort of rule that gets written into the script and
 * nowhere else, which makes it a suggestion. The value posts as a plain
 * comma-separated list of ids in an ordinary hidden input, so anything that
 * can post a form can post thirty of them to a field that says ten.
 *
 * So the cap is asserted where it has to hold: on the way in.
 */
final class GalleryTest extends TestCase {

	/**
	 * Build a gallery field.
	 *
	 * @param array<string, mixed> $config Extra configuration.
	 *
	 * @return Field
	 */
	private function field( array $config = [] ): Field {
		return new Field(
			'photos',
			( new Registry() )->get( 'gallery' ),
			array_merge( [ 'label' => 'Photos', 'input_name' => 'photos' ], $config ),
			null
		);
	}

	/**
	 * A submission longer than the cap is cut to it.
	 */
	public function test_a_submission_is_cut_to_the_limit(): void {
		$field = $this->field( [ 'max_items' => 3 ] );

		$this->assertSame(
			[ 1, 2, 3 ],
			$field->type()->sanitize( '1,2,3,4,5', $field )
		);
	}

	/**
	 * An array posts the same way a string does.
	 */
	public function test_an_array_is_cut_the_same_way(): void {
		$field = $this->field( [ 'max_items' => 2 ] );

		$this->assertSame(
			[ 7, 8 ],
			$field->type()->sanitize( [ 7, 8, 9 ], $field )
		);
	}

	/**
	 * No cap means no cap, not a default one.
	 */
	public function test_without_a_limit_everything_is_kept(): void {
		$field = $this->field();

		$this->assertCount( 5, $field->type()->sanitize( '1,2,3,4,5', $field ) );
	}

	/**
	 * Empty and non-numeric entries are dropped either way.
	 */
	public function test_rubbish_is_dropped_before_the_limit_applies(): void {
		$field = $this->field( [ 'max_items' => 3 ] );

		// Were the filtering done after slicing, this would return two ids.
		$this->assertSame(
			[ 4, 5, 6 ],
			$field->type()->sanitize( '0,abc,4,5,6,7', $field )
		);
	}

	/**
	 * The limit reaches the script, so the button can disable itself.
	 */
	public function test_the_limit_reaches_the_markup(): void {
		$html = ( new Renderer() )->render( $this->field( [ 'max_items' => 4 ] ) );

		$this->assertStringContainsString( 'data-max-items="4"', $html );
	}

	/**
	 * And the script actually reads it, on both paths that need it.
	 *
	 * The frame is a multi-select, so the check cannot happen once before it
	 * opens — each attachment arrives separately.
	 */
	public function test_the_script_enforces_it(): void {
		$js = (string) file_get_contents( dirname( __DIR__ ) . '/assets/js/field-kit.js' );

		$this->assertSame(
			2,
			substr_count( $js, 'wrap.dataset.maxItems' ),
			'The gallery limit must be checked as items arrive and again when the list settles.'
		);
	}
}
