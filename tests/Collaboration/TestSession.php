<?php

namespace PhpPlatform\Tests\Collaboration;

class TestSession extends \PHPUnit_Framework_TestCase{
	
	private static $WEB_RESOURCE_PATH = false;
	private static $WEB_SERVER_FILEPATH = false;
	
	private static $tmpLogFile = "";
	
	public static function setUpBeforeClass(){
		
		self::$WEB_RESOURCE_PATH = getenv('WEB_RESOURCE_PATH');
		if(!self::$WEB_RESOURCE_PATH && defined('WEB_RESOURCE_PATH')){
			self::$WEB_RESOURCE_PATH = WEB_RESOURCE_PATH;
		}
		if(self::$WEB_RESOURCE_PATH == ""){
			return;
		}
		
		self::$WEB_SERVER_FILEPATH = getenv('WEB_SERVER_FILEPATH');
		if(!self::$WEB_SERVER_FILEPATH && defined('WEB_SERVER_FILEPATH')){
			self::$WEB_SERVER_FILEPATH = WEB_SERVER_FILEPATH;
		}
		
		if(self::$WEB_SERVER_FILEPATH){
			$template = file_get_contents(__DIR__.'/TestSession.inc');
			$template = preg_replace('/__DIR__/', "'".__DIR__."'", $template);
			
			self::$tmpLogFile = tempnam(sys_get_temp_dir(), "PPS");
			$template = preg_replace('/LOG_FILE/', "'".self::$tmpLogFile."'", $template);
			
			file_put_contents(self::$WEB_SERVER_FILEPATH, $template);
		}else{
			throw new \Exception("Please provide WEB_RESOURCE_PATH and WEB_SERVER_FILEPATH");
		}
		
	}
	
	public function setUp(){
		if(self::$WEB_RESOURCE_PATH == ""){
			return;
		}
		file_put_contents(self::$tmpLogFile, "");
	}
	
	public function testNewSession(){
		if(self::$WEB_RESOURCE_PATH == ""){
			return;
		}
		
		$output = $this->execCurl(self::$WEB_RESOURCE_PATH."?method=CREATE_NEW_SESSION",'');
		parent::assertEquals("created new session", $output['content']);
		//print_r($output);
		
		$output = $this->execCurl(self::$WEB_RESOURCE_PATH."?method=SET_VALUE&key=myKey&value=myValue",'');
		parent::assertEquals("", $output['content']);
		$session2Cookies = $output['cookies'];
		//print_r($output);
		
		$output = $this->execCurl(self::$WEB_RESOURCE_PATH."?method=GET_VALUE&key=myKey",$session2Cookies);
		parent::assertEquals("myValue", $output['content']);
		//print_r($output);
		
		$output = $this->execCurl(self::$WEB_RESOURCE_PATH."?method=GET_VALUE&key=myKey",'');
		parent::assertEquals("", $output['content']);
		//print_r($output);
		
		parent::assertEquals("", file_get_contents(self::$tmpLogFile));
	}
	
	/**
	 * @dataProvider sessionRefreshOptions 
	 */
	public function testRefreshSession($carryOldData,$deleteOldData,$data,$expectedData){
		
		if(self::$WEB_RESOURCE_PATH == ""){
			return;
		}
		
		$method = 'REFRESH';
		$method .= ($carryOldData?'_TRUE':'_FALSE');
		$method .= ($deleteOldData?'_TRUE':'_FALSE');
		
		// set data in session 1
		$output = $this->execCurl(self::$WEB_RESOURCE_PATH."?method=SET_VALUE&key=myKey1&value=".$data[0]);
		parent::assertEquals("", $output['content']);
		$session1Cookies = $output['cookies'];
				
		// test data in session 1
		$output = $this->execCurl(self::$WEB_RESOURCE_PATH."?method=GET_VALUE&key=myKey1",$session1Cookies);
		parent::assertEquals($expectedData[0], $output['content']);
		
		// regenerate session 1
		$output = $this->execCurl(self::$WEB_RESOURCE_PATH."?method=$method",$session1Cookies);
		parent::assertEquals("", $output['content']);
		$regeneratedSession1Cookies = $output['cookies'];
		
		// test data in session 1 after refresh
		$output = $this->execCurl(self::$WEB_RESOURCE_PATH."?method=GET_VALUE&key=myKey1",$session1Cookies);
		parent::assertEquals($expectedData[1], $output['content']);
		
		// test data in regenerated session
		$output = $this->execCurl(self::$WEB_RESOURCE_PATH."?method=GET_VALUE&key=myKey1",$regeneratedSession1Cookies);
		parent::assertEquals($expectedData[2], $output['content']);
		
		// set/overide data in regenerated session
		$output = $this->execCurl(self::$WEB_RESOURCE_PATH."?method=SET_VALUE&key=myKey1&value=".$data[1],$regeneratedSession1Cookies);
		parent::assertEquals("", $output['content']);
		$output = $this->execCurl(self::$WEB_RESOURCE_PATH."?method=SET_VALUE&key=myKey2&value=".$data[2],$regeneratedSession1Cookies);
		parent::assertEquals("", $output['content']);
		
		// test overided data in regenerated session
		$output = $this->execCurl(self::$WEB_RESOURCE_PATH."?method=GET_VALUE&key=myKey1",$regeneratedSession1Cookies);
		parent::assertEquals($expectedData[3], $output['content']);
		
		// test old data in session 1
		$output = $this->execCurl(self::$WEB_RESOURCE_PATH."?method=GET_VALUE&key=myKey1",$session1Cookies);
		parent::assertEquals($expectedData[4], $output['content']);

		// test new data set in regenerated session from old session
		$output = $this->execCurl(self::$WEB_RESOURCE_PATH."?method=GET_VALUE&key=myKey2",$session1Cookies);
		parent::assertEquals($expectedData[5], $output['content']);
		
		sleep(11);
		
		// test old data in old session after 10 seconds
		$output = $this->execCurl(self::$WEB_RESOURCE_PATH."?method=GET_VALUE&key=myKey1",$session1Cookies);
		parent::assertEquals($expectedData[6], $output['content']);
		
	}
	
