<?php

namespace PhpPlatform\Collaboration\Models;

use PhpPlatform\Collaboration\Model;
use PhpPlatform\Errors\Exceptions\Application\BadInputException;
use PhpPlatform\Persist\TransactionManager;

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
	 * @return ComposedRoles
	 *
	 * @access ("person|systemAdmin")
	 */
	static function create($data){
		$roleId = $data["roleId"];
		$composedRoleId = $data["composedRoleId"];
		$composedRolesOfChild = array($composedRoleId);
		ComposedRoles::generateComposedRoleIds($composedRolesOfChild);
		if(in_array($roleId, $composedRolesOfChild)){
			throw new BadInputException("cyclic composition is not allowed");
		}
		return parent::create($data);
	}
	
	/**
	 * @param array $filters
	 * @param array $sort
	 * @param array $pagination
	 * @param string $where
	 *
	 * @return ComposedRoles[]
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
		try{
			TransactionManager::startTransaction(null,true);
			self::_generateComposedRoleIds($roleIds);
			TransactionManager::commitTransaction();
		}catch (\Exception $e){
			TransactionManager::abortTransaction();
			throw $e;
		}
	}
	
	private static function _generateComposedRoleIds(&$roleIds){
		if(!is_array($roleIds)){
			throw new BadInputException("Parameter 1 must be array");
		}
		if(count($roleIds) == 0){
			return;
		}
		$newRoleIds = array();
		$composedRoleObjs = ComposedRoles::find(array("roleId"=>array(self::OPERATOR_IN=>$roleIds)));
		foreach ($composedRoleObjs as $composedRoleObj){
			$composedRoleId = $composedRoleObj->composedRoleId;
			if(!in_array($composedRoleId, $roleIds)){
				$newRoleIds[] = $composedRoleId;
			}
		}
		self::_generateComposedRoleIds($newRoleIds);
		$roleIds = array_merge($roleIds,$newRoleIds);
	}
	
}