<?php
$suppress_decimal = checkSettingMode('wmt::suppress_vital_decimal','',$frmdir);
$vdata = array();
reset($_POST);
foreach ($_POST as $k => $var) {
 	if(($k != 'vid') && (substr($k,0,6) != 'vital_')) continue;
 	if(is_string($var)) $var = trim($var);
	if($k == 'vid') {
		$vdata[$k] = $var;
	} else $vdata[substr($k,6)] = $var;
	// IMPORTANT - REMOVE THEM ALL FROM POST EXCEPT THE ID WHICH WILL
	// BE LINKED INTO THE FORM
 	if(substr($k,0,6) == 'vital_') unset($_POST[$k]);
}
if(!isset($vdata['vid'])) $vdata['vid'] = '';
$log = "INSERT FORM $frmdir MODE [$form_mode] ($pid) '$encounter:$id' " .
	"PRE-PROCESS VITALS ID (".$vdata['vid'].")";
if($form_event_logging) auditSQLEvent($log, TRUE);

if($vdata['vid']) {
	if($changed = wmtVitals::vitalsChanged($vdata['vid'], $vdata, $suppress_decimal)) {
		$new_vitals = wmtVitals::addVitals($pid, $encounter, $vdata);
		$dt['vid'] = $new_vitals->vid;
		$log = "INSERT FORM $frmdir MODE [$form_mode] ($pid) '$encounter:$id' " .
			"SAVED CHANGED VITALS FORM ID (".$dt['vid'].") [$changed]";
		if($form_event_logging) auditSQLEvent($log, TRUE);
	} else {
		$dt['vid'] = $vdata['vid'];
		$log = "INSERT FORM $frmdir MODE [$form_mode] ($pid) '$encounter:$id' " .
			"VITALS (".$vdata['vid'].") UNCHANGED";
		if($form_event_logging) auditSQLEvent($log, TRUE);
	}
} else {
	$new_vitals = wmtVitals::addVitals($pid, $encounter, $vdata);
	$dt['vid'] = $new_vitals->vid;
	$log = "INSERT FORM $frmdir MODE [$form_mode] ($pid) '$encounter:$id' " .
		"SAVED NEW VITALS FORM ID (".$dt['vid'].")";
	if($form_event_logging) auditSQLEvent($log, TRUE);
}
$_POST['vid'] = $dt['vid'];
?>