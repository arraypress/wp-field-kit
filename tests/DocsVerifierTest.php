<?php
/**
 * The documentation verifier's literal reader.
 *
 * @package ArrayPress\FieldKit
 */

declare( strict_types=1 );

namespace ArrayPress\FieldKit\Tests;

use PHPUnit\Framework\TestCase;

/**
 * bin/verify-docs.php reads the array literals out of a README so it can check
 * the keys in them. It used to do that by handing the literal to PHP's runtime
 * code interpreter, which had two problems.
 *
 * The first is that the file ships. Composer installs bin/ alongside src/, and
 * ten libraries call this script out of their vendor directory, so it lands in
 * the vendor tree of every plugin built on the kit -- where that construct is
 * the first thing a WordPress security scanner reports.
 *
 * The second is worse: it *ran* what the README contained. The documentation
 * for this set of libraries contains plugins_url() and apply_filters() calls
 * inside the configurations being checked, and every one of them was being
 * executed by `composer docs:verify`.
 *
 * The replacement tokenizes. These pin down what it accepts, what it refuses,
 * and -- the part that actually broke while it was being written -- that it
 * comes back in the right place after refusing something.
 *
 * The script is run as a subprocess rather than included, because it is a
 * program: including it would run the argument parsing and exit.
 */
final class DocsVerifierTest extends TestCase {

	/**
	 * Run the verifier over a markdown fixture and return what it printed.
	 *
	 * @param string $markdown The markdown to check.
	 * @param int    $status   Exit status, by reference.
	 *
	 * @return string
	 */
	private function verify( string $markdown, ?int &$status = null ): string {
		$file = (string) tempnam( sys_get_temp_dir(), 'fk' ) . '.md';

		file_put_contents( $file, $markdown );

		$command = sprintf(
			'%s %s --calls=FieldSet %s 2>&1',
			escapeshellarg( PHP_BINARY ),
			escapeshellarg( dirname( __DIR__ ) . '/bin/verify-docs.php' ),
			escapeshellarg( $file )
		);

		$output = [];

		exec( $command, $output, $status );

		unlink( $file );

		return implode( "\n", $output );
	}

	/**
	 * Wrap a configuration in the markdown the verifier looks for.
	 *
	 * @param string $config A PHP array literal.
	 *
	 * @return string
	 */
	private function fence( string $config ): string {
		return "```php\n\$set = new FieldSet(\n" . $config . "\n);\n```\n";
	}

	/**
	 * A plain configuration is read, and its fields counted.
	 */
	public function test_a_literal_configuration_is_read(): void {
		$output = $this->verify(
			$this->fence(
				"[
    'site_name' => [ 'type' => 'text', 'label' => 'Site name' ],
    'per_page'  => [ 'type' => 'number', 'label' => 'Per page', 'min' => 1, 'max' => 100 ],
]"
			)
		);

