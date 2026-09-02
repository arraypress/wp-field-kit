/**
 * Smoke test for the field kit's script.
 *
 * The PHP suite renders markup and never executes a line of JavaScript, so a
 * ReferenceError in a module is invisible to it. That is not hypothetical:
 * `config` was resolved in one IIFE and used in another, and the resulting
 * throw stopped the init loop dead — every module after it silently never
 * ran, and the only symptom was that several fields "didn't work".
 *
 * This loads the script against a minimal DOM and calls every module's init,
 * which is the path that broke.
 */

'use strict';

const fs = require( 'fs' );
const path = require( 'path' );
const vm = require( 'vm' );

const source = fs.readFileSync(
	path.join( __dirname, '..', '..', 'assets', 'js', 'field-kit.js' ),
	'utf8'
);

/**
 * The smallest DOM the script touches.
 *
 * Deliberately minimal rather than jsdom: the point is to catch a reference
 * that does not resolve, not to assert behaviour, and a real DOM would drag
 * in a dependency this library otherwise does not have.
 */
function makeElement() {
	const element = {
		dataset: {},
		classList: { add() {}, remove() {}, contains: () => false, toggle() {} },
		style: {},
		offsetWidth: 240,
		offsetHeight: 30,
		offsetLeft: 0,
		offsetTop: 0,
		hidden: false,
		disabled: false,
		value: '',
		id: '',
		children: [],
		addEventListener() {},
		removeEventListener() {},
		querySelector: () => null,
		querySelectorAll: () => [],
		closest: () => null,
		hidden: false,
		getAttribute: () => null,
		setAttribute() {},
		hasAttribute: () => false,
		removeAttribute() {},
		appendChild() {},
		insertBefore() {},
		remove() {},
		focus() {},
		dispatchEvent() {},
		cloneNode() { return makeElement(); },
	};

	return element;
}

const documentStub = {
	readyState: 'complete',
	currentScript: { id: 'field-kit-js' },
	addEventListener() {},
	createElement: () => makeElement(),
	querySelector: () => null,
	querySelectorAll: () => [],
	body: makeElement(),
	ownerDocument: null,
};

const context = {
	document: documentStub,
	getComputedStyle: () => ( {} ),
	console,
	setTimeout,
	clearTimeout,
	fetch: () => Promise.resolve( { ok: true, json: () => Promise.resolve( {} ) } ),
	CSS: { escape: ( value ) => String( value ) },
	Event: function ( type ) { this.type = type; },
	CustomEvent: function ( type ) { this.type = type; },
	URL,
	Promise,
	navigator: { clipboard: null },
	location: { origin: 'https://example.test' },
};

context.window = context;
context.window.addEventListener = () => {};
context.window.removeEventListener = () => {};
context.window.setTimeout = setTimeout;
context.window.clearTimeout = clearTimeout;
context.window.isSecureContext = true;
context.window.location = context.location;
context.window.ArrayPressFieldKit = {
	'field-kit': { restUrl: 'https://example.test/wp-json/field-kit/v1/', restNonce: 'x', i18n: {} },
};

let failures = 0;

try {
	vm.createContext( context );
	vm.runInContext( source, context, { filename: 'field-kit.js' } );
} catch ( error ) {
	console.error( `  script threw while loading: ${ error.message }` );
	process.exit( 1 );
}

const modules = context.window.ArrayPressFieldKitModules;

if ( ! modules ) {
	console.error( '  the script exposed no modules' );
	process.exit( 1 );
}

/*
 * Read out of the script's own init list rather than kept by hand here. The
 * hand-written copy had drifted: IconPreview and Providers were both added to
 * the script and never to this list, so neither was ever checked -- and a
 * module that is not on the list is exactly the one nobody notices is broken.
 */
const bootstrap = source.match( /\[\s*'Conditions'[^\]]*\]/ );

if ( ! bootstrap ) {
	console.error( '  could not find the script\'s module list' );
	process.exit( 1 );
}

const expected = bootstrap[ 0 ].match( /'([A-Za-z]+)'/g ).map( ( name ) => name.slice( 1, -1 ) );

expected.forEach( ( name ) => {
	if ( ! modules[ name ] ) {
		console.error( `  ${ name }: not exposed` );
		failures ++;

		return;
	}

	if ( typeof modules[ name ].init !== 'function' ) {
		return;
	}

	try {
		modules[ name ].init( documentStub );
	} catch ( error ) {
		console.error( `  ${ name }.init threw: ${ error.message }` );
		failures ++;
	}
} );

// The bootstrap's own loop, which is where the ReferenceError surfaced.
try {
	modules.init( documentStub );
} catch ( error ) {
	console.error( `  init() threw: ${ error.message }` );
	failures ++;
}

/*
 * The colour picker must not fire a native change.
 *
 * iris runs its change callback before it writes the value, and re-reads the
 * input when a change arrives — so a native change handed it the old value
 * and it reset the picker. The palette opened, swatches highlighted, and
 * clicking one did nothing. From the clear callback the same dispatch loops,
 * because that one is reached from iris's own change listener.
 *
 * Asserted here because it is invisible in markup and the PHP suite cannot
 * reach it: the whole failure lives in which event name is used.
 */
