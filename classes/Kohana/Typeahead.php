<?php defined('SYSPATH') or die('No direct script access.');

/**
 * @author  Alex Brims
 * @package Typeahead
 */
class Kohana_Typeahead
{
	/**
	 * @var	Model
	 */
	protected $_model;

	/**
	 * @var	Typeahead_Model
	 */
	protected $_typeahead_model;

	/**
	 * @var Typeahead_Type_Model
	 */
	protected $_typeahead_type_model;
	
	/**
	 * 
	 * @param Model  $model
	 * @param array	 $config
	 */
	public function __construct(Model $model, array $config=array())
	{
		// If config's been passed in, use that
		if ($config)
		{
			$this->config = $config;
		}
		else
		{
			// Otherwise load from out config file
			$config = Kohana::$config->load('typeahead');
			
			// Try to load config for the specified model, default otherwise
			if (isset($config[$model->object_name()]))
			{
				$this->config = Arr::merge($config['default'], $config[$model->object_name()]);
			}
			else
			{
				$this->config = $config['default'];
			}
		}

		// Store model to make typeaheads from
		$this->_model = $model;
		
		// Load typeahead type model
		$this->typeahead_type = Model::Factory('typeahead_type')->find_by_model_name($this->_model->object_name());
		
		// Check model is listed in typeahead_types
		if ( ! $this->typeahead_type->loaded() )
		{
			throw new Kohana_Exception('Cannot load typeahead_type for '.$this->_model->object_name());
		}
		
		// Check that find_method exists
		if ( ! method_exists($this->_model, $this->config['find_method']))
		{
			throw new Kohana_Exception ('Model '.get_class($this->_model).' does not have '.$this->config['find_method'].' method');
		}

		
		
		// Load the model to store the typeahead data
		$this->_typeahead_model = Model::Factory($this->config['typeahead_model']);
	}
	
	
	/**
	 * 
	 * @param 	string	$string		The first letters for the typeahead
	 * @return	string				The list of items that match those letters
	 */
	public function __get($string)
	{
		if (isset($this->config['maximum_length']))
		{
			$string = substr($string, 0, $this->config['maximum_length']);
		}
		
		$typeahead = $this->_typeahead_model->find_by_key_and_type($string, $this->typeahead_type->id);
			
		if ($typeahead->loaded() OR $this->config['always_create'])
		{
			if ( is_null($typeahead->list))
			{
				// Key found, but no list associated.  Generate a list!
				$return = $this->_make_and_save_list($string);
			}
			else
			{
				// 
				$return = $typeahead->list;
			}
		}
		else
		{
			// No key found and always_create=FALSE means it doesn't exist and we don't want it to
			$return = NULL;
		}
		
		return $return;
	}
	
	/**
	 * Generates a list of items from $model that begin with $string, saves to $typeahead_model
	 * 
	 * @param  string $string
	 * @return string $list
	 */
	protected function _make_and_save_list($string)
	{
		$list = $this->_make_list($string);
		
		$this->_typeahead_model->typeahead_type_id = Model::Factory('typeahead_type')->find_by_model_name($this->_model->object_name())->id;
		$this->_typeahead_model->key = $string;
		$this->_typeahead_model->list = $list;
		$this->_typeahead_model->save();
		
		return $list;
	}
	
	/**
	 * Generates the typeahead list from $model and saves to $typeahead_model
	 * 
	 * @param	string	$string
	 * @return	string
	 */
	protected function _make_list($string, $list_length=10)	
	{
		$names = array();
		
		// Find the records from $model that match $string, search by find_method
		foreach ($this->_model->{$this->config['find_method']}($string, $list_length)->find_all() as $model)
		{
			$names[] = $model->name;
		}

		// Names will probably have been loaded by a weight factor, not alphabetically
		sort($names);
		
		// Sort by return format
		if ($this->config['return_format'] == 'json')
		{
			// Add extra layer of array
			foreach ($names as $name)
			{
				$return[]['name'] = $name;
			}
			
			// Format list
			$this->_typeahead_model->list = json_encode($return);
		}
		else
		{
			$this->_typeahead_model->list = implode(', ', $names);
		}		
		
		return $this->_typeahead_model->list;
	}
	
