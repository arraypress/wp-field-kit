/**
 * Field Kit
 *
 * One script for every field type. Vanilla apart from wp.media, which has no
 * alternative.
 *
 * Two conventions run through it.
 *
 * Configuration is resolved per build, from the id WordPress stamps on the
 * script element, not from a bare global. Two plugins each bundling a
 * Strauss-prefixed copy load two copies of this file, and a shared global
 * would leave whichever localised last owning the REST URL and nonce for
 * both.
 *
 * Every control that can be dragged can also be operated from the keyboard.
 * That is not a fallback for when the script fails — a drag-only control
 * cannot be reordered without a mouse at all, and it is the half these
 * widgets usually ship without.
 */
( function () {
	'use strict';

	/**
	 * This build's configuration.
	 */
	var config = ( function () {
		var el = document.currentScript;
		var handle = el && el.id ? el.id.replace( /-js$/, '' ) : '';
		var registry = window.ArrayPressFieldKit || {};

		return registry[ handle ] || window.FieldKit || {};
	} )();

	var i18n = config.i18n || {};

	/**
	 * Translate, falling back to the key's own English.
	 *
	 * @param {string} key      Message key.
	 * @param {string} fallback English text.
	 * @return {string} Message.
	 */
	function t( key, fallback ) {
		return i18n[ key ] || fallback;
	}

	/**
	 * Announce a message to assistive technology.
	 *
	 * Writes into a field's own live region where it has one, so the message
	 * is associated with the control it describes rather than shouted from a
	 * page-level region.
	 *
	 * @param {Element} region Live region element.
	 * @param {string}  text   Message.
	 */
	function announce( region, text ) {
		if ( ! region ) {
			return;
		}

		// Clearing first forces a re-announcement when the text is unchanged,
		// which is what happens when the same action is repeated.
		region.textContent = '';
		window.setTimeout( function () {
			region.textContent = text;
		}, 50 );
	}

	/* ====================================================================
	 * Conditional logic
	 * ================================================================= */

	var Conditions = {

		/**
		 * Bind every conditional field within a root.
		 *
		 * @param {Element} root Container.
		 */
		init: function ( root ) {
			var fields = root.querySelectorAll( '[data-conditions]' );

			if ( ! fields.length ) {
				return;
			}

			fields.forEach( function ( field ) {
				Conditions.evaluate( field );
			} );

			// One delegated listener rather than one per watched input: the
			// watched field may not exist yet inside a repeater row.
			//
			// field-kit:change is the kit's own signal, used where firing a
			// native change would be read by the control that wrote the value
			// as a fresh edit. See ColorPicker.
			[ 'change', 'input', 'field-kit:change' ].forEach( function ( type ) {
				root.addEventListener( type, function () {
					fields.forEach( function ( field ) {
						Conditions.evaluate( field );
					} );
				}, true );
			} );
		},

		/**
		 * Show or hide one field.
		 *
		 * @param {Element} field Field wrapper.
		 */
		evaluate: function ( field ) {
			var conditions;

			try {
				conditions = JSON.parse( field.getAttribute( 'data-conditions' ) );
			} catch ( e ) {
				return;
			}

			// Always a list. A map has no forEach, which is exactly how the
			// predecessor died when config was passed through unnormalised.
			if ( ! Array.isArray( conditions ) ) {
				return;
			}

			var met = conditions.every( function ( condition ) {
				return Conditions.matches( condition, Conditions.valueOf( field, condition.field ) );
			} );

			// On a settings, term or user screen a field is one cell of a
			// table row, and hiding the cell's contents leaves the row's
			// header behind — the label of a field that is not there. The row
			// is only taken when this wrapper is the row's own field: a
			// conditional field nested inside a repeater must not take the
			// whole repeater's row with it.
			var row = field.closest( 'tr' );
			var target = row && row.querySelector( '.field-kit__field' ) === field ? row : field;

			target.hidden = ! met;

			// Hidden inputs are still submitted, and a disabled one is not —
			// the server deletes a field whose conditions fail, but not
			// submitting it in the first place keeps the two agreeing.
			field.querySelectorAll( 'input, select, textarea' ).forEach( function ( input ) {
				input.disabled = ! met;
			} );
		},

		/**
		 * Read the current value of a watched field.
		 *
		 * @param {Element} field Field wrapper doing the watching.
		 * @param {string}  key   Watched field key.
		 * @return {*} Current value.
		 */
		valueOf: function ( field, key ) {
			var scope = field.closest( '.field-kit__repeater-row' ) || field.ownerDocument;
			var target = scope.querySelector( '[data-field-key="' + CSS.escape( key ) + '"]' );

			if ( ! target ) {
				return null;
			}

			var checked = target.querySelectorAll( 'input[type="checkbox"]:checked, input[type="radio"]:checked' );

			if ( checked.length ) {
				return checked.length === 1 ? checked[ 0 ].value : Array.from( checked ).map( function ( i ) {
					return i.value;
				} );
			}

			if ( target.querySelector( 'input[type="checkbox"], input[type="radio"]' ) ) {
				return '';
			}

			var input = target.querySelector( 'input, select, textarea' );

			return input ? input.value : null;
		},

		/**
		 * Evaluate one condition.
		 *
		 * Comparisons are loose for the same reason they are on the server:
		 * a value read off a form is a string and config is written in PHP,
		 * so "1" has to equal 1.
		 *
		 * @param {Object} condition Condition.
		 * @param {*}      current   Current value.
		 * @return {boolean} Whether it is met.
		 */
		matches: function ( condition, current ) {
			var expected = condition.value;

			switch ( condition.operator ) {
				case '!=':
					return current != expected; // eslint-disable-line eqeqeq
				case '>':
					return parseFloat( current ) > parseFloat( expected );
				case '>=':
					return parseFloat( current ) >= parseFloat( expected );
				case '<':
					return parseFloat( current ) < parseFloat( expected );
				case '<=':
					return parseFloat( current ) <= parseFloat( expected );
				case 'in':
					return [].concat( expected ).some( function ( one ) {
						return one == current; // eslint-disable-line eqeqeq
					} );
				case 'not_in':
					return ! [].concat( expected ).some( function ( one ) {
						return one == current; // eslint-disable-line eqeqeq
					} );
				case 'contains':
					return String( current ).indexOf( String( expected ) ) !== -1;
				case 'not_contains':
					return String( current ).indexOf( String( expected ) ) === -1;
				case 'empty':
					return ! current || ! String( current ).length;
				case 'not_empty':
					return !! current && !! String( current ).length;
				default:
					return current == expected; // eslint-disable-line eqeqeq
			}
		}
	};

	/* ====================================================================
	 * Range readout
	 * ================================================================= */

	var Range = {

		/**
		 * Keep each slider's readout in step with its value.
		 *
		 * @param {Element} root Container.
		 */
		init: function ( root ) {
			root.querySelectorAll( '.field-kit__range' ).forEach( function ( wrap ) {
				var input = wrap.querySelector( 'input[type="range"]' );
				var output = wrap.querySelector( '.field-kit__range-output' );

				if ( ! input || ! output ) {
					return;
				}

				var unit = wrap.getAttribute( 'data-unit' ) || '';

				input.addEventListener( 'input', function () {
					output.textContent = input.value + unit;
				} );
			} );
		}
	};

	/* ====================================================================
	 * Toggle
	 * ================================================================= */

	var Toggle = {

		/**
		 * Keep aria-checked in step with the underlying checkbox.
		 *
		 * role="switch" makes the state aria-checked rather than the native
		 * checked state, so it has to be maintained or the control announces
		 * the wrong thing after every interaction.
		 *
		 * @param {Element} root Container.
		 */
		init: function ( root ) {
			root.addEventListener( 'change', function ( event ) {
				var toggle = event.target.closest( '.field-kit__toggle' );

				if ( toggle ) {
					toggle.setAttribute( 'aria-checked', toggle.checked ? 'true' : 'false' );
				}
			} );
		}
	};

	/* ====================================================================
	 * Clipboard
	 * ================================================================= */

	var Clipboard = {

		/**
		 * Copy a value and say whether it worked.
		 *
		 * @param {Element} root Container.
		 */
		init: function ( root ) {
			root.addEventListener( 'click', function ( event ) {
				var button = event.target.closest( '.field-kit__clipboard-copy' );

				if ( ! button ) {
					return;
				}

				var wrap = button.closest( '.field-kit__clipboard' );
				var input = wrap.querySelector( '.field-kit__clipboard-value' );
				var status = wrap.querySelector( '.field-kit__clipboard-status' );
				var success = wrap.querySelector( '.field-kit__clipboard-success' );

				Clipboard.copy( input.value ).then( function ( ok ) {
					announce( status, ok ? t( 'copied', 'Copied to the clipboard.' ) : t( 'copyFailed', 'Could not copy.' ) );

					if ( ! success || ! ok ) {
						return;
					}

					// Shown, then hidden again after a moment — core does the
					// same in the media modal. aria-hidden stays on it: the
					// live region above is what announces this, and having
					// both would say it twice.
					success.classList.remove( 'hidden' );

					window.clearTimeout( success.fkTimer );

					success.fkTimer = window.setTimeout( function () {
						success.classList.add( 'hidden' );
					}, 3000 );
				} );
			} );
		},

		/**
		 * Copy text, falling back where the async API is unavailable.
		 *
		 * navigator.clipboard needs a secure context, which a local admin
		 * over plain http is not.
		 *
		 * @param {string} text Text to copy.
		 * @return {Promise<boolean>} Whether it worked.
		 */
		copy: function ( text ) {
			if ( navigator.clipboard && window.isSecureContext ) {
				return navigator.clipboard.writeText( text ).then( function () {
					return true;
				} ).catch( function () {
					return false;
				} );
			}

			var area = document.createElement( 'textarea' );
			area.value = text;
			area.setAttribute( 'readonly', '' );
			area.style.position = 'fixed';
			area.style.opacity = '0';
			document.body.appendChild( area );
			area.select();

			var ok = false;

			try {
				ok = document.execCommand( 'copy' );
			} catch ( e ) {
				ok = false;
			}

			document.body.removeChild( area );

			return Promise.resolve( ok );
		}
	};

	/**
	 * A text field with a button that fills it in.
	 *
	 * The alphabets are here rather than sent from the server, and the button
	 * names one rather than carrying its characters: a caller cannot put
	 * arbitrary text into a page and have this draw from it.
	 *
	 * Ambiguity is why these are not simply "letters and numbers" — a code
	 * gets read off a screen and typed into a box, and O/0 and I/1/l are
	 * where that goes wrong.
	 */
	var CodeGenerator = {

		alphabets: {
			alphanumeric_upper: 'ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789',
			alphanumeric: 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789',
			alpha_upper: 'ABCDEFGHIJKLMNOPQRSTUVWXYZ',
			hex: '0123456789ABCDEF',
			numeric: '0123456789'
		},

		/**
		 * Wire the generate buttons.
		 *
		 * @param {Element} root Container.
		 */
		init: function ( root ) {
			root.querySelectorAll( '.field-kit__code-generate' ).forEach( function ( button ) {
				// Marked, because init() is called again for every repeater
				// row and every flyout that opens, and a twice-bound button
				// generates two codes and keeps the second.
				if ( button.dataset.fkBound ) {
					return;
				}

				button.dataset.fkBound = '1';

				button.addEventListener( 'click', function () {
					var wrap = button.closest( '.field-kit__code' );
					var input = wrap && wrap.querySelector( '.field-kit__code-value' );

					if ( ! input ) {
						return;
					}

					input.value = CodeGenerator.build( button.dataset );

					// So a conditional field watching this one, or anything
					// else listening, hears about it. Setting .value fires
					// nothing.
					input.dispatchEvent( new Event( 'input', { bubbles: true } ) );
					input.dispatchEvent( new Event( 'change', { bubbles: true } ) );

					input.focus();
				} );
			} );
		},

		/**
		 * Build one code from a button's settings.
		 *
		 * @param {DOMStringMap} settings The button's data attributes.
		 * @return {string} The code.
		 */
		build: function ( settings ) {
			var alphabet = CodeGenerator.alphabets[ settings.format ] || CodeGenerator.alphabets.alphanumeric_upper;
			var length = parseInt( settings.length, 10 ) || 8;
			var segment = parseInt( settings.segmentLength, 10 ) || 0;
			var separator = settings.separator || '';
			var code = '';

			// crypto rather than Math.random: not because this has to be
			// unpredictable — it is a discount code, and anything that must
			// be should not be generated in a browser — but because
			// Math.random collides sooner than people expect at these
			// lengths, and a duplicate code is a support ticket.
			var bytes = new Uint32Array( length );
			window.crypto.getRandomValues( bytes );

			for ( var i = 0; i < length; i++ ) {
				code += alphabet.charAt( bytes[ i ] % alphabet.length );
			}

			if ( separator && segment > 0 ) {
				code = code.match( new RegExp( '.{1,' + segment + '}', 'g' ) ).join( separator );
			}

			return ( settings.prefix || '' ) + code;
		}
	};

	/**
	 * A URL that resolves to an embed, previewed as it is typed.
	 *
	 * Through core's own `oembed/1.0/proxy`, which is what the block editor
	 * uses: it fetches only from WordPress's provider allowlist and is gated
	 * on `edit_posts`, so there is no second endpoint here and no way to aim
	 * it at an arbitrary host.
	 *
	 * The request is debounced and the last one wins. Typing a URL fires an
	 * input event per character, and without a sequence number a slow lookup
	 * for half a URL can land after the fast one for the whole of it and
	 * overwrite the right answer with the wrong one.
	 */
	var Oembed = {

		/**
		 * Wire the oembed fields.
		 *
		 * @param {Element} root Container.
		 */
		init: function ( root ) {
			root.querySelectorAll( '.field-kit__oembed' ).forEach( function ( wrap ) {
				if ( wrap.dataset.fkBound ) {
					return;
				}

				wrap.dataset.fkBound = '1';

				var input = wrap.querySelector( '.field-kit__oembed-input' );
				var preview = wrap.querySelector( '.field-kit__oembed-preview' );

				if ( ! input || ! preview ) {
					return;
				}

				// What is on screen already, so a field that has not been
				// touched is not re-fetched on every keystroke elsewhere.
				var last = input.value.trim();
				var timer = null;
				var sequence = 0;

				input.addEventListener( 'input', function () {
					window.clearTimeout( timer );

					timer = window.setTimeout( function () {
						var url = input.value.trim();

						if ( url === last ) {
							return;
						}

						last = url;

						if ( '' === url ) {
							preview.innerHTML = '';

							return;
						}

						Oembed.resolve( preview, url, ++sequence, function () {
							return sequence;
						} );
					}, 500 );
				} );
			} );
		},

		/**
		 * Fetch one URL and render whatever came back.
		 *
		 * @param {Element}  preview The preview region.
		 * @param {string}   url     The URL to resolve.
		 * @param {number}   ticket  This request's number.
		 * @param {Function} current The latest request's number.
		 */
		resolve: function ( preview, url, ticket, current ) {
			preview.textContent = t( 'embedResolving', 'Looking for an embed…' );

			var endpoint = config.oembedUrl;

			if ( ! endpoint ) {
				return;
			}

			var query = endpoint + ( endpoint.indexOf( '?' ) === -1 ? '?' : '&' ) +
				'url=' + encodeURIComponent( url ) + '&maxwidth=600';

			window.fetch( query, {
				credentials: 'same-origin',
				headers: { 'X-WP-Nonce': config.restNonce }
			} )
				.then( function ( response ) {
					return response.ok ? response.json() : null;
				} )
				.catch( function () {
					return null;
				} )
				.then( function ( data ) {
					// A later keystroke has already asked for something else.
					if ( ticket !== current() ) {
						return;
					}

					Oembed.render( preview, data );
				} );
		},

		/**
		 * Show an embed, a card, or a failure.
		 *
		 * @param {Element}     preview The preview region.
		 * @param {Object|null} data    The oEmbed response.
		 */
		render: function ( preview, data ) {
			if ( ! data || ! data.html ) {
				preview.textContent = t( 'embedFailed', 'That URL could not be embedded.' );

				return;
			}

			// Some providers answer with a script rather than an iframe —
			// Twitter and Reddit among them — and innerHTML never executes
			// one. Rendering it would leave an empty box that looks like a
			// failure, so those get a card saying what was found instead.
			if ( data.html.indexOf( '<iframe' ) === -1 ) {
				preview.innerHTML = '';
				preview.appendChild( Oembed.card( data ) );

				return;
			}

			preview.innerHTML = data.html;
		},

		/**
		 * What was found, for a provider that cannot be shown inline.
		 *
		 * @param {Object} data The oEmbed response.
		 * @return {Element} The card.
		 */
		card: function ( data ) {
			var card = document.createElement( 'div' );
			card.className = 'field-kit__oembed-card';

			if ( data.thumbnail_url ) {
				var image = document.createElement( 'img' );
				image.src = data.thumbnail_url;
				image.alt = '';
				card.appendChild( image );
			}

			var body = document.createElement( 'div' );

			var title = document.createElement( 'strong' );
			title.textContent = data.title || data.provider_name || '';
			body.appendChild( title );

			var note = document.createElement( 'p' );
			note.className = 'description';
			note.textContent = t( 'embedOnSave', 'This will render once saved.' );
			body.appendChild( note );

			card.appendChild( body );

			return card;
		}
	};

	window.ArrayPressFieldKitModules = { Conditions: Conditions, Range: Range, Toggle: Toggle, Clipboard: Clipboard, CodeGenerator: CodeGenerator, Oembed: Oembed, announce: announce, t: t, config: config };
} )();

