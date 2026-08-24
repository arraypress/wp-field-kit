<?php
/**
 * Meta Registrar
 *
 * @package     ArrayPress\FieldKit
 * @copyright   Copyright (c) 2026, ArrayPress Limited
 * @license     GPL2+
 * @since       1.0.0
 */

declare( strict_types=1 );

namespace ArrayPress\FieldKit;

use ArrayPress\FieldKit\Contracts\Context;
use ArrayPress\FieldKit\Contracts\Registrable;

/**
 * Declares a field set's meta keys to WordPress.
 *
 * Writing meta works without this. What does not work without it is
 * everything that asks WordPress *about* the meta: `get_registered_meta_keys()`,
 * the REST API, the block editor, and the capability check that decides who
 * may write a key. A field set that only writes is a field set the rest of
 * WordPress cannot see.
 *
 * Three of the four arguments are worth being deliberate about.
 *
 * `sanitize_callback` runs the field's own type. That makes the meta key
 * behave like the settings option already does: a value written by
 * `update_post_meta()` from a cron job, an importer or another plugin gets
 * the same treatment as one typed into the form. Without it the form is the
 * only sanitized way in, and every other way in is not.
 *
 * `auth_callback` decides who may write the key over REST. WordPress's own
 * default for a registered key is `edit_post_meta`-style mapping, which is
 * usually right; a field naming a `capability` overrides it.
 *
 * `show_in_rest` is **off** unless a field asks for it, and refused outright
 * for an encrypted field. The predecessor library defaulted it to on, which
 * publishes every custom field a plugin has ever registered to the REST API
 * and the block editor — including the ones holding a licence key. An
 * encrypted field cannot opt in at all: what REST would expose is the
 * ciphertext, which is useless to a client and a disclosure to anyone else.
 */
final class MetaRegistrar {

	/**
	 * The store whose keys are being declared.
	 *
	 * @var Context
	 */
	private Context $context;

	/**
	 * The subtype, where the object has one.
	 *
	 * @var string
	 */
	private string $subtype;

	/**
	 * The type registry.
	 *
	 * @var Registry
	 */
	private Registry $registry;

	/**
	 * Construct.
	 *
	 * The meta type comes from the store rather than from the caller. The
	 * context is already the thing calling `update_metadata()` with that
	 * string; being told it a second time is one more place for "term" to be
	 * written, and the one that drifts is never the one being read.
	 *
	 * @param Context       $context  The store whose keys are being declared.
	 * @param string        $subtype  Post type or taxonomy, where there is one.
	 * @param Registry|null $registry Type registry.
	 */
	public function __construct( Context $context, string $subtype = '', ?Registry $registry = null ) {
		$this->context  = $context;
		$this->subtype  = $subtype;
		$this->registry = $registry ?? new Registry();
	}

	/**
	 * Register every field in a configuration.
	 *
	 * @param array<string, array<string, mixed>> $configs Field configuration.
	 *
	 * @return string[] The keys that were registered.
	 */
	public function register( array $configs ): array {
		// An option is not meta. A settings page declares itself once with
		// register_setting(), which is a different call with a different
		// shape — so there is nothing to do here rather than something to
		// approximate.
		if ( '' === $this->meta_type() ) {
			return [];
		}

		$registered = [];

		foreach ( $configs as $key => $config ) {
			foreach ( $this->register_field( (string) $key, (array) $config ) as $one ) {
				$registered[] = $one;
			}
		}

		return $registered;
	}

	/**
	 * Register one field.
	 *
	 * @param string               $key    Field key, which is the meta key.
	 * @param array<string, mixed> $config Field configuration.
	 *
	 * @return string[] The keys registered for this field.
	 */
	private function register_field( string $key, array $config ): array {
		$type = (string) ( $config['type'] ?? 'text' );

		if ( ! $this->registry->has( $type ) ) {
			return [];
		}

		$resolved = $this->registry->get( $type );

		// A heading is not a meta key. Neither is a clipboard or an action
		// button: they render, they store nothing, and registering them would
		// put keys in the REST index that can never hold a value.
		if ( ! $resolved->stores_value() ) {
			return [];
		}

		$field  = new Field( $key, $resolved, array_merge( $resolved->defaults(), $config ), null );
		$schema = $resolved->schema( $field );

		$args = [
			'type'              => $this->scalar_type( $schema ),
			'description'       => $field->description(),
			'single'            => true,
			'sanitize_callback' => fn( $value ) => $resolved->sanitize( $value, $field ),
			'show_in_rest'      => $this->rest_argument( $field, $schema ),
		];

		if ( '' !== $this->subtype ) {
			$args['object_subtype'] = $this->subtype;
		}

		if ( null !== $field->get( 'default' ) ) {
			$args['default'] = $field->get( 'default' );
		}

		if ( $field->has( 'capability' ) ) {
			$capability = (string) $field->get( 'capability' );

			$args['auth_callback'] = static fn() => current_user_can( $capability );
		}

		register_meta( $this->meta_type(), $key, $args );

		$keys = [ $key ];

		// An amount writes its unit to a key of its own, which is just as
		// much a meta key as the amount is.
		$companion = (string) $field->get( 'type_meta_key', '' );

		if ( '' !== $companion ) {
			register_meta(
				$this->meta_type(),
				$companion,
				array_merge(
					$args,
					[
						'type'              => 'string',
						'description'       => '',
						'sanitize_callback' => 'sanitize_text_field',
					]
				)
			);

			$keys[] = $companion;
		}

		return $keys;
	}

	/**
	 * The scalar type register_meta() wants, from a JSON Schema fragment.
	 *
	 * `register_meta()` takes one type and rejects anything else. A schema is
	 * allowed to say a value may be a number *or* a string — an amount is
	 * either — and handing that array straight over makes the registration
	 * fail silently. The first named type wins, and the full schema still
	 * goes to REST, which does understand a union.
	 *
	 * @param array<string, mixed> $schema The field's schema.
	 *
	 * @return string
	 */
	private function scalar_type( array $schema ): string {
		$type = $schema['type'] ?? 'string';

		return is_array( $type ) ? (string) ( $type[0] ?? 'string' ) : (string) $type;
	}

	/**
	 * What to pass as `show_in_rest`.
	 *
	 * @param Field                $field  The field.
	 * @param array<string, mixed> $schema The field's schema.
	 *
	 * @return array<string, mixed>|bool
	 */
	private function rest_argument( Field $field, array $schema ): array|bool {
		if ( ! $field->get( 'show_in_rest', false ) ) {
			return false;
		}

		// An encrypted field cannot be exposed, whatever the config says.
		// What REST would hand a client is the ciphertext: useless to them,
		// and a disclosure to anyone who should not have it. Refused here
		// rather than left to the consumer to remember.
		if ( (bool) $field->get( 'encrypted', false ) ) {
			_doing_it_wrong(
				__METHOD__,
				sprintf(
					/* translators: %s: the field key */
					esc_html__( 'The field "%s" is encrypted, so it cannot be exposed in the REST API. show_in_rest was ignored.', 'arraypress' ),
					esc_html( $field->key() )
				),
				'1.0.0'
			);

			return false;
		}

		return [ 'schema' => $schema ];
	}

	/**
	 * The kind of meta the store holds, or nothing when it is not meta.
	 *
	 * @return string
	 */
	private function meta_type(): string {
		return $this->context instanceof Registrable ? $this->context->meta_type() : '';
	}
}
