<?php

/**
 *  UserController
 *
 * @author joemmanuel
 */
require_once 'SessionController.php';

class UserController extends Controller {

	public static $sendEmailOnVerify = true;
	public static $redirectOnVerify = true;

	/**
	 * Entry point for Create a User API
	 * 
	 * @param Request $r
	 * @return array
	 * @throws InvalidDatabaseOperationException
	 * @throws DuplicatedEntryInDatabaseException
	 */
	public static function apiCreate(Request $r) {

		// Validate request
		Validators::isValidUsername($r["username"], "username");		
		
		Validators::isEmail($r["email"], "email");

		// Check password
		$hashedPassword = NULL;
		if (!isset($r["ignore_password"])) {
			SecurityTools::testStrongPassword($r["password"]);
			$hashedPassword = SecurityTools::hashString($r["password"]);
		}

		// Does user or email already exists?
		try {
			$user = UsersDAO::FindByUsername($r["username"]);
			$userByEmail = UsersDAO::FindByEmail($r["email"]);
		} catch (Exception $e) {
			throw new InvalidDatabaseOperationException($e);
		}

		if (!is_null($userByEmail)) {
			throw new DuplicatedEntryInDatabaseException("email already exists");
		}

		if (!is_null($user)) {
			throw new DuplicatedEntryInDatabaseException("Username already exists.");
		}

		// Prepare DAOs
		$user_data = array(
			"username" => $r["username"],
			"password" => $hashedPassword,
			"solved" => 0,
			"submissions" => 0,
			"verified" => 0,
			"verification_id" => self::randomString(50),
		);
		if (isset($r['name'])) {
			$user_data['name'] = $r['name'];
		}
		if (isset($r['facebook_user_id'])) {
			$user_data['facebook_user_id'] = $r['facebook_user_id'];
		}
		$user = new Users($user_data);

		$email = new Emails(array(
					"email" => $r["email"],
				));

		// Save objects into DB
		try {
			DAO::transBegin();

			UsersDAO::save($user);

			$email->setUserId($user->getUserId());
			EmailsDAO::save($email);

			$user->setMainEmailId($email->getEmailId());
			UsersDAO::save($user);

			DAO::transEnd();
		} catch (Exception $e) {
			DAO::transRollback();
			throw new InvalidDatabaseOperationException($e);
		}

		self::$log->info("User " . $user->getUsername() . " created, sending verification mail");

		$r["user"] = $user;
		self::sendVerificationEmail($r);

		self::registerToMailchimp($r);
		
		return array(
			"status" => "ok",
			"user_id" => $user->getUserId()
		);
	}
	
	
	/**
	 * Registers a user to Mailchimp
	 * 
	 * @param Request r
	 */
	private static function registerToMailchimp(Request $r) {			
		
		if (OMEGAUP_EMAIL_MAILCHIMP_ENABLE === true) {
		
			self::$log->info("Adding user to Mailchimp.");

			$MailChimp = new MailChimp(OMEGAUP_EMAIL_MAILCHIMP_API_KEY);
			$result = $MailChimp->call('lists/subscribe', array(
					'id'                => OMEGAUP_EMAIL_MAILCHIMP_LIST_ID,
					'email'             => array('email'=> $r["email"]),
					'merge_vars'        => array('FNAME'=>$r["user"]->getUsername()),
					'double_optin'      => false,
					'update_existing'   => true,
					'replace_interests' => false,
					'send_welcome'      => false,
				));						
			
			if (array_key_exists("status", $result) && $result["status"] == "error") {
				self::$log->error("Mailchimp error result: " . implode(" | ", $result));
			} else {
				self::$log->info("Mailchimp success result: " . implode(" | ", $result));
			}				
		}
	}
	

	/**
	 *
	 * Description:
	 *     Tests a if a password is valid for a given user.
	 *
	 * @param user_id
	 * @param email
	 * @param username
	 * @param password
	 *
	 * */
	public function TestPassword(Request $r) {
		$user_id = $email = $username = $password = null;

		if (isset($r["user_id"])) {
			$user_id = $r["user_id"];
		}

		if (isset($r["email"])) {
			$email = $r["email"];
		}

		if (isset($r["username"])) {
			$username = $r["username"];
		}

		if (isset($r["password"])) {
			$password = $r["password"];
		}

		if (is_null($user_id) && is_null($email) && is_null($username)) {
			throw new Exception("You must provide either one of the following: user_id, email or username");
		}

		$vo_UserToTest = null;

		//find this user
		if (!is_null($user_id)) {
			$vo_UserToTest = UsersDAO::getByPK($user_id);
		} else if (!is_null($email)) {
			$vo_UserToTest = $this->FindByEmail();
		} else {
			$vo_UserToTest = $this->FindByUserName();
		}

		if (is_null($vo_UserToTest)) {
			//user does not even exist
			return false;
		}

		$newPasswordCheck = SecurityTools::compareHashedStrings(
						$password, $vo_UserToTest->getPassword());

		// We are OK
		if ($newPasswordCheck === true) {
			return true;
		}

		// It might be an old password
		if (strcmp($vo_UserToTest->getPassword(), md5($password)) === 0) {
			try {
				// It is an old password, need to update
				$vo_UserToTest->setPassword(SecurityTools::hashString($password));
				UsersDAO::save($vo_UserToTest);
			} catch (Exception $e) {
				// We did our best effort, log that user update failed
				self::$log->warn("Failed to update user password!!");
			}

			return true;
		} else {
			return false;
		}
	}

