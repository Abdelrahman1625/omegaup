<?php

/**
 * Conjunto de validadores genéricos
 *
 * @author joemmanuel
 */
class Validators {

	const NOT_A_NUMBER = "parameterNotANumber";
	const INVALID_ALIAS = "parameterInvalidAlias";
	const IS_EMPTY = "parameterEmpty";
	const IS_INVALID = "parameterInvalid";

	/**
	 * Check if email is valid
	 * 
	 * @param string $email
	 * @param string $parameterName Name of parameter that will appear en error message
	 * @param boolean $required If $required is TRUE and the parameter is not present, check fails.
	 * @return boolean
	 * @throws InvalidArgumentException
	 */
	public static function isEmail($parameter, $parameterName, $required = true) {
		$isPresent = self::throwIfNotPresent($parameter, $parameterName, $required);

		if ($isPresent && !filter_var($parameter, FILTER_VALIDATE_EMAIL)) {
			throw new InvalidParameterException(Validators::IS_INVALID, $parameterName);
		}

		return true;
	}

	/**
	 * Check if string is string and not empty
	 * 
	 * @param string $email
	 * @param string $parameterName Name of parameter that will appear en error message
	 * @param boolean $required If $required is TRUE and the parameter is not present, check fails.
	 * @return boolean
	 * @throws InvalidArgumentException
	 */
	public static function isStringNonEmpty($parameter, $parameterName, $required = true) {
		$isPresent = self::throwIfNotPresent($parameter, $parameterName, $required);

		// Validate data is string        
		if ($isPresent && (!is_string($parameter) || strlen($parameter) < 1)) {
			throw new InvalidParameterException(Validators::IS_EMPTY, $parameterName);
		}

		// Validation passed
		return true;
	}

	/**
	 * 
	 * @param string $parameter
	 * @param string $parameterName
	 * @param int $maxLength
	 * @param boolean $required
	 */
	public static function isStringOfMaxLength($parameter, $parameterName, $maxLength, $required = true) {
		$isPresent = self::throwIfNotPresent($parameter, $parameterName, $required);

		if ($isPresent && !(is_string($parameter) && strlen($parameter) <= $maxLength)) {
			throw new InvalidParameterException("{$parameterName} is too large (max length: {$maxLength})");
		}

		return true;
	}

	/**
	 * 
	 * @param string $parameter
	 * @param string $parameterName
	 * @param int $minLength
	 * @param boolean $required
	 */
	public static function isStringOfMinLength($parameter, $parameterName, $minLength, $required = true) {
		$isPresent = self::throwIfNotPresent($parameter, $parameterName, $required);

		if ($isPresent && !(is_string($parameter) && strlen($parameter) >= $minLength)) {
			throw new InvalidParameterException("{$parameterName} is too short (min length: {$minLength})");
		}

		return true;
	}

	/**
	 * 
	 * @param string $parameter
	 * @param string $parameterName
	 * @param boolean $required
	 */
	public static function isValidAlias($parameter, $parameterName, $required = true) {
		$isPresent = self::throwIfNotPresent($parameter, $parameterName, $required);

		if ($isPresent &&
				!(is_string($parameter) &&
				strlen($parameter) > 0 &&
				strlen($parameter) <= 32 &&
				preg_match('/^[a-zA-Z0-9-_]+$/', $parameter) === 1)) {
					throw new InvalidParameterException(Validators::INVALID_ALIAS, $parameterName);
			}

		return true;
	}
	
	/**
	 * Enforces username requirements
	 * 
	 * @param string $parameter
	 * @param string $parameterName
	 * @param boolean $required
	 * @throws InvalidParameterException
	 */
	public static function isValidUsername($parameter, $parameterName, $required = true) {
		$isPresent = self::throwIfNotPresent($parameter, $parameterName, $required);
		
		if ($isPresent && preg_match("/[^a-zA-Z0-9_.-]/", $parameter)) {
			throw new InvalidParameterException(Validators::INVALID_ALIAS, $parameterName);
		}
		
		Validators::isStringOfMinLength($parameter, $parameterName, 2);
	}

