<?php
/**
 * Provider list tests.
 *
 * @package ArrayPress\FieldKit
 */

declare( strict_types=1 );

namespace ArrayPress\FieldKit\Tests;

use ArrayPress\FieldKit\Field;
use ArrayPress\FieldKit\Registry;
use ArrayPress\FieldKit\Renderer;
use PHPUnit\Framework\TestCase;

/**
 * A list of things another plugin registered: ordered, switched on, and each
 * configured on its own.
 *
 * Payment gateways are the shape everybody recognises, but nothing here knows
 * the word -- shipping methods, tax providers and storage backends are the
 * same list with different names in it.
 *
 * The two things worth pinning are the ones that bite in production: a
 * provider registered after a value was stored has to appear, and an id the
 * form offers that nobody registered has to be refused.
 */
final class ProvidersTest extends TestCase {

	/**
	 * Two providers, one of them configurable.
	 *
	 * @return array<string, array<string, mixed>>
	 */
	private static function gateways(): array {
		return [
			'stripe' => [
				'label'       => 'Stripe',
				'description' => 'Cards and wallets.',
				'fields'      => [
					'publishable_key' => [ 'type' => 'text', 'label' => 'Publishable key' ],
					'secret_key'      => [ 'type' => 'password', 'label' => 'Secret key' ],
				],
			],
			'paypal' => [
				'label' => 'PayPal',
			],
		];
	}

	/**
	 * Build the field.
	 *
	 * @param array<string, mixed> $config Extra configuration.
	 * @param mixed                $value  Stored value.
	 *
	 * @return Field
	 */
	private function field( array $config = [], $value = null ): Field {
		return new Field(
			'gateways',
			( new Registry() )->get( 'providers' ),
			array_merge(
				[
					'label'      => 'Payment gateways',
					'input_name' => 'gateways',
					'providers'  => self::gateways(),
				],
				$config
			),
			$value
		);
	}

	/**
	 * Render the field.
	 *
	 * @param array<string, mixed> $config Extra configuration.
	 * @param mixed                $value  Stored value.
	 *
	 * @return string
	 */
	private function render( array $config = [], $value = null ): string {
		return ( new Renderer() )->render( $this->field( $config, $value ) );
	}

	/**
	 * Sanitize a submission.
	 *
	 * @param mixed                $submitted Raw submitted value.
	 * @param array<string, mixed> $config    Extra configuration.
	 *
	 * @return array<string, mixed>
	 */
	private function save( $submitted, array $config = [] ): array {
		$field = $this->field( $config );

		return $field->type()->sanitize( $submitted, $field );
	}

	/**
	 * Every registered provider is a row, in the stored order.
	 */
	public function test_it_renders_a_row_per_provider_in_order(): void {
		$html = $this->render( [], [ 'order' => [ 'paypal', 'stripe' ] ] );

		$this->assertSame( 2, substr_count( $html, 'class="field-kit__provider"' ) );
		$this->assertLessThan(
			strpos( $html, 'data-key="stripe"' ),
			strpos( $html, 'data-key="paypal"' ),
			'The stored order was not honoured.'
		);
	}

	/**
	 * The order is the order of the posted values.
	 */
	public function test_each_row_carries_its_position(): void {
		$html = $this->render();

		$this->assertStringContainsString( 'name="gateways[order][]" value="stripe"', $html );
		$this->assertStringContainsString( 'name="gateways[order][]" value="paypal"', $html );
	}

	/**
	 * A provider registered after a value was stored still appears.
	 *
	 * Otherwise installing an add-on adds a gateway nobody can find. Appended
	 * rather than inserted: the end is the only position that does not
	 * silently change what was already arranged.
	 */
	public function test_a_newly_registered_provider_is_appended(): void {
		$html = $this->render( [], [ 'order' => [ 'paypal' ] ] );

		$this->assertSame( 2, substr_count( $html, 'class="field-kit__provider"' ) );
		$this->assertLessThan(
			strpos( $html, 'data-key="stripe"' ),
			strpos( $html, 'data-key="paypal"' )
		);
	}