	/**
	 * Send the mail with verification link to the user in the Request
	 * 
	 * @param Request $r
	 * @throws InvalidDatabaseOperationException
	 * @throws EmailVerificationSendException
	 */
	private static function sendVerificationEmail(Request $r) {
		if (!OMEGAUP_EMAIL_SEND_EMAILS) return;

		try {
			$email = EmailsDAO::getByPK($r["user"]->getMainEmailId());
		} catch (Exception $e) {
			throw new InvalidDatabaseOperationException($e);
		}

		self::$log->info("Sending email to user.");
		if (self::$sendEmailOnVerify) {
			$mail = new PHPMailer();
			$mail->IsSMTP();
			$mail->Host = OMEGAUP_EMAIL_SMTP_HOST;
			$mail->SMTPAuth = true;
			$mail->Password = OMEGAUP_EMAIL_SMTP_PASSWORD;
			$mail->From = OMEGAUP_EMAIL_SMTP_FROM;
			$mail->Port = 465;
			$mail->SMTPSecure = 'ssl';
			$mail->Username = OMEGAUP_EMAIL_SMTP_FROM;

			$mail->FromName = OMEGAUP_EMAIL_SMTP_FROM;
			$mail->AddAddress($email->getEmail());
			$mail->isHTML(true);
			$mail->Subject = "Bienvenido a Omegaup!";
			$mail->Body = 'Bienvenido a Omegaup! Por favor ingresa a la siguiente dirección para hacer login y verificar tu email: <a href="https://omegaup.com/api/user/verifyemail/id/' . $r["user"]->getVerificationId() . '"> https://omegaup.com/api/user/verifyemail/id/' . $r["user"]->getVerificationId() . '</a>';

			if (!$mail->Send()) {
				self::$log->error("Failed to send mail: " . $mail->ErrorInfo);
				throw new EmailVerificationSendException();
			}
		}
	}

	/**
	 * Check if email of user in request has been verified
	 * 
	 * @param Request $r
	 * @throws EmailNotVerifiedException
	 */
	public static function checkEmailVerification(Request $r) {

		if (OMEGAUP_FORCE_EMAIL_VERIFICATION) {
			// Check if he has been verified				
			if ($r["user"]->getVerified() == '0') {
				self::$log->info("User not verified.");

				if ($r["user"]->getVerificationId() == null) {

					self::$log->info("User does not have verification id. Generating.");

					try {
						$r["user"]->setVerificationId(self::randomString(50));
						UsersDAO::save($r["user"]);
					} catch (Exception $e) {
						// best effort, eat exception
					}

					self::sendVerificationEmail($r);
				}

				throw new EmailNotVerifiedException();
			} else {
				self::$log->info("User already verified.");
			}
		}
	}

	/**
	 * Exposes API /user/login
	 * Expects in request:
	 * user
	 * password 
	 *
	 * 
	 * @param Request $r
	 */
	public static function apiLogin(Request $r) {

		// Create a SessionController to perform login
		$sessionController = new SessionController();

		// Require the auth_token back
		$r["returnAuthToken"] = true;

		// Get auth_token
		$auth_token = $sessionController->NativeLogin($r);

		// If user was correctly logged in
		if ($auth_token !== false) {
			return array(
				"status" => "ok",
				"auth_token" => $auth_token);
		} else {
			throw new InvalidCredentialsException();
		}
	}

	/**
	 * Changes the password of a user
	 * 
	 * @param Request $rﬁ
	 * @return array
	 * @throws ForbiddenAccessException
	 */
	public static function apiChangePassword(Request $r) {

		self::authenticateRequest($r);

		$hashedPassword = NULL;
		if (Authorization::IsSystemAdmin($r["current_user_id"]) && isset($r["username"])) {
			// System admin can force reset passwords for any user
			Validators::isStringNonEmpty($r["username"], "username");
			
			try {
				$user = UsersDAO::FindByUsername($r["username"]);

				if (is_null($user)) {
					throw new NotFoundException("User does not exists");
				}
			} catch (Exception $e) {
				throw new InvalidDatabaseOperationException($e);
			}

			if (isset($r['password']) && $r['password'] != '') {
				SecurityTools::testStrongPassword($r["password"]);
				$hashedPassword = SecurityTools::hashString($r["password"]);
			}
		} else {
			$user = $r["current_user"];

			if ($user->getPassword() != NULL) {
				// Check the old password
				Validators::isStringNonEmpty($r["old_password"], "old_password");

				$old_password_valid = SecurityTools::compareHashedStrings(
								$r["old_password"], $user->getPassword());

				if ($old_password_valid === false) {
					throw new InvalidParameterException("old_password" . Validators::IS_INVALID);
				}
			}

			SecurityTools::testStrongPassword($r["password"]);
			$hashedPassword = SecurityTools::hashString($r["password"]);
		}

		$user->setPassword($hashedPassword);
		UsersDAO::save($user);

		return array("status" => "ok");
	}