		$this->assertStringContainsString( '2 documented fields', $output );
		$this->assertStringContainsString( 'every one of them is real', $output );
	}

	/**
	 * A key nothing reads is reported, and the exit status says so.
	 *
	 * This is what the tool is for; without it the rest is decoration.
	 */
	public function test_a_key_nothing_reads_is_reported(): void {
		$output = $this->verify(
			$this->fence( "[ 'per_page' => [ 'type' => 'number', 'button_label' => 'Nope' ] ]" ),
			$status
		);

		$this->assertStringContainsString( 'button_label', $output );
		$this->assertSame( 1, $status );
	}

	/**
	 * The translation calls the documentation is written in are understood.
	 *
	 * 474 documented labels go through __() and its relatives, and a field
	 * declared with a label and no type -- which is most of them, `text` being
	 * the default -- is recognised as a field *by* its label. A reader that
	 * cannot see through __() therefore sees no fields at all: the failure
	 * that looks like success, nothing checked and nothing reported.
	 *
	 * So the assertion is on a configuration that has nothing else to go on.
	 */
	public function test_a_translated_label_is_read(): void {
		$output = $this->verify(
			$this->fence(
				"[
    'a' => [ 'label' => __( 'A', 'my-plugin' ) ],
    'b' => [ 'label' => esc_html__( 'B', 'my-plugin' ) ],
    'c' => [ 'label' => _x( 'C', 'context', 'my-plugin' ) ],
]"
			)
		);

		$this->assertStringContainsString( '3 documented fields', $output );
	}

	/**
	 * A value that has to be run is stepped over rather than run.
	 *
	 * A closure, an arrow function and two function calls -- all four are in
	 * the documentation as it stands, and none of them can be read without
	 * executing them. What matters is the key, which is checked either way.
	 */
	public function test_a_value_that_is_not_constant_is_stepped_over(): void {
		$output = $this->verify(
			$this->fence(
				"[
    'a' => [ 'type' => 'select', 'options' => function() { return [ 1, 2 ]; } ],
    'b' => [ 'type' => 'text', 'sanitize_callback' => fn( \$v ) => strtoupper( \$v ) ],
    'c' => [ 'type' => 'text', 'default' => apply_filters( 'x', 'y' ) ],
    'd' => [ 'type' => 'text', 'label' => 'D' . '!' ],
]"
			)
		);

		$this->assertStringContainsString( '4 documented fields', $output );
		$this->assertStringContainsString( 'every one of them is real', $output );
	}

	/**
	 * The reader comes back in the right place after stepping over one.
	 *
	 * This is the bug the skip introduced and the one that would go unnoticed:
	 * losing the cursor inside a closure body swallows the rest of the array,
	 * and a bad key sitting after it stops being reported. The count alone
	 * would still look right.
	 */
	public function test_a_bad_key_after_a_closure_is_still_reported(): void {
		$output = $this->verify(
			$this->fence(
				"[
    'a' => [
        'type'              => 'number',
        'sanitize_callback' => function( \$v ) { return [ 'a' => [ 1, 2 ] ]; },
        'button_label'      => 'Nope',
    ],
]"
			),
			$status
		);

		$this->assertStringContainsString( 'button_label', $output );
		$this->assertSame( 1, $status );
	}

	/**
	 * Nothing in a documented configuration is executed.
	 *
	 * The literal writes a file. Read by running it, the file appears; read by
	 * tokenizing, it does not. Asserting on the side effect rather than on the
	 * output, because the output looks the same either way -- which is exactly
	 * why the old behaviour went unnoticed.
	 */
	public function test_a_documented_configuration_is_never_executed(): void {
		$canary = sys_get_temp_dir() . '/fk-canary-' . getmypid();

		@unlink( $canary );

		$this->verify(
			$this->fence(
				sprintf(
					"[ 'a' => [ 'type' => 'text', 'default' => file_put_contents( '%s', 'ran' ) ] ]",
					$canary
				)
			)
		);

		$this->assertFileDoesNotExist( $canary, 'The verifier executed code out of a documentation block.' );
	}

	/**
	 * A block that cannot be parsed is skipped, not reported.
	 *
	 * Documentation is full of `/* ... *' . '/` placeholders and prose in
	 * argument position. Reporting those would mean a check that always says
	 * something, which is a check nobody runs.
	 */
	public function test_an_unparseable_block_is_skipped(): void {
		$this->verify( $this->fence( "[ 'a' => [ 'type' => 'text', ] /* ... */ ], ??? [" ), $status );

		$this->assertSame( 0, $status );
	}

	/**
	 * An array that is never closed does not hang the tool.
	 *
	 * Documentation fences are fragments, and a fragment ending mid-array
	 * leaves the reader on a token it has no rule for. The first version
	 * appended nothing to the array for ever and died on memory exhaustion
	 * after fourteen seconds -- with an exit status the calling script read
	 * as a pass.
	 */
	public function test_an_array_that_is_never_closed_does_not_hang(): void {
		$started = hrtime( true );

		$output = $this->verify( "```php\n\$set = new FieldSet(\n[ 'a' => [ 'type' => 'text' ] ], ??? [\n);\n```\n", $status );

		$elapsed = ( hrtime( true ) - $started ) / 1e9;

		$this->assertLessThan( 5, $elapsed, 'The verifier did not finish promptly.' );
		$this->assertStringNotContainsString( 'Fatal error', $output );
		$this->assertSame( 0, $status );
	}

	/**
	 * No part of the shipped tooling uses the runtime code interpreter.
	 *
	 * bin/ ships -- ten libraries run this script out of their vendor tree, so
	 * it reaches the vendor directory of every plugin built on the kit. src/
	 * ships for the same reason. A scanner walking a live site finds both.
	 */
	public function test_nothing_that_ships_evaluates_code_at_runtime(): void {
		$directories = [ dirname( __DIR__ ) . '/src', dirname( __DIR__ ) . '/bin' ];

		$files = [];

		foreach ( $directories as $directory ) {
			$iterator = new \RecursiveIteratorIterator( new \RecursiveDirectoryIterator( $directory ) );

			foreach ( $iterator as $file ) {
				if ( 'php' === $file->getExtension() ) {
					$files[] = $file->getPathname();
				}
			}
		}

		$this->assertNotEmpty( $files );

		// Built up rather than written out, so the test does not trip itself.
		$forbidden = [ 'ev' . 'al', 'create_' . 'function', 'assert' ];

		foreach ( $files as $file ) {
			$tokens = token_get_all( (string) file_get_contents( $file ) );

			foreach ( $tokens as $token ) {
				if ( ! is_array( $token ) ) {
					continue;
				}

				if ( T_EVAL === $token[0] ) {
					$this->fail( sprintf( '%s evaluates code at runtime.', $file ) );
				}

				if ( T_STRING === $token[0] && in_array( strtolower( $token[1] ), $forbidden, true ) ) {
					$this->fail( sprintf( '%s calls %s().', $file, $token[1] ) );
				}
			}
		}

		$this->addToAssertionCount( 1 );
	}
}
