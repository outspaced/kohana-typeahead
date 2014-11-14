<?php defined('SYSPATH') or die('No direct script access.');

/**
 * @author 	Alex Brims
 * @package	Typeahead
 */
class Controller_Typeahead extends Controller {
	
	public function action_get ()
	{
		ini_set('display_errors', TRUE);
		
		$key = $this->request->param('key');
		$model = $this->request->param('model');
		
		try 
		{
			// Replace this with a static method
			// Involve caching first
			$t = new Typeahead(Model::Factory($model));
			
			return $this->response->body($t->{$key});
		}
		catch (Exception $e)
		{
			return $this->response->body('Error: '.$e->getMessage());
		}
	}	
	
	/**
	 * Sets the route for typeaheads
	 * @return  Route
	 */
	public static function set_route()
	{
		return Route::set('typeahead', 'typeahead/<model>/<key>')
			->defaults(
			[
			    'controller' => 'typeahead',
			    'action'     => 'get',
			]
		);
	}
}