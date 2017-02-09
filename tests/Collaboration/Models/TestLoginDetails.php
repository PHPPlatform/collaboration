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
use PhpPlatform\Persist\Exception\ObjectStateException;
use PhpPlatform\Mock\Config\MockSettings;

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
		
		TransactionManager::executeInTransaction(function () use (&$testPersonLoginDetails){
			
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
		TransactionManager::executeInTransaction(function () use (&$testPersonLoginDetails){
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
	
	
	function testChangePassword(){
		$systemAdminLoginDetails = null;
		$testPersonLoginDetails = null;
		
		TransactionManager::executeInTransaction(function () use (&$systemAdminLoginDetails,&$testPersonLoginDetails){
			$systemAdminLoginDetails = new LoginDetails('systemAdmin');
			$personObj = Person::create(array("accountName"=>"testPerson1","name"=>"Test Person 1"));
			$testPersonLoginDetails = LoginDetails::create(array("personId"=>$personObj->getAttribute("id"),"loginName"=>"testPerson1","password"=>"123"));
		},array(),true);
		
		// Change Password without session
		$isException = false;
		try{
			$systemAdminLoginDetails->changePassword("newSystemAdminPass");
		}catch (NoAccessException $e){
			$isException = true;
		}
		parent::assertTrue($isException);
		
		
		$isException = false;
		try{
			$testPersonLoginDetails->changePassword("newTestPersonPass");
		}catch (NoAccessException $e){
			$isException = true;
		}
		parent::assertTrue($isException);
		
		
		// Change Password with test person login
		Person::login('testPerson1', '123');
		$isException = false;
		try{
			$systemAdminLoginDetails->changePassword("newSystemAdminPass");
		}catch (NoAccessException $e){
			$isException = true;
		}
		parent::assertTrue($isException);
		
		
		$isException = false;
		try{
			$testPersonLoginDetails->changePassword("newTestPersonPass");
		}catch (NoAccessException $e){
			$isException = true;
		}
		parent::assertTrue(!$isException);
		Person::login('testPerson1', 'newTestPersonPass');
		
		
		// test with systemAdmin login
		$this->setSystemAdminSession();
		$isException = false;
		try{
			$systemAdminLoginDetails->changePassword("newSystemAdminPass");
		}catch (NoAccessException $e){
			$isException = true;
		}
		parent::assertTrue(!$isException);
		Person::login('systemAdmin', 'newSystemAdminPass');
		
	}
	
	
	function testVerify(){
		/**
		 * data
		 */
		$testPerson1LoginDetails = null;
		$testPerson2LoginDetails = null;
		$testPerson1CreateToken = null;
		$testPerson2CreateToken = null;
		
		
		TransactionManager::executeInTransaction(function () use (&$testPerson1LoginDetails,&$testPerson2LoginDetails,&$testPerson1CreateToken,&$testPerson2CreateToken){
			$person1Obj = Person::create(array("accountName"=>"testPerson1","name"=>"Test Person 1"));
			$testPerson1LoginDetails = LoginDetails::create(array("personId"=>$person1Obj->getAttribute("id"),"loginName"=>"testPerson1","password"=>"123"));

			$loginHistory = LoginHistory::find(array("logindetailsId"=>$testPerson1LoginDetails->getAttribute("id"),"type"=>LoginHistory::LH_REGISTRATION));
			$testPerson1CreateToken = $loginHistory[0]->getAttribute("sessionId");
			
			$person2Obj = Person::create(array("accountName"=>"testPerson2","name"=>"Test Person 2"));
			$testPerson2LoginDetails = LoginDetails::create(array("personId"=>$person2Obj->getAttribute("id"),"loginName"=>"testPerson2","password"=>"abc"));
				
			$loginHistory = LoginHistory::find(array("logindetailsId"=>$testPerson2LoginDetails->getAttribute("id"),"type"=>LoginHistory::LH_REGISTRATION));
			$testPerson2CreateToken = $loginHistory[0]->getAttribute("sessionId");
				
		},array(),true);
		
		
		// verify with out session
		$isException = false;
		try{
			LoginDetails::verify('testPerson1', $testPerson1CreateToken);
		}catch (NoAccessException $e){
			$isException = true;
		}
		parent::assertTrue($isException);
		
		// verify with system admin session
		$this->setSystemAdminSession();
		$isException = false;
		try{
			LoginDetails::verify('testPerson1', $testPerson1CreateToken);
		}catch (NoAccessException $e){
			$isException = true;
		}
		parent::assertTrue(!$isException);
		$testPerson1LoginDetails = new LoginDetails("testPerson1");
		parent::assertEquals(LoginDetails::STATUS_ACTIVE,$testPerson1LoginDetails->getAttribute('status'));
		
		
		// verify test person 2 with test person 1 session
		Person::login('testPerson1', '123');
		
		$isException = false;
		try{
			LoginDetails::verify('testPerson2', $testPerson2CreateToken);
		}catch (NoAccessException $e){
			$isException = true;
		}
		parent::assertTrue($isException);
		
		// reverify test person 1 
		$isException = false;
		try{
			LoginDetails::verify('testPerson1', $testPerson1CreateToken);
		}catch (BadInputException $e){
			$isException = true;
		}
		parent::assertTrue($isException);
		
		//login with test person 2
		Person::login('testPerson2', 'abc');
		
		// verify with wrong token
		$isException = false;
		try{
			LoginDetails::verify('testPerson2', $testPerson1CreateToken);
		}catch (BadInputException $e){
			$isException = true;
		}
		parent::assertTrue($isException);
		
		// verify with right token
		$isException = false;
		try{
			LoginDetails::verify('testPerson2', $testPerson2CreateToken);
		}catch (\Exception $e){
			$isException = true;
		}
		parent::assertTrue(!$isException);
		$testPerson2LoginDetails = new LoginDetails("testPerson2");
		parent::assertEquals(LoginDetails::STATUS_ACTIVE,$testPerson1LoginDetails->getAttribute('status'));
		
	}
	
	
	function testActivate(){
		/**
		 * data
		 */
		$testPerson1LoginDetails = null;
		$testPerson2LoginDetails = null;
		
		TransactionManager::executeInTransaction(function () use (&$testPerson1LoginDetails,&$testPerson2LoginDetails){
			$person1Obj = Person::create(array("accountName"=>"testPerson1","name"=>"Test Person 1"));
			$testPerson1LoginDetails = LoginDetails::create(array("personId"=>$person1Obj->getAttribute("id"),"loginName"=>"testPerson1","password"=>"123"));
			
			$person2Obj = Person::create(array("accountName"=>"testPerson2","name"=>"Test Person 2"));
			$testPerson2LoginDetails = LoginDetails::create(array("personId"=>$person2Obj->getAttribute("id"),"loginName"=>"testPerson2","password"=>"abc"));
			
		},array(),true);
	
		// manually change the status to DISABLED
		$this->manuallyChangeStatus($testPerson1LoginDetails, LoginDetails::STATUS_DISABLED);
		
		// activate with out session
		$isException = false;
		try{
			$testPerson1LoginDetails->activate();
		}catch (NoAccessException $e){
			$isException = true;
		}
		parent::assertTrue($isException);
		
		// manually change the status to PENDING VERIFICATION
		$this->manuallyChangeStatus($testPerson1LoginDetails,LoginDetails::STATUS_DISABLED);

		// activate with system admin session
		$this->setSystemAdminSession();
		$isException = false;
		try{
			$testPerson1LoginDetails->activate();
		}catch (NoAccessException $e){
			$isException = true;
		}
		parent::assertTrue(!$isException);
		$testPerson1LoginDetails = new LoginDetails("testPerson1");
		parent::assertEquals(LoginDetails::STATUS_ACTIVE,$testPerson1LoginDetails->getAttribute('status'));


		// activate test person 2 with test person 1 session
		Person::login('testPerson1', '123');

		// manually change the status to DISABLED
		$this->manuallyChangeStatus($testPerson2LoginDetails,LoginDetails::STATUS_DISABLED);
		
		$isException = false;
		try{
			$testPerson2LoginDetails->activate();
		}catch (NoAccessException $e){
			$isException = true;
		}
		parent::assertTrue($isException);

		// re-activate test person 1
		$isException = false;
		try{
			$testPerson1LoginDetails->activate();
		}catch (BadInputException $e){
			$isException = true;
		}
		parent::assertTrue($isException);
	
		// manually change the status to STATUS_PENDING_VERIFICATION
		$this->manuallyChangeStatus($testPerson2LoginDetails,LoginDetails::STATUS_PENDING_VERIFICATION);
		
		//login with test person 2
		Person::login('testPerson2', 'abc');
		
		// activate logindetails in PENDING VERIFICATION status 
		$isException = false;
		try{
			$testPerson2LoginDetails->activate();
		}catch (\PhpPlatform\Errors\Exceptions\Application\NoAccessException $e){
			$isException = true;
		}
		parent::assertTrue($isException);
		
		// manually disable the login details
		$this->manuallyChangeStatus($testPerson2LoginDetails,LoginDetails::STATUS_DISABLED);
		
		// activate logindetails in DISABLED status 
		$isException = false;
		try{
			$testPerson2LoginDetails->activate();
		}catch (\Exception $e){
			$isException = true;
		}
		parent::assertTrue(!$isException);
		$testPerson2LoginDetails = new LoginDetails("testPerson2");
		parent::assertEquals(LoginDetails::STATUS_ACTIVE,$testPerson1LoginDetails->getAttribute('status'));
		
	}
	
	function testDisable(){
		/**
		 * data
		 */
		$testPerson1LoginDetails = null;
		$testPerson2LoginDetails = null;
	
		TransactionManager::executeInTransaction(function () use (&$testPerson1LoginDetails,&$testPerson2LoginDetails){
			$person1Obj = Person::create(array("accountName"=>"testPerson1","name"=>"Test Person 1"));
			$testPerson1LoginDetails = LoginDetails::create(array("personId"=>$person1Obj->getAttribute("id"),"loginName"=>"testPerson1","password"=>"123"));
				
			$person2Obj = Person::create(array("accountName"=>"testPerson2","name"=>"Test Person 2"));
			$testPerson2LoginDetails = LoginDetails::create(array("personId"=>$person2Obj->getAttribute("id"),"loginName"=>"testPerson2","password"=>"abc"));
				
		},array(),true);
	
		// manually change the status to ACTIVE
		$this->manuallyChangeStatus($testPerson1LoginDetails,LoginDetails::STATUS_ACTIVE);

		// disable with out session
		$isException = false;
		try{
			$testPerson1LoginDetails->disable();
		}catch (NoAccessException $e){
			$isException = true;
		}
		parent::assertTrue($isException);

		// manually change the status to PENDING VERIFICATION
		$this->manuallyChangeStatus($testPerson1LoginDetails,LoginDetails::STATUS_PENDING_VERIFICATION);

		// activate with system admin session
		$this->setSystemAdminSession();
		$isException = false;
		try{
			$testPerson1LoginDetails->disable();
		}catch (NoAccessException $e){
			$isException = true;
		}
		parent::assertTrue(!$isException);
		parent::assertEquals(LoginDetails::STATUS_DISABLED,$testPerson1LoginDetails->getAttribute('status'));


		// disable test person 2 with test person 1 session
		$this->manuallyChangeStatus($testPerson1LoginDetails,LoginDetails::STATUS_ACTIVE);
		Person::login('testPerson1', '123');

		// manually change the status to ACTIVE
		$this->manuallyChangeStatus($testPerson2LoginDetails,LoginDetails::STATUS_ACTIVE);

		$isException = false;
		try{
			$testPerson2LoginDetails->disable();
		}catch (NoAccessException $e){
			$isException = true;
		}
		parent::assertTrue($isException);

		// manually change the status to DISABLE
		$this->manuallyChangeStatus($testPerson1LoginDetails,LoginDetails::STATUS_DISABLED);
		
		// re-disable test person 1
		$isException = false;
		try{
			$testPerson1LoginDetails->disable();
		}catch (BadInputException $e){
			$isException = true;
		}
		parent::assertTrue($isException);

		// manually change the status to STATUS_PENDING_VERIFICATION
		$this->manuallyChangeStatus($testPerson2LoginDetails,LoginDetails::STATUS_PENDING_VERIFICATION);

		//login with test person 2
		Person::login('testPerson2', 'abc');

		// disable logindetails in PENDING VERIFICATION status
		$isException = false;
		try{
			$testPerson2LoginDetails->disable();
		}catch (\PhpPlatform\Errors\Exceptions\Application\NoAccessException $e){
			$isException = true;
		}
		parent::assertTrue($isException);

		// manually activate the login details
		$this->manuallyChangeStatus($testPerson2LoginDetails,LoginDetails::STATUS_ACTIVE);

		// disable logindetails in ACTIVE status
		$isException = false;
		try{
			$testPerson2LoginDetails->disable();
		}catch (\Exception $e){
			$isException = true;
		}
		parent::assertTrue(!$isException);
		parent::assertEquals(LoginDetails::STATUS_DISABLED,$testPerson1LoginDetails->getAttribute('status'));

	}
	
	private function manuallyChangeStatus(&$loginDetails,$status){
		TransactionManager::executeInTransaction(function () use (&$loginDetails,&$status){
			Reflection::invokeArgs('PhpPlatform\Persist\Model', 'setAttributes', $loginDetails,array(array("status"=>$status)));
		},array(),true);
	}
	
	function testChangeLoginName(){
		/**
		 * data
		 */
		$testPerson1LoginDetails = null;
		$testPerson2LoginDetails = null;
		
		TransactionManager::executeInTransaction(function () use (&$testPerson1LoginDetails,&$testPerson2LoginDetails){
			$person1Obj = Person::create(array("accountName"=>"testPerson1","name"=>"Test Person 1"));
			$testPerson1LoginDetails = LoginDetails::create(array("personId"=>$person1Obj->getAttribute("id"),"loginName"=>"testPerson1","password"=>"123"));
		
			$person2Obj = Person::create(array("accountName"=>"testPerson2","name"=>"Test Person 2"));
			$testPerson2LoginDetails = LoginDetails::create(array("personId"=>$person2Obj->getAttribute("id"),"loginName"=>"testPerson2","password"=>"abc"));
		
		},array(),true);
		
		// change name with out session
		$isException = false;
		try{
			$testPerson1LoginDetails->changeLoginName('newLoginName1', '123');
		}catch (\PhpPlatform\Errors\Exceptions\Application\NoAccessException $e){
			$isException = true;
		}
		parent::assertTrue($isException);
		
		// change name with system admin session
		$this->setSystemAdminSession();
		$isException = false;
		try{
			$testPerson1LoginDetails->changeLoginName('newLoginName1', '123');
		}catch (NoAccessException $e){
			$isException = true;
		}
		parent::assertTrue(!$isException);
		
		$isException = false;
		try{
			$testPerson1LoginDetails->getAttribute('loginName');
		}catch (ObjectStateException $e){
			$isException = true;
		}
		parent::assertTrue($isException);
		
		$isException = false;
		try{
			Person::login('testPerson1', '123');
		}catch (\PhpPlatform\Errors\Exceptions\Application\NoAccessException $e){
			$isException = true;
		}
		parent::assertTrue($isException);
		
		// login from test person 1
		Person::login('newLoginName1', '123');
		
		// change the name of test person 2
		$isException = false;
		try{
			$testPerson2LoginDetails->changeLoginName('newLoginName2', 'abc');
		}catch (\PhpPlatform\Errors\Exceptions\Application\NoAccessException $e){
			$isException = true;
		}
		parent::assertTrue($isException);
		
		// login from test person 2
		Person::login('testPerson2', 'abc');
		
		// change the name of test person 2 with wrong password
		$isException = false;
		try{
			$testPerson2LoginDetails->changeLoginName('newLoginName2', 'wrong-password');
		}catch (\PhpPlatform\Errors\Exceptions\Application\NoAccessException $e){
			$isException = true;
		}
		parent::assertTrue($isException);
		
		// change the name of test person 2 with right password
		$isException = false;
		try{
			$testPerson2LoginDetails->changeLoginName('newLoginName2', 'abc');
		}catch (\Exception $e){
			$isException = true;
		}
		parent::assertTrue(!$isException);
		$isException = false;
		try{
			Person::login('testPerson2', 'abc');
		}catch (\PhpPlatform\Errors\Exceptions\Application\NoAccessException $e){
			$isException = true;
		}
		parent::assertTrue($isException);
		
		// login from test person 2
		Person::login('newLoginName2', 'abc');
		
	}
	
	function testLogin(){
		/**
		 * data
		 */
		$testPerson1LoginDetails = null;
		
		TransactionManager::executeInTransaction(function () use (&$testPerson1LoginDetails){
			$person1Obj = Person::create(array("accountName"=>"testPerson1","name"=>"Test Person 1"));
			$testPerson1LoginDetails = LoginDetails::create(array("personId"=>$person1Obj->getAttribute("id"),"loginName"=>"testPerson1","password"=>"123"));
		},array(),true);
		
		// login with wrong password
		$isException = false;
		try{
			$testPerson1LoginDetails->login('123456');
		}catch (\PhpPlatform\Errors\Exceptions\Application\NoAccessException $e){
			$isException = true;
		}
		parent::assertTrue($isException);
		
		// login with right password and no history
		MockSettings::setSettings('php-platform/collaboration', "saveLoginHistory", false);
		$isException = false;
		try{
			$testPerson1LoginDetails->login('123');
		}catch (\PhpPlatform\Errors\Exceptions\Application\NoAccessException $e){
			$isException = true;
		}
		parent::assertTrue(!$isException);
		
		// validate login history
		$loginHistory = null;
		TransactionManager::executeInTransaction(function () use (&$loginHistory,$testPerson1LoginDetails){
			$loginHistory = LoginHistory::find(array("logindetailsId"=>$testPerson1LoginDetails->getAttribute('id'),"type"=>LoginHistory::LH_LOGIN));
		},array(),true);
		parent::assertCount(0, $loginHistory);
		
		// login with right password and with history
		MockSettings::setSettings('php-platform/collaboration', "saveLoginHistory", true);
		$isException = false;
		try{
			$testPerson1LoginDetails->login('123');
		}catch (\PhpPlatform\Errors\Exceptions\Application\NoAccessException $e){
			$isException = true;
		}
		parent::assertTrue(!$isException);
		
		// validate login history
		$loginHistory = null;
		TransactionManager::executeInTransaction(function () use (&$loginHistory,$testPerson1LoginDetails){
			$loginHistory = LoginHistory::find(array("logindetailsId"=>$testPerson1LoginDetails->getAttribute('id'),"type"=>LoginHistory::LH_LOGIN));
		},array(),true);
		parent::assertCount(1, $loginHistory);
		
	}
	
	function testLogout(){
		/**
		 * data
		 */
		$testPerson1LoginDetails = null;
	
		TransactionManager::executeInTransaction(function () use (&$testPerson1LoginDetails){
			$person1Obj = Person::create(array("accountName"=>"testPerson1","name"=>"Test Person 1"));
			$testPerson1LoginDetails = LoginDetails::create(array("personId"=>$person1Obj->getAttribute("id"),"loginName"=>"testPerson1","password"=>"123"));
		},array(),true);
	
		
		// logout with right password and no history
		MockSettings::setSettings('php-platform/collaboration', "saveLoginHistory", false);
		$isException = false;
		try{
			$testPerson1LoginDetails->logout();
		}catch (\PhpPlatform\Errors\Exceptions\Application\NoAccessException $e){
			$isException = true;
		}
		parent::assertTrue(!$isException);

		// validate login history
		$loginHistory = null;
		TransactionManager::executeInTransaction(function () use (&$loginHistory,$testPerson1LoginDetails){
			$loginHistory = LoginHistory::find(array("logindetailsId"=>$testPerson1LoginDetails->getAttribute('id'),"type"=>LoginHistory::LH_LOGOUT));
		},array(),true);
			parent::assertCount(0, $loginHistory);

		// login with right password and with history
		MockSettings::setSettings('php-platform/collaboration', "saveLoginHistory", true);
		$isException = false;
		try{
			$testPerson1LoginDetails->logout();
		}catch (\PhpPlatform\Errors\Exceptions\Application\NoAccessException $e){
			$isException = true;
		}
		parent::assertTrue(!$isException);

		// validate login history
		$loginHistory = null;
		TransactionManager::executeInTransaction(function () use (&$loginHistory,$testPerson1LoginDetails){
			$loginHistory = LoginHistory::find(array("logindetailsId"=>$testPerson1LoginDetails->getAttribute('id'),"type"=>LoginHistory::LH_LOGOUT));
		},array(),true);
		parent::assertCount(1, $loginHistory);
	
	}
	
	function testAuthenticate(){
		/**
		 * data
		 */
		$loginDetails = null;
		TransactionManager::executeInTransaction(function () use (&$loginDetails){
			$person1Obj = Person::create(array("accountName"=>"testPerson1","name"=>"Test Person 1"));
			$loginDetails = LoginDetails::create(array("personId"=>$person1Obj->getAttribute("id"),"loginName"=>"testPerson1","password"=>"123"));
		},array(),true);
	
		// authenticate with wrong password
		$result = LoginDetails::authenticate('testPerson1', '1234');
		parent::assertTrue(!$result);
		
		// authenticate with wrong loginName
		$result = LoginDetails::authenticate('testPerson12', '123');
		parent::assertTrue(!$result);
		
		// authenticate with wrong loginName and wrong password
		$result = LoginDetails::authenticate('testPerson12', '1234');
		parent::assertTrue(!$result);
		
		// authenticate with right loginName and right password
	
		//     with status ACTIVE
		self::manuallyChangeStatus($loginDetails, LoginDetails::STATUS_ACTIVE);
		$result = LoginDetails::authenticate('testPerson1', '123');
		parent::assertTrue($result);
		
		//     with status DISABLED
		self::manuallyChangeStatus($loginDetails, LoginDetails::STATUS_DISABLED);
		$result = LoginDetails::authenticate('testPerson1', '123');
		parent::assertTrue(!$result);
		
		//     with status PENDING VERIFICATION
		self::manuallyChangeStatus($loginDetails, LoginDetails::STATUS_PENDING_VERIFICATION);
		$result = LoginDetails::authenticate('testPerson1', '123');
		parent::assertTrue(!$result);
		
	
	}
	
}