<?php
/**
 * Part of the Fuel framework.
 *
 * @package    Fuel
 * @version    1.7
 * @author     Fuel Development Team
 * @license    MIT License
 * @copyright  2010 - 2014 Fuel Development Team
 * @link       http://fuelphp.com
 */

namespace Fuel\Core;


/**
 * ViewModel
 *
 * @package	    Fuel
 * @subpackage  Core
 * @category    Core
 * @author      Jelmer Schreuder
 */
abstract class ViewModel
{
	/**
	 * Factory for fetching the ViewModel
	 *
	 * @param   string  ViewModel classname without View_ prefix or full classname
	 * @param   string  Method to execute
	 * @return  ViewModel
	 */
	public static function forge($viewmodel, $method = 'view', $auto_filter = null, $view = null)
	{
		// if no custom view is given, make it equal to the viewmodel name
		is_null($view) and $view = $viewmodel;

		// strip any extensions from the view name to determine the viewmodel to load
		$viewmodel = \Inflector::words_to_upper(str_replace(
			array('/', DS),
			'_',
			strpos($viewmodel, '.') === false ? $viewmodel : substr($viewmodel, 0, -strlen(strrchr($viewmodel, '.')))
		));

		// determine the viewmodel namespace from the current request context
		$namespace = \Request::active() ? ucfirst(\Request::active()->module) : '';

		// list of possible viewmodel classnames, start with the namespaced one
		$classes = array($namespace.'\\View_'.$viewmodel);

		// add the global version if needed
		empty($namespace) or $classes[] = 'View_'.$viewmodel;

		/**
		 * Add non View_ prefixed classnames to the list, for BC reasons
		 *
		 * @deprecated 1.6
		 */
		$classes[] = $namespace.'\\'.$viewmodel;

		// and add the global version of that if needed
		empty($namespace) or $classes[] = $viewmodel;

		// check if we can find one
		foreach ($classes as $class)
		{
			if (class_exists($class))
			{
				return new $class($method, $auto_filter, $view);
			}
		}

		throw new \OutOfBoundsException('ViewModel "'.reset($classes).'" could not be found.');
	}

	/**
	 * @var  string  method to execute when rendering
	 */
	protected $_method;

	/**
	 * @var  string|View  view name, after instantiation a View object
	 */
	protected $_view;

	/**
	 * @var  bool  whether or not to use auto filtering
	 */
	protected $_auto_filter;

	/**
	 * @var  Request  active request during ViewModel creation for proper context
	 */
	protected $_active_request;

	protected function __construct($method, $auto_filter = null, $view = null)
	{
		$this->_auto_filter = $auto_filter;
		$this->_view === null and $this->_view = $view;
		class_exists('Request', false) and $this->_active_request = \Request::active();

		if (empty($this->_view))
		{
			// Take the class name and guess the view name
			$class = get_class($this);
			$this->_view = strtolower(str_replace('_', DS, preg_replace('#^([a-z0-9_]*\\\\)?(View_)?#i', '', $class)));
		}

		$this->set_view();

		$this->_method = $method;
	}

	/**
	 * Returns the View object associated with this Viewmodel
	 *
	 * @return  View
	 */
	public function get_view()
	{
		return $this->_view;
	}

	/**
	 * Construct the View object
	 */
	protected function set_view()
	{
		$this->_view instanceOf View or $this->_view = \View::forge($this->_view);
	}

	/**
	 * Returns the active request object.
	 *
	 * @return  Request
	 */
	protected function request()
	{
		return $this->_active_request;
	}

	/**
	 * Executed before the view method
	 */
	public function before() {}

	/**
	 * The default view method
	 * Should set all expected variables upon itself
	 */
	public function view() {}

	/**
	 * Executed after the view method
	 */
	public function after() {}

	/**
	 * Fetches an existing value from the template
	 *
	 * @return  mixed
	 */
	public function & __get($name)
	{
		return $this->get($name);
	}

	/**
	 * Gets a variable from the template
	 *
	 * @param  string
	 */
	public function & get($key = null, $default = null)
	{
		if (is_null($default) and func_num_args() === 1)
		{
			return $this->_view->get($key);
		}
		return $this->_view->get($key, $default);
	}

	/**
	 * Sets and sanitizes a variable on the template
	 *
	 * @param  string
	 * @param  mixed
	 */
	public function __set($key, $value)
	{
		return $this->set($key, $value);
	}

	/**
	 * Sets a variable on the template
	 *
	 * @param  string
	 * @param  mixed
	 * @param  bool|null
	 */
	public function set($key, $value = null, $filter = null)
	{
		is_null($filter) and $filter = $this->_auto_filter;
		$this->_view->set($key, $value, $filter);

		return $this;
	}

	/**
	 * Magic method, determines if a variable is set.
	 *
	 *     isset($view->foo);
	 *
	 * @param   string  variable name
	 * @return  boolean
	 */
	public function __isset($key)
	{
		return isset($this->_view->$key);
	}

	/**
	 * Assigns a value by reference. The benefit of binding is that values can
	 * be altered without re-setting them. It is also possible to bind variables
	 * before they have values. Assigned values will be available as a
	 * variable within the view file:
	 *
	 *     $this->bind('ref', $bar);
	 *
	 * @param   string   variable name
	 * @param   mixed    referenced variable
	 * @param   bool     Whether to filter the var on output
	 * @return  $this
	 */
	public function bind($key, &$value, $filter = null)
	{
		$this->_view->bind($key, $value, $filter);

		return $this;
	}

	/**
	 * Change auto filter setting
	 *
	 * @param   null|bool  change setting (bool) or get the current setting (null)
	 * @return  void|bool  returns current setting or nothing when it is changed
	 */
	public function auto_filter($setting = null)
	{
		if (func_num_args() == 0)
		{
			return $this->_view->auto_filter();
		}

		return $this->_view->auto_filter($setting);
	}


	/**
	 * Add variables through method and after() and create template as a string
	 */
	public function render()
	{
		if (class_exists('Request', false))
		{
			$current_request = Request::active();
			Request::active($this->_active_request);
		}

		$this->before();
		$this->{$this->_method}();
		$this->after();

		$return = $this->_view->render();

		if (class_exists('Request', false))
		{
			Request::active($current_request);
		}

		return $return;
	}

	/**
	 * Auto-render on toString
	 */
	public function __toString()
	{
		try
		{
			return $this->render();
		}
		catch (\Exception $e)
		{
			\Error::exception_handler($e);

			return '';
		}
	}
}


