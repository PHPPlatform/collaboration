<?php

/**
 * User: Raaghu
 * Date: 12-09-2015
 * Time: PM 10:34
 */

namespace PhpPlatform\Collaboration;

use PhpPlatform\Annotations\Annotation;
use PhpPlatform\Persist\Model as PersistModel;
use PhpPlatform\Persist\TransactionManager;
use PhpPlatform\Persist\Reflection;
use PhpPlatform\Collaboration\Util\PersonSession;

abstract class Model extends PersistModel{

    protected static $thisPackageName = 'php-platform/collaboration';
    
    /**
     * checks the access for this operation based on the annotations defined for this operation
     * @param string|Model $object on which access needs to be validated
     * @throws Exception
     * @return boolean|string True or Valid where expression for Read access on Success other false 
     */
    private static function access($object){
    	
    	if(is_string($object)){
    		$className = $object;
    		$object = null;
    	}else{
    		$className = get_class($object);
    	}

        if(TransactionManager::isSuperUser()){
            return true;
        }

        $hasAccess = false;
        $hasAccessChecks = false;
        try{
            TransactionManager::startTransaction(null,true);

            $debugBacktraces = debug_backtrace(false);
            $args = null;
            $function = null;
            foreach($debugBacktraces as $debugBacktrace){
                if($debugBacktrace["class"] == $className){
                    $args = $debugBacktrace["args"];
                    $function = $debugBacktrace["function"];
                    break;
                }
            }
            
            $annotations = Annotation::getAnnotations($className,null,null,$function);
            $annotations = $annotations["methods"][$function];

            $accessAnnotations = false;
            if(array_key_exists("access", $annotations)){
            	$accessAnnotations = $annotations["access"];
            }
            if(is_string($accessAnnotations)){
            	$accessAnnotations = array($accessAnnotations);
            }
            $parentClass = $className;
            while(in_array("inherit", $accessAnnotations) && $parentClass != false){
            	$parentClass = get_parent_class($parentClass);
            	if($parentClass == false){
            		break;
            	}
            	$annotations = Annotation::getAnnotations($parentClass,null,null,$function);
            	if(array_key_exists($function, $annotations["methods"])
            			&& array_key_exists("access", $annotations["methods"][$function])){
            		$_accessAnnotations = $annotations["methods"][$function]["access"];
            		if(is_string($_accessAnnotations)){
            			$_accessAnnotations = array($_accessAnnotations);
            		}
            		unset($accessAnnotations["inherit"]);
            		$accessAnnotations = array_merge($accessAnnotations,$_accessAnnotations);
            	}
            }
            
            if(is_array($accessAnnotations)){
                $hasAccessChecks = true;
                
                // $annotations["access"] = array("group|G1_NAME","group|G2_NAME","organization|O1_NAME","role|R1_NAME","person|P_NAME","function|F1_NAME","function|F2_NAME")
                $accessMasks = array();
                foreach ($accessAnnotations as $accessMask){
                	$accessMaskArr = preg_split('/\|/', $accessMask);
                	$accessMaskType = $accessMaskArr[0];
                	$accessMaskName = $accessMaskArr[1];
                	if(!array_key_exists($accessMaskType, $accessMasks)){
                		$accessMaskNames = array();
                	}else{
                		$accessMaskNames = $accessMasks[$accessMaskType];
                	}
                	$accessMaskNames[] = $accessMaskName;
                	$accessMasks[$accessMaskType] = $accessMaskNames;
                }
                //$accessMasks = array("group"=>array("G1_NAME","G2_NAME"),"orgnaization"=>array("O1_NAME") .....)
                
                $sessionAccounts = PersonSession::getAccounts();
                if(!is_array($sessionAccounts)){
                	$sessionAccounts = array();
                }
                
                foreach (array("organization", "role", "person", "group") as $accountType){
                	if(array_key_exists($accountType, $accessMasks) && array_key_exists($accountType, $sessionAccounts)){
                		if(count(array_diff($accessMasks[$accountType], $sessionAccounts[$accountType])) < count($accessMask[$accountType])){
                			$hasAccess = true;
                			break;
                		}
                	}
                }
                
                if(!$hasAccess && array_key_exists("function", $accessMasks)){
                	foreach ($accessMasks["function"] as $function){
                		$result = Reflection::invokeArgs($className, $function, $object, $args);
                		if(is_string($result)){
                			$hasAccess = $result;
                			break;
                		}
                		if(isset($result) && $result){
                			$hasAccess = true;
                			break;
                		}
                	}
                }
            }

            TransactionManager::commitTransaction();
        }catch (\Exception $e){
            TransactionManager::abortTransaction();
            throw $e;
        }
        return $hasAccessChecks?$hasAccess:true;
    }



    /**
     * Method to check for Create Access
     * This method needs to be overridden by implementing Models
     *
     * @return boolean - True to allow creation , otherwise False
     */
    final protected static function CreateAccess(){
        return self::access(get_called_class());
    }

    /**
     * Method to check for Read Access
     * This method needs to be overridden by implementing Models
     *
     * @return string - Where expression to use to find objects with read access,
     *         boolean - False if no Read allowed for the particular model
     */
    final protected static function ReadAccess(){
        return self::access(get_called_class());
    }

    /**
     * Method to check for Update Access
     * This method needs to be overridden by implementing Models
     *
     * @return boolean - True to allow Update , otherwise False
     */
    final protected function UpdateAccess(){
        return self::access($this);
    }

    /**
     * Method to check for Delete Access
     * This method needs to be overridden by implementing Models
     *
     * @return boolean - True to allow deletion , otherwise False
     */
    final protected function DeleteAccess(){
        return self::access($this);
    }


}