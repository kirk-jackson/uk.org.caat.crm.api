<?php

use CRM_API_ExtensionUtil as E;

class CRM_API_Utils {
	// Convert data of any type to string, for diagnostic purposes. Similar to print_r and var_dump but more concise.
	public static function toString($arg, $multiline = FALSE) {
		return self::_toString($arg, $multiline);
	}
	
	public static function isSignedIntString($string) {
		return is_string($string) && preg_match('/^[+-]?\d+$/u', $string);
	}
	
	private static function _toString($arg, $multiline = FALSE, $objects = []) {
		$ws = $multiline ? PHP_EOL : ' ';
		$eol = $multiline ? PHP_EOL : '';
		
		if (is_array($arg)) {
			$string = '[';
			if ($arg) {
				$string .= $eol;
				$elementStrings = [];
				foreach ($arg as $key => $value)
					$elementStrings[] = self::_toString($key) . ' => ' . trim(self::_toString($value, $multiline, $objects));
				$elementsString = implode(',' . $ws, $elementStrings) . $eol;
				if ($multiline) $elementsString = self::indent($elementsString);
				$string .= $elementsString;
			}
			$string .=  ']';
			return $string;
		}
		
		if (is_object($arg)) {
			if (array_key_exists(spl_object_hash($arg), $objects))
				return 'RECURSION' . $eol;
			$objects[spl_object_hash($arg)] = NULL;
			
			if (!$multiline && method_exists($arg, '__toString')) return $arg->__toString();
			
			if (get_class($arg) === 'DateTime') return 'DateTime(' . $arg->format('Y-m-d H:i:s.v T') . ')';
			
			$string = get_class($arg) . '(' . $eol;
			$reflectionClass = new ReflectionClass($arg);
			$properties = array_filter($reflectionClass->getProperties(ReflectionProperty::IS_PUBLIC | ReflectionProperty::IS_PROTECTED | ReflectionProperty::IS_PRIVATE), function ($property) {return !$property->isStatic();});
			if ($properties) {
				$propertyStrings = [];
				foreach ($properties as $property) {
					$property->setAccessible(TRUE);
					$propertyStrings[] = '$' . $property->getName() . ' = ' . trim(self::_toString($property->getValue($arg), $multiline, $objects));
				}
				$propertiesString = implode(',' . $ws, $propertyStrings) . $eol;
				if ($multiline) $propertiesString = self::indent($propertiesString);
				$string .= $propertiesString;
			}
			$string .=  ')';
			return $string;
		}
		
		if (is_string($arg)) return '\'' . str_replace('\'', '\\\'', $arg) . '\'';
		
		if (is_bool($arg)) return $arg ? 'TRUE' : 'FALSE';
		
		if (is_null($arg)) return 'NULL';
		
		return (string)$arg;
	}
	
	private static function indent($text, $levels = 1) {
		$lineList = mb_split(PHP_EOL, $text);
		$wholeLines = end($lineList) === '';
		if ($wholeLines) array_pop($lineList);
		foreach ($lineList as &$line) $line = str_repeat("\t", $levels) . $line;
		if ($wholeLines) array_push($lineList, '');
		return implode(PHP_EOL, $lineList);
	}
}

?>
