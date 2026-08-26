<?php
/**
 * Providers Field Type
 *
 * @package     ArrayPress\FieldKit
 * @copyright   Copyright (c) 2026, ArrayPress Limited
 * @license     GPL2+
 * @since       1.0.0
 */

declare( strict_types=1 );

namespace ArrayPress\FieldKit\Types;

use ArrayPress\FieldKit\Attributes;
use ArrayPress\FieldKit\Field;

/**
 * A list of things another plugin registered: ordered, switched on, and each
 * configured on its own.
 *
 * Payment gateways are the shape everybody recognises -- the order is the
 * order they appear at checkout, the switch is whether they appear at all,
 * and each one wants a couple of API keys of its own. Shipping methods, tax
 * providers, storage backends and log destinations are all the same shape,
 * which is why nothing here knows the word "gateway".
 *
 * Where a repeater's rows are added by whoever is filling the form, these are
 * fixed: the list is whatever is registered, and the form can only order it,
 * switch entries on and configure them. That is the whole difference, and it
 * is why this is not a repeater with the add button hidden -- a row cannot be
 * added or removed, only moved.
 *
 * The registry is deliberately somewhere else. `providers` takes a callable,
 * resolved when the field is drawn, so the thing that owns the list keeps
 * owning it -- and the checkout that needs to know which gateways are on does
 * not have to load a field-rendering library to ask.
 *
 * A provider is:
 *
 *     'stripe' => [
 *         'label'       => 'Stripe',
 *         'description' => 'Cards, wallets and local methods.',
 *         'logo'        => 'https://example.com/stripe.svg',
 *         'icon'        => 'cart',
 *         'fields'      => [ 'secret_key' => [ 'type' => 'password' ] ],
 *     ]
 *
 * and the stored value is:
 *
 *     [
 *         'order'    => [ 'paypal', 'stripe' ],
 *         'enabled'  => [ 'stripe' ],
 *         'settings' => [ 'stripe' => [ 'secret_key' => '...' ] ],
 *     ]
 *
 * Three keys rather than a list of rows, because the two questions asked of
 * this at runtime are "which are on, in what order" and "what is this one's
 * configuration". Both are a lookup here and a scan in the other shape.
 */
final class ProvidersType extends AbstractNestedType {

	/**
	 * The type's id.
	 *
	 * @return string
	 */
	public function id(): string {
		return 'providers';
	}

	/**
	 * Defaults.
	 *
	 * @return array<string, mixed>
	 */
	public function defaults(): array {
		return [
			'providers'  => [],
			'config'     => 'inline',
			'orderable'  => true,
			'toggleable' => true,
		];
	}

	/**
	 * The registered providers, normalised.
	 *
	 * Accepts a callable, resolved at render, so a registry that fills up on
	 * `init` is read when the screen is drawn rather than when the field was
	 * configured -- which for anything registered by another plugin is the
	 * difference between the list being complete and being empty.
	 *
	 * @param Field $field The field.
	 *
	 * @return array<string, array<string, mixed>>
	 */
	private function providers( Field $field ): array {
		$providers = $field->get( 'providers', [] );

		if ( is_callable( $providers ) ) {
			$providers = $providers( $field );
		}

		if ( ! is_array( $providers ) ) {
			return [];
		}

		$clean = [];

		foreach ( $providers as $id => $provider ) {
			// A bare label is allowed: `[ 'stripe' => 'Stripe' ]` is a
			// perfectly good list of things with nothing to configure.
			$clean[ (string) $id ] = is_array( $provider )
				? $provider
				: [ 'label' => (string) $provider ];
		}

		return $clean;
	}

	/**
	 * The provider ids in their stored order, with new ones appended.
	 *
	 * A provider registered after a value was stored has to appear, or
	 * installing an add-on adds a gateway nobody can find. Appended rather
	 * than inserted, because the end of the list is the only position that
	 * does not silently change what was already arranged.
	 *
	 * @param Field                              $field     The field.
	 * @param array<string, array<string, mixed>> $providers Registered providers.
	 *
	 * @return string[]
	 */
	private function order( Field $field, array $providers ): array {
		$value  = (array) $field->value();
		$stored = array_map( 'strval', (array) ( $value['order'] ?? [] ) );
		$ids    = array_map( 'strval', array_keys( $providers ) );
		$order  = array_values( array_intersect( $stored, $ids ) );

		return array_merge( $order, array_values( array_diff( $ids, $order ) ) );
	}