( function () {
	const dispatched = [];
	const input = makeElement();

	input.dispatchEvent = ( event ) => dispatched.push( event.type );
	input.classList.contains = ( name ) => name === 'field-kit__color';

	let options = null;

	context.window.jQuery = Object.assign(
		() => ( { wpColorPicker: ( passed ) => { options = passed; } } ),
		{ fn: { wpColorPicker: () => {} } }
	);

	const root = Object.assign( makeElement(), {
		querySelectorAll: ( selector ) =>
			( 'input.field-kit__color' === selector ? [ input ] : [] ),
	} );

	modules.ColorPicker.init( root );

	if ( ! options || typeof options.change !== 'function' ) {
		console.error( '  ColorPicker: wpColorPicker was never called with a change callback' );
		failures ++;
	} else {
		options.change();
		options.clear();

		if ( dispatched.includes( 'change' ) ) {
			console.error( '  ColorPicker: fires a native change, which iris reads as a fresh edit' );
			failures ++;
		}

		if ( 2 !== dispatched.filter( ( type ) => 'field-kit:change' === type ).length ) {
			console.error( `  ColorPicker: expected two field-kit:change events, got ${ JSON.stringify( dispatched ) }` );
			failures ++;
		}
	}
} )();

/*
 * The combobox list is sized from the input, not from its wrapper.
 *
 * It used to be stretched across the wrapper with left:0;right:0, so anything
 * that made the wrapper wider than the input — core does, on a term screen —
 * left the list hanging past the control it belongs to. Measured from the
 * input, it cannot. This asserts the measurement happens at all, since the
 * failure is invisible in markup and the PHP suite cannot reach it.
 */
( function () {
	const source = fs.readFileSync(
		path.join( __dirname, '..', '..', 'assets', 'js', 'field-kit.js' ),
		'utf8'
	);

	const placed = /list\.style\.width\s*=\s*input\.offsetWidth/.test( source );
	const stretched = /\.field-kit__combobox-list[\s\S]{0,400}?right:\s*0/.test(
		fs.readFileSync(
			path.join( __dirname, '..', '..', 'assets', 'css', 'field-kit.css' ),
			'utf8'
		)
	);

	if ( ! placed ) {
		console.error( '  Combobox: the list is not measured from the input' );
		failures ++;
	}

	if ( stretched ) {
		console.error( '  Combobox: the list is stretched across its wrapper again' );
		failures ++;
	}
} )();

/*
 * Adding a row leaves it usable.
 *
 * The template is rendered for a row that has no position, so both its move
 * buttons come out disabled — and the row that used to be last still has its
 * "move down" switched off. add() renumbered the clone and stopped there, so
 * every added row was stuck where it landed. And nothing ran the modules over
 * the new markup, so a combobox, colour picker or media button inside a row
 * added after page load was inert: it rendered, and did nothing.
 *
 * Both are invisible to the PHP suite, which never adds a row.
 */
( function () {
	/**
	 * A repeater row carrying a pair of move buttons.
	 *
	 * @param {boolean} up   Whether "move up" starts disabled.
	 * @param {boolean} down Whether "move down" starts disabled.
	 * @return {object} The row.
	 */
	function makeRow( up, down ) {
		const moveUp = Object.assign( makeElement(), { disabled: up, dataset: { action: 'move-up' } } );
		const moveDown = Object.assign( makeElement(), { disabled: down, dataset: { action: 'move-down' } } );
		const row = makeElement();

		row.moveUp = moveUp;
		row.moveDown = moveDown;
		row.classList.contains = ( name ) => 'field-kit__repeater-row' === name;
		row.querySelector = ( selector ) => {
			if ( selector.includes( 'move-up' ) ) {
				return moveUp;
			}

			if ( selector.includes( 'move-down' ) ) {
				return moveDown;
			}

			return null;
		};
		row.querySelectorAll = () => [];
		row.cloneNode = () => makeRow( up, down );

		return row;
	}

	// One saved row, and a template whose row arrives with both moves off.
	const first = makeRow( true, true );
	const list = makeElement();

	list.children = [ first ];
	list.classList.contains = ( name ) => 'field-kit__repeater-rows' === name;
	list.querySelectorAll = () => list.children;
	list.appendChild = ( node ) => list.children.push( node );

	const template = makeElement();
	template.content = { firstElementChild: makeRow( true, true ) };

	const wrap = makeElement();
	list.closest = () => wrap;
	wrap.dataset = { fieldName: 'rates' };
	wrap.querySelector = ( selector ) => {
		if ( selector.includes( 'template' ) ) {
			return template;
		}

		if ( selector.includes( 'rows' ) ) {
			return list;
		}

		return null;
	};

	const initialised = [];
	const realInit = modules.init;
	modules.init = ( root ) => initialised.push( root );

	try {
		modules.Repeater.add( wrap );
	} catch ( error ) {
		console.error( `  Repeater.add threw: ${ error.message }` );
		failures ++;
	} finally {
		modules.init = realInit;
	}

	const added = list.children[ 1 ];

	if ( ! added ) {
		console.error( '  Repeater: no row was added' );
		failures ++;
	} else {
		if ( added.moveUp.disabled ) {
			console.error( '  Repeater: an added row cannot be moved up; it kept the template\'s disabled state' );
			failures ++;
		}

		if ( first.moveDown.disabled ) {
			console.error( '  Repeater: the row above an added one still cannot be moved down' );
			failures ++;
		}

		if ( ! first.moveUp.disabled ) {
			console.error( '  Repeater: the first row was given a move up' );
			failures ++;
		}

		if ( ! added.moveDown.disabled ) {
			console.error( '  Repeater: the last row was given a move down' );
			failures ++;
		}

		if ( ! initialised.includes( added ) ) {
			console.error( '  Repeater: the added row was never initialised; anything enhanced inside it is inert' );
			failures ++;
		}

		if ( 'false' !== list.dataset.empty ) {
			console.error( '  Repeater: the list still reports itself empty' );
			failures ++;
		}
	}
} )();

