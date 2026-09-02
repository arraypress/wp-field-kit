# Field Kit

Render, sanitise and store an admin form field — once, so nothing has to
render one again.

## What it does

Every library that puts a field on a screen ends up writing the same three
things: the markup, the sanitising, and the reading and writing of wherever
the value lives. Do it five times and you have five subtly different text
inputs, four of which forget `esc_attr` somewhere.

This is the one implementation. A field describes itself; the kit renders it,
coerces what comes back by type, and reads and writes it through a context —
post meta, term meta, an option, an array — so the same declaration works
wherever the screen happens to be.

## Features

* Render any of the field types below, escaped, with a label and description
* Sanitise on save by type, rather than a callback per field
* Store to post meta, term meta, user meta, an option or a plain array
* Show a field only when another has a given value
* Search posts, users or terms from one endpoint, whatever the field type
* Group fields into tabs or collapsible sections
* Sit two short fields on one line with `'width' => 'half'`
* Take an amount with its currency symbol inside the control
* Fill a select from a named preset — roles, timezones, countries — with one string
* Put a unit inside any input — a `$` before a price, `days` after a number
* Reveal a password as it is typed, and count characters against a limit
* Fold a repeater's rows away, each titled by one of its own fields
* Reorder a repeater by dragging its handle, or from the keyboard
* Refuse a value that fails a rule — required, email, url, your own callable — and say why
* Encrypt a value at rest, for an API key
* Get told, under `WP_DEBUG`, when a config key does nothing

## Installation

```bash
composer require arraypress/wp-field-kit
```

## Quick start

```php
use ArrayPress\FieldKit\FieldSet;
use ArrayPress\FieldKit\Context\OptionContext;

$set = new FieldSet(
	[
		'site_name' => [ 'type' => 'text', 'label' => __( 'Site name', 'my-plugin' ) ],
		'per_page'  => [ 'type' => 'number', 'label' => __( 'Per page', 'my-plugin' ), 'min' => 1 ],
	],
	new OptionContext( 'my_plugin' ),
	'my_plugin'
);

echo $set->render();

// Each value is coerced by its own type; a key you did not declare is dropped.
$set->save( $_POST['my_plugin'] ?? [] );
```

## Field types

Text and numeric
: `text` `email` `url` `tel` `password` `hidden` `number` `percentage` `range` `textarea` `code` `wysiwyg` `code_generator`

Date, time and colour
: `date` `time` `datetime` `color` `gradient` `date_range` `time_range`

Choice
: `select` `enhanced_select` `select_multiple` `checkbox` `toggle` `radio` `checkbox_group` `button_group` `card_choice` `country` `region` `currency`

Relational — one search endpoint behind all of them
: `post` `page` `user` `taxonomy` `ajax` `tags`

Media
: `image` `file` `file_url` `gallery` `files`

Compound
: `group` `repeater` `list` `key_value` `sortable` `providers` `dimensions` `amount_type` `money` `flexible`

Layout and display
: `heading` `separator` `message` `html` `link` `clipboard` `oembed` `custom` `tab` `accordion` `icon` `nav_menu`

Purpose-built
: `license` `email_editor` `action_button`


## Beyond the quick start

Sanitising coerces; it does not refuse. `required` and `validate` do the
refusing on the server: a value that fails is left where it was, every other
field is saved, and the message is kept for the next render. Options can name
a preset instead of listing choices. Any single-line input takes a `prefix`
or a `suffix`, a password can be revealed, and a `maxlength` shows a count. A
`region` follows the country beside it.

```php
$set = new FieldSet(
	[
		'contact'  => [ 'type' => 'email', 'label' => __( 'Contact', 'my-plugin' ), 'required' => true ],
		'notify'   => [ 'type' => 'tags', 'label' => __( 'Notify', 'my-plugin' ), 'validate' => 'email' ],
		'api_key'  => [
			'type'     => 'password',
			'label'    => __( 'API key', 'my-plugin' ),
			'reveal'   => true,
			'validate' => static fn( $value ) => str_starts_with( $value, 'sk_' ) ? true : __( 'An API key starts with sk_.', 'my-plugin' ),
		],
		'role'     => [ 'type' => 'select', 'label' => __( 'Notify role', 'my-plugin' ), 'options' => 'roles' ],
		'price'    => [ 'type' => 'number', 'label' => __( 'Price', 'my-plugin' ), 'prefix' => '$', 'step' => 0.01 ],
		'discount' => [ 'type' => 'percentage', 'label' => __( 'Discount', 'my-plugin' ) ],
		'tagline'  => [ 'type' => 'text', 'label' => __( 'Tagline', 'my-plugin' ), 'maxlength' => 80 ],
		'country'  => [ 'type' => 'country', 'label' => __( 'Country', 'my-plugin' ), 'continents' => true ],
		'region'   => [ 'type' => 'region', 'label' => __( 'State or province', 'my-plugin' ), 'country_key' => 'country' ],
		'currency' => [ 'type' => 'currency', 'label' => __( 'Currency', 'my-plugin' ) ],
	],
	new OptionContext( 'my_plugin' ),
	'my_plugin'
);

$set->save( $_POST['my_plugin'] ?? [] );

// Rendered after a save in the same request, each failing field is marked
// and shows its message; errors() has them for a notice of your own.
echo $set->render();
```

Rules are `email`, `url`, `numeric`, `integer`, `slug` and `alphanumeric`; a
callable returns `true`, a message or a `WP_Error`; on `tags` and `list` the
rule applies to each item; `validate()` checks without storing. Presets are
`roles`, `post_types`, `taxonomies`, `pages`, `timezones`, `image_sizes`,
`page_templates`, `cron_schedules`, `countries` and `currencies`, resolved on
render and filterable, with `Presets::register()` for your own. Countries and
regions come from `arraypress/wp-countries`, currencies from
`arraypress/wp-money`; a region is a select where the country has
subdivisions on file and a text input where it does not.

## Requirements

* PHP 8.3 or later
* WordPress 7.1 or later

## License

GPL-2.0-or-later
