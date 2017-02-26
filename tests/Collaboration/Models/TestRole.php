<?php
namespace PhpPlatform\Tests\Collaboration\Models;

use PhpPlatform\Tests\Collaboration\TestBase;
use PhpPlatform\Persist\TransactionManager;
use PhpPlatform\Collaboration\Models\Role;
use PhpPlatform\Errors\Exceptions\Persistence\NoAccessException;
use PhpPlatform\Collaboration\Models\Person;
use PhpPlatform\Collaboration\Models\LoginDetails;
use PhpPlatform\Persist\Reflection;
use PhpPlatform\Collaboration\Models\Organization;
use PhpPlatform\Errors\Exceptions\Application\BadInputException;


class TestRole extends TestBase {
	private $personRoleCreator = null;
	private $personForTest = null;
	
	function setUp(){
		parent::setUp();
	
		$personRoleCreator = null;
		$personForTest = null;
		TransactionManager::executeInTransaction(function() use (&$personRoleCreator,&$personForTest){
			$personRoleCreator = Person::create(array("accountName"=>"roleCreator1","firstName"=>"Role Creator"));
			$loginDetails = LoginDetails::create(array("personId"=>$personRoleCreator->getAttribute('id'),"loginName"=>"roleCreator1","password"=>"roleCreator1"));
			Reflection::invokeArgs('PhpPlatform\Persist\Model', 'setAttributes', $loginDetails,array(array("status"=>LoginDetails::STATUS_ACTIVE)));
			$personRoleCreator->addRoles(array(new Role(null,'personCreator'),new Role(null,'orgCreator'),new Role(null,'roleCreator')));
	
			$personForTest = Person::create(array("accountName"=>"personForTest","firstName"=>"person For Test"));
			$loginDetails = LoginDetails::create(array("personId"=>$personForTest->getAttribute('id'),"loginName"=>"personForTest1","password"=>"personForTest1"));
			Reflection::invokeArgs('PhpPlatform\Persist\Model', 'setAttributes', $loginDetails,array(array("status"=>LoginDetails::STATUS_ACTIVE)));
	
		},array(),true);
	
		$this->personRoleCreator = $personRoleCreator;
		$this->personForTest = $personForTest;
	}
	
	
	function testConstructor(){
		/**
		 * data
		 */
		
		$this->login("roleCreator1","roleCreator1");
		
		$role = Role::create(array("accountName"=>"role1","name"=>"Role 1"));
		
		// clear session
		$this->login();
		
		// construct without session
		$isException = false;
		try{
			new Role(null,'role1');
		}catch (NoAccessException $e){
			$isException = true;
		}
		parent::assertTrue($isException);

		$isException = false;
		try{
			new Role($role->getAttribute('id'));
		}catch (NoAccessException $e){
			$isException = true;
		}
		parent::assertTrue($isException);

		// construct with systemAdmin session
		$this->setSystemAdminSession();
		$isException = false;
		try{
			$role1 = new Role(null,'role1');
		}catch (NoAccessException $e){
			$isException = true;
		}
		parent::assertTrue(!$isException);
		parent::assertEquals($role->getAttribute('name'), $role1->getAttribute('name'));

		$isException = false;
		try{
			$role1 = new Role($role->getAttribute('id'));
		}catch (NoAccessException $e){
			$isException = true;
		}
		parent::assertTrue(!$isException);
		parent::assertEquals($role->getAttribute('name'), $role1->getAttribute('name'));
	}
	
