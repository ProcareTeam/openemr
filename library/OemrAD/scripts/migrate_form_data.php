<?php

$_SERVER['REQUEST_URI']=$_SERVER['PHP_SELF'];
$_SERVER['SERVER_NAME']='localhost';
$_SERVER['HTTP_HOST'] = 'localhost';
$_SESSION['site'] = 'default';
$backpic = "";
$ignoreAuth=1;

require_once("../interface/globals.php");


$dataList = sqlStatementNoLog("SELECT vof.id as dataId, vofl.id as logId, vof.form_id, vof.created_date  from vh_onsite_forms vof left join vh_onetimetoken_form_log vofl on vofl.form_id = vof.id where vof.form_id != '' order by vof.id asc", array());
            
while ($dataItem = sqlFetchArray($dataList)) {
	$formId = isset($dataItem['dataId']) ? $dataItem['dataId'] : 0;
	$tformId = isset($dataItem['form_id']) ? $dataItem['form_id'] : 0;
	$logId = isset($dataItem['logId']) ? $dataItem['logId'] : 0;
	$createdDate = isset($dataItem['created_date']) ? $dataItem['created_date'] : "";

	$newDataId = sqlInsert("INSERT INTO vh_form_data_log (form_id, type, created_date) VALUES(?, ?, ?)", array($tformId, 'form', $createdDate));

	if(!empty($newDataId)) {
		sqlQueryNoLog("UPDATE `vh_onsite_forms` SET `ref_id` = ? WHERE id = ? ", array($newDataId, $formId));

		sqlQueryNoLog("UPDATE `vh_onetimetoken_form_log` SET `ref_id` = ? WHERE id = ? ", array($newDataId, $logId));
	}

	echo '<pre>';
	print_r($dataItem);
	echo '</pre>';
}