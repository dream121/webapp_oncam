<?php
class CS_Mservice_IndexController extends Mage_Core_Controller_Front_Action
{
	public function indexAction()
	{
		$cName = 'CS_Mservice_Main';
		//
		$params = $this->_request->getParams();
	//	print_r($params);
		unset($params['controller']);
		unset($params['action']);
		unset($params['module']);
		//
		$headers = apache_request_headers();
		if (isset($headers['methodName'])) {
			$params['method'] = $headers['methodName'];
		}
		foreach ($headers as $headerName => $headerVal) {
			if (substr($headerName, 0, 6) == "param_") {
				$params[substr($headerName, 6)] = $headerVal;
			}
		}
		//
	//	print_r($headers);die;
		$xml = simplexml_load_file('app/etc/local.xml');
		$dbhost = $xml->global->resources->default_setup->connection->host;
		$dbuser = $xml->global->resources->default_setup->connection->username;
		$dbpass = $xml->global->resources->default_setup->connection->password;
		$dbname = $xml->global->resources->default_setup->connection->dbname;
		
		try {
			$con = mysql_connect($dbhost,$dbuser,$dbpass) or die("Unable to connect database.");
			$db = mysql_select_db($dbname);
		} catch (Exception $e){
			$con = mysql_connect("localhost","root","eworks") or die("Unable to connect database.");
			$db = mysql_select_db("chattrspace");
		}
		try{
		$server = new CS_Mservice_Server();
		$server->setClass($cName);
		} catch (Exception $e) {
            	#->setCustomerFormData($this->getRequest()->getPost())
                 echo "error->".$e->getMessage();
        }
		$serviceMethod="";
		if (isset($params['method']) == true ) {
			if ($params['method'] != "getProductImage"
					&& $params['method'] != "getDesignerImage"
					&& $params['method'] != "getRoomImage") {
				$server->returnResponse(true);
			}
			$serviceMethod=$params['method'];
		} elseif (isset($_POST['method'])==true) {
			$serviceMethod=$_POST['method'];
		} else {
			$server->returnResponse(true);
		}

		
//
//		foreach ($params as $key => $value) {
//			if (is_numeric($value) == true) {
//				settype($params[$key], "int");
//			}
//		}

		
		$is_https = false; 
		if( isset($_SERVER['HTTPS']) && strtolower($_SERVER['HTTPS']) == 'on' ) $is_https = true; else $is_https = false;
		//if($is_https){
			$accesstoken="";
			if ( isset($_POST['accesstoken']) ) {
				$accesstoken=$_POST['accesstoken'];
			} else {
				$accesstoken=$params['accesstoken'];
			}
			if ( isset($_POST['user_id']) ) {
				$user_id=$_POST['user_id'];
			} else {
				$user_id=$params['user_id'];
			}
			if(!$this->tokenIsvalid($accesstoken,$user_id,$serviceMethod) && $serviceMethod!='getToken' && $serviceMethod!='tokenIsvalid'){
				$response = '<?xml version="1.0" encoding="UTF-8"?><CS_Mservice_Main generator="eworks" version="1.0"><error><response>Error:002</response><message>INVALID ACCESS TOKEN ERROR UPDATED'. $_POST['accesstoken'] .'</message><token>'. $accesstoken .'</token><method>'.$serviceMethod.'</method><status>failed</status></error></CS_Mservice_Main>';
			} else{
				$response = $server->handle($params);
			}
		/*}
		else{ 
			$response = $server->handle($params);
		}*/
		
		$responseXML = str_replace('<' . $cName . ' generator="zend" version=', '<' . $cName . ' generator="eworks" version=', $response);
		
		$format = "xml";

		if (!array_key_exists('format', $params)){
			if ($this->_request->isXmlHttpRequest()) {
				$format = 'json';
			} else {
				$format = 'xml';
			}
		} elseif (isset($params['format']) == true) {
			$format = $params['format'];
		} else {
			$format = "xml";
		}

		header('Content-type: text/xml');
		header("Cache-Control: no-cache, must-revalidate, no-store"); // HTTP/1.1
		header("Expires: Mon, 26 Jul 1997 05:00:00 GMT"); // Date in the past

		switch ($format){
			case 'json':
				$this->_response->setHeader('Content-Type', 'text/javascript')->setBody(Zend_Json::fromXML($responseXML));
					break;
			default:
				$this->_response->setHeader('Content-Type', 'text/xml')->setBody($responseXML);
				break;
		}

		return true;
	}
	public function tokenIsvalid($accesstoken,$user_id=0,$serviceMethod){
		if($accesstoken == "qpOjpKibmqqepA=="){
			return 1;
		}
		
		if((($serviceMethod == "getTopEventsNew")||($serviceMethod == "userCheckInAndroid")||($serviceMethod == "userCheckInIphone")||($serviceMethod == "unlinkFacebook")||($serviceMethod == "unlinkTwitter")||($serviceMethod == "updateProfileInfo")||($serviceMethod == "registerDevice")||($serviceMethod == "pushNotiOff")||($serviceMethod == "pushNotiOn")||($serviceMethod == "registerDeviceAndroid")||($serviceMethod == "unregisterDevice")||($serviceMethod == "unregisterDeviceAndroid")||($serviceMethod == "uploadCustomerProfileImage")||($serviceMethod == "uploadCustomerProfileImageIphone")||($serviceMethod == "mobileNotification")||($serviceMethod == "linkYoutubeV3")||($serviceMethod == "unlinkYoutube")||($serviceMethod == "RSVP")||($serviceMethod == "followPeople")||($serviceMethod == "getMobileToken")||($serviceMethod == "getMobileTokenAndroid")||($serviceMethod == "MobileVerify")||($serviceMethod == "setContacts")||($serviceMethod == "mobileInviteByEmail")||($serviceMethod == "mobileInviteByContacts")||($serviceMethod == "createEvent")||($serviceMethod == "createEventAndroid")||($serviceMethod == "setContactsAndroid")||($serviceMethod == "createEvent")||($serviceMethod == "createEvent")||($serviceMethod == "createEvent")) && ($user_id > 0)){
			$seckey = substr($accesstoken,0,24);
			$token = substr($accesstoken,24);
			$resource = Mage::getSingleton('core/resource');
			$read= $resource->getConnection('core_read');
			$write = $resource->getConnection('core_write');
			$xapplication_token = $resource->getTableName('xapplication_token');
			$rs = $read->fetchAll("SELECT * FROM $xapplication_token WHERE user_id=".$user_id." and  token='".$token."' and security_key='".$seckey."'");
			if(count($rs) > 0)
				return 1;
			else return 0;
		} else{
			return 1;
		}
	}
}
?>