	function sessionRefreshOptions(){
		return array(
				array(true,true,array("myValue1","myValue2","myValue3"),  array("myValue1","",        "myValue1","myValue2","",        "","")),
				array(true,false,array("myValue1","myValue2","myValue3"), array("myValue1","myValue1","myValue1","myValue2","myValue1","","")),
				array(false,true,array("myValue1","myValue2","myValue3"), array("myValue1","",        "",        "myValue2","",        "","")),
				array(false,false,array("myValue1","myValue2","myValue3"),array("myValue1","myValue1","",        "myValue2","myValue1","",""))
		);
	}
	
	private function execCurl( $url, $cookiesIn = '' ){
		$options = array(
				CURLOPT_RETURNTRANSFER => true,     // return web page
				CURLOPT_HEADER         => true,     //return headers in addition to content
				CURLOPT_FOLLOWLOCATION => true,     // follow redirects
				CURLOPT_ENCODING       => "",       // handle all encodings
				CURLOPT_AUTOREFERER    => true,     // set referer on redirect
				CURLOPT_CONNECTTIMEOUT => 120,      // timeout on connect
				CURLOPT_TIMEOUT        => 120,      // timeout on response
				CURLOPT_MAXREDIRS      => 10,       // stop after 10 redirects
				CURLINFO_HEADER_OUT    => true,
				CURLOPT_SSL_VERIFYPEER => false,     // Disabled SSL Cert checks
				CURLOPT_HTTP_VERSION   => CURL_HTTP_VERSION_1_1,
				CURLOPT_COOKIE         => $cookiesIn
		);
	
		$ch      = curl_init( $url );
		curl_setopt_array( $ch, $options );
		$rough_content = curl_exec( $ch );
		$err     = curl_errno( $ch );
		$errmsg  = curl_error( $ch );
		$header  = curl_getinfo( $ch );
		curl_close( $ch );
	
		$header_content = substr($rough_content, 0, $header['header_size']);
		$body_content = trim(str_replace($header_content, '', $rough_content));
		$pattern = "#Set-Cookie:\\s+(?<cookie>[^=]+=[^;]+)#m";
		$matches = array();
		preg_match_all($pattern, $header_content, $matches);
		$cookiesOut = implode("; ", $matches['cookie']);
	
		$header['errno']   = $err;
		$header['errmsg']  = $errmsg;
		$header['headers']  = $header_content;
		$header['content'] = $body_content;
		
		$cookiesOutArray = array();
		foreach (preg_split('/;/', $cookiesOut) as $cookieOut){
			$cookieOut = trim($cookieOut);
			$cookieAssignPos = strpos($cookieOut, "=");
			$cookieName = substr($cookieOut, 0,$cookieAssignPos);
			$cookieValue = substr($cookieOut, $cookieAssignPos + 1);
			$cookiesOutArray[$cookieName] = $cookieValue;
		}
		
		$cookiesOut = "";
		foreach ($cookiesOutArray as $cookieName=>$cookieValue){
			if($cookieOut != ""){
				$cookiesOut .= "; ";
			}
			$cookiesOut .= $cookieName."=".$cookieValue;
		}
		
		$header['cookies'] = $cookiesOut;
		return $header;
	}
	
	
}