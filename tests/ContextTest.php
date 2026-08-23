<?php
/**
 * Context decorator tests.
 *
 * @package ArrayPress\FieldKit
 */

declare( strict_types=1 );

namespace ArrayPress\FieldKit\Tests;

use ArrayPress\FieldKit\Context\ConstantContext;
use ArrayPress\FieldKit\Context\EncryptedContext;
use ArrayPress\FieldKit\Context\OptionContext;
use ArrayPress\FieldKit\Field;
use ArrayPress\FieldKit\Registry;
use PHPUnit\Framework\TestCase;

/**
 * The decorators are where a credential either stays secret or does not, so
 * the cases that matter are the ones where something goes wrong.
 */
final class ContextTest extends TestCase {

	/**
	 * Reset the stubbed option store.
	 */
	protected function setUp(): void {
		$GLOBALS['fk_options'] = [];
	}

	/**
	 * Build a field.
	 *
	 * @param array<string, mixed> $config Field configuration.
	 *
	 * @return Field
	 */
	private function field( array $config = [] ): Field {
		$registry = new Registry();

		return new Field( 'api_key', $registry->get( 'text' ), $config, null );
	}

	/**
	 * An encrypted value round-trips.
	 */
	public function test_encrypted_value_round_trips(): void {
		if ( ! EncryptedContext::available() ) {
			$this->markTestSkipped( 'OpenSSL or the salts are unavailable.' );
		}

		$context = new EncryptedContext( new OptionContext( 'fk_test' ) );
		$field   = $this->field( [ 'encrypted' => true ] );

		$context->write( 0, $field, 'sk-secret-value' );

		$this->assertSame( 'sk-secret-value', $context->read( 0, $field ) );
	}

	/**
	 * What lands in storage is not the plaintext.
	 *
	 * The whole point: a database dump must not contain the credential.
	 */
	public function test_stored_value_is_not_the_plaintext(): void {
		if ( ! EncryptedContext::available() ) {
			$this->markTestSkipped( 'OpenSSL or the salts are unavailable.' );
		}

		$inner   = new OptionContext( 'fk_test' );
		$context = new EncryptedContext( $inner );
		$field   = $this->field( [ 'encrypted' => true ] );

		$context->write( 0, $field, 'sk-secret-value' );
		$inner->save();

		$stored = $GLOBALS['fk_options']['fk_test']['api_key'];

		$this->assertNotSame( 'sk-secret-value', $stored );
		$this->assertStringNotContainsString( 'sk-secret-value', $stored );
		$this->assertStringStartsWith( 'fkenc:', $stored );
	}

	/**
	 * A field that was not marked encrypted is untouched.
	 */
	public function test_unencrypted_fields_pass_through(): void {
		$context = new EncryptedContext( new OptionContext( 'fk_test' ) );
		$field   = $this->field();

		$context->write( 0, $field, 'plain' );

		$this->assertSame( 'plain', $context->read( 0, $field ) );
	}

	/**
	 * A value stored before the field was marked encrypted still reads.
	 *
	 * Turning encryption on for a field that already holds something must
	 * not make it unreadable.
	 */
	public function test_pre_existing_plaintext_still_reads(): void {
		$inner = new OptionContext( 'fk_test' );
		$field = $this->field( [ 'encrypted' => true ] );

		$GLOBALS['fk_options']['fk_test'] = [ 'api_key' => 'stored-before' ];

		$this->assertSame( 'stored-before', ( new EncryptedContext( $inner ) )->read( 0, $field ) );
	}

	/**
	 * A defined constant stands in for the stored value.
	 */
	public function test_constant_overrides_the_stored_value(): void {
		define( 'FK_TEST_API_KEY', 'from-config' );

		$context = new ConstantContext( new OptionContext( 'fk_test' ) );
		$field   = $this->field( [ 'constant' => 'FK_TEST_API_KEY' ] );

		$GLOBALS['fk_options']['fk_test'] = [ 'api_key' => 'from-database' ];

		$this->assertSame( 'from-config', $context->read( 0, $field ) );
		$this->assertTrue( $context->overrides( $field ) );
	}

	/**
	 * A write to an overridden field is dropped, not shadowed.
	 *
	 * Storing it anyway would leave a value that reappears the day the
	 * constant is removed, from a source nobody remembers.
	 */
	public function test_write_to_an_overridden_field_is_dropped(): void {
		$inner   = new OptionContext( 'fk_test' );
		$context = new ConstantContext( $inner );
		$field   = $this->field( [ 'constant' => 'FK_TEST_API_KEY' ] );

		$context->write( 0, $field, 'attempted' );
		$inner->save();

		$this->assertArrayNotHasKey( 'api_key', $inner->values() );
	}

	/**
	 * A field that did not opt in gets no derived constant.
	 *
	 * Deriving a name for every field would let an unrelated constant that
	 * happens to match a key silently take it over.
	 */
	public function test_no_constant_is_derived_without_opting_in(): void {
		define( 'FK_PREFIX_API_KEY', 'should-not-apply' );

		$context = new ConstantContext( new OptionContext( 'fk_test' ), 'fk_prefix_' );

		$this->assertFalse( $context->overrides( $this->field() ) );
		$this->assertTrue( $context->overrides( $this->field( [ 'use_constant' => true ] ) ) );
	}

	/**
	 * The decorators compose.
	 */
	public function test_decorators_compose(): void {
		if ( ! EncryptedContext::available() ) {
			$this->markTestSkipped( 'OpenSSL or the salts are unavailable.' );
		}

		$inner   = new OptionContext( 'fk_test' );
		$context = new ConstantContext( new EncryptedContext( $inner ) );
		$field   = $this->field( [ 'encrypted' => true ] );

		$context->write( 0, $field, 'layered' );

		$this->assertSame( 'layered', $context->read( 0, $field ) );
	}

}
