<?php

namespace PHPPlatform\Tests\Collaboration\Models;

use PhpPlatform\Tests\Collaboration\TestBase;
use PhpPlatform\Collaboration\Models\Organization;
use PhpPlatform\Persist\TransactionManager;
use PhpPlatform\Errors\Exceptions\Persistence\NoAccessException;
use PhpPlatform\Collaboration\Models\Person;
use PhpPlatform\Collaboration\Models\LoginDetails;
use PhpPlatform\Collaboration\Models\Role;
use PhpPlatform\Persist\Reflection;
use PhpPlatform\Collaboration\Models\OrganizationPerson;
use PhpPlatform\Errors\Exceptions\Application\BadInputException;

class TestOrganization extends TestBase {
	
	private $orgOwner = null;
	private $orgAdmin = null;
	private $orgMember = null;
	
	function setUp(){
		parent::setUp();
		
		$orgOwner = null;
		$orgAdmin = null;
		$orgMember = null;
		TransactionManager::executeInTransaction(function() use (&$orgOwner,&$orgAdmin,&$orgMember){
			$orgOwner = Person::create(array("accountName"=>"orgOwner1","firstName"=>"Organization Owner 1"));
			$loginDetails = LoginDetails::create(array("personId"=>$orgOwner->getAttribute('id'),"loginName"=>"orgOwner1","password"=>"orgOwner1"));
			Reflection::invokeArgs('PhpPlatform\Persist\Model', 'setAttributes', $loginDetails,array(array("status"=>LoginDetails::STATUS_ACTIVE)));
			
			$orgOwner->addRoles(array(new Role(null,'orgCreator')));
			
			$orgAdmin = Person::create(array("accountName"=>"orgAdmin1","firstName"=>"Organization Admin 1"));
			$loginDetails = LoginDetails::create(array("personId"=>$orgAdmin->getAttribute('id'),"loginName"=>"orgAdmin1","password"=>"orgAdmin1"));
			Reflection::invokeArgs('PhpPlatform\Persist\Model', 'setAttributes', $loginDetails,array(array("status"=>LoginDetails::STATUS_ACTIVE)));
			
			
			$orgMember = Person::create(array("accountName"=>"orgMember1","firstName"=>"Organization Member 1"));
			$loginDetails = LoginDetails::create(array("personId"=>$orgMember->getAttribute('id'),"loginName"=>"orgMember1","password"=>"orgMember1"));
			Reflection::invokeArgs('PhpPlatform\Persist\Model', 'setAttributes', $loginDetails,array(array("status"=>LoginDetails::STATUS_ACTIVE)));
			
		},array(),true);
		
		$this->orgOwner  = $orgOwner;
		$this->orgAdmin  = $orgAdmin;
		$this->orgMember = $orgMember;
		
	}
	
    function testConstructor(){
    	/**
    	 * data
    	 */
    	$organization = null;
    	
    	TransactionManager::executeInTransaction(function () use (&$organization){
    		$organization = Organization::create(array('name'=>"My Org 1",'accountName'=>'myOrg1'));
    	},array(),true);
    	
		// construct without session
		$isException = false;
		try{
			new Organization(null,'myOrg1');
		}catch (NoAccessException $e){
			$isException = true;
		}
		parent::assertTrue($isException);
		
		$isException = false;
		try{
			new Organization($organization->getAttribute('id'));
		}catch (NoAccessException $e){
			$isException = true;
		}
		parent::assertTrue($isException);
		
		
		// construct with systemAdmin session
		$this->setSystemAdminSession();
		$isException = false;
		try{
			new Organization(null,'myOrg1');
		}catch (NoAccessException $e){
			$isException = true;
		}
		parent::assertTrue(!$isException);
		
		$isException = false;
		try{
			new Organization($organization->getAttribute('id'));
		}catch (NoAccessException $e){
			$isException = true;
		}
		parent::assertTrue(!$isException);
	}
	
