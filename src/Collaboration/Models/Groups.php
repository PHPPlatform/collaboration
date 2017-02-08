<?php 
/**
 * User: Raaghu
 */

namespace PhpPlatform\Collaboration\Models;

use PhpPlatform\Persist\TransactionManager;
use PhpPlatform\Persist\Exception\ObjectStateException;
use PhpPlatform\Errors\Exceptions\Application\NoAccessException;

/**
 * @tableName groups
 * @prefix Groups
 */
class Groups extends Account {
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
     * @return Groups
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
     * @return Groups[]
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
    
    
    // group's child accounts manupulations // 
    
    /**
     * @param string $typeFilter
     * @throws Exception
     * @return Account[]
     */
    function getAccounts($typeFilter = null){
    	if(!$this->isObjectInitialised) throw new ObjectStateException("Object Not initialised");
    	if($typeFilter == null){
    		$typeFilter = array(GroupsAccounts::TYPE_ORGANIZATION,GroupsAccounts::TYPE_PERSON,GroupsAccounts::TYPE_GROUPS,GroupsAccounts::TYPE_ROLE);
    	}else if(is_string($typeFilter)){
    		$typeFilter = array($typeFilter);
    	}
    	
    	try{
    		TransactionManager::startTransaction(null,true);
    		
    		$groupAccountObjs = GroupsAccounts::find(array("groupId"=>$this->getAttribute("id"),"accountType"=>array(self::OPERATOR_IN=>$typeFilter)));
    		$accountIdsByType = array();
    		foreach ($groupAccountObjs as $groupAccountObj){
    			$groupAccount = $groupAccountObj->getAttributes("*");
    			$accountType = $groupAccount["accountType"];
    			if(array_key_exists($accountType, $accountIdsByType)){
    				$accountIdsForThisType = $accountIdsByType[$accountType];
    			}else{
    				$accountIdsForThisType = array();
    			}
    			$accountIdsForThisType[] = $groupAccount["accountId"];
    			$accountIdsByType[$accountType] = $accountIdsForThisType;
    		}
    		
    		$accounts = array();
    		foreach ($accountIdsByType as $accountType=>$accountIds){
    			$filter = array("accountId"=>array(self::OPERATOR_IN=>$accountIds));
    			switch ($accountType){
    				case GroupsAccounts::TYPE_ORGANIZATION:
    					$accountsForThisType = Organization::find($filter);
    					break;
    				case GroupsAccounts::TYPE_PERSON:
    					$accountsForThisType = Person::find($filter);
    					break;
    				case GroupsAccounts::TYPE_GROUPS:
    					$accountsForThisType = Groups::find($filter);
    					break;
    				case GroupsAccounts::TYPE_ROLE:
    					$accountsForThisType = Role::find($filter);
    					break;
    				default: $accountsForThisType = array();
    			}
    			
    			foreach ($accountsForThisType as $accountObj){
    				$account = $accountObj->getAttributes("*");
    				$account["accountType"] = $accountType;
    				$accounts[] = $account;
    			}
    		}
    		
    		TransactionManager::commitTransaction();
    	}catch (\Exception $e){
    		TransactionManager::abortTransaction();
    		throw $e;
    	}
    	return $accounts;
    }
    
    
    /**
     * @param string $accountType
     * @param Account $account
     * @throws Exception
     * @return Groups
     * 
     * @access ("person|systemAdmin","function|canEdit")
     */
    function addAccount($account){
    	if(!$this->isObjectInitialised) throw new ObjectStateException("Object Not initialised");
    	if(!self::UpdateAccess()){ throw new NoAccessException('No access to add account');} // force the access check
    	try{
    		TransactionManager::startTransaction(null,true);
    		
    		$accountId = $account->getAttribute("accountId");
    		$accountTypeClass = get_class($account);
    		switch ($accountTypeClass){
    			case 'PhpPlatform\Collaboration\Models\Organization':
    				$accountType = GroupsAccounts::TYPE_ORGANIZATION;
    				break;
    			case 'PhpPlatform\Collaboration\Models\Person':
    				$accountType = GroupsAccounts::TYPE_PERSON;
    				break;
    			case 'PhpPlatform\Collaboration\Models\Role':
    				$accountType = GroupsAccounts::TYPE_ROLE;
    				break;
    			case 'PhpPlatform\Collaboration\Models\Groups':
    				$accountType = GroupsAccounts::TYPE_GROUPS;
    				break;
    			default:
    				$accountType = "";
    		}
    		
    		GroupsAccounts::create(array(
    			"groupId"=>$this->id,
    			"accountId"=>$accountId,
    			"accountType"=>$accountType	
    		));
    	
    		TransactionManager::commitTransaction();
    	}catch (\Exception $e){
    		TransactionManager::abortTransaction();
    		throw $e;
    	}
    	return $this;
    }
    
    /**
     * 
     * @param Account $account
     * @throws Exception
     * @return Groups
     * 
     * @access ("person|systemAdmin","function|canEdit")
     */
    function removeAccount($account){
    	if(!$this->isObjectInitialised) throw new ObjectStateException("Object Not initialised");
    	if(!self::UpdateAccess()){ throw new NoAccessException('No access to remove account');} // force the access check
    	try{
    		TransactionManager::startTransaction(null,true);
    
    		$groupAccount = new GroupsAccounts($this->id,$account->getAttribute("accountId"));
    		$groupAccount->delete();
    		
    		TransactionManager::commitTransaction();
    	}catch (\Exception $e){
    		TransactionManager::abortTransaction();
    		throw $e;
    	}
    	return $this;
    }
    
    

}
?>
