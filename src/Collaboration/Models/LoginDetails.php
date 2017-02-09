<?php 
/**
 * User: Raaghu
 */

namespace PhpPlatform\Collaboration\Models;

use PhpPlatform\Collaboration\Model;
use PhpPlatform\Persist\TransactionManager;
use PhpPlatform\Errors\Exceptions\Application\BadInputException;
use PhpPlatform\Config\Settings;
use PhpPlatform\Persist\MySql;
use PhpPlatform\Collaboration\Session;
use PhpPlatform\Collaboration\Util\PersonSession;
use PhpPlatform\Errors\Exceptions\Application\NoAccessException;
use PhpPlatform\Errors\Exceptions\Persistence\DataNotFoundException;

/**
 * @tableName login_details
 * @prefix LoginDetails
 */
class LoginDetails extends Model {
    /**
     * @columnName ID
     * @type bigint
     * @primary
     * @autoIncrement
     * @get
     */
    private $id = null;

    /**
     * @columnName PERSON_ID
     * @type bigint
     * @get
     */
    private $personId = null;
    
    /**
     * @columnName LOGIN_NAME
     * @type varchar
     * @get
     */
    private $loginName = null;

    /**
     * @columnName PASSWORD
     * @type varchar
     * @set
     */
    private $password = null;
    
    /**
     * @columnName STATUS
     * @type enum
     * @set
     * @get
     */
    private $status = null;
    
    const STATUS_ACTIVE = 'ACTIVE';
    const STATUS_PENDING_VERIFICATION = 'PENDING_VERIFICATION';
    const STATUS_DISABLED = 'DISABLED';


    function __construct($loginName = null){
        $this->loginName = $loginName;
        if(isset($loginName)){
        	$this->status = self::STATUS_ACTIVE;
        }
        parent::__construct();
    }
    
    private function hashPassword($password){
    	return $this->id.$this->personId.md5($this->id.'|'.$this->personId.'|'.$password);
    }
    
    /**
     * @param array $data
     * @throws Exception
     * @return LoginDetails
     * 
     * @access ("person|systemAdmin","function|canCreate")
     */
    static function create($data){
    	try{
    		TransactionManager::startTransaction();
    		
    		$data["status"] = self::STATUS_PENDING_VERIFICATION;
    		
    		/**
    		 * @var LoginDetails $loginDetails
    		 */
    		$loginDetails = parent::create($data);
    		$loginDetails->changePassword($data["password"]);
    		
    		$createToken = $loginDetails->personId.md5($loginDetails->personId.rand(1,1000).MySql::getMysqlDate(null,true).$loginDetails->id);
    		
    		LoginHistory::create(array(
    				"logindetailsId"=>$loginDetails->id,
    				"type"=>LoginHistory::LH_REGISTRATION,
    				"sessionId"=>$createToken
    		));
    		
    		TransactionManager::commitTransaction();
    	}catch (\Exception $e){
    		TransactionManager::abortTransaction();
    		throw $e;
    	}
    	return $loginDetails;
    }

    /**
     * @param array $filters
     * @param array $sort
     * @param array $pagination
     * @param string $where
     *
     * @return LoginDetails[]
     * 
     * @access ("person|systemAdmin","function|canRead")
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
    
    /**
     * Deletes Account Object
     *
     * @access ("person|systemAdmin")
     */
    function delete(){
    	parent::delete();
    }
    
    // status change functions //
    
    static function verify($loginName,$createToken){
        $loginDetails = self::find(array("loginName"=>$loginName,"status"=>array(self::OPERATOR_IN=>array(self::STATUS_ACTIVE,self::STATUS_PENDING_VERIFICATION))));
        if(count($loginDetails) != 1){
        	throw new \PhpPlatform\Errors\Exceptions\Persistence\NoAccessException("User don't have access to verify $loginName");
        }
        $loginDetail = $loginDetails[0];
        if($loginDetail->status == self::STATUS_ACTIVE){
        	throw new BadInputException("Already Verified");
        }
        
        try{
        	// self::find will check for read access on logindetails,
        	//so loginhistory is accessed through superUser's transaction
        	TransactionManager::startTransaction(null,true);
        	
        	$loginHistory = LoginHistory::find(array("logindetailsId"=>$loginDetail->id,"sessionId"=>$createToken,"type"=>LoginHistory::LH_REGISTRATION));
        	
        	if(count($loginHistory) == 0){
        		throw new BadInputException("Invalid loginName or createToken");
        	}
        	
        	// verified , so activate 
        	$loginDetail->activate();
        	
        	TransactionManager::commitTransaction();
        }catch (\Exception $e){
        	TransactionManager::abortTransaction();
        	throw $e;
        }
        
    }
    