	/**
	 * Verifies the user given its verification id
	 * 
	 * @param Request $r
	 * @return type
	 * @throws ApiException
	 * @throws InvalidDatabaseOperationException
	 * @throws NotFoundException
	 */
	public static function apiVerifyEmail(Request $r) {

		$user = null;
		
		// Admin can override verification by sending username
		if (isset($r["usernameOrEmail"])) {
			self::authenticateRequest($r);
			
			if (!Authorization::IsSystemAdmin($r["current_user_id"])) {
				throw new ForbiddenAccessException();
			}

			self::$log->info("Admin verifiying user..." . $r["usernameOrEmail"]);
			
			Validators::isStringNonEmpty($r["usernameOrEmail"], "usernameOrEmail");
			
			$user = self::resolveUser($r["usernameOrEmail"]);
			
			self::$redirectOnVerify = false;
			
		} else {
			// Normal user verification path
			Validators::isStringNonEmpty($r["id"], "id");
			
			try {
				$users = UsersDAO::search(new Users(array(
									"verification_id" => $r["id"]
								)));

				$user = (is_array($users) && count($users) > 0) ? $users[0] : null;
			} catch (Exception $e) {
				throw new InvalidDatabaseOperationException($e);
			}
		}			
		
		if (is_null($user)) {
			throw new NotFoundException("Verification id is invalid.");
		}
				
		try {
			$user->setVerified(1);
			UsersDAO::save($user);
		} catch (Exception $e) {
			throw new InvalidDatabaseOperationException($e);
		}

		self::$log->info("User verification complete.");

		if (self::$redirectOnVerify) {
			die(header('Location: /login.php'));
		}
		return array("status" => "ok");
	}

	/**
	 * Given a username or a email, returns the user object
	 * 
	 * @param type $userOrEmail
	 * @return User
	 * @throws ApiException
	 * @throws InvalidDatabaseOperationException
	 * @throws InvalidParameterException
	 */
	public static function resolveUser($userOrEmail) {

		Validators::isStringNonEmpty($userOrEmail, "Username or email not found");

		$user = null;

		try {
			if (!is_null($user = UsersDAO::FindByEmail($userOrEmail))
					|| !is_null($user = UsersDAO::FindByUsername($userOrEmail))) {
				return $user;
			} else {
				throw new NotFoundException("Username or email not found");
			}
		} catch (ApiException $apiException) {
			throw $apiException;
		} catch (Exception $e) {
			throw new InvalidDatabaseOperationException($e);
		}

		return $user;
	}
	
	/**
	 * Resets the password of the OMI user and adds the user to the private 
	 * contest.
	 * If the user does not exists, we create him.
	 * 
	 * @param Request $r
	 * @param string $username
	 * @param string $password
	 */
	private static function omiPrepareUser(Request $r, $username, $password) {

		try {
			$user = UsersDAO::FindByUsername($username);
		} catch (Exception $e) {
			throw new InvalidDatabaseOperationException($e);
		}

		if (is_null($user)) {
			self::$log->info("Creating user: " . $username);
			$createRequest = new Request(array(
						"username" => $username,
						"password" => $password,
						"email" => $username . "@omi.com",
					));

			UserController::$sendEmailOnVerify = false;
			self::apiCreate($createRequest);
		} else {
			$resetRequest = new Request();
			$resetRequest["auth_token"] = $r["auth_token"];
			$resetRequest["username"] = $username;
			$resetRequest["password"] = $password;
			self::apiChangePassword($resetRequest);
		}		
	}