/*
 * Renumbering admits the template's index.
 *
 * The template row is rendered at -1, an index no saved row can hold, and
 * the pattern that renumbers names matched digits only. Every added row
 * kept [-1], so two of them posted as one, and dragging could not move them.
 */
( function () {
	/**
	 * A row whose inputs carry the template's index.
	 *
	 * @return {object} The row.
	 */
	function makeRow() {
		const input = Object.assign( makeElement(), { name: 'rates[-1][amount]' } );
		const row = makeElement();

		row.input = input;
		row.querySelectorAll = ( selector ) => ( '[name]' === selector ? [ input ] : [] );

		return row;
	}

	const rows = [ makeRow(), makeRow() ];
	const list = makeElement();
	const wrap = makeElement();

	list.children = rows;
	list.closest = () => wrap;
	wrap.dataset = { fieldName: 'rates' };

	try {
		modules.Repeater.reindex( list );
	} catch ( error ) {
		console.error( `  Repeater.reindex threw: ${ error.message }` );
		failures ++;
	}

	const names = rows.map( ( row ) => row.input.name );

	if ( 'rates[0][amount]' !== names[ 0 ] || 'rates[1][amount]' !== names[ 1 ] ) {
		console.error( `  Repeater: rows cloned from the template are not renumbered: ${ JSON.stringify( names ) }` );
		failures ++;
	}
} )();

/*
 * Reordering chips reaches the combobox.
 *
 * Combobox lives in the block before Reorder. Referred to bare from
 * Reorder.sync, it was a ReferenceError the first time a sortable chip
 * moved -- and the new order never reached the select, so it was lost on save.
 */
( function () {
	const select = Object.assign( makeElement(), { options: [] } );
	const wrap = Object.assign( makeElement(), { querySelector: ( selector ) => ( 'select' === selector ? select : null ) } );
	const chips = makeElement();

	chips.classList.contains = ( name ) => 'field-kit__combobox-chips' === name;
	chips.closest = () => wrap;

	try {
		modules.Reorder.sync( chips );
	} catch ( error ) {
		console.error( `  Reorder.sync on chips threw: ${ error.message }` );
		failures ++;
	}
} )();

/*
 * A root is bound once.
 *
 * init() runs at DOMContentLoaded and again at load, and for every added
 * row. A module listening on the root itself bound a second handler each
 * time: two copies to the clipboard, and every conditional field evaluated
 * twice per keystroke.
 */
( function () {
	const root = makeElement();
	const listeners = [];

	root.addEventListener = ( type ) => listeners.push( type );

	modules.Clipboard.init( root );
	modules.Clipboard.init( root );
	modules.Toggle.init( root );
	modules.Toggle.init( root );
	modules.Conditions.init( root );
	modules.Conditions.init( root );
	modules.ActionButton.init( root );
	modules.ActionButton.init( root );

	// One click for Clipboard, one change for Toggle, three for Conditions,
	// one click for ActionButton.
	if ( 6 !== listeners.length ) {
		console.error( `  a second init() bound the root again: ${ JSON.stringify( listeners ) }` );
		failures ++;
	}
} )();

/*
 * An added gallery tile is the server's tile.
 *
 * The script cloned the first tile when there was one and built its own
 * when there was not -- a tile with chevrons and no drag handle. It clones
 * the template the server ships now, and names the attachment in the
 * labels the template left as {name}.
 */
( function () {
	const handle = Object.assign( makeElement(), { attributes: {} } );
	handle.getAttribute = ( name ) => handle.attributes[ name ] ?? null;
	handle.setAttribute = ( name, value ) => { handle.attributes[ name ] = value; };
	handle.setAttribute( 'aria-label', 'Reorder {name}, 1 of 1' );
	handle.setAttribute( 'data-label-template', 'Reorder {name}, {position} of {total}' );

	const tile = makeElement();
	tile.querySelectorAll = ( selector ) => ( selector.includes( 'aria-label' ) ? [ handle ] : [] );
	tile.cloneNode = () => tile;

	const template = makeElement();
	template.content = { firstElementChild: tile };

	const list = makeElement();
	list.children = [];
	list.querySelector = () => null;
	list.appendChild = ( node ) => list.children.push( node );

	const wrap = makeElement();
	wrap.dataset = {};
	wrap.querySelector = ( selector ) => {
		if ( selector.includes( 'template' ) ) {
			return template;
		}

		if ( selector.includes( 'items' ) ) {
			return list;
		}

		return null;
	};

	try {
		modules.Gallery.append( wrap, { id: 7, title: 'Kick drum', sizes: {} } );
	} catch ( error ) {
		console.error( `  Gallery.append threw: ${ error.message }` );
		failures ++;
	}

	if ( 1 !== list.children.length ) {
		console.error( '  Gallery: no tile was added from the template' );
		failures ++;
	}

	if ( 'Reorder Kick drum, 1 of 1' !== handle.getAttribute( 'aria-label' ) ) {
		console.error( `  Gallery: the template's {name} was not filled in: ${ handle.getAttribute( 'aria-label' ) }` );
		failures ++;
	}

	if ( source.includes( 'field-kit__gallery-move' ) ) {
		console.error( '  Gallery: the script still builds a tile with chevrons' );
		failures ++;
	}
} )();

/*
 * The email panel stands aside where core already toggles postboxes.
 *
 * core's postboxes.js binds '.postbox .hndle, .postbox .handlediv' across the
 * whole document on any screen that calls add_postbox_toggles(). The email
 * editor renders as a postbox with a .handlediv, so inside a metabox both
 * handlers fired, the panel toggled twice, and the collapse appeared to do
 * nothing at all.
 */
