<?php
/**
 * Icon Field Type
 *
 * @package     ArrayPress\FieldKit
 * @copyright   Copyright (c) 2026, ArrayPress Limited
 * @license     GPL-2.0-or-later
 * @since       1.1.0
 * @author      David Sherlock
 */

declare( strict_types=1 );

namespace ArrayPress\FieldKit\Types;

use ArrayPress\FieldKit\Attributes;
use ArrayPress\FieldKit\Field;

/**
 * Class IconType
 *
 * Pick a Dashicon.
 *
 * A searchable select rather than a grid of every icon: WordPress ships around
 * three hundred of them, and a wall of pictograms is slower to use than typing
 * the first few letters of the one you want. The chosen icon is shown beside
 * the control so the name is not the only feedback.
 *
 * The stored value is the full class -- `dashicons-cart` -- so it can be
 * printed straight into a class attribute without the caller reassembling it.
 *
 * Supply `options` to offer a different set. The value is used as a class name,
 * so anything with a class-per-icon works; the icon font itself is the
 * caller's to enqueue.
 */
final class IconType extends SelectType {

	/**
	 * Dashicons worth offering, grouped by what they are for.
	 *
	 * A curated list rather than all three hundred: the long tail is
	 * WordPress-admin furniture nobody picks deliberately, and offering it
	 * makes the useful ones harder to find. Pass `options` for the rest.
	 *
	 * @since 1.1.0
	 * @var array<string, string>
	 */
	public const ICONS = [
		'dashicons-admin-generic'   => 'Settings',
		'dashicons-admin-site'      => 'Site',
		'dashicons-admin-users'     => 'Users',
		'dashicons-analytics'       => 'Analytics',
		'dashicons-archive'         => 'Archive',
		'dashicons-awards'          => 'Awards',
		'dashicons-bell'            => 'Bell',
		'dashicons-book'            => 'Book',
		'dashicons-calendar-alt'    => 'Calendar',
		'dashicons-camera'          => 'Camera',
		'dashicons-cart'            => 'Cart',
		'dashicons-chart-bar'       => 'Chart',
		'dashicons-clipboard'       => 'Clipboard',
		'dashicons-clock'           => 'Clock',
		'dashicons-cloud'           => 'Cloud',
		'dashicons-download'        => 'Download',
		'dashicons-email'           => 'Email',
		'dashicons-flag'            => 'Flag',
		'dashicons-format-image'    => 'Image',
		'dashicons-groups'          => 'Groups',
		'dashicons-heart'           => 'Heart',
		'dashicons-info'            => 'Info',
		'dashicons-location'        => 'Location',
		'dashicons-lock'            => 'Lock',
		'dashicons-media-document'  => 'Document',
		'dashicons-megaphone'       => 'Megaphone',
		'dashicons-money-alt'       => 'Money',
		'dashicons-nametag'         => 'Nametag',
		'dashicons-palmtree'        => 'Palm tree',
		'dashicons-performance'     => 'Performance',
		'dashicons-phone'           => 'Phone',
		'dashicons-products'        => 'Products',
		'dashicons-shield'          => 'Shield',
		'dashicons-star-filled'     => 'Star',
		'dashicons-store'           => 'Store',
		'dashicons-tag'             => 'Tag',
		'dashicons-tickets-alt'     => 'Tickets',
		'dashicons-translation'     => 'Translation',
		'dashicons-trash'           => 'Trash',
		'dashicons-warning'         => 'Warning',
	];

	/**
	 * The type's id.
	 *
	 * @return string
	 * @since 1.1.0
	 */
	public function id(): string {
		return 'icon';
	}

	/**
	 * Searchable, and offering the curated set unless told otherwise.
	 *
	 * @return array<string, mixed>
	 * @since 1.1.0
	 */
	public function defaults(): array {
		return array_merge(
			parent::defaults(),
			[
				'options'    => self::ICONS,
				'searchable' => true,
			]
		);
	}

	/**
	 * The control, with the chosen icon shown beside it.
	 *
	 * @param Field      $field      The field.
	 * @param Attributes $attributes Control attributes.
	 *
	 * @return string
	 * @since 1.1.0
	 */
	public function render( Field $field, Attributes $attributes ): string {
		$attributes->add_class( 'field-kit__icon-select' );

		$value = (string) $field->value();

		/*
		 * The empty state gets its own class rather than an empty dashicon.
		 * `class="dashicons "` renders nothing at all, so the control came up
		 * with an invisible gap where the preview should be and read as
		 * broken before it had been touched.
		 */
		$preview = sprintf(
			'<span class="field-kit__icon-preview dashicons %s%s" aria-hidden="true"></span>',
			'' === $value ? 'dashicons-art field-kit__icon-preview--empty' : esc_attr( $value ),
			''
		);

		return sprintf(
			'<span class="field-kit__icon">%s%s</span>',
			$preview,
			parent::render( $field, $attributes )
		);
	}

	/**
	 * Only a value that is actually on offer is stored.
	 *
	 * The value is printed into a class attribute, so anything typed rather
	 * than chosen has to be rejected here.
	 *
	 * @param mixed $value The submitted value.
	 * @param Field $field The field.
	 *
	 * @return mixed
	 * @since 1.1.0
	 */
	public function sanitize( mixed $value, Field $field ): mixed {
		$value = sanitize_html_class( (string) ( is_array( $value ) ? '' : $value ) );

		return array_key_exists( $value, $field->options() ) ? $value : '';
	}
}
