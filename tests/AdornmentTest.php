<?php
/**
 * Prefix and suffix adornments.
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
 * A unit belongs inside the box it qualifies.
 *
 * Set beside the input as an element of its own it wraps onto a line of its
 * own in a narrow panel, which is what amount_type does and what the money
 * field was written to stop. The same construction now serves any single
 * input -- and because the unit is aria-hidden as drawn, it has to reach
 * assistive technology some other way, or "10" in a box marked "%" is
 * announced as ten.
 */
final class AdornmentTest extends TestCase {

	public function test_a_prefix_is_drawn_inside_the_control(): void {
		$html = $this->render( 'number', [ 'prefix' => '$' ], '19.99' );

		$this->assertStringContainsString(
			'<span class="field-kit__adornment field-kit__adornment--prefix" aria-hidden="true">$</span>',
			$html
		);

		// The affix precedes the input, and both are inside one wrapper.
		$this->assertMatchesRegularExpression(
			'/<span class="field-kit__adorned field-kit__adorned--prefix">.*adornment--prefix.*<input/s',
			$html
		);
	}

	public function test_a_suffix_follows_the_input(): void {
		$html = $this->render( 'number', [ 'suffix' => '%' ], '10' );

		$this->assertStringContainsString( 'field-kit__adorned--suffix', $html );
		$this->assertMatchesRegularExpression(
			'/<input[^>]*\/><span class="field-kit__adornment field-kit__adornment--suffix" aria-hidden="true">%<\/span>/',
			$html
		);
	}

	public function test_both_sides_can_be_set_at_once(): void {
		$this->assertStringContainsString(
			'class="field-kit__adorned field-kit__adorned--prefix field-kit__adorned--suffix field-kit__adorned--wide-suffix"',
			$this->render( 'text', [ 'prefix' => '$', 'suffix' => 'USD' ], '5' )
		);
	}

	/**
	 * A longer affix needs more room, and CSS cannot see how long it is.
	 * Measured in characters: "£" is one, however many bytes it takes.
	 */
	public function test_a_long_affix_gets_more_room(): void {
		$this->assertStringContainsString( 'field-kit__adorned--wide-suffix', $this->render( 'number', [ 'suffix' => 'days' ], '3' ) );
		$this->assertStringNotContainsString( 'field-kit__adorned--wide', $this->render( 'number', [ 'suffix' => '%' ], '3' ) );
		$this->assertStringContainsString( 'field-kit__adorned--wide-prefix', $this->render( 'text', [ 'prefix' => 'CHF' ], '3' ) );
		$this->assertStringNotContainsString( 'field-kit__adorned--wide', $this->render( 'text', [ 'prefix' => '£' ], '3' ) );
	}

	/**
	 * The drawn affix is hidden from assistive technology, so the same text
	 * is supplied as a description of the input.
	 */
	public function test_the_affix_is_announced_with_the_value(): void {
		$html = $this->render( 'number', [ 'suffix' => '%' ], '10' );

		$this->assertStringContainsString( '<span class="screen-reader-text" id="demo__suffix">%</span>', $html );
		$this->assertStringContainsString( 'aria-describedby="demo__suffix"', $html );
	}

	/**
	 * Ahead of the description, so the unit is read beside the value it
	 * qualifies rather than after the help text.
	 */
	public function test_the_affix_comes_before_the_description(): void {
		$html = $this->render(
			'number',
			[ 'prefix' => '$', 'suffix' => '%', 'description' => 'Applied at checkout.' ],
			'10'
		);

		$this->assertStringContainsString( 'aria-describedby="demo__prefix demo__suffix demo__description"', $html );
		$this->assertStringContainsString( 'id="demo__prefix"', $html );
		$this->assertStringContainsString( 'id="demo__description"', $html );
	}

	public function test_nothing_is_wrapped_when_neither_is_set(): void {
		$html = $this->render( 'text', [], 'plain' );

		$this->assertStringNotContainsString( 'field-kit__adorn', $html );
		$this->assertStringNotContainsString( 'screen-reader-text', $html );
		$this->assertStringNotContainsString( 'aria-describedby', $html );
	}

	public function test_the_affix_is_escaped(): void {
		$html = $this->render( 'text', [ 'prefix' => '<b>' ], '' );

		$this->assertStringNotContainsString( '<b>', $html );
		$this->assertStringContainsString( '&lt;b&gt;', $html );
	}

	/**
	 * Every single-input type takes one, not only the ones tested above.
	 *
	 * @param string $type Type id.
	 */
	#[\PHPUnit\Framework\Attributes\DataProvider( 'inputTypeProvider' )]
	public function test_every_single_input_takes_one( string $type ): void {
		$this->assertStringContainsString(
			'field-kit__adorned--suffix',
			$this->render( $type, [ 'suffix' => 'x' ], '' ),
			sprintf( '%s takes no suffix.', $type )
		);
	}

	/**
	 * The single-input types with a box to draw in.
	 *
	 * @return array<int, array{string}>
	 */
	public static function inputTypeProvider(): array {
		return array_map(
			static fn( $id ) => [ $id ],
			[ 'text', 'number', 'email', 'url', 'tel', 'password', 'date', 'time', 'datetime', 'tags' ]
		);
	}

	public function test_a_hidden_input_has_no_box_to_draw_in(): void {
		$html = $this->render( 'hidden', [ 'prefix' => '$', 'suffix' => '%' ], '1' );

		$this->assertStringNotContainsString( 'field-kit__adorn', $html );
		$this->assertStringContainsString( 'type="hidden"', $html );
	}

	/**
	 * Tags wraps the base render in a list of its own, and still has to.
	 */
	public function test_tags_keep_their_list_and_placeholder(): void {
		$html = $this->render( 'tags', [ 'suffix' => 'tags' ], 'a, b' );

		$this->assertMatchesRegularExpression(
			'/<div class="field-kit__tags"><span class="field-kit__adorned[^"]*">.*field-kit__tags-input.*<\/span><div class="field-kit__tags-list" aria-live="polite">/s',
			$html
		);
		$this->assertStringContainsString( 'placeholder="Separate tags with commas"', $html );
		$this->assertStringContainsString( 'value="a, b"', $html );
	}

	/**
	 * Render a field of a type.
	 *
	 * @param string               $type   Type id.
	 * @param array<string, mixed> $config Field configuration.
	 * @param string               $value  Its value.
	 *
	 * @return string
	 */
	private function render( string $type, array $config, string $value ): string {
		$field = new Field(
			'demo',
			( new Registry() )->get( $type ),
			array_merge( [ 'label' => 'Demo', 'input_name' => 'demo' ], $config ),
			null
		);

		return ( new Renderer() )->render( $field->with_value( $value ) );
	}
}