	/**
	 * It is a switch, not a checkbox.
	 *
	 * On and off is what is being asked, and `role="switch"` is what makes it
	 * announce that way instead of as checked and unchecked. Still a real
	 * checkbox underneath, so it is focusable and operable from the keyboard
	 * with nothing rebuilt.
	 */
	public function test_enabling_a_provider_is_a_switch(): void {
		$html = $this->render( [], [ 'enabled' => [ 'stripe' ] ] );

		$this->assertMatchesRegularExpression(
			'/class="field-kit__toggle[^"]*"[^>]*value="stripe"[^>]*role="switch"[^>]*aria-checked="true"/',
			$html
		);

		$this->assertMatchesRegularExpression(
			'/value="paypal"[^>]*aria-checked="false"/',
			$html
		);

		// Icon-only, so it carries its own name.
		$this->assertStringContainsString( 'aria-label="Enable Stripe"', $html );
	}

	/**
	 * Reordering is one control, and it can be operated from a keyboard.
	 *
	 * A pair of chevrons beside a drag handle is two controls doing the same
	 * job. Dropping them is only safe because the handle became a real
	 * button: a handle that is only draggable cannot be used from a keyboard
	 * at all, which would leave the list unorderable without a pointer.
	 */
	public function test_reordering_is_a_single_focusable_handle(): void {
		$html = $this->render();

		$this->assertStringNotContainsString( 'field-kit__provider-move', $html );
		$this->assertStringNotContainsString( 'dashicons-arrow-up-alt2', $html );

		$this->assertMatchesRegularExpression(
			'/<button[^>]*field-kit__drag-handle[^>]*type="button"/',
			$html
		);

		$this->assertStringContainsString( 'aria-keyshortcuts="ArrowUp ArrowDown"', $html );
	}

	/**
	 * The handle says where the row is, and can say where it moved to.
	 *
	 * A handle that only says "Reorder Stripe" gives no feedback that a move
	 * landed. The wording is kept as a template so the script rewrites the
	 * numbers without a second string to translate.
	 */
	public function test_the_handle_announces_its_position(): void {
		$html = $this->render();

		$this->assertStringContainsString( 'aria-label="Reorder Stripe, 1 of 2"', $html );
		$this->assertStringContainsString( 'aria-label="Reorder PayPal, 2 of 2"', $html );
		$this->assertStringContainsString( 'data-label-template="Reorder Stripe, {position} of {total}"', $html );

		$js = (string) file_get_contents( dirname( __DIR__ ) . '/assets/js/field-kit.js' );

		$this->assertStringContainsString( 'handle.dataset.labelTemplate', $js );
		$this->assertStringContainsString( "button.field-kit__drag-handle", $js );
	}

	/**
	 * A provider's own fields are namespaced under it.
	 */
	public function test_a_providers_settings_are_scoped_to_it(): void {
		$html = $this->render(
			[],
			[
				'settings' => [
					'stripe' => [ 'publishable_key' => 'pk_test', 'secret_key' => 'sk_test' ],
				],
			]
		);

		$this->assertStringContainsString( 'name="gateways[settings][stripe][publishable_key]"', $html );
		$this->assertStringContainsString( 'value="pk_test"', $html );

		// A password inside a provider's panel is still a password: the type
		// never echoes a stored value back into the page, and being nested
		// does not change that.
		$this->assertStringContainsString( 'name="gateways[settings][stripe][secret_key]"', $html );
		$this->assertStringNotContainsString( 'sk_test', $html );
	}

	/**
	 * A provider with nothing to configure gets no Configure button.
	 *
	 * A control that opens an empty panel is worse than no control.
	 */
	public function test_a_provider_with_no_fields_has_no_configure(): void {
		$html = $this->render();

		$this->assertSame( 1, substr_count( $html, 'field-kit__provider-configure' ) );
		$this->assertStringContainsString( 'aria-label="Configure Stripe"', $html );
	}

	/**
	 * Inline, the settings are a disclosure.
	 */
	public function test_inline_settings_are_a_disclosure(): void {
		$html = $this->render( [ 'config' => 'inline' ] );

		$this->assertStringContainsString( 'aria-expanded="false"', $html );
		$this->assertStringContainsString( 'field-kit__provider-body', $html );
		$this->assertStringNotContainsString( '<dialog', $html );
	}

