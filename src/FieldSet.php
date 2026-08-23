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

use ArrayPress\FieldKit\Context\OptionContext;
use ArrayPress\FieldKit\Contracts\Context;

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

		$field = new Field( $key, $resolved, $config, null );

		return $resolved->stores_value()
			? $field->with_value( $this->context->read( $object_id, $field ) )
			: $field;
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

		// An option-backed set batches its writes, so the whole page is one
		// database write rather than one per field.
		if ( $this->context instanceof OptionContext ) {
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
	 * Script and style handles every field in the set needs.
	 *
	 * @return array{scripts: string[], styles: string[]}
	 */
	public function dependencies(): array {
		$scripts = [];
		$styles  = [];

		foreach ( $this->fields() as $field ) {
			$needs   = $field->type()->dependencies();
			$scripts = array_merge( $scripts, $needs['scripts'] ?? [] );
			$styles  = array_merge( $styles, $needs['styles'] ?? [] );
		}

		return [
			'scripts' => array_values( array_unique( $scripts ) ),
			'styles'  => array_values( array_unique( $styles ) ),
		];
	}
}
