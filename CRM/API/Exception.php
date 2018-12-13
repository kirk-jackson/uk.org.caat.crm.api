<?php

use CRM_API_ExtensionUtil as E;

class CRM_API_Exception extends Exception {
	public function __construct(string $message, CiviCRM_API3_Exception $previous) {
		// Set the extended message.
		$extendedMessage = $message . ': ' . $previous->getMessage();
		if (array_key_exists('trace', $previous->getExtraParams()))
			$extendedMessage .= PHP_EOL . 'Stack trace from API:' . PHP_EOL . $previous->getExtraParams()['trace'];
		
		foreach ([$previous->getErrorCode(), $previous->getCode(), 0] as $code) {
			if (is_int($code)) {
				break;
			} elseif (is_string($code) && preg_match('/^-?\d+$/u', trim($code))) {
				$code = (int)$code;
				break;
			}
		}
		
		parent::__construct($extendedMessage, $code, $previous);
	}
}

?>
