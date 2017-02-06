<?php 
/**
 * User: Raaghu
 */

namespace PhpPlatform\Collaboration\Models;


use PhpPlatform\Persist\Exception\ObjectStateException;
use PhpPlatform\Persist\TransactionManager;
use PhpPlatform\Errors\Exceptions\Application\BadInputException;
use PhpPlatform\Collaboration\Session;
use PhpPlatform\Errors\Exceptions\Application\NoAccessException;
use PhpPlatform\Errors\Exceptions\Application\ProgrammingError;

/**
 * @tableName person
 * @prefix Person
 */
class Person extends Account {
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
     * @columnName FIRST_NAME
     * @type varchar
     * @set
     * @get
     */
    private $firstName = null;

    /**
     * @columnName MIDDLE_NAME
     * @type varchar
     * @set
     * @get
     */
    private $middleName = null;

    /**
     * @columnName LAST_NAME
     * @type varchar
     * @set
     * @get
     */
    private $lastName = null;

    /**
     * @columnName DOB
     * @type date
     * @set
     * @get
     */
    private $dob = null;

    /**
     * @columnName GENDER
     * @type enum
     * @set
     * @get
     */
    private $gender = null;

    const GENDER_MALE   = "MALE";
    const GENDER_FEMALE = "FEMALE";
    const GENDER_OTHER  = "OTHER";

    function __construct($id = null,$accountName = null){
        $this->id = $id;
        parent::__construct($accountName);
    }

    /**
     * @param array $data
     * 
     * @return Person
     * 
     * @access inherit
     */
    static function create( $data){
        return parent::create($data);
    }

    /**
     * @param array $filters
     * @param array $sort
     * @param array $pagination
     * @param string $where
     *
     * @return Person[]
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

    function setAttribute($name,$value){
    	return parent::setAttribute($name, $value);
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

    // connected organization functions //
    
    /**
     * @throws Exception
     * @return Organization[]
     */
    function getOrganizations(){
    	if(!$this->isObjectInitialised) throw new ObjectStateException("Object Not initialised");
    	try{
    		TransactionManager::startTransaction(null,true);
    		$organizationPersonObjs = OrganizationPerson::find(array("personId"=>$this->id));
    		$organizationIds = array();
    		foreach ($organizationPersonObjs as $organizationPersonObj){
    			$organizationIds[] = $organizationPersonObj->getAttribute("organizationId");
    		}
    		TransactionManager::commitTransaction();
    	}catch (\Exception $e){
    		TransactionManager::abortTransaction();
    		throw $e;
    	}
    	return Organization::find(array("id"=>array(self::OPERATOR_IN=>$organizationIds)));  
    }
    
    
    // connected role functions //
    
    /**
     * @throws Exception
     * @return Role[]
     */
    function getRoles(){
    	if(!$this->isObjectInitialised) throw new ObjectStateException("Object Not initialised");
    	try{
    		TransactionManager::startTransaction(null,true);
    		$personRoleObjs = PersonRole::find(array("personId"=>$this->id));
    		$roleIds = array();
    		foreach ($personRoleObjs as $personRoleObj){
    			$roleIds[] = $personRoleObj->getAttribute("roleId");
    		}
    		TransactionManager::commitTransaction();
    	}catch (\Exception $e){
    		TransactionManager::abortTransaction();
    		throw $e;
    	}
    	return Role::find(array("id"=>array(self::OPERATOR_IN=>$roleIds)));
    }
    
    /**
     * @param Role[] $roles
     * 
     * @access ("person|systemAdmin","function|canEdit")
     */
    function addRoles($roles){
    	if(!$this->isObjectInitialised) throw new ObjectStateException("Object Not initialised");
    	if(!is_array($roles)) throw new BadInputException("$roles is not array");
    	parent::UpdateAccess(); // explicitly check for update access
    	
    	try{
    		TransactionManager::startTransaction(null,true);
    		foreach($roles as $role){
    			PersonRole::create(array(
    					"personId"=>$this->id,
    					"roleId"=>$role->getAttribute("id")
    			));
    		}
    		TransactionManager::commitTransaction();
    	}catch (\Exception $e){
    		TransactionManager::abortTransaction();
    		throw $e;
    	}
    	   
    }
    
