<?php
/**
 * Code generator layout tests.
 *
 * @package ArrayPress\FieldKit
 */

declare( strict_types=1 );

namespace ArrayPress\FieldKit\Tests;

use ArrayPress\FieldKit\Field;
use ArrayPress\FieldKit\Registry;
use ArrayPress\FieldKit\Renderer;
use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

/**
 * An eight-character code is not a page-wide control.
 *
 * The input carried `flex: 1` inside a flex row, so on a settings screen --
 * where the cell is as wide as the page -- it grew to fill it and left the
 * Generate button stranded at the far end. `regular-text` is core's own 25em,
 * and its own 100% below 782px, so the width was already decided.
 *
 * The wrapper's class was the worse half. It was `field-kit__code`, which is
 * also what the code *editor* puts on its textarea, so the flex rule written
 * for this shape was being applied to every code editor on the site.
 */
final class CodeGeneratorLayoutTest extends TestCase {

	/**
	 * Render a field.
	 *
	 * @param string $type Type id.
	 *
	 * @return string
	 */
	private function render( string $type ): string {
		$field = new Field(
			'code',
			( new Registry() )->get( $type ),
			[ 'label' => 'Code', 'input_name' => 'code' ],
			null
		);

		return ( new Renderer() )->render( $field );
	}

	/**
	 * The generator and the editor do not share a class.
	 */
	public function test_the_generator_and_the_editor_are_told_apart(): void {
		$generator = $this->render( 'code_generator' );
		$editor    = $this->render( 'code' );

		$this->assertStringContainsString( 'field-kit__code-generator', $generator );
		$this->assertStringContainsString( 'field-kit__code', $editor );

		// The editor's class is on the control itself, so the generator must
		// not also carry it on a wrapper.
		$this->assertDoesNotMatchRegularExpression(
			'/class="field-kit__code"/',
			$generator
		);
	}

	/**
	 * Nothing styles the class the editor puts on its textarea.
	 *
	 * It is a hook for the script and nothing else. A rule on it reaches
	 * every code editor, which is how `display: flex` ended up on one.
	 */
	public function test_the_editors_class_carries_no_styling(): void {
		$css = (string) preg_replace(
			'#/\*.*?\*/#s',
			'',
			(string) file_get_contents( dirname( __DIR__ ) . '/assets/css/field-kit.css' )
		);

		$this->assertDoesNotMatchRegularExpression(
			'/(^|[^-\w])\.field-kit__code(?![-\w])/m',
			$css,
			'A rule targets the class the code editor puts on its textarea.'
		);
	}

	/**
	 * The input takes core's width rather than the page's.
	 */
	public function test_the_input_does_not_grow_to_fill_the_row(): void {
		$css = (string) file_get_contents( dirname( __DIR__ ) . '/assets/css/field-kit.css' );

		preg_match( '/\.field-kit__code-value\s*\{([^}]*)\}/', $css, $match );

		$this->assertNotEmpty( $match );
		$this->assertDoesNotMatchRegularExpression(
			'/flex:\s*1\b/',
			$match[1],
			'The code input grows to fill whatever it is put in.'
		);

		// And it keeps core's own class, which is what decides the width.
		$this->assertStringContainsString( 'regular-text', $this->render( 'code_generator' ) );
	}

	/**
	 * No class is used both as a control class and as a wrapper class.
	 *
	 * The general form of the bug above: a name that means "this control" in
	 * one type and "the box around a control" in another cannot be styled
	 * without hitting both, and the collision is invisible from either file.
	 */
	public function test_no_class_is_both_a_control_and_a_wrapper(): void {
		$control = [];
		$wrapper = [];

		$files = new RecursiveIteratorIterator(
			new RecursiveDirectoryIterator( dirname( __DIR__ ) . '/src' )
		);

		foreach ( $files as $file ) {
			if ( ! $file->isFile() || 'php' !== $file->getExtension() ) {
				continue;
			}

			$src = (string) file_get_contents( $file->getPathname() );

			preg_match_all( '/\$attributes->add_class\(([^;]*?)\);/s', $src, $calls );

			foreach ( $calls[1] as $call ) {
				preg_match_all( "/'(field-kit__[a-z0-9_-]+)'/", $call, $names );
				$control = array_merge( $control, $names[1] );
			}

			preg_match_all( '/class="([^"]*field-kit__[^"]*)"/', $src, $markup );

			foreach ( $markup[1] as $list ) {
				foreach ( preg_split( '/\s+/', $list ) as $name ) {
					if ( str_starts_with( (string) $name, 'field-kit__' ) ) {
						$wrapper[] = $name;
					}
				}
			}
		}

		$both = array_values( array_intersect( array_unique( $control ), array_unique( $wrapper ) ) );

		$this->assertSame(
			[],
			$both,
			"These name a control in one place and a wrapper in another:\n  " . implode( "\n  ", $both )
		);
	}
}