	/**
	 * 
	 * @param Request $r
	 * @return array
	 * @throws ForbiddenAccessException
	 */
	public static function apiGenerateOmiUsers(Request $r) {

		self::authenticateRequest($r);		

		$response = array();

		if ($r["contest_type"] == "OMI") {
			
			if (!Authorization::IsSystemAdmin($r["current_user_id"])) {
				throw new ForbiddenAccessException();
			}
			
			// Arreglo de estados de MX
			$keys = array(
				"AGU" => 4,
				"BCN" => 4,
				"BCS" => 4,
				"CAM" => 4,
				"COA" => 4,
				"COL" => 4,
				"CHP" => 4,
				"CHH" => 4,
				"DIF" => 4,
				"DUR" => 4,
				"GUA" => 4,
				"GRO" => 4,
				"HID" => 4,
				"JAL" => 4,
				"MEX" => 8,
				"MIC" => 4,
				"MOR" => 4,
				"NAY" => 4,
				"NLE" => 4,
				"OAX" => 4,
				"PUE" => 4,
				"QUE" => 4,
				"ROO" => 4,
				"SLP" => 4,
				"SIN" => 4,
				"SON" => 4,
				"TAB" => 4,
				"TAM" => 4,
				"TLA" => 4,
				"VER" => 4,
				"YUC" => 4,
				"ZAC" => 4,
			);
		} else if ($r["contest_type"] == "ORIG") {
			
			if (!($r["current_user"]->getUsername() == "kuko.coder" || Authorization::IsSystemAdmin($r["current_user_id"]))) {
				throw new ForbiddenAccessException();
			}
			
			$keys = array (				
				"GTO-SFR" => 17,
				"GTO-URI" => 25,
				"GTO-IRA" => 19,
				"GTO-LEO" => 22,
				"GTO-VDS" => 17,
				"GTO-GTO" => 13,
				"GTO-CEL" => 14,
				"GTO-SIL" => 19,
				"GTO-PEN" => 19,
			);
			
		} else {
			throw new InvalidParameterException("Invalid contest_type");
		}
			
		foreach ($keys as $k => $n) {
						
			for ($i = 1; $i <= $n; $i++) {

				$username = $k . "-" . $i;
				$password = self::randomString(8);

				self::omiPrepareUser($r, $username, $password);
		
				// Add user to contest if needed
				if (!is_null($r["contest_alias"])) {
					$addUserRequest = new Request();
					$addUserRequest["auth_token"] = $r["auth_token"];
					$addUserRequest["usernameOrEmail"] = $username;
					$addUserRequest["contest_alias"] = $r["contest_alias"];
					ContestController::apiAddUser($addUserRequest);
				}
				
				$response[$username] = $password;			
			}
		}

		return $response;
	}

	/**
	 * Get list of contests where the user has admin priviledges
	 * 
	 * @param Request $r
	 * @return string
	 * @throws InvalidDatabaseOperationException
	 */
	public static function apiContests(Request $r) {

		self::authenticateRequest($r);

		$response = array();
		$response["contests"] = array();

		try {

			$contest_director_key = new Contests(array(
						"director_id" => $r["current_user_id"]
					));
			$contests_director = ContestsDAO::search($contest_director_key);

			foreach ($contests_director as $contest) {
				$response["contests"][] = $contest->asArray();
			}

			$contest_admin_key = new UserRoles(array(
						"user_id" => $r["current_user_id"],
						"role_id" => CONTEST_ADMIN_ROLE,
					));
			$contests_admin = UserRolesDAO::search($contest_admin_key);

			foreach ($contests_admin as $contest_key) {
				$contest = ContestsDAO::getByPK($contest_key->getContestId());

				if (is_null($contest)) {
					self::$log->error("UserRoles has a invalid contest: {$contest->getContestId()}");
					continue;
				}

				$response["contests"][] = $contest->asArray();
			}

			usort($response["contests"], function ($a, $b) {
						return ($a["contest_id"] > $b["contest_id"]) ? -1 : 1;
					});
		} catch (Exception $e) {
			throw new InvalidDatabaseOperationException($e);
		}

		$response["status"] = "ok";
		return $response;
	}

	/**
	 * Get list of my editable problems
	 * 
	 * @param Request $r
	 * @return string
	 * @throws InvalidDatabaseOperationException
	 */
	public static function apiProblems(Request $r) {

		self::authenticateRequest($r);

		$response = array();
		$response["problems"] = array();

		try {
			$problems_key = new Problems(array(
						"author_id" => $r["current_user_id"]
					));

			$problems = ProblemsDAO::search($problems_key);

			foreach ($problems as $problem) {
				$response["problems"][] = $problem->asArray();
			}

			usort($response["problems"], function ($a, $b) {
						return ($a["problem_id"] > $b["problem_id"]) ? -1 : 1;
					});
		} catch (Exception $e) {
			throw new InvalidDatabaseOperationException($e);
		}

		$response["status"] = "ok";
		return $response;
	}
		
	
	/**
	 * Returns the prefered language as a string (en,es,fra) of the user given
	 * If no user is give, language is retrived from the browser.
	 * 
	 * @param Users $user
	 * @return String
	 */
	public static function getPreferredLanguage(Request $r = NULL) {

		$found = FALSE;
		$result = "es";

		// for quick debugging 
		if (isset($_GET["lang"])) {
			$result = $_GET["lang"];
			$found = TRUE;
		}
		if (!$found) {
			$user = self::resolveTargetUser($r);
			if (!is_null($user) && !is_null($user->getLanguageId())) {
					$result = LanguagesDAO::getByPK( $user->getLanguageId() );
					if (is_null($result)) {
						self::$log->warn("Invalid language id for user");
					} else {
						$result = $result->getName();
						$found = true;
					}
				}
		}

		if (!$found) {
			$langs = array();

			if (isset($_SERVER['HTTP_ACCEPT_LANGUAGE'])) {
				// break up string into pieces (languages and q factors)
				preg_match_all('/([a-z]{1,8}(-[a-z]{1,8})?)\s*(;\s*q\s*=\s*(1|0\.[0-9]+))?/i', $_SERVER['HTTP_ACCEPT_LANGUAGE'], $lang_parse);

				if (count($lang_parse[1])) {
					// create a list like "en" => 0.8
					$langs = array_combine($lang_parse[1], $lang_parse[4]);

					// set default to 1 for any without q factor
					foreach ($langs as $lang => $val) {
						if ($val === '') $langs[$lang] = 1;
					}

					// sort list based on value	
					arsort($langs, SORT_NUMERIC);
				}
			}

			foreach ($langs as $langCode => $langWeight) {
				switch (substr($langCode, 0, 2)) {
					case "en":
						$result = "en";
						$found = true;
					break;

					case "es":
						$result = "es";
						$found = true;
					break;
				}
			}
		}

		switch ($result) {
			case "en":
			case "en-us":
				$result = "en";
				break;

			case "es":
			case "es-mx":
				$result = "es";
				break;

			case "ps":
			case "ps-ps":
				$result = "hacker-boy";
				break;
		}
		return $result;
	}