/**
 * Field Kit — searchable relational fields.
 *
 * Implements the ARIA combobox pattern against the real <select> the server
 * rendered, rather than replacing it. The select stays the source of truth,
 * so the field still submits correctly if this never runs, and there is a
 * genuine element to mirror instead of state invented in script.
 */
( function () {
	'use strict';

	var kit = window.ArrayPressFieldKitModules;
	var t = kit.t;
	var config = kit.config;

	var Combobox = {

		/**
		 * Put the select's options in the order its chips are now in.
		 *
		 * The chips are only a picture of what is chosen; the select is what
		 * gets posted, and a multiple select posts in option order rather than
		 * in the order things were picked. Dragging a chip therefore has to
		 * move the option too, or the new order is lost on save.
		 *
		 * Unselected options are left where they are: they are not part of the
		 * value and moving them would churn the DOM for nothing.
		 *
		 * @param {Element} chips The chip list.
		 */
		/**
		 * A move control for a chip.
		 *
		 * Reorder.bindButtons() finds these by data-direction, so a chip gets
		 * keyboard reordering for free and the drag path is the same code.
		 *
		 * @param {string} direction 'up' or 'down'.
		 * @param {string} label     The chip's text, for the accessible name.
		 * @return {HTMLElement} The button.
		 */
		moveButton: function ( direction, label ) {
			var button = document.createElement( 'button' );

			button.type = 'button';
			button.className = 'field-kit__combobox-chip-move';
			button.dataset.direction = direction;
			button.setAttribute(
				'aria-label',
				t( 'up' === direction ? 'moveUp' : 'moveDown', 'up' === direction ? 'Move up' : 'Move down' ) + ': ' + label
			);
			button.innerHTML = '<span class="dashicons dashicons-arrow-' + direction + '-alt2" aria-hidden="true"></span>';

			return button;
		},

		syncOrder: function ( chips ) {
			var wrap = chips.closest( '.field-kit__combobox' ) || chips.parentElement;
			var select = wrap ? wrap.querySelector( 'select' ) : null;

			if ( ! select ) {
				return;
			}

			var order = Array.prototype.slice.call(
				chips.querySelectorAll( '.field-kit__combobox-chip' )
			).map( function ( chip ) {
				return chip.dataset.value;
			} );

			order.forEach( function ( value ) {
				var option = Array.prototype.slice.call( select.options ).filter( function ( candidate ) {
					return candidate.value === value;
				} )[ 0 ];

				if ( option ) {
					select.appendChild( option );
				}
			} );

			select.dispatchEvent( new Event( 'change', { bubbles: true } ) );
		},

		/**
		 * Upgrade every enhanced select within a root.
		 *
		 * @param {Element} root Container.
		 */
		init: function ( root ) {
			// Two kinds, one control. A relational field searches a server;
			// an enhanced select filters the options it was already given.
			// The enhanced ones used to be rendered with the class and no
			// behaviour behind it at all, so a `select2` field was a plain
			// dropdown with a promise attached.
			var selector = 'select.field-kit__relational, select.field-kit__select--enhanced';

			root.querySelectorAll( selector ).forEach( function ( select ) {
				if ( select.dataset.fkBound ) {
					return;
				}

				select.dataset.fkBound = '1';
				Combobox.build( select );
			} );
		},

		/**
		 * Build the combobox around one select.
		 *
		 * @param {HTMLSelectElement} select The select.
		 */
		build: function ( select ) {
			var wrap = document.createElement( 'div' );
			wrap.className = 'field-kit__combobox';

			var input = document.createElement( 'input' );
			var listId = select.id + '__listbox';

			input.type = 'text';
			input.className = 'regular-text field-kit__combobox-input';
			input.id = select.id + '__combobox';
			input.setAttribute( 'role', 'combobox' );
			input.setAttribute( 'aria-expanded', 'false' );
			input.setAttribute( 'aria-controls', listId );
			input.setAttribute( 'aria-autocomplete', 'list' );
			input.setAttribute( 'autocomplete', 'off' );

			// The visible control takes over the label and the description the
			// server associated with the select, or the combobox would be
			// announced as unlabelled while the labelled element is hidden.
			var label = select.ownerDocument.querySelector( 'label[for="' + CSS.escape( select.id ) + '"]' );

			if ( label ) {
				label.setAttribute( 'for', input.id );
			}

			if ( select.hasAttribute( 'aria-describedby' ) ) {
				input.setAttribute( 'aria-describedby', select.getAttribute( 'aria-describedby' ) );
			}

			if ( select.hasAttribute( 'aria-required' ) ) {
				input.setAttribute( 'aria-required', select.getAttribute( 'aria-required' ) );
			}

			var list = document.createElement( 'ul' );
			list.className = 'field-kit__combobox-list';
			list.id = listId;
			list.setAttribute( 'role', 'listbox' );
			list.hidden = true;

			var status = document.createElement( 'span' );
			status.className = 'screen-reader-text';
			status.setAttribute( 'aria-live', 'polite' );

			var multiple = select.multiple;

			// A multiple select shows what is chosen as removable chips above
			// the input, the way every tag control does — the input is left
			// free to keep searching rather than holding one of the values.
			var chips = null;

			if ( multiple ) {
				chips = document.createElement( 'ul' );
				chips.className = 'field-kit__combobox-chips';

				// Set here rather than when the chips are first drawn:
				// Reorder.init() selects on this attribute and may have run
				// already by then.
				if ( 'true' === select.getAttribute( 'data-sortable' ) ) {
					chips.setAttribute( 'data-sortable', 'true' );
				}
			}

			var selected = multiple ? null : select.options[ select.selectedIndex ];
			input.value = selected && selected.value ? selected.text : '';

			// A single control that is allowed to hold nothing gets a way
			// back to nothing. A multiple one does not need it — each chip
			// carries its own remove — and a required one has nothing valid
			// to be cleared to.
			var clearable = ! multiple && 'true' !== select.getAttribute( 'aria-required' ) && ! select.required;
			var clear = null;

			if ( clearable ) {
				// The wrapper says so, so the input can hold room for the
				// button whether or not it is showing — otherwise the text
				// jumps sideways the moment something is chosen.
				wrap.classList.add( 'field-kit__combobox--clearable' );

				clear = document.createElement( 'button' );
				clear.type = 'button';
				clear.className = 'field-kit__combobox-clear';
				clear.innerHTML = '<span class="dashicons dashicons-no-alt" aria-hidden="true"></span>';
			}

			// A closed dropdown with nothing chosen must still say what it is
			// for. The select's own empty option carries that text — it is
			// what a native select shows — so the placeholder comes from
			// there rather than from a second piece of configuration.
			var empty = Array.prototype.slice.call( select.options ).filter( function ( option ) {
				return '' === option.value;
			} )[ 0 ];

			input.placeholder = select.dataset.placeholder || ( empty ? empty.text : '' );

			// The select carries the value and stays in the form; it is
			// removed from the tab order rather than hidden, so it is not
			// reachable twice.
			select.setAttribute( 'tabindex', '-1' );
			select.setAttribute( 'aria-hidden', 'true' );
			select.classList.add( 'screen-reader-text' );

			select.parentNode.insertBefore( wrap, select );

			if ( chips ) {
				wrap.appendChild( chips );
			}

			wrap.appendChild( input );

			if ( clear ) {
				wrap.appendChild( clear );
			}

			wrap.appendChild( select );
			wrap.appendChild( list );
			wrap.appendChild( status );

			Combobox.bind( select, input, list, status, chips, clear );
		},

		/**
		 * Wire the behaviour.
		 *
		 * @param {HTMLSelectElement} select The select.
		 * @param {HTMLInputElement}  input  The visible input.
		 * @param {HTMLElement}       list   The listbox.
		 * @param {HTMLElement}       status The live region.
		 * @param {HTMLElement|null}  chips  Chip list, for a multiple select.
		 */
		bind: function ( select, input, list, status, chips, clear ) {
			var active = -1;
			var results = [];
			var timer = null;
			var remote = !! select.dataset.searchEndpoint;
			var multiple = select.multiple;

			// Whether a value the list does not offer can be added anyway.
			// This is what makes a control a tag input rather than a picker:
			// the same combobox, allowed to invent an option.
			var creatable = 'true' === select.dataset.creatable;

			// A local list needs no minimum: there is nothing to spare by
			// waiting, and an empty query means "show me everything".
			var minChars = remote ? parseInt( select.dataset.minChars || '0', 10 ) : 0;

			/**
			 * Every option the select carries, as search results.
			 *
			 * Read fresh each time rather than cached at build: a select's
			 * options can be rewritten by conditional logic or by another
			 * field, and a stale copy would offer choices that no longer
			 * exist.
			 *
			 * @return {Array} Results.
			 */
			function options() {
				return Array.prototype.slice.call( select.options )
					.filter( function ( option ) {
						return '' !== option.value;
					} )
					.map( function ( option ) {
						return { id: option.value, text: option.text };
					} );
			}

			/**
			 * Filter the local options by a term.
			 *
			 * @param {string} term Search term.
			 */
			function filter( term ) {
				var needle = term.toLowerCase().trim();

				results = options().filter( function ( result ) {
					return '' === needle || result.text.toLowerCase().indexOf( needle ) > -1;
				} );

				render();
			}

			/**
			 * Put the list under the input, at the input's width.
			 *
			 * Measured rather than inherited. The list used to be stretched
			 * across the wrapper with left:0;right:0, so whenever anything
			 * made the wrapper wider than the input — which core does on a
			 * term screen — the list hung out past the control it belongs to.
			 * The input is the only thing that knows how wide the control is.
			 */
			function place() {
				list.style.width = input.offsetWidth + 'px';
				list.style.left = input.offsetLeft + 'px';
				list.style.top = ( input.offsetTop + input.offsetHeight ) + 'px';
			}

			function close() {
				list.hidden = true;
				list.innerHTML = '';
				input.setAttribute( 'aria-expanded', 'false' );
				input.removeAttribute( 'aria-activedescendant' );
				active = -1;
			}

			function highlight( index ) {
				var options = list.querySelectorAll( '[role="option"]' );

				if ( ! options.length ) {
					return;
				}

				active = ( index + options.length ) % options.length;

				options.forEach( function ( option, i ) {
					option.setAttribute( 'aria-selected', i === active ? 'true' : 'false' );
				} );

				// Focus stays in the input; the active option is pointed at
				// rather than focused, which is what aria-activedescendant is
				// for and why the highlight cannot be a :focus style.
				input.setAttribute( 'aria-activedescendant', options[ active ].id );
				options[ active ].scrollIntoView( { block: 'nearest' } );
			}

			/**
			 * The option carrying a value, creating it if it is allowed to.
			 *
			 * @param {string} value The value.
			 * @param {string} text  Its label.
			 * @return {HTMLOptionElement|null} The option.
			 */
			function optionFor( value, text ) {
				var existing = Array.prototype.slice.call( select.options ).filter(
					function ( option ) {
						return option.value === value;
					}
				)[ 0 ];

				if ( existing ) {
					return existing;
				}

				var option = document.createElement( 'option' );
				option.value = value;
				option.text = text;
				select.appendChild( option );

				return option;
			}

			/**
			 * Draw the chosen values as removable chips.
			 */
			function renderChips() {
				if ( ! chips ) {
					return;
				}

				chips.innerHTML = '';

				var sortable = 'true' === select.getAttribute( 'data-sortable' );

				Array.prototype.slice.call( select.selectedOptions ).forEach( function ( option ) {
					var chip = document.createElement( 'li' );
					chip.className = 'field-kit__combobox-chip';

					// syncOrder() matches chips back to options by value.
					chip.dataset.value = option.value;

					if ( sortable ) {
						chip.appendChild( Combobox.moveButton( 'up', option.text ) );
						chip.appendChild( Combobox.moveButton( 'down', option.text ) );
					}

					var label = document.createElement( 'span' );
					label.textContent = option.text;

					var remove = document.createElement( 'button' );
					remove.type = 'button';
					remove.className = 'field-kit__combobox-chip-remove';

					// Named, not just an ×: a row of identical "Remove"
					// buttons is a row nobody can tell apart by ear.
					remove.setAttribute(
						'aria-label',
						t( 'removeItem', 'Remove' ) + ': ' + option.text
					);
					remove.innerHTML = '<span class="dashicons dashicons-no-alt" aria-hidden="true"></span>';
					remove.addEventListener( 'click', function () {
						option.selected = false;

						// An invented option has nothing to go back to, so it
						// is removed rather than left in the list unselected.
						if ( 'true' === option.dataset.fkCreated ) {
							option.remove();
						}

						renderChips();
						select.dispatchEvent( new Event( 'change', { bubbles: true } ) );
						input.focus();
					} );

					chip.appendChild( label );
					chip.appendChild( remove );
					chips.appendChild( chip );
				} );
			}

			/**
			 * Take a value, however it was arrived at.
			 *
			 * @param {string}  value   The value.
			 * @param {string}  text    Its label.
			 * @param {boolean} created Whether it was invented rather than chosen.
			 */
			function take( value, text, created ) {
				if ( multiple ) {
					// A limit that is only enforced on save is a limit the
					// editor discovers after losing their work.
					var max = parseInt( select.getAttribute( 'data-max' ) || '0', 10 );

					if ( max > 0 && select.selectedOptions.length >= max ) {
						kit.announce(
							status,
							t( 'maxReached', 'You can choose at most %d.' ).replace( '%d', String( max ) )
						);

						return;
					}

					var option = optionFor( value, text );

					if ( created ) {
						option.dataset.fkCreated = 'true';
					}

					option.selected = true;
					input.value = '';
					renderChips();
				} else if ( remote && ! created ) {
					// A searched result is not in the select yet, and the
					// previous one is no longer a valid choice.
					select.innerHTML = '';

					var single = document.createElement( 'option' );
					single.value = value;
					single.text = text;
					single.selected = true;
					select.appendChild( single );
					input.value = text;
				} else {
					optionFor( value, text ).selected = true;
					select.value = value;
					input.value = text;
				}

				select.dispatchEvent( new Event( 'change', { bubbles: true } ) );
				syncClear();
				close();
			}

			/**
			 * Show the clear button only when there is something to clear.
			 */
			function syncClear() {
				if ( clear ) {
					clear.hidden = '' === select.value;
				}
			}

			/**
			 * Put the control back to holding nothing.
			 *
			 * Emptying the input is not enough: the input is a search box and
			 * the select is what posts, so the select has to be emptied too —
			 * and for a remote control the option has to go, since it was
			 * only ever there to carry the current choice.
			 */
			function clearValue() {
				if ( remote ) {
					select.innerHTML = '';
				} else {
					select.value = '';
				}

				input.value = '';
				syncClear();
				select.dispatchEvent( new Event( 'change', { bubbles: true } ) );

				// Focus goes back to the control rather than being dropped on
				// the body, since the button that had it has just gone.
				input.focus();
			}

			if ( clear ) {
				syncClear();

				clear.setAttribute(
					'aria-label',
					t( 'clearSelection', 'Clear the selection' )
				);

				clear.addEventListener( 'click', function ( event ) {
					event.preventDefault();
					clearValue();
				} );
			}

			function choose( index ) {
				var result = results[ index ];

				if ( ! result ) {
					return;
				}

				take( String( result.id ), result.text, false );
			}

			/**
			 * Add whatever is typed, for a control allowed to invent values.
			 */
			function create() {
				var value = input.value.trim();

				if ( '' === value ) {
					return;
				}

				take( value, value, true );
			}

			function render() {
				list.innerHTML = '';

				// A creatable control offers what was typed as the first row,
				// so the pointer has the same way in as the keyboard.
				var typed = input.value.trim();

				if ( creatable && '' !== typed && ! results.some( function ( result ) {
					return String( result.text ).toLowerCase() === typed.toLowerCase();
				} ) ) {
					results = [ {
						id: typed,
						text: typed,
						create: true
					} ].concat( results );
				}

				if ( ! results.length ) {
					var empty = document.createElement( 'li' );
					empty.className = 'field-kit__combobox-option';
					empty.textContent = t( 'noResults', 'No results found.' );
					list.appendChild( empty );
					place();
					list.hidden = false;
					input.setAttribute( 'aria-expanded', 'true' );
					kit.announce( status, t( 'noResults', 'No results found.' ) );

					return;
				}

				results.forEach( function ( result, i ) {
					var option = document.createElement( 'li' );
					option.className = 'field-kit__combobox-option';
					option.id = list.id + '__' + i;
					option.setAttribute( 'role', 'option' );
					option.setAttribute( 'aria-selected', 'false' );
					option.textContent = result.create
						? t( 'addItem', 'Add' ) + ' “' + result.text + '”'
						: result.text;

					option.addEventListener( 'mousedown', function ( event ) {
						event.preventDefault();

						if ( result.create ) {
							take( String( result.id ), String( result.text ), true );

							return;
						}

						choose( i );
					} );
					list.appendChild( option );
				} );

				place();
				list.hidden = false;
				input.setAttribute( 'aria-expanded', 'true' );

				kit.announce(
					status,
					results.length + ' ' + t( 'resultsAvailable', 'results available.' )
				);
			}

			function search( term ) {
				var url = new URL( select.dataset.searchEndpoint, window.location.origin );
				var args = select.dataset.searchArgs ? JSON.parse( select.dataset.searchArgs ) : {};

				url.searchParams.set( 'source', select.dataset.searchSource );
				url.searchParams.set( 'q', term );

				Object.keys( args ).forEach( function ( key ) {
					url.searchParams.set( 'args[' + key + ']', args[ key ] );
				} );

				fetch( url, {
					credentials: 'same-origin',
					headers: { 'X-WP-Nonce': select.dataset.searchNonce || config.restNonce || '' }
				} ).then( function ( response ) {
					return response.ok ? response.json() : { results: [] };
				} ).then( function ( data ) {
					results = data.results || [];
					render();
				} ).catch( function () {
					results = [];
					render();
				} );
			}

			/**
			 * Show results for the current input, from wherever they come.
			 *
			 * @param {string} term Search term.
			 */
			function open( term ) {
				if ( remote ) {
					search( term );

					return;
				}

				filter( term );
			}

			input.addEventListener( 'input', function () {
				window.clearTimeout( timer );

				if ( input.value.length < minChars ) {
					close();

					return;
				}

				if ( ! remote ) {
					// Nothing to spare by waiting on a list already in the page.
					filter( input.value );

					return;
				}

				// Debounced, or every keystroke is a query.
				timer = window.setTimeout( function () {
					search( input.value );
				}, 250 );
			} );

			// Clicking anywhere in the control opens it, which is what a
			// dropdown does. A local list opens showing everything; a remote
			// one waits for a term it can actually search on.
			function openAll() {
				if ( ! list.hidden ) {
					close();

					return;
				}

				input.focus();

				if ( ! remote ) {
					filter( '' );

					return;
				}

				if ( input.value.length >= minChars ) {
					open( input.value );
				}
			}

			renderChips();

			// Clicking the control opens it, which is what a dropdown does.
			// The arrow is painted on the input rather than being an element
			// of its own, so there is nothing else to click.
			input.addEventListener( 'mousedown', function () {
				if ( ! list.hidden ) {
					return;
				}

				// Deferred, so the click that opened it does not also place a
				// caret and immediately close it again.
				window.setTimeout( openAll, 0 );
			} );

			input.addEventListener( 'keydown', function ( event ) {
				switch ( event.key ) {
					case 'ArrowDown':
						event.preventDefault();

						if ( list.hidden ) {
							open( remote ? input.value : '' );
						} else {
							highlight( active + 1 );
						}

						break;
					case 'ArrowUp':
						event.preventDefault();
						highlight( active - 1 );
						break;
					case 'Home':
						if ( ! list.hidden ) {
							event.preventDefault();
							highlight( 0 );
						}
						break;
					case 'End':
						if ( ! list.hidden ) {
							event.preventDefault();
							highlight( results.length - 1 );
						}
						break;
					case 'Enter':
						if ( ! list.hidden && active > -1 ) {
							event.preventDefault();
							choose( active );
						} else if ( creatable && '' !== input.value.trim() ) {
							// Enter on typed text that matched nothing is how
							// every tag control in existence adds a value, and
							// preventing the default keeps it from submitting
							// the form instead.
							event.preventDefault();
							create();
						}
						break;
					case 'Escape':
						close();
						break;
					default:
						break;
				}
			} );

			input.addEventListener( 'blur', function () {
				// Deferred so a mousedown on an option is not lost to the blur.
				window.setTimeout( close, 150 );
			} );
		}
	};

	window.ArrayPressFieldKitModules.Combobox = Combobox;
} )();

