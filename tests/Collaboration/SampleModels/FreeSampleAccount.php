<?php

namespace PhpPlatform\Tests\Collaboration\SampleModels;

use PhpPlatform\Collaboration\Models\Account;

class FreeSampleAccount extends Account{
	
	
	function __construct($accountName = null){
		parent::__construct($accountName);
	}
	
	/**
	 * @param array $data
	 * @throws \Exception
	 *
	 * @return SampleAccount
	 *
	 * @access ("person|systemAdmin","function|canCreate")
	 */
	static function create($data){
		return parent::create($data);
	}
	
	/**
	 * @param array $filters
	 * @param array $sort
	 * @param array $pagination
	 * @param string $where
	 *
	 * @return SampleAccount[]
	 * 
	 * @access ("person|systemAdmin","function|canRead")
	 */
	static function find($filters,$sort = null,$pagination = null, $where = null){
		return parent::find($filters, $sort, $pagination, $where);
	}
	
	function setAttribute($name,$value){
		$args = array();
		$args[$name] = $value;
		$this->setAttributes($args);
	}
	
	/**
	 * @access ("person|systemAdmin","function|canEdit")
	 */
	function setAttributes($args){
		parent::setAttributes($args);
	}
	
	function getAttribute($name){
		return parent::getAttribute($name);
	}
	
	function getAttributes($args){
		return parent::getAttributes($args);
	}
	
	/**
	 * @access ("person|systemAdmin","function|canDelete")
	 */
	function delete(){
		return parent::delete();
	}
	
	protected static function canCreate($data){
		return true;
	}
	
	protected static function canRead($data){
		return true;
	}
	
	protected function canEdit($data){
		return true;
	}
	
	protected function canDelete(){
		return true;
	}
}