	/**
	 * Returns the profile of the user given
	 * 
	 * @param Users $user
	 * @return array
	 * @throws InvalidDatabaseOperationException
	 */
	public static function getProfile(Users $user) {
		
		$response = array();
		$response["userinfo"] = array();
		$response["problems"] = array();

		$response["userinfo"]["username"] = $user->getUsername();		
		$response["userinfo"]["name"] = $user->getName();
		$response["userinfo"]["solved"] = $user->getSolved();
		$response["userinfo"]["submissions"] = $user->getSubmissions();
		$response["userinfo"]["birth_date"] = is_null($user->getBirthDate()) ? null : strtotime($user->getBirthDate());
		$response["userinfo"]["graduation_date"] = is_null($user->getGraduationDate()) ? null : strtotime($user->getGraduationDate());
		$response["userinfo"]["scholar_degree"] = $user->getScholarDegree();

		if (!is_null($user->getLanguageId())) {
			$query = LanguagesDAO::getByPK($user->getLanguageId());	
			if (!is_null($query)) {
				$response["userinfo"]["locale"] = $query->getName();
			}
		}

		try {
			$response["userinfo"]["email"] = EmailsDAO::getByPK($user->getMainEmailId())->getEmail();

			$country = CountriesDAO::getByPK($user->getCountryId());
			$response["userinfo"]["country"] = is_null($country) ? null : $country->getName();
			$response["userinfo"]["country_id"] = $user->getCountryId();

			$state = StatesDAO::getByPK($user->getStateId());
			$response["userinfo"]["state"] = is_null($state) ? null : $state->getName();
			$response["userinfo"]["state_id"] = $user->getStateId();

			$school = SchoolsDAO::getByPK($user->getSchoolId());
			$response["userinfo"]["school_id"] = $user->getSchoolId();
			$response["userinfo"]["school"] = is_null($school) ? null : $school->getName();
		} catch (Exception $e) {
			throw new InvalidDatabaseOperationException($e);
		}

		$response["userinfo"]["gravatar_92"] = 'https://secure.gravatar.com/avatar/' . md5($response["userinfo"]["email"]) . '?s=92';

		return $response;
	}

	
	/**
	 * Get general user info
	 * 
	 * @param Request $r
	 * @return response array with user info
	 * @throws InvalidDatabaseOperationException
	 */
	public static function apiProfile(Request $r) {

		self::authenticateRequest($r);
				
		$r["user"] = self::resolveTargetUser($r);
		
		Cache::getFromCacheOrSet(Cache::USER_PROFILE, $r["user"]->getUsername(), $r, function(Request $r) { 
										
			return UserController::getProfile($r["user"]);
			
		}, $response);
		
		// Do not leak plain emails in case the request is for a profile other than 
		// the logged user's one
		if ($r["user"]->getUserId() !== $r['current_user_id']) {
			unset($response["userinfo"]["email"]);
		}
		
		$response["status"] = "ok";
		return $response;
	}
	
	/**
	 * Get coder of the month by trying to find it in the table using the first 
	 * day of the current month. If there's no coder of the month for the given 
	 * date, calculate it and save it.
	 * 
	 * @param Request $r
	 * @return array
	 * @throws InvalidDatabaseOperationException
	 */
	public static function apiCoderOfTheMonth(Request $r) {				

		// Get first day of the current month
		$firstDay = date('Y-m-01');
		
		try {
			
			$coderOfTheMonth = null;
			
			$codersOfTheMonth = CoderOfTheMonthDAO::search(new CoderOfTheMonth(array("time" => $firstDay)));
			if (count($codersOfTheMonth) > 0) {
				$coderOfTheMonth = $codersOfTheMonth[0];
			}
								
			if (is_null($coderOfTheMonth)) {				
				
				// Generate the coder
				$retArray = CoderOfTheMonthDAO::calculateCoderOfTheMonth($firstDay);
				if ($retArray == null) {
					throw new InvalidParameterException("No coders.");
				}

				$user = $retArray["user"];
				
				// Save it
				$c = new CoderOfTheMonth(array(
					"coder_of_the_month_id" => $user->getUserId(),
					"time" => $firstDay,
					
				));
				CoderOfTheMonthDAO::save($c);
				
				
			} else {
				
				// Grab the user info
				$user = UsersDAO::getByPK($coderOfTheMonth->getCoderOfTheMonthId());
			}			
							
		} catch (Exception $e) {
			throw new InvalidDatabaseOperationException($e);
		}

		// Get the profile of the coder of the month
		$response = self::getProfile($user);		
		
		$response["status"] = "ok";		
		return $response;
	}
	