	function testCreate(){
		// create without session
		$isException = false;
		try{
			Organization::create(array("accountName"=>"myOrg1","name"=>"My Organization 1"));
		}catch (NoAccessException $e){
			$isException = true;
		}
		parent::assertTrue($isException);
		
		// create with systemAdmin session
		$this->setSystemAdminSession();
		$isException = false;
		try{
			$org1 = Organization::create(array("accountName"=>"myOrg1","name"=>"My Organization 1"));
		}catch (NoAccessException $e){
			$isException = true;
		}
		parent::assertTrue(!$isException);
		parent::assertEquals("myOrg1", $org1->getAttribute("accountName"));
		parent::assertEquals("My Organization 1", $org1->getAttribute("name"));
		
		// clean session and login as org member
		$this->login('orgMember1', 'orgMember1');
		
		// create with session but not in orgCreator role 
		$isException = false;
		try{
			$org2 = Organization::create(array("accountName"=>"myOrg2","name"=>"My Organization 2"));
		}catch (NoAccessException $e){
			$isException = true;
		}
		parent::assertTrue($isException);
		
		// clean session and login as org owner
		$this->login('orgOwner1', 'orgOwner1');
		
		// create with session in orgCreator role
		$isException = false;
		try{
			$org2 = Organization::create(array("accountName"=>"myOrg2","name"=>"My Organization 2"));
		}catch (NoAccessException $e){
			$isException = true;
		}
		parent::assertTrue(!$isException);
		parent::assertEquals("myOrg2", $org2->getAttribute("accountName"));
		parent::assertEquals("My Organization 2", $org2->getAttribute("name"));
		
		
		// create with parentId in data
		$isException = false;
		try{
			Organization::create(array("accountName"=>"myOrg3","name"=>"My Organization 3","parentId"=>$org2->getAttribute('id')));
		}catch (BadInputException $e){
			$isException = true;
		}
		parent::assertTrue($isException);
	}
	
	function testFind(){
		/**
		 * data
		 */
		TransactionManager::executeInTransaction(function() {
			Organization::create(array('name'=>"My Org 1",'accountName'=>'myOrg1'));
		},array(),true);
		$this->login('orgOwner1', 'orgOwner1');
		$organization = Organization::create(array('name'=>"My Org 2",'accountName'=>'myOrg2'));
		
		// find without session
		$this->login();
		
		$isException = false;
		try{
			$organizations = Organization::find(array("name"=>"My Org 1"));
		}catch (NoAccessException $e){
			$isException = true;
		}
		parent::assertTrue($isException);
		
		// find with systemAdmin session
		$this->setSystemAdminSession();
		$isException = false;
		try{
			$organizations = Organization::find(array("name"=>array(Organization::OPERATOR_LIKE=>"My Org")));
		}catch (NoAccessException $e){
			$isException = true;
		}
		parent::assertTrue(!$isException);
		parent::assertCount(2,$organizations);
		
		
		// find with orgOwner session
		$this->login('orgOwner1', 'orgOwner1');
		$isException = false;
		try{
			$organizations = Organization::find(array("name"=>array(Organization::OPERATOR_LIKE=>"My Org")));
		}catch (NoAccessException $e){
			$isException = true;
		}
		parent::assertTrue(!$isException);
		parent::assertCount(1,$organizations);
		
		// find with orgMember session, before becoming a member
		$this->login('orgMember1', 'orgMember1');
		$isException = false;
		try{
			$organizations = Organization::find(array("name"=>array(Organization::OPERATOR_LIKE=>"My Org")));
		}catch (NoAccessException $e){
			$isException = true;
		}
		if(!$isException){
			parent::assertCount(0, $organizations);
		}
		
		$member = $this->orgMember;
		
		// add as memeber
		TransactionManager::executeInTransaction(function() use ($organization,$member){
			$organization->addPeople(array($member));
		},array(),true);
		
		// find with orgMember session, after becoming a member
		$this->login('orgMember1', 'orgMember1');
		$isException = false;
		try{
			$organizations = Organization::find(array("name"=>array(Organization::OPERATOR_LIKE=>"My Org")));
		}catch (NoAccessException $e){
			$isException = true;
		}
		parent::assertTrue(!$isException);
		parent::assertCount(1,$organizations);
		
	}
	
