<?php

namespace PhpPlatform\Tests\Collaboration\SampleModels;

use PhpPlatform\Collaboration\Models\Account;

/**
 * @tableName sample_account
 * @prefix SampleAccount
 */
class SampleAccount extends Account{
	/**
	 * @columnName ID
	 * @type bigint
	 * @primary
	 * @autoIncrement
	 * @get
	 */
	private $id = null;
	
	/**
	 * @columnName ACCOUNT_ID
	 * @type bigint
	 * @reference
	 * @get
	 */
	private $accountId = null;
	
	/**
	 * @columnName NAME
	 * @type varchar
	 * @set
	 * @get
	 */
	private $name = null;
	
	function __construct($id = null,$accountName = null){
		$this->id = $id;
		parent::__construct($accountName);
	}
	
	/**
	 * @param array $data
	 * @throws \Exception
	 *
	 * @return SampleAccount
	 *
	 * @access inherit
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
	 * @access inherit
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
	 * @access inherit
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
	 * @access inherit
	 */
	function delete(){
		return parent::delete();
	}
}