<?php
/**
 * Field Set
 *
 * @package     ArrayPress\FieldKit
 * @copyright   Copyright (c) 2026, ArrayPress Limited
 * @license     GPL2+
 * @since       1.0.0
 */

declare( strict_types=1 );

namespace ArrayPress\FieldKit;

use ArrayPress\FieldKit\Contracts\Context;
use ArrayPress\FieldKit\Contracts\Flushable;
use ArrayPress\FieldKit\Rest\ActionController;
use ArrayPress\FieldKit\Rest\SearchController;
use ArrayPress\FieldKit\Actions\Actions;
use ArrayPress\FieldKit\Actions\CallbackAction;
use ArrayPress\FieldKit\Search\CallbackSource;
use ArrayPress\FieldKit\Search\Sources;
use ArrayPress\FieldKit\Support\Badge;
use ArrayPress\FieldKit\Support\Rules;
use ArrayPress\FieldKit\Support\Sections;

/**
 * A group of fields bound to one storage context.
 *
 * This is what a consuming library builds: a set of field configs, a context
 * to read and write them through, and the two calls that render and save.
 * Everything the five predecessor libraries duplicated lives beneath it.
 */
final class FieldSet {

	/**
	 * Field configuration, keyed by field key.
	 *
	 * @var array<string, array<string, mixed>>
	 */
	private array $configs;

	/**
	 * Where values are read from and written to.
	 *
	 * @var Context
	 */
	private Context $context;

	/**
	 * The type registry.
	 *
	 * @var Registry
	 */
	private Registry $registry;

	/**
	 * The renderer.
	 *
	 * @var Renderer
	 */
	private Renderer $renderer;

	/**
	 * Prefix applied to every input name.
	 *
	 * @var string
	 */
	private string $input_prefix;

	/**
	 * The messages from the last save(), keyed by field.
	 *
	 * Kept on the set rather than returned, because save() already returns
	 * what it stored and its callers rely on that shape. The next render()
	 * in the same request reads these for itself.
	 *
	 * @var array<string, string>
	 */
	private array $errors = [];

	/**
	 * Construct.
	 *
	 * @param array<string, array<string, mixed>> $configs      Field configuration.
	 * @param Context                             $context      Storage context.
	 * @param string                              $input_prefix Prefix for input names.
	 * @param Registry|null                       $registry     Type registry.
	 */
	public function __construct(
		array $configs,
		Context $context,
		string $input_prefix = '',
		?Registry $registry = null
	) {
		$this->configs      = $configs;
		$this->context      = $context;
		$this->input_prefix = $input_prefix;
		$this->registry     = $registry ?? new Registry();
		$this->renderer     = new Renderer();

		$this->register_search_sources();
		$this->register_actions();

		self::boot_endpoints();
	}

	/**
	 * Register the REST routes, once per request.
	 *
	 * Booted from here rather than left to the consumer: a field emits an
	 * endpoint URL whether or not anyone remembered to register the route,
	 * and a button posting to a 404 looks exactly like a button that does
	 * nothing. Both controllers refuse to register the same namespace twice,
	 * so several field sets on one screen is not a problem.
	 *
	 * @return void
	 */
	private static function boot_endpoints(): void {
		static $booted = false;

		if ( $booted ) {
			return;
		}

		$booted = true;

		( new SearchController() )->boot();
		( new ActionController() )->boot();
	}

	/**
	 * Register a search source for every field that supplies a callback.
	 *
	 * Done at construction rather than at render time: the endpoint resolves
	 * the source on a later request, by which point nothing has rendered.
	 * The source is named after the field, so the name in the page is
	 * meaningless to anyone who has not registered it.
	 *
	 * @return void
	 */
	private function register_search_sources(): void {
		$sources = Sources::shared();

		foreach ( $this->configs as $key => $config ) {
			$callback = $config['search_callback'] ?? null;

			if ( ! is_callable( $callback ) ) {
				continue;
			}

			$sources->register(
				new CallbackSource(
					$this->source_name_for( (string) $key, $config ),
					$callback,
					(string) ( $config['search_capability'] ?? 'edit_posts' ),
					array_keys( (array) ( $config['search_args'] ?? [] ) )
				)
			);
		}
	}

