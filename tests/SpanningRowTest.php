<?php
/**
 * Spanning row tests.
 *
 * @package ArrayPress\FieldKit
 */

declare( strict_types=1 );

namespace ArrayPress\FieldKit\Tests;

use ArrayPress\FieldKit\Registry;
use PHPUnit\Framework\TestCase;

/**
 * A heading, a separator or a notice spans both columns of a form table.
 *
 * Core pads that cell `15px 10px` while padding the label cell
 * `20px 10px 20px 0` — so a label starts at the table's edge and a spanning
 * cell starts ten pixels in, and the divider ends up visibly indented
 * relative to everything it divides.
 *
 * Three libraries build that row and each had its own wrapper class, so the
 * rule keyed on none of them. They share one now.
 */
final class SpanningRowTest extends TestCase {

	/**
	 * The types that span a row, so a consuming library knows which they are.
	 *
	 * Asserted as a set rather than one at a time: adding a layout type and
	 * forgetting to mark it is how one of them ends up in a cell built for a
	 * control beside a label.
	 */
	public function test_exactly_these_types_span_a_row(): void {
		$registry = new Registry();
		$spanning = [];

		foreach ( $registry->ids() as $id ) {
			if ( $registry->get( $id )->spans_row() ) {
				$spanning[] = $id;
			}
		}

		sort( $spanning );

		$this->assertSame(
			[ 'email_editor', 'heading', 'html', 'message', 'separator' ],
			$spanning
		);
	}

	/**
	 * The stylesheet pulls that cell back to the table's edge.
	 */
	public function test_the_spanning_cell_is_flush_with_the_labels(): void {
		$css = (string) file_get_contents( dirname( __DIR__ ) . '/assets/css/field-kit.css' );

		preg_match(
			'/tr\.field-kit__spans-row\s*>\s*td\s*\{([^}]*)\}/',
			$css,
			$rule
		);

		$this->assertNotEmpty( $rule, 'Nothing pulls a spanning cell back.' );
		$this->assertStringContainsString( 'padding-left: 0', $rule[1] );
		$this->assertStringContainsString( 'padding-right: 0', $rule[1] );
	}
}