	function testUpdate(){
		/**
		 * data
		 */
		$this->login('orgOwner1', 'orgOwner1');
		$organization = Organization::create(array('name'=>"My Org 1",'accountName'=>'myOrg1'));
		
		// update without session
		$this->login();
		$isException = false;
		try{
			$organization->setAttribute('name', 'My Org 1 New ');
		}catch (NoAccessException $e){
			$isException = true;
		}
		parent::assertTrue($isException);
		parent::assertEquals('My Org 1', $organization->getAttribute('name'));
		
		// update with systemAdmin session
		$this->setSystemAdminSession();
		$isException = false;
		try{
			$organization->setAttribute('name', 'My Org 1 New ');
		}catch (NoAccessException $e){
			$isException = true;
		}
		parent::assertTrue(!$isException);
		parent::assertEquals('My Org 1 New ', $organization->getAttribute('name'));
		
		
		// update with orgOwner session
		$this->login('orgOwner1', 'orgOwner1');
		$isException = false;
		try{
			$organization->setAttribute('name', 'My Org 1 New New');
		}catch (NoAccessException $e){
			$isException = true;
		}
		parent::assertTrue(!$isException);
		parent::assertEquals('My Org 1 New New', $organization->getAttribute('name'));
		
		// add a memeber to organization
		$member = $this->orgMember;
		TransactionManager::executeInTransaction(function() use ($organization,$member){
			$organization->addPeople(array($member));
		},array(),true);
		
		// update with orgMember session
		$this->login('orgMember1', 'orgMember1');
		$isException = false;
		try{
			$organization->setAttribute('name', 'My Org 1 New New New');
		}catch (NoAccessException $e){
			$isException = true;
		}
		parent::assertTrue($isException);
		parent::assertEquals('My Org 1 New New', $organization->getAttribute('name'));
		
		
		// add an administrator to organization
		$admin = $this->orgAdmin;
		TransactionManager::executeInTransaction(function() use ($organization,$admin){
			$organization->addPeople(array($admin),OrganizationPerson::TYPE_ADMINISTRATOR);
		},array(),true);
		
		// update with administrator session
		$this->login('orgAdmin1', 'orgAdmin1');
		$isException = false;
		try{
			$organization->setAttribute('name', 'My Org 1 New New New');
		}catch (NoAccessException $e){
			$isException = true;
		}
		parent::assertTrue(!$isException);
		parent::assertEquals('My Org 1 New New New', $organization->getAttribute('name'));
		
		// update parentId
		$this->login('orgOwner1', 'orgOwner1');
		$childOrg = Organization::create(array('name'=>"My Org 2",'accountName'=>'myOrg2'));
		$isException = false;
		try{
			$childOrg->setAttribute('parentId', $organization->getAttribute('id'));
		}catch (BadInputException $e){
			$isException = true;
		}
		parent::assertTrue($isException);
		
	}
	
    function testDelete(){
		/**
		 * data
		 */
		$this->login('orgOwner1', 'orgOwner1');
		$organization1 = Organization::create(array('name'=>"My Org 1",'accountName'=>'myOrg1'));
		$organization2 = Organization::create(array('name'=>"My Org 2",'accountName'=>'myOrg2'));
		$organization3 = Organization::create(array('name'=>"My Org 3",'accountName'=>'myOrg3'));
		
		// delete without session
		$this->login();
		$isException = false;
		try{
			$organization1->delete();
		}catch (NoAccessException $e){
			$isException = true;
		}
		parent::assertTrue($isException);
		
		// delete with systemAdmin session
		$this->setSystemAdminSession();
		$isException = false;
		try{
			$organization1->delete();
		}catch (NoAccessException $e){
			$isException = true;
		}
		parent::assertTrue(!$isException);
		
		
		// delete with orgOwner session
		$this->login('orgOwner1', 'orgOwner1');
		$isException = false;
		try{
			$organization2->delete();
		}catch (NoAccessException $e){
			$isException = true;
		}
		parent::assertTrue(!$isException);
		
		// add a memeber to organization
		$member = $this->orgMember;
		TransactionManager::executeInTransaction(function() use ($organization3,$member){
			$organization3->addPeople(array($member));
		},array(),true);
		
		// delete with orgMember session
		$this->login('orgMember1', 'orgMember1');
		$isException = false;
		try{
			$organization3->delete();
		}catch (NoAccessException $e){
			$isException = true;
		}
		parent::assertTrue($isException);
		
		
		// add an administrator to organization
		$admin = $this->orgAdmin;
		TransactionManager::executeInTransaction(function() use ($organization3,$admin){
			$organization3->addPeople(array($admin),OrganizationPerson::TYPE_ADMINISTRATOR);
		},array(),true);
		
		// delete with administrator session
		$this->login('orgAdmin1', 'orgAdmin1');
		$isException = false;
		try{
			$organization3->delete();
		}catch (NoAccessException $e){
			$isException = true;
		}
		parent::assertTrue($isException);
		
		
		// validate that 2 organizations were deleted in db
		$organizations = null;
		TransactionManager::executeInTransaction(function() use (&$organizations){
			$organizations = Organization::find(array("name"=>array(Organization::OPERATOR_LIKE=>"My Org")));
		},array(),true);
		
		parent::assertCount(1, $organizations);
		
	}
	
