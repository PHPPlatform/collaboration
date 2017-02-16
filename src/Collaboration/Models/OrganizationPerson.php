<?php 
/**
 * User: Raaghu
 */

namespace PhpPlatform\Collaboration\Models;

use PhpPlatform\Collaboration\Model;

/**
 * @tableName organization_person
 * @prefix OrganizationPerson
 */
class OrganizationPerson extends Model {
    /**
     * @columnName ORGANIZATION_ID
     * @type bigint
     * @set
     * @get
     */
    private $organizationId = null;

    /**
     * @columnName PERSON_ID
     * @type bigint
     * @set
     * @get
     */
    private $personId = null;
    
    /**
     * @columnName TYPE
     * @type enum
     * @set
     * @get
     */
    private $type = null;
    
    const TYPE_OWNER = 'OWNER';
    const TYPE_ADMINISTRATOR = 'ADMINISTRATOR';
    const TYPE_MEMBER = 'MEMBER';

    function __construct($organizationId = null,$personId = null){
        $this->organizationId = $organizationId;
        $this->personId       = $personId;
        parent::__construct();
    }

    /**
     * @param array $data
     * @return OrganizationPerson
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
	 * @return OrganizationPerson[]
	 * 
     * @access ("person|systemAdmin")
	 */
	static function find($filters,$sort = null,$pagination = null, $where = null){
		return parent::find($filters, $sort, $pagination, $where);
	}
	
	function getAttribute($name){
		return parent::getAttribute($name);
	}
	
	/**
     * @access ("person|systemAdmin")
	 */
	function delete(){
		return parent::delete();
	}

}
?>
