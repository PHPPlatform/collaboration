<?php

namespace PhpPlatform\Collaboration\Models;

use PhpPlatform\Collaboration\Model;

/**
 * @tableName composed_roles
 * @prefix ComposedRoles
 */
class ComposedRoles extends Model {

	/**
	 * @columnName ROLE_ID
	 * @type bigint
	 * @set
	 * @get
	 */
	private $roleId = null;
	
	/**
	 * @columnName COMPOSED_ROLE_ID
	 * @type bigint
	 * @set
	 * @get
	 */
	private $composedRoleId = null;
	
	function __construct($roleId = null, $composedRoleId = null){
		$this->roleId = $roleId;
		$this->composedRoleId   = $composedRoleId;
		parent::__construct();
	}
	
	/**
	 * @param array $data
	 * @return PersonRole
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
	 * @return PersonRole[]
	 *
	 * @access ("person|systemAdmin")
	 */
	static function find($filters,$sort = null,$pagination = null, $where = null){
		return parent::find($filters, $sort, $pagination, $where);
	}

	
	function getAttribute($name){
		return parent::getAttribute($name);
	}
	
	/**
	 * @access ("person|systemAdmin")
	 */
	function delete(){
		return parent::delete();
	}
	
	static function generateComposedRoleIds(&$roleIds){
		 
	}
	
}