	function testGetAttributes(){
		$organization = null;
		$childOrg = null;
		TransactionManager::executeInTransaction(function() use(&$organization,&$childOrg){
			$organization = Organization::create(array('name'=>"My Org 1",'accountName'=>'myOrg1'));
			$childOrg = Organization::create(array('name'=>"My Org 2",'accountName'=>'myOrg2'));
			$organization->addChildren(array($childOrg));
		},array(),true);
	
		parent::assertEquals(1,$organization->getAttribute('id'));
		parent::assertEquals('My Org 1',$organization->getAttribute('name'));
		parent::assertEquals('myOrg1',$organization->getAttribute('accountName'));
		parent::assertEquals(null,$childOrg->getAttribute('parentId'));
		
	}
	
	function testParentAndChildOrgs(){
		$this->login('orgOwner1', 'orgOwner1');
		$organization1 = Organization::create(array('name'=>"My Org 1",'accountName'=>'myOrg1'));
		$organization2 = Organization::create(array('name'=>"My Org 2",'accountName'=>'myOrg2'));
		$organization3 = Organization::create(array('name'=>"My Org 3",'accountName'=>'myOrg3'));
		$organization4 = Organization::create(array('name'=>"My Org 4",'accountName'=>'myOrg4'));
		TransactionManager::executeInTransaction(function() use(&$organization1,&$organization2,&$organization3,&$organization4){
			$organization1->addChildren(array($organization2));
			$organization2->addChildren(array($organization3,$organization4));
		},array(),true);
		
		// test getChildren
		$org1Childs = $organization1->getChildren();
		parent::assertCount(1, $org1Childs);
		parent::assertEquals($organization2->getAttributes("name"), $org1Childs[0]->getAttributes("name"));
		
		$org2Childs = $organization2->getChildren();
		parent::assertCount(2, $org2Childs);
		
		$org3Childs = $organization3->getChildren();
		parent::assertCount(0, $org3Childs);
		
		
		// test getParent
		$org1Parent = $organization1->getParent();
		parent::assertNull($org1Parent);
		
		$org2Parent = $organization2->getParent();
		parent::assertEquals($organization1->getAttribute('name'), $org2Parent->getAttribute('name'));
		
		$org3Parent = $organization3->getParent();
		parent::assertEquals($organization2->getAttribute('name'), $org3Parent->getAttribute('name'));
		
		
		// test getParents
		$org1Parents = $organization1->getAllParents();
		parent::assertCount(0,$org1Parents);
		
		$org2Parents = $organization2->getAllParents();
		parent::assertCount(1,$org2Parents);
		parent::assertEquals($organization1->getAttribute('name'), $org2Parents[0]->getAttribute('name'));
		
		$org3Parents = $organization3->getAllParents();
		parent::assertCount(2,$org3Parents);
		parent::assertEquals(
				array(
					$organization1->getAttribute('name'),
					$organization2->getAttribute('name')
				), array(
					$org3Parents[0]->getAttribute('name'),
					$org3Parents[1]->getAttribute('name')
				)
		);
		
	}
	
