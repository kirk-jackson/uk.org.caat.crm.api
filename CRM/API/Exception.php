<?php

use CRM_API_ExtensionUtil as E;

class CRM_API_Exception extends Exception {
	protected $apiTraceString;
	
	public function __construct($message, $apiResult) {
		parent::__construct($message . ': ' . $apiResult['error_message']);
		$this->apiTraceString = $apiResult['trace'];
	}
	
	public function getApiTraceString() {
		return $this->apiTraceString;
	}
	
	public function __toString() {
		$string = 'exception \'' . get_called_class() . '\' with message \'' . $this->message . '\' in ' . $this->file . ':' . $this->line . PHP_EOL;
		$string .= 'Stack trace:' . PHP_EOL;
		$string .= $this->apiTraceString;
		return $string;
	}
}

?>
