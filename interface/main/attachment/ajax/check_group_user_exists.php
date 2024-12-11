<?php

require_once("../../../globals.php");

$users = isset($_REQUEST['user']) ? $_REQUEST['user'] : array();
$uGroup = !empty($users) ? explode(":", $users) : array();

if(!empty($uGroup) && $uGroup[0] != "GRP") {
	echo json_encode(array(
		'status' => true,
		'isGroup' => false,
		'data' =>  ''
	));
	exit();
}

$userGroup = !empty($uGroup) && $uGroup[0] == "GRP" ? $uGroup[1] : "";
$responce = array();


if(!empty($userGroup)) {
	$fres = sqlStatement("SELECT * FROM `msg_group_link` JOIN `users` ON users.id  = msg_group_link.user_id WHERE group_id = ?", array($userGroup));
	$sResult = array();
	while($frow = sqlFetchArray($fres)) {
		unset($frow['uuid']);
		$uname = text($frow['lname']);
		if($frow['fname']) $uname .= ', ' . text($frow['fname']);
		$sResult[] = $uname;
	}

	$responce = array(
		'status' => true,
		'isGroup' => true,
		'data' => !empty($sResult) ? (string) count($sResult) : "0",
		'userlist' => $sResult
	);

} else {
	$responce = array(
		'status' => false,
		'isGroup' => true,
		'data' =>  '',
		'userlist' => array()
	);
}
echo json_encode($responce);