( function () {
	const panel = makeElement();
	const button = makeElement();
	const header = makeElement();

	let expanded = 'true';
	button.getAttribute = () => expanded;
	button.setAttribute = ( name, value ) => {
		if ( 'aria-expanded' === name ) {
			expanded = value;
		}
	};

	const clicks = [];
	button.addEventListener = ( type, handler ) => clicks.push( handler );
	header.addEventListener = () => {};

	panel.querySelector = ( selector ) => {
		if ( selector.includes( 'email-toggle' ) ) {
			return button;
		}

		if ( selector.includes( 'hndle' ) ) {
			return header;
		}

		return null;
	};

	const root = Object.assign( makeElement(), {
		querySelectorAll: ( selector ) =>
			( selector.includes( 'field-kit__email' ) ? [ panel ] : [] ),
	} );

	modules.EmailPanel.init( root );

	if ( ! clicks.length ) {
		console.error( '  EmailPanel: nothing was bound to the toggle' );
		failures ++;
	} else {
		// On its own, it toggles.
		delete context.window.postboxes;
		clicks[ 0 ]();

		if ( 'false' !== expanded ) {
			console.error( '  EmailPanel: the toggle does not collapse the panel' );
			failures ++;
		}

		// With core handling postboxes, it must not — core's handler is
		// already doing it, and doing it twice is doing nothing.
		expanded = 'true';
		context.window.postboxes = { page: 'post' };
		clicks[ 0 ]();

		if ( 'true' !== expanded ) {
			console.error( '  EmailPanel: toggles as well as core, so the panel never moves' );
			failures ++;
		}

		delete context.window.postboxes;
	}
} )();

/*
 * A single combobox can be put back to holding nothing.
 *
 * The input is a search box and the select is what posts, so emptying the
 * visible text is not clearing the field — the select has to be emptied too,
 * and for a remote control the option has to go, since it only ever existed
 * to carry the current choice.
 *
 * Read out of the source rather than driven through the stub DOM. The button
 * is built during init(), which needs a good deal more of a document than
 * this file has, and a stub elaborate enough to run it would be asserting on
 * the stub. What matters is the three decisions, and each is one line.
 */
( function () {
	const source = fs.readFileSync(
		path.join( __dirname, '..', '..', 'assets', 'js', 'field-kit.js' ),
		'utf8'
	);

	const checks = [
		[
			/var clearable = ! multiple &&[^;]*required/,
			'the clear button is offered to multiple or required controls, which have nothing to clear to',
		],
		[
			/function clearValue\(\)[\s\S]{0,500}select\.innerHTML = ''/,
			'clearing does not empty a remote control\'s option, so the old choice still posts',
		],
		[
			/function clearValue\(\)[\s\S]{0,500}input\.value = ''/,
			'clearing does not empty the input',
		],
		[
			/function clearValue\(\)[\s\S]{0,700}dispatchEvent/,
			'clearing fires no change event, so nothing watching the field hears it',
		],
		[
			/clear\.hidden = '' === select\.value/,
			'the clear button is shown when there is nothing to clear',
		],
		[
			/field-kit__combobox--clearable/,
			'the wrapper is not marked clearable, so the input reserves no room and the text jumps',
		],
	];

	checks.forEach( ( [ pattern, complaint ] ) => {
		if ( ! pattern.test( source ) ) {
			console.error( `  Combobox: ${ complaint }` );
			failures ++;
		}
	} );
} )();

/*
 * A drag moves an item one place, not two.
 *
 * dragover fires continuously — many times per second — and the list has
 * already been reordered underneath the pointer since the previous one.
 * Without a guard, a single crossing keeps re-inserting and the item travels
 * two or three places at once, which is what a drag in a flyout was doing.
 */
