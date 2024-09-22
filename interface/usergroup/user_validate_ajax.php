<?php

require_once("../globals.php");

use OpenEMR\Common\Csrf\CsrfUtils;
use OpenEMR\Core\Header;

$reqAction = $_REQUEST['action'] ?? "";
$userId = $_REQUEST['id'] ?? "";

$userNPI = $_REQUEST['npi'] ?? "";
if (empty($userNPI)) {
	$userNPI = $_REQUEST['form_npi'] ?? "";
}

$userEmail = $_REQUEST['form_email'] ?? "";

$res = array(
	"status" => false,
	"msg" => ""
);

if (!empty($reqAction)) {

	if (in_array($reqAction, array("validate_npi", "both")) && !empty($userNPI)) {
		$bindArray = array();
		
		$sqlQuery = "SELECT u.id, u.fname, u.mname, u.lname from users u where u.email = ? ";
		$bindArray[] = $userNPI;

		if (!empty($userId)) {
			$sqlQuery .= " AND u.id != ? ";
			$bindArray[] = $userId;
		}

		$sqlQuery .= " LIMIT 1";

		$userData = sqlQuery($sqlQuery, $bindArray);

		if (!empty($userData)) {
			$res['status'] = true;
			$res['msg'] = $res['msg'] . "NPI id already used by another user. \n";
		}
	} 

	if (in_array($reqAction, array("validate_unique_email", "both")) && !empty($userEmail)) {
		
		$bindArray = array();

		$sqlQuery = "SELECT u.id, u.fname, u.mname, u.lname from users u where u.email = ? ";
		$bindArray[] = $userEmail;

		if (!empty($userId)) {
			$sqlQuery .= " AND u.id != ? ";
			$bindArray[] = $userId;
		}

		$userSql = sqlStatement($sqlQuery, $bindArray);

		$userData = array();
		while ($userrow = sqlFetchArray($userSql)) {
			$username = $userrow['fname'] ?? "";
			if (!empty($userrow['lname'])) {
				$username .= " " . $userrow['lname'];
			}

			$userData[] = $username;
		}

		if (!empty($userData) && count($userData) > 0) {
			$res['status'] = true;
			$res['msg'] = $res['msg'] . "Email Id already used by another user. ( " . implode(", ", $userData) . " ) \n";
		}
	}
}

echo json_encode($res);