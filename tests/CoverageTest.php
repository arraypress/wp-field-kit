<?php
/**
 * Coverage against the predecessor libraries.
 *
 * @package ArrayPress\FieldKit
 */

declare( strict_types=1 );

namespace ArrayPress\FieldKit\Tests;

use ArrayPress\FieldKit\Registry;
use PHPUnit\Framework\TestCase;

/**
 * The kit only replaces the five wp-register-*-fields libraries if it renders
 * everything they did. This reads their supported types out of their own
 * source and fails on anything the registry cannot resolve — including the
 * spellings that differ between libraries, which resolve through aliases.
 *
 * Skipped rather than failed when a library is not checked out, so the suite
 * still runs in CI where only this repository is present.
 */
final class CoverageTest extends TestCase {

	/**
	 * Where the sibling libraries live relative to this one.
	 */
	private const LIBRARIES = __DIR__ . '/../..';

	/**
	 * Read a library's supported types from its own source.
	 *
	 * @param string $relative Path under the libraries directory.
	 * @param string $pattern  Regex capturing one type per match.
	 *
	 * @return string[]
	 */
	private function types_from( string $relative, string $pattern ): array {
		$path = self::LIBRARIES . '/' . $relative;

		if ( ! file_exists( $path ) ) {
			$this->markTestSkipped( sprintf( '%s is not checked out.', $relative ) );
		}

		preg_match_all( $pattern, (string) file_get_contents( $path ), $matches );

		$types = [];

		foreach ( $matches[1] as $match ) {
			foreach ( preg_match( "/'/", $match ) ? explode( ',', $match ) : [ $match ] as $one ) {
				$one = trim( $one, " \t\n'" );

				if ( '' !== $one ) {
					$types[] = $one;
				}
			}
		}

		return array_values( array_unique( $types ) );
	}

	/**
	 * Assert the registry resolves every type in a list.
	 *
	 * @param string[] $types   Types the library renders.
	 * @param string   $library Library name for the message.
	 */
	private function assertAllResolve( array $types, string $library ): void {
		$registry = new Registry();
		$missing  = [];

		foreach ( $types as $type ) {
			if ( ! $registry->has( $type ) ) {
				$missing[] = $type;
			}
		}

		$this->assertSame(
			[],
			$missing,
			sprintf(
				"%s renders these types and the kit cannot resolve them: %s",
				$library,
				implode( ', ', $missing )
			)
		);
	}

	/**
	 * Every setting field type resolves.
	 */
	public function test_setting_fields_types_all_resolve(): void {
		$this->assertAllResolve(
			$this->types_from(
				'wp-register-setting-fields/src/Traits/FieldRenderer.php',
				"/\n\s+((?:'[a-z_0-9]+',?\s*)+)=>\s*\\\$this->render_/"
			),
			'wp-register-setting-fields'
		);
	}

	/**
	 * Every post field type resolves.
	 */
	public function test_post_fields_types_all_resolve(): void {
		$path = self::LIBRARIES . '/wp-register-post-fields/src/Traits/ConfigParser.php';

		if ( ! file_exists( $path ) ) {
			$this->markTestSkipped( 'wp-register-post-fields is not checked out.' );
		}

		preg_match( '/\$field_types\s*=\s*\[(.*?)\n\t\];/s', (string) file_get_contents( $path ), $block );

		preg_match_all( "/'([a-z_0-9]+)'\s*=>/", $block[1] ?? '', $matches );

		// The same array carries metabox config keys, which are not types.
		$types = array_diff(
			$matches[1],
			[ 'title', 'post_types', 'context', 'priority', 'prefix', 'capability', 'fields', 'full_width', 'min', 'max', 'step', 'sanitize_callback' ]
		);

		$this->assertNotEmpty( $types );
		$this->assertAllResolve( array_values( $types ), 'wp-register-post-fields' );
	}

	/**
	 * Every flyout form field type resolves.
	 */
	public function test_flyout_types_all_resolve(): void {
		$this->assertAllResolve(
			$this->types_from(
				'wp-register-flyouts/src/Components/FormField.php',
				"/^\s+'([a-z_0-9]+)'\s*=>\s*'render_/m"
			),
			'wp-register-flyouts'
		);
	}

	/**
	 * Every term and list-table field type resolves.
	 */
	public function test_term_and_list_table_types_all_resolve(): void {
		foreach (
			[
				'wp-register-term-fields/src/TermFields.php',
				'wp-register-quick-edit-fields/src/QuickEditFields.php',
				'wp-register-bulk-edit-fields/src/BulkEditFields.php',
			] as $relative
		) {
			$path = self::LIBRARIES . '/' . $relative;

			if ( ! file_exists( $path ) ) {
				continue;
			}

			preg_match_all( "/case '([a-z_0-9]+)':/", (string) file_get_contents( $path ), $matches );

			$types = array_diff( $matches[1], [ 'description', 'type', 'label', 'options' ] );

			$this->assertAllResolve( array_values( $types ), $relative );
		}
	}

	/**
	 * Every registered type is instantiable and reports the id it is keyed by.
	 */
	public function test_every_registered_type_resolves_to_its_own_id(): void {
		$registry = new Registry();

		foreach ( $registry->ids() as $id ) {
			$this->assertSame(
				$id,
				$registry->get( $id )->id(),
				sprintf( 'Registry key "%s" resolves to a type reporting a different id.', $id )
			);
		}
	}

	/**
	 * Every alias resolves to a real type.
	 */
	public function test_every_alias_resolves(): void {
		$registry = new Registry();

		foreach ( $registry->accepted_ids() as $id ) {
			$this->assertTrue( $registry->has( $id ), sprintf( '"%s" does not resolve.', $id ) );
		}
	}

}
