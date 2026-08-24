<?php
/**
 * Stylesheet and markup must agree.
 *
 * @package ArrayPress\FieldKit
 */

declare( strict_types=1 );

namespace ArrayPress\FieldKit\Tests;

use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

/**
 * A rule targeting a class the markup never emits is dead code that looks
 * like styling. Nothing raises for it, and it survives every other test.
 *
 * This is not hypothetical. Switching radios and checkbox groups to wrapping
 * labels removed the per-option wrapper element, and the rules that had
 * spaced those options stayed behind pointing at a class nothing rendered any
 * more. The options ran together on one line with no separation between them,
 * and the whole suite still passed.
 */
final class StylesheetTest extends TestCase {

	/**
	 * Classes assembled at runtime rather than written out in full.
	 *
	 * Listed rather than inferred: parsing PHP concatenation from a regex is
	 * its own source of wrong answers, and this list is short and changes
	 * rarely. Each entry names where it is built.
	 *
	 * @var string[]
	 */
	private const BUILT_AT_RUNTIME = [
		// CheckboxGroupType writes this one directly.
		'field-kit__checkbox-group',

		// RepeaterType: 'field-kit__repeater-' . $action
		'field-kit__repeater-move-up',
		'field-kit__repeater-move-down',
		'field-kit__repeater-remove',

		// AbstractType / Renderer: 'field-kit__field--' . $type->id()
		'field-kit__field--conditional',

		// The wrapper carries field-kit__field--{type} for every registered
		// type, built from the id at render time. Only the ones the stylesheet
		// actually targets need listing.
		'field-kit__field--heading',
		'field-kit__field--locked',

		// Modifiers a consumer opts into.
		'field-kit__radio-group--inline',
		'field-kit__checkbox-group--inline',
	];

	/**
	 * Classes a consuming library emits, styled here because it ships no CSS.
	 *
	 * Separate from the list above on purpose. That one is checked back
	 * against this repository's own source; these cannot be, since what
	 * writes them is in another repository. Keeping them apart is what stops
	 * the check on the first list from having to make exceptions — and an
	 * exception is where a genuine mismatch hides.
	 *
	 * @var string[]
	 */
	private const EMITTED_BY_A_CONSUMER = [
		// wp-register-term-fields: a term screen's own row heading.
		'field-kit__row-label',

		// wp-register-setting-fields: a settings page's export, import and
		// reset controls, which live in the Screen Options panel.
		'field-kit__screen-tools',
		'field-kit__screen-tool',
		'field-kit__screen-tool-title',
		'field-kit__screen-tool-controls',
	];

	/**
	 * Class names the stylesheet targets.
	 *
	 * @return string[]
	 */
	private function stylesheet_classes(): array {
		$css = (string) file_get_contents( dirname( __DIR__ ) . '/assets/css/field-kit.css' );

		// Comments discuss class names in prose; they are not selectors.
		$css = (string) preg_replace( '#/\*.*?\*/#s', '', $css );

		preg_match_all( '/\.(field-kit__[a-z0-9_-]+)/', $css, $matches );

		return array_values( array_unique( $matches[1] ) );
	}

	/**
	 * Class names written out in the source or the script.
	 *
	 * @return string[]
	 */
	private function emitted_classes(): array {
		$found = [];

		foreach ( [ '/src', '/assets/js' ] as $directory ) {
			$iterator = new RecursiveIteratorIterator(
				new RecursiveDirectoryIterator( dirname( __DIR__ ) . $directory )
			);

			foreach ( $iterator as $file ) {
				if ( ! $file->isFile() || ! in_array( $file->getExtension(), [ 'php', 'js' ], true ) ) {
					continue;
				}

				preg_match_all(
					'/field-kit__[a-z0-9_-]+/',
					(string) file_get_contents( $file->getPathname() ),
					$matches
				);

				$found = array_merge( $found, $matches[0] );
			}
		}

		return array_values(
			array_unique( array_merge( $found, self::BUILT_AT_RUNTIME, self::EMITTED_BY_A_CONSUMER ) )
		);
	}

	/**
	 * Every styled class is one something actually renders.
	 */
	public function test_no_rule_targets_a_class_nothing_emits(): void {
		$dead = array_diff( $this->stylesheet_classes(), $this->emitted_classes() );

		$this->assertSame(
			[],
			array_values( $dead ),
			"These are styled but nothing renders them, so the rules do nothing:\n  "
			. implode( "\n  ", $dead )
		);
	}

	/**
	 * A class listed as built at runtime is really built somewhere.
	 *
	 * The list above is an exemption, and an exemption nobody checks becomes
	 * a place to hide a genuine mismatch.
	 */
	public function test_runtime_classes_have_a_stem_in_source(): void {
		$source = '';

		foreach ( [ '/src', '/assets/js' ] as $directory ) {
			$iterator = new RecursiveIteratorIterator(
				new RecursiveDirectoryIterator( dirname( __DIR__ ) . $directory )
			);

			foreach ( $iterator as $file ) {
				if ( $file->isFile() && in_array( $file->getExtension(), [ 'php', 'js' ], true ) ) {
					$source .= (string) file_get_contents( $file->getPathname() );
				}
			}
		}

		$orphans = [];

		foreach ( self::BUILT_AT_RUNTIME as $class ) {
			// Either written out in full, or some prefix of it appears — the
			// stem it is concatenated onto.
			if ( str_contains( $source, $class ) ) {
				continue;
			}

			$stem  = $class;
			$found = false;

			while ( false !== strrpos( $stem, '-' ) ) {
				$stem = substr( $stem, 0, strrpos( $stem, '-' ) + 1 );

				if ( str_contains( $source, "'" . $stem . "'" ) ) {
					$found = true;
					break;
				}

				$stem = rtrim( $stem, '-' );
			}

			// A modifier is opted into by a consumer's config rather than
			// written by the kit, so there is no stem to find.
			if ( ! $found && ! str_contains( $class, '--inline' ) ) {
				$orphans[] = $class;
			}
		}

		$this->assertSame(
			[],
			$orphans,
			'Exempted as built at runtime, but nothing builds them: ' . implode( ', ', $orphans )
		);
	}

}