	/**
	 * Returns the list of coders of the month
	 * 
	 * @param Request $r
	 */
	public static function apiCoderOfTheMonthList(Request $r) {
	
		self::authenticateRequest($r);
				
		$response = array();
		$response["coders"] = array();
		try {
			$coders = CoderOfTheMonthDAO::getAll(null,null,"time", "DESC");
			
			foreach ($coders as $c) {
				$user = UsersDAO::getByPK($c->getCoderOfTheMonthId());
				$email = EmailsDAO::getByPK($user->getMainEmailId());
				$response["coders"][] = array(
					"username" => $user->getUsername(),
					"gravatar_32" => "https://secure.gravatar.com/avatar/" . md5($email->getEmail()) . "?s=32",
					"date" => $c->getTime()
				);
			}
			
		} catch (Exception $ex) {
			throw new InvalidDatabaseOperationException($e);
		}
		
		$response["status"] = "ok";
		return $response;
	}
	

	/**
	 * Get Contests which a certain user has participated in
	 * 
	 * @param Request $r
	 * @return Contests array
	 * @throws InvalidDatabaseOperationException
	 */
	public static function apiContestStats(Request $r) {

		self::authenticateRequest($r);

		$response = array();
		$response["contests"] = array();

		$user = self::resolveTargetUser($r);
		
		$contest_user_key = new ContestsUsers();
		$contest_user_key->setUserId($user->getUserId());

		try {
			$db_results = ContestsUsersDAO::search($contest_user_key, "contest_id", 'DESC');
		} catch (Exception $e) {
			throw new InvalidDatabaseOperationException($e);
		}

		$contests = array();
		foreach ($db_results as $result) {
			
			// Get contest data
			$contest_id = $result->getContestId();
			$contest = ContestsDAO::getByPK($contest_id);
			
			// Get user ranking
			$scoreboardR = new Request(array("auth_token" => $r["auth_token"], "contest_alias" => $contest->getAlias()));
			$scoreboardResponse = ContestController::apiScoreboard($scoreboardR);
			
			// Grab the place of the current user in the given contest	
			$contests[$contest->getAlias()]["place"]  = null;
			foreach($scoreboardResponse["ranking"] as $userData) {								
				if ($userData["username"] == $user->getUsername()) {
					$contests[$contest->getAlias()]["place"] = $userData["place"];
					break;
				}				
			}
			
			$contest->toUnixTime();
			$contests[$contest->getAlias()]["data"] = $contest->asArray();									
		}

		$response["contests"] = $contests;
		$response["status"] = "ok";
		return $response;
	}

	/**
	 * Get Problems solved by user
	 * 
	 * @param Request $r
	 * @return Problems array
	 * @throws InvalidDatabaseOperationException
	 */
	public static function apiProblemsSolved(Request $r) {

		self::authenticateRequest($r);

		$response = array();
		$response["problems"] = array();
		
		$user = self::resolveTargetUser($r);
		
		try {
			$db_results = ProblemsDAO::getProblemsSolved($user->getUserId());
		} catch (Exception $e) {
			throw new InvalidDatabaseOperationException($e);
		}
		
		if (!is_null($db_results)) {
			$relevant_columns = array("title", "alias", "submissions", "accepted");
			foreach($db_results as $problem) {
				if ($problem->getPublic() == 1) {
					array_push($response["problems"], $problem->asFilteredArray($relevant_columns));
				}
			}
		}

		$response["status"] = "ok";
		return $response;
	}

	/**
	 * Gets a list of users 
	 * 
	 * @param Request $r
	 */
	public static function apiList(Request $r) {

		self::authenticateRequest($r);

		$param = "";
		if (!is_null($r["term"])) {
			$param = "term";
		} else if (!is_null($r["query"])) {
			$param = "query";
		} else {
			throw new InvalidParameterException("query".Validators::IS_EMPTY);
		}
		
		try {
			$users = UsersDAO::FindByUsernameOrName($r[$param]);
		} catch (Exception $e) {
			throw new InvalidDatabaseOperationException($e);
		}

		$response = array();
		foreach ($users as $user) {
			$entry = array("label" => $user->getUsername(), "value" => $user->getUsername());
			array_push($response, $entry);
		}

		return $response;
	}
	
