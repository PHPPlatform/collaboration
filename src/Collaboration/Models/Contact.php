<?php

namespace PhpPlatform\Collaboration\Models;

use PhpPlatform\Collaboration\Model;

/**
 * @tableName contact
 * @prefix Contact
 */
class Contact extends Model {
	
    /**
     * @columnName ID
     * @type bigint
     * @primary
     * @autoIncrement
     * @get
     */
    private $id = null;

    /**
     * @columnName INFO
     * @type text
     * @set
     * @get
     */
    private $info = null;
	
	function __construct($id = null){
		$this->id = $id;
		parent::__construct();
	}
	
	/**
	 * @param array $data
	 * @return Contact
	 * 
     * @access ("person|systemAdmin")
	 */
	static function create($data){
		if(array_key_exists("info", $data)){
			$data["info"] = json_encode($data["info"]);
		}else{
			$data["info"] = "{}";
		}
		return parent::create($data);
	}
	
	/**
	 * @param array $filters
	 * @param array $sort
	 * @param array $pagination
	 * @param string $where
	 * 
	 * @return Contact[]
	 * 
     * @access ("person|systemAdmin")
	 */
	static function find($filters,$sort = null,$pagination = null, $where = null){
		return parent::find($filters, $sort, $pagination, $where);
	}
	
	function setAttribute($name, $value){
		return parent::setAttribute($name, $value);
	}
	
	/**
     * @access ("person|systemAdmin")
	 */
	function setAttributes($args){
		if(array_key_exists("info", $args)){
			$args["info"] = json_encode($args["info"]);
		}
		parent::setAttributes($args);
	}
	
	function getAttribute($name){
		return parent::getAttribute($name);
	}
	
	function getAttributes($args){
		$attributes = parent::getAttributes($args);
		if(array_key_exists("info", $attributes)){
			$attributes["info"] = json_decode($attributes["info"],true);
		}
		return $attributes;
	}
	
	/**
     * @access ("person|systemAdmin")
	 */
	function delete(){
		return parent::delete();
	}
	
}