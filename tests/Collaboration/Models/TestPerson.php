<?php
namespace PhpPlatform\Tests\Collaboration\Models;

use PhpPlatform\Tests\Collaboration\TestBase;
use PhpPlatform\Persist\TransactionManager;
use PhpPlatform\Collaboration\Models\Person;
use PhpPlatform\Errors\Exceptions\Persistence\NoAccessException;
use PhpPlatform\Persist\MySql;
use PhpPlatform\Collaboration\Models\LoginDetails;
use PhpPlatform\Persist\Reflection;
use PhpPlatform\Collaboration\Models\Role;
use PhpPlatform\Collaboration\Models\Organization;
use PhpPlatform\Collaboration\Models\ComposedRoles;

class TestPerson extends TestBase {
	
	private $personCreator = null;
	private $personForTest = null;
	
	function setUp(){
		parent::setUp();
	
		$personCreator = null;
		$personForTest = null;
	    TransactionManager::executeInTransaction(function() use (&$personCreator,&$personForTest){
			$personCreator = Person::create(array("accountName"=>"personCreator1","firstName"=>"person Creator"));
			$loginDetails = LoginDetails::create(array("personId"=>$personCreator->getAttribute('id'),"loginName"=>"personCreator1","password"=>"personCreator1"));
			Reflection::invokeArgs('PhpPlatform\Persist\Model', 'setAttributes', $loginDetails,array(array("status"=>LoginDetails::STATUS_ACTIVE)));
			$personCreator->addRoles(array(new Role(null,'personCreator'),new Role(null,'orgCreator'),new Role(null,'roleCreator')));
				
			$personForTest = Person::create(array("accountName"=>"personForTest","firstName"=>"person For Test"));
			$loginDetails = LoginDetails::create(array("personId"=>$personForTest->getAttribute('id'),"loginName"=>"personForTest1","password"=>"personForTest1"));
			Reflection::invokeArgs('PhpPlatform\Persist\Model', 'setAttributes', $loginDetails,array(array("status"=>LoginDetails::STATUS_ACTIVE)));
				
		},array(),true);
	
		$this->personCreator = $personCreator;
		$this->personForTest = $personForTest;
	}
	
	
	function testConstructor(){
		/**
		 * data
		 */
		$person = null;
		 
		TransactionManager::executeInTransaction(function () use (&$person){
			$person = Person::create(array('firstName'=>"test Person 1",'accountName'=>'testPerson1'));
		},array(),true);
			 
		// construct without session
		$isException = false;
		try{
			new Person(null,'testPerson1');
		}catch (NoAccessException $e){
			$isException = true;
		}
		parent::assertTrue($isException);

		$isException = false;
		try{
			new Person($person->getAttribute('id'));
		}catch (NoAccessException $e){
			$isException = true;
		}
		parent::assertTrue($isException);

		// construct with systemAdmin session
		$this->setSystemAdminSession();
		$isException = false;
		try{
			$person1 = new Person(null,'testPerson1');
		}catch (NoAccessException $e){
			$isException = true;
		}
		parent::assertTrue(!$isException);
		parent::assertEquals($person->getAttribute('firstName'), $person1->getAttribute('firstName'));

		$isException = false;
		try{
			$person2 = new Person($person->getAttribute('id'));
		}catch (NoAccessException $e){
			$isException = true;
		}
		parent::assertTrue(!$isException);
		parent::assertEquals($person->getAttribute('firstName'), $person2->getAttribute('firstName'));
	}
	
