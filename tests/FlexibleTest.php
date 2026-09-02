<?php
/**
 * Flexible content field type.
 *
 * @package ArrayPress\FieldKit
 */

declare( strict_types=1 );

namespace ArrayPress\FieldKit\Tests;

use ArrayPress\FieldKit\Field;
use ArrayPress\FieldKit\Registry;
use ArrayPress\FieldKit\Renderer;
use ArrayPress\FieldKit\Types\FlexibleType;
use PHPUnit\Framework\TestCase;

/**
 * Covers FlexibleType.
 */
final class FlexibleTest extends TestCase {

	/**
	 * Two layouts of different shapes.
	 *
	 * @return array
	 */
	private function layouts(): array {
		return [
			'hero'    => [
				'label'  => 'Hero',
				'fields' => [
					'headline' => [ 'type' => 'text', 'label' => 'Headline' ],
					'image'    => [ 'type' => 'text', 'label' => 'Image' ],
				],
			],
			'columns' => [
				'label'  => 'Two columns',
				'fields' => [
					'left'  => [ 'type' => 'textarea', 'label' => 'Left' ],
					'right' => [ 'type' => 'textarea', 'label' => 'Right' ],
				],
			],
		];
	}

	/**
	 * Build a field the way FieldSet does.
	 *
	 * @param array $config Field configuration.
	 * @param mixed $value  Stored value.
	 *
	 * @return Field
	 */
	private function field( array $config = [], mixed $value = null ): Field {
		$resolved = ( new Registry() )->get( 'flexible' );

		return new Field(
			'blocks',
			$resolved,
			array_merge(
				$resolved->defaults(),
				[ 'label' => 'Blocks', 'input_name' => 'blocks', 'layouts' => $this->layouts() ],
				$config
			),
			$value
		);
	}

	/**
	 * Render the control.
	 *
	 * @param array $config Field configuration.
	 * @param mixed $value  Stored value.
	 *
	 * @return string
	 */
	private function render( array $config = [], mixed $value = null ): string {
		return ( new Renderer() )->render( $this->field( $config, $value ) );
	}

	/**
	 * Sanitise a submission.
	 *
	 * @param mixed $value  Submitted rows.
	 * @param array $config Field configuration.
	 *
	 * @return array
	 */
	private function sanitize( mixed $value, array $config = [] ): array {
		return ( new Registry() )->get( 'flexible' )->sanitize( $value, $this->field( $config ) );
	}

	/**
	 * One template per layout, so the script can clone the right shape.
	 *
	 * @return void
	 */
	public function test_a_template_per_layout(): void {
		$markup = $this->render();

		$this->assertSame( 2, substr_count( $markup, '<template' ) );
		$this->assertStringContainsString( 'data-layout="hero"', $markup );
		$this->assertStringContainsString( 'data-layout="columns"', $markup );
	}

	/**
	 * With few layouts, each gets its own button.
	 *
	 * @return void
	 */
	public function test_few_layouts_get_buttons(): void {
		$markup = $this->render();

		$this->assertStringContainsString( '>Add Hero<', $markup );
		$this->assertStringContainsString( '>Add Two columns<', $markup );
		$this->assertStringNotContainsString( 'field-kit__flexible-layout', $markup );
	}

	/**
	 * Past a handful, a row of buttons stops being scannable, so it is a select.
	 *
	 * @return void
	 */
	public function test_many_layouts_get_a_select(): void {
		$layouts = [];

		foreach ( range( 1, 6 ) as $n ) {
			$layouts[ 'layout' . $n ] = [ 'label' => 'Layout ' . $n, 'fields' => [] ];
		}

		$markup = $this->render( [ 'layouts' => $layouts ] );

		$this->assertStringContainsString( 'field-kit__flexible-layout', $markup );
		$this->assertStringContainsString( '<select', $markup );
	}

	/**
	 * A stored row renders the fields of the layout it chose, and no others.
	 *
	 * @return void
	 */
	public function test_a_row_renders_its_own_layout(): void {
		$markup = $this->render( [], [
			[ FlexibleType::LAYOUT_KEY => 'hero', 'headline' => 'Big news' ],
		] );

		$this->assertStringContainsString( 'name="blocks[0][headline]"', $markup );
		$this->assertStringContainsString( 'value="Big news"', $markup );
		$this->assertStringNotContainsString( 'name="blocks[0][left]"', $markup );
	}

	/**
	 * Rows of different layouts coexist, each with its own fields.
	 *
	 * @return void
	 */
	public function test_rows_of_different_layouts(): void {
		$markup = $this->render( [], [
			[ FlexibleType::LAYOUT_KEY => 'hero', 'headline' => 'One' ],
			[ FlexibleType::LAYOUT_KEY => 'columns', 'left' => 'Two' ],
		] );

		$this->assertStringContainsString( 'name="blocks[0][headline]"', $markup );
		$this->assertStringContainsString( 'name="blocks[1][left]"', $markup );
		$this->assertStringNotContainsString( 'name="blocks[1][headline]"', $markup );
	}

	/**
	 * Each row carries its layout in a hidden input, so a reorder keeps it.
	 *
	 * @return void
	 */
	public function test_the_layout_is_submitted_with_the_row(): void {
		$markup = $this->render( [], [ [ FlexibleType::LAYOUT_KEY => 'hero' ] ] );

		$this->assertStringContainsString( 'name="blocks[0][_layout]"', $markup );
		$this->assertStringContainsString( 'value="hero"', $markup );
	}

