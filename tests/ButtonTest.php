<?php
/**
 * Button tests.
 *
 * @package ArrayPress\FieldKit
 */

declare( strict_types=1 );

namespace ArrayPress\FieldKit\Tests;

use ArrayPress\FieldKit\Support\Button;
use PHPUnit\Framework\TestCase;

/**
 * One button, in core's clothes.
 *
 * Most of what is asserted here is that a button is a button and not a submit,
 * and that the variants stay core's — the two things that were repeatedly got
 * wrong when every component built its own.
 */
final class ButtonTest extends TestCase {

	/**
	 * A button is a button, not a submit.
	 *
	 * The default for a button inside a form is submit, so an "Add row"
	 * button written without an explicit type saves the post. Every component
	 * that built its own had to remember this, and several did not.
	 */
	public function test_a_button_never_submits_by_accident(): void {
		$this->assertStringContainsString( 'type="button"', Button::render( [ 'label' => 'Add row' ] ) );
	}

	/**
	 * A submit can still be asked for.
	 */
	public function test_a_submit_can_be_asked_for(): void {
		$this->assertStringContainsString(
			'type="submit"',
			Button::render( [ 'label' => 'Save', 'type' => 'submit' ] )
		);
	}

	/**
	 * Each variant renders core's own class.
	 *
	 * @dataProvider variantProvider
	 *
	 * @param string $variant  The variant.
	 * @param string $expected The class it must carry.
	 */
	#[\PHPUnit\Framework\Attributes\DataProvider( 'variantProvider' )]
	public function test_a_variant_renders_cores_class( string $variant, string $expected ): void {
		$this->assertStringContainsString(
			$expected,
			Button::render( [ 'label' => 'Go', 'variant' => $variant ] )
		);
	}

	/**
	 * The variants, and the class core gives each.
	 *
	 * @return array<string, array{0: string, 1: string}>
	 */
	public static function variantProvider(): array {
		return [
			'primary'     => [ 'primary', 'button-primary' ],
			'secondary'   => [ 'secondary', 'button-secondary' ],
			'link'        => [ 'link', 'button-link' ],
			'destructive' => [ 'destructive', 'field-kit__button--delete' ],
		];
	}

	/**
	 * There are no success, warning or danger buttons.
	 *
	 * Core has two weights and one destructive treatment. A library had these
	 * three as hardcoded green, amber and red fills — not core's notice
	 * palette, and blind to the admin colour scheme the user picked. An
	 * unknown variant falls back to secondary rather than emitting a class
	 * nothing styles, which is what a plain `'button-' . $style` did.
	 *
	 * @dataProvider inventedProvider
	 *
	 * @param string $variant A variant core does not have.
	 */
	#[\PHPUnit\Framework\Attributes\DataProvider( 'inventedProvider' )]
	public function test_an_invented_variant_falls_back_to_secondary( string $variant ): void {
		$html = Button::render( [ 'label' => 'Go', 'variant' => $variant ] );

		$this->assertStringContainsString( 'button-secondary', $html );
		$this->assertStringNotContainsString( 'button-' . $variant, $html );
	}

	/**
	 * Variants core does not have.
	 *
	 * @return array<string, array{0: string}>
	 */
	public static function inventedProvider(): array {
		return [
			'success' => [ 'success' ],
			'warning' => [ 'warning' ],
			'danger'  => [ 'danger' ],
			'nonsense' => [ 'nonsense' ],
		];
	}

	/**
	 * A destructive button is core's treatment, not a red link in a border.
	 *
	 * `.button-link-delete` is core's red *link* — red text, no border. On a
	 * `.button` it produced a blue label inside a red outline, which is what
	 * this was corrected from.
	 */
	public function test_a_destructive_button_is_not_a_red_link(): void {
		$html = Button::render( [ 'label' => 'Delete', 'variant' => 'destructive' ] );

		$this->assertStringNotContainsString( 'button-link-delete', $html );
		$this->assertStringContainsString( 'class="button field-kit__button--delete"', $html );
	}

	/**
	 * Only the sizes core has.
	 */
	public function test_only_cores_sizes_are_accepted(): void {
		$this->assertStringContainsString( 'button-small', Button::render( [ 'size' => 'small', 'label' => 'x' ] ) );
		$this->assertStringNotContainsString( 'button-enormous', Button::render( [ 'size' => 'enormous', 'label' => 'x' ] ) );
	}

	/**
	 * An icon is decorative, and the label is the name.
	 */
	public function test_an_icon_is_hidden_from_assistive_technology(): void {
		$html = Button::render( [ 'label' => 'Add', 'icon' => 'plus-alt' ] );

		$this->assertStringContainsString( 'dashicons-plus-alt', $html );
		$this->assertStringContainsString( 'aria-hidden="true"', $html );
		$this->assertStringContainsString( '>Add<', $html );
	}

	/**
	 * An icon-only button still announces itself.
	 *
	 * Otherwise it is a button whose accessible name is empty, which a screen
	 * reader reads out as "button" and nothing else.
	 */
	public function test_an_icon_only_button_has_a_name(): void {
		$html = Button::render( [ 'icon' => 'trash', 'aria_label' => 'Remove row' ] );

		$this->assertStringContainsString( 'aria-label="Remove row"', $html );
	}

	/**
	 * A caller's own attributes and classes come through.
	 */
	public function test_attributes_and_classes_come_through(): void {
		$html = Button::render(
			[
				'label'      => 'Run',
				'class'      => 'my-thing',
				'disabled'   => true,
				'attributes' => [ 'data-action' => 'run' ],
			]
		);

		$this->assertStringContainsString( 'my-thing', $html );
		$this->assertStringContainsString( 'data-action="run"', $html );
		$this->assertStringContainsString( 'disabled', $html );
	}

	/**
	 * A label is escaped.
	 */
	public function test_a_label_is_escaped(): void {
		$this->assertStringNotContainsString(
			'<script',
			Button::render( [ 'label' => '<script>alert(1)</script>' ] )
		);
	}
}
