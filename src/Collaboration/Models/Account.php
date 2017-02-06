<?php 
/**
 * User: Raaghu
 */

namespace PhpPlatform\Collaboration\Models;

use PhpPlatform\Collaboration\Model;
use PhpPlatform\Collaboration\Session;
use PhpPlatform\Persist\MySql;
use PhpPlatform\Persist\TransactionManager;

/**
 * @tableName account
 * @prefix Account
 */
abstract class Account extends Model {
    /**
     * @columnName ID
     * @type bigint
     * @primary
     * @autoIncrement
     * @get
     */
    private $id = null;

    /**
     * @columnName ACCOUNT_NAME
     * @type varchar
     * @set
     * @get
     */
    private $accountName = null;

    /**
     * @columnName CREATED
     * @type timestamp
     * @set
     * @get
     */
    private $created = null;

    /**
     * @columnName MODIFIED
     * @type timestamp
     * @set
     * @get
     */
    private $modified = null;

    /**
     * @columnName CREATED_BY_ID
     * @type bigint
     * @set
     * @get
     */
    private $createdById = null;
    
    /**
     * @columnName CONTACT_ID
     * @type bigint
     * @set
     * @get
     */
    private $contactId = null;
    
    function __construct($accountName = null){
        $this->accountName = $accountName;
        parent::__construct();
    }

    /**
     * @param array $data
     * @return Account
     * 
     * @access ("person|systemAdmin","function|canCreate")
     */
    static function create($data){
        // get the created by Id from session if present
        $sessionPersonId = null;
        $sessionPerson = Session::getInstance()->get(Session::SESSION_PERSON);
        if(isset($sessionPerson) && isset($sessionPerson["id"])){
        	$sessionPersonId = $sessionPerson["id"];
        }
        $data['createdById'] = $sessionPersonId;

        $currentDate = MySql::getMysqlDate(null,true);
        $data['created'] = $currentDate;
        $data['modified'] = $currentDate;
        
        try{
        	TransactionManager::startTransaction();
        	
        	// create contact
        	$contactId = null;
        	if(array_key_exists("contact", $data)){
        		// create contact in superUser's transaction
        		try{
        			TransactionManager::startTransaction(null,true);

        			$contact = Contact::create(array("info"=>$data["contact"]));
        			$contactId = $contact->getAttribute("id");
        			
        			TransactionManager::commitTransaction();
        		}catch (\Exception $e){
        			TransactionManager::abortTransaction();
        			throw $e;
        		}
        	}
        	$data["contactId"] = $contactId;
        	
        	// create account
        	$accountObj = parent::create($data);
        	
        	TransactionManager::commitTransaction();
        }catch (\Exception $e){
        	TransactionManager::abortTransaction();
        	throw $e;
        }

        return $accountObj;
    }
    
    /**
     * @param array $filters
     * @param array $sort
     * @param array $pagination
     * @param string $where
     *
     * @return Account[]
     * 
     * @access ("person|systemAdmin","function|canRead")
     */
    static function find($filters,$sort = null,$pagination = null, $where = null){
    	return parent::find($filters, $sort, $pagination, $where);
    }

    /**
     * @param $args
     * @throws \Exception
     *
     * @access ("person|systemAdmin","function|canEdit")
     */
    function setAttributes($args){
    	$args["modified"] = MySql::getMysqlDate(null,true);
    	
    	try{
    		TransactionManager::startTransaction();

    		if(isset($args["contactId"])){
    			unset($args["contactId"]);
    		}
    		
    		// modify contact
    		if(array_key_exists("contact", $args)){
    			try{
    				// modify contact in superUser's Transaction
    				TransactionManager::startTransaction(null,true);
    				
    				if(isset($this->contactId)){
    					//update existing contact
    					$contact = new Contact($this->contactId);
    					$contact->setAttribute("info",$args["contact"]);
    				}else{
    					//create new contact
    					$contact = Contact::create(array("info"=>$args["contact"]));
    					$args["contactId"] = $contact->getAttribute("id");
    				}
    				
    				TransactionManager::commitTransaction();
    			}catch (\Exception $e){
    				TransactionManager::abortTransaction();
    				throw $e;
    			}
    		}
    		 
    		// modify account
    		$accountObj = parent::setAttributes($args);
    		 
    		TransactionManager::commitTransaction();
    	}catch (\Exception $e){
    		TransactionManager::abortTransaction();
    		throw $e;
    	}
    	
    	return $accountObj;
    }
    
    /**
     * Deletes Account Object
     * 
     * @access ("person|systemAdmin","function|canDelete")
     */
    function delete(){
    	try{
    		TransactionManager::startTransaction();
    		
    		if(isset($this->contactId)){
    			// delete contact in superUser's transaction 
    			try{
    				TransactionManager::startTransaction(null,true);
    				$contact = new Contact($this->contactId);
    				$contact->delete();
    				TransactionManager::commitTransaction();
    			}catch (\Exception $e){
    				TransactionManager::abortTransaction();
    				throw $e;
    			}
    		}
    		$result = parent::delete();
    		TransactionManager::commitTransaction();
    	}catch (\Exception $e){
    		TransactionManager::abortTransaction();
    		throw $e;
    	}
    	return $result;
    }
    
    function getAttribute($name){
    	return parent::getAttribute($name);
    }
    
    
    function getAttributes($args){
    	
    	$attributes = parent::getAttributes($args);
    	
    	if($args == "*" || array_key_exists("contact", $args)){
    		if(isset($this->contactId)){
    			try{
    				TransactionManager::startTransaction(null,true);
    				
    				$contactObj = new Contact($this->contactId);
    				$attributes['contact'] = $contactObj->getAttribute("info");
    				
    				TransactionManager::commitTransaction();
    			}catch (\Exception $e){
    				TransactionManager::abortTransaction();
    				throw $e;
    			}
    		}else{
    			$attributes['contact'] = array();
    		}
    	}
    	if(isset($attributes["contactId"])){
    		unset($attributes["contactId"]);
    	}
    	return $attributes;
    }
    
    
    
    // access functions //
    
    protected static function canCreate($data){
    	return false;
    }

    protected static function canRead($args){
    	
        $sessionPerson = Session::getInstance()->get(Session::SESSION_PERSON);
        if($sessionPerson){
            $accountClass = get_class();
            $sessionPersonAccountId = $sessionPerson['accountId'];

            $accountIdExpr = "{".$accountClass."."."id"."}";
            $accountCreatedByIdExpr = "{".$accountClass."."."createdById"."}";
            
            $accessQuery = "$accountIdExpr = $sessionPersonAccountId OR $accountCreatedByIdExpr = $sessionPersonAccountId";
            return $accessQuery;
        }
        return false;
    }

    protected function canEdit($args){

    	$sessionPerson = Session::getInstance()->get(Session::SESSION_PERSON);
        if($sessionPerson){
            $sessionPersonAccountId = $sessionPerson['accountId'];
            return ($this->id == $sessionPersonAccountId) || ($this->createdById == $sessionPersonAccountId);
        }
        return false;
    }
    
    protected function canDelete(){
    	return $this->canEdit(array());
    }

}
?>
