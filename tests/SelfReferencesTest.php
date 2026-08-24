<?php
/**
 * Tests for the self-reference checker.
 *
 * @package ArrayPress\FieldKit
 */

declare( strict_types=1 );

namespace ArrayPress\FieldKit\Tests;

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/fixtures/composition/Fixtures.php';

/**
 * The checker ten libraries rely on, checked itself.
 *
 * It had no test of its own, which for a tool whose whole job is to notice
 * absence is an uncomfortable position: a checker that silently stopped
 * finding anything would look exactly like a codebase with nothing wrong.
 *
 * The fixtures are one file of deliberately misshapen traits — a sound one,
 * one calling a method no composing class has, and one nothing composes.
 */
final class SelfReferencesTest extends TestCase {

	/**
	 * What the checker says about the fixtures.
	 *
	 * @return string[]
	 */
	private function report(): array {
		return SelfReferences::broken( __DIR__ . '/fixtures/composition' );
	}

	/**
	 * A trait calling a sibling trait's method is not a problem.
	 *
	 * `self::punctuate()` does not resolve in the trait that writes it and
	 * resolves perfectly in the class that runs it. Reporting it would be the
	 * checker being wrong in the direction that gets it switched off.
	 */
	public function test_a_sibling_traits_method_is_not_reported(): void {
		foreach ( $this->report() as $line ) {
			$this->assertStringNotContainsString( 'punctuate', $line );
			$this->assertStringNotContainsString( 'shout', $line );
		}

		$this->assertTrue( true );
	}

	/**
	 * A trait calling a method no composing class has is reported.
	 *
	 * This is the failure mode of splitting a large class into traits: the
	 * code moves, the `use` line is forgotten, everything lints, and the page
	 * dies the moment the hook fires.
	 */
	public function test_a_method_no_host_has_is_reported(): void {
		$found = array_filter(
			$this->report(),
			static fn( string $line ): bool => str_contains( $line, 'never_defined_anywhere' )
		);

		$this->assertNotEmpty( $found, 'A call no composing class can satisfy went unreported.' );

		// And it says which class it looked at, because the trait file alone
		// does not tell you where to go and add the method.
		$this->assertStringContainsString( 'Host', (string) reset( $found ) );
	}

	/**
	 * A trait nothing composes is reported.
	 *
	 * Code that cannot run is not code that works, and nothing else here
	 * would have looked at it.
	 */
	public function test_a_trait_nothing_uses_is_reported(): void {
		$this->assertNotEmpty(
			array_filter(
				$this->report(),
				static fn( string $line ): bool => str_contains( $line, 'Orphan' )
			),
			'A trait no class uses went unreported.'
		);
	}

	/**
	 * Core's API is not reported as missing.
	 *
	 * A list table calls dozens of methods it inherits, and the stub carries
	 * only what the tests touch. Demanding every `$this->` resolve would
	 * report core as broken — which is the other direction that gets a
	 * checker switched off.
	 */
	public function test_a_name_the_library_does_not_declare_is_left_alone(): void {
		foreach ( $this->report() as $line ) {
			$this->assertStringNotContainsString( 'set_pagination_args', $line );
		}

		$this->assertTrue( true );
	}

	/**
	 * The report is exactly those two things and nothing else.
	 *
	 * Pinned as a whole, so a change that starts reporting something new has
	 * to be a decision rather than a surprise.
	 */
	public function test_nothing_else_is_reported(): void {
		$this->assertCount( 2, $this->report(), "Reported:\n  " . implode( "\n  ", $this->report() ) );
	}
}