	function testCreate(){
		// create without session
		$isException = false;
		try{
			Person::create(array('firstName'=>"test Person 1",'accountName'=>'testPerson1'));
		}catch (NoAccessException $e){
			$isException = true;
		}
		parent::assertTrue($isException);
	
		// create with systemAdmin session
		$this->setSystemAdminSession();
		$isException = false;
		try{
			$person1 = Person::create(array('firstName'=>"test Person 1",'accountName'=>'testPerson1'));
		}catch (NoAccessException $e){
			$isException = true;
		}
		parent::assertTrue(!$isException);
		parent::assertEquals("testPerson1", $person1->getAttribute("accountName"));
		parent::assertEquals("test Person 1", $person1->getAttribute("firstName"));
	
		// clean session and login as ordinary person
		$this->login('personForTest1', 'personForTest1');
	
		// create with session but not in personCreator role
		$isException = false;
		try{
			$person2 = Person::create(array('firstName'=>"test Person 2",'accountName'=>'testPerson2'));
		}catch (NoAccessException $e){
			$isException = true;
		}
		parent::assertTrue($isException);
	
		// clean session and login as person creator
		$this->login('personCreator1', 'personCreator1');
	
		// create with session in personCreator role
		$isException = false;
		try{
			$person2 = Person::create(array('firstName'=>"test Person 2",'accountName'=>'testPerson2'));
		}catch (NoAccessException $e){
			$isException = true;
		}
		parent::assertTrue(!$isException);
		parent::assertEquals("testPerson2", $person2->getAttribute("accountName"));
		parent::assertEquals("test Person 2", $person2->getAttribute("firstName"));
	
	
		// create with complete data
		$person3 = Person::create(array(
				"firstName"  => "Test",
				"middleName" => "Person",
				"lastName"   => "3",
				"dob"        => MySql::getMysqlDate("01st Jan 1987"),
				"gender"     => Person::GENDER_MALE,
				"contact"    => array(
						"emailId" => "testPerson3@gmail.com",
						"phoneNo" => "1234567891",
						"address" => "#1,2nd cross, 3rd main, JP Nagar , Bangalore - 560078"
				),
				"accountName"=>"testPerson3"
		));
		parent::assertEquals("Test", $person3->getAttribute('firstName'));
		parent::assertEquals("Person", $person3->getAttribute('middleName'));
		parent::assertEquals("3", $person3->getAttribute('lastName'));
		parent::assertEquals("1987-01-01", $person3->getAttribute('dob'));
		parent::assertEquals(Person::GENDER_MALE, $person3->getAttribute('gender'));
		parent::assertEquals(array(
						"emailId" => "testPerson3@gmail.com",
						"phoneNo" => "1234567891",
						"address" => "#1,2nd cross, 3rd main, JP Nagar , Bangalore - 560078"
				), $person3->getAttribute('contact'));
		parent::assertEquals("testPerson3", $person3->getAttribute('accountName'));
		
	}
	
	function testFind(){
		// find without session
		
		$isException = false;
		try{
			$people = Person::find(array("firstName"=>"person For Test"));
		}catch (NoAccessException $e){
			$isException = true;
		}
		parent::assertTrue($isException);

		// find with systemAdmin session
		$this->setSystemAdminSession();
		$isException = false;
		try{
			$people = Person::find(array("firstName"=>array(Person::OPERATOR_LIKE=>"person")));
		}catch (NoAccessException $e){
			$isException = true;
		}
		parent::assertTrue(!$isException);
		parent::assertCount(2,$people);

		// find with person creator session
		$this->login('personCreator1', 'personCreator1');
		$isException = false;
		try{
			$people = Person::find(array("firstName"=>array(Person::OPERATOR_LIKE=>"person")));
		}catch (NoAccessException $e){
			$isException = true;
		}
		parent::assertTrue(!$isException);
		parent::assertCount(1,$people);
		parent::assertEquals($this->personCreator->getAttribute('firstName'), $people[0]->getAttribute('firstName'));
		
		// create an organization and add personForTest
		$organization = Organization::create(array("accountName"=>"myOrg1","name"=>"My Org 1"));
		$organization->addPeople(array($this->personForTest));
		
		// find with person creator session + some members in the his organization
		$this->login('personCreator1', 'personCreator1');
		$isException = false;
		try{
			$people = Person::find(array("firstName"=>array(Person::OPERATOR_LIKE=>"person")));
		}catch (NoAccessException $e){
			$isException = true;
		}
		parent::assertTrue(!$isException);
		parent::assertCount(2,$people);
	}
	
