<?php
/**
 * Meta registration tests.
 *
 * @package ArrayPress\FieldKit
 */

declare( strict_types=1 );

namespace ArrayPress\FieldKit\Tests;

use ArrayPress\FieldKit\Context\CommentMetaContext;
use ArrayPress\FieldKit\Context\OptionContext;
use ArrayPress\FieldKit\Context\PostMetaContext;
use ArrayPress\FieldKit\Context\TermMetaContext;
use ArrayPress\FieldKit\Context\UserMetaContext;
use ArrayPress\FieldKit\Contracts\Context;
use ArrayPress\FieldKit\FieldSet;
use ArrayPress\FieldKit\MetaRegistrar;
use ArrayPress\FieldKit\Registry;
use PHPUnit\Framework\TestCase;

/**
 * Writing meta works without registration. What does not work without it is
 * everything that asks WordPress *about* the meta — REST, the block editor,
 * and the capability check that decides who may write a key.
 *
 * Two things here are security decisions rather than conveniences: nothing
 * reaches REST unless it asks, and an encrypted field cannot ask.
 */
final class MetaRegistrarTest extends TestCase {

	/**
	 * Reset the stubbed registry.
	 */
	protected function setUp(): void {
		$GLOBALS['fk_meta_registry'] = [];
		$GLOBALS['fk_doing_it_wrong'] = [];
	}

	/**
	 * What was registered for one key.
	 *
	 * @param string $key       Meta key.
	 * @param string $meta_type Meta type.
	 *
	 * @return array<string, mixed>|null
	 */
	private function registered( string $key, string $meta_type = 'term' ): ?array {
		return $GLOBALS['fk_meta_registry'][ $meta_type ][ $key ] ?? null;
	}

	/**
	 * Register a configuration.
	 *
	 * @param array<string, array<string, mixed>> $configs Field configuration.
	 * @param string                              $type    Meta type.
	 * @param string                              $subtype Subtype.
	 *
	 * @return string[]
	 */
	private function register( array $configs, string $type = 'term', string $subtype = '' ): array {
		return ( new MetaRegistrar( $this->context( $type ), $subtype, new Registry() ) )->register( $configs );
	}

	/**
	 * A store of the given kind.
	 *
	 * @param string $type Meta type.
	 *
	 * @return Context
	 */
	private function context( string $type ): Context {
		return match ( $type ) {
			'post'    => new PostMetaContext(),
			'user'    => new UserMetaContext(),
			'comment' => new CommentMetaContext(),
			default   => new TermMetaContext(),
		};
	}

	/**
	 * A field set registers itself, which is the entry point a library uses.
	 *
	 * The object type is not an argument: the context already knows what kind
	 * of store it is, being the thing that calls update_metadata() with that
	 * same string. Asserted through FieldSet rather than the registrar
	 * because that is the call a consuming library makes, and a signature
	 * change that compiles against the registrar and not against the caller
	 * is a fatal on a live page.
	 */
	public function test_a_field_set_registers_itself(): void {
		$set = new FieldSet(
			[ 'colour' => [ 'type' => 'text' ] ],
			new TermMetaContext(),
			''
		);

		$this->assertSame( [ 'colour' ], $set->register_meta( 'category' ) );
		$this->assertSame( 'category', $this->registered( 'colour' )['object_subtype'] );
	}

	/**
	 * An option-backed set registers nothing.
	 *
	 * A settings page declares itself once with register_setting(), which is a
	 * different call with a different shape — not something to approximate
	 * per field.
	 */
	public function test_an_option_backed_set_registers_nothing(): void {
		$set = new FieldSet(
			[ 'colour' => [ 'type' => 'text' ] ],
			new OptionContext( 'fk_test' ),
			'fk_test'
		);

		$this->assertSame( [], $set->register_meta() );
		$this->assertSame( [], $GLOBALS['fk_meta_registry'] );
	}

	/**
	 * A decorated store is still the store it decorates.
	 *
	 * Encryption and a constant override both wrap the context, and a term
	 * screen using either must still register.
	 */
	public function test_a_decorated_meta_store_still_registers(): void {
		$set = new FieldSet(
			[ 'colour' => [ 'type' => 'text' ] ],
			new \ArrayPress\FieldKit\Context\ConstantContext(
				new \ArrayPress\FieldKit\Context\EncryptedContext( new PostMetaContext() )
			),
			''
		);

		$this->assertSame( [ 'colour' ], $set->register_meta( 'product' ) );
		$this->assertSame( 'product', $this->registered( 'colour', 'post' )['object_subtype'] );
	}

