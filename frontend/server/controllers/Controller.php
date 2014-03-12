<?php

/**
 * Controllers parent class
 *
 * @author joemmanuel
 */
class Controller {

	// If we turn this into protected,
	// how are we going to initialize?
	public static $log;

	/**
	 * List of veredicts
	 * 
	 * @var array 
	 */
	public static $veredicts = array("AC", "PA", "WA", "TLE", "MLE", "OLE", "RTE", "RFE", "CE", "JE", "NO-AC");
	
	/**
	 * Given the request, returns what user is performing the request by
	 * looking at the auth_token
	 * 
	 * @param Request $r
	 * @throws InvalidDatabaseOperationException
	 * @throws ForbiddenAccessException
	 */
	protected static function authenticateRequest(Request $r) {
		
		try {
			Validators::isStringNonEmpty($r["auth_token"], "auth_token");
		} catch(Exception $e) {
			throw new ForbiddenAccessException();
		}
		
		try {
			$user = AuthTokensDAO::getUserByToken($r["auth_token"]);
		} catch (Exception $e) {
			throw new InvalidDatabaseOperationException($e);
		}
	
		if (is_null($user)) {
			throw new ForbiddenAccessException();
		}
		
		$r["current_user"] = $user;
		$r["current_user_id"] = $user->getUserId();
	}
	
	/**
	 * Resolves the target user for the API. If a username is provided in
	 * the request, then we use that one. Otherwise, we use currently logged-in
	 * user.
	 * 
	 * Request must be authenticated before this function is called.
	 * 
	 * @param Request $r
	 * @return Users
	 * @throws InvalidDatabaseOperationException
	 * @throws NotFoundException
	 */
	protected static function resolveTargetUser(Request $r) {
		
		// By default use current user		
		$user = $r["current_user"];	 
		
		if (!is_null($r["username"])) {
			
			Validators::isStringNonEmpty($r["username"], "username");
			
			try {
				$user = UsersDAO::FindByUsername($r["username"]);

				if (is_null($user)) {
					throw new NotFoundException("User does not exist");
				}
			} 
			catch (ApiException $e) {
				throw $e;
			}
			catch (Exception $e) {
				throw new InvalidDatabaseOperationException($e);
			}			
		}
		
		return $user;
	}
	
	/**
	 * Retunrs a random string of size $length
	 * 
	 * @param string $length
	 * @return string
	 */
	public static function randomString($length) {
		$chars = "abcdefghjkmnpqrstuvwxyzABCDEFGHJKLMNPQRSTUVWXYZ23456789";
		$str = "";
		$size = strlen($chars);
		for ($i = 0; $i < $length; $i++) {
			$str .= $chars[rand(0, $size - 1)];
		}

		return $str;
	}

	/**
	 * Converts underscore property names into camel case method names:
	 * 'contest_id' => 'ContestId'
	 *
	 * @param string $name
	 */
	protected static function toCamelCase($name) {
		return preg_replace_callback(
			'|_(\w)|',                      // Match letters following an underscore.
			function($matches) {
				return ucfirst($matches[1]);  // Convert every matching letter to upper case.
			},
			ucfirst($name));                // Converts the first letter in the name to upper case.
	}

	/**
	 * Update properties of $object based on what is provided in $request.
	 * $properties can have 'simple' and 'complex' properties.
	 * - A simple property is just a name using underscores, and it's getter and setter methods should
	 *   be the camel case version of the property name.
	 * - An advanced property can have:
	 *   > A getter/setter base name
	 *   > A flag indicating it is important. Important properties are checked to determined if they
	 *     really changed. For example: properties that should cause a problem to be rejudged,
	 *     like time limits or memory constraints.
	 *   > A transform method that takes the new property value stored in the request and transforms
	 *     it into the proper form that should be stored in $object. For example:
	 *     function($value) { return gmdate('Y-m-d H:i:s', $value); }
	 *
	 * @param Request $request
	 * @param object $object
	 * @param array $properties
	 * @return boolean True if there were changes to any property marked as 'important'.
	 */
	protected static function updateValueProperties($request, $object, $properties) {
		$importantChange = false;
		foreach ($properties as $source => $info) {
			if (is_int($source)) {
				// Simple property:
				$source = $info;
				$info = [Controller::toCamelCase($source)];
			}
			if (is_null($request[$source])) {
				continue;
			}
			// Get the base name for the property accessors.
			if (isset($info[0]) || isset($info['accessor'])) {
				$accessor = isset($info[0]) ? $info[0] : $info['accessor'];
			} else {
				$accessor = Controller::toCamelCase($source);
			}
			// Get or calculate new value.
			$value = $request[$source];
			if (isset($info[2]) || isset($info['transform'])) {
				$transform = isset($info[2]) ? $info[2] : $info['transform'];
				$value = $transform($value);
			}
			// Important property, so check if it changes.
			if (isset($info[1]) || isset($info['important'])) {
				$important = isset($info[1]) ? $info[1] : $info['important'];
				if ($important) {
					$getter = "get" . $accessor;
					if ($value != $object->$getter()) {
						$importantChange = true;
					}
				}
			}
			$setter = "set" . $accessor;
			$object->$setter($value);
		}
		return $importantChange;
	}
}

Controller::$log = Logger::getLogger("controller");