	function testUpdate(){
		// update without session
		
		$isException = false;
		try{
			$this->personCreator->setAttribute('lastName', 'creator');
		}catch (NoAccessException $e){
			$isException = true;
		}
		parent::assertTrue($isException);
		
		// update with systemAdmin session
		$this->setSystemAdminSession();
		$isException = false;
		try{
			$this->personCreator->setAttribute('lastName', 'creator');
		}catch (NoAccessException $e){
			$isException = true;
		}
		parent::assertTrue(!$isException);
		parent::assertEquals('creator',$this->personCreator->getAttribute('lastName'));
		
		// update personCreator with person creator session
		$this->login('personCreator1', 'personCreator1');
		$isException = false;
		try{
			$this->personCreator->setAttribute('middleName', 'mid-name');
		}catch (NoAccessException $e){
			$isException = true;
		}
		parent::assertTrue(!$isException);
		parent::assertEquals('mid-name',$this->personCreator->getAttribute('middleName'));
		
		// update personForTest with person creator session
		$isException = false;
		try{
			$this->personForTest->setAttribute('lastName', 'test');
		}catch (NoAccessException $e){
			$isException = true;
		}
		parent::assertTrue($isException);
		
		// create an organization and add personForTest
		$organization = Organization::create(array("accountName"=>"myOrg1","name"=>"My Org 1"));
		$organization->addPeople(array($this->personForTest));
		
		// update personForTest with person creator session + in own organization
		$this->login('personCreator1', 'personCreator1');
		$isException = false;
		try{
			$this->personForTest->setAttribute('lastName', 'test');
		}catch (NoAccessException $e){
			$isException = true;
		}
		parent::assertTrue($isException);
		
		// create person 
		$person1 = Person::create(array('firstName'=>"test Person 1",'accountName'=>'testPerson1'));
		
		// edit created person 
		$person1->setAttribute('lastName', 'Last-name');
		parent::assertEquals('Last-name', $person1->getAttribute('lastName'));
		
	}
	
	function testDelete(){
		// delete without session
		$isException = false;
		try{
			$this->personForTest->delete();
		}catch (NoAccessException $e){
			$isException = true;
		}
		parent::assertTrue($isException);
		
		// delete from other's session
		$this->login('personCreator1', 'personCreator1');
		$isException = false;
		try{
			$this->personForTest->delete();
		}catch (NoAccessException $e){
			$isException = true;
		}
		parent::assertTrue($isException);
		
		// delete in his own session
		$this->login('personForTest1', 'personForTest1');
		$isException = false;
		try{
			$this->personForTest->delete();
		}catch (NoAccessException $e){
			$isException = true;
		}
		parent::assertTrue(!$isException);
		
		$this->login('personCreator1', 'personCreator1');
		// create an organization and add people
		$organization = Organization::create(array("accountName"=>"myOrg1","name"=>"My Org 1"));
		$person1 = Person::create(array('firstName'=>"test Person 1",'accountName'=>'testPerson1'));
		$organization->addPeople(array($person1));
		$person2 = Person::create(array('firstName'=>"test Person 2",'accountName'=>'testPerson2'));
		
		//logout and create people and add to organization
		$this->login();
		$person3 = $person4 = null;
		TransactionManager::executeInTransaction(function() use(&$person3,&$person4,$organization){
			$person3 = Person::create(array('firstName'=>"test Person 3",'accountName'=>'testPerson3'));
			$organization->addPeople(array($person3));
			$person4 = Person::create(array('firstName'=>"test Person 4",'accountName'=>'testPerson4'));
		},array(),true);
		
		$this->login('personCreator1', 'personCreator1');
		
		// delete created person in own organization
		$person1->delete();
		
		// delete created person not in own organization
		$person2->delete();
		
		// delete not created person in own organization
		$isException = false;
		try{
			$person3->delete();
		}catch (NoAccessException $e){
			$isException = true;
		}
		parent::assertTrue($isException);
		
		// delete all in systemAdmin Session
		$this->setSystemAdminSession();
		$person3->delete();
		$person4->delete();

	}
	
