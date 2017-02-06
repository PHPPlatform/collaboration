<?php

namespace PhpPlatform\Tests\Collaboration;

use PhpPlatform\Persist\RelationalMappingCache;
use PhpPlatform\Collaboration\Session;
use PhpPlatform\Collaboration\Models\Person;
use PhpPlatform\Tests\PersistUnit\ModelTest as PersistUnitTestCase;
use PhpPlatform\Persist\Reflection;


abstract class TestBase extends PersistUnitTestCase{
	
    
    protected static function getSchemaFiles(){
    	$schemaFiles = parent::getSchemaFiles();
    	array_push($schemaFiles, dirname(__FILE__).'/../../database/install/schema/001.sql');
    	array_push($schemaFiles, dirname(__FILE__).'/collaborationtest_ddl.sql');
    	return $schemaFiles;
    }
    
    protected static function getDataFiles(){
    	$dataFiles = parent::getDataFiles();
    	array_push($dataFiles, dirname(__FILE__).'/../../database/install/data/001.sql');
    	return $dataFiles;
    }
    
    protected static function getDataSetFiles(){
    	$dataSetFiles = parent::getDataSetFiles();
    	array_push($dataSetFiles, dirname(__FILE__).'/collaborationtest_seed.xml');
    	return $dataSetFiles;
    }
    
    protected static function getCaches(){
    	$caches = parent::getCaches();
    	array_push($caches, RelationalMappingCache::getInstance());
    	return $caches;
    }
    
    function setUp(){
    	parent::setUp();
	    // clear session
    	$thisInstance = Session::getInstance();
    	Reflection::invokeArgs(get_parent_class($thisInstance), 'reset', $thisInstance);
    }
    
    public function setSystemAdminSession(){
    	Person::login('systemAdmin', 'systemAdmin');
    }
    
    
}
