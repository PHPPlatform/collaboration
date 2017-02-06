<?php

namespace PhpPlatform\Tests\Collaboration\Models;

use PhpPlatform\Tests\Collaboration\TestBase;
use PhpPlatform\Tests\Collaboration\SampleModels\SampleAccount;
use PhpPlatform\Errors\Exceptions\Persistence\DataNotFoundException;
use PhpPlatform\Errors\Exceptions\Persistence\NoAccessException;
use PhpPlatform\Tests\Collaboration\SampleModels\FreeSampleAccount;
use PhpPlatform\Persist\TransactionManager;

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
		$freeSampleAccount = null;
		$thisObj = $this;
		TransactionManager::executeInTransaction(function() use(&$sampleAccount,&$freeSampleAccount,$thisObj){
			$sampleAccount = new SampleAccount(1);
			$freeSampleAccount = new FreeSampleAccount($thisObj->getDatasetValue("account", "0","ACCOUNT_NAME"));
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
	}
	
	function testDelete(){
		$sampleAccount = null;
		$freeSampleAccount = null;
		TransactionManager::executeInTransaction(function() use(&$sampleAccount,&$freeSampleAccount){
			$sampleAccount = SampleAccount::create(array("name"=>"Sample Account 1","accountName"=>"sampleAccountName1"));
			$freeSampleAccount = FreeSampleAccount::create(array("accountName"=>"freeSampleAccountName1"));
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
	
	
			// update with systemAdmin session
			$this->setSystemAdminSession();
			$isException = false;
			try{
				$sampleAccount->delete();
			}catch (NoAccessException $e){
				$isException = true;
			}
			parent::assertTrue(!$isException);
			TransactionManager::executeInTransaction(function() use($thisObj){
				$sampleAccounts = SampleAccount::find(array("name"=>"Sample Account 1","accountName"=>"sampleAccountName1"));
				$thisObj->assertCount(0,$sampleAccounts);
			},array(),true);
			
	}
}