	function testGetOrganizations(){
		// without session
		$isException = false;
		try{
			$organizations = $this->personCreator->getOrganizations();
		}catch (NoAccessException $e){
			$isException = true;
		}
		parent::assertTrue($isException);
		
		$this->login('personCreator1', 'personCreator1');
		
		// with session
		$organizations = $this->personCreator->getOrganizations();
		parent::assertCount(0, $organizations);
		
		// create organization and add people
		$organization = Organization::create(array("accountName"=>"myOrg1","name"=>"My Org 1"));
		$organization->addPeople(array($this->personForTest));
		
		$organizations = $this->personCreator->getOrganizations();
		parent::assertCount(1, $organizations);
		parent::assertEquals($organization->getAttribute("name"), $organizations[0]->getAttribute("name"));
		
		$organizations = $this->personForTest->getOrganizations();
		parent::assertCount(1, $organizations);
		parent::assertEquals($organization->getAttribute("name"), $organizations[0]->getAttribute("name"));
		
		//logout and create organization and add personForTest to it
		$this->login();
		$organization2 = null;
		$personForTest = $this->personForTest;
		TransactionManager::executeInTransaction(function() use(&$organization2,$personForTest){
			$organization2 = Organization::create(array("accountName"=>"myOrg2","name"=>"My Org 2"));
			$organization2->addPeople(array($personForTest));
		},array(),true);
		
		// get organizations that loggedin person belongs 
		$this->login('personCreator1', 'personCreator1');
		$organizations = $this->personForTest->getOrganizations();
		parent::assertCount(1, $organizations);
		parent::assertEquals($organization->getAttribute("name"), $organizations[0]->getAttribute("name"));
		
		$this->login('personForTest1', 'personForTest1');
		$organizations = $this->personForTest->getOrganizations();
		parent::assertCount(2, $organizations);
	}
	
	function testGetRoles(){
		/**
		 * data
		 */
		$role1 = $role2 = $role3 = $role4 = null;
		$personCreator = $this->personCreator;
		$personForTest = $this->personForTest;
		
		TransactionManager::executeInTransaction(function() use(&$role1,&$role2,&$role3,&$role4,$personCreator,$personForTest){
			$role1 = Role::create(array("accountName"=>"role1","name"=>"Role 1"));
			$role2 = Role::create(array("accountName"=>"role2","name"=>"Role 2"));
			$role3 = Role::create(array("accountName"=>"role3","name"=>"Role 3"));
			$role4 = Role::create(array("accountName"=>"role4","name"=>"Role 4"));
				
			ComposedRoles::create(array("roleId"=>$role1->getAttribute('id'),"composedRoleId"=>$role2->getAttribute('id')));
			ComposedRoles::create(array("roleId"=>$role2->getAttribute('id'),"composedRoleId"=>$role3->getAttribute('id')));
			ComposedRoles::create(array("roleId"=>$role2->getAttribute('id'),"composedRoleId"=>$role4->getAttribute('id')));
			
			$personCreator->addRoles(array($role1));
			$personForTest->addRoles(array($role2));
			
		},array(),true);
		
		// without session
		$isException = false;
		try{
			$roles = $this->personCreator->getRoles();
		}catch (NoAccessException $e){
			$isException = true;
		}
		parent::assertTrue($isException);
		
		$this->login('personCreator1', 'personCreator1');
		
		$roles = $this->personCreator->getRoles(); // personCreator, orgCreator, roleCreator, role1
		parent::assertCount(4, $roles);
		$roleNames = array_map(function($role){return $role->getAttribute('accountName');},$roles);
		parent::assertEquals(array('personCreator', 'orgCreator', 'roleCreator', 'role1'), $roleNames);
		
		$roles = $this->personCreator->getRoles(true); // personCreator, orgCreator, roleCreator, role1, role2, role3, role4
		parent::assertCount(7, $roles);
		$roleNames = array_map(function($role){return $role->getAttribute('accountName');},$roles);
		parent::assertEquals(array('personCreator','orgCreator', 'roleCreator', 'role1','role2','role3','role4'), $roleNames);
		
		$this->login('personForTest1', 'personForTest1');
		
		$roles = $this->personForTest->getRoles(); // role2
		parent::assertCount(1, $roles);
		$roleNames = array_map(function($role){return $role->getAttribute('accountName');},$roles);
		parent::assertEquals(array('role2'), $roleNames);
		
		$roles = $this->personForTest->getRoles(true); // role2, role3, role4
		parent::assertCount(3, $roles);
		$roleNames = array_map(function($role){return $role->getAttribute('accountName');},$roles);
		parent::assertEquals(array('role2','role3','role4'), $roleNames);
	}
	
