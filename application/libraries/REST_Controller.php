<?php defined('BASEPATH') OR exit('No direct script access allowed');

abstract class REST_Controller extends CI_Controller
{
	// Types of permitted HTTP request methods
	protected $allowed_http_methods = array('post','get','put','delete');
	
	// Permitted types of output format
	protected $_supported_formats = array(
		'json' 	=> 'application/json',
		'xml'	=> 'application/xml',
		'php'	=> 'text/plain',
		'html'	=> 'text/html'
	);
	
	// Types of reserved actions that can be called
	protected $_reserved_actions = array('search');
	protected $action = NULL;
	
	// Specified output format
	protected $output_format = NULL;
	
	// Declare space for response/request objects
	protected $request = NULL;
	protected $response = NULL;
	protected $rest = NULL;
	
	// Containers for request arguments
	protected $_get_args = array();
	protected $_post_args = array();
	protected $_put_args = array();
	protected $_delete_args = array();
	
	protected $_args = array();
	
	public function __construct()
	{
		parent::__construct();
		
		// Loads the rest configuration settings
		$this->load->config('rest');
		
		// Let's create the request
		$this->request = New stdClass();
		
		// Detects the HTTP method (POST, GET, PUT, DELETE)
		$this->request->method = $this->_detect_method();
		
		// Is this an SSL request?
		$this->request->ssl = $this->_detect_ssl();
		
		$this->load->library('format');
		
		// Try to find an input format for this request
		$this->request->format = $this->_detect_input_format();

		// Some requests don't set the body
		$this->request->body = NULL;

		// Set up our GET variables from the URI
//		$this->_get_args = array_merge($this->_get_args, $this->uri->ruri_to_assoc());

		// Parses the input information to their respective arrays
		$this->{'_parse_'.$this->request->method}();

		// Now we know all about our request, let's try and parse the body if it exists
		if ($this->request->format and $this->request->body)
		{
			$this->request->body = $this->format->factory($this->request->body, $this->request->format)->to_array();
			// Assign payload arguments to proper method container
			$this->{'_'.$this->request->method.'_args'} = $this->request->body;
		}

		// Merge both for one mega-args variable
		$this->_args = array_merge($this->_get_args, $this->_put_args, $this->_post_args, $this->_delete_args, $this->{'_'.$this->request->method.'_args'});

		// Create the framework for the response
		$this->response = New stdClass();
		
		// Detects the specified format for the output of this request
		$this->response->format = $this->_detect_output_format();
		
		// Create the REST object
		$this->rest = New stdClass();
	}

	public function _remap($object_called, $arguments)
	{
		// TODO Check to see if the user is authorized to perform this request.

		// Check to see if the requested URI is generally within the correct format	
		if($this->uri->total_segments() > 4){
			$this->response(array('status' => FALSE, 'error' => 'Invalid request format: Excessive URI segments requested'));
		}else{
		// Parse the URI, get the version, object, target, actions, etc.
			$this->_parse_uri();
		}
	
		// Check the requested version
		if( ! $this->_check_version()){
			$this->response(array('status' => FALSE, 'error' => 'Unknown version: '.$this->rest->version));
		}
		
		// Create the controller/method call
		$controller_method = $this->rest->object . '_' . $this->request->method;

		// Determine if the controller/object exists. If not, return an error
		if( ! method_exists($this,$controller_method)){
			$this->response(array('status' => FALSE, 'error' => 'Unknown object class: '.$this->rest->object),404);
		}

		// Parse out the database variables
		$this->_parse_db_args();

		// Has an action been set? (i.e. search) execute different controller
		// Check if 3rd argument exists as an object (controller)
		// Do we want to log this method?

		// Execute the controller_method
		$this->_fire_method(array($this,$controller_method),$arguments);
	}



	/*
	 * Fire Method
	 * 
	 * Fires the method request
	 * @param $method string The method being called
	 * @param $args array Arguments being passed to the requested method
	 */

	protected function _fire_method($method,$args = array())
	{
		call_user_func_array($method, $args);
	}

	/*
	 * Check version
	 * 
	 * Checks the requested version (as set in the config file) to see if it exists
	 * TODO make this scan the directory for folders, and check to see if the requested version is one of them
	 *
	 */

	protected function _check_version()
	{
		if(in_array($this->rest->version,config_item('api_versions'))){
			return TRUE;
		}else{
			return FALSE;
		}
	}

	/*
	 * Response
	 * 
	 * @param array $data The data that will be sent in the response
	 * @param int $http_code Code of of the success of the request
	 * 
	 */