	/**
	 * A field becomes a registered key.
	 */
	public function test_a_field_is_registered(): void {
		$keys = $this->register(
			[
				'colour' => [
					'type'        => 'text',
					'description' => 'The colour.',
				],
			]
		);

		$this->assertSame( [ 'colour' ], $keys );

		$args = $this->registered( 'colour' );

		$this->assertSame( 'string', $args['type'] );
		$this->assertSame( 'The colour.', $args['description'] );
		$this->assertTrue( $args['single'] );
	}

	/**
	 * A field that stores nothing is not a meta key.
	 *
	 * A heading, a clipboard and an action button all render and none of them
	 * hold a value. Registering them would put keys in the REST index that
	 * can never have one.
	 */
	public function test_a_field_that_stores_nothing_is_not_registered(): void {
		$keys = $this->register(
			[
				'intro'  => [ 'type' => 'heading' ],
				'copy'   => [ 'type' => 'clipboard' ],
				'run'    => [ 'type' => 'action_button' ],
				'colour' => [ 'type' => 'text' ],
			]
		);

		$this->assertSame( [ 'colour' ], $keys );
	}

	/**
	 * Nothing reaches REST unless the field asks.
	 *
	 * The predecessor library defaulted this to on, which publishes every
	 * custom field a plugin has ever registered to the REST API and the block
	 * editor — including the ones holding a licence key.
	 */
	public function test_rest_is_off_unless_asked_for(): void {
		$this->register(
			[
				'quiet' => [ 'type' => 'text' ],
				'loud'  => [
					'type'         => 'text',
					'show_in_rest' => true,
				],
			]
		);

		$this->assertFalse( $this->registered( 'quiet' )['show_in_rest'] );
		$this->assertIsArray( $this->registered( 'loud' )['show_in_rest'] );
		$this->assertSame( [ 'type' => 'string' ], $this->registered( 'loud' )['show_in_rest']['schema'] );
	}

	/**
	 * An encrypted field cannot be exposed, whatever the config says.
	 *
	 * What REST would hand a client is the ciphertext: useless to them, and a
	 * disclosure to anyone who should not have it.
	 */
	public function test_an_encrypted_field_is_refused_rest_exposure(): void {
		$this->register(
			[
				'api_key' => [
					'type'         => 'text',
					'encrypted'    => true,
					'show_in_rest' => true,
				],
			]
		);

		$this->assertFalse( $this->registered( 'api_key' )['show_in_rest'] );

		// And says so, rather than quietly doing something other than asked.
		$this->assertNotEmpty( $GLOBALS['fk_doing_it_wrong'] );
	}

	/**
	 * The sanitize callback runs the field's own type.
	 *
	 * This is the point of registering at all: a value written by
	 * update_post_meta() from a cron job, an importer or another plugin gets
	 * the same treatment as one typed into the form.
	 */
	public function test_the_sanitize_callback_is_the_fields_own(): void {
		$this->register(
			[
				'count' => [
					'type' => 'number',
					'min'  => 0,
					'max'  => 10,
				],
			]
		);

		$sanitize = $this->registered( 'count' )['sanitize_callback'];

		$this->assertSame( 5, $sanitize( '5' ) );

		// Clamped, exactly as a submission would be.
		$this->assertSame( 10, $sanitize( '99' ) );
		$this->assertSame( 0, $sanitize( 'not a number' ) );
	}

	/**
	 * A field naming a capability gets an auth callback; others do not.
	 *
	 * Without one WordPress applies its own mapping for the object type,
	 * which is usually right — replacing it with something weaker would be a
	 * quiet downgrade.
	 */
	public function test_a_capability_becomes_an_auth_callback(): void {
		$this->register(
			[
				'open'   => [ 'type' => 'text' ],
				'closed' => [
					'type'       => 'text',
					'capability' => 'manage_options',
				],
			]
		);

		$this->assertArrayNotHasKey( 'auth_callback', $this->registered( 'open' ) );
		$this->assertIsCallable( $this->registered( 'closed' )['auth_callback'] );
	}

	/**
	 * The subtype is passed on where there is one.
	 */
	public function test_the_subtype_is_registered(): void {
		$this->register( [ 'colour' => [ 'type' => 'text' ] ], 'post', 'product' );

		$this->assertSame( 'product', $this->registered( 'colour', 'post' )['object_subtype'] );

		// And is absent, not empty, when the object has none.
		$this->register( [ 'colour' => [ 'type' => 'text' ] ], 'user' );

		$this->assertArrayNotHasKey( 'object_subtype', $this->registered( 'colour', 'user' ) );
	}