	function testAddRemoveRole(){
		/**
		 * data
		 */
		$role1 = $role2 = $role3 = $role4 = null;
		$role_RoleCreator = null;
		$this->login('personCreator1','personCreator1');
		$role1 = Role::create(array("accountName"=>"role1","name"=>"Role 1"));
		
		$this->login();	
		TransactionManager::executeInTransaction(function() use($role1,&$role2,&$role3,&$role4,&$role_RoleCreator){
			$role2 = Role::create(array("accountName"=>"role2","name"=>"Role 2"));
			$role3 = Role::create(array("accountName"=>"role3","name"=>"Role 3"));
			$role4 = Role::create(array("accountName"=>"role4","name"=>"Role 4"));
		
			ComposedRoles::create(array("roleId"=>$role1->getAttribute('id'),"composedRoleId"=>$role2->getAttribute('id')));
			ComposedRoles::create(array("roleId"=>$role2->getAttribute('id'),"composedRoleId"=>$role3->getAttribute('id')));
			ComposedRoles::create(array("roleId"=>$role2->getAttribute('id'),"composedRoleId"=>$role4->getAttribute('id')));
			
			$role_RoleCreator = new Role(null,'roleCreator');
				
		},array(),true);
		
		
		// with out session
		// + add role
		$isException = false;
		try{
			$this->personCreator->addRoles(array($role1));
		}catch (NoAccessException $e){
			$isException = true;
		}
		parent::assertTrue($isException);
		
		// + remove role
		$isException = false;
		try{
			$this->personCreator->removeRoles(array($role_RoleCreator));
		}catch (NoAccessException $e){
			$isException = true;
		}
		parent::assertTrue($isException);
		
		// with session
		$this->login('personCreator1','personCreator1');
		
		// + add own role
		$this->personCreator->addRoles(array($role1));
		parent::assertCount(4, $this->personCreator->getRoles());
		
		// - remove own role
		$this->personCreator->removeRoles(array($role1));
		parent::assertCount(3, $this->personCreator->getRoles());
		
		// + add other role
		$isException = false;
		try{
			$this->personCreator->addRoles(array($role2));
		}catch (\PhpPlatform\Errors\Exceptions\Application\NoAccessException $e){
			$isException = true;
			parent::assertEquals('No access to add role '.$role2->getAttribute('accountName'), $e->getMessage());
		}
		parent::assertTrue($isException);
		parent::assertCount(3, $this->personCreator->getRoles());
		
		// - remove other role
		$isException = false;
		try{
			$this->personCreator->removeRoles(array($role_RoleCreator));
		}catch (\PhpPlatform\Errors\Exceptions\Application\NoAccessException $e){
			$isException = true;
			parent::assertEquals('No access to remove role '.$role_RoleCreator->getAttribute('accountName'), $e->getMessage());
		}
		parent::assertTrue($isException);
		parent::assertCount(3, $this->personCreator->getRoles());
		
	}
	
}