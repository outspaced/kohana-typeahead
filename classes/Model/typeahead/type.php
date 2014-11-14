<?php defined('SYSPATH') or die('No direct script access.');

class Model_Typeahead_Type extends ORM
{
	public function find_by_model_name ($model_name)
	{
		return $this->where('model_name', '=', $model_name)
			->find();
	}	
}