	/**
	 * Get stats 
	 * 
	 * @param Request $r
	 */
	public static function apiStats(Request $r) {
		
		self::authenticateRequest($r);
					
		$user = self::resolveTargetUser($r);
		
		try {
			
			$totalRunsCount = RunsDAO::CountTotalRunsOfUser($user->getUserId());
			
			// List of veredicts			
			$veredict_counts = array();
			
			foreach (self::$veredicts as $veredict) {
				$veredict_counts[$veredict] = RunsDAO::CountTotalRunsOfUserByVeredict($user->getUserId(), $veredict);
			}			
			
		} catch (Exception $e) {
			throw new InvalidDatabaseOperationException($e);
		}
		
		return array(
			"veredict_counts" => $veredict_counts,
			"total_runs" => $totalRunsCount,
			"status" => "ok"
		);
	}

	/**
	 * Update basic user profile info when logged with fb/gool
	 * 
	 * @param Request $r
	 * @return array
	 * @throws InvalidDatabaseOperationException
	 * @throws InvalidParameterException
	 */
	public static function apiUpdateBasicInfo(Request $r) {
		self::authenticateRequest($r);

		//Buscar que el nuevo username no este ocupado si es que selecciono uno nuevo
		if ($r["username"] != $r["current_user"]->getUsername()) {
			$testu = UsersDAO::FindByUsername($r["username"]);

			if (!is_null($testu)) {
				throw new InvalidParameterException("Este nombre de usuario ya esta tomado.");
			}
		}

		SecurityTools::testStrongPassword($r["password"]);
		$hashedPassword = SecurityTools::hashString($r["password"]);
		$r["current_user"]->setPassword($hashedPassword);

		$r["current_user"]->setUsername($r["username"]);
		UsersDAO::save($r["current_user"]);

		return array("status" => "ok");
	}
	
	/**
	 * Update user profile
	 * 
	 * @param Request $r
	 * @return array
	 * @throws InvalidDatabaseOperationException
	 * @throws InvalidParameterException
	 */
	public static function apiUpdate(Request $r) {
		
		self::authenticateRequest($r);
		
		Validators::isStringNonEmpty($r["name"], "name", false);
		Validators::isStringNonEmpty($r["country_id"], "country_id", false);
		
		if (!is_null($r["country_id"])) {
			try {
				$r["country"] = CountriesDAO::getByPK($r["country_id"]);	
			} catch(Exception $e) {
				throw new InvalidDatabaseOperationException($e);
			}
			
			if (is_null($r["country"])) {
				throw new InvalidParameterException("Country not found");
			}
		}
		
		if ($r["state_id"] === 'null') {
			$r["state_id"] = null;
		}
		
		Validators::isNumber($r["state_id"], "state_id", false);
		
		if (!is_null($r["state_id"])) {
			try {
				$r["state"] = StatesDAO::getByPK($r["state_id"]);
			} catch (Exception $e) { 
				throw new InvalidDatabaseOperationException($e);
			}
			
			if (is_null($r["state"])) {
				throw new InvalidParameterException("State not found");
			}
		}
		
		if (!is_null($r["school_id"])) {
			
			if ($r["school_id"] == -1) {
				// UI sets -1 if school does not exists.
				try {
					$schoolR = new Request(array("name" => $r["school_name"], "state_id" => $r["state_id"], "auth_token" => $r["auth_token"]));
					$response = SchoolController::apiCreate($schoolR);
					$r["school_id"] = $response["school_id"];
				} catch (Exception $e) {
					throw new InvalidParameterException("School creation failed.", $e);
				}
			} else if ($r["school_id"] == "") {
				$r["school_id"] = null;
			} else {			
				try {
					$r["school"] = SchoolsDAO::getByPK($r["school_id"]);	
				} catch(Exception $e) {
					throw new InvalidDatabaseOperationException($e);
				}

				if (is_null($r["school"])) {
					throw new InvalidParameterException("School not found");
				}
			}
		}
		
		Validators::isStringNonEmpty($r["scholar_degree"], "scholar_degree", false);
		Validators::isDate($r["graduation_date"], "graduation_date", false);
		Validators::isDate($r["birth_date"], "birth_date", false);
		
		if (!is_null($r["locale"])) {
			// find language in Language
			$query = LanguagesDAO::search(new Languages( array( "name" => $r["locale"])));
			if (sizeof($query) == 1) {
				$r["current_user"]->setLanguageId($query[0]->getLanguageId());
			}
		}

		$valueProperties = array(
			"name",
			"country_id",
			"state_id",
			"scholar_degree",
			"school_id",
			"graduation_date" => array("transform" => function($value) { return gmdate('Y-m-d', $value); }),
			"birth_date"			=> array("transform" => function($value) { return gmdate('Y-m-d', $value); }),
		);
		self::updateValueProperties($r, $r["current_user"], $valueProperties);
		
		try {
			UsersDAO::save($r["current_user"]);			
		} catch(Exception $e) {
			throw new InvalidDatabaseOperationException($e);
		}
		
		// Expire profile cache
		Cache::deleteFromCache(Cache::USER_PROFILE, $r["current_user"]->getUsername());		

		return array("status" => "ok");
	}
	
