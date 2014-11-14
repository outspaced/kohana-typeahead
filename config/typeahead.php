<?php defined('SYSPATH') or die('No direct script access.');

return array(
	// Application defaults
	'default' => array
	(
		'typeahead_model'   => 'typeahead',
		'always_create'     => TRUE,
		'list_length'       => 8,
		'maximum_length'    => 6,
		'find_method'       => 'find_for_typeahead',
		'return_format'            => 'json'
	),
	
/*	
	'band' => array (
		'list_length'		=> 10,
	)
*/
);
