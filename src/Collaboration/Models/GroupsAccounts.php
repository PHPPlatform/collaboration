<?php

namespace PhpPlatform\Collaboration\Models;

use PhpPlatform\Collaboration\Model;

/**
 * @tableName groups_accounts
 * @prefix GroupsAccounts
 */
class GroupsAccounts extends Model {
	
    /**
     * @columnName GROUP_ID
     * @type bigint
     * @get
     * @set
     */
	private $groupId = null;

    /**
     * @columnName ACCOUNT_ID
     * @type bigint
     * @get
     * @set
     */
	private $accountId = null;
	
	/**
	 * @columnName ACCOUNT_TYPE
	 * @type enum
	 * @get
	 * @set
	 */
	private $accountType = null;
	
	const TYPE_ORGANIZATION = 'ORGANIZATION';
	const TYPE_PERSON       = 'PERSON';
	const TYPE_GROUPS       = 'GROUPS';
	const TYPE_ROLE         = 'ROLE';
	
	function __construct($groupId = null,$accountId = null){
		$this->groupId = $groupId;
		$this->accountId = $accountId;
		parent::__construct();
	}
	
	/**
	 * @param array $data
	 * @return GroupsAccounts
	 * 
	 * @access ("person|systemAdmin")
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
	 * @return GroupsAccounts[]
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
		parent::setAttributes($args);
	}
	
	function getAttribute($name){
		return parent::getAttribute($name);
	}
	
	function getAttributes($args){
		return parent::getAttributes($args);
	}
	
	/**
	 * @access ("person|systemAdmin")
	 */
	function delete(){
		return parent::delete();
	}
	
}