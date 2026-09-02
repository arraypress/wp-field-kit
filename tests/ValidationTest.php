<?php
/**
 * Validation tests.
 *
 * @package ArrayPress\FieldKit
 */

declare( strict_types=1 );

namespace ArrayPress\FieldKit\Tests;

use ArrayPress\FieldKit\Context\OptionContext;
use ArrayPress\FieldKit\Field;
use ArrayPress\FieldKit\FieldSet;
use ArrayPress\FieldKit\Registry;
use PHPUnit\Framework\TestCase;

/**
 * Sanitizing coerces and never refuses. That is right for a value nobody
 * typed and wrong for one somebody did: a silent correction is how a setting
 * comes to hold a value nobody chose. These pin down where the kit says no —
 * what it refuses, what it says, and, the part that actually matters, what
 * happens to the stored value and to every other field when it does.
 */
final class ValidationTest extends TestCase {

	/**
	 * Reset the stubbed stores between tests.
	 */
	protected function setUp(): void {
		$GLOBALS['fk_options']        = [];
		$GLOBALS['fk_doing_it_wrong'] = [];
	}

	/**
	 * A set over one option.
	 *
	 * @param array<string, array<string, mixed>> $configs Field configuration.
	 *
	 * @return array{0: FieldSet, 1: OptionContext}
	 */
	private function option_set( array $configs ): array {
		$context = new OptionContext( 'fk_test' );

		return [ new FieldSet( $configs, $context, 'fk_test' ), $context ];
	}

	/**
	 * A required field with nothing in it is refused, and says which field.
	 */
	public function test_a_required_field_rejects_an_empty_value(): void {
		[ $set ] = $this->option_set(
			[
				'name'  => [
					'type'     => 'text',
					'label'    => 'Name',
					'required' => true,
				],
				'other' => [
					'type'     => 'text',
					'required' => true,
				],
			]
		);

		$this->assertSame(
			[
				'name'  => 'Name is required.',
				'other' => 'This field is required.',
			],
			$set->validate( [ 'name' => '   ' ] )
		);
	}

	/**
	 * A required field with something in it passes — and zero is something.
	 *
	 * An unchecked checkbox stores 0 deliberately, and a required field that
	 * treated it as missing could never be saved off.
	 */
	public function test_a_required_field_accepts_a_value(): void {
		[ $set ] = $this->option_set(
			[
				'name'  => [
					'type'     => 'text',
					'required' => true,
				],
				'count' => [
					'type'     => 'number',
					'required' => true,
				],
			]
		);

		$this->assertSame( [], $set->validate( [ 'name' => 'Widget', 'count' => '0' ] ) );
	}

	/**
	 * Each named rule, with a value it takes and one it refuses.
	 *
	 * @return array<string, array{string, string, string}>
	 */
	public static function ruleProvider(): array {
		return [
			'email'        => [ 'email', 'dev@example.com', 'bad@' ],
			'url'          => [ 'url', 'https://example.com/path?x=1', 'not a url' ],
			'numeric'      => [ 'numeric', '3.5', 'three' ],
			'integer'      => [ 'integer', '-42', '4.2' ],
			'slug'         => [ 'slug', 'hello-world', 'Hello World' ],
			'alphanumeric' => [ 'alphanumeric', 'abc123', 'abc-123' ],
		];
	}

