<?php
/**
 * Context decorator tests.
 *
 * @package ArrayPress\FieldKit
 */

declare( strict_types=1 );

namespace ArrayPress\FieldKit\Tests;

use ArrayPress\FieldKit\Context\ArrayContext;
use ArrayPress\FieldKit\Context\ConstantContext;
use ArrayPress\FieldKit\Context\EncryptedContext;
use ArrayPress\FieldKit\Context\OptionContext;
use ArrayPress\FieldKit\Contracts\Flushable;
use ArrayPress\FieldKit\Field;
use ArrayPress\FieldKit\FieldSet;
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


	/**
	 * Every type encrypts, not only the ones that store a string.
	 *
	 * write() only encrypted strings, so a group, a repeater, a set of
	 * checkboxes — anything marked encrypted whose value is an array — went
	 * into the database in the clear, with no error and nothing on the page
	 * to say so. The field said encrypted and the database said otherwise,
	 * which is the worst answer available.
	 *
	 * @dataProvider valueProvider
	 *
	 * @param mixed $value A value of some type.
	 */
	#[\PHPUnit\Framework\Attributes\DataProvider( 'valueProvider' )]
	public function test_every_value_type_round_trips_encrypted( mixed $value ): void {
		if ( ! EncryptedContext::available() ) {
			$this->markTestSkipped( 'OpenSSL or the salts are unavailable.' );
		}

		$inner   = new OptionContext( 'fk_test' );
		$context = new EncryptedContext( $inner );
		$field   = $this->field( [ 'encrypted' => true ] );

		$context->write( 0, $field, $value );
		$inner->save();

		$stored = $GLOBALS['fk_options']['fk_test']['api_key'];

		// In the store: a marked string, whatever the value was.
		$this->assertIsString( $stored );
		$this->assertStringStartsWith( 'fkenc:', $stored );

		// Nothing recognisable from the plaintext survives into it.
		$this->assertStringNotContainsString(
			'sk-live',
			(string) wp_json_encode( $GLOBALS['fk_options']['fk_test'] )
		);

		// And back out with its type intact.
		$this->assertSame( $value, $context->read( 0, $field ) );
	}

	/**
	 * One value of every shape a field can store.
	 *
	 * @return array<string, array{0: mixed}>
	 */
	public static function valueProvider(): array {
		return [
			'string'       => [ 'sk-live-DO-NOT-LEAK' ],
			'int'          => [ 42 ],
			'zero'         => [ 0 ],
			'float'        => [ 1.5 ],
			'true'         => [ true ],
			'false'        => [ false ],
			'list'         => [ [ 'sk-live-one', 'sk-live-two' ] ],
			'map'          => [
				[
					'user' => 'sk-live-user',
					'pass' => 'sk-live-pass',
				],
			],
			'nested'       => [ [ 'rows' => [ [ 'key' => 'sk-live-nested' ] ] ] ],
			'numeric text' => [ '0123' ],
		];
	}

	/**
	 * A value stored before types were encoded still reads.
	 *
	 * The old marker's payload is the plain string, and there is no way to
	 * tell it from the new one by looking — `123` under one is a string and
	 * under the other an int — so the marker is versioned and the old one is
	 * still read.
	 */
	public function test_a_value_written_by_the_old_format_still_reads(): void {
		if ( ! EncryptedContext::available() ) {
			$this->markTestSkipped( 'OpenSSL or the salts are unavailable.' );
		}

		$inner   = new OptionContext( 'fk_test' );
		$context = new EncryptedContext( $inner );
		$field   = $this->field( [ 'encrypted' => true ] );

		// Written the way the previous version wrote it: the raw string as
		// the payload, under the unversioned marker.
		$encrypt = ( new \ReflectionObject( $context ) )->getMethod( 'encrypt' );

		$GLOBALS['fk_options']['fk_test'] = [
			'api_key' => $encrypt->invoke( $context, 'fkenc:', 'sk-live-DO-NOT-LEAK' ),
		];

		$this->assertSame( 'sk-live-DO-NOT-LEAK', $context->read( 0, $field ) );
	}

	/**
	 * An empty value is not encrypted.
	 *
	 * There is nothing in it to protect, and encrypting it would make "no
	 * value" and "a value" tell each other apart by length.
	 */
	public function test_an_empty_value_is_stored_as_it_is(): void {
		$inner   = new OptionContext( 'fk_test' );
		$context = new EncryptedContext( $inner );
		$field   = $this->field( [ 'encrypted' => true ] );

		foreach ( [ '', [], null ] as $empty ) {
			$context->write( 0, $field, $empty );
			$inner->save();

			$this->assertSame( $empty, $GLOBALS['fk_options']['fk_test']['api_key'] );
		}
	}

	/**
	 * A value that is already ciphertext is not encrypted a second time.
	 *
	 * Anything that writes an option back wholesale — a reset, an import, a
	 * plain update_option() — hands back what it read. Encrypting that again
	 * leaves a value that decrypts to ciphertext, and the field then shows
	 * `fkenc:…` where the credential used to be.
	 */
	public function test_an_already_encrypted_value_is_not_encrypted_again(): void {
		if ( ! EncryptedContext::available() ) {
			$this->markTestSkipped( 'OpenSSL or the salts are unavailable.' );
		}

		$inner   = new OptionContext( 'fk_test' );
		$context = new EncryptedContext( $inner );
		$field   = $this->field( [ 'encrypted' => true ] );

		$context->write( 0, $field, 'sk-secret-value' );
		$inner->save();

		$ciphertext = $GLOBALS['fk_options']['fk_test']['api_key'];

		// What a reset or an import does: write back what was read.
		$context->write( 0, $field, $ciphertext );
		$inner->save();

		$this->assertSame( $ciphertext, $GLOBALS['fk_options']['fk_test']['api_key'] );
		$this->assertSame( 'sk-secret-value', $context->read( 0, $field ) );
	}

	/**
	 * A decorated batching store is still flushed.
	 *
	 * The field set holds the outermost decorator, so if a decorator did not
	 * pass save() on, an option-backed page would stage every value it was
	 * given and write none of them — with nothing to show for it, because
	 * staged values read back correctly for the rest of the request.
	 */
	public function test_a_decorated_option_context_is_flushed(): void {
		$options = new OptionContext( 'fk_test' );
		$context = new ConstantContext( new EncryptedContext( $options ) );

		$this->assertInstanceOf( Flushable::class, $context );

		$set = new FieldSet( [ 'api_key' => [ 'type' => 'text' ] ], $context, 'fk_test' );

		$set->save( [ 'api_key' => 'plain-value' ] );

		$this->assertSame( [ 'api_key' => 'plain-value' ], $GLOBALS['fk_options']['fk_test'] ?? [] );
	}

	/**
	 * Flushing a store with nothing staged is not an error.
	 */
	public function test_flushing_an_untouched_store_does_nothing(): void {
		$context = new ConstantContext( new EncryptedContext( new OptionContext( 'fk_test' ) ) );

		$context->save();

		$this->assertArrayNotHasKey( 'fk_test', $GLOBALS['fk_options'] );
	}

	/**
	 * A decorator over a store that does not batch has nothing to flush.
	 */
	public function test_flushing_a_non_batching_store_is_harmless(): void {
		$collector = new ArrayContext();
		$context   = new EncryptedContext( $collector );

		$context->write( 0, $this->field(), 'value' );
		$context->save();

		$this->assertSame( [ 'api_key' => 'value' ], $collector->values() );
	}

	/**
	 * An array context collects instead of storing.
	 */
	public function test_array_context_collects_without_storing(): void {
		$collector = new ArrayContext();
		$field     = $this->field();

		$collector->write( 0, $field, 'value' );

		$this->assertSame( 'value', $collector->read( 0, $field ) );
		$this->assertSame( [ 'api_key' => 'value' ], $collector->values() );
		$this->assertSame( [], $GLOBALS['fk_options'] );
	}

	/**
	 * It keeps the values it was seeded with.
	 *
	 * This is what lets one tab of a settings page be saved without the
	 * fields on the other tabs reading as cleared.
	 */
	public function test_array_context_keeps_what_it_was_seeded_with(): void {
		$collector = new ArrayContext( [ 'other_tab' => 'kept' ] );

		$collector->write( 0, $this->field(), 'value' );

		$this->assertSame(
			[
				'other_tab' => 'kept',
				'api_key'   => 'value',
			],
			$collector->values()
		);
	}

	/**
	 * Deleting removes the key rather than emptying it.
	 */
	public function test_array_context_delete_removes_the_key(): void {
		$collector = new ArrayContext( [ 'api_key' => 'value' ] );

		$collector->delete( 0, $this->field() );

		$this->assertSame( [], $collector->values() );
	}
}
