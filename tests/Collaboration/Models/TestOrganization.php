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
			TransactionManager::executeInTransaction(function () use ($loginDetails){
				Reflection::invokeArgs('PhpPlatform\Persist\Model', 'setAttributes', $loginDetails,array(array("status"=>LoginDetails::STATUS_ACTIVE)));
			},array(),true);
			
			$orgOwner->addRoles(array(new Role(null,'orgCreator')));
			
			$orgAdmin = Person::create(array("accountName"=>"orgAdmin1","firstName"=>"Organization Admin 1"));
			$loginDetails = LoginDetails::create(array("personId"=>$orgAdmin->getAttribute('id'),"loginName"=>"orgAdmin1","password"=>"orgAdmin1"));
			TransactionManager::executeInTransaction(function () use ($loginDetails){
				Reflection::invokeArgs('PhpPlatform\Persist\Model', 'setAttributes', $loginDetails,array(array("status"=>LoginDetails::STATUS_ACTIVE)));
			},array(),true);
			
			
			$orgMember = Person::create(array("accountName"=>"orgMember1","firstName"=>"Organization Member 1"));
			$loginDetails = LoginDetails::create(array("personId"=>$orgMember->getAttribute('id'),"loginName"=>"orgMember1","password"=>"orgMember1"));
			TransactionManager::executeInTransaction(function () use ($loginDetails){
				Reflection::invokeArgs('PhpPlatform\Persist\Model', 'setAttributes', $loginDetails,array(array("status"=>LoginDetails::STATUS_ACTIVE)));
			},array(),true);
			
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
		
		
		
	}
	
	
	
	
}