	/**
	 * A named rule accepts what it should and refuses what it should not.
	 *
	 * The message names the field and does not repeat what was typed: it is
	 * escaped when rendered, but a pasted paragraph coming back as the whole
	 * paragraph is not a message anybody reads.
	 *
	 * @param string $rule The rule name.
	 * @param string $good A value that passes.
	 * @param string $bad  A value that fails.
	 */
	#[\PHPUnit\Framework\Attributes\DataProvider( 'ruleProvider' )]
	public function test_a_named_rule_accepts_and_rejects( string $rule, string $good, string $bad ): void {
		[ $set ] = $this->option_set(
			[
				'x' => [
					'type'     => 'text',
					'label'    => 'Value',
					'validate' => $rule,
				],
			]
		);

		$this->assertSame( [], $set->validate( [ 'x' => $good ] ), sprintf( '%s refused %s.', $rule, $good ) );

		$errors = $set->validate( [ 'x' => $bad ] );

		$this->assertArrayHasKey( 'x', $errors, sprintf( '%s accepted %s.', $rule, $bad ) );
		$this->assertStringContainsString( 'Value', $errors['x'] );
		$this->assertStringNotContainsString( $bad, $errors['x'] );
	}

	/**
	 * The url rule wants a web address, not merely something with a colon.
	 *
	 * It is not `wp_http_validate_url()`, which guards the HTTP API against
	 * being pointed at the server's own network: a URL somebody types into a
	 * settings field may perfectly well be localhost.
	 */
	public function test_the_url_rule_wants_http_and_a_host(): void {
		[ $set ] = $this->option_set(
			[ 'x' => [ 'type' => 'text', 'validate' => 'url' ] ]
		);

		$this->assertArrayHasKey( 'x', $set->validate( [ 'x' => 'ftp://example.com/file' ] ) );
		$this->assertArrayHasKey( 'x', $set->validate( [ 'x' => 'https://' ] ) );
		$this->assertArrayHasKey( 'x', $set->validate( [ 'x' => 'javascript:alert(1)' ] ) );
		$this->assertSame( [], $set->validate( [ 'x' => 'http://localhost:8080/admin' ] ) );
	}

	/**
	 * A callable is given the sanitized value and the field, and true means fine.
	 */
	public function test_a_callable_returning_true_accepts(): void {
		$seen = null;

		[ $set ] = $this->option_set(
			[
				'x' => [
					'type'     => 'text',
					'validate' => static function ( $value, Field $field ) use ( &$seen ) {
						$seen = [ $value, $field->key() ];

						return true;
					},
				],
			]
		);

		$this->assertSame( [], $set->validate( [ 'x' => '  <b>hello</b> ' ] ) );

		// Sanitized before the callable sees it, not raw.
		$this->assertSame( [ 'hello', 'x' ], $seen );
	}

	/**
	 * A callable's string is the message.
	 */
	public function test_a_callable_returning_a_string_rejects_with_it(): void {
		[ $set ] = $this->option_set(
			[
				'x' => [
					'type'     => 'text',
					'validate' => static fn( $value ) => str_starts_with( $value, 'sk_' ) ? true : 'An API key starts with sk_.',
				],
			]
		);

		$this->assertSame( [ 'x' => 'An API key starts with sk_.' ], $set->validate( [ 'x' => 'pk_123' ] ) );
		$this->assertSame( [], $set->validate( [ 'x' => 'sk_123' ] ) );
	}

	/**
	 * A WP_Error is read for its message, the way the rest of WordPress does.
	 */
	public function test_a_callable_returning_a_wp_error_rejects_with_its_message(): void {
		[ $set ] = $this->option_set(
			[
				'x' => [
					'type'     => 'text',
					'validate' => static fn() => new \WP_Error( 'taken', 'That name is taken.' ),
				],
			]
		);

		$this->assertSame( [ 'x' => 'That name is taken.' ], $set->validate( [ 'x' => 'anything' ] ) );
	}

	/**
	 * False is a refusal with no reason, and still a refusal.
	 */
	public function test_a_callable_returning_false_rejects_with_the_generic_message(): void {
		[ $set ] = $this->option_set(
			[
				'x' => [
					'type'     => 'text',
					'label'    => 'Code',
					'validate' => static fn() => false,
				],
			]
		);

		$this->assertSame( [ 'x' => 'Code is not valid.' ], $set->validate( [ 'x' => 'anything' ] ) );
	}