	/**
	 * Register an action for every field that supplies a handler.
	 *
	 * Same reasoning as the search sources: the endpoint resolves the handler
	 * on a later request, so registration cannot wait for a render, and the
	 * name is meaningless to anyone who has not registered it.
	 *
	 * A field may name several — a licence has activate and deactivate, an
	 * email has preview and test — so handlers are read from an `actions`
	 * map as well as from a single `action_callback`.
	 *
	 * @return void
	 */
	private function register_actions(): void {
		$actions = Actions::shared();

		foreach ( $this->configs as $key => $config ) {
			$handlers = (array) ( $config['actions'] ?? [] );

			if ( isset( $config['action_callback'] ) ) {
				$handlers['run'] = $config['action_callback'];
			}

			$named = (array) ( $config['action_names'] ?? [] );

			foreach ( $handlers as $name => $callback ) {
				if ( ! is_callable( $callback ) ) {
					continue;
				}

				$actions->register(
					new CallbackAction(
						(string) ( $named[ $name ] ?? $this->action_name( (string) $key, (string) $name ) ),
						$callback,
						(string) ( $config['action_capability'] ?? 'manage_options' )
					)
				);
			}
		}
	}

	/**
	 * The action name a field's button uses.
	 *
	 * @param string $key  Field key.
	 * @param string $name Action name within the field.
	 *
	 * @return string
	 */
	public function action_name( string $key, string $name ): string {
		return sanitize_key(
			( '' === $this->input_prefix ? '' : $this->input_prefix . '_' ) . $key . '_' . $name
		);
	}

	/**
	 * The source name a callback-backed field uses.
	 *
	 * @param string $key Field key.
	 *
	 * @return string
	 */
	public function source_name( string $key ): string {
		return sanitize_key( ( '' === $this->input_prefix ? '' : $this->input_prefix . '_' ) . $key );
	}

	/**
	 * The name this field's source is actually registered under.
	 *
	 * A consumer may name it itself, and one has to: a field set built with
	 * no input prefix derives the name from the field key alone, so two
	 * flyouts each with a `customer` field would name the same source and the
	 * second registration would answer the first's searches. A library that
	 * knows what distinguishes its field sets — which flyout, which metabox —
	 * supplies the name, and the same name is what the field emits.
	 *
	 * @param string               $key    Field key.
	 * @param array<string, mixed> $config Field configuration.
	 *
	 * @return string
	 */
	private function source_name_for( string $key, array $config ): string {
		$named = (string) ( $config['search_source'] ?? '' );

		return '' === $named ? $this->source_name( $key ) : $named;
	}

	/**
	 * Build one field, with its stored value loaded.
	 *
	 * @param string     $key       Field key.
	 * @param int|string $object_id Object the values belong to.
	 *
	 * @return Field|null
	 */
	public function field( string $key, int|string $object_id = 0 ): ?Field {
		$config = $this->configs[ $key ] ?? null;

		if ( null === $config ) {
			return null;
		}

		$type = (string) ( $config['type'] ?? 'text' );

		if ( ! $this->registry->has( $type ) ) {
			return null;
		}

		$resolved = $this->registry->get( $type );

		$config = array_merge(
			$resolved->defaults(),
			$config,
			[ 'input_name' => '' === $this->input_prefix ? $key : $this->input_prefix . '[' . $key . ']' ]
		);

		// A callback-backed field points at the source registered for it.
		if ( isset( $config['search_callback'] ) ) {
			$config['search_source'] = $this->source_name_for( $key, $config );
		}

		// Likewise for its buttons: the field emits the registered names, so
		// a button in the page corresponds to a handler that exists.
		//
		// A consumer may have named them itself, for the same reason it may
		// name a search source — a set built with no input prefix derives the
		// name from the field key alone, and two panels each with an
		// `activate` button would name the same handler.
		if ( ! isset( $config['action_names'] ) || ! is_array( $config['action_names'] ) ) {
			$config['action_names'] = $this->action_names_for( $key, $config );
		}

		$field = new Field( $key, $resolved, $config, null );

		return $resolved->stores_value()
			? $field->with_value( $this->context->read( $object_id, $field ) )
			: $field;
	}