	function testCreate(){
		// create without session
		$isException = false;
		try{
			Role::create(array('name'=>"test Role 1",'accountName'=>'testRole1'));
		}catch (NoAccessException $e){
			$isException = true;
		}
		parent::assertTrue($isException);
	
		// create with systemAdmin session
		$this->setSystemAdminSession();
		$isException = false;
		try{
			$role1 = Role::create(array('name'=>"test Role 1",'accountName'=>'testRole1'));
		}catch (NoAccessException $e){
			$isException = true;
		}
		parent::assertTrue(!$isException);
		parent::assertEquals("testRole1", $role1->getAttribute("accountName"));
		parent::assertEquals("test Role 1", $role1->getAttribute("name"));
	
		// clean session and login as ordinary person
		$this->login('personForTest1', 'personForTest1');
	
		// create with session but not in roleCreator role
		$isException = false;
		try{
			$person2 = Role::create(array('name'=>"test Role 2",'accountName'=>'testRole2'));
		}catch (NoAccessException $e){
			$isException = true;
		}
		parent::assertTrue($isException);
	
		// clean session and login as person creator
		$this->login('roleCreator1', 'roleCreator1');
	
		// create with session in personCreator role
		$isException = false;
		try{
			$person2 = Role::create(array('name'=>"test Role 2",'accountName'=>'testRole2'));
		}catch (NoAccessException $e){
			$isException = true;
		}
		parent::assertTrue(!$isException);
		parent::assertEquals("testRole2", $person2->getAttribute("accountName"));
		parent::assertEquals("test Role 2", $person2->getAttribute("name"));
	}
	
	function testFind(){
		// find without session
		$isException = false;
		try{
			$roles = Role::find(array());
		}catch (NoAccessException $e){
			$isException = true;
		}
		parent::assertTrue($isException);
	
		// find with systemAdmin session
		$this->setSystemAdminSession();
		$isException = false;
		try{
			$roles = Role::find(array("accountName"=>array(Person::OPERATOR_LIKE=>"roleCreator")));
		}catch (NoAccessException $e){
			$isException = true;
		}
		parent::assertTrue(!$isException);
		parent::assertCount(1,$roles);
	
		// find with role creator session
		$this->login('roleCreator1', 'roleCreator1');
		$role1 = Role::create(array('name'=>"test Role 1", 'accountName'=>"testRole1"));
		
		$isException = false;
		try{
			$roles = Role::find(array("accountName"=>array(Person::OPERATOR_LIKE=>"testRole1")));
		}catch (NoAccessException $e){
			$isException = true;
		}
		parent::assertTrue(!$isException);
		parent::assertCount(1,$roles);
		parent::assertEquals("testRole1", $roles[0]->getAttribute('accountName'));
	    
		
		// find the roles added to loggedin person
		$personForTest = $this->personForTest;
		TransactionManager::executeInTransaction(function() use($personForTest,$role1){
			$personForTest->addRoles(array($role1));
		},array(),true);
		
		$this->login("personForTest1","personForTest1");
		$isException = false;
		try{
			$roles = Role::find(array("accountName"=>array(Person::OPERATOR_LIKE=>"testRole1")));
		}catch (NoAccessException $e){
			$isException = true;
		}
		parent::assertTrue(!$isException);
		parent::assertCount(1,$roles);
		parent::assertEquals("testRole1", $roles[0]->getAttribute('accountName'));
	}
	
	function testUpdate(){
		/**
		 * data
		 */
		$this->login("roleCreator1","roleCreator1");
		
		$role = Role::create(array("accountName"=>"role1","name"=>"Role 1"));
		
		// clear session
		$this->login();
		
		// update without session
		$isException = false;
		try{
			$role->setAttribute('name', 'Role1');
		}catch (NoAccessException $e){
			$isException = true;
		}
		parent::assertTrue($isException);
	
		// update with systemAdmin session
		$this->setSystemAdminSession();
		$isException = false;
		try{
			$role->setAttribute('name', 'Role1');
		}catch (NoAccessException $e){
			$isException = true;
		}
		parent::assertTrue(!$isException);
		parent::assertEquals('Role1',$role->getAttribute('name'));
	
		// update own role with role creator session
		$this->login('roleCreator1', 'roleCreator1');
		$isException = false;
		try{
			$role->setAttribute('name', 'Role_1');
		}catch (NoAccessException $e){
			$isException = true;
		}
		parent::assertTrue(!$isException);
		parent::assertEquals('Role_1',$role->getAttribute('name'));
	
		// update other's role from role creator session
		$role_RoleCreator = new Role(null,"roleCreator");
		$isException = false;
		try{
			$role_RoleCreator->setAttribute('name', 'Role Creator 1');
		}catch (NoAccessException $e){
			$isException = true;
		}
		parent::assertTrue($isException);
	}
	