	/**
	 * An optional field left blank is not checked against its rule.
	 *
	 * Whether it may be blank is what `required` decides. A rule that had to
	 * guard against blank first is a rule every consumer forgets to guard
	 * once.
	 */
	public function test_an_optional_blank_is_not_checked_against_its_rule(): void {
		$called = false;

		[ $set ] = $this->option_set(
			[
				'email' => [
					'type'     => 'text',
					'validate' => 'email',
				],
				'own'   => [
					'type'     => 'text',
					'validate' => static function () use ( &$called ) {
						$called = true;

						return 'no';
					},
				],
			]
		);

		$this->assertSame( [], $set->validate( [ 'email' => '', 'own' => '' ] ) );
		$this->assertFalse( $called );
	}

	/**
	 * On a tags field the rule is about each tag, and one message covers them.
	 */
	public function test_tags_are_checked_one_by_one(): void {
		[ $set ] = $this->option_set(
			[
				'notify' => [
					'type'     => 'tags',
					'label'    => 'Notify',
					'validate' => 'email',
				],
			]
		);

		$this->assertSame( [], $set->validate( [ 'notify' => 'a@example.com, b@example.com' ] ) );

		$this->assertSame(
			[ 'notify' => '2 of these are not email addresses: bad@, nope' ],
			$set->validate( [ 'notify' => 'a@example.com, bad@, nope' ] )
		);

		$this->assertSame(
			[ 'notify' => '1 of these is not an email address: nope' ],
			$set->validate( [ 'notify' => 'a@example.com, nope' ] )
		);
	}

	/**
	 * A per-item message names the first few offenders and counts the rest.
	 */
	public function test_a_per_item_message_names_only_the_first_few(): void {
		[ $set ] = $this->option_set(
			[ 'notify' => [ 'type' => 'tags', 'validate' => 'email' ] ]
		);

		$this->assertSame(
			[ 'notify' => '5 of these are not email addresses: a, b, c, …' ],
			$set->validate( [ 'notify' => 'a, b, c, d, e' ] )
		);
	}

	/**
	 * A callable applies per item too, with a message that counts.
	 */
	public function test_a_callable_applies_to_each_tag(): void {
		[ $set ] = $this->option_set(
			[
				'codes' => [
					'type'     => 'tags',
					'validate' => static fn( $value ) => strlen( $value ) === 3 ? true : 'Three letters.',
				],
			]
		);

		$this->assertSame(
			[ 'codes' => '1 of these is not valid: four' ],
			$set->validate( [ 'codes' => 'abc, four, def' ] )
		);
	}

	/**
	 * On a list field each entry is checked, read out of its row.
	 */
	public function test_list_entries_are_checked_one_by_one(): void {
		[ $set ] = $this->option_set(
			[
				'features' => [
					'type'     => 'list',
					'label'    => 'Features',
					'validate' => 'slug',
				],
			]
		);

		$rows = [
			[ 'item' => 'one' ],
			[ 'item' => 'Two Words' ],
			[ 'item' => 'three' ],
		];

		$this->assertSame(
			[ 'features' => '1 of these is not a slug: Two Words' ],
			$set->validate( [ 'features' => $rows ] )
		);

		$this->assertSame( [], $set->validate( [ 'features' => [ [ 'item' => 'one' ], [ 'item' => 'two' ] ] ] ) );
	}

	/**
	 * validate() reports and stores nothing at all.
	 */
	public function test_validate_reports_without_storing(): void {
		[ $set, $context ] = $this->option_set(
			[
				'name'    => [ 'type' => 'text' ],
				'contact' => [
					'type'     => 'text',
					'label'    => 'Contact',
					'validate' => 'email',
				],
			]
		);

		$errors = $set->validate( [ 'name' => 'Widget', 'contact' => 'bad@' ] );

		$this->assertSame( [ 'contact' => 'Contact must be an email address.' ], $errors );
		$this->assertSame( [], $context->values() );
		$this->assertSame( [], $GLOBALS['fk_options'] );
		$this->assertSame( [], $set->errors(), 'validate() is not a save and leaves errors() alone.' );
	}

