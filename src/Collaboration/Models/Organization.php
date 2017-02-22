<?php 
/**
 * User: Raaghu
 */

namespace PhpPlatform\Collaboration\Models;

use PhpPlatform\Errors\Exceptions\Application\BadInputException;
use PhpPlatform\Persist\TransactionManager;
use PhpPlatform\Persist\Exception\ObjectStateException;
use PhpPlatform\Collaboration\Util\PersonSession;

/**
 * @tableName organization
 * @prefix Organization
 */
class Organization extends Account {
    /**
     * @columnName ID
     * @type bigint
     * @primary
     * @autoIncrement
     * @get
     */
    private $id = null;

    /**
     * @columnName ACCOUNT_ID
     * @type bigint
     * @reference
     * @get
     */
    private $accountId = null;

    /**
     * @columnName NAME
     * @type varchar
     * @set
     * @get
     */
    private $name = null;

    /**
     * @columnName DOI
     * @type date
     * @set
     * @get
     */
    private $doi = null;

    /**
     * @columnName TYPE
     * @type varchar
     * @set
     * @get
     */
    private $type = null;

    /**
     * @columnName PARENT_ID
     * @type bigint
     * @set
     * @get
     */
    private $parentId = null;


    function __construct($id = null, $accountName = null){
        $this->id = $id;
        parent::__construct($accountName);
    }

    /**
     * @param array $data
     * @return Organization
     * 
     * @access ("role|orgCreator",inherit)
     */
    static function create($data){
    	try{
    		TransactionManager::startTransaction();
    		if(isset($data["parentId"])){
    			throw new BadInputException("parentId is not accepted during creation , please use addChildren api");
    		}
    		$organization = parent::create($data);
    		
    		// add session person as owner of this organization
    		$sessionPersonId = PersonSession::getPersonId();
    		if($sessionPersonId){
    			TransactionManager::executeInTransaction(function() use($organization,$sessionPersonId){
    				OrganizationPerson::create(array(
    						"organizationId"=>$organization->getAttribute('id'),
    						"personId"=>$sessionPersonId,
    						"type"=>OrganizationPerson::TYPE_OWNER
    				));
    			},array(),true);
    		}
    		
    		TransactionManager::commitTransaction();
    	}catch (\Exception $e){
    		TransactionManager::abortTransaction();
    		throw $e;
    	}
        return $organization;
    }

    /**
     * @param array $filters
     * @param array $sort
     * @param array $pagination
     * @param string $where
     *
     * @return Organization[]
     * 
     * @access inherit
     */
    static function find($filters,$sort = null,$pagination = null, $where = null){
    	return parent::find($filters, $sort, $pagination, $where);
    }

    function setAttribute($name,$value){
        return parent::setAttribute($name, $value);
    }

    /**
     * @access inherit
     */
    function setAttributes($args){
    	if(isset($args["parentId"])){
    		throw new BadInputException("Can not set parentId , please use addChildren/removeChildren api");
    	}
        return parent::setAttributes($args);
    }

    function getAttribute($name){
    	return parent::getAttribute($name);
    }

    function getAttributes($args){
        $attributes = parent::getAttributes($args);
        if(isset($attributes["parentId"])){
        	unset($attributes["parentId"]);
        }
        return $attributes;
    }
    
    /**
     * @access inherit
     */
    function delete(){
    	return parent::delete();
    }
    
    // child organization manupulations //
    
    /**
     * @return Organization[]
     */
    function getChildren(){
    	return static::find(array("parentId"=>$this->id));
    }
    
    /**
     * @return Organization|NULL
     */
    function getParent(){
    	if($this->parentId != null){
    		return new Organization($this->parentId);
    	}else{
    		return null;
    	}
    }
    
    /**
     * @return Organization[]
     */
    function getAllParents(){
    	$parent = $this->getParent();
    	if($parent == null){
    		return array();
    	}
    	$allParents = $parent->getAllParents();
    	array_push($allParents, $parent);
    	return $allParents;
    }
    