	/**
	 * 
	 * @param date $parameter
	 * @param string $parameterName
	 * @param boolean $required
	 * @return boolean
	 * @throws InvalidParameterException
	 */
	public static function isDate($parameter, $parameterName, $required = true) {
		$isPresent = self::throwIfNotPresent($parameter, $parameterName, $required);

		// Validate that we are working with a date
		// @TODO This strtotime() allows nice strings like "next Thursday". 
		if ($isPresent && strtotime($parameter) === -1) {
			throw new InvalidParameterException(Validators::IS_INVALID, $parameterName);
		}

		return true;
	}

	/**
	 * 
	 * @param string $parameter
	 * @param string $parameterName
	 * @param int $lowerBound
	 * @param int $upperBound
	 * @param boolean $required
	 * @return boolean
	 * @throws InvalidParameterException
	 */
	public static function isNumberInRange($parameter, $parameterName, $lowerBound, $upperBound, $required = true) {
		$isPresent = self::throwIfNotPresent($parameter, $parameterName, $required);

		self::isNumber($parameter, $parameterName, $required);

		// Validate that is target number is inside the range
		if ($isPresent && !($parameter >= $lowerBound && $parameter <= $upperBound)) {
			throw new InvalidParameterException("{$parameterName} is outside the allowed range ({$lowerBound}, {$upperBound})");
		}

		return true;
	}

	/**
	 * 
	 * @param int $parameter
	 * @param string $parameterName
	 * @param bool $required
	 * @return boolean
	 * @throws InvalidParameterException
	 */
	public static function isNumber($parameter, $parameterName, $required = true) {
		$isPresent = self::throwIfNotPresent($parameter, $parameterName, $required);

		// Validate that we are working with a number
		if ($isPresent && !is_numeric($parameter)) {
			throw new InvalidParameterException(Validators::NOT_A_NUMBER, $parameterName);
		}

		return true;
	}

	/**
	 * 
	 * @param mixed $parameter
	 * @param string $parameterName
	 * @param array $enum
	 * @param type $required
	 * @return boolean
	 * @throws InvalidParameterException
	 */
	public static function isInEnum($parameter, $parameterName, array $enum, $required = true) {

		$isPresent = self::throwIfNotPresent($parameter, $parameterName, $required);

		if ($isPresent && !in_array($parameter, $enum)) {
			throw new InvalidParameterException("{$parameterName} is not in expected set: " . implode(", ", $enum));
		}

		return true;
	}

	/**
	 * 
	 * @param mixed $parameter
	 * @param string $parameterName
	 * @param array $enum
	 * @param type $required
	 * @return boolean
	 * @throws InvalidParameterException
	 */
	public static function isValidSubset($parameter, $parameterName, array $enum, $required = true) {

		$isPresent = self::throwIfNotPresent($parameter, $parameterName, $required);

		if ($isPresent) {
			$bad_elements = array();
			$elements = explode(",", $parameter);
			foreach ($elements as $element) {
				if (!in_array($element, $enum)) {
					$bad_elements[] = $element;
				}
			}
			if (count($bad_elements) > 0) {
				throw new InvalidParameterException(implode(",", $bad_elements) . " are not in expected set: " . implode(", ", $enum));
			}
		}

		return true;
	}

	/**
	 * 
	 * @param type $parameter
	 * @param type $parameterName
	 * @param boolean $required
	 * @return boolean
	 * @throws InvalidParameterException
	 */
	private static function throwIfNotPresent($parameter, $parameterName, $required = true) {
		$isPresent = !is_null($parameter);

		if ($required && !$isPresent) {
			throw new InvalidParameterException(Validators::IS_EMPTY, $parameterName);
		}

		return $isPresent;
	}

}