/**
 * Field Kit — reorderable lists and repeaters.
 *
 * Dragging is added on top of move buttons that already work, never instead
 * of them. Every reorder goes through the same move() call, so the keyboard
 * path and the drag path cannot drift apart — and the keyboard path is the
 * one that gets exercised by everyone, because the buttons are always there.
 */
( function () {
	'use strict';

	var kit = window.ArrayPressFieldKitModules;
	var t = kit.t;

	var Reorder = {

		/**
		 * Bind every reorderable list within a root.
		 *
		 * @param {Element} root Container.
		 */
		init: function ( root ) {
			root.querySelectorAll( '.field-kit__gallery-items, .field-kit__sortable, .field-kit__repeater-rows, .field-kit__combobox-chips[data-sortable]' ).forEach( function ( list ) {
				if ( list.dataset.fkBound ) {
					return;
				}

				list.dataset.fkBound = '1';
				Reorder.bindButtons( list );
				Reorder.bindDrag( list );
			} );
		},

		/**
		 * The item selector for a given list.
		 *
		 * @param {Element} list The list.
		 * @return {string} Selector.
		 */
		itemSelector: function ( list ) {
			if ( list.classList.contains( 'field-kit__gallery-items' ) ) {
				return '.field-kit__gallery-item';
			}

			if ( list.classList.contains( 'field-kit__sortable' ) ) {
				return '.field-kit__sortable-item';
			}

			if ( list.classList.contains( 'field-kit__combobox-chips' ) ) {
				return '.field-kit__combobox-chip';
			}

			return '.field-kit__repeater-row';
		},

		/**
		 * Whether the item's neighbours sit beside it rather than under it.
		 *
		 * Measured rather than configured, so a grid that wraps to a single
		 * column on a narrow screen answers vertically without being told —
		 * and a repeater whose `direction` was changed does too.
		 *
		 * @param {Element} item An item in the list.
		 * @return {boolean} Whether the list runs across at this point.
		 */
		isHorizontal: function ( item ) {
			var neighbour = item.nextElementSibling || item.previousElementSibling;

			if ( ! neighbour ) {
				return false;
			}

			// Four pixels of slack: two elements in a row rarely share a top
			// to the pixel once borders and margins are involved.
			return Math.abs(
				neighbour.getBoundingClientRect().top - item.getBoundingClientRect().top
			) < 4;
		},

		/**
		 * Wire the move buttons.
		 *
		 * @param {Element} list The list.
		 */
		bindButtons: function ( list ) {
			list.addEventListener( 'click', function ( event ) {
				var button = event.target.closest( '[data-direction], [data-action="move-up"], [data-action="move-down"]' );

				if ( ! button ) {
					return;
				}

				event.preventDefault();

				var direction = button.dataset.direction || ( button.dataset.action === 'move-up' ? 'up' : 'down' );
				var item = button.closest( Reorder.itemSelector( list ) );

				if ( ! item ) {
					return;
				}

				Reorder.move( list, item, direction );

				// Focus would otherwise be lost when the button it sits on is
				// re-rendered or becomes disabled at the end of the list.
				Reorder.restoreFocus( list, item, direction );
			} );
		},

		/**
		 * Move one item.
		 *
		 * @param {Element} list      The list.
		 * @param {Element} item      The item.
		 * @param {string}  direction Either "up" or "down".
		 */
		move: function ( list, item, direction ) {
			var sibling = direction === 'up' ? item.previousElementSibling : item.nextElementSibling;

			if ( ! sibling ) {
				return;
			}

			if ( direction === 'up' ) {
				list.insertBefore( item, sibling );
			} else {
				list.insertBefore( sibling, item );
			}

			Reorder.sync( list );
		},

		/**
		 * Put focus back on the equivalent control after a move.
		 *
		 * @param {Element} list      The list.
		 * @param {Element} item      The moved item.
		 * @param {string}  direction The direction moved.
		 */
		restoreFocus: function ( list, item, direction ) {
			var selector = '[data-direction="' + direction + '"], [data-action="move-' + direction + '"]';
			var button = item.querySelector( selector );

			if ( button && ! button.disabled ) {
				button.focus();

				return;
			}

			// At the end of the list that direction is gone, so focus moves to
			// the opposite control rather than being dropped onto the body.
			var opposite = direction === 'up' ? 'down' : 'up';
			var fallback = item.querySelector( '[data-direction="' + opposite + '"], [data-action="move-' + opposite + '"]' );

			if ( fallback ) {
				fallback.focus();
			}
		},

		/**
		 * Update everything that depends on order.
		 *
		 * @param {Element} list The list.
		 */
		sync: function ( list ) {
			var items = list.querySelectorAll( Reorder.itemSelector( list ) );
			var total = items.length;

			items.forEach( function ( item, index ) {
				// Disable the moves that are no longer possible, so a keyboard
				// user is not sent to a control that does nothing.
				var up = item.querySelector( '[data-direction="up"], [data-action="move-up"]' );
				var down = item.querySelector( '[data-direction="down"], [data-action="move-down"]' );

				if ( up ) {
					up.disabled = index === 0;
				}

				if ( down ) {
					down.disabled = index === total - 1;
				}

				var position = item.querySelector( '.field-kit__gallery-position, .field-kit__repeater-position' );

				if ( position ) {
					position.textContent = t( 'position', 'Item' ) + ' ' + ( index + 1 ) + ' / ' + total;
				}

				item.dataset.index = String( index );
			} );

			if ( list.classList.contains( 'field-kit__combobox-chips' ) ) {
				Combobox.syncOrder( list );
			}

			if ( list.classList.contains( 'field-kit__gallery-items' ) ) {
				Gallery.sync( list );
			}

			if ( list.classList.contains( 'field-kit__repeater-rows' ) ) {
				// Looked up rather than closed over: Repeater is defined in a
				// later block, so a direct reference would be a ReferenceError
				// the first time a gallery or sortable is reordered.
				var repeater = window.ArrayPressFieldKitModules.Repeater;

				if ( repeater ) {
					repeater.reindex( list );
				}
			}

			list.dataset.empty = total ? 'false' : 'true';
		},

		/**
		 * Add dragging on top of the buttons.
		 *
		 * @param {Element} list The list.
		 */
		bindDrag: function ( list ) {
			var selector = Reorder.itemSelector( list );
			var dragging = null;

			list.addEventListener( 'pointerdown', function ( event ) {
				// Never start a drag from a control: it would swallow the
				// click that operates it.
				if ( event.target.closest( 'button, input, select, textarea, a' ) ) {
					return;
				}

				var item = event.target.closest( selector );

				if ( item ) {
					item.draggable = true;
				}
			} );

			// A drag that ends anywhere but on the list still has to put the
			// item down, or it stays marked draggable and the next pointerdown
			// on a control starts a drag instead of clicking it.
			list.addEventListener( 'pointerup', function () {
				list.querySelectorAll( '[draggable="true"]' ).forEach( function ( item ) {
					item.draggable = false;
				} );
			} );

			list.addEventListener( 'dragstart', function ( event ) {
				dragging = event.target.closest( selector );

				if ( dragging ) {
					dragging.classList.add( 'is-dragging' );
					event.dataTransfer.effectAllowed = 'move';
				}
			} );

			list.addEventListener( 'dragover', function ( event ) {
				if ( ! dragging ) {
					return;
				}

				event.preventDefault();

				var over = event.target.closest( selector );

				if ( ! over || over === dragging ) {
					return;
				}

				// Past the halfway line of the item under the pointer, the
				// dragged one belongs after it rather than before — but which
				// halfway line depends on how the list is actually laid out.
				//
				// A gallery is a grid, so its tiles sit side by side and the
				// question is left-or-right. Asking top-or-bottom there means
				// two tiles in the same row give the same answer over most of
				// their area, which is why a tile could not be dropped into
				// the first position at all.
				var box = over.getBoundingClientRect();

				var past = Reorder.isHorizontal( over )
					? ( event.clientX - box.left ) / box.width > 0.5
					: ( event.clientY - box.top ) / box.height > 0.5;

				var reference = past ? over.nextElementSibling : over;

				// Already exactly there. dragover fires continuously — many
				// times per second — and the list has been reordered
				// underneath the pointer since the last one, so without this
				// a single crossing keeps re-inserting and the item travels
				// two or three places at once instead of one.
				if ( reference === dragging || dragging.nextElementSibling === reference ) {
					return;
				}

				list.insertBefore( dragging, reference );
			} );

			[ 'dragend', 'drop' ].forEach( function ( type ) {
				list.addEventListener( type, function ( event ) {
					event.preventDefault();

					if ( dragging ) {
						dragging.classList.remove( 'is-dragging' );
						dragging.draggable = false;
						dragging = null;
						Reorder.sync( list );
					}
				} );
			} );
		}
	};

	/* ====================================================================
	 * Gallery
	 * ================================================================= */

	var Gallery = {

		/**
		 * Bind every gallery within a root.
		 *
		 * @param {Element} root Container.
		 */
		init: function ( root ) {
			root.querySelectorAll( '.field-kit__gallery' ).forEach( function ( wrap ) {
				if ( wrap.dataset.fkBound ) {
					return;
				}

				wrap.dataset.fkBound = '1';

				wrap.addEventListener( 'click', function ( event ) {
					if ( event.target.closest( '.field-kit__media-choose' ) ) {
						event.preventDefault();
						Gallery.open( wrap );
					}

					var remove = event.target.closest( '.field-kit__gallery-remove' );

					if ( remove ) {
						event.preventDefault();
						Gallery.remove( wrap, remove.closest( '.field-kit__gallery-item' ) );
					}
				} );
			} );
		},

		/**
		 * Open the media frame.
		 *
		 * @param {Element} wrap The gallery.
		 */
		open: function ( wrap ) {
			if ( ! window.wp || ! window.wp.media ) {
				return;
			}

			var frame = window.wp.media( {
				title: wrap.dataset.frameTitle,
				library: { type: wrap.dataset.mimeType || undefined },
				multiple: 'add',
				button: { text: wrap.dataset.chooseLabel }
			} );

			frame.on( 'select', function () {
				frame.state().get( 'selection' ).forEach( function ( attachment ) {
					Gallery.append( wrap, attachment.toJSON() );
				} );

				Reorder.sync( wrap.querySelector( '.field-kit__gallery-items' ) );
			} );

			frame.open();
		},

		/**
		 * Add one attachment.
		 *
		 * @param {Element} wrap       The gallery.
		 * @param {Object}  attachment Attachment data.
		 */
		append: function ( wrap, attachment ) {
			var list = wrap.querySelector( '.field-kit__gallery-items' );

			if ( list.querySelector( '[data-id="' + attachment.id + '"]' ) ) {
				return;
			}

			// The frame is a multi-select, so a limit has to be checked per
			// attachment as they arrive rather than once before it opens.
			var max = parseInt( wrap.dataset.maxItems || '0', 10 );

			if ( max > 0 && list.children.length >= max ) {
				return;
			}

			var template = list.querySelector( '.field-kit__gallery-item' );
			var item;

			if ( template ) {
				// Cloning an existing item keeps the markup — and so the
				// labelling — identical to what the server rendered.
				item = template.cloneNode( true );
			} else {
				item = document.createElement( 'li' );
				item.className = 'field-kit__gallery-item';
				item.innerHTML = '<img alt="" /><span class="field-kit__gallery-position screen-reader-text"></span>' +
					'<span class="field-kit__gallery-actions">' +
					'<button type="button" class="button-link field-kit__gallery-move" data-direction="up"><span class="dashicons dashicons-arrow-up-alt2" aria-hidden="true"></span></button>' +
					'<button type="button" class="button-link field-kit__gallery-move" data-direction="down"><span class="dashicons dashicons-arrow-down-alt2" aria-hidden="true"></span></button>' +
					'<button type="button" class="button-link field-kit__gallery-remove"><span class="dashicons dashicons-no-alt" aria-hidden="true"></span></button>' +
					'</span>';
			}

			item.dataset.id = attachment.id;

			var image = item.querySelector( 'img' );
			var sizes = attachment.sizes || {};
			// Medium first, matching what the server renders. Picking the
			// thumbnail here meant a freshly added tile was a 150px file in a
			// 150px box — visibly softer than the ones beside it, in the same
			// gallery.
			var thumb = sizes.medium || sizes.thumbnail || {};

			if ( image ) {
				image.src = thumb.url || attachment.url;
				image.alt = attachment.alt || '';
			}

			var name = attachment.title || attachment.filename || String( attachment.id );

			item.querySelectorAll( '[aria-label]' ).forEach( function ( button ) {
				button.setAttribute( 'aria-label', button.getAttribute( 'aria-label' ).replace( /—.*$/, '' ) + name );
			} );

			list.appendChild( item );
		},

		/**
		 * Remove one item.
		 *
		 * @param {Element} wrap The gallery.
		 * @param {Element} item The item.
		 */
		remove: function ( wrap, item ) {
			var list = item.parentNode;
			var next = item.nextElementSibling || item.previousElementSibling;

			item.remove();
			Reorder.sync( list );

			// Focus would land on the body otherwise, which loses a keyboard
			// user's place in the list entirely.
			var target = next ? next.querySelector( 'button' ) : wrap.querySelector( '.field-kit__media-choose' );

			if ( target ) {
				target.focus();
			}
		},

		/**
		 * Write the current order back to the hidden input.
		 *
		 * @param {Element} list The item list.
		 */
		sync: function ( list ) {
			var wrap = list.closest( '.field-kit__gallery' );
			var input = wrap.querySelector( '.field-kit__media-value' );
			var items = Array.from( list.querySelectorAll( '.field-kit__gallery-item' ) );

			if ( input ) {
				input.value = items.map( function ( item ) {
					return item.dataset.id;
				} ).join( ',' );
			}

			// A full gallery says so by disabling the button rather than by
			// opening the frame and then quietly discarding the choice.
			var max = parseInt( wrap.dataset.maxItems || '0', 10 );
			var choose = wrap.querySelector( '.field-kit__media-choose' );

			if ( choose && max > 0 ) {
				choose.disabled = items.length >= max;
			}
		}
	};

	window.ArrayPressFieldKitModules.Reorder = Reorder;
	window.ArrayPressFieldKitModules.Gallery = Gallery;
} )();

