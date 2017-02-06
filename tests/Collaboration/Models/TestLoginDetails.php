<?php

namespace PHPPlatform\Tests\Collaboration\Models;

use PhpPlatform\Tests\Collaboration\TestBase;
use PhpPlatform\Collaboration\Models\LoginDetails;
use PhpPlatform\Errors\Exceptions\Persistence\NoAccessException;
use PhpPlatform\Errors\Exceptions\Persistence\DataNotFoundException;
use PhpPlatform\Errors\Exceptions\Persistence\BadQueryException;
use PhpPlatform\Persist\TransactionManager;
use PhpPlatform\Collaboration\Models\Person;
use PhpPlatform\Collaboration\Models\LoginHistory;

class TestLoginDetails extends TestBase {
	
	function _testConstructor(){
		// construct without session
		$isException = false;
		try{
			new LoginDetails('systemAdmin');
		}catch (NoAccessException $e){
			$isException = true;
		}
		parent::assertTrue($isException);
		
		
		// construct with systemAdmin session
		$this->setSystemAdminSession();
		$isException = false;
		try{
			new LoginDetails('systemAdmin');
		}catch (DataNotFoundException $e){
			$isException = true;
		}catch (NoAccessException $e){
			$isException = true;
		}
		parent::assertTrue(!$isException);
	}
	
	function _testCreate(){
		// create without session
		$isException = false;
		try{
			LoginDetails::create(array("personId"=>"1","loginName"=>"abcd1234","password"=>"123"));
		}catch (NoAccessException $e){
			$isException = true;
		}
		parent::assertTrue($isException);
		
		// create with systemAdmin session, and no personId
		$this->setSystemAdminSession();
		$isException = false;
		try{
			LoginDetails::create(array("loginName"=>"testPerson1","password"=>"123"));
		}catch (BadQueryException $e){
			$isException = true;
		}
		parent::assertTrue($isException);
		
		// create a test person account
		$personObj = null;
		TransactionManager::executeInTransaction(function () use (&$personObj){
			$personObj = Person::create(array("accountName"=>"testPerson1","name"=>"Test Person 1"));
		},array(),true);
		
		// create with systemAdmin session, and with personId
		$this->setSystemAdminSession();
		$isException = false;
		try{
			$loginDetails = LoginDetails::create(array("personId"=>$personObj->getAttribute("id"),"loginName"=>"testPerson1","password"=>"123"));
		}catch (BadQueryException $e){
			$isException = true;
		}
		parent::assertTrue(!$isException);
		parent::assertEquals('2', $loginDetails->getAttribute('id'));
		parent::assertEquals('testPerson1', $loginDetails->getAttribute('loginName'));
		parent::assertEquals('PENDING_VERIFICATION', $loginDetails->getAttribute('status'));
		parent::assertEquals(null, $loginDetails->getAttribute('password'));
		
		// assert login history
		TransactionManager::executeInTransaction(function () use (&$loginDetails){
			$loginHistories = LoginHistory::find(array('logindetailsId'=>$loginDetails->getAttribute('id'),'type'=>LoginHistory::LH_REGISTRATION));
			parent::assertCount(1, $loginHistories);
		},array(),true);
		
		// create another test person account
		$personObj2 = null;
		TransactionManager::executeInTransaction(function () use (&$personObj2){
			$personObj2 = Person::create(array("accountName"=>"testPerson2","name"=>"Test Person 2"));
		},array(),true);
		
		// login with test person 1
		Person::login('testPerson1', '123');
		
		// create with test person1's session 
		$isException = false;
		try{
			$loginDetails = LoginDetails::create(array("personId"=>$personObj2->getAttribute("id"),"loginName"=>"testPerson2","password"=>"1234"));
		}catch (NoAccessException $e){
			$isException = true;
		}
		parent::assertTrue($isException);
	}
	
	function testFind(){
		// find without session
		$isException = false;
		try{
		    $loginDetails = LoginDetails::find(array("loginName"=>"systemAdmin"));
		}catch (NoAccessException $e){
			$isException = true;
		}
		parent::assertTrue($isException);
		
		// find with systemAdmin session
		$this->setSystemAdminSession();
		$loginDetails = LoginDetails::find(array("loginName"=>"systemAdmin"));
		parent::assertCount(1,$loginDetails);
		
		// create a test person account
		$personObj = null;
		TransactionManager::executeInTransaction(function () use (&$personObj){
			$personObj = Person::create(array("accountName"=>"testPerson1","name"=>"Test Person 1"));
			LoginDetails::create(array("personId"=>$personObj->getAttribute("id"),"loginName"=>"testPerson1","password"=>"123"));
		},array(),true);
		
		// login with test person
		Person::login('testPerson1', '123');
		
		// find with test person
		$loginDetails = LoginDetails::find(array("loginName"=>"systemAdmin"));
		parent::assertCount(0,$loginDetails);
		
		$loginDetails = LoginDetails::find(array("loginName"=>"testPerson1"));
		parent::assertCount(1,$loginDetails);
		
	}
	
}