    /**
     * @param Organization $parentObj
     * 
     * @access ("person|systemAdmin","function|canEdit")
     */
    private function addToParent($parentObj){
        $parentId = $parentObj->getAttribute("id");
        if($this->parentId != null){
        	throw new BadInputException("This organization is already a child of another organization with id ".$this->parentId);
        }
        if($this->id == $parentObj->getAttribute('id')){
        	throw new BadInputException('An Organization can not be a child its own');
        }
        
        if($this->isChild($parentObj)){
        	throw new BadInputException("Circular child-parent connection");
        }
        
        parent::setAttributes(array("parentId"=>$parentId));
    }
    
    private function isChild($child){
    	$allParentsOfChild = null;
    	TransactionManager::executeInTransaction(function() use(&$allParentsOfChild,$child) {
    		$allParentsOfChild = $child->getAllParents();
    	},array(),true);
    	foreach ($allParentsOfChild as $parent){
    		if($parent->getAttribute('id') == $this->id){
    			return true;
    		}
    	}
    	return false;
    }

    /**
     * @param Organization $parentObj
     * 
     * @access ("person|systemAdmin","function|canEdit")
     */
    private function removeFromParent($parentObj){
        if($this->parentId != $parentObj->getAttribute("id")){
            $parentName = $parentObj->getAttribute("name");
            $thisName   = $this->name;
            throw new BadInputException("Organization $thisName is not a child of $parentName");
        }
        parent::setAttributes(array("parentId"=>array(self::OPERATOR_EQUAL=>null)));
    }


    /**
     * @param Organization[] $children
     * 
     * @access ("person|systemAdmin","function|canEdit")
     */
    function addChildren($children){
        if(!is_array($children)) throw new BadInputException("1st parameter is not an array");
        self::checkAccess($this, 'UpdateAccess', 'No access to add children'); // force the access check
        try{
            TransactionManager::startTransaction();
            foreach($children as $child){
                $child->addToParent($this);
            }
            TransactionManager::commitTransaction();
        }catch (\Exception $e){
            TransactionManager::abortTransaction();
            throw $e;
        }
    }

    /**
     * @param Organization[] $children
     * 
     * @access ("person|systemAdmin","function|canEdit")
     */
    function removeChildren($children){
        if(!is_array($children)) throw new BadInputException("1st parameter is not an array");
        self::checkAccess($this, 'UpdateAccess', 'No access to remove children'); // force the access check
        try{
            TransactionManager::startTransaction();
            foreach($children as $child){
                $child->removeFromParent($this);
            }
            TransactionManager::commitTransaction();
        }catch (\Exception $e){
            TransactionManager::abortTransaction();
            throw $e;
        }
    }
    
    
    // connected people manupulations //
    
    /**
     * @throws Exception
     * @return Person[][]
     */
    function getPeople($type = null){
    	if(!$this->isObjectInitialised) throw new ObjectStateException("Object Not initialised");
    	$allowedTypes = array(OrganizationPerson::TYPE_OWNER,OrganizationPerson::TYPE_ADMINISTRATOR,OrganizationPerson::TYPE_MEMBER);
    	if($type == null){
    		$type = $allowedTypes;
    	}
    	if(is_string($type)){
    		$type = array($type);
    	}
    	if(!is_array($type)){
    		throw new BadInputException("1st parameter is not an array");
    	}
    	if(count(array_diff($type, $allowedTypes)) > 0){
    		throw new BadInputException("1st parameter contains invalid type");
    	}
    	$personIdsPerType = array();
    	try{
    		TransactionManager::startTransaction(null,true);
    		$organizationPersonObjs = OrganizationPerson::find(array("organizationId"=>$this->id,"type"=>array(self::OPERATOR_IN=>$type)));
    		$personIds = array();
    		foreach ($organizationPersonObjs as $organizationPersonObj){
    			$personId= $organizationPersonObj->getAttribute("personId");
    			$personIds[] = $personId;
    			$type = $organizationPersonObj->getAttribute('type');
    			if(!array_key_exists($type, $personIdsPerType)){
    				$personIdsPerType[$type] = array();
    			}
    			$personIdsPerType[$type][] = $personId;
    		}
    		TransactionManager::commitTransaction();
    	}catch (\Exception $e){
    		TransactionManager::abortTransaction();
    		throw $e;
    	}
    	$people = Person::find(array("id"=>array(self::OPERATOR_IN=>$personIds)));
    	$peopleAssociatedFromId = array();
    	foreach ($people as $person){
    		$id = $person->getAttribute('id');
    		$peopleAssociatedFromId[$id] = $person;
    	}
    	
    	$peopleAssociatedFromType = array();
    	foreach ($personIdsPerType as $type=>$personIds){
    		if(!array_key_exists($type, $peopleAssociatedFromType)){
    			$peopleAssociatedFromType[$type] = array();
    		}
    		foreach ($personIds as $personId){
    			$peopleAssociatedFromType[$type][] = $peopleAssociatedFromId[$personId];
    		}
    	}
    	
    	return $peopleAssociatedFromType;
    }

