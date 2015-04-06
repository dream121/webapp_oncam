<?php
class CS_Service_IndexController extends Mage_Core_Controller_Front_Action
{
	public function indexAction()
	{
		$cName = 'CS_Service_Main';
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
		$server = new CS_Service_Server();
		$server->setClass($cName);
		} catch (Exception $e) {
            	#->setCustomerFormData($this->getRequest()->getPost())
                 echo "error->".$e->getMessage();
        }
		
		if (isset($params['method']) == true) {
			if ($params['method'] != "getProductImage"
					&& $params['method'] != "getDesignerImage"
					&& $params['method'] != "getRoomImage") {
				$server->returnResponse(true);
			}
		} else {
			$server->returnResponse(true);
		}

		
//
//		foreach ($params as $key => $value) {
//			if (is_numeric($value) == true) {
//				settype($params[$key], "int");
//			}
//		}

		$response = $server->handle($params);
		
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
}
?>