	function testDelete(){
		/**
		 * data
		 */
		$this->login("roleCreator1","roleCreator1");
		
		$role = Role::create(array("accountName"=>"role1","name"=>"Role 1"));
		
		// clear session
		$this->login();
		
		// delete without session
		$isException = false;
		try{
			$role->delete();
		}catch (NoAccessException $e){
			$isException = true;
		}
		parent::assertTrue($isException);
	
		// delete from other's session
		$this->login('personForTest1', 'personForTest1');
		$isException = false;
		try{
			$role->delete();
		}catch (NoAccessException $e){
			$isException = true;
		}
		parent::assertTrue($isException);
	
		// delete in his own session
		$this->login('roleCreator1', 'roleCreator1');
		$isException = false;
		try{
			$role->delete();
		}catch (NoAccessException $e){
			$isException = true;
		}
		parent::assertTrue(!$isException);
		parent::assertCount(3, Role::find(array()));
	
		// delete all in systemAdmin Session
		$this->setSystemAdminSession();
		$role_RoleCreator = new Role(null,"roleCreator");
		$role_RoleCreator->delete();
	}
	
	function testGetPeople(){
		/**
		 * data
		 */
		$this->login("roleCreator1","roleCreator1");
		
		$role = Role::create(array("accountName"=>"role1","name"=>"Role 1"));
		
		// clear session
		$this->login();
		
		// getPeople without session
		$isException = false;
		try{
			$role->getPeople();
		}catch (NoAccessException $e){
			$isException = true;
		}
		parent::assertTrue($isException);
		
		// getPeople with proper session 
		$this->login("roleCreator1","roleCreator1");
		$people = $role->getPeople();
		parent::assertCount(0, $people);
		
		// add role to personForTest
		$personForTest = $this->personForTest;
		TransactionManager::executeInTransaction(function() use($personForTest,$role){
			$personForTest->addRoles(array($role));
		},array(),true);
		
		// getPeople 
		$people = $role->getPeople();
		parent::assertCount(0, $people); // personForTest is not readable to roleCreator1
		
		// getPeople with systemAdmin
		$this->setSystemAdminSession();
		$people = $role->getPeople();
		parent::assertCount(1, $people); 
		
		// create organization and add personForTest
		$this->login("roleCreator1","roleCreator1");
		$organization = Organization::create(array('name'=>"My Org 1",'accountName'=>'myOrg1'));
		$member = $this->personForTest;
		
		// add as memeber
		TransactionManager::executeInTransaction(function() use ($organization,$member){
			$organization->addPeople(array($member));
		},array(),true);
		
		// getPeople 
		$this->login("roleCreator1","roleCreator1");
		$people = $role->getPeople();
		parent::assertCount(1, $people); // personForTest is now readable to roleCreator1
		
	}
	