    /**
     * @access ("person|systemAdmin","function|canEdit")
     */
    function activate(){
    	try{
    		TransactionManager::startTransaction();
    		
    		if(!TransactionManager::isSuperUser() && !PersonSession::hasPerson('systemAdmin') && $this->status == self::STATUS_PENDING_VERIFICATION){
    			throw new NoAccessException("Verification Pending");
    		}
    		
    		if($this->status == self::STATUS_ACTIVE){
    			throw new BadInputException("Already Active");
    		}

    		parent::setAttributes(array("status"=>self::STATUS_ACTIVE));
    		// add login history in superUser's Transaction
    		try{
    			TransactionManager::startTransaction(null,true);
    		
    			LoginHistory::create(array(
    					"logindetailsId" => $this->id,
    					"type"=>LoginHistory::LH_ACTIVATED
    			));
    		
    			TransactionManager::commitTransaction();
    		}catch (\Exception $e){
    			TransactionManager::abortTransaction();
    			throw $e;
    		}
    		
    		TransactionManager::commitTransaction();
    	}catch (\Exception $e){
    		TransactionManager::abortTransaction();
    		throw $e;
    	}
    }
    
    /**
     * @access ("person|systemAdmin","function|canEdit")
     */
    function disable(){
    	try{
    		TransactionManager::startTransaction();
    	
    		if(!TransactionManager::isSuperUser() && !PersonSession::hasPerson('systemAdmin') && $this->status == self::STATUS_PENDING_VERIFICATION){
    		    throw new NoAccessException("Verification Pending");
    		}
    	
    		if($this->status == self::STATUS_DISABLED){
    			throw new BadInputException("Already Disabled");
    		}
    		
    		parent::setAttributes(array("status"=>self::STATUS_DISABLED));
    		// add login history in superUser's Transaction
    		try{
    			TransactionManager::startTransaction(null,true);
    	
    			LoginHistory::create(array(
    					"logindetailsId" => $this->id,
    					"type"=>LoginHistory::LH_DISABLED
    			));
    	
    			TransactionManager::commitTransaction();
    		}catch (\Exception $e){
    			TransactionManager::abortTransaction();
    			throw $e;
    		}
    		TransactionManager::commitTransaction();
    	}catch (\Exception $e){
    		TransactionManager::abortTransaction();
    		throw $e;
    	}
    }
    
    // change login name function 
    
    /**
     * @param unknown $newLoginName
     * @param unknown $password
     * 
     * @return LoginDetails
     * 
     * @access ("person|systemAdmin","function|canEdit")
     */
    function changeLoginName($newLoginName,$password){
    	if(!self::UpdateAccess()){ throw new NoAccessException('No access to change login name');} // force the access check
    	if($this->hashPassword($password) != $this->password){
    		throw new NoAccessException("Invalid Password");
    	}
    	try{
    		TransactionManager::startTransaction(null,true);
    		
    		// disable this object
    		$this->disable();
    		
    		// create new object
    		$newLoginDetails = LoginDetails::create(array(
    				"personId"=>$this->personId,
    				"loginName"=>$newLoginName,
    				"password"=>$password
    		));
    		
    		// uninitialize this object
    		$this->isObjectInitialised = false;
    		
    		TransactionManager::commitTransaction();
    	}catch (\Exception $e){
    		TransactionManager::abortTransaction();
    		throw $e;
    	}
    	return $newLoginDetails;
    }
    
    
    // authentication functions //
    