	/**
	 * validate() skips exactly what save() skips.
	 *
	 * A field whose conditions are not met is hidden and would be cleared,
	 * not stored, so a `required` on it must not block the save. A locked or
	 * disabled control sends nothing and is left alone. A heading stores
	 * nothing and has nothing to check.
	 */
	public function test_validate_skips_what_save_skips(): void {
		[ $set ] = $this->option_set(
			[
				'intro'    => [
					'type'  => 'heading',
					'label' => 'Remote',
				],
				'mode'     => [
					'type'    => 'select',
					'options' => [
						'local'  => 'Local',
						'remote' => 'Remote',
					],
				],
				'endpoint' => [
					'type'      => 'text',
					'label'     => 'Endpoint',
					'required'  => true,
					'validate'  => 'url',
					'show_when' => [ 'mode' => 'remote' ],
				],
				'locked'   => [
					'type'     => 'text',
					'required' => true,
					'disabled' => true,
				],
			]
		);

		$this->assertSame( [], $set->validate( [ 'mode' => 'local', 'endpoint' => '' ] ) );

		$this->assertSame(
			[ 'endpoint' => 'Endpoint is required.' ],
			$set->validate( [ 'mode' => 'remote', 'endpoint' => '' ] )
		);

		$this->assertSame(
			[ 'endpoint' => 'Endpoint must be a web address starting with http:// or https://.' ],
			$set->validate( [ 'mode' => 'remote', 'endpoint' => 'nowhere' ] )
		);
	}

	/**
	 * A consumer's own sanitize_callback runs first, and the rule sees its result.
	 */
	public function test_a_sanitize_callback_runs_before_the_rule(): void {
		[ $set ] = $this->option_set(
			[
				'x' => [
					'type'              => 'text',
					'validate'          => 'integer',
					'sanitize_callback' => static fn( $raw ) => preg_replace( '/\D/', '', (string) $raw ),
				],
			]
		);

		$this->assertSame( [], $set->validate( [ 'x' => 'a1b2' ] ) );
	}

	/**
	 * save() leaves a failing field exactly as it was and stores the rest.
	 *
	 * The alternative — clearing it, or refusing the whole submission — is
	 * how a page of twenty settings gets thrown away over one bad address.
	 */
	public function test_save_leaves_a_failing_field_as_it_was(): void {
		$GLOBALS['fk_options']['fk_test'] = [
			'contact' => 'old@example.com',
			'name'    => 'Old',
		];

		[ $set, $context ] = $this->option_set(
			[
				'name'    => [ 'type' => 'text' ],
				'contact' => [
					'type'     => 'text',
					'label'    => 'Contact',
					'validate' => 'email',
				],
			]
		);

		$stored = $set->save( [ 'name' => 'New', 'contact' => 'bad@' ] );

		$this->assertSame( [ 'name' => 'New' ], $stored );
		$this->assertSame( 'old@example.com', $context->values()['contact'] );
		$this->assertSame( 'New', $context->values()['name'] );
		$this->assertSame( [ 'contact' => 'Contact must be an email address.' ], $set->errors() );

		// And a required field is not deleted for being blank, since it was
		// refused before the empty-means-delete rule could reach it.
		$GLOBALS['fk_options']['fk_test'] = [ 'name' => 'Old' ];

		[ $set, $context ] = $this->option_set(
			[
				'name' => [
					'type'     => 'text',
					'required' => true,
				],
			]
		);

		$set->save( [ 'name' => '' ] );

		$this->assertSame( 'Old', $context->values()['name'] );
	}

	/**
	 * errors() is about the last save, not every save so far.
	 */
	public function test_errors_are_reset_by_the_next_save(): void {
		[ $set ] = $this->option_set(
			[ 'contact' => [ 'type' => 'text', 'validate' => 'email' ] ]
		);

		$set->save( [ 'contact' => 'bad@' ] );
		$this->assertArrayHasKey( 'contact', $set->errors() );

		$set->save( [ 'contact' => 'good@example.com' ] );
		$this->assertSame( [], $set->errors() );
	}

