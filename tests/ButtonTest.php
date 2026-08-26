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
	 * A labelled button does not also get a picture.
	 *
	 * WordPress puts no glyph in front of a button's text anywhere in its
	 * own admin, and a button carrying both says the same thing twice. An
	 * `icon` passed alongside a `label` is dropped rather than rejected:
	 * a caller who has a label is always better served by it.
	 */
	public function test_a_labelled_button_drops_its_icon(): void {
		$html = Button::render( [ 'label' => 'Add', 'icon' => 'plus-alt' ] );

		$this->assertStringNotContainsString( 'dashicons', $html );
		$this->assertStringContainsString( '>Add<', $html );
	}

	/**
	 * An icon-only button keeps its icon, and it stays decorative.
	 *
	 * The glyph is not the name — the aria-label is — so announcing it too
	 * would read the button out twice.
	 */
	public function test_an_icon_only_button_hides_the_glyph_from_assistive_technology(): void {
		$html = Button::render( [ 'icon' => 'trash', 'aria_label' => 'Remove' ] );

		$this->assertStringContainsString( 'dashicons-trash', $html );
		$this->assertStringContainsString( 'aria-hidden="true"', $html );
		$this->assertStringContainsString( 'aria-label="Remove"', $html );
	}

	/**
	 * A button with an icon is marked, so one rule can align it.
	 *
	 * Core has no rule for an icon inside a button — a dashicon is a 20px
	 * inline box on a baseline inside a button whose line-height is 2.15, so
	 * it rides low. Only icon-only buttons reach this now, but the box still
	 * has to be centred inside the button.
	 */
	public function test_an_icon_button_is_marked_and_aligned(): void {
		$this->assertStringContainsString(
			'field-kit__button--icon',
			Button::render( [ 'icon' => 'plus-alt', 'aria_label' => 'Add' ] )
		);

		// And not when there is no icon to align.
		$this->assertStringNotContainsString(
			'field-kit__button--icon',
			Button::render( [ 'label' => 'Add' ] )
		);

		$css = (string) file_get_contents( dirname( __DIR__ ) . '/assets/css/field-kit.css' );

		$this->assertMatchesRegularExpression(
			'/\.field-kit__button--icon\s*\{[^}]*align-items:\s*center/',
			$css,
			'Nothing aligns the icon.'
		);
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
	 * A spinner is asked for, and is invisible until it is running.
	 *
	 * Core's own spinner is visibility:hidden with a fixed 20px box, because
	 * it sits outside a Save button where reserving the space stops the
	 * layout jumping. Inside a button that is dead air that never goes away —
	 * so the stylesheet hides it outright until `.is-active`, which is the
	 * class core switches it with either way.
	 */
	public function test_a_spinner_is_hidden_until_it_runs(): void {
		$html = Button::render( [ 'label' => 'Run', 'spinner' => true ] );

		$this->assertStringContainsString( 'class="spinner field-kit__button-spinner"', $html );
		$this->assertStringNotContainsString( 'is-active', $html );

		$css = (string) file_get_contents( dirname( __DIR__ ) . '/assets/css/field-kit.css' );

		$this->assertMatchesRegularExpression(
			'/\.field-kit__button-spinner:not\(\.is-active\)[^{]*\{[^}]*display:\s*none/',
			$css,
			'A button spinner takes up space when nothing is running.'
		);
	}

	/**
	 * No spinner unless one is asked for.
	 */
	public function test_no_spinner_by_default(): void {
		$this->assertStringNotContainsString( 'spinner', Button::render( [ 'label' => 'Run' ] ) );
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
