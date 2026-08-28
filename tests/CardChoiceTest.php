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
	 * The card is the control, so the radio inside it is not drawn.
	 *
	 * It stays in the markup: it is still what receives focus and what the
	 * arrow keys move between, and the card paints checked and focus from it.
	 * A radio dot inside a card that is itself the selected state says the
	 * same thing twice.
	 *
	 * @return void
	 */
	public function test_the_radio_is_present_but_not_drawn(): void {
		$html = $this->render();

		$this->assertStringContainsString( 'type="radio"', $html );

		// The icon beside a card is decorative and says so; the input must
		// not, or the control is hidden from the people who need it most.
		$this->assertDoesNotMatchRegularExpression(
			'/<input[^>]*field-kit__card-input[^>]*(aria-hidden|hidden=)/',
			$html
		);

		$css = (string) file_get_contents( dirname( __DIR__ ) . '/assets/css/field-kit.css' );

		// Hidden by clipping, not by display:none, which would take it out of
		// the tab order and leave the group unusable without a pointer.
		$this->assertMatchesRegularExpression( '/\.field-kit__card-input \{[^}]*clip-path/s', $css );
		$this->assertDoesNotMatchRegularExpression( '/\.field-kit__card-input \{[^}]*display:\s*none/s', $css );
	}

	/**
	 * A label obvious from what surrounds it can be kept for assistive
	 * technology without being drawn -- which is not the same as dropping it,
	 * since that leaves the group with no name at all.
	 *
	 * @return void
	 */
	public function test_a_hidden_label_is_still_a_legend(): void {
		$html = $this->render( [ 'hide_label' => true ] );

		$this->assertStringContainsString( 'field-kit__legend screen-reader-text', $html );
		$this->assertStringContainsString( 'Plan', $html );
	}

	/**
	 * And without it the legend is drawn as usual.
	 *
	 * @return void
	 */
	public function test_a_label_is_drawn_by_default(): void {
		$this->assertStringNotContainsString( 'screen-reader-text', $this->render() );
	}

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
