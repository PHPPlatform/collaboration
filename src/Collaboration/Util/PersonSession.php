<?php
namespace PhpPlatform\Collaboration\Util;

use PhpPlatform\Collaboration\Models\Groups;
use PhpPlatform\Collaboration\Models\GroupsAccounts;
use PhpPlatform\Collaboration\Models\Person;
use PhpPlatform\Collaboration\Models\LoginDetails;
use PhpPlatform\Collaboration\Models\Organization;
use PhpPlatform\Collaboration\Models\Role;
use PhpPlatform\Collaboration\Session;

class PersonSession {

	static public function update(Person $loggedInPerson,LoginDetails $loginDetails){
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
			$orgGroups = GroupsAccounts::find(array("groupId"=>array(Person::OPERATOR_IN=>$childGroupIds),"accountType"=>GroupsAccounts::TYPE_ORGANIZATION));
			$orgAccountIds = array_map(function($orgGroup){return $orgGroup->getAttribute("accountId");}, $orgGroups);
			$orgObjsInGroups = Organization::find(array("accountId"=>array(Person::OPERATOR_IN=>$orgAccountIds)));
			self::getAllParentOrganizationNames($orgObjsInGroups, $orgNames);

			// orgs from loggedInPerson
			$directOrgObjs = $loggedInPerson->getOrganizations();
			self::getAllParentOrganizationNames($directOrgObjs, $orgNames);

			// roles in all child groups
			$roleGroups = GroupsAccounts::find(array("groupId"=>array(Person::OPERATOR_IN=>$childGroupIds),"accountType"=>GroupsAccounts::TYPE_ROLE));
			$roleAccountIds = array_map(function($roleGroup){return $roleGroup->getAttribute("accountId");}, $roleGroups);
			$allRoleObjsInGroups = Role::find(array("accountId"=>array(Person::OPERATOR_IN=>$roleAccountIds)));

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
		$groups = Groups::find(array("id"=>array(Person::OPERATOR_IN=>$groupIds)));
		$groupAccountIds = array();
		foreach ($groups as $group){
			$groupName = $group->getAttribute("accountName");
			if(!in_array($groupName, $groupNames)){
				$groupNames[] = $groupName;
			}
			$groupAccountIds[] = $group->getAttribute("accountId");
		}
			
		$parentGroups = GroupsAccounts::find(array("accountId"=>array(Person::OPERATOR_IN=>$groupAccountIds),"accountType"=>GroupsAccounts::TYPE_GROUPS));
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
			
		$childGroups = Groups::find(array("accountId"=>array(Person::OPERATOR_IN=>$childGroupAccountIds)));
		foreach ($childGroups as $childGroup){
			$childGroupId = $childGroup->getAttribute("id");
			if(!in_array($childGroupId, $childGroupIds)){
				self::getAllChildGroupIds($childGroupId, $childGroupIds);
			}
		}
	}
	
	static public function clear(){
		Session::getInstance()->refresh(false,true);
	}
	
	static private function has($account,$type){
		$sessionAccounts = Session::getInstance()->get(Session::SESSION_ACCOUNTS.".".$type);
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
	
}

