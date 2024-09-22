<?php

require_once("../globals.php");
require_once($srcdir . "/OemrAD/oemrad.globals.php");

use OpenEMR\OemrAd\EmailVerificationLib;
use OpenEMR\Common\Crypto\CryptoGen;

$cryptoGen = new CryptoGen();
$emailVerificationApi = $cryptoGen->decryptStandard($GLOBALS['email_verification_api']);

$returnResponce = array(
	"success" =>"false",
	"message" => "Something went wrong"
);

if(empty($_GET['email'])) {
	echo json_encode($returnResponce);
	exit();
}

if(!empty($_GET['email'])) {
	$emailStatus =  EmailVerificationLib::getEmailVerificationData($_GET['email']);
	if($emailStatus == "1") {
		echo json_encode(array(
			"success" => "true",
			"result" => "valid",
			"disposable" => "false",
			"accept_all" => "false"
		));
		exit();
	}
}

$getUrl = "http://api.quickemailverification.com/v1/verify?email=".$_GET['email']."&apikey=".$emailVerificationApi;

$ch = curl_init();
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, FALSE);
curl_setopt($ch, CURLOPT_FOLLOWLOCATION, TRUE);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
curl_setopt($ch, CURLOPT_URL, $getUrl);
curl_setopt($ch, CURLOPT_TIMEOUT, 80);
 
$response = curl_exec($ch);
 
if(curl_error($ch)){
	echo json_encode($returnResponce);
} else {
	echo json_encode(json_decode($response), true);
}
 
curl_close($ch);