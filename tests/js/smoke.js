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

const expected = [
	'Conditions', 'Range', 'Toggle', 'Clipboard', 'Combobox', 'Reorder',
	'Gallery', 'Repeater', 'Media', 'Tags', 'CodeEditor', 'ColorPicker',
	'TagModal', 'PanelTabs', 'EmailPanel', 'ActionButton',
];

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

if ( failures ) {
	console.error( `\n${ failures } failure(s)` );
	process.exit( 1 );
}

console.log( `  ${ expected.length } modules loaded and initialised cleanly, colour picker signals correctly` );