	/**
	 * The registered action names for one field, keyed by their local name.
	 *
	 * @param string               $key    Field key.
	 * @param array<string, mixed> $config Field configuration.
	 *
	 * @return array<string, string>
	 */
	private function action_names_for( string $key, array $config ): array {
		$names    = [];
		$handlers = (array) ( $config['actions'] ?? [] );

		if ( isset( $config['action_callback'] ) ) {
			$handlers['run'] = $config['action_callback'];
		}

		foreach ( array_keys( $handlers ) as $name ) {
			$names[ (string) $name ] = $this->action_name( $key, (string) $name );
		}

		return $names;
	}

	/**
	 * Every field, in configuration order.
	 *
	 * @param int|string $object_id Object the values belong to.
	 *
	 * @return Field[]
	 */
	public function fields( int|string $object_id = 0 ): array {
		$fields = [];

		foreach ( array_keys( $this->configs ) as $key ) {
			$field = $this->field( (string) $key, $object_id );

			if ( null !== $field ) {
				$fields[] = $field;
			}
		}

		return $fields;
	}

	/**
	 * Render every field.
	 *
	 * With no messages passed, the ones from a save() earlier in the same
	 * request are used. A consumer that saves and then redraws the screen
	 * gets each failing field marked and explained without carrying the
	 * messages from one call to the other — a step every one of them would
	 * otherwise have to remember.
	 *
	 * @param int|string            $object_id Object the values belong to.
	 * @param array<string, string> $errors    Validation messages keyed by field.
	 *
	 * @return string
	 */
	public function render( int|string $object_id = 0, array $errors = [] ): string {
		if ( [] === $errors ) {
			$errors = $this->errors;
		}

		$fields = $this->fields( $object_id );
		$layout = Sections::split( $fields );

		$render = function ( array $group ) use ( $errors ): string {
			$markup = '';

			foreach ( $group as $field ) {
				// A lone marker is not a layout, and it draws nothing, so it
				// is dropped rather than left to emit an empty row.
				if ( Sections::is_marker( $field ) ) {
					continue;
				}

				$markup .= $this->renderer->render( $field, $errors[ $field->key() ] ?? '' );
			}

			return $markup;
		};

		if ( [] === $layout ) {
			return $render( $fields );
		}

		return Sections::render( $layout, $render, $this->input_prefix );
	}

	/**
	 * Render one field that has already been built.
	 *
	 * Consumers that lay fields out themselves — a term screen's table rows,
	 * a metabox's own grid — need the control without the set's own loop.
	 *
	 * As with render(), a message from a save() in the same request is used
	 * when none is passed, so a consumer with its own layout is not the one
	 * that has to remember.
	 *
	 * @param Field  $field      The field.
	 * @param string $error      Optional validation message.
	 * @param bool   $with_label Whether to emit the label.
	 *
	 * @return string
	 */
	public function render_field( Field $field, string $error = '', bool $with_label = true ): string {
		if ( '' === $error ) {
			$error = $this->errors[ $field->key() ] ?? '';
		}

		return $this->renderer->render( $field, $error, $with_label );
	}

	/**
	 * Check a submission without storing any of it.
	 *
	 * The same walk as save() — unslashed once, conditions applied, each
	 * value sanitized by its type or by the field's own `sanitize_callback`,
	 * the same fields skipped — so what this reports is exactly what save()
	 * would refuse. A consumer that would rather reject a whole submission
	 * than store the parts of it that passed asks this first.
	 *
	 * @param array<string, mixed> $input     Raw submitted values, still slashed.
	 * @param int|string           $object_id Object the values belong to.
	 *
	 * @return array<string, string> A message per failing field, keyed by
	 *                               field. Empty when everything passed.
	 * @since 1.2.0
	 */
	public function validate( array $input, int|string $object_id = 0 ): array {
		$errors = [];

		foreach ( $this->sanitized( $input, $object_id ) as [ $field, $met, $value ] ) {
			if ( ! $met ) {
				continue;
			}

			$message = $this->check( $field, $value );

			if ( '' !== $message ) {
				$errors[ $field->key() ] = $message;
			}
		}

		return $errors;
	}

