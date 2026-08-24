<?php
/**
 * Card choice tests.
 *
 * @package ArrayPress\FieldKit
 */

declare( strict_types=1 );

namespace ArrayPress\FieldKit\Tests;

use ArrayPress\FieldKit\Context\ArrayContext;
use ArrayPress\FieldKit\FieldSet;
use PHPUnit\Framework\TestCase;

/**
 * A plan picker, an onboarding step, a payment-method chooser: each option
 * needs a heading and a sentence, which a radio with a long label reads badly
 * for.
 *
 * Underneath it is still a radio group, and that is the part worth pinning
 * down — the whole reason to build it this way rather than out of divs and a
 * click handler is that arrow keys, focus and the fieldset all come free.
 */
final class CardChoiceTest extends TestCase {

	/**
	 * Render a card choice.
	 *
	 * @param array<string, mixed> $config Extra configuration.
	 * @param mixed                $stored The stored value.
	 *
	 * @return string
	 */
	private function render( array $config = [], mixed $stored = null ): string {
		return ( new FieldSet(
			[
				'plan' => array_merge(
					[
						'type'    => 'card_choice',
						'label'   => 'Plan',
						'options' => [
							'free' => [ 'label' => 'Free', 'description' => 'One site' ],
							'pro'  => [ 'label' => 'Pro', 'description' => 'Five sites', 'icon' => 'star-filled' ],
							'plus' => 'Plus',
						],
					],
					$config
				),
			],
			new ArrayContext( null === $stored ? [] : [ 'plan' => $stored ] ),
			''
		) )->render();
	}

	/**
	 * It is a radio group, so the keyboard works without any script.
	 */
	public function test_it_is_a_radio_group(): void {
		$html = $this->render();

		$this->assertSame( 3, substr_count( $html, 'type="radio"' ) );
		$this->assertSame( 3, substr_count( $html, 'name="plan"' ) );

		// One fieldset and legend, from the renderer, because there is no
		// single control for a label to point at.
		$this->assertStringContainsString( '<fieldset', $html );
		$this->assertStringContainsString( '<legend', $html );
	}

	/**
	 * Each label wraps its own input.
	 *
	 * No id to collide with the same option in another repeater row, and the
	 * whole card becomes the hit area.
	 */
	public function test_each_label_wraps_its_input(): void {
		$html = $this->render();

		$this->assertSame( 3, substr_count( $html, '<label class="field-kit__card"><input' ) );
		$this->assertStringNotContainsString( 'field-kit__card-input" id=', $html );
	}

	/**
	 * The short and long option forms work in the same list.
	 */
	public function test_both_option_forms_are_accepted(): void {
		$html = $this->render();

		$this->assertStringContainsString( '>Free</span>', $html );
		$this->assertStringContainsString( '>One site</span>', $html );

		// The short form: a label and nothing else.
		$this->assertStringContainsString( '>Plus</span>', $html );
	}

	/**
	 * An icon is decorative; the title is the name.
	 */
	public function test_an_icon_is_decorative(): void {
		$this->assertMatchesRegularExpression(
			'/dashicons-star-filled[^>]*aria-hidden="true"/',
			$this->render()
		);
	}

	/**
	 * The stored value is the one checked.
	 */
	public function test_the_stored_value_is_checked(): void {
		$html = $this->render( [], 'pro' );

		$this->assertSame( 1, substr_count( $html, 'checked' ) );
		$this->assertMatchesRegularExpression( '/value="pro" checked/', $html );
	}

	/**
	 * Only an offered value is stored.
	 */
	public function test_only_an_offered_value_is_stored(): void {
		$context = new ArrayContext( [] );

		$set = new FieldSet(
			[
				'plan' => [
					'type'    => 'card_choice',
					'options' => [ 'free' => 'Free', 'pro' => 'Pro' ],
				],
			],
			$context,
			''
		);

		$set->save( [ 'plan' => 'pro' ] );
		$this->assertSame( 'pro', $context->values()['plan'] );

		// Nothing survives the allow-list, so the option context drops it —
		// which is a store's answer to an empty value.
		$set->save( [ 'plan' => 'enterprise' ] );
		$this->assertArrayNotHasKey( 'plan', $context->values() );
	}

	/**
	 * The column count reaches the stylesheet.
	 */
	public function test_the_column_count_reaches_the_markup(): void {
		$this->assertStringContainsString(
			'--field-kit-card-columns:2',
			$this->render( [ 'columns' => 2 ] )
		);
	}

	/**
	 * It does not fit an inline row.
	 *
	 * The plain radio group it inherits from does, which is why it has to say
	 * so — a grid of cards with sentences in them is the opposite of what one
	 * row of a list table has room for.
	 */
	public function test_it_is_not_an_inline_type(): void {
		$this->assertFalse( ( new \ArrayPress\FieldKit\Registry() )->get( 'card_choice' )->supports_inline() );
	}
}
