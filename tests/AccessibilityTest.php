<?php
/**
 * Accessibility contract tests.
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
 * Accessibility is owned by the renderer rather than by each type, so it can
 * be asserted once for every registered type instead of hoped for fifty
 * times. These run across the whole registry: a new type inherits the
 * guarantees, and a type that breaks one fails here.
 */
final class AccessibilityTest extends TestCase {

	/**
	 * Build a field of the given type.
	 *
	 * @param string               $id     Type id.
	 * @param array<string, mixed> $config Extra config.
	 *
	 * @return Field
	 */
	private function field( string $id, array $config = [] ): Field {
		$registry = new Registry();

		return new Field(
			'demo_' . $id,
			$registry->get( $id ),
			array_merge(
				[
					'label'      => 'Demo label',
					'input_name' => 'demo_' . $id,
					'options'    => [ 'a' => 'Alpha', 'b' => 'Beta' ],
				],
				$config
			),
			null
		);
	}

	/**
	 * Every registered type id.
	 *
	 * @return array<int, array{string}>
	 */
	public static function typeProvider(): array {
		return array_map( static fn( $id ) => [ $id ], ( new Registry() )->ids() );
	}

	/**
	 * Every type produces a control the label can be associated with.
	 *
	 * @param string $id Type id.
	 */
	#[\PHPUnit\Framework\Attributes\DataProvider( 'typeProvider' )]
	public function test_every_type_is_labelled( string $id ): void {
		$field  = $this->field( $id );
		$markup = ( new Renderer() )->render( $field );
		$type   = $field->type();

		if ( ! $type->stores_value() ) {
			$this->addToAssertionCount( 1 );

			return;
		}

		if ( $type->is_grouped() ) {
			$this->assertStringContainsString( '<legend', $markup, "$id: a group needs a legend." );
			$this->assertStringContainsString( '<fieldset', $markup, "$id: a group needs a fieldset." );

			return;
		}

		$this->assertMatchesRegularExpression(
			'/<label[^>]*\bfor="' . preg_quote( $field->input_id(), '/' ) . '"/',
			$markup,
			"$id: no label points at the control."
		);
	}

	/**
	 * A description is announced with the control, not left orphaned.
	 *
	 * @param string $id Type id.
	 */
	#[\PHPUnit\Framework\Attributes\DataProvider( 'typeProvider' )]
	public function test_description_is_associated( string $id ): void {
		$field = $this->field( $id, [ 'description' => 'Helpful text.' ] );

		if ( ! $field->type()->stores_value() || $field->type()->is_grouped() ) {
			$this->addToAssertionCount( 1 );

			return;
		}

		$markup      = ( new Renderer() )->render( $field );
		$description = $field->input_id() . '__description';

		$this->assertStringContainsString(
			'id="' . $description . '"',
			$markup,
			"$id: the description has no id to reference."
		);
		$this->assertStringContainsString(
			'aria-describedby="' . $description . '"',
			$markup,
			"$id: the control does not reference its description."
		);
	}

	/**
	 * Required state is conveyed to assistive technology, not just visually.
	 *
	 * @param string $id Type id.
	 */
	#[\PHPUnit\Framework\Attributes\DataProvider( 'typeProvider' )]
	public function test_required_is_announced( string $id ): void {
		$field = $this->field( $id, [ 'required' => true ] );

		if ( ! $field->type()->stores_value() ) {
			$this->addToAssertionCount( 1 );

			return;
		}

		$markup = ( new Renderer() )->render( $field );

		$this->assertStringContainsString( 'aria-required="true"', $markup, "$id: required is not announced." );

		// The asterisk is decoration; the word is what gets read out.
		$this->assertStringContainsString( 'aria-hidden="true">*<', $markup, "$id: the asterisk is not hidden." );
		$this->assertStringContainsString( 'screen-reader-text', $markup, "$id: no textual required marker." );
	}

	/**
	 * An error is announced when it appears and marks the control invalid.
	 */
	public function test_error_is_announced_and_marks_the_control(): void {
		$markup = ( new Renderer() )->render( $this->field( 'text' ), 'That value is not valid.' );

		$this->assertStringContainsString( 'role="alert"', $markup );
		$this->assertStringContainsString( 'aria-invalid="true"', $markup );
		$this->assertStringContainsString( 'demo_text__error', $markup );
	}

	/**
	 * A self-labelling control is not given a second label above it.
	 */
	public function test_self_labelling_controls_are_not_double_labelled(): void {
		$markup = ( new Renderer() )->render( $this->field( 'checkbox' ) );

		$this->assertSame( 1, substr_count( $markup, '<label' ), 'The checkbox was labelled twice.' );
	}

	/**
	 * A toggle announces as a switch, not as a checkbox.
	 */
	public function test_toggle_announces_as_a_switch(): void {
		$markup = ( new Renderer() )->render( $this->field( 'toggle' ) );

		$this->assertStringContainsString( 'role="switch"', $markup );
		$this->assertStringContainsString( 'aria-checked="false"', $markup );
	}

	/**
	 * Each option in a group carries its own label.
	 */
	public function test_grouped_options_are_individually_labelled(): void {
		foreach ( [ 'radio', 'checkbox_group', 'button_group' ] as $id ) {
			$markup = ( new Renderer() )->render( $this->field( $id ) );

			$this->assertSame(
				2,
				substr_count( $markup, '<label for=' ),
				"$id: each option needs its own label."
			);
		}
	}

}
