<?php

namespace PhpPlatform\Tests\Collaboration;

use PhpPlatform\Session\Session;
use PhpPlatform\JSONCache\Cache;
use PhpPlatform\Persist\Reflection;
use PhpPlatform\Session\Factory;

class SessionImpl extends Cache implements Session{
	
	private static $instace = null;
	private $id = null;
	
	
	protected function __construct(){
		$this->id = md5(rand(0, 100).time().'SimpleSession for testing'.rand(100, 1000));
		$this->cacheFileName = $this->id;
		parent::__construct();
	}
	
	public static function getInstance(){
		if(self::$instace == null){
			self::$instace = new SessionImpl();
		}
		return self::$instace;
	}
	
	public function set($key, $value){
		$keys = Reflection::invokeArgs(get_parent_class(), 'getPaths', $this, array($key));
		foreach (array_reverse($keys) as $_key){
			$value = array($_key=>$value);
		}
		return parent::setData($value);
	}
	
	public function get($key) {
		return parent::getData($key);
	}
	
	public function clear(){
		return parent::reset();
	}
	
	
	public function reset($flag=0){ 
		self::$instace = new SessionImpl();
		if($flag & Session::RESET_COPY_OLD){
			self::$instace->setData(self::getData(''));
		}
		if($flag & Session::RESET_DELETE_OLD){
			parent::reset();
		}
		Reflection::setValue('PhpPlatform\Session\Factory', 'session', null, self::$instace);
		return self::$instace;
	}
	
	
	public function getId(){ 
		return $this->id;
	}

}