	/**
	 * The messages from the last save().
	 *
	 * Empty when every field passed, and when nothing has been saved yet.
	 *
	 * @return array<string, string> Keyed by field.
	 * @since 1.2.0
	 */
	public function errors(): array {
		return $this->errors;
	}

	/**
	 * Sanitize, check and store a submission.
	 *
	 * The raw input is unslashed once here, at the boundary, rather than in
	 * each sanitizer. Storage APIs disagree about whether they want slashed
	 * data, so each context re-slashes as its own API requires.
	 *
	 * A field whose conditions are not met is deleted rather than stored:
	 * the script hides it, but nothing stops a submission carrying it, and a
	 * hidden field silently keeping a stale value is how conditional settings
	 * come back to life.
	 *
	 * A field whose value fails validation is neither written nor cleared.
	 * What it held before the save is what it holds after, the message is
	 * kept for errors() and for the next render(), and every other field is
	 * stored as normal — a page of twenty settings is not thrown away over
	 * one bad email address.
	 *
	 * @param array<string, mixed> $input     Raw submitted values, still slashed.
	 * @param int|string           $object_id Object the values belong to.
	 *
	 * @return array<string, mixed> The values actually stored.
	 */
	public function save( array $input, int|string $object_id = 0 ): array {
		$stored       = [];
		$this->errors = [];

		foreach ( $this->sanitized( $input, $object_id ) as [ $field, $met, $value ] ) {
			if ( ! $met ) {
				$this->context->delete( $object_id, $field );

				continue;
			}

			$message = $this->check( $field, $value );

			if ( '' !== $message ) {
				$this->errors[ $field->key() ] = $message;

				continue;
			}

			if ( $this->is_empty( $value ) ) {
				$this->context->delete( $object_id, $field );

				continue;
			}

			$this->context->write( $object_id, $field, $value );

			$stored[ $field->key() ] = $value;
		}

		// A batching store — an option holds every field in one row — is told
		// once, here, rather than writing per field. Checked against the
		// contract and not against OptionContext, because the context reaching
		// this point is routinely a decorator wrapping one.
		if ( $this->context instanceof Flushable ) {
			$this->context->save();
		}

		return $stored;
	}

	/**
	 * Walk a submission the way save() does, producing each field's value.
	 *
	 * One walk shared by save() and validate(), because the two have to
	 * agree about which fields count and what their values are. A check
	 * that runs on slightly different input from the store it guards is a
	 * check that passes values the store then refuses, or the reverse — and
	 * two copies of a pipeline are never slightly different on purpose.
	 *
	 * A field a save would not touch — a type that stores nothing, a locked
	 * or disabled control — is left out entirely. One whose conditions are
	 * not met is included and marked so, since save() has to clear it.
	 *
	 * @param array<string, mixed> $input     Raw submitted values, still slashed.
	 * @param int|string           $object_id Object the values belong to.
	 *
	 * @return array<int, array{0: Field, 1: bool, 2: mixed}> Per field: the
	 *                                                        field, whether its
	 *                                                        conditions are met,
	 *                                                        and its sanitized
	 *                                                        value — null when
	 *                                                        they are not.
	 */
	private function sanitized( array $input, int|string $object_id ): array {
		$input   = wp_unslash( $input );
		$entries = [];

		foreach ( $this->fields( $object_id ) as $field ) {
			if ( ! $field->type()->stores_value() ) {
				continue;
			}

			// A locked or disabled control sends nothing, so the rules below would
			// read it as cleared and delete the stored value. An install that lost
			// a licence would have its premium settings wiped by the next unrelated
			// save, and get them back as blanks when the licence returned.
			if ( (bool) $field->get( 'disabled' ) || Badge::locks( $field ) ) {
				continue;
			}

			$conditions = Conditions::from( $field->get( 'show_when', $field->get( 'depends', [] ) ) );

			if ( ! $conditions->is_empty() && ! $conditions->are_met( $input ) ) {
				$entries[] = [ $field, false, null ];

				continue;
			}

			$raw = $input[ $field->key() ] ?? null;

			// A consumer's own cleaning, which replaces the type's rather
			// than running after it: someone who supplies one has an opinion
			// about the whole value, and running the type's first would mean
			// they never see what was actually submitted. The key is listed
			// on Field as common, so every type has to honour it — only
			// `custom` did, which made it true of one type and documented for
			// all of them.
			$override = $field->get( 'sanitize_callback' );

			$entries[] = [
				$field,
				true,
				is_callable( $override )
					? $override( $raw, $field )
					: $field->type()->sanitize( $raw, $field ),
			];
		}

		return $entries;
	}