	/**
	 * Submitted rows are sanitised against the layout each one names.
	 *
	 * @return void
	 */
	public function test_sanitize_uses_the_rows_layout(): void {
		$clean = $this->sanitize( [
			[ FlexibleType::LAYOUT_KEY => 'hero', 'headline' => 'Big news', 'left' => 'ignored' ],
		] );

		$this->assertSame( 'hero', $clean[0][ FlexibleType::LAYOUT_KEY ] );
		$this->assertSame( 'Big news', $clean[0]['headline'] );
		$this->assertArrayNotHasKey( 'left', $clean[0], 'a field from another layout must not be kept' );
	}

	/**
	 * A row with no layout is not a row.
	 *
	 * @return void
	 */
	public function test_a_row_without_a_layout_is_dropped(): void {
		$this->assertSame( [], $this->sanitize( [ [ 'headline' => 'orphaned' ] ] ) );
	}

	/**
	 * An empty row of a known layout is dropped, as the repeater does.
	 *
	 * @return void
	 */
	public function test_an_empty_row_is_dropped(): void {
		$this->assertSame( [], $this->sanitize( [ [ FlexibleType::LAYOUT_KEY => 'hero', 'headline' => '' ] ] ) );
	}

	/**
	 * Row order is the submitted order.
	 *
	 * @return void
	 */
	public function test_order_is_preserved(): void {
		$clean = $this->sanitize( [
			[ FlexibleType::LAYOUT_KEY => 'columns', 'left' => 'first' ],
			[ FlexibleType::LAYOUT_KEY => 'hero', 'headline' => 'second' ],
		] );

		$this->assertSame( 'columns', $clean[0][ FlexibleType::LAYOUT_KEY ] );
		$this->assertSame( 'hero', $clean[1][ FlexibleType::LAYOUT_KEY ] );
	}

	/**
	 * A row whose layout has been removed keeps its content.
	 *
	 * Deleting a layout from the configuration must not silently delete what
	 * somebody wrote with it -- putting the layout back has to bring the
	 * content back too.
	 *
	 * @return void
	 */
	public function test_an_orphaned_row_survives_saving(): void {
		$clean = $this->sanitize( [
			[ FlexibleType::LAYOUT_KEY => 'retired', 'headline' => 'Written last year' ],
		] );

		$this->assertCount( 1, $clean );
		$this->assertSame( 'retired', $clean[0][ FlexibleType::LAYOUT_KEY ] );
		$this->assertSame( 'Written last year', $clean[0]['headline'] );
	}

	/**
	 * An orphaned row says so, and carries its values in hidden inputs.
	 *
	 * @return void
	 */
	public function test_an_orphaned_row_is_explained(): void {
		$markup = $this->render( [], [
			[ FlexibleType::LAYOUT_KEY => 'retired', 'headline' => 'Written last year' ],
		] );

		$this->assertStringContainsString( 'field-kit__flexible-orphan', $markup );
		$this->assertStringContainsString( 'no longer available', $markup );
		$this->assertStringContainsString( 'value="Written last year"', $markup );
	}

	/**
	 * A field with no layouts says so rather than rendering a dead control.
	 *
	 * @return void
	 */
	public function test_no_layouts_is_explained(): void {
		$markup = $this->render( [ 'layouts' => [] ] );

		$this->assertStringContainsString( 'no layouts configured', $markup );
		$this->assertStringNotContainsString( '<template', $markup );
	}

	/**
	 * A malformed layout list does not fatal.
	 *
	 * @return void
	 */
	public function test_malformed_layouts_are_ignored(): void {
		$markup = $this->render( [ 'layouts' => [ 'ok' => [ 'label' => 'Fine', 'fields' => [] ], 'bad' => 'nonsense', '' => [] ] ] );

		$this->assertStringContainsString( '>Add Fine<', $markup );
		$this->assertSame( 1, substr_count( $markup, '<template' ) );
	}

	/**
	 * Values are namespaced per row, so two rows do not share inputs.
	 *
	 * @return void
	 */
	public function test_rows_do_not_share_input_names(): void {
		$markup = $this->render( [], [
			[ FlexibleType::LAYOUT_KEY => 'hero', 'headline' => 'One' ],
			[ FlexibleType::LAYOUT_KEY => 'hero', 'headline' => 'Two' ],
		] );

		$this->assertStringContainsString( 'name="blocks[0][headline]"', $markup );
		$this->assertStringContainsString( 'name="blocks[1][headline]"', $markup );
	}

	/**
	 * The type is registered and reports its id.
	 *
	 * @return void
	 */
	public function test_registered(): void {
		$registry = new Registry();

		$this->assertContains( 'flexible', $registry->ids() );
		$this->assertSame( 'flexible', $registry->get( 'flexible' )->id() );
	}

	/**
	 * A row whose layout has gone keeps the slug it was matched by, not what was posted.
	 */
	public function test_an_orphan_row_keeps_the_matched_slug_and_clean_keys(): void {
		$clean = $this->sanitize(
			[
				[
					'_layout'      => 'Gone<b>',
					'headline'     => 'kept <b>as</b> text',
					'odd key here' => 'x',
				],
			]
		);

		$this->assertCount( 1, $clean );
		// sanitize_key() on what was posted, not the posted text itself.
		$this->assertSame( 'goneb', $clean[0]['_layout'] );
		$this->assertSame( 'kept as text', $clean[0]['headline'] );
		$this->assertArrayNotHasKey( 'odd key here', $clean[0] );
		$this->assertArrayHasKey( 'oddkeyhere', $clean[0] );
	}
}
