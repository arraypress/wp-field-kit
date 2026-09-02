<?php
/**
 * The character count.
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
 * A limit worth setting is worth showing. The browser stops anyone typing
 * past a maxlength and says nothing about it, so a meta description cut
 * off at 160 characters looks like a keyboard that stopped working.
 *
 * The count is rendered with the saved value's length already in it, so
 * the field reads right before the script runs; the script only keeps it
 * moving.
 */
final class CounterTest extends TestCase {

	public function test_nothing_counts_without_a_limit(): void {
		$this->assertStringNotContainsString( 'data-counter', $this->render( 'text', [], 'hello' ) );
	}

	public function test_the_count_starts_at_the_current_length(): void {
		$html = $this->render( 'text', [ 'maxlength' => 200 ], 'Hello' );

		$this->assertStringContainsString( 'maxlength="200"', $html );
		$this->assertMatchesRegularExpression(
			'/<input[^>]*\/><span class="field-kit__count description regular-text" data-counter aria-live="polite">5 \/ 200<\/span>/',
			$html
		);
	}

	public function test_characters_are_counted_and_not_bytes(): void {
		$this->assertStringContainsString( '>5 / 10<', $this->render( 'text', [ 'maxlength' => 10 ], 'héllo' ) );
	}

	/**
	 * A postcode has a limit and no use for a count.
	 */
	public function test_it_can_be_turned_off(): void {
		$html = $this->render( 'text', [ 'maxlength' => 8, 'counter' => false ], 'AB1 2CD' );

		$this->assertStringContainsString( 'maxlength="8"', $html );
		$this->assertStringNotContainsString( 'data-counter', $html );
	}

	/**
	 * The count is as wide as the control it sits under, so its number
	 * lands at the control's end rather than the row's.
	 */
	public function test_the_count_is_as_wide_as_its_control(): void {
		$this->assertStringContainsString(
			'class="field-kit__count description large-text"',
			$this->render( 'text', [ 'maxlength' => 5, 'size' => 'large' ], '' )
		);
		$this->assertStringContainsString(
			'class="field-kit__count description"',
			$this->render( 'text', [ 'maxlength' => 5, 'size' => 'none' ], '' )
		);
	}

	public function test_a_textarea_counts_too(): void {
		$this->assertMatchesRegularExpression(
			'/<\/textarea><span class="field-kit__count description large-text" data-counter aria-live="polite">7 \/ 500<\/span>/',
			$this->render( 'textarea', [ 'maxlength' => 500 ], "one\ntwo" )
		);
		$this->assertStringNotContainsString(
			'data-counter',
			$this->render( 'textarea', [ 'maxlength' => 500, 'counter' => false ], 'x' )
		);
	}

	/**
	 * A password never renders its value, so it has nothing to count -- and
	 * a count of the saved secret's length would be a small leak.
	 */
	public function test_a_password_counts_nothing_it_has_not_shown(): void {
		$html = $this->render( 'password', [ 'maxlength' => 64 ], 'hunter2' );

		$this->assertStringContainsString( '>0 / 64<', $html );

		// After the whole control, not between the input and its button.
		$this->assertMatchesRegularExpression( '/<\/button><\/span><span class="field-kit__count/', $html );
	}

	/**
	 * The browser stops anyone typing past the limit; it does not truncate
	 * what was saved before the limit was lowered.
	 */
	public function test_a_prefilled_value_over_the_limit_is_marked(): void {
		$html = $this->render( 'text', [ 'maxlength' => 3 ], 'four' );

		$this->assertStringContainsString( 'field-kit__count--over', $html );
		$this->assertStringContainsString( '>4 / 3<', $html );
		$this->assertStringNotContainsString(
			'field-kit__count--over',
			$this->render( 'text', [ 'maxlength' => 4 ], 'four' )
		);
	}

	public function test_it_follows_an_adorned_control(): void {
		$this->assertMatchesRegularExpression(
			'/adornment--suffix.*<\/span><\/span><span class="field-kit__count/s',
			$this->render( 'text', [ 'maxlength' => 20, 'suffix' => 'px' ], '12' )
		);
	}

	public function test_a_hidden_input_counts_nothing(): void {
		$this->assertStringNotContainsString(
			'data-counter',
			$this->render( 'hidden', [ 'maxlength' => 5 ], 'abc' )
		);
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