	/**
	 * As a flyout, they are a native dialog.
	 *
	 * Which brings the backdrop, the focus trap and Esc with it -- and says
	 * it opens something rather than that it is collapsed, because
	 * announcing "collapsed" for a modal is simply wrong.
	 */
	public function test_flyout_settings_are_a_dialog(): void {
		$html = $this->render( [ 'config' => 'flyout' ] );

		$this->assertStringContainsString( '<dialog', $html );
		$this->assertStringContainsString( 'aria-haspopup="dialog"', $html );
		$this->assertStringNotContainsString( 'aria-expanded', $html );

		// Not a nested form: this sits inside the form around it, and the
		// controls in it have to post with it.
		$this->assertStringNotContainsString( '<form', $html );
	}

	/**
	 * An id nobody registered is not a provider, whatever the form said.
	 */
	public function test_an_unregistered_id_is_refused(): void {
		$saved = $this->save(
			[
				'order'   => [ 'paypal', 'evil', 'stripe' ],
				'enabled' => [ 'evil', 'stripe' ],
			]
		);

		$this->assertSame( [ 'paypal', 'stripe' ], $saved['order'] );
		$this->assertSame( [ 'stripe' ], $saved['enabled'] );
	}

	/**
	 * A provider missing from the submission is appended rather than lost.
	 */
	public function test_a_provider_absent_from_the_submission_survives(): void {
		$saved = $this->save( [ 'order' => [ 'stripe' ] ] );

		$this->assertSame( [ 'stripe', 'paypal' ], $saved['order'] );
	}

	/**
	 * A provider's settings go through its own fields.
	 */
	public function test_settings_are_sanitized_by_the_providers_own_fields(): void {
		$saved = $this->save(
			[
				'settings' => [
					'stripe' => [ 'publishable_key' => ' pk_test ', 'not_a_field' => 'x' ],
					'evil'   => [ 'publishable_key' => 'nope' ],
				],
			]
		);

		$this->assertArrayHasKey( 'stripe', $saved['settings'] );
		$this->assertArrayNotHasKey( 'not_a_field', $saved['settings']['stripe'] );

		// PayPal declares no fields, so it has no settings to store.
		$this->assertArrayNotHasKey( 'paypal', $saved['settings'] );
		$this->assertArrayNotHasKey( 'evil', $saved['settings'] );
	}

	/**
	 * Nothing submitted at all is still a valid, complete value.
	 */
	public function test_an_empty_submission_stores_every_provider_unswitched(): void {
		$saved = $this->save( null );

		$this->assertSame( [ 'stripe', 'paypal' ], $saved['order'] );
		$this->assertSame( [], $saved['enabled'] );
	}

	/**
	 * The list can come from a callable, resolved when the field is drawn.
	 *
	 * Which is the whole point: a registry that fills up on `init` is read
	 * when the screen is drawn rather than when the field was configured,
	 * and the thing that owns the list keeps owning it.
	 */
	public function test_the_list_can_be_a_callable(): void {
		$html = $this->render( [ 'providers' => static fn(): array => self::gateways() ] );

		$this->assertStringContainsString( 'data-key="stripe"', $html );
		$this->assertStringContainsString( 'data-key="paypal"', $html );
	}

	/**
	 * A bare label is a provider with nothing to configure.
	 */
	public function test_a_string_is_a_label(): void {
		$html = $this->render( [ 'providers' => [ 'cod' => 'Cash on delivery' ] ] );

		$this->assertStringContainsString( '>Cash on delivery</span>', $html );
		$this->assertStringNotContainsString( 'field-kit__provider-configure', $html );
	}

	/**
	 * Nothing registered says so rather than drawing an empty box.
	 */
	public function test_an_empty_registry_says_so(): void {
		$html = $this->render( [ 'providers' => [] ] );

		$this->assertStringContainsString( 'field-kit__providers-empty', $html );
		$this->assertStringNotContainsString( '<ol', $html );
	}

	/**
	 * Ordering and switching can each be turned off.
	 */
	public function test_ordering_and_switching_are_optional(): void {
		$html = $this->render( [ 'orderable' => false, 'toggleable' => false ] );

		$this->assertStringNotContainsString( 'field-kit__drag-handle', $html );
		$this->assertStringNotContainsString( '[enabled][]', $html );

		// The position input stays: it is what tells the save what was on
		// screen, whether or not it could be rearranged.
		$this->assertStringContainsString( 'name="gateways[order][]"', $html );
	}
}