	public function response($data = array(), $http_code = NULL)
	{
		// If data is empty and not code provide, error and bail
		if (empty($data) && $http_code === null)
		{
			$http_code = 404;

			// create the output variable here in the case of $this->response(array());
			$output = NULL;
		}
		
		$output = json_encode($data);

		if( isset($this->response->format)){
			header('Content-Type: '.$this->_supported_formats[$this->response->format]);
		}

		header('HTTP/1.1: ' . $http_code);
		header('Status: ' . $http_code);
		
		
		exit($output);
	}
	
	
	/*
	 * Detect SSL use
	 *
	 * Detect whether SSL is being used or not
	 */
	protected function _detect_ssl()
	{
    		return (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] == "on");
	}
	
	/*
	 * Detect input format
	 *
	 * Detect which format the HTTP Body is provided in
	 */
	protected function _detect_input_format()
	{
		if ($this->input->server('CONTENT_TYPE'))
		{
			// Check all formats against the HTTP_ACCEPT header
			foreach ($this->_supported_formats as $format => $mime)
			{
				if (strpos($match = $this->input->server('CONTENT_TYPE'), ';'))
				{
					$match = current(explode(';', $match));
				}

				if ($match == $mime)
				{
					return $format;
				}
			}
		}

		return NULL;
	}

	protected function _detect_method()
	{
		$method = strtolower($this->input->server('REQUEST_METHOD'));

		if (in_array($method, $this->allowed_http_methods) && method_exists($this, '_parse_' . $method))
		{
			return $method;
		}
		
		return 'get';
 	}
	
	public function get($key = NULL)
	{
		if($key === NULL){
			return $this->_get_args;
		}
		
		return array_key_exists($key,$this->_get_args) ? $this->_get_args[$key] : FALSE;
	}
	
	public function post($key = NULL)
	{
		if($key === NULL){
			return $this->_post_args;
		}
		
		return array_key_exists($key,$this->_post_args) ? $this->_post_args[$key] : FALSE;
	}
	
	public function put($key = NULL)
	{
		if($key === NULL){
			return $this->_put_args;
		}
		
		return array_key_exists($key,$this->_put_args) ? $this->_put_args[$key] : FALSE;
	}
	
	public function delete($key = NULL)
	{
		if($key === NULL){
			return $this->_delete_args;
		}
		
		return array_key_exists($key,$this->_delete_args) ? $this->_delete_args[$key] : FALSE;
	}
	
	protected function _detect_output_format()
	{
		$i = $this->uri->total_segments();
		$format = end(explode('.',$this->uri->segment($i)));
		
		if(array_key_exists($format, $this->_supported_formats)){
			return $format;
		}else{
			return config_item('default_output_format');
		}
		
	}

	protected function _parse_uri()
	{
		// Convert the URI to an indexed array
		$uri = $this->uri->segment_array();
		
		// If an output format type has been requested, it is cleaned from this request
		$temp =  explode('.',$uri[count($uri)]);
		$last = $temp[0];

		// Is the last item in the URI a search function?
		if(in_array($last,$this->_reserved_actions)){
		// Set the action variable
			$this->action = $last;
		}
		
		// Replase the last value in the URI array with the stripped version
		$uri[count($uri)] = $last;

		// Set each of the API request variables
		isset($uri[1]) ? $this->rest->version = $uri[1] : '';
		isset($uri[2]) ? $this->rest->object = $uri[2] : '';
		isset($uri[3]) && $this->action == NULL ? $this->rest->specific_object = $uri[3] : '';
		isset($uri[4]) && $this->action == NULL ? $this->rest->association = $uri[4] : '';
	}

	protected function _parse_db_args()
	{
		$db_args = array();
		
		$db_args['fields'] = NULL;
		$db_args['limit'] = NULL;
		$db_args['offset'] = NULL;

		//What are the fields that are specified within this request?
		if( isset($this->_args['fields'])){
			$db_args['fields'] = $this->_parse_fields($this->_args['fields']);
		}

		if( isset($this->_args['limit'])){
			$db_args['limit'] = $this->_args['limit'];
		}
		
		if( isset($this->_args['offset'])){
			$db_args['offset'] = $this->args['offset'];
		}

		$this->rest->_db_args = $db_args;
	}

	protected function _parse_fields($string, $delimiter = ',')
	{
		return explode($delimiter,$string);
	}

	protected function _parse_post()
	{
		$this->_post_args = $_POST;
		$this->request->format and $this->request->body = file_get_contents('php://input');
	}
	
	protected function _parse_get()
	{
		// Grab proper GET variables
		parse_str(parse_url($_SERVER['REQUEST_URI'], PHP_URL_QUERY), $get);

		// Merge both the URI segments and GET params
		$this->_get_args = array_merge($this->_get_args, $get);
	}

	protected function _parse_put()
	{
		// It might be a HTTP body
		if ($this->request->format)
		{
			$this->request->body = file_get_contents('php://input');
		}

		// If no file type is provided, this is probably just arguments
		else
		{
			parse_str(file_get_contents('php://input'), $this->_put_args);
		}
	}

	protected function _parse_delete()
	{
		// Set up out DELETE variables (which shouldn't really exist, but sssh!)
		parse_str(file_get_contents('php://input'), $this->_delete_args);
	}


	
}	
	