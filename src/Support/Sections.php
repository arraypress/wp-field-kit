<?php
/**
 * Section Splitting
 *
 * @package     ArrayPress\FieldKit
 * @copyright   Copyright (c) 2026, ArrayPress Limited
 * @license     GPL2+
 * @since       1.0.0
 */

declare( strict_types=1 );

namespace ArrayPress\FieldKit\Support;

use ArrayPress\FieldKit\Field;
use ArrayPress\FieldKit\Types\AccordionType;
use ArrayPress\FieldKit\Utils\Runtime;

/**
 * Turns a flat list of fields into tabs or collapsible regions.
 *
 * A `tab` or `accordion` field is a marker rather than a control: it divides
 * everything after it, up to the next marker, which is ACF's model and the
 * one people expect.
 *
 * This lives apart from FieldSet because a field set is not the only thing
 * that lays fields out. A post metabox, a term form and a user profile each
 * build their own `form-table` and ask the kit for one control at a time --
 * which meant the markers reached them as ordinary fields, rendered nothing,
 * and every tabbed metabox on the demo came out as one flat list with no
 * tabs anywhere. Splitting is the part they were missing; how a group is
 * drawn is theirs, and it is passed in.
 */
final class Sections {

	/**
	 * Split fields on tab or accordion markers.
	 *
	 * Anything before the first marker belongs to no section and is returned
	 * as `lead`, so a set can open with a few fields and then start tabbing.
	 *
	 * One marker is not a division, so a single tab is treated as no tabs at
	 * all -- a tab strip with one tab is furniture that does nothing.
	 *
	 * @param Field[] $fields The built fields.
	 *
	 * @return array{kind: string, lead: Field[], sections: array<int, array{field: Field, fields: Field[]}>}|array
	 *         An empty array when there is nothing to divide.
	 */
	public static function split( array $fields ): array {
		$kind     = '';
		$lead     = [];
		$sections = [];

		foreach ( $fields as $field ) {
			$opens = $field->type()->opens_section();

			if ( '' !== $opens ) {
				// The first marker decides the kind. Mixing tabs and
				// accordions in one set is not a layout, it is a mistake.
				if ( '' === $kind ) {
					$kind = $opens;
				}

				if ( $opens === $kind ) {
					$sections[] = [
						'field'  => $field,
						'fields' => [],
					];

					continue;
				}
			}

			if ( [] === $sections ) {
				$lead[] = $field;

				continue;
			}

			$sections[ array_key_last( $sections ) ]['fields'][] = $field;
		}

		if ( count( $sections ) < 2 ) {
			return [];
		}

		return [
			'kind'     => $kind,
			'lead'     => $lead,
			'sections' => $sections,
		];
	}

	/**
	 * Whether a field is a marker rather than a control.
	 *
	 * A caller laying fields out itself has to skip these in its flat path:
	 * they draw nothing, so left in they emit an empty row.
	 *
	 * @param Field $field The field.
	 *
	 * @return bool
	 */
	public static function is_marker( Field $field ): bool {
		return '' !== $field->type()->opens_section();
	}

	/**
	 * Draw the sections, as tabs or as collapsible regions.
	 *
	 * Tabs are handed to PanelTabs, which already emits the ARIA tabs pattern
	 * its script drives -- roving tabindex, arrow keys, the lot. Accordions
	 * use details/summary, a disclosure widget the browser implements itself,
	 * including keyboard operation and find-in-page opening the right one,
	 * neither of which a div and a click handler gets.
	 *
	 * @param array    $layout The result of split().
	 * @param callable $render Given Field[], returns the markup for a group.
	 * @param string   $scope  Something unique to this set, for ids.
	 *
	 * @return string
	 */
	public static function render( array $layout, callable $render, string $scope ): string {
		// Anything before the first marker, drawn ahead of the sections.
		$markup = (string) $render( $layout['lead'] );

		if ( 'accordion' === $layout['kind'] ) {
			// Core's own accordion: the markup the privacy guide and Site
			// Health draw, with the classes edit.css styles on every admin
			// screen -- a heading holding a button that says whether it is
			// expanded, and a panel the button controls. A <details> element
			// did the folding for free but looked like nothing else in the
			// admin; this looks like the screen beside it, chevron at the
			// end and turning as it opens.
			$markup .= '<div class="privacy-settings-accordion field-kit__accordion">';

			foreach ( $layout['sections'] as $index => $section ) {
				$open  = AccordionType::starts_open( $section['field'], $index );
				$panel = sanitize_key( $scope . '-accordion-' . $index );

				$markup .= sprintf(
					'<h4 class="privacy-settings-accordion-heading">' .
					'<button aria-expanded="%1$s" class="privacy-settings-accordion-trigger" aria-controls="%2$s" type="button">' .
					'<span class="title">%3$s</span><span class="icon"></span></button></h4>' .
					'<div id="%2$s" class="privacy-settings-accordion-panel field-kit__accordion-panel"%4$s>%5$s</div>',
					$open ? 'true' : 'false',
					esc_attr( $panel ),
					esc_html( $section['field']->label() ),
					$open ? '' : ' hidden',
					$render( $section['fields'] )
				);
			}

			return $markup . '</div>';
		}

		$panels = [];

		foreach ( $layout['sections'] as $index => $section ) {
			$panels[ 'section-' . $index ] = [
				'label'   => $section['field']->label(),
				'content' => $render( $section['fields'] ),
			];
		}

		return $markup . PanelTabs::render( self::scope_id( $scope, count( $layout['sections'] ) ), $panels );
	}

	/**
	 * An id prefix unique to one set of sections on the page.
	 *
	 * Two metaboxes each with tabs would otherwise both call their first
	 * panel `section-0`, and a duplicate id breaks aria-controls: the second
	 * tab strip drives the first one's panels.
	 *
	 * @param string $scope Something unique to the set.
	 * @param int    $count How many sections it has.
	 *
	 * @return string
	 */
	private static function scope_id( string $scope, int $count ): string {
		return Runtime::handle( 'section' ) . '-' . substr( md5( $scope . $count ), 0, 8 );
	}
}