	/**
	 * Gets the top N users who have solved more problems
	 * 
	 * @param Request $r
	 * @return string
	 * @throws InvalidDatabaseOperationException
	 */
	public static function apiRankByProblemsSolved(Request $r) {
		
		self::authenticateRequest($r);
		
		Validators::isNumber($r["offset"], "offset", false);
		Validators::isNumber($r["rowcount"], "rowcount", false);

		// Defaults for offset and rowcount
		if (!isset($r["offset"])) {
			$r["offset"] = 0;
		}
		if (!isset($r["rowcount"])) {
			$r["rowcount"] = 100;
		}
				
		return self::getRankByProblemsSolved($r);
	}
	
	/**
	 * Get rank by problems solved logic. It has its own func so 
	 * it can be accesed internally without authentication
	 * 
	 * @param Request $r
	 * @throws InvalidDatabaseOperationException
	 */
	public static function getRankByProblemsSolved(Request $r) {
		
		$rankCacheName =  $r["offset"] . '-' . $r["rowcount"];
		
		$cacheUsed = Cache::getFromCacheOrSet(Cache::PROBLEMS_SOLVED_RANK, $rankCacheName, $r, function(Request $r) {
		
			$response = array();
			$response["rank"] = array();
			try {
				$db_results = UsersDAO::GetRankByProblemsSolved($r["rowcount"], $r["offset"]);
			} catch (Exception $e) {
				throw new InvalidDatabaseOperationException($e);
			}

			if (!is_null($db_results)) {
				foreach ($db_results as $userEntry) {
					$user = $userEntry["user"];
					array_push($response["rank"], array("username" => $user->getUsername(), "name" => $user->getName(), "problems_solved" => $userEntry["problems_solved"]));
				}
			}
			
			return $response;			
		}, $response); 
		
		// If cache was set, we need to maintain a list of different ranks in the cache
		// (A different rank means different offset and rowcount params
		if ($cacheUsed === false) {
			self::setProblemsSolvedRankCacheList($rankCacheName);
		}		
		
		$response["status"] = "ok";
		return $response;
	}
	
	/**
	 * Adds the rank name to a list of stored ranks so we know we ranks to delete
	 * after
	 * 
	 * @param string $rankCacheName
	 */
	private static function setProblemsSolvedRankCacheList($rankCacheName) {
		
		// Save the instance of the rankName in a key/value array, so we know all ranks to 
		// expire
		$rankCacheList = new Cache(Cache::PROBLEMS_SOLVED_RANK_LIST, "");
		$ranksList = $rankCacheList->get();

		if (is_null($ranksList)) {
			// Simulating a set
			$ranksList = array($rankCacheName => 1);				
		} else {
			$ranksList[$rankCacheName] = 1;
		}

		$rankCacheList->set($ranksList, 0);
	}
	
	/**
	 * Expires the known ranks
	 * @TODO: This should be called only in the grader->frontend callback and only IFF 
	 * veredict = AC (and not test run)
	 */
	public static function deleteProblemsSolvedRankCacheList() {
		
		$rankCacheList = new Cache(Cache::PROBLEMS_SOLVED_RANK_LIST, "");
		$ranksList = $rankCacheList->get();
		
		if (!is_null($ranksList)) {
			
			$rankCacheList->delete();
			
			foreach($ranksList as $key => $value) {
				Cache::deleteFromCache(Cache::PROBLEMS_SOLVED_RANK, $key);				
			}
		}
	}
	
	/**
	 * Updates the main email of the current user
	 * 
	 * @param Request $r
	 */
	public static function apiUpdateMainEmail(Request $r) {
		
		self::authenticateRequest($r);
		
		Validators::isEmail($r["email"], "email");
				
		try {
			// Update email
			$email = EmailsDAO::getByPK($r["current_user"]->getMainEmailId());			
			$email->setEmail($r["email"]);			
			EmailsDAO::save($email);
			
			// Add verification_id if not there
			if ($r["current_user"]->getVerified() == '0') {
				self::$log->info("User not verified.");

				if ($r["current_user"]->getVerificationId() == null) {

					self::$log->info("User does not have verification id. Generating.");

					try {
						$r["current_user"]->setVerificationId(self::randomString(50));
						UsersDAO::save($r["current_user"]);
					} catch (Exception $e) {
						// best effort, eat exception
					}					
				}				
			} 
			
		} catch (Exception $e) {
			// If duplicate in DB
			if (strpos($e->getMessage(), "1062") !== FALSE) {
				throw new DuplicatedEntryInDatabaseException("El email seleccionado ya está ocupado. Intenta con otro email válido.", $e);
			} else {
				throw new InvalidDatabaseOperationException($e);
			}
		}
		
		// Delete profile cache 
		Cache::deleteFromCache(Cache::USER_PROFILE, $r["current_user"]->getUsername());
		
		// Send verification email 
		$r["user"] = $r["current_user"];
		self::sendVerificationEmail($r);
				
		return array("status" => "ok");
		
	}
	
}

