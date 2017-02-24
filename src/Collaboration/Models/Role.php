<?php 
/**
 * User: Raaghu
 */

namespace PhpPlatform\Collaboration\Models;


use PhpPlatform\Persist\Exception\ObjectStateException;
use PhpPlatform\Persist\TransactionManager;
use PhpPlatform\Collaboration\Util\PersonSession;

/**
 * @tableName role
 * @prefix Role
 */
class Role extends Account {
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

    function __construct($id = null,$accountName = null){
        $this->id = $id;
        parent::__construct($accountName);
    }

    /**
     * @param array $data
     * @return Role
     * 
     * @access inherit
     */
    static function create($data){
        return parent::create($data);
    }

     /**
     * @param array $filters
     * @param array $sort
     * @param array $pagination
     * @param string $where
     *
     * @return Role[]
     * 
     * @access inherit
     */
    static function find($filters,$sort = null,$pagination = null, $where = null){
    	return parent::find($filters, $sort, $pagination, $where);
    }

    /**
     * @access inherit
     */
    function delete(){
        parent::delete();
    }

    function setAttribute($name,$value){
        $args = array();
        $args[$name] = $value;
        $this->setAttributes($args);
    }
    
    /**
     * @access inherit
     */
    function setAttributes($args){
        parent::setAttributes($args);
    }

    function getAttribute($name){
        $args = array();
        $args[] = $name;
        $attrValues = $this->getAttributes($args);
        return $attrValues[$name];
    }

    function getAttributes($args){
        $attributes =  parent::getAttributes($args);
        return $attributes;
    }
    
    // connected people manupulations //
    
    /**
     * @throws Exception
     * @return Person[]
     */
    function getPeople(){
    	if(!$this->isObjectInitialised) throw new ObjectStateException("Object Not initialised");
    	try{
    		TransactionManager::startTransaction(null,true);
    		$personRoleObjs = PersonRole::find(array("roleId"=>$this->id));
    		$personIds = array();
    		foreach ($personRoleObjs as $personRoleObj){
    			$personIds[] = $personRoleObj->getAttribute("personId");
    		}
    		TransactionManager::commitTransaction();
    	}catch (\Exception $e){
    		TransactionManager::abortTransaction();
    		throw $e;
    	}
    	return Person::find(array("id"=>array(self::OPERATOR_IN=>$personIds)));
    }
    
    protected static function canCreate($data = array()){
    	return PersonSession::hasRole('roleCreator');
    }
    
    protected static function canRead($args = array()){
    	$readExpr = parent::canRead($args);
    	// can read all the roles he belongs to
    	$belongingRoles = PersonSession::getRoles();
    	if(count($belongingRoles) > 0 ){
    		$accountClass = get_parent_class();
    		$accountNameExpr = "{".$accountClass."."."accountName"."}";
    		$dbs = TransactionManager::getConnection();
    	
    		$belongingRoles = array_map(function($belongingRole) use ($dbs){
    			return $dbs->escape_string($belongingRole);
    		}, $belongingRoles);
    	
    		$belongingOrgsStr = "'".implode("','", $belongingRoles)."'";
    			 
    		$readExpr = "($readExpr) OR $accountNameExpr in ($belongingOrgsStr)";
    	}
    	return $readExpr;
    }
    
    protected function canEdit($args){
    	$canEdit = parent::canEdit($args);
    	if(!$canEdit && PersonSession::hasRole('roleEditor')){
    		$belongingOrgs = PersonSession::getRoles();
    		$canEdit = in_array($this->getAttribute('accountName'), $belongingOrgs);
    	}
    	return $canEdit;
    }
    
    protected function canDelete(){
    	$canDelete = parent::canDelete();
    	if(!$canDelete && PersonSession::hasRole('roleEraser')){
    		$belongingOrgs = PersonSession::getRoles();
    		$canDelete = in_array($this->getAttribute('accountName'), $belongingOrgs);
    	}
    	return $canDelete;
    }
    

}
?>