    /**
     * @param Person[] $people
     * 
     * @access ("person|systemAdmin","function|canEdit")
     */
    function addPeople($people, $type = OrganizationPerson::TYPE_MEMBER){
    	if(!$this->isObjectInitialised) throw new ObjectStateException("Object Not initialised");
        if(!is_array($people)) throw new BadInputException("$people is not array");
        self::checkAccess($this, 'UpdateAccess', 'No access to add people'); // force the access check
        try{
            TransactionManager::startTransaction(null,true);
            foreach($people as $person){
                OrganizationPerson::create(array(
                		"organizationId"=>$this->id,
                		"personId"=>$person->getAttribute("id"),
                		"type"=>$type
                ));
            }
            TransactionManager::commitTransaction();
        }catch (\Exception $e){
            TransactionManager::abortTransaction();
            throw $e;
        }
    }

    /**
     * @param Person[] $people
     * 
     * @access ("person|systemAdmin","function|canEdit")
     */
    function removePeople($people){
    	if(!$this->isObjectInitialised) throw new ObjectStateException("Object Not initialised");
        if(!is_array($people)) throw new BadInputException("$people is not array");
        self::checkAccess($this, 'UpdateAccess', 'No access to remove people'); // force the access check
        try{
            TransactionManager::startTransaction(null,true);
            foreach($people as $person){
                $organizationPerson = new OrganizationPerson($this->id,$person->getAttribute("id"));
                $organizationPerson->delete();
            }
            TransactionManager::commitTransaction();
        }catch (\Exception $e){
            TransactionManager::abortTransaction();
            throw $e;
        }
    }
    
    protected static function canRead($args = array()){
    	$readExpr = parent::canRead($args);
    	// can read all the org he belongs to
    	$belongingOrgs = PersonSession::getOrganizations();
    	if(count($belongingOrgs) > 0 ){
    		$accountClass = get_parent_class();
    		$accountNameExpr = "{".$accountClass."."."accountName"."}";
    		$dbs = TransactionManager::getConnection();
    		
    		$belongingOrgs = array_map(function($belongingOrg) use ($dbs){
    			return $dbs->escape_string($belongingOrg);
    		}, $belongingOrgs);
    		
    		$belongingOrgsStr = "'".implode("','", $belongingOrgs)."'";
    		 
    		$readExpr = "($readExpr) OR $accountNameExpr in ($belongingOrgsStr)";
    	}
    	return $readExpr;
    }
    
    protected function canEdit($args){
    	$canEdit = parent::canEdit($args);
    	if(!$canEdit){
    		
    		// owner or administrator can edit organization 
    		$organizationPerson = OrganizationPerson::find(array(
    				"organizationId"=>$this->id,
    				"personId"=>PersonSession::getPersonId(),
    				"type"=>array(
    						self::OPERATOR_IN=>array(
    								OrganizationPerson::TYPE_OWNER,
    								OrganizationPerson::TYPE_ADMINISTRATOR
    						)
    				)
    		));
    		if(count($organizationPerson) == 1){
    			$canEdit = true;
    		}
    		
    	}
    	return $canEdit;
    }
    
    protected function canDelete(){
    	$canDelete = parent::canDelete();
    	if(!$canDelete){
    		// only owner can delete organization
    		$organizationPerson = OrganizationPerson::find(array(
    				"organizationId"=>$this->id,
    				"personId"=>PersonSession::getPersonId(),
    				"type"=>OrganizationPerson::TYPE_OWNER
    		));
    		if(count($organizationPerson) == 1){
    			$canDelete = true;
    		}
    	}
    	return $canDelete;
    }


}
?>
