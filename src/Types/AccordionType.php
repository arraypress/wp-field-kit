<?php
/**
 * Accordion Field Type
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
 * Class AccordionType
 *
 * Divides the fields that follow it into a collapsible section, up to the
 *
 * next marker.
 *
 * It is a marker, not a control. It renders nothing here: the field set sees
 * the marker and builds a heading and a collapsible region around the fields
 * between markers, because that grouping cannot be expressed by a field that
 * only knows about itself.
 *
 * Set `open` to have the first state be expanded.
 */
final class AccordionType extends AbstractLayoutType {

	/**
	 * This field opens an accordion section.
	 *
	 * @return string
	 * @since 1.1.0
	 */
	public function opens_section(): string {
		return 'accordion';
	}

	/**
	 * Nothing: the field set draws the section from the marker.
	 *
	 * @param Field      $field      The field.
	 * @param Attributes $attributes Control attributes.
	 *
	 * @return string
	 * @since 1.1.0
	 */
	public function render( Field $field, Attributes $attributes ): string {
		return '';
	}
}
