<?php

namespace PHPPlatform\Tests\Collaboration\Models;

use PhpPlatform\Tests\Collaboration\TestBase;
use PhpPlatform\Collaboration\Models\Organization;
use PhpPlatform\Persist\TransactionManager;
use PhpPlatform\Errors\Exceptions\Persistence\NoAccessException;

class TestOrganization extends TestBase {
	
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
	}
	
	function testFind(){
		/**
		 * data
		 */
		TransactionManager::executeInTransaction(function (){
			Organization::create(array('name'=>"My Org 1",'accountName'=>'myOrg1'));
		},array(),true);
		
		// find without session
		$isException = false;
		try{
			Organization::find(array("name"=>"My Org 1"));
		}catch (NoAccessException $e){
			$isException = true;
		}
		parent::assertTrue($isException);
		
		// find with systemAdmin session
		$this->setSystemAdminSession();
		$isException = false;
		try{
			$Organizations = Organization::find(array("name"=>"My Org 1"));
		}catch (NoAccessException $e){
			$isException = true;
		}
		parent::assertTrue(!$isException);
		parent::assertCount(1,$Organizations);
		parent::assertEquals("myOrg1", $Organizations[0]->getAttribute("accountName"));
		parent::assertEquals("My Org 1", $Organizations[0]->getAttribute("name"));
	}
	
	function testUpdate(){
		$organization = null;
		TransactionManager::executeInTransaction(function() use(&$organization){
			$organization = Organization::create(array('name'=>"My Org 1",'accountName'=>'myOrg1'));
		},array(),true);
	
		// update without session
		$isException = false;
		try{
			$organization->setAttribute("name","new Org");
		}catch (NoAccessException $e){
			$isException = true;
		}
		parent::assertTrue($isException);
		parent::assertEquals("My Org 1", $organization->getAttribute("name"));


		// update with systemAdmin session
		$this->setSystemAdminSession();
		$isException = false;
		try{
			$organization->setAttribute("name","New Organization Name");
			$organization->setAttribute("accountName","New Organization Account Name");
		}catch (NoAccessException $e){
			$isException = true;
		}
		parent::assertTrue(!$isException);
		parent::assertEquals("New Organization Name",$organization->getAttribute("name"));
		parent::assertEquals("New Organization Account Name",$organization->getAttribute("accountName"));
	}
	
	function testDelete(){
		$organization = null;
		TransactionManager::executeInTransaction(function() use(&$organization){
			$organization = Organization::create(array('name'=>"My Org 1",'accountName'=>'myOrg1'));
		},array(),true);
	
		// delete without session
		$isException = false;
		try{
			$organization->delete();
		}catch (NoAccessException $e){
			$isException = true;
		}
		parent::assertTrue($isException);
		parent::assertEquals("My Org 1", $organization->getAttribute("name"));

		// update with systemAdmin session
		$this->setSystemAdminSession();
		$isException = false;
		try{
			$organization->delete();
		}catch (NoAccessException $e){
			$isException = true;
		}
		parent::assertTrue(!$isException);
		$organizations = null;
		TransactionManager::executeInTransaction(function() use(&$organizations){
			$organizations = Organization::find(array('name'=>"My Org 1",'accountName'=>'myOrg1'));
		},array(),true);
		parent::assertCount(0,$organizations);
						
	}
	
	function testGetAttributes(){
		$organization = null;
		TransactionManager::executeInTransaction(function() use(&$organization){
			$organization = Organization::create(array('name'=>"My Org 1",'accountName'=>'myOrg1'));
		},array(),true);
	
		parent::assertEquals(1,$organization->getAttribute('id'));
		parent::assertEquals('My Org 1',$organization->getAttribute('name'));
		parent::assertEquals('myOrg1',$organization->getAttribute('accountName'));
	}
	
	
	function testAddChildren(){
		
	}
	
	
}