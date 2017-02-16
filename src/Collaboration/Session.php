<?php

namespace PhpPlatform\Collaboration;

use PhpPlatform\JSONCache\Cache;
use PhpPlatform\Config\Settings;
use PhpPlatform\Persist\Reflection;
use PhpPlatform\Errors\Exceptions\Application\ProgrammingError;

class Session extends Cache{
	
	/**
	 * @var Session
	 */
	private static $sessionObj = null;
	private $id = null;
	
	protected $cacheFileName = "session_cache_"; // cache for session
	
	protected function __construct(){
		if(session_id() == ""){
			session_start();
		}
		$this->id = session_id();
		$this->cacheFileName .=  $this->id;
		session_write_close();
		
		$sessionSavePath = Settings::getSettings('php-platform/collaboration','session.savePath');
		if(!isset($sessionSavePath)){
			$sessionSavePath = session_save_path();
		}
		
		if(!is_dir($sessionSavePath)){
			mkdir($sessionSavePath,'0777',true);
		}
		
		$this->cacheFileName = $sessionSavePath."/".$this->cacheFileName;
		if(is_file($this->cacheFileName)){
			$fileContents = file_get_contents($this->cacheFileName);
			$sessionData = json_decode($fileContents,true);
			if($sessionData !== null){
				if(array_key_exists("regeneratedAt", $sessionData)){
					if(microtime(true) - $sessionData['regeneratedAt'] > 10){
						//data from old sessions which are regenerated 10 seconds ago will be deleted
						$sessionData = array("data"=>array());
						parent::setData($sessionData);
					}
				}
				Reflection::setValue(get_parent_class(), 'settings', $this, $sessionData);
			}
		}
		
		$_cacheFileName = $this->cacheFileName;
		
		session_set_save_handler(
		function($savePath, $sessionName){ // open
			return true;
		}, function(){ //close
			return true;
		}, function($sessionId){ // read
			return "";
		}, function($sessionId, $data){ //write
			return true;
		}, function($sessionId) use ($_cacheFileName){ // destroy
			if(file_exists($_cacheFileName)){
				unlink($_cacheFileName);
			}
			return true;
		}, function($maxlifetime) use ($_cacheFileName){ // gc
			$sessionSavePath = dirname($_cacheFileName);
			foreach (glob("$sessionSavePath/session_cache_*") as $file) {
				if (filemtime($file) + $maxlifetime < time() && file_exists($file)) {
					unlink($file);
				}
			}
		});
	}
	
	/**
	 * @return Session
	 */
	public static function getInstance(){
		if(self::$sessionObj == null){
			self::$sessionObj = new Session();
		}
		return self::$sessionObj;
	}
	
	public function get($key){
		return parent::getData("data.".$key);
	}
	
	public function set($key,$value){
		return parent::setData(array("data"=>array($key=>$value)));
	}
	
	/**
	 * @deprecated
	 */
	public function getData($key){
		throw new ProgrammingError('Please use '.get_class().'::get($key) instead');
	}
	
	/**
	 * @deprecated
	 */
	public function setData(array $data){
		throw new ProgrammingError('Please use '.get_class().'::set($key,$value) instead');
	}
	
	/**
	 * 
	 * @param string $carryOldData , if true , data from old session is carry forwarded
	 * @param string $deleteOldSession , if true, data in old session is deleted
	 * @return Session , a new Seesion  object
	 */
	public function refresh($carryOldData = true, $deleteOldSession = false){
		if($carryOldData){
			$data = parent::getData("");
		}else{
			$data = array();
		}
		$regeneratedAt = microtime(true);
		parent::setData(array("regeneratedAt"=>$regeneratedAt));
		session_start();
		session_regenerate_id();
		session_write_close();
		self::$sessionObj = new Session();
		Reflection::invokeArgs(get_parent_class(), 'setData', self::$sessionObj,array($data));
		
		if($deleteOldSession){
			parent::reset();
		}
		
		return self::$sessionObj;
	}
	
	public function getId(){
		return $this->id;
	}
	
	public function reset(){
		return $this->refresh(false,true);
	}
	
	
}