	/**
	 * An amount's unit is a meta key too.
	 */
	public function test_a_companion_key_is_registered(): void {
		$keys = $this->register(
			[
				'discount' => [
					'type'          => 'amount_type',
					'type_meta_key' => 'discount_unit',
				],
			]
		);

		$this->assertSame( [ 'discount', 'discount_unit' ], $keys );
		$this->assertSame( 'string', $this->registered( 'discount_unit' )['type'] );
	}

	/**
	 * register_meta() takes one type, so a union schema is narrowed for it.
	 *
	 * An amount is a number or the empty string. Handing that array to
	 * register_meta() makes the registration fail; the full schema still goes
	 * to REST, which does understand a union.
	 */
	public function test_a_union_schema_is_narrowed_for_register_meta(): void {
		$this->register(
			[
				'discount' => [
					'type'         => 'amount_type',
					'show_in_rest' => true,
				],
			]
		);

		$args = $this->registered( 'discount' );

		$this->assertSame( 'number', $args['type'] );
		$this->assertSame( [ 'number', 'string' ], $args['show_in_rest']['schema']['type'] );
	}

	/**
	 * The schema describes what the type actually stores.
	 *
	 * A schema that disagrees with the stored shape is worse than none: REST
	 * rejects valid values against it, and only over REST, so it works in the
	 * admin and fails everywhere else.
	 *
	 * @dataProvider shapeProvider
	 *
	 * @param array<string, mixed> $config   Field configuration.
	 * @param mixed                $expected The expected schema type.
	 */
	#[\PHPUnit\Framework\Attributes\DataProvider( 'shapeProvider' )]
	public function test_the_schema_matches_the_stored_shape( array $config, mixed $expected ): void {
		$this->register( [ 'field' => array_merge( $config, [ 'show_in_rest' => true ] ) ] );

		$this->assertSame( $expected, $this->registered( 'field' )['show_in_rest']['schema']['type'] );
	}

	/**
	 * One field of each stored shape.
	 *
	 * @return array<string, array{0: array<string, mixed>, 1: mixed}>
	 */
	public static function shapeProvider(): array {
		return [
			'text'        => [ [ 'type' => 'text' ], 'string' ],
			'number'      => [ [ 'type' => 'number' ], 'integer' ],
			'decimal'     => [
				[
					'type' => 'number',
					'step' => 0.01,
				],
				'number',
			],
			'checkbox'    => [ [ 'type' => 'checkbox' ], 'integer' ],
			'tags'        => [ [ 'type' => 'tags' ], 'array' ],
			'gallery'     => [ [ 'type' => 'gallery' ], 'array' ],
			'group'       => [
				[
					'type'   => 'group',
					'fields' => [ 'street' => [ 'type' => 'text' ] ],
				],
				'object',
			],
			'repeater'    => [
				[
					'type'   => 'repeater',
					'fields' => [ 'name' => [ 'type' => 'text' ] ],
				],
				'array',
			],
			'link'        => [ [ 'type' => 'link' ], 'object' ],
			'post'        => [ [ 'type' => 'post' ], 'integer' ],
			'posts'       => [
				[
					'type'     => 'post',
					'multiple' => true,
				],
				'array',
			],
			'creatable'   => [
				[
					'type'      => 'post',
					'creatable' => true,
				],
				'string',
			],
		];
	}

	/**
	 * A group's schema is built from the fields it renders.
	 *
	 * Derived rather than declared, so the two cannot drift.
	 */
	public function test_a_groups_schema_describes_its_children(): void {
		$this->register(
			[
				'address' => [
					'type'         => 'group',
					'show_in_rest' => true,
					'fields'       => [
						'street' => [ 'type' => 'text' ],
						'number' => [ 'type' => 'number' ],
						'active' => [ 'type' => 'checkbox' ],
						'note'   => [ 'type' => 'heading' ],
					],
				],
			]
		);

		$properties = $this->registered( 'address' )['show_in_rest']['schema']['properties'];

		$this->assertSame( 'string', $properties['street']['type'] );
		$this->assertSame( 'integer', $properties['number']['type'] );
		$this->assertSame( 'integer', $properties['active']['type'] );

		// A heading inside a group holds no value either.
		$this->assertArrayNotHasKey( 'note', $properties );
	}

	/**
	 * A select's options become its allow-list.
	 */
	public function test_a_selects_options_become_an_enum(): void {
		$this->register(
			[
				'mode' => [
					'type'         => 'select',
					'show_in_rest' => true,
					'options'      => [
						'a' => 'A',
						'b' => 'B',
					],
				],
			]
		);

		$schema = $this->registered( 'mode' )['show_in_rest']['schema'];

		// The empty option is a real choice: it is how a non-required select
		// says nothing was picked.
		$this->assertSame( [ '', 'a', 'b' ], $schema['enum'] );
	}
}