( function () {
	const source = fs.readFileSync(
		path.join( __dirname, '..', '..', 'assets', 'js', 'field-kit.js' ),
		'utf8'
	);

	if ( ! /reference === dragging \|\| dragging\.nextElementSibling === reference/.test( source ) ) {
		console.error( '  Reorder: a dragover with nothing to change still re-inserts, so one drag moves several places' );
		failures ++;
	}

	if ( ! /if \( ! over \|\| over === dragging \)/.test( source ) ) {
		console.error( '  Reorder: dragover no longer bails when there is nothing under the pointer' );
		failures ++;
	}

	/*
	 * Which halfway line a drop is measured against.
	 *
	 * A gallery is a grid, so its tiles sit side by side and the question is
	 * left-or-right. Asking top-or-bottom there gives two tiles in the same
	 * row the same answer over most of their area, and a tile could not be
	 * dropped into the first position at all.
	 *
	 * The helper is pulled out of the source rather than restated, so this
	 * cannot pass against a copy that has drifted.
	 */
	// That it exists is half of it; the other half is that dragover asks it.
	// Testing the helper alone passes against a call site that has gone back
	// to measuring vertically, which is exactly the bug.
	/*
	 * A handler is given the field's own value under `value`.
	 *
	 * Without it a handler has to know the field key to find it, and the key
	 * differs per screen for the same field: a licence is `apfd_license` on a
	 * term screen and `apfd_every_license` on a settings page, so a handler
	 * written for one silently read nothing on the other and reported an
	 * empty box no matter what had been typed into it.
	 */
	if ( ! /payload\.value = input\.value/.test( source ) ) {
		console.error( '  ActionButton: the payload no longer carries the field\'s own value under `value`' );
		failures ++;
	}

	if ( ! /past = Reorder\.isHorizontal\( over \)/.test( source ) ) {
		console.error( '  Reorder: dragover no longer asks which way the list runs, so a grid is measured vertically' );
		failures ++;
	}

	const helper = source.match( /isHorizontal: function \( item \) \{[\s\S]*?\n\t\t\},/ );

	if ( ! helper ) {
		console.error( '  Reorder: isHorizontal is gone, so a grid is measured vertically again' );
		failures ++;
	} else {
		const Reorder = eval( '({ ' + helper[ 0 ] + ' })' );

		const at = ( left, top ) => ( {
			getBoundingClientRect: () => ( { left, top, width: 150, height: 150 } ),
			nextElementSibling: null,
			previousElementSibling: null,
		} );

		const link = ( items ) => {
			items.forEach( ( one, index ) => {
				one.nextElementSibling = items[ index + 1 ] || null;
				one.previousElementSibling = items[ index - 1 ] || null;
			} );

			return items;
		};

		const side = ( over, x, y ) => {
			const box = over.getBoundingClientRect();

			const past = Reorder.isHorizontal( over )
				? ( x - box.left ) / box.width > 0.5
				: ( y - box.top ) / box.height > 0.5;

			return past ? 'after' : 'before';
		};

		// Two columns, so the first two tiles share a row.
		const grid = link( [ at( 0, 0 ), at( 160, 0 ), at( 0, 160 ) ] );

		if ( 'before' !== side( grid[ 0 ], 20, 75 ) ) {
			console.error( '  Reorder: a tile cannot be dropped into the first position of a grid' );
			failures ++;
		}

		if ( 'after' !== side( grid[ 0 ], 130, 75 ) ) {
			console.error( '  Reorder: the right half of a grid tile does not drop after it' );
			failures ++;
		}

		// Rows under each other, where the question really is top-or-bottom.
		const rows = link( [ at( 0, 0 ), at( 0, 160 ) ] );

		if ( 'before' !== side( rows[ 0 ], 75, 10 ) || 'after' !== side( rows[ 0 ], 75, 140 ) ) {
			console.error( '  Reorder: a stacked list is no longer measured vertically' );
			failures ++;
		}
	}
} )();

/*
 * A licence field takes the state its handler reported.
 *
 * The whole point of the field is to say whether the licence is active, so an
 * activation that succeeds and leaves the badge reading "Not active" with a
 * seat count of nought is the one outcome that must not happen — and it is
 * exactly what shipped: the handler's answer went into the status line and
 * nowhere else, and the field told the user to reload.
 *
 * Written against a stub rather than jsdom for the same reason as the rest of
 * this file, but with real classList and textContent, because those are what
 * is being asserted.
 */
( function () {
	/**
	 * An element that remembers what was done to it.
	 *
	 * @param {string} selector What querySelector call should return it.
	 *
	 * @return {Object} The element.
	 */
	function node( selector ) {
		const classes = new Set();

		return {
			selector,
			dataset: {},
			textContent: '',
			hidden: false,
			readOnly: false,
			value: '',
			classList: {
				add: ( name ) => classes.add( name ),
				remove: ( name ) => classes.delete( name ),
				contains: ( name ) => classes.has( name ),
				toggle: ( name, on ) => ( on ? classes.add( name ) : classes.delete( name ) ),
			},
			querySelector: () => null,
		};
	}

	const licence = node( '.field-kit__license' );
	const badge = node( '.field-kit__license-state' );
	const text = node( '.field-kit__license-state-text' );
	const icon = node( '.dashicons' );
	const button = node( '.field-kit__license-action' );
	const input = node( '.field-kit__license-key' );
	const meta = node( '.field-kit__license-meta' );
	const sites = node( '.field-kit__license-sites' );

	licence.dataset.labelActive = 'Active';
	licence.dataset.labelInactive = 'Not active';
	meta.dataset.sitesTemplate = '%1$s of %2$s sites';
	button.dataset.actionActivate = 'demo_activate';
	button.dataset.actionDeactivate = 'demo_deactivate';
	button.dataset.labelActivate = 'Activate';
	button.dataset.labelDeactivate = 'Deactivate';
	button.dataset.action = 'demo_activate';

	badge.querySelector = ( one ) => ( '.dashicons' === one ? icon : null );
	badge.classList.add( 'field-kit__license-state--inactive' );
	icon.classList.add( 'dashicons-marker' );
	sites.hidden = true;
	text.textContent = 'Not active';

	const found = [ licence, badge, text, button, input, meta, sites ];
	const wrap = makeElement();

	wrap.querySelector = ( one ) => found.find( ( each ) => each.selector === one ) || null;

	const ActionButton = modules.ActionButton;

	if ( typeof ActionButton.applyState !== 'function' ) {
		console.error( '  ActionButton: applyState is gone, so a licence never leaves the state it loaded in' );
		failures ++;
	} else {
		// Through report(), not applyState() directly. Testing the helper on
		// its own is how the Reorder bug got past: the helper was correct and
		// nothing called it. This is the path a real response takes.
		const respond = ( state ) => ActionButton.report(
			wrap,
			null,
			{ success: true, message: 'Done.', data: { state } }
		);

		respond( { active: true, key: '*********9012', sites: [ 1, 3 ] } );

		if ( 'Active' !== text.textContent ) {
			console.error( `  License: the badge still reads "${ text.textContent }" after activating` );
			failures ++;
		}

		if ( ! badge.classList.contains( 'field-kit__license-state--active' )
			|| badge.classList.contains( 'field-kit__license-state--inactive' ) ) {
			console.error( '  License: the badge kept its inactive class after activating' );
			failures ++;
		}

		if ( ! icon.classList.contains( 'dashicons-yes-alt' ) || icon.classList.contains( 'dashicons-marker' ) ) {
			console.error( '  License: the badge icon did not change' );
			failures ++;
		}

		if ( '1 of 3 sites' !== sites.textContent || sites.hidden ) {
			console.error( `  License: the seat count reads "${ sites.textContent }" (hidden: ${ sites.hidden })` );
			failures ++;
		}

		// The button has to become the other one, or the next press calls the
		// handler that just ran.
		if ( 'Deactivate' !== button.textContent || 'demo_deactivate' !== button.dataset.action ) {
			console.error( `  License: the button is still "${ button.textContent }" (${ button.dataset.action })` );
			failures ++;
		}

		if ( ! input.readOnly || '*********9012' !== input.value ) {
			console.error( '  License: the key box is still editable, or still holds the typed key' );
			failures ++;
		}

		// And back, which is not symmetrical for free: the reverse swap reads
		// a different pair of data attributes.
		respond( { active: false, key: 'AP-1234-5678-9012', sites: [ 0, 3 ] } );

		if ( 'Not active' !== text.textContent
			|| 'Activate' !== button.textContent
			|| 'demo_activate' !== button.dataset.action
			|| input.readOnly
			|| '0 of 3 sites' !== sites.textContent
			|| ! badge.classList.contains( 'field-kit__license-state--inactive' )
			|| badge.classList.contains( 'field-kit__license-state--active' )
			|| ! icon.classList.contains( 'dashicons-marker' ) ) {
			console.error( '  License: deactivating does not put the field back' );
			failures ++;
		}
	}
} )();