/**
 * Field Kit — repeaters, media pickers and tags.
 */
( function () {
	'use strict';

	var kit = window.ArrayPressFieldKitModules;
	var t = kit.t;

	// Pulled from the shared modules object rather than closed over: this is
	// a separate IIFE from the one that resolved it, and referencing `config`
	// directly here threw a ReferenceError that stopped every module after it
	// from initialising.
	var config = kit.config;

	var Repeater = {

		/**
		 * Bind every repeater within a root.
		 *
		 * @param {Element} root Container.
		 */
		init: function ( root ) {
			root.querySelectorAll( '.field-kit__repeater' ).forEach( function ( wrap ) {
				if ( wrap.dataset.fkBound ) {
					return;
				}

				wrap.dataset.fkBound = '1';

				wrap.addEventListener( 'click', function ( event ) {
					if ( event.target.closest( '.field-kit__repeater-add' ) ) {
						event.preventDefault();
						Repeater.add( wrap );
					}

					var remove = event.target.closest( '.field-kit__repeater-remove' );

					if ( remove ) {
						event.preventDefault();
						Repeater.remove( wrap, remove.closest( '.field-kit__repeater-row' ) );
					}
				} );
			} );
		},

		/**
		 * Add a row from the server-rendered template.
		 *
		 * The template came through the same renderer as a saved row, so a new
		 * row is labelled and associated exactly like an existing one — which
		 * is not true of a row assembled from a string in script.
		 *
		 * @param {Element} wrap The repeater.
		 */
		add: function ( wrap ) {
			var template = wrap.querySelector( '.field-kit__repeater-template' );
			var list = wrap.querySelector( '.field-kit__repeater-rows' );

			if ( ! template || ! list ) {
				return;
			}

			var max = parseInt( wrap.dataset.maxRows || '0', 10 );

			if ( max > 0 && list.children.length >= max ) {
				return;
			}

			var row = template.content.firstElementChild.cloneNode( true );

			list.appendChild( row );

			// Through Reorder rather than reindex(): the template is rendered
			// for a row that has no position yet, so it arrives with both its
			// move buttons disabled — and the row that used to be last still
			// has its "move down" switched off. sync() renumbers and settles
			// all of that; reindex() on its own only renumbers, which left
			// every added row unable to move in either direction.
			kit.Reorder.sync( list );

			// The new row's markup has never been past the modules, so a
			// combobox, colour picker or media button inside it is inert
			// until it has. init() is re-entrant — every module marks what it
			// has bound — so this reaches only what was just inserted.
			kit.init( row );

			var empty = wrap.querySelector( '.field-kit__repeater-empty' );

			if ( empty ) {
				empty.hidden = true;
			}

			// Focus into the new row, or adding one is silent and leaves a
			// keyboard user still on the button.
			var first = row.querySelector( 'input:not([type="hidden"]), select, textarea' );

			if ( first ) {
				first.focus();
			}

			kit.announce( wrap.querySelector( '[aria-live]' ), t( 'rowAdded', 'Row added.' ) );
		},

		/**
		 * Remove a row.
		 *
		 * @param {Element} wrap The repeater.
		 * @param {Element} row  The row.
		 */
		remove: function ( wrap, row ) {
			var list = row.parentNode;
			var min = parseInt( wrap.dataset.minRows || '0', 10 );

			if ( min > 0 && list.children.length <= min ) {
				return;
			}

			var next = row.nextElementSibling || row.previousElementSibling;

			row.remove();

			// sync() rather than reindex(), for the same reason as add(): the
			// new first and last rows have moves that are no longer possible.
			kit.Reorder.sync( list );

			var empty = wrap.querySelector( '.field-kit__repeater-empty' );

			if ( empty ) {
				empty.hidden = list.children.length > 0;
			}

			var target = next
				? next.querySelector( 'input:not([type="hidden"]), select, textarea, button' )
				: wrap.querySelector( '.field-kit__repeater-add' );

			if ( target ) {
				target.focus();
			}

			kit.announce( wrap.querySelector( '[aria-live]' ), t( 'rowRemoved', 'Row removed.' ) );
		},

		/**
		 * Renumber every row's input names and ids.
		 *
		 * Removing a middle row leaves a gap in the indexes, and duplicate ids
		 * silently break every label association in the rows below it.
		 *
		 * @param {Element} list The row list.
		 */
		reindex: function ( list ) {
			var wrap = list.closest( '.field-kit__repeater' );

			if ( ! wrap ) {
				return;
			}

			var base = wrap.dataset.fieldName || '';

			Array.from( list.children ).forEach( function ( row, index ) {
				row.dataset.index = String( index );

				row.querySelectorAll( '[name]' ).forEach( function ( input ) {
					input.name = input.name.replace(
						new RegExp( '^' + base.replace( /[.*+?^${}()|[\]\\]/g, '\\$&' ) + '\\[\\d*\\]' ),
						base + '[' + index + ']'
					);
				} );

				row.querySelectorAll( '[id]' ).forEach( function ( element ) {
					var id = element.id.replace( /__row\d+/, '' );
					var next = id + '__row' + index;

					// Move every label and description pointing at the old id,
					// or the association is lost the moment a row is renumbered.
					row.querySelectorAll( '[for="' + CSS.escape( element.id ) + '"]' ).forEach( function ( label ) {
						label.setAttribute( 'for', next );
					} );

					row.querySelectorAll( '[aria-describedby~="' + CSS.escape( element.id ) + '"]' ).forEach( function ( described ) {
						described.setAttribute(
							'aria-describedby',
							described.getAttribute( 'aria-describedby' ).replace( element.id, next )
						);
					} );

					element.id = next;
				} );
			} );
		}
	};

	/* ====================================================================
	 * Media
	 * ================================================================= */

	var Media = {

		/**
		 * Bind every single-item media field within a root.
		 *
		 * @param {Element} root Container.
		 */
		init: function ( root ) {
			root.querySelectorAll( '.field-kit__media:not(.field-kit__gallery)' ).forEach( function ( wrap ) {
				if ( wrap.dataset.fkBound ) {
					return;
				}

				wrap.dataset.fkBound = '1';

				wrap.addEventListener( 'click', function ( event ) {
					if ( event.target.closest( '.field-kit__media-choose' ) ) {
						event.preventDefault();
						Media.open( wrap );
					}

					if ( event.target.closest( '.field-kit__media-clear' ) ) {
						event.preventDefault();
						Media.clear( wrap );
					}
				} );
			} );
		},

		/**
		 * Open the media frame.
		 *
		 * @param {Element} wrap The field.
		 */
		open: function ( wrap ) {
			if ( ! window.wp || ! window.wp.media ) {
				return;
			}

			var frame = window.wp.media( {
				title: wrap.dataset.frameTitle,
				library: { type: wrap.dataset.mimeType || undefined },
				multiple: false,
				button: { text: wrap.dataset.chooseLabel }
			} );

			frame.on( 'select', function () {
				Media.apply( wrap, frame.state().get( 'selection' ).first().toJSON() );
			} );

			frame.open();
		},

		/**
		 * Store and preview a selection.
		 *
		 * @param {Element} wrap       The field.
		 * @param {Object}  attachment Attachment data.
		 */
		apply: function ( wrap, attachment ) {
			var input = wrap.querySelector( '.field-kit__media-value' );
			var preview = wrap.querySelector( '.field-kit__media-preview' );
			var clear = wrap.querySelector( '.field-kit__media-clear' );

			// file_url stores the URL; every other media field stores the id.
			input.value = wrap.dataset.returns === 'url' ? attachment.url : attachment.id;
			input.dispatchEvent( new Event( 'change', { bubbles: true } ) );

			if ( preview ) {
				var sizes = attachment.sizes || {};
				var thumb = sizes.medium || sizes.thumbnail;

				preview.dataset.empty = 'false';
				preview.innerHTML = thumb
					? '<img src="' + thumb.url + '" alt="' + ( attachment.alt || '' ).replace( /"/g, '&quot;' ) + '" />'
					: '<span class="field-kit__media-filename"></span>';

				var filename = preview.querySelector( '.field-kit__media-filename' );

				if ( filename ) {
					filename.textContent = attachment.filename || attachment.title || '';
				}
			}

			if ( clear ) {
				clear.hidden = false;
			}
		},

		/**
		 * Clear a selection.
		 *
		 * @param {Element} wrap The field.
		 */
		clear: function ( wrap ) {
			var input = wrap.querySelector( '.field-kit__media-value' );
			var preview = wrap.querySelector( '.field-kit__media-preview' );
			var clear = wrap.querySelector( '.field-kit__media-clear' );
			var choose = wrap.querySelector( '.field-kit__media-choose' );

			input.value = '';
			input.dispatchEvent( new Event( 'change', { bubbles: true } ) );

			if ( preview ) {
				preview.innerHTML = '';
				preview.dataset.empty = 'true';
			}

			if ( clear ) {
				clear.hidden = true;
			}

			// The button that was just used is now hidden, so focus has to go
			// somewhere deliberate rather than to the body.
			if ( choose ) {
				choose.focus();
			}
		}
	};

	/* ====================================================================
	 * Tags
	 * ================================================================= */

	var Tags = {

		/**
		 * Mirror each tags input into a visible, announced list.
		 *
		 * The input stays the source of truth and stays editable, so the
		 * field is fully usable before this runs and remains so if it fails.
		 *
		 * @param {Element} root Container.
		 */
		init: function ( root ) {
			root.querySelectorAll( '.field-kit__tags' ).forEach( function ( wrap ) {
				if ( wrap.dataset.fkBound ) {
					return;
				}

				wrap.dataset.fkBound = '1';

				var input = wrap.querySelector( '.field-kit__tags-input' );
				var list = wrap.querySelector( '.field-kit__tags-list' );

				if ( ! input || ! list ) {
					return;
				}

				var separator = input.dataset.separator || ',';

				function render() {
					list.innerHTML = '';

					input.value.split( separator ).map( function ( tag ) {
						return tag.trim();
					} ).filter( Boolean ).forEach( function ( tag ) {
						var pill = document.createElement( 'span' );
						pill.className = 'field-kit__tag';
						pill.textContent = tag;
						list.appendChild( pill );
					} );
				}

				input.addEventListener( 'input', render );
				render();
			} );
		}
	};


	/* ====================================================================
	 * Code editor
	 * ================================================================= */

	var CodeEditor = {

		/**
		 * Upgrade every code field to CodeMirror.
		 *
		 * The control stays a textarea underneath, so a user who has turned
		 * syntax highlighting off in their profile — in which case
		 * wp_enqueue_code_editor() returns false and there are no settings
		 * to apply — still gets a working, keyboard-accessible field rather
		 * than nothing.
		 *
		 * @param {Element} root Container.
		 */
		init: function ( root ) {
			if ( ! window.wp || ! window.wp.codeEditor || ! config.codeEditors ) {
				return;
			}

			root.querySelectorAll( 'textarea.field-kit__code' ).forEach( function ( textarea ) {
				if ( textarea.dataset.fkBound ) {
					return;
				}

				var settings = config.codeEditors[ textarea.dataset.language ];

				if ( ! settings ) {
					return;
				}

				textarea.dataset.fkBound = '1';

				var editor = window.wp.codeEditor.initialize( textarea, settings );

				// CodeMirror renders into its own element and stops firing
				// the textarea's events, so the value is written back on
				// change — otherwise the field submits whatever it loaded
				// with, however much was typed.
				if ( editor && editor.codemirror ) {
					editor.codemirror.on( 'change', function ( instance ) {
						textarea.value = instance.getValue();
					} );

					// The generated element is not a form control and gets no
					// label, so the field's own label is pointed at it.
					var wrapper = editor.codemirror.getWrapperElement();

					if ( wrapper ) {
						wrapper.setAttribute( 'aria-label', textarea.getAttribute( 'aria-label' ) || '' );
					}
				}
			} );
		}
	};

	window.ArrayPressFieldKitModules.CodeEditor = CodeEditor;

	/* ====================================================================
	 * Colour picker
	 * ================================================================= */

	var ColorPicker = {

		/**
		 * Upgrade every colour field to core's picker.
		 *
		 * Enqueueing wp-color-picker is not enough: it is a jQuery plugin and
		 * does nothing until it is called. The control was a plain text input
		 * on screen for exactly that reason.
		 *
		 * The underlying input keeps working if jQuery or the plugin is
		 * missing — a hex value can still be typed — which is why the field
		 * is a text input rather than input[type=color].
		 *
		 * @param {Element} root Container.
		 */
		init: function ( root ) {
			var $ = window.jQuery;

			if ( ! $ || ! $.fn || ! $.fn.wpColorPicker ) {
				return;
			}

			root.querySelectorAll( 'input.field-kit__color' ).forEach( function ( input ) {
				if ( input.dataset.fkBound ) {
					return;
				}

				input.dataset.fkBound = '1';

				/**
				 * Tell the kit the value moved, without telling iris.
				 *
				 * iris fires its change callback *before* it writes the new
				 * value to the input — verified in iris.min.js `_change`,
				 * which calls `_trigger("change")` and only then
				 * `element.val()`. It also listens for `change` on the input
				 * itself and re-reads the value when one arrives.
				 *
				 * So dispatching a native change from here handed iris the
				 * *old* value and it reset the picker: the palette opened,
				 * swatches highlighted, and clicking one changed nothing.
				 * From the clear callback it was worse — that one is reached
				 * from iris's own change listener, so a native change there
				 * loops.
				 *
				 * A custom event does neither. Conditions listens for it.
				 */
				function notify() {
					input.dispatchEvent( new CustomEvent( 'field-kit:change', { bubbles: true } ) );
				}

				$( input ).wpColorPicker( {
					palettes: input.dataset.palette ? input.dataset.palette.split( ',' ) : true,
					change: notify,
					clear: notify
				} );
			} );
		}
	};

	window.ArrayPressFieldKitModules.ColorPicker = ColorPicker;

	/* ====================================================================
	 * Panel tabs
	 * ================================================================= */

	var PanelTabs = {

		/**
		 * Bind every tab list within a root.
		 *
		 * @param {Element} root Container.
		 */
		init: function ( root ) {
			root.querySelectorAll( '.field-kit__panel-tabs' ).forEach( function ( wrap ) {
				if ( wrap.dataset.fkBound ) {
					return;
				}

				wrap.dataset.fkBound = '1';
				PanelTabs.bind( wrap );
			} );
		},

		/**
		 * Wire one tab list.
		 *
		 * @param {Element} wrap The tabbed panel.
		 */
		bind: function ( wrap ) {
			var tabs = Array.prototype.slice.call(
				wrap.querySelectorAll( '.field-kit__panel-tab' )
			);

			if ( ! tabs.length ) {
				return;
			}

			/**
			 * Show one tab's panel and hide the rest.
			 *
			 * @param {number}  index      Which tab.
			 * @param {boolean} moveFocus  Whether to focus it.
			 */
			function select( index, moveFocus ) {
				tabs.forEach( function ( tab, i ) {
					var chosen = i === index;
					var panel = wrap.querySelector( '#' + CSS.escape( tab.getAttribute( 'aria-controls' ) ) );

					tab.setAttribute( 'aria-selected', chosen ? 'true' : 'false' );

					// Only the selected tab is in the tab order: arrow keys
					// move between them, and tabbing leaves the list.
					tab.tabIndex = chosen ? 0 : -1;
					tab.classList.toggle( 'is-active', chosen );

					if ( panel ) {
						panel.hidden = ! chosen;
					}
				} );

				if ( moveFocus ) {
					tabs[ index ].focus();
				}
			}

			tabs.forEach( function ( tab, index ) {
				tab.addEventListener( 'click', function () {
					select( index, false );
				} );

				tab.addEventListener( 'keydown', function ( event ) {
					var last = tabs.length - 1;

					switch ( event.key ) {
						// Vertical list, so up and down are the ones that
						// matter — but left and right are bound too, because
						// a tab list is a tab list whichever way it is drawn.
						case 'ArrowDown':
						case 'ArrowRight':
							event.preventDefault();
							select( index === last ? 0 : index + 1, true );
							break;
						case 'ArrowUp':
						case 'ArrowLeft':
							event.preventDefault();
							select( index === 0 ? last : index - 1, true );
							break;
						case 'Home':
							event.preventDefault();
							select( 0, true );
							break;
						case 'End':
							event.preventDefault();
							select( last, true );
							break;
						default:
							break;
					}
				} );
			} );
		}
	};

	window.ArrayPressFieldKitModules.PanelTabs = PanelTabs;

	/* ====================================================================
	 * Merge tag chooser
	 * ================================================================= */

	var TagModal = {

		/**
		 * Bind every Add Tag button within a root.
		 *
		 * @param {Element} root Container.
		 */
		init: function ( root ) {
			root.querySelectorAll( '.field-kit__tag-button' ).forEach( function ( button ) {
				if ( button.dataset.fkBound ) {
					return;
				}

				button.dataset.fkBound = '1';
				TagModal.bind( button );
			} );
		},

		/**
		 * Wire one button to its dialog.
		 *
		 * @param {Element} button The Add Tag button.
		 */
		bind: function ( button ) {
			var modal = document.getElementById( button.dataset.modal );

			if ( ! modal ) {
				return;
			}

			var backdrop = modal.nextElementSibling;
			var search = modal.querySelector( '.field-kit__tag-search-input' );
			var empty = modal.querySelector( '.field-kit__tag-empty' );
			var rows = Array.prototype.slice.call( modal.querySelectorAll( '.field-kit__tag-items li' ) );

			function open() {
				modal.hidden = false;

				if ( backdrop ) {
					backdrop.hidden = false;
				}

				if ( search ) {
					search.value = '';
					filter( '' );
					search.focus();
				}

				document.addEventListener( 'keydown', onKey );
			}

			function close() {
				modal.hidden = true;

				if ( backdrop ) {
					backdrop.hidden = true;
				}

				document.removeEventListener( 'keydown', onKey );

				// Focus goes back where it came from, or it lands at the top
				// of the document and the next tab starts the page again.
				button.focus();
			}

			function onKey( event ) {
				if ( 'Escape' === event.key ) {
					close();
				}
			}

			function filter( term ) {
				var needle = term.toLowerCase().trim();
				var shown = 0;

				rows.forEach( function ( row ) {
					var item = row.querySelector( '.field-kit__tag-item' );
					var match = '' === needle
						|| ( item && item.dataset.search && item.dataset.search.indexOf( needle ) > -1 );

					row.hidden = ! match;

					if ( match ) {
						shown ++;
					}
				} );

				if ( empty ) {
					empty.hidden = shown > 0;
				}
			}

			button.addEventListener( 'click', open );

			var closer = modal.querySelector( '.field-kit__tag-close' );

			if ( closer ) {
				closer.addEventListener( 'click', close );
			}

			if ( backdrop ) {
				backdrop.addEventListener( 'click', close );
			}

			if ( search ) {
				search.addEventListener( 'input', function () {
					filter( search.value );
				} );
			}

			rows.forEach( function ( row ) {
				var item = row.querySelector( '.field-kit__tag-item' );

				if ( ! item ) {
					return;
				}

				item.addEventListener( 'click', function () {
					TagModal.insert( button.dataset.editor, item.dataset.tag || '' );
					close();
				} );
			} );
		},

		/**
		 * Put a tag into an editor.
		 *
		 * A visual editor is not an input at all — its content lives in an
		 * iframe — so writing to the textarea underneath it would be
		 * overwritten the moment the editor syncs.
		 *
		 * @param {string} editorId The editor's id.
		 * @param {string} tag      The tag to insert.
		 */
		insert: function ( editorId, tag ) {
			if ( '' === tag ) {
				return;
			}

			var editor = window.tinymce ? window.tinymce.get( editorId ) : null;

			if ( editor && ! editor.isHidden() ) {
				editor.execCommand( 'mceInsertContent', false, tag );

				return;
			}

			var textarea = document.getElementById( editorId );

			if ( ! textarea ) {
				return;
			}

			var start = textarea.selectionStart;
			var end = textarea.selectionEnd;

			if ( 'number' !== typeof start ) {
				textarea.value += tag;
			} else {
				textarea.value = textarea.value.slice( 0, start ) + tag + textarea.value.slice( end );
				textarea.selectionStart = start + tag.length;
				textarea.selectionEnd = textarea.selectionStart;
			}

			textarea.focus();
			textarea.dispatchEvent( new Event( 'input', { bubbles: true } ) );
		}
	};

	window.ArrayPressFieldKitModules.TagModal = TagModal;

	/* ====================================================================
	 * Email panels
	 * ================================================================= */

	var EmailPanel = {

		/**
		 * Bind every email panel within a root.
		 *
		 * Two behaviours, both of which the markup promises and neither of
		 * which core supplies here: the postbox header collapses the panel
		 * (core's postboxes.js only runs on metabox screens), and a merge tag
		 * inserts itself where the author was last typing.
		 *
		 * @param {Element} root Container.
		 */
		init: function ( root ) {
			root.querySelectorAll( '.field-kit__email' ).forEach( function ( panel ) {
				if ( panel.dataset.fkBound ) {
					return;
				}

				panel.dataset.fkBound = '1';
				EmailPanel.bindToggle( panel );
				EmailPanel.bindTags( panel );
			} );
		},

		/**
		 * Collapse and expand from the header.
		 *
		 * @param {Element} panel The panel.
		 */
		bindToggle: function ( panel ) {
			var button = panel.querySelector( '.field-kit__email-toggle' );
			var header = panel.querySelector( '.postbox-header .hndle' );

			if ( ! button ) {
				return;
			}

			function toggle() {
				// Core's postboxes.js binds '.postbox .hndle, .postbox
				// .handlediv' across the whole document on any screen that
				// calls add_postbox_toggles() — a post editor, the dashboard
				// — and its handler does everything this one does. Two
				// handlers on the same button is a panel that toggles twice
				// and so never appears to move at all, which is exactly what
				// happened to the email editor inside a metabox.
				//
				// Checked here rather than at bind time because core sets
				// this on DOM ready and the order of the two is not fixed.
				if ( window.postboxes && window.postboxes.page ) {
					return;
				}

				var open = 'true' === button.getAttribute( 'aria-expanded' );

				button.setAttribute( 'aria-expanded', open ? 'false' : 'true' );
				panel.classList.toggle( 'closed', open );
			}

			button.addEventListener( 'click', toggle );

			// Core makes the whole header clickable, and so does this — but
			// only the button carries the state, so there is one control to
			// find from a keyboard rather than two that disagree.
			if ( header ) {
				header.addEventListener( 'click', toggle );
			}
		},

		/**
		 * Insert a merge tag where the author was last typing.
		 *
		 * @param {Element} panel The panel.
		 */
		bindTags: function ( panel ) {
			var target = null;

			panel.querySelectorAll( 'input[type="text"], textarea' ).forEach( function ( input ) {
				input.addEventListener( 'focus', function () {
					target = input;
				} );
			} );

			panel.querySelectorAll( '.field-kit__email-tag' ).forEach( function ( button ) {
				button.addEventListener( 'click', function () {
					EmailPanel.insert( panel, target, button.dataset.tag || '' );
				} );
			} );
		},

		/**
		 * Put a tag into whichever editor is in play.
		 *
		 * @param {Element}      panel The panel.
		 * @param {Element|null} input The last focused input, if any.
		 * @param {string}       tag   The tag to insert.
		 */
		insert: function ( panel, input, tag ) {
			if ( '' === tag ) {
				return;
			}

			// A visual editor is not an input at all — its content lives in an
			// iframe — so writing to the textarea underneath it would be
			// overwritten the moment the editor syncs.
			var editor = EmailPanel.activeEditor( panel );

			if ( editor && ! editor.isHidden() ) {
				editor.execCommand( 'mceInsertContent', false, tag );

				return;
			}

			var field = input || panel.querySelector( 'textarea, input[type="text"]' );

			if ( ! field ) {
				return;
			}

			var start = field.selectionStart;
			var end = field.selectionEnd;

			if ( 'number' !== typeof start ) {
				field.value += tag;
			} else {
				field.value = field.value.slice( 0, start ) + tag + field.value.slice( end );
				field.selectionStart = start + tag.length;
				field.selectionEnd = field.selectionStart;
			}

			field.focus();
			field.dispatchEvent( new Event( 'input', { bubbles: true } ) );
		},

		/**
		 * The TinyMCE instance for this panel's body, if there is one.
		 *
		 * @param {Element} panel The panel.
		 * @return {Object|null} The editor.
		 */
		activeEditor: function ( panel ) {
			var textarea = panel.querySelector( 'textarea.wp-editor-area' );

			if ( ! textarea || ! window.tinymce ) {
				return null;
			}

			return window.tinymce.get( textarea.id ) || null;
		}
	};

	window.ArrayPressFieldKitModules.EmailPanel = EmailPanel;

	/* ====================================================================
	 * Buttons that run an action
	 * ================================================================= */

	var ActionButton = {

		/**
		 * Bind every action button within a root.
		 *
		 * One handler for licences, email previews, test sends and whatever
		 * a consumer wires up: they are all the same shape — post a payload,
		 * name a handler, show what came back.
		 *
		 * @param {Element} root Container.
		 */
		init: function ( root ) {
			root.addEventListener( 'click', function ( event ) {
				var button = event.target.closest( '[data-endpoint][data-action]' );

				if ( ! button || button.disabled ) {
					return;
				}

				event.preventDefault();
				ActionButton.run( button );
			} );
		},

		/**
		 * Run one button's action.
		 *
		 * @param {HTMLButtonElement} button The button.
		 */
		run: function ( button ) {
			if ( button.dataset.confirm && ! window.confirm( button.dataset.confirm ) ) {
				return;
			}

			var wrap = button.closest( '.field-kit__field' ) || document;
			var status = wrap.querySelector( '[aria-live]' );
			var spinner = wrap.querySelector( '.spinner' );

			// Disabled for the duration, or an impatient double click runs a
			// licence activation twice.
			button.disabled = true;

			if ( spinner ) {
				spinner.classList.add( 'is-active' );
			}

			fetch( button.dataset.endpoint, {
				method: 'POST',
				credentials: 'same-origin',
				headers: {
					'Content-Type': 'application/json',
					'X-WP-Nonce': button.dataset.nonce || config.restNonce || ''
				},
				body: JSON.stringify( {
					action: button.dataset.action,
					payload: ActionButton.payload( button, wrap )
				} )
			} ).then( function ( response ) {
				return response.json().catch( function () {
					return { success: false, message: t( 'actionFailed', 'That did not work.' ) };
				} );
			} ).then( function ( result ) {
				ActionButton.report( wrap, status, result );
			} ).catch( function () {
				ActionButton.report( wrap, status, {
					success: false,
					message: t( 'actionFailed', 'That did not work.' )
				} );
			} ).finally( function () {
				button.disabled = false;

				if ( spinner ) {
					spinner.classList.remove( 'is-active' );
				}
			} );
		},

		/**
		 * Collect what the field wants to send.
		 *
		 * A licence sends its key, an email its subject and body. Rather than
		 * a rule per type, every named input inside the field travels.
		 *
		 * @param {HTMLButtonElement} button The button.
		 * @param {Element}           wrap   The field wrapper.
		 * @return {Object} Payload.
		 */
		payload: function ( button, wrap ) {
			var payload = {};
			var key = wrap.dataset ? wrap.dataset.fieldKey : '';

			wrap.querySelectorAll( '[name]' ).forEach( function ( input ) {
				if ( input.type === 'checkbox' || input.type === 'radio' ) {
					if ( ! input.checked ) {
						return;
					}
				}

				// Bracketed names are the field's own structure; the last
				// segment is what a handler is going to look for.
				var name = input.name.replace( /^.*\[([^\]]+)\]$/, '$1' );

				payload[ name ] = input.value;

				// And under `value`, for the field's own control.
				//
				// Otherwise a handler has to know the field key to find it,
				// which differs per screen for the same field: a licence is
				// `apfd_license` on a term screen and `apfd_every_license` on
				// a settings page, so a handler written for one silently read
				// nothing on the other and reported the box as empty.
				if ( key && name === key ) {
					payload.value = input.value;
				}
			} );

			return payload;
		},

		/**
		 * Show the outcome.
		 *
		 * Written into the field's own live region, so it is announced where
		 * it happened rather than shouted from the page, and given a notice
		 * class so success and failure do not read identically.
		 *
		 * @param {Element} wrap   The field wrapper.
		 * @param {Element} status The live region.
		 * @param {Object}  result What came back.
		 */
		report: function ( wrap, status, result ) {
			var message = result.message || ( result.success
				? t( 'actionDone', 'Done.' )
				: t( 'actionFailed', 'That did not work.' ) );

			if ( status ) {
				status.classList.remove( 'field-kit__status--success', 'field-kit__status--error' );
				status.classList.add( result.success ? 'field-kit__status--success' : 'field-kit__status--error' );
				kit.announce( status, message );
			}

			// A handler may hand back markup to show — an email preview, for
			// instance — which goes in a region of its own rather than into
			// the live region, where it would be read out in full.
			if ( result.data && result.data.html ) {
				var preview = wrap.querySelector( '.field-kit__action-preview' );

				if ( preview ) {
					preview.innerHTML = result.data.html;
					preview.hidden = false;
				}
			}

			// Or the new state of the field it acted on, which is what a
			// licence activation has to hand back: an action that succeeds
			// and leaves the field showing the state it was in before is an
			// action that looks like it failed.
			if ( result.success && result.data && result.data.state ) {
				ActionButton.applyState( wrap, result.data.state );
			}
		},

		/**
		 * Put a field into the state a handler reported.
		 *
		 * Only the licence field has a state to be in; everything else is a
		 * button that does something and says what happened. Written here
		 * rather than in a module of its own because this is where the
		 * response arrives, and a second listener for the same fetch would be
		 * a second place for the two to drift apart.
		 *
		 * @param {Element} wrap  The field.
		 * @param {Object}  state What it is now.
		 */
		applyState: function ( wrap, state ) {
			var licence = wrap.querySelector( '.field-kit__license' );

			if ( ! licence ) {
				return;
			}

			var active = !! state.active;
			var badge = wrap.querySelector( '.field-kit__license-state' );
			var text = wrap.querySelector( '.field-kit__license-state-text' );
			var icon = badge && badge.querySelector( '.dashicons' );
			var button = wrap.querySelector( '.field-kit__license-action' );
			var input = wrap.querySelector( '.field-kit__license-key' );
			var meta = wrap.querySelector( '.field-kit__license-meta' );
			var sites = wrap.querySelector( '.field-kit__license-sites' );

			if ( badge ) {
				badge.classList.toggle( 'field-kit__license-state--active', active );
				badge.classList.toggle( 'field-kit__license-state--inactive', ! active );
			}

			if ( text ) {
				text.textContent = active
					? licence.dataset.labelActive
					: licence.dataset.labelInactive;
			}

			if ( icon ) {
				icon.classList.toggle( 'dashicons-yes-alt', active );
				icon.classList.toggle( 'dashicons-marker', ! active );
			}

			if ( button ) {
				button.textContent = active
					? button.dataset.labelDeactivate
					: button.dataset.labelActivate;

				// The registered name of the *other* action, so the next
				// press reaches the handler that undoes this one.
				button.dataset.action = active
					? ( button.dataset.actionDeactivate || '' )
					: ( button.dataset.actionActivate || '' );

				button.classList.toggle( 'field-kit__button--delete', active );
			}

			// The key is masked while a licence is active, and the box is
			// read-only — there is nothing to type until it is released.
			if ( input ) {
				input.readOnly = active;

				if ( typeof state.key === 'string' ) {
					input.value = state.key;
				}
			}

			if ( sites && meta && Array.isArray( state.sites ) && 2 === state.sites.length ) {
				sites.hidden = false;
				sites.textContent = ( meta.dataset.sitesTemplate || '%1$s of %2$s sites' )
					.replace( '%1$s', String( state.sites[ 0 ] ) )
					.replace( '%2$s', String( state.sites[ 1 ] ) );
			}
		}
	};

	/* ====================================================================
	 * Tooltips
	 * ================================================================= */

	var Tooltip = {

		/**
		 * Bind every tooltip within a root.
		 *
		 * The panel is already in the markup and already hidden, so a tooltip
		 * that this never reaches is a button that does nothing rather than
		 * an explanation that does not exist — the panel is still associated
		 * with the button through aria-describedby, so it is still announced.
		 *
		 * @param {Element} root Container.
		 */
		init: function ( root ) {
			root.querySelectorAll( '.field-kit__tooltip' ).forEach( function ( wrap ) {
				if ( wrap.dataset.fkBound ) {
					return;
				}

				wrap.dataset.fkBound = '1';

				var button = wrap.querySelector( '.field-kit__tooltip-toggle' );
				var panel = wrap.querySelector( '.field-kit__tooltip-panel' );

				if ( ! button || ! panel ) {
					return;
				}

				function open() {
					panel.hidden = false;
					button.setAttribute( 'aria-expanded', 'true' );
					Tooltip.place( panel, button );
				}

				function close() {
					panel.hidden = true;
					button.setAttribute( 'aria-expanded', 'false' );
				}

				// Hover and focus both, because a tooltip that only opens on
				// hover is one that does not exist for anyone using a
				// keyboard — which is the failing this component was written
				// to avoid.
				wrap.addEventListener( 'mouseenter', open );
				wrap.addEventListener( 'mouseleave', close );
				button.addEventListener( 'focus', open );
				button.addEventListener( 'blur', close );

				// Click as well, for touch, where there is no hover at all.
				button.addEventListener( 'click', function ( event ) {
					event.preventDefault();

					if ( panel.hidden ) {
						open();
					} else {
						close();
					}
				} );

				button.addEventListener( 'keydown', function ( event ) {
					if ( 'Escape' === event.key && ! panel.hidden ) {
						event.stopPropagation();
						close();
					}
				} );
			} );
		},

		/**
		 * Keep the panel on the screen.
		 *
		 * It is centred on its icon, which is wrong twice: a tooltip on the
		 * first field of a metabox opens off the top of the scrolled area,
		 * and one on a label in the left-hand column of a settings table
		 * hangs half its width off the left of the window — far enough that
		 * the first words are behind the admin menu.
		 *
		 * So it flips below when there is no room above, and slides sideways
		 * when there is none beside. The arrow is moved back onto the icon by
		 * the same distance, because an arrow that no longer points at
		 * anything is worse than no arrow.
		 *
		 * @param {Element} panel  The panel.
		 * @param {Element} button The icon it belongs to.
		 */
		place: function ( panel, button ) {
			var gap = 8;

			// Measured from a clean slate: this runs again every time the
			// panel opens, and a shift left over from the last position would
			// be measured as though it were the natural one.
			delete panel.dataset.position;
			panel.style.transform = '';
			panel.style.removeProperty( '--fk-tooltip-arrow' );

			var box = panel.getBoundingClientRect();

			if ( box.top < gap ) {
				panel.dataset.position = 'below';
			}

			var room = ( window.innerWidth || document.documentElement.clientWidth ) - gap;
			var shift = 0;

			if ( box.left < gap ) {
				shift = gap - box.left;
			} else if ( box.right > room ) {
				shift = room - box.right;
			}

			if ( 0 === shift ) {
				return;
			}

			panel.style.transform = 'translateX(calc(-50% + ' + Math.round( shift ) + 'px))';

			// The arrow follows the icon rather than the panel. Clamped
			// inside the panel's own corners, so it stays a pointer rather
			// than becoming a notch in the edge.
			var icon = button.getBoundingClientRect();
			var centre = icon.left + ( icon.width / 2 ) - ( box.left + shift );

			panel.style.setProperty(
				'--fk-tooltip-arrow',
				Math.round( Math.min( Math.max( centre, 12 ), box.width - 12 ) ) + 'px'
			);
		}
	};

	window.ArrayPressFieldKitModules.Tooltip = Tooltip;

	window.ArrayPressFieldKitModules.ActionButton = ActionButton;



	window.ArrayPressFieldKitModules.Repeater = Repeater;
	window.ArrayPressFieldKitModules.Media = Media;
	window.ArrayPressFieldKitModules.Tags = Tags;

	/* ====================================================================
	 * Bootstrap
	 * ================================================================= */

	/**
	 * Bind every module within a root.
	 *
	 * Safe to call again: each module marks what it has bound, so newly
	 * inserted markup — a repeater row, a flyout that just opened — can be
	 * initialised without rebinding what is already live.
	 *
	 * @param {Element} root Container, defaulting to the document.
	 */
	function init( root ) {
		root = root || document;

		[ 'Conditions', 'Range', 'Toggle', 'Clipboard', 'CodeGenerator', 'Oembed', 'Combobox', 'Reorder', 'Gallery', 'Repeater', 'Media', 'Tags', 'CodeEditor', 'ColorPicker', 'TagModal', 'PanelTabs', 'EmailPanel', 'ActionButton', 'Tooltip' ].forEach( function ( name ) {
			var module = window.ArrayPressFieldKitModules[ name ];

			if ( module && typeof module.init === 'function' ) {
				module.init( root );
			}
		} );
	}

	window.ArrayPressFieldKitModules.init = init;

	if ( document.readyState === 'loading' ) {
		document.addEventListener( 'DOMContentLoaded', function () {
			init( document );
		} );
	} else {
		init( document );
	}

	// Run once more when everything has loaded. Each module marks what it has
	// bound, so this rebinds nothing — it only picks up a control whose
	// library was not there the first time. Belt and braces: the failure mode
	// when a jQuery plugin has not loaded yet is that the field silently
	// stays a plain input, with nothing in the console to explain it.
	window.addEventListener( 'load', function () {
		init( document );
	} );
} )();