	function testAddRemoveChildren(){
		/**
		 * data
		 */
		$parentOwn = null;
		$parentAdmin = null;
		$parentMember = null;
		$parentNone = null;
		
		$childOwn = null;
		$childAdmin = null;
		$childMember = null;
		$childNone = null;
		
		$owner = $this->orgOwner;
		
		TransactionManager::executeInTransaction(function() 
			use($owner,&$parentOwn,&$parentAdmin,&$parentMember,&$parentNone,&$childOwn,&$childAdmin,&$childMember,&$childNone){
			$parentOwn    = Organization::create(array('name'=>"My Org 1",'accountName'=>'myOrg1'));
		    OrganizationPerson::create(array("organizationId"=>$parentOwn->getAttribute('id'),"personId"=>$owner->getAttribute('id'),"type"=>OrganizationPerson::TYPE_OWNER));
			
		    $parentAdmin  = Organization::create(array('name'=>"My Org 2",'accountName'=>'myOrg2'));
		    OrganizationPerson::create(array("organizationId"=>$parentAdmin->getAttribute('id'),"personId"=>$owner->getAttribute('id'),"type"=>OrganizationPerson::TYPE_ADMINISTRATOR));
			
		    $parentMember = Organization::create(array('name'=>"My Org 3",'accountName'=>'myOrg3'));
		    OrganizationPerson::create(array("organizationId"=>$parentMember->getAttribute('id'),"personId"=>$owner->getAttribute('id'),"type"=>OrganizationPerson::TYPE_MEMBER));
		    
		    $parentNone   = Organization::create(array('name'=>"My Org 4",'accountName'=>'myOrg4'));
		    
		    $childOwn     = Organization::create(array('name'=>"My Org 5",'accountName'=>'myOrg5'));
		    OrganizationPerson::create(array("organizationId"=>$childOwn->getAttribute('id'),"personId"=>$owner->getAttribute('id'),"type"=>OrganizationPerson::TYPE_OWNER));
		    
		    $childAdmin   = Organization::create(array('name'=>"My Org 6",'accountName'=>'myOrg6'));
		    OrganizationPerson::create(array("organizationId"=>$childAdmin->getAttribute('id'),"personId"=>$owner->getAttribute('id'),"type"=>OrganizationPerson::TYPE_ADMINISTRATOR));
		    
		    $childMember  = Organization::create(array('name'=>"My Org 7",'accountName'=>'myOrg7'));
		    OrganizationPerson::create(array("organizationId"=>$childMember->getAttribute('id'),"personId"=>$owner->getAttribute('id'),"type"=>OrganizationPerson::TYPE_MEMBER));
		    
		    $childNone    = Organization::create(array('name'=>"My Org 8",'accountName'=>'myOrg8'));
		},array(),true);
		
		$this->login('orgOwner1','orgOwner1');
		
		// ---------------------------------------------------------------
		
		// parent-owner + child-owner
		$this->executeAddRemoveChildren(true,$parentOwn, array($childOwn));
		parent::assertEquals($parentOwn->getAttribute('name'), $childOwn->getParent()->getAttribute('name'));
		$this->executeAddRemoveChildren(false, $parentOwn, array($childOwn));
		parent::assertEquals(null, $childOwn->getParent());
		
		// parent-owner + child-admin
		$this->executeAddRemoveChildren(true,$parentOwn, array($childAdmin));
		parent::assertEquals($parentOwn->getAttribute('name'), $childAdmin->getParent()->getAttribute('name'));
		$this->executeAddRemoveChildren(false, $parentOwn, array($childAdmin));
		parent::assertEquals(null, $childAdmin->getParent());
		
		// parent-owner + child-member
		$this->executeAddRemoveChildren(true,$parentOwn, array($childMember),'PhpPlatform\Errors\Exceptions\Persistence\NoAccessException');
		$this->manuallyAddRemoveChildren(true, $parentOwn, array($childMember));
		$this->executeAddRemoveChildren(false,$parentOwn, array($childMember),'PhpPlatform\Errors\Exceptions\Persistence\NoAccessException');
		$this->manuallyAddRemoveChildren(false, $parentOwn, array($childMember));
		
		
		// parent-owner + child-none
		$this->executeAddRemoveChildren(true,$parentOwn, array($childNone),'PhpPlatform\Errors\Exceptions\Persistence\NoAccessException');
		$this->manuallyAddRemoveChildren(true, $parentOwn, array($childNone));
		$this->executeAddRemoveChildren(false,$parentOwn, array($childNone),'PhpPlatform\Errors\Exceptions\Persistence\NoAccessException');
		$this->manuallyAddRemoveChildren(false, $parentOwn, array($childNone));
		
		// ----------------------------------------------------------------
		
		// parent-admin + child-owner
		$this->executeAddRemoveChildren(true,$parentAdmin, array($childOwn));
		parent::assertEquals($parentAdmin->getAttribute('name'), $childOwn->getParent()->getAttribute('name'));
		$this->executeAddRemoveChildren(false, $parentAdmin, array($childOwn));
		parent::assertEquals(null, $childOwn->getParent());
		
		// parent-admin + child-admin
		$this->executeAddRemoveChildren(true,$parentAdmin, array($childAdmin));
		parent::assertEquals($parentAdmin->getAttribute('name'), $childAdmin->getParent()->getAttribute('name'));
		$this->executeAddRemoveChildren(false, $parentAdmin, array($childAdmin));
		parent::assertEquals(null, $childAdmin->getParent());
		
		// parent-admin + child-member
		$this->executeAddRemoveChildren(true,$parentAdmin, array($childMember),'PhpPlatform\Errors\Exceptions\Persistence\NoAccessException');
		$this->manuallyAddRemoveChildren(true, $parentAdmin, array($childMember));
		$this->executeAddRemoveChildren(false,$parentAdmin, array($childMember),'PhpPlatform\Errors\Exceptions\Persistence\NoAccessException');
		$this->manuallyAddRemoveChildren(false, $parentAdmin, array($childMember));
		
		// parent-admin + child-none
		$this->executeAddRemoveChildren(true,$parentAdmin, array($childNone),'PhpPlatform\Errors\Exceptions\Persistence\NoAccessException');
		$this->manuallyAddRemoveChildren(true, $parentAdmin, array($childNone));
		$this->executeAddRemoveChildren(false,$parentAdmin, array($childNone),'PhpPlatform\Errors\Exceptions\Persistence\NoAccessException');
		$this->manuallyAddRemoveChildren(false, $parentAdmin, array($childNone));
		
		// ----------------------------------------------------------------
		
		// parent-member + child-owner
		$this->executeAddRemoveChildren(true,$parentMember, array($childOwn),'PhpPlatform\Errors\Exceptions\Persistence\NoAccessException');
		$this->manuallyAddRemoveChildren(true, $parentMember, array($childOwn));
		$this->executeAddRemoveChildren(false,$parentMember, array($childOwn),'PhpPlatform\Errors\Exceptions\Persistence\NoAccessException');
		$this->manuallyAddRemoveChildren(false, $parentMember, array($childOwn));
		
		// parent-member + child-admin
		$this->executeAddRemoveChildren(true,$parentMember, array($childAdmin),'PhpPlatform\Errors\Exceptions\Persistence\NoAccessException');
		$this->manuallyAddRemoveChildren(true, $parentMember, array($childAdmin));
		$this->executeAddRemoveChildren(false,$parentMember, array($childAdmin),'PhpPlatform\Errors\Exceptions\Persistence\NoAccessException');
		$this->manuallyAddRemoveChildren(false, $parentMember, array($childAdmin));
		
		// parent-member + child-member
		$this->executeAddRemoveChildren(true,$parentMember, array($childMember),'PhpPlatform\Errors\Exceptions\Persistence\NoAccessException');
		$this->manuallyAddRemoveChildren(true, $parentMember, array($childMember));
		$this->executeAddRemoveChildren(false,$parentMember, array($childMember),'PhpPlatform\Errors\Exceptions\Persistence\NoAccessException');
		$this->manuallyAddRemoveChildren(false, $parentMember, array($childMember));
		
		// parent-member + child-none
		$this->executeAddRemoveChildren(true,$parentMember, array($childNone),'PhpPlatform\Errors\Exceptions\Persistence\NoAccessException');
		$this->manuallyAddRemoveChildren(true, $parentMember, array($childNone));
		$this->executeAddRemoveChildren(false,$parentMember, array($childNone),'PhpPlatform\Errors\Exceptions\Persistence\NoAccessException');
		$this->manuallyAddRemoveChildren(false, $parentMember, array($childNone));
				
		// ----------------------------------------------------------------
		
		// parent-none + child-owner
		$this->executeAddRemoveChildren(true,$parentNone, array($childOwn),'PhpPlatform\Errors\Exceptions\Persistence\NoAccessException');
		$this->manuallyAddRemoveChildren(true, $parentNone, array($childOwn));
		$this->executeAddRemoveChildren(false,$parentNone, array($childOwn),'PhpPlatform\Errors\Exceptions\Persistence\NoAccessException');
		$this->manuallyAddRemoveChildren(false, $parentNone, array($childOwn));
		
		// parent-none + child-admin
		$this->executeAddRemoveChildren(true,$parentNone, array($childAdmin),'PhpPlatform\Errors\Exceptions\Persistence\NoAccessException');
		$this->manuallyAddRemoveChildren(true, $parentNone, array($childAdmin));
		$this->executeAddRemoveChildren(false,$parentNone, array($childAdmin),'PhpPlatform\Errors\Exceptions\Persistence\NoAccessException');
		$this->manuallyAddRemoveChildren(false, $parentNone, array($childAdmin));
		
		// parent-none + child-member
		$this->executeAddRemoveChildren(true,$parentNone, array($childMember),'PhpPlatform\Errors\Exceptions\Persistence\NoAccessException');
		$this->manuallyAddRemoveChildren(true, $parentNone, array($childMember));
		$this->executeAddRemoveChildren(false,$parentNone, array($childMember),'PhpPlatform\Errors\Exceptions\Persistence\NoAccessException');
		$this->manuallyAddRemoveChildren(false, $parentNone, array($childMember));
		
		// parent-none + child-none
		$this->executeAddRemoveChildren(true,$parentNone, array($childNone),'PhpPlatform\Errors\Exceptions\Persistence\NoAccessException');
		$this->manuallyAddRemoveChildren(true, $parentNone, array($childNone));
		$this->executeAddRemoveChildren(false,$parentNone, array($childNone),'PhpPlatform\Errors\Exceptions\Persistence\NoAccessException');
		$this->manuallyAddRemoveChildren(false, $parentNone, array($childNone));
		
		// ----------------------------------------------------------------
		
		// test with invalid parameters 
		$this->executeAddRemoveChildren(true,$parentOwn, 'invalid input','PhpPlatform\Errors\Exceptions\Application\BadInputException','1st parameter is not an array');
		$this->executeAddRemoveChildren(false,$parentOwn, 'invalid input','PhpPlatform\Errors\Exceptions\Application\BadInputException','1st parameter is not an array');
		$this->executeAddRemoveChildren(true, $parentOwn, array($parentOwn),'PhpPlatform\Errors\Exceptions\Application\BadInputException','An Organization can not be a child its own');
		$this->executeAddRemoveChildren(true, $parentOwn, array($childOwn));
		$this->executeAddRemoveChildren(true, $parentAdmin, array($childOwn),'PhpPlatform\Errors\Exceptions\Application\BadInputException','This organization is already a child of another organization with id '.$parentOwn->getAttribute('id'));
		$this->executeAddRemoveChildren(false, $parentAdmin, array($childOwn),'PhpPlatform\Errors\Exceptions\Application\BadInputException',"Organization ".$childOwn->getAttribute('name')." is not a child of ".$parentAdmin->getAttribute('name'));
		
		$this->executeAddRemoveChildren(true, $childOwn, array($parentOwn),'PhpPlatform\Errors\Exceptions\Application\BadInputException','Circular child-parent connection');
		$this->executeAddRemoveChildren(true, $childOwn, array($parentAdmin));
		$this->executeAddRemoveChildren(true, $parentAdmin, array($parentOwn),'PhpPlatform\Errors\Exceptions\Application\BadInputException','Circular child-parent connection');
		
	}
	