    /**
     * @param Role[] $roles
     *
     * @access ("person|systemAdmin","function|canEdit")
     */
    function removeRoles($roles){
    	if(!$this->isObjectInitialised) throw new ObjectStateException("Object Not initialised");
    	if(!is_array($roles)) throw new BadInputException("$roles is not array");
    	parent::UpdateAccess(); // explicitly check for update access
    	 
    	try{
    		TransactionManager::startTransaction(null,true);
    		foreach($roles as $role){
    			$personRole = new PersonRole($this->id,$role->getAttribute("id"));
    			$personRole->delete();
    		}
    		TransactionManager::commitTransaction();
    	}catch (\Exception $e){
    		TransactionManager::abortTransaction();
    		throw $e;
    	}
    
    }
    
    // register and login functions //
    
    /**
     * This method creates an account with login details
     * 
     * This methods exposes attacker to create many registrations , 
     * its left to service implementations on deciding the illegal access to this method
     *
     * @param string $loginName
     * @param string $password
     * @param array|null $data
     * @throws \Exception
     * 
     * @return string RegistrationToken to be sent to login email or mobile or other channels to verify registration
     */
    static protected function register($loginName,$password,$data){
    	$accountName = substr(md5($loginName),0,15);
    	try{
    		TransactionManager::startTransaction(null,true);
    		
    		//check if loginName exists
    		$loginDetails = LoginDetails::find(array("loginName"=>$loginName));
    		if(count($loginDetails) > 0){
    			throw new BadInputException("Login name '$loginName' already exists ");
    		}
    		
    		// create account
    		$data["accountName"] = $accountName;
    		$registeredPerson = self::create($data);
    		 
    		// create login details
    		$loginDetails = LoginDetails::create(array(
    				"personId"=>$registeredPerson->getAttribute("id"),
    				"loginName"=>$loginName,
    				"password"=>$password
    		));
    		
    		$loginHistory = LoginHistory::find(array("logindetailsId"=>$loginDetails->getAttribute("id"),"type"=>LoginHistory::LH_REGISTRATION));
    		if(count($loginHistory) != 1){
    			throw new ProgrammingError("Error in retrieving a valid registration Token");
    		}
    		$registrationToken = $loginHistory[0]->getAttribute("sessionId");
    		
    		if(Session::getInstance()->get(Session::SESSION_PERSON) == null){
    			$registeredPerson->login($loginName, $password);
    		}
    		
    		TransactionManager::commitTransaction();
    	}catch (\Exception $e){
    		TransactionManager::abortTransaction();
    		throw $e;
    	}
    	return $registrationToken;
    }
    
