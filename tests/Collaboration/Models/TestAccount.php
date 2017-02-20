<?php

namespace PhpPlatform\Tests\Collaboration\Models;

use PhpPlatform\Tests\Collaboration\TestBase;
use PhpPlatform\Tests\Collaboration\SampleModels\SampleAccount;
use PhpPlatform\Errors\Exceptions\Persistence\DataNotFoundException;
use PhpPlatform\Errors\Exceptions\Persistence\NoAccessException;
use PhpPlatform\Tests\Collaboration\SampleModels\FreeSampleAccount;
use PhpPlatform\Persist\TransactionManager;
use PhpPlatform\Collaboration\Models\Contact;
use PhpPlatform\Errors\Exceptions\Application\BadInputException;

class TestAccount extends TestBase {
	
	function testConstructor(){
		// construct without session
		$isException = false;
		try{
			new SampleAccount(1);
		}catch (NoAccessException $e){
			$isException = true;
		}
		parent::assertTrue($isException);
		
		
		// construct with systemAdmin session
		$this->setSystemAdminSession();
		$isException = false;
		try{
			new SampleAccount(1);
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
			SampleAccount::create(array("accountName"=>"sampleAccount1","name"=>"Sample Account1"));
		}catch (NoAccessException $e){
			$isException = true;
		}
		parent::assertTrue($isException);
		
		// create access overrided account , without session
		$isException = false;
		try{
			$freeSampleAccount1 = FreeSampleAccount::create(array("accountName"=>"freeSampleAccount1"));
		}catch (NoAccessException $e){
			$isException = true;
		}
		parent::assertTrue(!$isException);
		parent::assertEquals("freeSampleAccount1", $freeSampleAccount1->getAttribute("accountName"));
		
		
		// create with systemAdmin session
		$this->setSystemAdminSession();
		$isException = false;
		try{
			$sampleAccount1 = SampleAccount::create(array("accountName"=>"sampleAccount1","name"=>"Sample Account1"));
		}catch (NoAccessException $e){
			$isException = true;
		}
		parent::assertTrue(!$isException);
		parent::assertEquals("sampleAccount1", $sampleAccount1->getAttribute("accountName"));
		parent::assertEquals("Sample Account1", $sampleAccount1->getAttribute("name"));
		
		// create with contact information
		$isException = false;
		try{
			$sampleAccount2 = SampleAccount::create(array("accountName"=>"sampleAccount2","name"=>"Sample Account2","contact"=>array("email"=>"sample2@example.com","phone"=>"1234567890")));
		}catch (NoAccessException $e){
			$isException = true;
		}
		parent::assertTrue(!$isException);
		parent::assertEquals("sampleAccount2", $sampleAccount2->getAttribute("accountName"));
		parent::assertEquals("Sample Account2", $sampleAccount2->getAttribute("name"));
		parent::assertEquals(array("email"=>"sample2@example.com","phone"=>"1234567890"), $sampleAccount2->getAttribute("contact"));
		
	}
	
	function testFind(){
		// find without session
		$isException = false;
		try{
			SampleAccount::find(array("name"=>$this->getDatasetValue("sample_account", "0","NAME")));
		}catch (NoAccessException $e){
			$isException = true;
		}
		parent::assertTrue($isException);
	
		// find access overrided account , without session
	    $isException = false;
		try{
			$freeSampleAccounts = FreeSampleAccount::find(array("accountName"=>$this->getDatasetValue("account", "0","ACCOUNT_NAME")));
		}catch (NoAccessException $e){
			$isException = true;
		}
		parent::assertTrue(!$isException);
		parent::assertCount(1,$freeSampleAccounts);
		parent::assertEquals($this->getDatasetValue("account", "0","ACCOUNT_NAME"),$freeSampleAccounts[0]->getAttribute("accountName"));
		
		// find with systemAdmin session
		$this->setSystemAdminSession();
		$isException = false;
		try{
			$sampleAccounts = SampleAccount::find(array("name"=>$this->getDatasetValue("sample_account", "0","NAME")));
		}catch (NoAccessException $e){
			$isException = true;
		}
		parent::assertTrue(!$isException);
		parent::assertCount(1,$sampleAccounts);
		parent::assertEquals($this->getDatasetValue("account", "0","ACCOUNT_NAME"), $sampleAccounts[0]->getAttribute("accountName"));
		parent::assertEquals($this->getDatasetValue("sample_account", "0","NAME"), $sampleAccounts[0]->getAttribute("name"));
	}
	
