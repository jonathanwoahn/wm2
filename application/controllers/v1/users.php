<?php defined('BASEPATH') OR exit('No direct script access allowed');

//require APPPATH.'/libraries/REST_Controller.php';

class Users extends REST_Controller
{
	// Array holding the data that will be sent in the response
	protected $data = array();
	
	function __construct()
	{
		parent::__construct();
		
		$this->load->model('users_model');
	}		

	function users_get()
	{
		$this->_sanitize_fields();
	} 

	function users_post()
	{
		
	}
	
	function users_put()
	{
		
	}
	
	function users_delete()
	{
		
	}
	
	function _sanitize_fields()
	{
		$fields = $this->users_model->get_user_fields();
		
		var_dump($this->rest->_db_args);
		
	}
}
	