<?php defined('BASEPATH') OR exit('No direct script access allowed');

//require APPPATH.'/libraries/REST_Controller.php';

class Users_model extends CI_Model
{
	function __construct()
	{
		parent::__construct();
	}
	
	function get_user($id, $fields = array())
	{
		if(empty($fields)){
			$qry = "SELECT * FROM `users` WHERE `id` = '$id'";
		}else{
			$qry = "SELECT ".$fields[0];
			for($i = 1; $i < count($fields); $i++){
				$qry .=", ".$fields[$i];
			}
			
			$qry .= " FROM `users` WHERE `id` = '$id'";
		}
		
		return $this->db->query($qry)->row();
	}
	
	function get_all_users($fields = array())
	{
		if(empty($fields)){
			$qry = "SELECT * FROM `users`";
		}else{
			$qry = "SELECT ".$fields[0];
			for($i = 1; $i < count($fields); $i++){
				$qry .=", ".$fields[$i];
			}
			
			$qry .= " FROM `users`";
		}
	}
	
	function get_user_fields()
	{
		$qry = "DESCRIBE `users`";
		
		$temp = $this->db->query($qry)->result();
		
		$response = array();
		
		foreach($temp as $t){
			$response[] = $t->Field;
		}
		
		return $response;
	}
	
	function create_seo_url($string, $maxlen=0)
	{
	    $string = trim(preg_replace('/[^a-z0-9]+/', '-', strtolower($string)), '-');
	    if ($maxlen && strlen($string) > $maxlen) {
	        $string = substr($string, 0, $maxlen);
	        $pos = strrpos($string, '-');
	        if ($pos > 0) {
	            $string = substr($string, 0, $pos);
	        }
	    }
		
		$i = 0;
		$temp = NULL;
		
		$qry = "SELECT * FROM `events` WHERE `seo_url` = '$string'";
		$result = $this->db->query($qry)->row();
		
		while( ! empty($result)){//checks to see if the SEO url exists already, and if it does, it adds an incrementing integer to the end
			$i++;
			$temp = $string."-".$i;
			$qry = "SELECT * FROM `events` WHERE `seo_url` = '$temp'";
			$result = $this->db->query($qry)->row();
		}

		if($i > 0){
			return $temp;
		}else{
		    return $string;
		}		
	}	
	
}
