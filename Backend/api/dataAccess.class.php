<?php

	require_once("secret.class.php");
	require_once("signal.class.php");
	require_once("exception.class.php");

	/*
		Database interface
		
		Public interface:

		Return value is ALWAYS an object of type ISignal

		caretaker:
		careREGISTER: registers a new caretaker
		careLOGIN: caretaker login
		careGET: standard caretaker GET request
		carePOST: standard caretaker POST request
		capaGET: caretaker accessing patient data (checks relation)
		capaPOST: caretaker creating patient data (checks relation)

		patient:
		patiLINK: links caretaker and patient
		patiGET: standard patient GET request
		patiPOST: standard patient POST request

	*/

	class DataAccess
	{

		private static $ip = "localhost";
		private static $dbName = "pace2016";

		const T_PATI = 0;
		const T_CARE = 1;

		/*
			Public interface
		*/

		//Caretaker

		public static function careREGISTER($username, $password) {
			return self::run(function() use ($username, $password) {
				return DataAccess::CARE_register($username, $password);
			});
		}

		public static function careLOGIN($username, $password) { 
			return self::run(function() use ($username, $password) {
				return DataAccess::CARE_login($username, $password);
			});
		}

		public static function careGET($authcode, $funcname) {
			return self::run(function() use ($authcode, $funcname) {
				$realfunc = "DataAccess::GET_CARE_$funcname";
				$db = self::getConnection();
				$userid = DataAccess::authSetup($db, $authcode, self::T_CARE);

				return call_user_func($realfunc, $db, $userid);
			});
		}

		public static function carePOST($authcode, $funcname, $params) {
			return self::run(function() use ($authcode, $funcname, $params) {
				$realfunc = "DataAccess::POST_CARE_$funcname";
				$db = self::getConnection();
				$userid = DataAccess::authSetup($db, $authcode, self::T_CARE);

				return call_user_func($realfunc, $db, $userid, $params);
			});
		}

		public static function capaGET($authcode, $pid, $funcname) {
			return self::run(function() use ($authcode, $pid, $funcname) {
				$realfunc = "DataAccess::GET_CAPA_$funcname";
				$db = self::getConnection();
				$cid = DataAccess::authSetup($db, $authcode, self::T_CARE);
				$pid = self::checkRelation($db, $cid, $pid);

				return call_user_func($realfunc, $db, $cid, $pid);
			});
		}

		public static function capaPOST($authcode, $pid, $funcname, $params) {
			return self::run(function() use ($authcode, $pid, $funcname, $params) {
				$realfunc = "DataAccess::POST_CAPA_$funcname";
				$db = self::getConnection();
				$cid = DataAccess::authSetup($db, $authcode, self::T_CARE);
				$pid = self::checkRelation($db, $cid, $pid);

				return call_user_func($realfunc, $db, $cid, $pid, $params);
			});
		}

		//Patient

		public static function patiLINK($lcode) {
			return self::run(function() use ($lcode) {
				return DataAccess::PATI_link($lcode);
			});
		}

		public static function patiGET($authcode, $funcname) {
			return self::run(function() use ($authcode, $funcname) {
				$realfunc = "DataAccess::GET_PATI_$funcname";
				$db = self::getConnection();
				$userid = DataAccess::authSetup($db, $authcode, self::T_PATI);

				return call_user_func($realfunc, $db, $userid);
			});
		}

		public static function patiPOST($authcode, $funcname, $params) {
			return self::run(function() use ($authcode, $funcname, $params) {
				$realfunc = "DataAccess::POST_PATI_$funcname";
				$db = self::getConnection();
				$userid = DataAccess::authSetup($db, $authcode, self::T_PATI);

				return call_user_func($realfunc, $db, $userid, $params);
			});
		}



		/*
			Private methods

			All functions are ran through the run() method
			This ensures consistent error handling

		*/

		private static function run($function) {
			try {
				return $function();
			} catch(DBConnectException $e) {
				return Signal::dbConnectionError();
			} catch(AuthException $e) {
				return Signal::authError();
			} catch(Exception $e) {
				return Signal::error()->setMessage($e->getMessage());
			}
		}

		private static function getConnection() {
			$db = new mysqli(self::$ip, Secret::$username, Secret::$password, self::$dbName);

			if($db->connect_error)
				throw new DBConnectException();

			return $db;
		}

		private static function hash($value) {
			return hash("sha256", $value);
		}

		private static function hashPass($pass, $salt) {
			return self::hash($pass.$salt);
		}

		/*
			Helper functions that interface with the database
		*/

		private static function authSetup($db, $authcode, $type) {
			//Get userid from auth
			$stmt = $db->prepare("SELECT userid FROM auth WHERE authcode=? AND NOW() < expire");
			$stmt->bind_param('s', $authcode);
			$stmt->execute();
			$res = $stmt->get_result();
			$stmt->close();

			if($res->num_rows != 1)
				throw new AuthException();

			$userid = $res->fetch_assoc()['userid'];
			$userinfo = $db->query("SELECT * FROM users WHERE userid=$userid");
			$userinfo = $userinfo->fetch_assoc();

			if($userinfo['usertype'] != $type)
				throw new AuthException();

			//Update authcode expiration
			self::updateAuthExpiration($db, $userid);
			return $userid;
		}

		private static function checkRelation($db, $cid, $pid) {
			//Get userid from auth
			$stmt = $db->prepare("SELECT pid FROM relation WHERE cid=? AND pid=?");
			$stmt->bind_param("ii", $cid, $pid);
			$stmt->execute();
			$res = $stmt->get_result();
			$stmt->close();

			if($res->num_rows != 1)
				throw new Exception("Invalid patient id");

			return $res->fetch_assoc()['pid'];
		}

		private static function getIdFromUsername($db, $username) {
			$db = self::getConnection();
			$stmt = $db->prepare("SELECT cid FROM caretakers WHERE username=?");
			$stmt->bind_param('s', $username);
			$stmt->execute();
			$res = $stmt->get_result();
			$stmt->close();

			if($res->num_rows != 1) {
				throw new Exception("Invalid caretaker id");
			}
			return $res->fetch_assoc()['cid'];
		}

		private static function updateAuthExpiration($db, $userid) {
			$db->query("UPDATE auth SET expire=DATE_ADD(NOW(), INTERVAL 1 MONTH) WHERE userid=$userid");
		}

		//Creates a JSON array out of multiple results
		private static function formatArrayResults($res) {
			//Format results
			$rows = array();
			while($r = $res->fetch_assoc()) {
				$rows[] = $r;
			}
			return Signal::success()->setData($rows);
		}

		/*
			All actions
		*/

		//User

		private static function CARE_register($username, $password) {
			$db = self::getConnection();

			//Verify basic UN + Pass checks
			//UN >= 4 chars, Pass >= 8 chars
			if(strlen($username) < 5 || strlen($password) < 8)
				throw new Exception("Parameter length error");

			//Check if user exists
			$stmt = $db->prepare('SELECT cid FROM caretakers WHERE username=?');
			$stmt->bind_param('s', $username);
			$stmt->execute();
			$res = $stmt->get_result();
			if($res->num_rows > 0)
				throw new Exception("Username already taken");
			$stmt->close();

			//Process password: generate salt and hash pwd + salt
			$random = openssl_random_pseudo_bytes(64);
			$salt = self::hash($random);
			$hshpass = self::hashPass($password, $salt);

			//Insert user into database
			$stmt = $db->query('INSERT INTO users VALUES (null, 1)');
			$res = $db->query("SELECT LAST_INSERT_ID()");
			$cid = $res->fetch_assoc()['LAST_INSERT_ID()'];

			$stmt = $db->prepare('INSERT INTO caretakers VALUES (?, ?, ?, ?)');
			$stmt->bind_param('isss', $cid, $username, $hshpass, $salt);
			$stmt->execute();
			$stmt->close();
			return Signal::success();
		}

		private static function CARE_login($username, $password) {
			$db = self::getConnection();

			//Fetch salt + check if user exists
			$stmt = $db->prepare('SELECT username, salt FROM caretakers WHERE username=?');
			$stmt->bind_param('s', $username);
			$stmt->execute();
			$res = $stmt->get_result();
			$stmt->close();

			//User found (note same error)
			if($res->num_rows != 1)
				throw new Exception("Invalid credentials error");

			$row = $res->fetch_assoc();
			$username = $row['username']; //username is safe now: no risk of sql injection
			$salt = $row['salt'];

			//Salt password
			$hshpass = self::hashPass($password, $salt); //hshpass also safe, no sql injection in a hash
			$res = $db->query("SELECT cid FROM caretakers WHERE username='$username' AND password='$hshpass'");

			//Authentication
			if($res->num_rows != 1)
				throw new Exception("Invalid credentials error");
			$cid = $res->fetch_assoc()["cid"];

			//Check if user in auth table
			$res = $db->query("SELECT authcode FROM auth WHERE userid=$cid");

			//Generate a random authcode
			$random = openssl_random_pseudo_bytes(64);
			$authcode = self::hash($random);

			if($res->num_rows >= 1) {
				$authcode = $res->fetch_assoc()['authcode'];
			} else {
				//Set temporary expiration date and then update
				$db->query("INSERT INTO auth VALUES (null, $cid, '$authcode', NOW() )");
			}
			self::updateAuthExpiration($db, $cid);

			//Return success with data
			$data = array("authcode" => $authcode);
			return Signal::success()->setData($data);
		}

		private static function PATI_link($lcode) {
			$db = self::getConnection();

			//Fetch link
			$stmt = $db->prepare('SELECT lid, cid, pid FROM link WHERE lcode=? AND open=1');
			$stmt->bind_param('s', $lcode);
			$stmt->execute();
			$res = $stmt->get_result();
			$stmt->close();

			//Link code error
			if($res->num_rows != 1)
				throw new Exception("Invalid link code error");

			$res = $res->fetch_assoc();
			$lid = $res['lid'];
			$cid = $res['cid'];
			$pid = $res['pid'];

			//Update relation
			$db->query("UPDATE relation SET active=1 WHERE cid=$cid AND pid=$pid");

			//Update link
			$db->query("UPDATE link SET open=0 WHERE lid=$lid");

			//Return authcode
			$random = openssl_random_pseudo_bytes(64);
			$authcode = self::hash($random);
			$db->query("INSERT INTO auth VALUES (null, $pid, '$authcode', NOW() )");
			self::updateAuthExpiration($db, $pid);

			//Return success with data
			$data = array("authcode" => $authcode);
			return Signal::success()->setData($data);
		}

		private static function GET_CARE_verify($db, $cid) {
			//If userid exists, it means that authcode is valid already
			return Signal::success();
		}

		private static function GET_CARE_info($db, $cid) {
			//Username
			$res = $db->query("SELECT username FROM caretakers WHERE cid=$cid");
			$username = $res->fetch_assoc()["username"];

			//Data
			$data = array("username" => $username);
			return Signal::success()->setData($data);
		}

		private static function GET_CARE_patients($db, $cid) {
			$res = $db->query("SELECT cid, pid FROM relation WHERE cid=$cid");

			return self::formatArrayResults($res);
		}

		private static function POST_CARE_createPatient($db, $cid, $params) {
			if(is_null($params['name']) || is_null($params['usability']))
				throw new Exception("Invalid POST data");

			//Patient info
			$name = $params['name'];
			$usability = $params['usability'];

			//Create patient
			$db->query('INSERT INTO users VALUES (null, 0)');
			$res = $db->query("SELECT LAST_INSERT_ID()");
			$pid = $res->fetch_assoc()['LAST_INSERT_ID()']; 

			$stmt = $db->prepare('INSERT INTO patients VALUES (?, ?, ?)');
			$stmt->bind_param('iss', $pid, $name, $usability);
			$stmt->execute();
			$stmt->close();

			//Create relation
			$db->query("INSERT INTO relation VALUES (null, $cid, $pid, 0)");

			$random = openssl_random_pseudo_bytes(6);
			$lcode = base64_encode($random);

			//Create link
			$db->query("INSERT INTO link VALUES (null, $cid, $pid, '$lcode', 1)");
			$data = array("pid" => $pid, "lcode" => $lcode);
			return Signal::success()->setData($data);
		}

		private static function registerDevice($db, $userid, $uiud) {
			$stmt = $db->prepare('INSERT INTO devices VALUES (null, ?, ?)');
			$stmt->bind_param('is', $userid, $uiud);
			$stmt->execute();
			$stmt->close();
		}

		private static function POST_CARE_registerDevice($db, $cid, $params) {
			if(is_null($params['uiud']) || strlen($params['uiud']) != 64)
				throw new Exception("Invalid POST data");

			self::registerDevice($db, $cid, $params['uiud']);
			return Signal::success();
		}

		private static function POST_PATI_registerDevice($db, $pid, $params) {
			if(is_null($params['uiud']) || strlen($params['uiud']) != 64)
				throw new Exception("Invalid POST data");

			self::registerDevice($db, $pid, $params['uiud']);
			return Signal::success();
		}


		private static function GET_CAPA_relink($db, $cid, $pid) {
			$data = array();
			//Look for existing open link
			$res = $db->query("SELECT lcode FROM link WHERE cid=$cid AND pid=$pid AND open=1");
			if($res->num_rows == 1) {
				$data['lcode'] = $res->fetch_assoc()['lcode'];
				return Signal::success()->setData($data);
			}

			//Create link
			$random = openssl_random_pseudo_bytes(6);
			$lcode = base64_encode($random);

			$db->query("INSERT INTO link VALUES (null, $cid, $pid, '$lcode', 1)");
			$data['lcode'] = $lcode;
			return Signal::success()->setData($data);
		}

		private static function GET_CAPA_share($db, $cid, $pid, $params) {
			$username = $params['username'];
			$ncid = self::getIdFromUsername($username);

			//Create relation
			$db->query("INSERT INTO relation VALUES (null, $cid, $pid, 1)");
			return Signal::success();
		}

	}
?>