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
use PhpPlatform\Persist\MySql;
use PhpPlatform\Config\Settings;
use PhpPlatform\Errors\Exceptions\Application\BadInputException;
use PhpPlatform\Persist\Reflection;

class TestLoginDetails extends TestBase {
	
	function testConstructor(){
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
	
	function testCreate(){
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
		$loginHistories = null;
		TransactionManager::executeInTransaction(function () use (&$loginDetails,&$loginHistories){
			$loginHistories = LoginHistory::find(array('logindetailsId'=>$loginDetails->getAttribute('id'),'type'=>LoginHistory::LH_REGISTRATION));
		},array(),true);
		parent::assertCount(1, $loginHistories);
			
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
	
	function testGetAttributes(){
		$systemAdminLoginDetails = null;
		TransactionManager::executeInTransaction(function () use (&$systemAdminLoginDetails){
			$systemAdminLoginDetails = new LoginDetails('systemAdmin');
		},array(),true);
		
		parent::assertEquals(array(
		    "id" => 1,
		    "personId" => 1,
		    "loginName" => "systemAdmin",
		    "status" => "ACTIVE"
		),$systemAdminLoginDetails->getAttributes("*"));
	}
	
	function testDelete(){
		$systemAdminLoginDetails = null;
		$testPersonLoginDetails = null;
		
		TransactionManager::executeInTransaction(function () use (&$systemAdminLoginDetails,&$testPersonLoginDetails){
			$systemAdminLoginDetails = new LoginDetails('systemAdmin');
			
			$personObj = Person::create(array("accountName"=>"testPerson1","name"=>"Test Person 1"));
			$testPersonLoginDetails = LoginDetails::create(array("personId"=>$personObj->getAttribute("id"),"loginName"=>"testPerson1","password"=>"123"));
			
		},array(),true);
		
		
		// delete without session
		$isException = false;
		try{
			$systemAdminLoginDetails->delete();
		}catch (NoAccessException $e){
			$isException = true;
		}
		parent::assertTrue($isException);
		
		
		$isException = false;
		try{
			$testPersonLoginDetails->delete();
		}catch (NoAccessException $e){
			$isException = true;
		}
		parent::assertTrue($isException);
		
		
		// delete with test person login
		Person::login('testPerson1', '123');
		$isException = false;
		try{
			$systemAdminLoginDetails->delete();
		}catch (NoAccessException $e){
			$isException = true;
		}
		parent::assertTrue($isException);
		
		
		$isException = false;
		try{
			$testPersonLoginDetails->delete();
		}catch (NoAccessException $e){
			$isException = true;
		}
		parent::assertTrue($isException);
		
		
		// test with systemAdmin login
		$this->setSystemAdminSession();
		$isException = false;
		try{
			$systemAdminLoginDetails->delete();
		}catch (NoAccessException $e){
			$isException = true;
		}
		parent::assertTrue(!$isException);
		
		
		$isException = false;
		try{
			$testPersonLoginDetails->delete();
		}catch (NoAccessException $e){
			$isException = true;
		}
		parent::assertTrue(!$isException);
		
		$loginDetailsList = null;
		TransactionManager::executeInTransaction(function () use(&$loginDetailsList){
			$loginDetailsList = LoginDetails::find(array("loginName"=>array(LoginDetails::OPERATOR_IN=>array('systemAdmin','testPerson1'))));
		},array(),true);
		
		parent::assertTrue(is_array($loginDetailsList));
		parent::assertCount(0,$loginDetailsList);
	}
	
	public function testPasswordReset(){
	
		/**
		 * Data
		 */
		$testPersonLoginDetails = null;
		
		TransactionManager::executeInTransaction(function () use (&$systemAdminLoginDetails,&$testPersonLoginDetails){
			$systemAdminLoginDetails = new LoginDetails('systemAdmin');
			
			$personObj = Person::create(array("accountName"=>"testPerson1","name"=>"Test Person 1"));
			$testPersonLoginDetails = LoginDetails::create(array("personId"=>$personObj->getAttribute("id"),"loginName"=>"testPerson1","password"=>"123"));
			
		},array(),true);
	
		/**
		 * Request Password reset
		 */
		// request password reset for non-existing details, should result in exception
		$isException = false;
		try{
			$token = LoginDetails::requestPasswordReset("noLoginName");
		}catch (DataNotFoundException $e){
			$isException = true;
		}
		$this->assertTrue($isException);
	
		//make a password reset request for status not ACTIVE
		$isException = false;
		try{
			$token = LoginDetails::requestPasswordReset("testPerson1");
		}catch (DataNotFoundException $e){
			$isException = true;
		}
		$this->assertTrue($isException);
		
		// make the test person login details active
		TransactionManager::executeInTransaction(function () use (&$testPersonLoginDetails){
			$testPersonLoginDetails->activate();
		},array(),true);
		
		//make a password reset request
		$token = LoginDetails::requestPasswordReset("testPerson1");
	
		$loginHistories = array();
		TransactionManager::executeInTransaction(function () use (&$loginHistories,&$testPersonLoginDetails){
			$loginHistories = LoginHistory::find(array("logindetailsId"=>$testPersonLoginDetails->getAttribute("id"),"type"=>LoginHistory::LH_PASSWORD_RESET_REQUEST));
		},array(),true);
		
		$this->assertCount(1,$loginHistories);
		$loginId = $testPersonLoginDetails->getAttribute("id");
		$loginName = $testPersonLoginDetails->getAttribute("loginName");
		$this->assertEquals($loginId.md5($loginId.$loginName.$token),$loginHistories[0]->getAttribute("sessionId"));
	
		/**
		 * Validate password reset token
		 */
		// validate with wrong token
		$isException = false;
		try{
			LoginDetails::validatePasswordResetToken($loginName,'ABCD');
		}catch (BadInputException $e){
			$isException = true;
		}
		$this->assertTrue($isException);
	
		//validate with expired token
		$expiredToken = LoginDetails::requestPasswordReset($loginName);
		// manually expire the token
		TransactionManager::executeInTransaction(function () use (&$expiredToken,$loginId,$loginName){
			$loginHistories = LoginHistory::find(array("sessionId"=>$loginId.md5($loginId.$loginName.$expiredToken)));
			
			$currentTime = time();
			$tokenLifetime = Settings::getSettings('php-platform/collaboration','passwordResetRequestLifetime');
			$expiredTime = $currentTime - $tokenLifetime -1;
			$expiredTime = date('d-M-Y H:i:s',$expiredTime);
			Reflection::invokeArgs('PhpPlatform\Persist\Model', 'setAttributes', $loginHistories[0],array(array("time"=>MySql::getMysqlDate($expiredTime,true))));
		},array(),true);
		
		$isException = false;
		try{
			LoginDetails::validatePasswordResetToken($loginName,$expiredToken);
		}catch (BadInputException $e){
			$isException = true;
		}
		$this->assertTrue($isException);
	
		//validate correct token
		$validationToken = LoginDetails::validatePasswordResetToken($loginName,$token);
	
		/**
		 * Reset Password
		 */
		// reset with wrong tokens
		
		$isException = false;
		try{
			LoginDetails::resetPassword($loginName,'aaa','bbb','newPWD001');
		}catch (BadInputException $e){
			$isException = true;
		}
		$this->assertTrue($isException);
	
		$isException = false;
		try{
			LoginDetails::resetPassword($loginName,$token,'bbb','newPWD001');
		}catch (BadInputException $e){
			$isException = true;
		}
		$this->assertTrue($isException);
	
		$isException = false;
		try{
			LoginDetails::resetPassword($loginName,'aaa',$validationToken,'newPWD001');
		}catch (BadInputException $e){
			$isException = true;
		}
		$this->assertTrue($isException);
	
		$isException = false;
		try{
			LoginDetails::resetPassword('abcd',$token,$validationToken,'newPWD001');
		}catch (DataNotFoundException $e){
			$isException = true;
		}
		$this->assertTrue($isException);
	
	
		//manually expire the validation token
		$loginHistories = null;
		$validTime = null;
		TransactionManager::executeInTransaction(function () use (&$loginHistories,&$validTime,&$validationToken,$loginId,$loginName){
			$loginHistories = LoginHistory::find(array("sessionId"=>$loginId.md5($loginId.$validationToken.$loginName)));

			$validTime = $loginHistories[0]->getAttribute('time');
			
			$currentTime = time();
			$tokenLifetime = Settings::getSettings('php-platform/collaboration','passwordResetRequestValidationLifetime');
			$expiredTime = $currentTime - $tokenLifetime -1;
			$expiredTime = date('d-M-Y H:i:s',$expiredTime);
			Reflection::invokeArgs('PhpPlatform\Persist\Model', 'setAttributes', $loginHistories[0],array(array("time"=>MySql::getMysqlDate($expiredTime,true))));
		},array(),true);
		
	
		$isException = false;
		try{
			LoginDetails::resetPassword($loginName,$token,$validationToken,'newPWD001');
		}catch (BadInputException $e){
			$isException = true;
		}
		$this->assertTrue($isException);
	
		//manually reset expired token
		TransactionManager::executeInTransaction(function () use (&$loginHistories,&$validTime){
			Reflection::invokeArgs('PhpPlatform\Persist\Model', 'setAttributes', $loginHistories[0],array(array("time"=>MySql::getMysqlDate($validTime,true))));
		},array(),true);
		
	    // reset password
		LoginDetails::resetPassword($loginName,$token,$validationToken,'newPWD002');
	
		// validate the new password works 
		TransactionManager::executeInTransaction(function () use (&$systemAdminLoginDetails,&$testPersonLoginDetails){
			$testPersonLoginDetails = new LoginDetails("testPerson1");
		},array(),true);
		
		$isException = false;
		try{
			$testPersonLoginDetails->login('newPWD002');
		}catch (\Exception $e){
			$isException = true;
		}
		$this->assertTrue(!$isException);
	}
	
	
}