/**
 * An element that remembers its attributes and classes.
 *
 * For the tests that assert on what a module wrote, rather than only that
 * it ran: makeElement() forgets everything set on it, which is right for a
 * smoke test and useless for one that reads the result back.
 *
 * @return {Object} The element.
 */
function recorder() {
	const classes = new Set();
	const attributes = {};

	return {
		dataset: {},
		type: '',
		value: '',
		textContent: '',
		classList: {
			add: ( name ) => classes.add( name ),
			remove: ( name ) => classes.delete( name ),
			contains: ( name ) => classes.has( name ),
			toggle: ( name, on ) => ( on ? classes.add( name ) : classes.delete( name ) ),
		},
		getAttribute: ( name ) => ( name in attributes ? attributes[ name ] : null ),
		setAttribute: ( name, value ) => {
			attributes[ name ] = String( value );
		},
		querySelector: () => null,
		closest: () => null,
		addEventListener() {},
	};
}

/*
 * A reveal button shows what has been typed, and says so.
 *
 * The input's type is what does it, and the button has to describe the
 * state it put the input in -- icon, pressed state and label together, or a
 * screen reader hears "Show password" over a field already showing it. The
 * PHP suite can render the button but never press it.
 */
( function () {
	const icon = recorder();
	const input = recorder();
	const wrap = recorder();
	const button = recorder();
	const clicks = [];

	icon.classList.add( 'dashicons' );
	icon.classList.add( 'dashicons-visibility' );
	input.type = 'password';
	wrap.querySelector = ( selector ) => ( 'input' === selector ? input : null );
	button.closest = () => wrap;
	button.querySelector = ( selector ) => ( '.dashicons' === selector ? icon : null );
	button.addEventListener = ( type, handler ) => clicks.push( handler );
	button.setAttribute( 'aria-label', 'Show password' );
	button.setAttribute( 'aria-pressed', 'false' );
	button.setAttribute( 'data-toggle', '0' );

	const root = Object.assign( makeElement(), {
		querySelectorAll: ( selector ) => ( selector.includes( 'password-toggle' ) ? [ button ] : [] ),
	} );

	// Twice, because init() runs twice on a normal page load.
	modules.PasswordReveal.init( root );
	modules.PasswordReveal.init( root );

	if ( 1 !== clicks.length ) {
		console.error( `  PasswordReveal: expected one click handler on the button, got ${ clicks.length }` );
		failures ++;

		return;
	}

	clicks[ 0 ]();

	if ( 'text' !== input.type ) {
		console.error( '  PasswordReveal: the input is still a password after a press' );
		failures ++;
	}

	if ( ! icon.classList.contains( 'dashicons-hidden' ) || icon.classList.contains( 'dashicons-visibility' ) ) {
		console.error( '  PasswordReveal: the icon did not change' );
		failures ++;
	}

	if ( 'true' !== button.getAttribute( 'aria-pressed' ) || '1' !== button.getAttribute( 'data-toggle' ) ) {
		console.error( '  PasswordReveal: the button does not report itself pressed' );
		failures ++;
	}

	if ( 'Hide password' !== button.getAttribute( 'aria-label' ) ) {
		console.error( `  PasswordReveal: the label still reads "${ button.getAttribute( 'aria-label' ) }" over a shown password` );
		failures ++;
	}

	clicks[ 0 ]();

	if ( 'password' !== input.type
		|| ! icon.classList.contains( 'dashicons-visibility' )
		|| icon.classList.contains( 'dashicons-hidden' )
		|| 'false' !== button.getAttribute( 'aria-pressed' )
		|| '0' !== button.getAttribute( 'data-toggle' )
		|| 'Show password' !== button.getAttribute( 'aria-label' ) ) {
		console.error( '  PasswordReveal: a second press does not hide the password again' );
		failures ++;
	}
} )();