    /**
     * This method makes login and constructs the model
     *
     * @param string $loginName
     * @param string $password
     */
    static function login($loginName,$password){
    	try{
    		TransactionManager::startTransaction(null,true);
    		$loginDetailsList = LoginDetails::find(array("loginName"=>$loginName,"status"=>array(self::OPERATOR_IN=>array(LoginDetails::STATUS_ACTIVE,LoginDetails::STATUS_PENDING_VERIFICATION))));
    		
    		if(count($loginDetailsList) != 1){
    			throw new NoAccessException("Invalid loginName or Password");
    		}
    		$loginDetails = $loginDetailsList[0];
    		
    		$loginDetails->login($password);
    		
    		$personId = $loginDetails->getAttribute("personId");
    		
    		$loggedInPerson = new Person($personId);
    		
    		Session::getInstance()->refresh();
    		
    		// save account info in session
    		Session::getInstance()->set(Session::SESSION_PERSON,$loggedInPerson->getAttributes("*"));
    
    		// save loggedIn Person's related accounts in session
    		$sessionAccounts = array();
    		$sessionAccounts["person"] = array($loggedInPerson->getAttribute('accountName'));
    		
    		if($loginDetails->getAttribute("status") == LoginDetails::STATUS_ACTIVE){
    			// save related role and organization info in session , only if login-details is Active 
    			
    			// groups
    			$childGroupIds = array();
    			$personAccountGroupObjs = GroupsAccounts::find(array("accountId"=>$loggedInPerson->getAttribute("accountId")));
    			foreach ($personAccountGroupObjs as $personAccountGroupObj){
    				$groupId = $personAccountGroupObj->getAttribute("groupId");
    				if(!in_array($groupId, $childGroupIds)){
    					self::getAllChildGroupIds($groupId, $childGroupIds);
    				}
    			}
    			
    			// orgs in all child Groups
    			$orgNames = array();
    			$orgGroups = GroupsAccounts::find(array("groupId"=>array(self::OPERATOR_IN=>$childGroupIds),"accountType"=>GroupsAccounts::TYPE_ORGANIZATION));
    			$orgAccountIds = array_map(function($orgGroup){return $orgGroup->getAttribute("accountId");}, $orgGroups);
    			$orgObjsInGroups = Organization::find(array("accountId"=>array(self::OPERATOR_IN=>$orgAccountIds)));
    			self::getAllParentOrganizationNames($orgObjsInGroups, $orgNames);
    			
    			// orgs from loggedInPerson
    			$directOrgObjs = $loggedInPerson->getOrganizations();
    			self::getAllParentOrganizationNames($directOrgObjs, $orgNames);
    			
    			// roles in all child groups
    			$roleGroups = GroupsAccounts::find(array("groupId"=>array(self::OPERATOR_IN=>$childGroupIds),"accountType"=>GroupsAccounts::TYPE_ROLE));
    			$roleAccountIds = array_map(function($roleGroup){return $roleGroup->getAttribute("accountId");}, $roleGroups);
    			$allRoleObjsInGroups = Role::find(array("accountId"=>array(self::OPERATOR_IN=>$roleAccountIds)));
    			
    			// roles from loggedInPerson
    			$allRolesFromloggedInPerson = $loggedInPerson->getRoles();
    			$allRoles = array_merge($allRoleObjsInGroups,$allRolesFromloggedInPerson);
    			$roleNames = array();
    			foreach ($allRoles as $roleObj){
    				$roleName = $roleObj->getAttribute("accountName");
    				if(!in_array($roleName, $roleNames)){
    					$roleNames[] = $roleName;
    				}
    			}
    			
    			$sessionAccounts["organization"] = $orgNames;
    			$sessionAccounts["role"] = $roleNames;
    		}
    		
    		Session::getInstance()->set(Session::SESSION_ACCOUNTS, $sessionAccounts);
    		
    		TransactionManager::commitTransaction();
    	}catch (DataNotFoundException $dnfe){
    		TransactionManager::abortTransaction();
    		throw new BadInputException("Login Name '$loginName' does not exist",$dnfe);
    	}catch (\Exception $e){
    		TransactionManager::abortTransaction();
    		throw $e;
    	}
    }
    
    /**
     * @param Organization[] $organizations
     * @param string[] $orgNames
     */
    static private function getAllParentOrganizationNames($organizations,&$orgNames){
    	foreach ($organizations as $directOrgObj){
    		$orgAccountName = $directOrgObj->getAttribute("accountName");
    		if(!in_array($orgAccountName, $orgNames)){
    			$orgNames[] = $orgAccountName;
    			$parentOrgs = $directOrgObj->getAllParents();
    			foreach ($parentOrgs as $parentOrg){
    				$orgAccountName = $parentOrg->getAttribute("accountName");
    				if(!in_array($orgAccountName, $orgNames)){
    					$orgNames[] = $orgAccountName;
    				}
    			}
    		}
    	}
    }
    
