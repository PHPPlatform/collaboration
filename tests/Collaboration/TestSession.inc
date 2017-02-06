<?php

use PhpPlatform\Collaboration\Session;
use PhpPlatform\Mock\Config\MockSettings;
use PhpPlatform\Errors\ErrorHandler;

include_once __DIR__.'/../../vendor/autoload.php';

MockSettings::setSettings('php-platform/errors', "logs", array("Application"=>LOG_FILE,"Http"=>LOG_FILE));
MockSettings::setSettings('php-platform/errors', "traces", array("Application"=>LOG_FILE,"Http"=>LOG_FILE));

ErrorHandler::handleError();

$method = $_REQUEST['method'];

switch ($method){
	case "CREATE_NEW_SESSION":
		echo "created new session";
		break;
	case "SET_VALUE":
		Session::getInstance()->set($_REQUEST["key"], $_REQUEST["value"]);
		break;
	case "GET_VALUE":	
		echo Session::getInstance()->get($_REQUEST["key"]);
		break;
	case "REFRESH_TRUE_TRUE":
		Session::getInstance()->refresh(true,true);
		break;
	case "REFRESH_TRUE_FALSE":
		Session::getInstance()->refresh(true,false);
		break;
	case "REFRESH_FALSE_TRUE":
		Session::getInstance()->refresh(false,true);
		break;
	case "REFRESH_FALSE_FALSE":
		Session::getInstance()->refresh(false,false);
		break;
					
}
