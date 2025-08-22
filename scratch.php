<?php
/**
 * A sample of acceptable filter/column data
 */

$filters_and_columns = [
	'key' => [ // Key is required.
		'name'        => 'Filter / Column title', // Required.
		'meta'        => 'Piece of post meta to query', // If it's a meta query, set this to the meta key to be queried.
		'taxonomy'    => 'registered taxonomy', // The taxonomy. APM by default automatically adds taxonomies.
		'custom_type' => 'a key for registering your own query handlers', // Your query type doesn't fit the standard kind.
		'options'     => [ // A way to limit the queried field to a dropdown rather than a search box.
			// The key is the value of the option, the value is the label.
			'meta_value'         => 'Nicer Title',
			'another_meta_value' => 'another_meta_value',
		],
		'cast'        => 'SIGNED', // Optional, for use with "meta" filters. Useful for when you want ordering to assume that meta_values are a certain type, such as numeric or date.
		'field'       => 'text', // What type of field to use in the meta box.
		'desc'        => 'Optional supporting text for display inside a metabox', // Read the value.
		'metabox'     => 'somemetaboxid', // Explicitly put in a particular metabox. Pay attention to the $metaboxes arg on tribe_setup_apm().
		'meta_order'  => 3, // Set an explicit order inside a metabox.
		// Some 'types' take additional arguments.
	],
];
