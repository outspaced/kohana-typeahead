<?php defined('SYSPATH') or die('No direct script access.');

class Model_Typeahead extends ORM
{
	/**
	 * @var	array
	 */
	protected $_created_column = array('column'=>'created_timestamp', 'format'=>TRUE);

	/**
	 * @var	array
	 */
	protected $_updated_column = array('column'=>'updated_timestamp', 'format'=>TRUE);
	
	public function __set($key, $value)
	{
		switch ($key)
		{
			case 'key':
				$value = strtoupper($value);	
			default:
				return parent::__set($key, $value);
		}
	}
	
	/**
	 * @param 	string	$string
	 * @param 	int		$type_id
	 * @return	self
	 * @author	Alex Brims
	 */
	public function find_by_key_and_type($string, $type_id)
	{
		return $this->clear()
			->where('key', '=', $string)
			->where('typeahead_type_id', '=', $type_id)
			->find();
	}	
}