	/**
	 * Render the list.
	 *
	 * @param Field      $field      The field.
	 * @param Attributes $attributes Prepared attributes.
	 *
	 * @return string
	 */
	public function render( Field $field, Attributes $attributes ): string {
		$providers = $this->providers( $field );

		if ( [] === $providers ) {
			return sprintf(
				'<p class="field-kit__providers-empty description">%s</p>',
				esc_html( (string) $field->get( 'empty_label', __( 'Nothing is registered yet.', 'arraypress' ) ) )
			);
		}

		$order = $this->order( $field, $providers );
		$total = count( $order );
		$rows  = '';

		foreach ( $order as $position => $id ) {
			$rows .= $this->render_provider( $field, (string) $id, $providers[ $id ], $position, $total );
		}

		$wrapper = new Attributes();
		$wrapper->add_class( 'field-kit__providers' );
		$wrapper->set( 'data-config', $this->layout( $field ) );

		return sprintf(
			'<div%s><ol class="field-kit__provider-list">%s</ol></div>',
			$wrapper->render(),
			$rows
		);
	}

	/**
	 * Where a provider's own settings are drawn.
	 *
	 * @param Field $field The field.
	 *
	 * @return string Either `inline` or `flyout`.
	 */
	private function layout( Field $field ): string {
		return 'flyout' === (string) $field->get( 'config', 'inline' ) ? 'flyout' : 'inline';
	}

	/**
	 * One provider.
	 *
	 * @param Field                $field    The field.
	 * @param string               $id       Provider id.
	 * @param array<string, mixed> $provider Provider configuration.
	 * @param int                  $position Its place in the list.
	 * @param int                  $total    How many there are.
	 *
	 * @return string
	 */
	private function render_provider( Field $field, string $id, array $provider, int $position, int $total ): string {
		$label     = (string) ( $provider['label'] ?? $id );
		$orderable = (bool) $field->get( 'orderable', true );
		$fields    = (array) ( $provider['fields'] ?? [] );
		$body_id   = sanitize_key( $field->input_id() . '_' . $id . '_settings' );

		// The order is the order of the posted values, so every row carries a
		// hidden input and reordering the list reorders them. Present whether
		// or not the list can be reordered, because it is also what tells the
		// save which providers were on screen.
		$position_input = sprintf(
			'<input type="hidden" name="%s[order][]" value="%s" />',
			esc_attr( $field->input_name() ),
			esc_attr( $id )
		);

		$row = new Attributes();
		$row->add_class( 'field-kit__provider' );
		$row->set( 'data-key', $id );

		// Name on the left, controls on the right, the grab edge outermost.
		$controls = sprintf(
			'<span class="field-kit__provider-controls">%s%s%s</span>',
			$this->render_toggle( $field, $id, $label ),
			[] === $fields ? '' : $this->render_configure( $label, $body_id, $this->layout( $field ) ),
			$orderable ? $this->render_handle( $label, $position, $total ) : ''
		);

		return sprintf(
			'<li%s>%s<div class="field-kit__provider-row">%s%s</div>%s</li>',
			$row->render(),
			$position_input,
			$this->render_identity( $provider, $label ),
			$controls,
			[] === $fields ? '' : $this->render_body( $field, $id, $label, $fields, $body_id )
		);
	}

	/**
	 * The switch that says whether a provider is on.
	 *
	 * @param Field  $field The field.
	 * @param string $id    Provider id.
	 * @param string $label Provider label.
	 *
	 * @return string
	 */
	private function render_toggle( Field $field, string $id, string $label ): string {
		if ( ! (bool) $field->get( 'toggleable', true ) ) {
			return '';
		}

		$value   = (array) $field->value();
		$enabled = array_map( 'strval', (array) ( $value['enabled'] ?? [] ) );

		$on = in_array( $id, $enabled, true );

		// The kit's switch rather than a bare checkbox: on and off is what is
		// being asked, and role="switch" is what makes it announce that way
		// instead of as checked and unchecked. Still a real checkbox
		// underneath, so it is focusable and operable from the keyboard with
		// nothing rebuilt.
		$box = new Attributes();
		$box->set( 'type', 'checkbox' );
		$box->set( 'name', $field->input_name() . '[enabled][]' );
		$box->set( 'value', $id );
		$box->add_class( 'field-kit__toggle', 'field-kit__provider-switch' );
		$box->set( 'role', 'switch' );
		$box->set( 'aria-checked', $on ? 'true' : 'false' );
		$box->set_if( $on, 'checked', true );
		$box->set(
			'aria-label',
			sprintf(
				/* translators: %s: provider label, such as "Stripe" */
				__( 'Enable %s', 'arraypress' ),
				$label
			)
		);

		return sprintf( '<input%s />', $box->render() );
	}