	/**
	 * The message for one sanitized value, or nothing.
	 *
	 * `required` is checked here rather than by the type, because it is the
	 * one rule every field shares and the one whose answer depends on what
	 * this set considers empty: zero is a value, an empty list is not, and
	 * that line is drawn once, in is_empty(), rather than by each of fifty
	 * types. The field's own `validate` rule runs only on a value that is
	 * there — an optional field left blank is not wrong.
	 *
	 * Only the set's own fields are checked. A required field inside a group
	 * or a repeater row is marked required in the markup, which the browser
	 * enforces, but its parent's value is stored whole and is not walked
	 * here.
	 *
	 * @param Field $field The field.
	 * @param mixed $value Its sanitized value.
	 *
	 * @return string
	 */
	private function check( Field $field, mixed $value ): string {
		if ( ! $this->is_empty( $value ) ) {
			return $field->type()->validate( $value, $field );
		}

		if ( ! $field->is_required() ) {
			return '';
		}

		return sprintf(
			/* translators: %s: field label */
			__( '%s is required.', 'arraypress' ),
			Rules::name( $field )
		);
	}

	/**
	 * Whether a sanitized value is worth storing.
	 *
	 * Zero and "0" are values, not emptiness — an unchecked checkbox stores
	 * 0 deliberately, and treating it as empty is how a saved "off" reverts
	 * to the default on the next load.
	 *
	 * @param mixed $value Sanitized value.
	 *
	 * @return bool
	 */
	private function is_empty( mixed $value ): bool {
		if ( is_array( $value ) ) {
			return [] === $value;
		}

		return null === $value || '' === $value;
	}

	/**
	 * Declare this set's keys to WordPress.
	 *
	 * One line for a consuming library, and the object type is not one of the
	 * arguments: the context already knows what kind of store it is, being
	 * the thing that calls `update_metadata()` with that same string.
	 *
	 * A set backed by an option registers nothing. A settings page declares
	 * itself once with `register_setting()`, which is a different call with a
	 * different shape — not something to approximate per field.
	 *
	 * @param string $subtype Post type or taxonomy, where the object has one.
	 *
	 * @return string[] The keys that were registered.
	 */
	public function register_meta( string $subtype = '' ): array {
		return ( new MetaRegistrar( $this->context, $subtype, $this->registry ) )->register( $this->configs );
	}

	/**
	 * Script and style handles every field in the set needs.
	 *
	 * @return array{scripts: string[], styles: string[]}
	 */
	public function dependencies(): array {
		$scripts = [];
		$styles  = [];
		$editors = [];

		foreach ( $this->fields() as $field ) {
			$type    = $field->type();
			$needs   = $type->dependencies();
			$scripts = array_merge( $scripts, $needs['scripts'] ?? [] );
			$styles  = array_merge( $styles, $needs['styles'] ?? [] );

			// A code field's language comes from its own config, not from the
			// type, so it is asked per field rather than once per type.
			if ( method_exists( $type, 'editor_types' ) ) {
				$editors = array_merge( $editors, $type->editor_types( $field ) );
			}
		}

		return [
			'scripts'      => array_values( array_unique( $scripts ) ),
			'styles'       => array_values( array_unique( $styles ) ),
			'code_editors' => array_values( array_unique( $editors ) ),
		];
	}
}