/*
 * A count follows what is typed.
 *
 * Rendered by PHP with the saved value's length and then left to the
 * script, which is the half the PHP suite cannot reach. It also has to find
 * its control through the wrapper an affix puts round the input, or every
 * adorned field with a limit has a count that never moves.
 */
( function () {
	const control = recorder();
	const counter = recorder();
	const listeners = [];

	control.tagName = 'INPUT';
	control.value = 'hello';
	control.setAttribute( 'maxlength', '8' );
	control.addEventListener = ( type, handler ) => listeners.push( [ type, handler ] );
	counter.textContent = '5 / 8';
	counter.previousElementSibling = control;

	modules.Counter.init( Object.assign( makeElement(), {
		querySelectorAll: ( selector ) => ( '[data-counter]' === selector ? [ counter ] : [] ),
	} ) );

	const bound = listeners.find( ( [ type ] ) => 'input' === type );

	if ( ! bound ) {
		console.error( '  Counter: nothing listens to the control, so the count never moves' );
		failures ++;

		return;
	}

	const update = bound[ 1 ];

	control.value = 'hello wor';
	update();

	if ( '9 / 8' !== counter.textContent || ! counter.classList.contains( 'field-kit__count--over' ) ) {
		console.error( `  Counter: reads "${ counter.textContent }" for nine of eight, over: ${ counter.classList.contains( 'field-kit__count--over' ) }` );
		failures ++;
	}

	control.value = 'hi';
	update();

	if ( '2 / 8' !== counter.textContent || counter.classList.contains( 'field-kit__count--over' ) ) {
		console.error( '  Counter: does not come back under the limit' );
		failures ++;
	}

	// Characters, as PHP counted them, not code units.
	control.value = '\u{1F600}\u{1F600}';
	update();

	if ( '2 / 8' !== counter.textContent ) {
		console.error( `  Counter: two emoji read as "${ counter.textContent }"` );
		failures ++;
	}

	// Through the wrapper an affix puts round the input.
	const wrapped = recorder();
	const second = recorder();
	const found = [];

	wrapped.tagName = 'SPAN';
	wrapped.querySelector = ( selector ) => ( 'input, textarea' === selector ? control : null );
	second.previousElementSibling = wrapped;
	control.addEventListener = ( type ) => found.push( type );

	modules.Counter.init( Object.assign( makeElement(), {
		querySelectorAll: ( selector ) => ( '[data-counter]' === selector ? [ second ] : [] ),
	} ) );

	if ( ! found.includes( 'input' ) ) {
		console.error( '  Counter: cannot find its control through an affix wrapper' );
		failures ++;
	}
} )();

/*
 * A region follows the country beside it.
 *
 * PHP draws a text input, live, and a select, empty and disabled, because
 * it cannot know the country at render time. The script asks the endpoint
 * for that country's regions and swaps to the select when there are some,
 * and only then -- a hidden control still posts, so the one standing down
 * has to be disabled, or both submit under one name. And a slow answer for
 * the previous country must not land after the answer for the current one.
 *
 * Asynchronous, because fetch is, so the report waits for it.
 */