	/**
	 * The logo or glyph, the name, and what it is.
	 *
	 * @param array<string, mixed> $provider Provider configuration.
	 * @param string               $label    Provider label.
	 *
	 * @return string
	 */
	private function render_identity( array $provider, string $label ): string {
		$logo = (string) ( $provider['logo'] ?? '' );
		$icon = (string) ( $provider['icon'] ?? '' );

		// alt="" on purpose: the name is right beside it, so a described logo
		// would announce the provider twice.
		$mark = '';

		if ( '' !== $logo ) {
			$mark = sprintf(
				'<img class="field-kit__provider-logo" src="%s" alt="" />',
				esc_url( $logo )
			);
		} elseif ( '' !== $icon ) {
			$mark = sprintf(
				'<span class="field-kit__provider-logo dashicons dashicons-%s" aria-hidden="true"></span>',
				esc_attr( $icon )
			);
		}

		$description = (string) ( $provider['description'] ?? '' );

		return sprintf(
			'<span class="field-kit__provider-identity">%s<span class="field-kit__provider-text">' .
			'<span class="field-kit__provider-label">%s</span>%s</span></span>',
			$mark,
			esc_html( $label ),
			'' === $description
				? ''
				: sprintf( '<span class="description">%s</span>', esc_html( $description ) )
		);
	}

	/**
	 * The grab handle, which is also the keyboard's way to reorder.
	 *
	 * A button rather than a decorative span. Dragging cannot be done from a
	 * keyboard at all, so a handle that is only draggable makes the list
	 * unorderable for anyone not using a pointer -- and a pair of chevrons
	 * beside it is a second control doing the same job, which is what this
	 * replaced. One control: drag it, or focus it and press the arrow keys.
	 *
	 * The position is in its accessible name because a handle that only says
	 * "Reorder Stripe" gives no feedback that anything happened; announcing
	 * "3 of 4" is how a keyboard user knows the move landed.
	 *
	 * @param string $label    Provider label.
	 * @param int    $position Its place in the list.
	 * @param int    $total    How many there are.
	 *
	 * @return string
	 */
	private function render_handle( string $label, int $position, int $total ): string {
		$handle = new Attributes();
		$handle->set( 'type', 'button' );
		$handle->add_class( 'field-kit__drag-handle', 'field-kit__provider-handle' );
		$handle->set( 'aria-roledescription', __( 'Sortable', 'arraypress' ) );
		$handle->set( 'aria-keyshortcuts', 'ArrowUp ArrowDown' );
		/*
		 * One translated string, kept as a template so the script can rewrite
		 * the numbers after a move without a second copy of the wording to
		 * translate -- and without the announced position going stale the
		 * moment a row is dragged.
		 */
		$template = sprintf(
			/* translators: 1: provider label, 2: its position, 3: how many there are */
			__( 'Reorder %1$s, %2$s of %3$s', 'arraypress' ),
			$label,
			'{position}',
			'{total}'
		);

		$handle->set( 'data-label-template', $template );
		$handle->set(
			'aria-label',
			strtr(
				$template,
				[
					'{position}' => (string) ( $position + 1 ),
					'{total}'    => (string) $total,
				]
			)
		);

		return sprintf(
			'<button%s><span class="dashicons dashicons-menu" aria-hidden="true"></span></button>',
			$handle->render()
		);
	}

	/**
	 * The control that reveals a provider's settings.
	 *
	 * @param string $label   Provider label.
	 * @param string $body_id The element it controls.
	 * @param string $layout  Either `inline` or `flyout`.
	 *
	 * @return string
	 */
	private function render_configure( string $label, string $body_id, string $layout ): string {
		$button = new Attributes();
		$button->set( 'type', 'button' );
		$button->add_class( 'button', 'field-kit__provider-configure' );
		$button->set( 'data-target', $body_id );
		$button->set(
			'aria-label',
			sprintf(
				/* translators: %s: provider label */
				__( 'Configure %s', 'arraypress' ),
				$label
			)
		);

		// A disclosure says whether it is open; a dialog trigger says what it
		// opens. Announcing "collapsed" for something that opens a modal is
		// simply wrong, so only the inline one carries it.
		if ( 'inline' === $layout ) {
			$button->set( 'aria-expanded', 'false' );
			$button->set( 'aria-controls', $body_id );
		} else {
			$button->set( 'aria-haspopup', 'dialog' );
		}

		return sprintf(
			'<button%s>%s</button>',
			$button->render(),
			esc_html__( 'Configure', 'arraypress' )
		);
	}