	function testUpdate(){
		$sampleAccount = null;
		$sampleAccountWithContact = null;
		$freeSampleAccount = null;
		$thisObj = $this;
		TransactionManager::executeInTransaction(function() use(&$sampleAccount,&$freeSampleAccount,&$sampleAccountWithContact,$thisObj){
			$sampleAccount = new SampleAccount(1);
			$freeSampleAccount = new FreeSampleAccount($thisObj->getDatasetValue("account", "0","ACCOUNT_NAME"));
			$sampleAccountWithContact = SampleAccount::create(array("accountName"=>"sampleAccount2","name"=>"Sample Account2","contact"=>array("email"=>"sample2@example.com","phone"=>"1234567890")));
		},array(),true);
		
		// update without session
		$isException = false;
		try{
			$sampleAccount->setAttribute("name","new Sample Account");
		}catch (NoAccessException $e){
			$isException = true;
		}
		parent::assertTrue($isException);
		parent::assertEquals($this->getDatasetValue("sample_account", "0","NAME"), $sampleAccount->getAttribute("name"));
		
		
		// update access overrided account , without session
		$isException = false;
		try{
			$freeSampleAccount->setAttribute("accountName","NewSampleAccountName");
		}catch (NoAccessException $e){
			$isException = true;
		}
		parent::assertTrue(!$isException);
		parent::assertEquals("NewSampleAccountName",$freeSampleAccount->getAttribute("accountName"));
		
		
		// update with systemAdmin session
		$this->setSystemAdminSession();
		$isException = false;
		try{
			$sampleAccount->setAttribute("name","New SampleAccount Name");
			$sampleAccount->setAttribute("accountName","New SampleAccount Account Name");
		}catch (NoAccessException $e){
			$isException = true;
		}
		parent::assertTrue(!$isException);
		parent::assertEquals("New SampleAccount Name",$sampleAccount->getAttribute("name"));
		parent::assertEquals("New SampleAccount Account Name",$sampleAccount->getAttribute("accountName"));
		
		// try updating contactId
		$isException = false;
		try{
			$sampleAccount->setAttribute("contactId",2);
		}catch (BadInputException $e){
			$isException = true;
		}
		parent::assertTrue($isException);
		
		// update contact for account having no prior contact
		$isException = false;
		try{
			$sampleAccount->setAttribute("contact",array("email"=>"sample@abcd.com"));
		}catch (NoAccessException $e){
			$isException = true;
		}
		parent::assertTrue(!$isException);
		parent::assertEquals(array("email"=>"sample@abcd.com"),$sampleAccount->getAttribute("contact"));
		
		// update contact for account having prior contact
		$isException = false;
		try{
			$sampleAccountWithContact->setAttribute("contact",array("email"=>"sample@abcd.com"));
		}catch (NoAccessException $e){
			$isException = true;
		}
		parent::assertTrue(!$isException);
		parent::assertEquals(array("email"=>"sample@abcd.com"),$sampleAccountWithContact->getAttribute("contact"));
		
	}
	
	function testGetAttributes(){
		// data
		$sampleAccount = null;
		TransactionManager::executeInTransaction(function() use(&$sampleAccount){
			$sampleAccount = SampleAccount::create(array("accountName"=>"sampleAccount1","name"=>"Sample Account1","contact"=>array("email"=>"sample1@example.com","phone"=>"1234567890")));
		},array(),true);
		
		parent::assertEquals("sampleAccount1", $sampleAccount->getAttribute("accountName"));
		parent::assertEquals("Sample Account1", $sampleAccount->getAttribute("name"));
		parent::assertEquals(array("email"=>"sample1@example.com","phone"=>"1234567890"), $sampleAccount->getAttribute("contact"));
		parent::assertEquals(null, $sampleAccount->getAttribute('contactId'));
		
		parent::assertArraySubset(array(
				"accountName"=>"sampleAccount1",
				"name"=>"Sample Account1",
				"contact"=>array("email"=>"sample1@example.com","phone"=>"1234567890")
		), $sampleAccount->getAttributes("*"));
		
	}
	
	
	function testDelete(){
		$sampleAccount = null;
		$sampleAccountWithContact = null;
		$freeSampleAccount = null;
		TransactionManager::executeInTransaction(function() use(&$sampleAccount,&$freeSampleAccount,&$sampleAccountWithContact){
			$sampleAccount = SampleAccount::create(array("name"=>"Sample Account 1","accountName"=>"sampleAccountName1"));
			$freeSampleAccount = FreeSampleAccount::create(array("accountName"=>"freeSampleAccountName1"));
			$sampleAccountWithContact = SampleAccount::create(array("accountName"=>"sampleAccount2","name"=>"Sample Account2","contact"=>array("email"=>"sample2@example.com","phone"=>"1234567890")));
		},array(),true);
	
			// delete without session
			$isException = false;
			try{
				$sampleAccount->delete();
			}catch (NoAccessException $e){
				$isException = true;
			}
			parent::assertTrue($isException);
			parent::assertEquals("Sample Account 1", $sampleAccount->getAttribute("name"));
	
	
			// delete access overrided account , without session
			$isException = false;
			try{
				$freeSampleAccount->delete();
			}catch (NoAccessException $e){
				$isException = true;
			}
			parent::assertTrue(!$isException);
			$thisObj = $this;
			TransactionManager::executeInTransaction(function() use($thisObj){
				$sampleAccounts = FreeSampleAccount::find(array("accountName"=>"freeSampleAccountName1"));
				$thisObj->assertCount(0,$sampleAccounts);
			},array(),true);
	
	
			// delete with systemAdmin session
			$this->setSystemAdminSession();
			$isException = false;
			try{
				$sampleAccount->delete();
			}catch (NoAccessException $e){
				$isException = true;
			}
			parent::assertTrue(!$isException);
			$sampleAccounts = null;
			TransactionManager::executeInTransaction(function() use(&$sampleAccounts){
				$sampleAccounts = SampleAccount::find(array("name"=>"Sample Account 1","accountName"=>"sampleAccountName1"));
			},array(),true);
			parent::assertCount(0,$sampleAccounts);
			
			
			// delete account with contact
			$isException = false;
			try{
				$sampleAccountWithContact->delete();
			}catch (NoAccessException $e){
				$isException = true;
			}
			parent::assertTrue(!$isException);
			$sampleAccounts = null;
			$contacts = null;
			TransactionManager::executeInTransaction(function() use(&$sampleAccounts,&$contacts){
				$sampleAccounts = SampleAccount::find(array("name"=>"Sample Account 1","accountName"=>"sampleAccountName1"));
				$contacts = Contact::find(array());
			},array(),true);
			parent::assertCount(0,$sampleAccounts);
			parent::assertCount(0, $contacts);
			
	}
}