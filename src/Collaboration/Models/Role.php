<?php 
/**
 * User: Raaghu
 */

namespace PhpPlatform\Collaboration\Models;


use PhpPlatform\Persist\TransactionManager;
use PhpPlatform\Collaboration\Util\PersonSession;
use PhpPlatform\Errors\Exceptions\Application\BadInputException;
use PhpPlatform\Errors\Exceptions\Persistence\DataNotFoundException;

/**
 * @tableName role
 * @prefix Role
 */
class Role extends Account {
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
     * @return Role
     * 
     * @access ("role|roleCreator",inherit)
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
     * @return Role[]
     * 
     * @access inherit
     */
    static function find($filters,$sort = null,$pagination = null, $where = null){
    	return parent::find($filters, $sort, $pagination, $where);
    }

    /**
     * @access inherit
     */
    function delete(){
        parent::delete();
    }

    /**
     * @param string $name
     * @param string $value
     * 
     * @return Role
     */
    function setAttribute($name,$value){
        $args = array();
        $args[$name] = $value;
        return $this->setAttributes($args);
    }
    
    /**
     * @param array $args
     * @return Role
     * 
     * @access inherit
     */
    function setAttributes($args){
        return parent::setAttributes($args);
    }

    function getAttribute($name){
        $attrValues = $this->getAttributes(array($name));
        return $attrValues[$name];
    }

    function getAttributes($args){
        $attributes =  parent::getAttributes($args);
        return $attributes;
    }
    
    // composed roles manupulation
    
    /**
     * @return Role[]
     */
    function getComposedRoles(){
    	try{
    		TransactionManager::startTransaction(null,true);
    		$composedRoleObjs = ComposedRoles::find(array("roleId"=>$this->id));
    		$composedRoleIds = array_map(function($composedRoleObj){return $composedRoleObj->getAttribute("composedRoleId");}, $composedRoleObjs);
    		TransactionManager::commitTransaction();
    	}catch (\Exception $e){
    		TransactionManager::abortTransaction();
    		throw $e;
    	}
    	return Role::find(array("id"=>array(self::OPERATOR_IN=>$composedRoleIds)));
    }
    
    
    /**
     * @param Role[] $roles
     * @throws BadInputException
     * 
     * @return Role
     * 
     * @access ("person|systemAdmin","function|canEdit")
     */
    function addComposedRoles($roles){
    	if(!is_array($roles)) throw new BadInputException("1st parameter is not an array");
    	self::checkAccess($this, 'UpdateAccess', 'No access to add composed roles'); // force the access check
    	try{
    		TransactionManager::startTransaction();
    		foreach($roles as $role){
    			self::checkAccess($role, 'UpdateAccess', 'No access to add as composed role');
    			$roleId = $this->id;
    			$composedRoleId = $role->id;
    			TransactionManager::executeInTransaction(function() use($roleId,$composedRoleId){
    				ComposedRoles::create(array("roleId"=>$roleId,"composedRoleId"=>$composedRoleId));
    			},array(),true);
    		}
    		TransactionManager::commitTransaction();
    	}catch (\Exception $e){
    		TransactionManager::abortTransaction();
    		throw $e;
    	}
    	return $this;
    }
    
    /**
     * @param Role[] $roles
     * @throws BadInputException
     *
     * @return Role
     *
     * @access ("person|systemAdmin","function|canEdit")
     */
    function removeComposedRoles($roles){
    	if(!is_array($roles)) throw new BadInputException("1st parameter is not an array");
    	self::checkAccess($this, 'UpdateAccess', 'No access to remove composed roles'); // force the access check
    	try{
    		TransactionManager::startTransaction();
    		foreach($roles as $role){
    			self::checkAccess($role, 'UpdateAccess', 'No access to remove composed roles');
    			$roleId = $this->id;
    			$composedRoleId = $role->id;
    			TransactionManager::executeInTransaction(function() use($roleId,$composedRoleId){
    				$composedRole = new ComposedRoles($roleId,$composedRoleId);
    				$composedRole->delete();
    			},array(),true);
    		}
    		TransactionManager::commitTransaction();
    	}catch (DataNotFoundException $e){
    		TransactionManager::abortTransaction();
    		throw new BadInputException($this->getAttribute('accountName')." and ".$role->getAttribute('accountName')." are not connected");
    	}catch (\Exception $e){
    		TransactionManager::abortTransaction();
    		throw $e;
    	}
    	return $this;
    }
    
    
    // connected people manupulations //
    
    /**
     * @throws Exception
     * @return Person[]
     */
    function getPeople(){
    	try{
    		TransactionManager::startTransaction(null,true);
    		$personRoleObjs = PersonRole::find(array("roleId"=>$this->id));
    		$personIds = array();
    		foreach ($personRoleObjs as $personRoleObj){
    			$personIds[] = $personRoleObj->getAttribute("personId");
    		}
    		TransactionManager::commitTransaction();
    	}catch (\Exception $e){
    		TransactionManager::abortTransaction();
    		throw $e;
    	}
    	return Person::find(array("id"=>array(self::OPERATOR_IN=>$personIds)));
    }
    
    protected static function canRead($args = array()){
    	$readExpr = parent::canRead($args);
    	// can read all the roles he belongs to
    	$belongingRoles = PersonSession::getRoles();
    	if(count($belongingRoles) > 0 ){
    		$accountClass = get_parent_class();
    		$accountNameExpr = "{".$accountClass."."."accountName"."}";
    		$dbs = TransactionManager::getConnection();
    	
    		$belongingRoles = array_map(function($belongingRole) use ($dbs){
    			return $dbs->escape_string($belongingRole);
    		}, $belongingRoles);
    	
    		$belongingOrgsStr = "'".implode("','", $belongingRoles)."'";
    			 
    		$readExpr = "($readExpr) OR $accountNameExpr in ($belongingOrgsStr)";
    	}
    	return $readExpr;
    }
    
}
?>