    function login($password){
    	
    	$encryptedPassword = $this->hashPassword($password);
    	
    	// to compensate timing attack
    	time_nanosleep(0, rand(0,1000));
    	if($this->password !== $encryptedPassword ){
    		throw new NoAccessException('Invalid loginName or password');
    	}
    	
    	$loggedInTime = MySql::getMysqlDate(null,true);
    	
    	if(Settings::getSettings(self::$thisPackageName,"saveLoginHistory")){
    	    // add login history in superUser's Transaction
    	    try{
    	    	TransactionManager::startTransaction(null,true);
    	    	
    	    	LoginHistory::create(array(
    	    			"logindetailsId" => $this->id,
    	    			"type"=>LoginHistory::LH_LOGIN,
    	    			"time"=>$loggedInTime
    	    	));
    	    	
    	    	TransactionManager::commitTransaction();
    	    }catch (\Exception $e){
    	    	TransactionManager::abortTransaction();
    	    	throw $e;
    	    }
    	}
        return true;
    }


    function logout(){

         $loggedOutTime = MySql::getMysqlDate(null,true);

         if(Settings::getSettings(self::$thisPackageName,"saveLoginHistory")){
         	// add login history in superUser's Transaction
         	try{
         		TransactionManager::startTransaction(null,true);
         	
         		LoginHistory::create(array(
         				"logindetailsId" => $this->id,
         				"type"=>LoginHistory::LH_LOGOUT,
         				"time"=>$loggedOutTime
         		));
         	
         		TransactionManager::commitTransaction();
         	}catch (\Exception $e){
         		TransactionManager::abortTransaction();
         		throw $e;
         	}
         }

    }
    
    
    static function authenticate($loginName,$password){
    	$loginDetails = null;
    	TransactionManager::executeInTransaction(function() use (&$loginDetails,$loginName){
    		try{
    			$loginDetails = new LoginDetails($loginName);
    		}catch (DataNotFoundException $e){
    			// dont do anything
    		}
    	},array(),true);
    	if($loginDetails == null){
    		return false;
    	}
    	return $loginDetails->hashPassword($password) == $loginDetails->password;
    }
    
    
    // password reset functions //
    
    static function requestPasswordReset($loginName){
        try{
        	TransactionManager::startTransaction(null,true);
        	
        	$token = null;
        	$loginDetails = new LoginDetails($loginName);
        	
        	$loginId = $loginDetails->getAttribute("id");
        	$loginPersonId = $loginDetails->getAttribute("personId");
        	
        	$token = md5(uniqid(MySql::getMysqlDate(null,true).$loginId.$loginPersonId.$loginName,true));
        	
        	$savedToken = $loginId.md5($loginId.$loginName.$token);
        
        	LoginHistory::create(array(
        			"logindetailsId" => $loginId,
        			"type"=>LoginHistory::LH_PASSWORD_RESET_REQUEST,
        			"sessionId"=>$savedToken
        	));
        
        	TransactionManager::commitTransaction();
        }catch (\Exception $e){
        	TransactionManager::abortTransaction();
        	throw $e;
        }
        
        return $token;
    }

    static function validatePasswordResetToken($loginName,$token){
        try{
            TransactionManager::startTransaction(null,true);

            $loginDetails = new LoginDetails($loginName);
            $loginId = $loginDetails->getAttribute("id");
            $loginPersonId = $loginDetails->getAttribute("personId");
            
            $savedToken = $loginId.md5($loginId.$loginName.$token);

            $tmpLoginHistory = new LoginHistory();

            $existingPasswordResetRequests = $tmpLoginHistory->find(array(
                "sessionId"=>$savedToken,
                "type"=>LoginHistory::LH_PASSWORD_RESET_REQUEST,
                "logindetailsId"=>$loginId
            ));

            // check if Password reset request was made
            if(count($existingPasswordResetRequests) != 1){
                throw new BadInputException("No Password reset request for $loginName and $token");
            }

            // check if Password reset request is not expired
            $currentTime = MySql::getMysqlDate(null,true);
            $passwordResetRequestTime = $existingPasswordResetRequests[0]->getAttribute("time");

            $differenceTime = strtotime($currentTime) - strtotime($passwordResetRequestTime);

            if($differenceTime > Settings::getSettings(Model::$thisPackageName,'passwordResetRequestLifetime')){
                throw new BadInputException("Password reset request token '$token' expired");
            }

            // generate validation token
            $validationToken = md5($loginPersonId.$currentTime.md5($currentTime.$token.$loginName));

            $savedToken = $loginId.md5($loginId.$validationToken.$loginName);
            
            LoginHistory::create(array(
            		"logindetailsId" => $loginId,
            		"type"=>LoginHistory::LH_PASSWORD_RESET_REQUEST_VALIDATED,
            		"time"=>$currentTime,
            		"sessionId"=>$savedToken
            ));
            
            TransactionManager::commitTransaction();
        }catch (\Exception $e){
            TransactionManager::abortTransaction();
            throw $e;
        }
        return $validationToken;
    }