	/**
	 * A provider's own settings.
	 *
	 * Inline it is a disclosure; as a flyout it is a native dialog, which
	 * brings its own backdrop, focus trap and Esc handling rather than three
	 * more things to write and keep correct. It is not moved in the document
	 * when it opens -- the top layer is a paint concern -- so the controls
	 * inside it still post with the form around them.
	 *
	 * @param Field                $field   The field.
	 * @param string               $id      Provider id.
	 * @param string               $label   Provider label.
	 * @param array<string, mixed> $fields  The provider's fields.
	 * @param string               $body_id The element's id.
	 *
	 * @return string
	 */
	private function render_body( Field $field, string $id, string $label, array $fields, string $body_id ): string {
		$value    = (array) $field->value();
		$settings = (array) ( $value['settings'][ $id ] ?? [] );

		$children = $this->render_children(
			$field,
			$settings,
			$field->input_name() . '[settings][' . $id . ']',
			$id,
			$fields
		);

		if ( 'flyout' === $this->layout( $field ) ) {
			return sprintf(
				'<dialog class="field-kit__provider-dialog" id="%s" aria-labelledby="%1$s-title">' .
				'<div class="field-kit__provider-dialog-header">' .
				'<h2 id="%1$s-title">%2$s</h2>' .
				'<button type="button" class="field-kit__provider-dialog-close" aria-label="%3$s">' .
				'<span class="dashicons dashicons-no-alt" aria-hidden="true"></span></button></div>' .
				'<div class="field-kit__provider-dialog-body">%4$s</div>' .
				'<div class="field-kit__provider-dialog-footer">' .
				'<button type="button" class="button button-primary field-kit__provider-dialog-close">%5$s</button>' .
				'</div></dialog>',
				esc_attr( $body_id ),
				esc_html( $label ),
				esc_attr__( 'Close', 'arraypress' ),
				$children,
				esc_html__( 'Done', 'arraypress' )
			);
		}

		return sprintf(
			'<div class="field-kit__provider-body" id="%s" hidden>%s</div>',
			esc_attr( $body_id ),
			$children
		);
	}

	/**
	 * Coerce a submission.
	 *
	 * @param mixed $value Raw submitted value.
	 * @param Field $field The field.
	 *
	 * @return array{order: string[], enabled: string[], settings: array<string, array<string, mixed>>}
	 */
	public function sanitize( mixed $value, Field $field ): array {
		$providers = $this->providers( $field );
		$ids       = array_map( 'strval', array_keys( $providers ) );
		$value     = is_array( $value ) ? $value : [];

		// Intersect rather than filter: an id that is not registered is not a
		// provider, whatever the form said it was.
		$order = array_values(
			array_intersect( array_map( 'strval', (array) ( $value['order'] ?? [] ) ), $ids )
		);

		$order = array_merge( $order, array_values( array_diff( $ids, $order ) ) );

		$enabled = array_values(
			array_intersect( array_map( 'strval', (array) ( $value['enabled'] ?? [] ) ), $ids )
		);

		$settings = [];

		foreach ( $providers as $id => $provider ) {
			$fields = (array) ( $provider['fields'] ?? [] );

			if ( [] === $fields ) {
				continue;
			}

			$settings[ (string) $id ] = $this->sanitize_children(
				$field,
				(array) ( $value['settings'][ $id ] ?? [] ),
				$fields
			);
		}

		return [
			'order'    => $order,
			'enabled'  => $enabled,
			'settings' => $settings,
		];
	}

	/**
	 * Several controls under one heading.
	 *
	 * @return bool
	 */
	public function is_grouped(): bool {
		return true;
	}

	/**
	 * What this stores.
	 *
	 * @param Field $field The field.
	 *
	 * @return array<string, mixed>
	 */
	public function schema( Field $field ): array {
		return [
			'type'       => 'object',
			'properties' => [
				'order'    => [
					'type' => 'array',
					'items' => [ 'type' => 'string' ],
				],
				'enabled'  => [
					'type' => 'array',
					'items' => [ 'type' => 'string' ],
				],
				'settings' => [ 'type' => 'object' ],
			],
		];
	}

	/**
	 * Scripts and styles this needs.
	 *
	 * @return array{scripts: string[], styles: string[]}
	 */
	public function dependencies(): array {
		return [
			'scripts' => [],
			'styles'  => [ 'dashicons' ],
		];
	}

	/**
	 * The configuration keys this type reads.
	 *
	 * @return string[]
	 */
	public function config_keys(): array {
		return array_merge(
			parent::config_keys(),
			[ 'config', 'empty_label', 'orderable', 'providers', 'toggleable' ]
		);
	}
}