	/**
	 * 
	 * @param  int    $string_length
	 * @param  int    $typeahead_type_id
	 * @param  bool   $save_to_database
	 */
	protected function _make_keys($string_length, $save_to_database=FALSE, $check_in_model=TRUE, $make_list=FALSE)
	{
		$make_list = TRUE;
		
		// @todo fix me
		$this->_typeahead_type_model = Model::Factory('typeahead_type')
			->where('model_name', '=', $this->_model->object_name())
			->find();
		
		// Initialise
		// @todo alphabet into config?
		$alphabet = array (
			'A', 'B', 'C', 'D', 'E', 'F', 'G', 'H', 'I', 'J', 'K', 'L', 'M', 
			'N', 'O', 'P', 'Q', 'R', 'S', 'T', 'U', 'V', 'W', 'X', 'Y', 'Z', 
			'0', '1', '2', '3', '4', '5', '6', '7', '8', '9'
		);
		
		$string = '';
		$strings = array();
		$go = TRUE;
		
		// Load up the alphabet arrays
		for ($i = 0 ; $i <= $string_length ; $i++)
		{
			$alphabets[$i] = $alphabet;
		}
		
		while($go)
		{
			// Make up the string from the current positions of the $i alphabets
			for ($i = 1 ; $i <= $string_length ; $i++)
			{
				$string .= current($alphabets[$i]);
			}
			
			// Store the string and reset it
			if ($save_to_database === TRUE)
			{
				// Try to find an existing record
				$save = $this->_typeahead_model->find_by_key_and_type($string, $this->_typeahead_type_model->id);

				// Create new record if none found
				if ( ! $save->loaded())
				{
					// If we want to check_in_model, check that the key exists in the $model
					// @todo fix me!
					if ( ! $check_in_model OR $this->_model->where('name', 'LIKE', $string.'%')->limit(1)->count_all())
					{
						// If make list then$make_list go ahead and make it, otherwise save an empty list
						if ($make_list)
						{
							$this->_make_and_save_list($string);
						}
						else
						{
							$save->key = $string;
							$save->typeahead_type_id = $this->_typeahead_type_model->id;
							$save->save();
						}
					}
				}
			}
			else
			{
				$strings[] = $string;
			}
			
			$string = '';

			// Increment the relevant alphabet
			for ($x = $string_length ; $x > 0 ; $x--)
			{
				if ($alphabets[$x])
				{
					// If this alphabet can be incremented then don't look to the next ones
					if (next($alphabets[$x]))
					{
						break;
					}
					else
					{
						// If we're resetting the first letter then we're finished
						if ($x == 1)
						{
							$go = FALSE;
						}
						
						// Otherwise reset this alphabet to first letter, then continue loop to increment next alphabet
						reset($alphabets[$x]);
					}
				}
			}
		}
		
		return $strings;
	}

	/**
	 * Finds all the typeahead models in typeahead_types
	 * Loops through them and makes keys for all letter combinations up to $config['maximum_length']
	 */
	public static function make_and_save_all_keys_and_lists()
	{
		// We might be here a while
		ini_set('max_execution_time', 6000);
		ini_set('memory_limit', '1000M');
		
		$typeahead_model = Model::factory('typeahead');
		
		// @todo fix this config
		$config = Kohana::$config->load('typeahead.default');
		
		foreach (Model::factory('typeahead_type')->find_all() as $type)
		{
			$model = Model::factory($type->model_name);
			
			for ($i = 1 ; $i < $config['maximum_length'] ; $i++)
			{
				$typeahead = new Typeahead($model, $typeahead_model);
				$typeahead->_make_keys($i, TRUE, TRUE, TRUE);
				
				if ($i === 3) exit('nah');
			}
		}
	}
}

