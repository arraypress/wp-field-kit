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

## Validation

Sanitising coerces; it does not refuse. A field that must hold something, or
something of a particular shape, says so. A value that fails is left where it
was — the stored one stays, every other field is saved — and the reason is
kept for the next render:

```php
$set = new FieldSet(
	[
		'contact' => [ 'type' => 'email', 'label' => __( 'Contact', 'my-plugin' ), 'required' => true ],
		'handle'  => [ 'type' => 'text', 'label' => __( 'Handle', 'my-plugin' ), 'validate' => 'slug' ],
		'api_key' => [
			'type'     => 'text',
			'label'    => __( 'API key', 'my-plugin' ),
			'validate' => static fn( $value, $field ) => str_starts_with( $value, 'sk_' ) ? true : __( 'An API key starts with sk_.', 'my-plugin' ),
		],
		'notify'  => [ 'type' => 'tags', 'label' => __( 'Notify', 'my-plugin' ), 'validate' => 'email' ],
	],
	new OptionContext( 'my_plugin' ),
	'my_plugin'
);

$set->save( $_POST['my_plugin'] ?? [] );

// Rendered after a save in the same request, each failing field is marked
// and shows its message. errors() has the messages for a notice of your own.
echo $set->render();
```

The rules are `email`, `url`, `numeric`, `integer`, `slug` and `alphanumeric`.
A callable is given the sanitised value and the field, and returns `true`, a
message, or a `WP_Error`. On `tags` and `list` the rule is applied to each
item. To check a submission without storing any of it, `validate()` takes the
same input as `save()` and returns the messages keyed by field.

`required` and `validate` are checked on the set's own fields. A field inside
a group or a repeater row is marked required in the markup, which the browser
enforces, but is not checked on the server.

## Field types

Text and numeric
: `text` `email` `url` `tel` `password` `hidden` `number` `range` `textarea` `code` `wysiwyg` `code_generator`

Date, time and colour
: `date` `time` `datetime` `color` `gradient` `date_range` `time_range`

Choice
: `select` `enhanced_select` `select_multiple` `checkbox` `toggle` `radio` `checkbox_group` `button_group` `card_choice` `country` `currency`

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

## Options from a preset

The lists every settings screen ends up needing — the site's roles, its
timezones, its page templates — are presets, and a select asks for one by
name rather than building it again:

```php
use ArrayPress\FieldKit\FieldSet;
use ArrayPress\FieldKit\Context\OptionContext;

$set = new FieldSet(
	[
		'notify_role' => [ 'type' => 'select', 'label' => __( 'Notify', 'my-plugin' ), 'options' => 'roles' ],
		'timezone'    => [ 'type' => 'select', 'label' => __( 'Timezone', 'my-plugin' ), 'options' => 'timezones' ],
		'country'     => [ 'type' => 'country', 'label' => __( 'Country', 'my-plugin' ), 'continents' => true ],
		'currency'    => [ 'type' => 'currency', 'label' => __( 'Currency', 'my-plugin' ) ],
	],
	new OptionContext( 'my_plugin' ),
	'my_plugin'
);
```

The built-ins are `roles`, `post_types`, `taxonomies`, `pages`, `timezones`,
`image_sizes`, `page_templates`, `cron_schedules`, `countries` and
`currencies`. The last two draw on `arraypress/wp-countries` and
`arraypress/wp-money`; the `country` and `currency` types use them by default.

A preset is resolved when the field renders, not when it is declared, and its
options pass through the `field_kit_preset_options` filter. Register your own
with `Presets::register( 'products', fn() => $options )` and any field can say
`'options' => 'products'`.

## A unit in the box, a reveal, a count

Any single-line input takes a `prefix` or a `suffix`, drawn inside the box
and announced with the value. A `percentage` is a number from 0 to 100 with
the sign already in it. A password shows what is typed on request, and
anything with a `maxlength` counts as you go.

```php
$set = new FieldSet(
	[
		'price'    => [ 'type' => 'number', 'label' => __( 'Price', 'my-plugin' ), 'prefix' => '$', 'step' => 0.01 ],
		'discount' => [ 'type' => 'percentage', 'label' => __( 'Discount', 'my-plugin' ) ],
		'expires'  => [ 'type' => 'number', 'label' => __( 'Expires after', 'my-plugin' ), 'suffix' => 'days' ],
		'api_key'  => [ 'type' => 'password', 'label' => __( 'API key', 'my-plugin' ), 'reveal' => true ],
		'tagline'  => [ 'type' => 'text', 'label' => __( 'Tagline', 'my-plugin' ), 'maxlength' => 80 ],
	],
	new OptionContext( 'my_plugin' ),
	'my_plugin'
);
```

## Requirements

* PHP 8.3 or later
* WordPress 7.1 or later

## License

GPL-2.0-or-later