    static function resetPassword($loginName,$token,$validationToken,$newPassword){
        try{
            TransactionManager::startTransaction(null,true);
            
            $loginDetails = new LoginDetails($loginName);
            $loginId = $loginDetails->getAttribute("id");
            $loginPersonId = $loginDetails->getAttribute("personId");
            
            $tmpLoginHistory = new LoginHistory();
            $existingPasswordResetRequests = $tmpLoginHistory->find(array(
                "sessionId"=>array(
                    self::OPERATOR_IN => array(
                        $loginId.md5($loginId.$loginName.$token),
                        $loginId.md5($loginId.$validationToken.$loginName)
                    )
                ),
                "logindetailsId"=>$loginId
            ));

            // check if Password reset request was made
            if(count($existingPasswordResetRequests) != 2){
                throw new BadInputException("No Password reset request for $loginName and $token");
            }

            foreach($existingPasswordResetRequests as $existingPasswordResetRequest){
                if($existingPasswordResetRequest->getAttribute("type") == LoginHistory::LH_PASSWORD_RESET_REQUEST){
                    $tokenObj = $existingPasswordResetRequest;
                }else if($existingPasswordResetRequest->getAttribute("type") == LoginHistory::LH_PASSWORD_RESET_REQUEST_VALIDATED){
                    $validationTokenObj = $existingPasswordResetRequest;
                }
            }

            if(!isset($tokenObj) || !isset($validationTokenObj)){
                throw new BadInputException("invalid tokens $token or $validationToken");
            }
            
            
            // validate for matching token and validationToken
            $validationTime = $validationTokenObj->getAttribute("time");
            $validationTime = MySql::getMysqlDate($validationTime,true);
            if($validationToken !== md5($loginPersonId.$validationTime.md5($validationTime.$token.$loginName))){
                throw new BadInputException("$token and $validationToken does not match");
            }

            // check for validation token lifetime
            $currentTime = MySql::getMysqlDate(null,true);
            $differenceTime = strtotime($currentTime) - strtotime($validationTime);
            if($differenceTime > Settings::getSettings(Model::$thisPackageName,'passwordResetRequestValidationLifetime')){
                throw new BadInputException("Password reset request validation token '$validationToken' expired");
            }

            // If everything is ok , reset password
            $loginDetails->changePassword($newPassword);

            LoginHistory::create(array(
            		"logindetailsId" => $loginId,
            		"type"=>LoginHistory::LH_PASSWORD_RESET
            ));

            TransactionManager::commitTransaction();
        }catch (\Exception $e){
            TransactionManager::abortTransaction();
            throw $e;
        }
    }
    
    /**
     * @param string $newPassword
     * @access ("person|systemAdmin","function|canChangePassword")
     */
    public function changePassword($newPassword){
        // to compensate timing attack
        time_nanosleep(0, rand(0,1000));
        
        $password = $this->hashPassword($newPassword);
        parent::setAttributes(array("password"=>$password));

    }
    
    
    // access functions //

    protected static function canCreate($data){
        return false;
    }

    protected static function canRead($filters){
        $sessionPerson = Session::getInstance()->get(Session::SESSION_PERSON);
        if($sessionPerson){
            $loginDetailsClass = get_class();
            $sessionPersonId = $sessionPerson['id'];

            $sessionPersonIdExpr = "{".$loginDetailsClass."."."personId"."}";
            
            $accessQuery = "$sessionPersonIdExpr = $sessionPersonId";
            return $accessQuery;
        }
        return false;
    }
    
    protected function canEdit($args = array()){
    	$sessionPerson = Session::getInstance()->get(Session::SESSION_PERSON);
    	if($sessionPerson){
    		$sessionPersonId = $sessionPerson['id'];
    		return $sessionPersonId == $this->personId;
    	}
    	return false;
    }
    
    protected function canChangePassword($newPassword){
    	return $this->canEdit(array());
    }
    
    protected function canDelete(){
    	return false;
    }

}
?>
