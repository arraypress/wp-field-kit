<?php
/**
 * Configuration vocabulary tests.
 *
 * @package ArrayPress\FieldKit
 */

declare( strict_types=1 );

namespace ArrayPress\FieldKit\Tests;

use ArrayPress\FieldKit\Field;
use ArrayPress\FieldKit\Registry;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

/**
 * A configuration key nothing reads is not an error in PHP. The entry sits in
 * the array, the control renders with its defaults, and the only symptom is
 * that a documented option quietly does nothing.
 *
 * That is not hypothetical: the examples shipped with wp-register-post-fields
 * used `button_label` on a repeater — whose add button is `add_label` — in
 * twenty-three places, `max_items` where the limit is `max_rows`, and gave a
 * multiple select `display => checkbox` six times, which is its own type.
 * Every one of those had been copy-pasted into real code and rendered without
 * complaint.
 *
 * So each type declares what it reads. The declaration is only worth
 * something if it stays true, which is what the first test here is for: it
 * reads each type's own source and compares.
 */
final class ConfigKeysTest extends TestCase {

	/**
	 * Every registered type id.
	 *
	 * @return array<int, array{string}>
	 */
	public static function typeProvider(): array {
		return array_map( static fn( $id ) => [ $id ], ( new Registry() )->ids() );
	}

	/**
	 * What a type declares is what its source actually reads.
	 *
	 * Read back out of the file rather than trusted, because a declaration
	 * that drifts from the code is worse than no declaration: it makes a real
	 * key look like a typo.
	 *
	 * @dataProvider typeProvider
	 *
	 * @param string $id Type id.
	 */
	#[\PHPUnit\Framework\Attributes\DataProvider( 'typeProvider' )]
	public function test_a_type_declares_every_key_it_reads( string $id ): void {
		$type      = ( new Registry() )->get( $id );
		$declared  = array_merge( Field::COMMON_KEYS, $type->config_keys() );
		$reflected = new ReflectionClass( $type );

		// The whole inheritance chain: an abstract's reads are the concrete
		// type's reads too, and its keys come through parent::config_keys().
		$read = [];

		for ( $class = $reflected; $class && $class->getFileName(); $class = $class->getParentClass() ) {
			$source = (string) file_get_contents( (string) $class->getFileName() );

			preg_match_all( '/\$field->(?:get|has)\(\s*\'([a-z_]+)\'/', $source, $matches );

			$read = array_merge( $read, $matches[1] );
		}

		$missing = array_values( array_diff( array_unique( $read ), $declared ) );

		$this->assertSame(
			[],
			$missing,
			sprintf(
				'%s reads configuration it does not declare: %s. Add it to config_keys(), '
				. 'or a consumer setting it will be told the key does nothing.',
				$id,
				implode( ', ', $missing )
			)
		);
	}

	/**
	 * A type does not claim keys it never reads.
	 *
	 * The other direction, and the one that lets a stale key survive a rename.
	 *
	 * @dataProvider typeProvider
	 *
	 * @param string $id Type id.
	 */
	#[\PHPUnit\Framework\Attributes\DataProvider( 'typeProvider' )]
	public function test_a_type_declares_nothing_it_does_not_read( string $id ): void {
		$type      = ( new Registry() )->get( $id );
		$declared  = $type->config_keys();
		$reflected = new ReflectionClass( $type );
		$read      = [];

		for ( $class = $reflected; $class && $class->getFileName(); $class = $class->getParentClass() ) {
			$source = (string) file_get_contents( (string) $class->getFileName() );

			preg_match_all( '/\$field->(?:get|has)\(\s*\'([a-z_]+)\'/', $source, $matches );

			$read = array_merge( $read, $matches[1] );
		}

		$phantom = array_values( array_diff( $declared, $read, Field::COMMON_KEYS ) );

		$this->assertSame(
			[],
			$phantom,
			sprintf( '%s declares configuration it never reads: %s.', $id, implode( ', ', $phantom ) )
		);
	}

	/**
	 * The three keys that started this.
	 */
	public function test_the_documented_mistakes_are_now_caught(): void {
		$registry = new Registry();

		$cases = [
			// [ type, bad key, the key that was meant ]
			[ 'repeater', 'button_label', 'add_label' ],
			[ 'repeater', 'max_items', 'max_rows' ],
			[ 'select', 'display', null ],
			[ 'repeater', 'row_title', null ],
		];

		foreach ( $cases as [ $type, $bad, $meant ] ) {
			$field = new Field( 'demo', $registry->get( $type ), [ 'label' => 'Demo', $bad => 'x' ], null );

			$this->assertSame(
				[ $bad ],
				$field->unknown_keys(),
				sprintf( '%s on a %s is not reported.', $bad, $type )
			);

			if ( null !== $meant ) {
				$right = new Field( 'demo', $registry->get( $type ), [ 'label' => 'Demo', $meant => 'x' ], null );

				$this->assertSame( [], $right->unknown_keys(), sprintf( '%s is wrongly reported.', $meant ) );
			}
		}
	}

	/**
	 * A field with nothing but common keys is clean.
	 */
	public function test_the_common_keys_are_accepted_by_every_type(): void {
		$registry = new Registry();

		foreach ( $registry->ids() as $id ) {
			$field = new Field(
				'demo',
				$registry->get( $id ),
				[
					'label'       => 'Demo',
					'description' => 'A demo field.',
					'default'     => '',
					'class'       => 'demo',
					'show_when'   => [ 'other' => 1 ],
					'badge'       => 'Pro',
				],
				null
			);

			$this->assertSame( [], $field->unknown_keys(), sprintf( '%s rejects a common key.', $id ) );
		}
	}
}