	/**
	 * Rendering after a failed save marks the field without being handed the errors.
	 *
	 * A consumer that saves and redraws gets the highlighting for free. The
	 * message sits in an element the control points at, so it is announced
	 * with the control and not merely printed near it.
	 */
	public function test_render_marks_the_failing_field_after_a_save(): void {
		[ $set ] = $this->option_set(
			[
				'name'    => [ 'type' => 'text', 'label' => 'Name' ],
				'contact' => [
					'type'     => 'text',
					'label'    => 'Contact',
					'validate' => 'email',
				],
			]
		);

		$set->save( [ 'name' => 'Widget', 'contact' => 'bad@' ] );

		$html  = $set->render();
		$error = $set->field( 'contact' )->input_id() . '__error';

		$this->assertStringContainsString( 'aria-invalid="true"', $html );
		$this->assertStringContainsString( sprintf( 'aria-describedby="%s"', $error ), $html );
		$this->assertStringContainsString(
			sprintf( '<p class="field-kit__error" id="%s" role="alert">Contact must be an email address.</p>', $error ),
			$html
		);

		// Only the failing field is marked.
		$this->assertSame( 1, substr_count( $html, 'aria-invalid' ) );

		// The same for a consumer laying the field out itself.
		$this->assertStringContainsString( 'Contact must be an email address.', $set->render_field( $set->field( 'contact' ) ) );
		$this->assertStringNotContainsString( 'aria-invalid', $set->render_field( $set->field( 'name' ) ) );

		// Messages passed in still win over the remembered ones.
		$this->assertStringContainsString( 'Something else.', $set->render( 0, [ 'name' => 'Something else.' ] ) );
	}

	/**
	 * A message is text, whatever a callable put in it.
	 */
	public function test_a_message_is_escaped_when_rendered(): void {
		[ $set ] = $this->option_set(
			[
				'x' => [
					'type'     => 'text',
					'validate' => static fn() => '<script>alert(1)</script>',
				],
			]
		);

		$set->save( [ 'x' => 'anything' ] );

		$this->assertStringNotContainsString( '<script>', $set->render() );
		$this->assertStringContainsString( '&lt;script&gt;', $set->render() );
	}

	/**
	 * A rule that does not exist is reported, and does not refuse anything.
	 *
	 * A misspelt rule silently accepting everything would be worse than no
	 * rule; a misspelt rule blocking every save would be worse still.
	 */
	public function test_an_unknown_rule_is_reported_and_accepts(): void {
		[ $set ] = $this->option_set(
			[
				'x' => [ 'type' => 'text', 'validate' => 'emial' ],
				'y' => [ 'type' => 'text', 'validate' => [ 'email', 'slug' ] ],
			]
		);

		$this->assertSame( [], $set->validate( [ 'x' => 'anything', 'y' => 'anything' ] ) );

		$this->assertCount( 2, $GLOBALS['fk_doing_it_wrong'] );
		$this->assertStringContainsString( 'emial', $GLOBALS['fk_doing_it_wrong'][0] );
		$this->assertStringContainsString( 'array', $GLOBALS['fk_doing_it_wrong'][1] );
	}

	/**
	 * A type answers for itself, without a set around it.
	 */
	public function test_a_type_answers_for_itself(): void {
		$type  = ( new Registry() )->get( 'text' );
		$field = new Field( 'x', $type, [ 'label' => 'Count', 'validate' => 'numeric' ], null );

		$this->assertSame( '', $type->validate( '12', $field ) );
		$this->assertSame( '', $type->validate( '', $field ) );
		$this->assertSame( 'Count must be a number.', $type->validate( 'twelve', $field ) );
	}
}
