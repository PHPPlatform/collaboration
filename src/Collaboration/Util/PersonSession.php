<?php
namespace PhpPlatform\Collaboration\Util;

use PhpPlatform\Collaboration\Models\Person;
use PhpPlatform\Collaboration\Models\LoginDetails;
use PhpPlatform\Collaboration\Models\Organization;
use PhpPlatform\Collaboration\Models\Role;
use PhpPlatform\Collaboration\Session;

class PersonSession {
	
	private static $SESSION_ACCOUNTS = 'SESSION_ACCOUNTS';
	private static $SESSION_PERSON = 'SESSION_PERSON';
    private static $SESSION_LOGINNAME = 'SESSION_LOGINNAME'; 
	
	
	static public function update(Person $loggedInPerson,LoginDetails $loginDetails){
		
		// save account info in session
		Session::getInstance()->refresh(true,true);
		Session::getInstance()->set(self::$SESSION_PERSON,$loggedInPerson->getAttributes("*"));
		Session::getInstance()->set(self::$SESSION_LOGINNAME,$loginDetails->getAttribute('loginName'));
		
		$sessionAccounts = array();
		$sessionAccounts["person"] = array($loggedInPerson->getAttribute('accountName'));

		if($loginDetails->getAttribute("status") == LoginDetails::STATUS_ACTIVE){
			// save related role and organization info in session , only if login-details is Active
			
			// orgs from loggedInPerson
			$orgNames = array();
			$loggedInPersonOrgs = $loggedInPerson->getOrganizations();
			foreach ($loggedInPersonOrgs as $orgObj){
				$orgNames[] = $orgObj->getAttribute("accountName");
			}
			
			// roles from loggedInPerson
			$allRolesFromloggedInPerson = $loggedInPerson->getRoles(true);
			$roleNames = array();
			foreach ($allRolesFromloggedInPerson as $roleObj){
				$roleNames[] = $roleObj->getAttribute("accountName");
			}

			$sessionAccounts["organization"] = $orgNames;
			$sessionAccounts["role"] = $roleNames;
		}

		Session::getInstance()->set(self::$SESSION_ACCOUNTS, $sessionAccounts);
	}
	
	static public function clear(){
		return Session::getInstance()->reset();
	}
	
	static private function has($account,$type){
		$sessionAccounts = self::getAccounts($type);
		return in_array($account, $sessionAccounts);
	}
	
	static public function hasRole($role){
		return self::has($role, 'role');
	}
	
	static public function hasOrganization($organization){
		return self::has($organization, 'organization');
	}
	
	static public function hasPerson($person){
		return self::has($person, 'person');
	}
	
	static public function getAccounts($type = null){
		$sessionKey = self::$SESSION_ACCOUNTS;
		if(isset($type)){
			$sessionKey .= ".$type";
		}
		
		$sessionAccounts = Session::getInstance()->get($sessionKey);
		if(!is_array($sessionAccounts)){
			$sessionAccounts = array();
		}
		return $sessionAccounts;
	}
	
	static public function getRoles(){
		return self::getAccounts('role');
	}
	
	static public function getOrganizations(){
		return self::getAccounts('organization');
	}
	
	static public function getPersons(){
		return self::getAccounts('person');
	}
	
	static public function getPerson(){
		return Session::getInstance()->get(self::$SESSION_PERSON);
	}
	
	static public function getPersonId(){
		$personSession = self::getPerson();
		if(is_array($personSession)){
			return $personSession['id'];
		}
		return false;
	}
	
	static public function getLoginName(){
		return Session::getInstance()->get(self::$SESSION_LOGINNAME);
	}
	
}

