<?php 
/**
 * User: Raaghu
 */

namespace PhpPlatform\Collaboration\Models;

use PhpPlatform\Collaboration\Model;

/**
 * @tableName person_role
 * @prefix PersonRole
 */
class PersonRole extends Model {
    
    /**
     * @columnName PERSON_ID
     * @type bigint
     * @set
     * @get
     */
    private $personId = null;

    /**
     * @columnName ROLE_ID
     * @type bigint
     * @set
     * @get
     */
    private $roleId = null;
    

    function __construct($personId = null, $roleId = null){
        $this->personId = $personId;
        $this->roleId   = $roleId;
        parent::__construct();
    }

    /**
     * @param array $data
     * @return PersonRole
     * 
	 * @access ("person|systemAdmin")
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
	 * @return PersonRole[]
	 * 
	 * @access ("person|systemAdmin")
	 */
	static function find($filters,$sort = null,$pagination = null, $where = null){
		return parent::find($filters, $sort, $pagination, $where);
	}
	
	/**
	 * @access ("person|systemAdmin")
	 */
	function delete(){
		return parent::delete();
	}

}
?>
