<?php
/**
 * User: Raaghu
 */

namespace PhpPlatform\Collaboration\Models;


use PhpPlatform\Collaboration\Model;
use PhpPlatform\Collaboration\Session;
use PhpPlatform\Persist\TransactionManager;

/**
 * @tableName login_history
 * @prefix LoginHistory
 */
class LoginHistory extends Model {
    /**
     * @columnName ID
     * @type bigint
     * @primary
     * @autoIncrement
     * @get
     */
    private $id = null;

    /**
     * @columnName LOGINDETAILS_ID
     * @type bigint
     * @set
     * @get
     */
    private $logindetailsId = null;

    /**
     * @columnName TYPE
     * @type enum
     * @set
     * @get
     */
    private $type = null;

    /**
     * @columnName TIME
     * @type timestamp
     * @set
     * @get
     */
    private $time = null;

    /**
     * @columnName LOGIN_IP
     * @type varchar
     * @set
     * @get
     */
    private $loginIp = null;

    /**
     * @columnName SESSION_ID
     * @type varchar
     * @set
     * @get
     */
    private $sessionId = null;

    const LH_REGISTRATION = "REGISTRATION";
    const LH_ACTIVATED = "ACTIVATED";
    const LH_DISABLED = "DISABLED";
    const LH_LOGIN = "LOGIN";
    const LH_LOGOUT = "LOGOUT";
    const LH_PASSWORD_RESET_REQUEST = 'PASSWORD_RESET_REQUEST';
    const LH_PASSWORD_RESET_REQUEST_VALIDATED = 'PASSWORD_RESET_REQUEST_VALIDATED';
    const LH_PASSWORD_RESET = 'PASSWORD_RESET';

    function __construct($id = null){
    	$this->id = $id;
        parent::__construct();
    }

    /**
     * @param array $data
     * 
     * @return LoginHistory
     *
     * @access ("person|systemAdmin")
     */
    static function create($data){
    	$data["loginIp"] = self::getUserIP();
    	if(!array_key_exists("sessionId", $data)){
    		$data["sessionId"] = Session::getInstance()->getId();
    	}
    	if(!array_key_exists("time", $data)){
    		TransactionManager::executeInTransaction(function() use (&$data){
    			$connection = TransactionManager::getConnection();
    			$data["time"] = $connection->formatDate(null,true);
    		});
    	}
    	return parent::create($data);
    }

     /**
     * @param array $filters
     * @param array $sort
     * @param array $pagination
     * @param string $where
     *
     * @return LoginHistory[]
     * 
     * @access ("person|systemAdmin")
     */
    static function find($filters,$sort = null,$pagination = null, $where = null){
    	return parent::find($filters, $sort, $pagination, $where);
    }

    function getAttribute($name){
    	return parent::getAttribute($name);
    }

    function getAttributes($args){
        return parent::getAttributes($args);
    }
    
    // Function to get the user IP address
    private static function getUserIP() {
	    $ipaddress = '';
	    if (isset($_SERVER['HTTP_CLIENT_IP']))
	        $ipaddress = $_SERVER['HTTP_CLIENT_IP'];
	    else if(isset($_SERVER['HTTP_X_FORWARDED_FOR']))
	        $ipaddress = $_SERVER['HTTP_X_FORWARDED_FOR'];
	    else if(isset($_SERVER['HTTP_X_FORWARDED']))
	        $ipaddress = $_SERVER['HTTP_X_FORWARDED'];
	    else if(isset($_SERVER['HTTP_X_CLUSTER_CLIENT_IP']))
	        $ipaddress = $_SERVER['HTTP_X_CLUSTER_CLIENT_IP'];
	    else if(isset($_SERVER['HTTP_FORWARDED_FOR']))
	        $ipaddress = $_SERVER['HTTP_FORWARDED_FOR'];
	    else if(isset($_SERVER['HTTP_FORWARDED']))
	        $ipaddress = $_SERVER['HTTP_FORWARDED'];
	    else if(isset($_SERVER['REMOTE_ADDR']))
	        $ipaddress = $_SERVER['REMOTE_ADDR'];
	    else
	        $ipaddress = 'UNKNOWN';
	    return $ipaddress;
	}

}
?>
