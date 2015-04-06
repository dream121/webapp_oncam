<?php
class CS_Mservice_Server extends Zend_Rest_Server {

	function fault($exception = null, $code = null){
		try {
			return parent::fault($exception, $code);
		} catch (Exception $e) {
			$xml = simplexml_load_string('<?xml version="1.0" encoding="utf-8"?><fault></fault>');
			$xml->addChild('status', 'fail');


			if ($exception instanceof Exception) {
				$xml->addChild('error', $exception->getMessage());
			} else {
				$xml->addChild('error', 'Unknown error');
			}

			if (is_null($code) || (404 != $code))
			{
				$this->_headers[] = 'HTTP/1.0 400 Bad Request';
			} else {
				$this->_headers[] = 'HTTP/1.0 404 File Not Found';
			}
			return $xml;
		}
	}

}

?>