const regionChecks = ( async function () {
	/**
	 * An element that keeps the options and listeners given to it.
	 *
	 * @return {object} The element.
	 */
	function control() {
		const element = makeElement();

		element.children = [];
		element.listeners = {};
		element.appendChild = ( node ) => element.children.push( node );
		element.addEventListener = ( type, handler ) => {
			element.listeners[ type ] = handler;
		};

		// Setting textContent on a select empties it; the stub mirrors that.
		Object.defineProperty( element, 'textContent', {
			get: () => element.text || '',
			set: ( value ) => {
				element.text = value;
				element.children = [];
			},
		} );

		return element;
	}

	/**
	 * A region field beside a country control holding the given code.
	 *
	 * @param {string} code The country's value.
	 * @return {object} The parts.
	 */
	function makeRegion( code ) {
		const country = Object.assign( control(), { value: code, name: 'country' } );
		const row = Object.assign( makeElement(), { querySelector: () => country } );
		const select = Object.assign( control(), { disabled: true, hidden: true } );
		const text = Object.assign( control(), { value: 'CA' } );
		const wrap = makeElement();

		wrap.dataset = {
			countryKey: 'country',
			searchEndpoint: 'https://example.test/wp-json/field-kit/v1/search',
			searchNonce: 'n',
		};
		wrap.closest = ( selector ) => ( selector.includes( 'repeater-row' ) ? row : null );
		wrap.querySelector = ( selector ) => {
			if ( selector.includes( 'region-select' ) ) {
				return select;
			}

			if ( selector.includes( 'region-text' ) ) {
				return text;
			}

			return null;
		};

		const root = Object.assign( makeElement(), {
			querySelectorAll: ( selector ) => ( selector.includes( 'field-kit__region' ) ? [ wrap ] : [] ),
		} );

		return { country, select, text, wrap, root };
	}

	// Every request is held until the test answers it, so order can be
	// chosen.
	const requests = [];
	const realFetch = context.fetch;

	context.fetch = ( url ) => new Promise( ( resolve ) => {
		requests.push( {
			url: String( url ),
			answer: ( results ) => resolve( { ok: true, json: () => Promise.resolve( { results } ) } ),
		} );
	} );

	const settle = () => new Promise( ( resolve ) => setTimeout( resolve, 0 ) );

	try {
		// Regions come back: the select takes over, holding the stored value.
		const one = makeRegion( 'US' );

		modules.Region.init( one.root );

		if ( 1 !== requests.length ) {
			console.error( '  Region: nothing was asked for the country beside it' );
			failures ++;
		} else {
			const url = requests[ 0 ].url;

			if ( ! url.includes( 'source=region' ) || ! /args(%5B|\[)country(%5D|\])=US/.test( url ) ) {
				console.error( `  Region: asked the endpoint the wrong question: ${ url }` );
				failures ++;
			}

			requests[ 0 ].answer( [ { id: 'CA', text: 'California' }, { id: 'NY', text: 'New York' } ] );
			await settle();

			if ( one.select.disabled || one.select.hidden ) {
				console.error( '  Region: the select is not live after regions arrived' );
				failures ++;
			}

			if ( ! one.text.disabled || ! one.text.hidden ) {
				console.error( '  Region: the text input still submits beside the select' );
				failures ++;
			}

			const values = one.select.children.map( ( option ) => option.value );

			if ( '["","CA","NY"]' !== JSON.stringify( values ) ) {
				console.error( `  Region: the select holds ${ JSON.stringify( values ) }` );
				failures ++;
			}

			const chosen = one.select.children.filter( ( option ) => option.selected ).map( ( option ) => option.value );

			if ( '["CA"]' !== JSON.stringify( chosen ) ) {
				console.error( `  Region: the stored value was not kept selected: ${ JSON.stringify( chosen ) }` );
				failures ++;
			}

			if ( 'California' !== one.select.children[ 1 ].textContent ) {
				console.error( '  Region: an option is not labelled with its name' );
				failures ++;
			}

			// The country changes to one with no list: back to the text input.
			one.country.value = 'GB';
			one.country.listeners.change();
			requests[ 1 ].answer( [] );
			await settle();

			if ( ! one.select.disabled || ! one.select.hidden || one.select.children.length ) {
				console.error( '  Region: a country with no regions left the select live' );
				failures ++;
			}

			if ( one.text.disabled || one.text.hidden ) {
				console.error( '  Region: the text input did not come back' );
				failures ++;
			}
		}

		// No regions from the start: the text input stays as drawn.
		const two = makeRegion( 'GB' );

		modules.Region.init( two.root );
		requests[ requests.length - 1 ].answer( [] );
		await settle();

		if ( two.text.disabled || two.text.hidden || ! two.select.disabled || ! two.select.hidden ) {
			console.error( '  Region: a country with no regions did not stay on the text input' );
			failures ++;
		}

		// A slow answer for the previous country arrives after the current
		// one and must be ignored.
		const three = makeRegion( 'US' );

		modules.Region.init( three.root );
		const stale = requests[ requests.length - 1 ];

		three.country.value = 'GB';
		three.country.listeners.change();
		requests[ requests.length - 1 ].answer( [] );
		await settle();
		stale.answer( [ { id: 'CA', text: 'California' } ] );
		await settle();

		if ( ! three.select.disabled || three.text.disabled ) {
			console.error( '  Region: a slow answer for the previous country replaced the current one' );
			failures ++;
		}

		// No country control to be found: nothing is asked, text stays live.
		const four = makeRegion( 'US' );
		const asked = requests.length;

		four.wrap.closest = () => null;
		modules.Region.init( four.root );

		if ( requests.length !== asked || four.text.disabled || ! four.select.disabled ) {
			console.error( '  Region: a field with no country beside it did not leave the text input live' );
			failures ++;
		}
	} finally {
		context.fetch = realFetch;
	}
} )();

/*
 * An accordion trigger opens its panel, and says so.
 *
 * The markup is core's but the privacy screen's script is not loaded
 * anywhere else, so the toggle is ours: the button's expanded state and the
 * panel's hidden attribute have to move together or a screen reader hears
 * one thing and sees another.
 */
( function () {
	const button = recorder();
	const panel = recorder();
	const clicks = [];
	const realGetElementById = context.document.getElementById;

	button.dataset = {};
	button.setAttribute( 'aria-expanded', 'false' );
	button.setAttribute( 'aria-controls', 'fk-accordion-1' );
	button.addEventListener = ( type, handler ) => clicks.push( handler );
	panel.hidden = true;

	const root = Object.assign( makeElement(), {
		querySelectorAll: ( selector ) => ( selector.includes( 'accordion-trigger' ) ? [ button ] : [] ),
	} );

	context.document.getElementById = ( id ) => ( 'fk-accordion-1' === id ? panel : null );

	try {
		modules.Accordion.init( root );
		modules.Accordion.init( root );

		if ( 1 !== clicks.length ) {
			console.error( `  Accordion: expected one click handler on the trigger, got ${ clicks.length }` );
			failures ++;

			return;
		}

		clicks[ 0 ]();

		if ( panel.hidden || 'true' !== button.getAttribute( 'aria-expanded' ) ) {
			console.error( '  Accordion: a press did not open the panel and say so' );
			failures ++;
		}

		clicks[ 0 ]();

		if ( ! panel.hidden || 'false' !== button.getAttribute( 'aria-expanded' ) ) {
			console.error( '  Accordion: a second press did not close the panel again' );
			failures ++;
		}
	} finally {
		context.document.getElementById = realGetElementById;
	}
} )();

/**
 * The report, once the asynchronous checks have settled.
 */
function finish() {
	if ( failures ) {
		console.error( `\n${ failures } failure(s)` );
		process.exit( 1 );
	}

	console.log( `  ${ expected.length } modules loaded and initialised cleanly, colour picker signals correctly, an added repeater row is live and renumbered, chips reorder, a root binds once, a password reveals, a count counts, a region follows its country, an accordion opens and closes` );
}

regionChecks.then( finish, ( error ) => {
	console.error( `  Region: ${ error.message }` );
	failures ++;
	finish();
} );