	/**
	 * 
	 * @param boolean $isAdd
	 * @param Organization $parent
	 * @param Organization[] $children
	 * @param string $exception
	 * @param string $exceptionMessage
	 */
	private function executeAddRemoveChildren($isAdd,$parent,$children,$exception = null,$exceptionMessage = null){
		$isException = false;
		$exp = null;
		try{
			if($isAdd){
				$parent->addChildren($children);
			}else{
				$parent->removeChildren($children);
			}
		}catch (\Exception $e){
			$isException = true;
			$exp = $e;
		}
		if(isset($exception)){
			parent::assertTrue($isException);
		    parent::assertEquals($exception,get_class($exp));
		    if(isset($exceptionMessage)){
		    	parent::assertEquals($exceptionMessage, $exp->getMessage());
		    }
		}else{
			parent::assertTrue(!$isException);
		}
	}
	
	/**
	 * 
	 * @param boolean $isAdd
	 * @param Organization $parent
	 * @param Organization[] $children
	 */
	private function manuallyAddRemoveChildren($isAdd,$parent,$children){
		TransactionManager::executeInTransaction(function() use ($isAdd,$parent,$children){
			if($isAdd){
				$parent->addChildren($children);
			}else{
				$parent->removeChildren($children);
			}
		},array(),true);
	}
	
	
	
	
}