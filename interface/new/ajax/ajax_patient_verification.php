<?php

require_once("../../globals.php");
require_once("$srcdir/patient.inc");

if(!isset($_GET['firstName'])) $_GET['firstName'] = '';
if(!isset($_GET['lastName'])) $_GET['lastName'] = '';
if(!isset($_GET['dob'])) $_GET['dob'] = '';

function isPatientExists($firstName = "", $lastName = "", $dob = "") {
	$sql = "select * from patient_data where fname= ? AND lname = ? AND DOB = ?";
	$row = sqlQuery($sql, array($firstName, $lastName, $dob));
	return $row;
}

/* Set Request data */
$firstName = strip_tags($_GET['firstName']);
$lastName = strip_tags($_GET['lastName']);
$dob = strip_tags($_GET['dob']);

$newDate = "";
try {

	if(isset($dob) && !empty($dob)) {
		$newDate = date("Y-m-d", strtotime($dob));
	}

	if($firstName == "" && $lastName == "") {
		throw new Exception("Firstname & Lastname is empty.");
	}

	//Get Patient Data
	$verificationResponce = isPatientExists($firstName, $lastName, $newDate);

	$isExists = false;
	if(isset($verificationResponce) && $verificationResponce != null && !empty($verificationResponce)) {
		$isExists = true;
	}

	//Unset uuid field because facing issue while rendering   
	unset($verificationResponce['uuid']);

	echo json_encode(array('status' => 'true', 'isExists' => $isExists, 'data' => $verificationResponce));
} catch(Exception $e) {
	echo json_encode(array('status' => 'false', 'isExists' => 'null', 'error' => $e->getMessage()));
}
