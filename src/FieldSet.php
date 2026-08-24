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
					$this->source_name( (string) $key ),
					$callback,
					(string) ( $config['search_capability'] ?? 'edit_posts' )
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

			foreach ( $handlers as $name => $callback ) {
				if ( ! is_callable( $callback ) ) {
					continue;
				}

				$actions->register(
					new CallbackAction(
						$this->action_name( (string) $key, (string) $name ),
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
			$config['search_source'] = $this->source_name( $key );
		}

		// Likewise for its buttons: the field emits the registered names, so
		// a button in the page corresponds to a handler that exists.
		$config['action_names'] = $this->action_names_for( $key, $config );

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
	 * @param int|string            $object_id Object the values belong to.
	 * @param array<string, string> $errors    Validation messages keyed by field.
	 *
	 * @return string
	 */
	public function render( int|string $object_id = 0, array $errors = [] ): string {
		$markup = '';

		foreach ( $this->fields( $object_id ) as $field ) {
			$markup .= $this->renderer->render( $field, $errors[ $field->key() ] ?? '' );
		}

		return $markup;
	}

	/**
	 * Render one field that has already been built.
	 *
	 * Consumers that lay fields out themselves — a term screen's table rows,
	 * a metabox's own grid — need the control without the set's own loop.
	 *
	 * @param Field  $field      The field.
	 * @param string $error      Optional validation message.
	 * @param bool   $with_label Whether to emit the label.
	 *
	 * @return string
	 */
	public function render_field( Field $field, string $error = '', bool $with_label = true ): string {
		return $this->renderer->render( $field, $error, $with_label );
	}

	/**
	 * Sanitize and store a submission.
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
	 * @param array<string, mixed> $input     Raw submitted values, still slashed.
	 * @param int|string           $object_id Object the values belong to.
	 *
	 * @return array<string, mixed> The values actually stored.
	 */
	public function save( array $input, int|string $object_id = 0 ): array {
		$input  = wp_unslash( $input );
		$stored = [];

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
				$this->context->delete( $object_id, $field );

				continue;
			}

			$value = $field->type()->sanitize( $input[ $field->key() ] ?? null, $field );

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
