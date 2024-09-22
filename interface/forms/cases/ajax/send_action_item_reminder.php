<?php

require_once("../../../globals.php");
require_once($GLOBALS['srcdir'].'/wmt-v2/wmtcase.class.php');
require_once($GLOBALS['srcdir']."/pnotes.inc");

$actionItemId = isset($_REQUEST['action_id']) ? $_REQUEST['action_id'] : "";
$caseId = isset($_REQUEST['case_id']) ? $_REQUEST['case_id'] : "";
$pid = isset($_REQUEST['pid']) ? $_REQUEST['pid'] : "";

$aiActionItem = isset($_REQUEST['ai_action_item']) ? $_REQUEST['ai_action_item'] : "";
$aiOwner = isset($_REQUEST['ai_owner']) ? $_REQUEST['ai_owner'] : "";
$aiStatus = isset($_REQUEST['ai_status']) ? $_REQUEST['ai_status'] : "";
$refreshStatus = false;

$responceData = array(
	'status' => false,
	'message' => "Something wrong"
);

if(empty($actionItemId) || empty($caseId)) {
	$responceData['message'] = "Something wrong";
	echo json_encode($responceData);
	exit();
}

if($actionItemId == "new") {
	if(empty($aiActionItem)) {
		$responceData['message'] = "Unable to save. Please enter required details";
		echo json_encode($responceData);
		exit();
	}

	// Insert
    $aiItemId = sqlInsert("INSERT INTO `vh_action_items_details` (case_id, action_item, owner, status, updated_by) VALUES (?, ?, ?, ?, ?) ", array(
        $caseId,
        $aiActionItem,
        $aiOwner,
        $aiStatus,
        $_SESSION['authUserID']
    ));

    if(empty($aiItemId)) {
    	$responceData['message'] = "Something wrong";
		echo json_encode($responceData);
		exit();
    }

    $actionItemId = $aiItemId;
    $refreshStatus = true;
}

//Prepare action item data
$ai_items_data = wmtCase::getActionItems($caseId);
$internalMessages = array();  

foreach ($ai_items_data as $aItem) {
	if(isset($aItem['id']) && $aItem['id'] == $actionItemId) {

		if(!isset($internalMessages[$aItem['owner']])) $internalMessages[$aItem['owner']] = array();

        $internalMessages[$aItem['owner']][] = array('id' => $aItem['id'], 'note' => strlen($aItem['action_item']) > 30 ? substr($aItem['action_item'], 0, 30) . "..." : $aItem['action_item']);
		break;
	}
}


foreach ($internalMessages as $aowner => $ainotevalue) {
	$pnoteResponce = addPNoteForAi($pid, $aowner, "", "Case Management", $caseId, $ainotevalue);
	
	if($pnoteResponce !== false) {
		$responceData = array(
			'status' => true,
			'refresh' => $refreshStatus,
			'message' => "Success"
		);
	}
}

echo json_encode($responceData);

function addPNoteForAi($set_assign_pid, $set_username, $set_group, $set_note_type, $case_id = '', $ai_text = array()) {
    $pData = sqlQuery("SELECT CONCAT(CONCAT_WS(' ', IF(LENGTH(pd.fname),pd.fname,NULL), IF(LENGTH(pd.lname),pd.lname,NULL))) as patient_name, pd.pubpid as pubpid from patient_data pd where pid = ?;", array($set_assign_pid));

    $pai_text = array();
    foreach ($ai_text as $anote) {
        $pai_text[] = "{{aitemlink|".$anote['note']."|'".$case_id."','".$set_assign_pid."','aitem".$anote['id']."'}}";
    }

    $note = "You have been assigned a case management action item {{plink|".$pData['patient_name']." (".$pData['pubpid'].")"."|'".$set_assign_pid."'}} \nAction Items: ". implode(", ",$pai_text);

    $assigned_to = $set_username;
    if(isset($set_group) && !empty($set_group)) {
        $assigned_to = 'GRP:'.$set_group;
    }

    if(!empty($assigned_to) && !empty($set_note_type)) {
        return addPnote($set_assign_pid, $note, '1', '1', $set_note_type, $assigned_to, '', "New");
    }

    return false;
}