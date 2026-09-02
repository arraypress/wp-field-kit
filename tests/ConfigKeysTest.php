<?php
/**
 * Configuration vocabulary tests.
 *
 * @package ArrayPress\FieldKit
 */

declare( strict_types=1 );

namespace ArrayPress\FieldKit\Tests;

use ArrayPress\FieldKit\Contracts\FieldType;
use ArrayPress\FieldKit\Field;
use ArrayPress\FieldKit\Registry;
use ArrayPress\FieldKit\Support\Presets;
use Closure;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use ReflectionFunction;
use ReflectionProperty;

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
		$type     = ( new Registry() )->get( $id );
		$declared = array_merge( Field::COMMON_KEYS, $type->config_keys() );
		$read     = self::keys_read_by( $type );

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
		$type     = ( new Registry() )->get( $id );
		$declared = $type->config_keys();
		$read     = self::keys_read_by( $type );

		$phantom = array_values( array_diff( $declared, $read, Field::COMMON_KEYS ) );

		$this->assertSame(
			[],
			$phantom,
			sprintf( '%s declares configuration it never reads: %s.', $id, implode( ', ', $phantom ) )
		);
	}

	/**
	 * Every configuration key a type's source reads off a field.
	 *
	 * The whole inheritance chain: an abstract's reads are the concrete
	 * type's reads too, and its keys come through parent::config_keys().
	 *
	 * And the preset its options default to. A country field says
	 * `'options' => 'countries'`, and it is the countries preset -- handed
	 * the field -- that reads `continents` off it. To anyone configuring the
	 * field that is the type's key: it is documented on the type, defaulted
	 * by the type, and declared by the type. So a read the preset makes on
	 * the type's behalf counts as the type's, or the declaration that keeps
	 * the key from being reported as a mistake would itself be reported as
	 * one.
	 *
	 * @param FieldType $type The type.
	 *
	 * @return string[]
	 */
	private static function keys_read_by( FieldType $type ): array {
		$read = [];

		for ( $class = new ReflectionClass( $type ); $class && $class->getFileName(); $class = $class->getParentClass() ) {
			$read = array_merge( $read, self::keys_read_in( (string) file_get_contents( (string) $class->getFileName() ) ) );
		}

		$preset = $type->defaults()['options'] ?? null;

		if ( ! is_string( $preset ) || ! Presets::has( $preset ) ) {
			return $read;
		}

		// The resolver's own lines, and no other preset's: the file holds
		// them all, and a key one of them reads is not a key they all read.
		$resolvers = new ReflectionProperty( Presets::class, 'resolvers' );
		$resolver  = new ReflectionFunction( Closure::fromCallable( $resolvers->getValue()[ $preset ] ) );
		$lines     = file( (string) $resolver->getFileName() ) ?: [];
		$body      = implode( '', array_slice( $lines, $resolver->getStartLine() - 1, $resolver->getEndLine() - $resolver->getStartLine() + 1 ) );

		return array_merge( $read, self::keys_read_in( $body ) );
	}

	/**
	 * The keys read off a field in a piece of source.
	 *
	 * @param string $source PHP source.
	 *
	 * @return string[]
	 */
	private static function keys_read_in( string $source ): array {
		preg_match_all( '/\$field->(?:get|has)\(\s*\'([a-z_]+)\'/', $source, $matches );

		return $matches[1];
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