    /**
     * 
     * @param string[] $groupIds
     * @param string[] $groupNames
     */
    static private function getAllParentGroupNames($groupIds,&$groupNames){
    	
    	if(count($groupIds) == 0){
    		return ;
    	}
    	// find groupNames for $groupIds
    	$groups = Groups::find(array("id"=>array(self::OPERATOR_IN=>$groupIds)));
    	$groupAccountIds = array();
    	foreach ($groups as $group){
    		$groupName = $group->getAttribute("accountName");
    		if(!in_array($groupName, $groupNames)){
    			$groupNames[] = $groupName;
    		}
    		$groupAccountIds[] = $group->getAttribute("accountId");
    	}
    	
    	$parentGroups = GroupsAccounts::find(array("accountId"=>array(self::OPERATOR_IN=>$groupAccountIds),"accountType"=>GroupsAccounts::TYPE_GROUPS));
    	$parentGroupIds = array_map(function($parentGroup){return $parentGroup->getAttribute("groupId");}, $parentGroups);
    	self::getAllParentGroupNames($parentGroupIds, $groupNames);
    	
    }
    
    /**
     * 
     * @param string[] $groupId
     * @param string[] $childGroupIds
     */
    static private function getAllChildGroupIds($groupId,&$childGroupIds){
    	$childGroupIds[] = $groupId;
    	$childGroupAccountIds = array();
    	$childGroupAccountObjs = GroupsAccounts::find(array("groupId"=>$groupId,"accountType"=>GroupsAccounts::TYPE_GROUPS));
    	foreach ($childGroupAccountObjs as $childGroupAccountObj){
    		$childGroupAccountIds[] = $childGroupAccountObj->getAttribute("accountId");
    	}
    	
    	$childGroups = Groups::find(array("accountId"=>array(self::OPERATOR_IN=>$childGroupAccountIds)));
    	foreach ($childGroups as $childGroup){
    		$childGroupId = $childGroup->getAttribute("id");
    		if(!in_array($childGroupId, $childGroupIds)){
    			self::getAllChildGroupIds($childGroupId, $childGroupIds);
    		}
    	}
    }
    
    static function logout(){
    	try{
    		TransactionManager::startTransaction(null,true);
    		$loggedInPerson = Session::getInstance()->get(Session::SESSION_PERSON);
    		if($loggedInPerson != null){
    			$loginDetails = new LoginDetails($loggedInPerson["id"]);
    			$loginDetails->logout();
    			Session::getInstance()->refresh(false,true);
    		}else{
    			throw new NoAccessException("Session is not logged in");
    		}
    		TransactionManager::commitTransaction();
    	}catch (\Exception $e){
    		TransactionManager::abortTransaction();
    		throw $e;
    	}
    }
    
    
    /**
     * @throws Exception
     * @return array[][] Login history
     */
    function getLoginHistory(){
    	try{
    		TransactionManager::startTransaction(null,true);
    		
    		// get all login details for this person
    		$loginDetails = LoginDetails::find(array("personId"=>$this->getAttribute("id")));
    		$loginDetailsIds = array_map(function($loginDetail){return $loginDetail->getAttribute("id");}, $loginDetails);
    
    		$loginHistoryObjs = LoginHistory::find(
    				array(
    						"logindetailsId"=>array(self::OPERATOR_IN=>$loginDetailsIds),
    						"type"=>array(
    								self::OPERATOR_IN=>array(
    										LoginHistory::LH_ACTIVATED,
    										LoginHistory::LH_DISABLED,
    										LoginHistory::LH_LOGIN,
    										LoginHistory::LH_LOGOUT,
    										LoginHistory::LH_PASSWORD_RESET_REQUEST,
    										LoginHistory::LH_PASSWORD_RESET
    								)
    						)
    				),
    				array(
    						"time"=>self::SORTBY_DESC
    				)
    				);
    
    		$loginHistory = array();
    		foreach ($loginHistoryObjs as $loginHistoryObj){
    			$loginHistory[] = $loginHistoryObj->getAttributes("*");
    		}
    
    		TransactionManager::commitTransaction();
    	}catch (\Exception $e){
    		TransactionManager::abortTransaction();
    		throw $e;
    	}
    	return $loginHistory;
    }

}
?>