	function testAddComposedRoles(){
		/**
		 * data
		 */
		$notOwnParent = $notOwnChild = null;
		TransactionManager::executeInTransaction(function() use(&$notOwnParent, &$notOwnChild){
			$notOwnParent = Role::create(array("accountName"=>"role1","name"=>"Role 1"));
			$notOwnChild = Role::create(array("accountName"=>"role2","name"=>"Role 2"));
		},array(),true);
		
		$this->login("roleCreator1","roleCreator1");
		$ownParent = Role::create(array("accountName"=>"role3","name"=>"Role 3"));
		$ownChild = Role::create(array("accountName"=>"role4","name"=>"Role 4"));
		
		// + add ownParent and ownChild
		$ownParent->addComposedRoles(array($ownChild));
		parent::assertCount(1, $ownParent->getComposedRoles());
		
		// + add ownParent and notOwnChild
		$isException = true;
		try{
			$ownParent->addComposedRoles(array($notOwnChild));
		}catch (NoAccessException $e){
			$isException = true;
			parent::assertEquals("No access to add as composed role", $e->getMessage());
		}
		parent::assertTrue($isException);
		
		// + add notOwnParent and ownChild
		$isException = false;
		try{
			$notOwnParent->addComposedRoles(array($ownChild));
		}catch (NoAccessException $e){
			$isException = true;
			parent::assertEquals("No access to add composed roles", $e->getMessage());
		}
		parent::assertTrue($isException);
		
		// + add notOwnParent and notOwnChild
		$isException = false;
		try{
			$notOwnParent->addComposedRoles(array($notOwnChild));
		}catch (NoAccessException $e){
			$isException = true;
			parent::assertEquals("No access to add composed roles", $e->getMessage());
		}
		parent::assertTrue($isException);
		
		
		// add ownParent and ownParent
		$isException = false;
		try{
			$ownParent->addComposedRoles(array($ownParent));
		}catch (BadInputException $e){
			$isException = true;
			parent::assertEquals("cyclic composition is not allowed", $e->getMessage());
		}
		parent::assertTrue($isException);
		
		// add ownChild and ownParent
		$isException = false;
		try{
			$ownChild->addComposedRoles(array($ownParent));
		}catch (BadInputException $e){
			$isException = true;
			parent::assertEquals("cyclic composition is not allowed", $e->getMessage());
		}
		parent::assertTrue($isException);
		
		// invalid input
		$isException = false;
		try{
			$ownParent->addComposedRoles('ownChild');
		}catch (BadInputException $e){
			$isException = true;
			parent::assertEquals("1st parameter is not an array", $e->getMessage());
		}
		parent::assertTrue($isException);
	}
	
	function testRemoveComposedRoles(){
		/**
		 * data
		 */
		$notOwnParent = $notOwnChild = null;
		TransactionManager::executeInTransaction(function() use(&$notOwnParent, &$notOwnChild){
			$notOwnParent = Role::create(array("accountName"=>"role1","name"=>"Role 1"));
			$notOwnChild = Role::create(array("accountName"=>"role2","name"=>"Role 2"));
		},array(),true);
	
		$this->login("roleCreator1","roleCreator1");
		$ownParent = Role::create(array("accountName"=>"role3","name"=>"Role 3"));
		$ownChild = Role::create(array("accountName"=>"role4","name"=>"Role 4"));
		
		TransactionManager::executeInTransaction(function() use($ownParent, $ownChild, $notOwnParent , $notOwnChild){
			$ownParent->addComposedRoles(array($ownChild,$notOwnChild));
			$notOwnParent->addComposedRoles(array($ownChild,$notOwnChild));
		},array(),true);
		
		// remove ownParent and ownChild
		$ownParent->removeComposedRoles(array($ownChild));
		parent::assertCount(0, $ownParent->getComposedRoles());

		// remove ownParent and notOwnChild
		$isException = true;
		try{
			$ownParent->removeComposedRoles(array($notOwnChild));
		}catch (NoAccessException $e){
			$isException = true;
			parent::assertEquals("No access to remove composed roles", $e->getMessage());
		}
		parent::assertTrue($isException);

		// remove notOwnParent and ownChild
		$isException = false;
		try{
			$notOwnParent->removeComposedRoles(array($ownChild));
		}catch (NoAccessException $e){
			$isException = true;
			parent::assertEquals("No access to remove composed roles", $e->getMessage());
		}
		parent::assertTrue($isException);

		// remove notOwnParent and notOwnChild
		$isException = false;
		try{
			$notOwnParent->removeComposedRoles(array($notOwnChild));
		}catch (NoAccessException $e){
			$isException = true;
			parent::assertEquals("No access to remove composed roles", $e->getMessage());
		}
		parent::assertTrue($isException);

		// remove not connected roles
		$isException = false;
		try{
			$ownParent->removeComposedRoles(array($ownChild));
		}catch (BadInputException $e){
			$isException = true;
			parent::assertEquals($ownParent->getAttribute('accountName')." and ".$ownChild->getAttribute('accountName')." are not connected", $e->getMessage());
		}
		parent::assertTrue($isException);
		
		
		// invalid input
		$isException = false;
		try{
			$ownParent->removeComposedRoles('ownChild');
		}catch (BadInputException $e){
			$isException = true;
			parent::assertEquals("1st parameter is not an array", $e->getMessage());
		}
		parent::assertTrue($isException);
	}
	
}