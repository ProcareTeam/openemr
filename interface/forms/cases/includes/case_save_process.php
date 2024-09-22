<?php

include_once("../../globals.php");
include_once($GLOBALS['srcdir'].'/wmt-v2/wmtcase.class.php');
require_once($GLOBALS['srcdir']."/pnotes.inc");

use OpenEMR\Common\Logging\EventAuditLogger;

$mode = isset($_REQUEST['mode']) ? $_REQUEST['mode'] : "";

if($frmn == "form_cases") {

    if($frmn == "form_cases" && $mode == "updatenotes") {
        // $field_prefix = '';

        // $form_lb_date = isset($_POST['tmp_lb_date']) && !empty($_POST['tmp_lb_date']) ? date("Y-m-d",strtotime(trim($_POST['tmp_lb_date'])))  : NULL;
        // $form_lb_notes = isset($_POST['tmp_lb_notes']) ? trim($_POST['tmp_lb_notes']) : "";
        // $form_lb_list_interim = "";
        // //$form_lb_list_interim = isset($_POST['tmp_lb_list_interim']) ? trim($_POST['tmp_lb_list_interim']) : "";

        // if(isset($_POST['tmp_lb_list_interim']) && !empty($_POST['tmp_lb_list_interim'])) {
        //  $nq_filter = ' AND option_id = "'.$_POST['tmp_lb_list_interim'].'"';
        //  $listOptions = LoadList('Case_Billing_Notes', 'active', 'seq', '', $nq_filter);

        //  if(!empty($listOptions)) {
        //      $form_lb_list_interim = $listOptions[0] && isset($listOptions[0]['title']) ? $listOptions[0]['title'] : "";
        //  }
        // }

        // if(!empty($form_lb_list_interim)) {
        //  if(!empty($form_lb_notes)) {
        //      $form_lb_notes = $form_lb_list_interim . " - ".$form_lb_notes;
        //  } else {
        //      $form_lb_notes = $form_lb_list_interim;
        //  }
        // }
        
        // if(!empty($form_lb_date) && !empty($form_lb_notes)) {
        //  $sql = "INSERT INTO `case_form_value_logs` ( case_id, delivery_date, notes, user ) VALUES (?, ?, ?, ?) ";
        //  sqlInsert($sql, array(
        //      $id,
        //      $form_lb_date,
        //      $form_lb_notes,
        //      $_SESSION['authUserID']
        //  ));
        // }
    }

    if($frmn == "form_cases" && ($mode == "save" || $mode == "updatenotes")) {
        foreach($modules as $module) {
            if($module['codes'] != '') $chp_options = explode('|', $module['codes']);
            $field_prefix = $chp_options[1];

            if($module['option_id'] == "case_header") {
                $sc_referring_id_tmp = isset($_REQUEST['tmp_' . $field_prefix . 'sc_referring_id']) ? $_REQUEST['tmp_' . $field_prefix . 'sc_referring_id'] : array();
                $sc_filter_referring_id = array();

                foreach($sc_referring_id_tmp as $key => $val) {
                    if(!empty($val)) {
                        $sc_filter_referring_id[] = $val;
                    }
                }
                $sc_referring_id = implode("|",$sc_filter_referring_id);
                wmtCase::addScRcData($id, $sc_referring_id);
            }
        }
    }


    if($frmn == "form_cases" && ($mode == "save" || $mode == "updatenotes")) {
        $bc_date_value = isset($_POST['bc_date']) ? $_POST['bc_date'] : "";
        $bc_notes_value = isset($_POST['bc_notes']) ? $_POST['bc_notes'] : "";
        $bc_notes_dsc_value = isset($_POST['bc_notes_dsc']) ? $_POST['bc_notes_dsc'] : "";
        
        $bc_old_value = isset($_POST['tmp_old_bc_value']) ? $_POST['tmp_old_bc_value'] : "";
        $bc_new_value = oeFormatShortDate($bc_date_value) . $bc_notes_value . $bc_notes_dsc_value;

        if($bc_old_value !== $bc_new_value) {
            $form_lb_date = !empty($bc_date_value) ? date("Y-m-d",strtotime(trim($bc_date_value))) : NULL;
            $form_lb_list_interim = "";
            $form_lb_notes = $bc_notes_dsc_value;

            if(!empty($bc_notes_value)) {
                $nq_filter = ' AND option_id = "'.$bc_notes_value.'"';
                $listOptions = LoadList('Case_Billing_Notes', 'active', 'seq', '', $nq_filter);

                if(!empty($listOptions)) {
                    $form_lb_list_interim = $listOptions[0] && isset($listOptions[0]['title']) ? $listOptions[0]['title'] : "";
                }
            }

            if(!empty($form_lb_list_interim)) {
                if(!empty($form_lb_notes)) {
                    $form_lb_notes = $form_lb_list_interim . " - " . $form_lb_notes;
                } else {
                    $form_lb_notes = $form_lb_list_interim;
                }
            }
            
            if(!empty($form_lb_date) || !empty($form_lb_notes)) {
                $sql = "INSERT INTO `case_form_value_logs` ( case_id, delivery_date, notes, user ) VALUES (?, ?, ?, ?) ";
                $sId = sqlInsert($sql, array(
                    $id,
                    $form_lb_date,
                    $form_lb_notes,
                    $_SESSION['authUserID']
                ));

                if(!empty($id)) {
                    wmtCase::updateRecentDate($id);
                }
            }
        }
    }

    if ($frmn == "form_cases" && ($mode == "save" || $mode == "updatenotes")) {
        if (!empty($oldCaseData ?? array())) {
            if ($dt['closed'] == "1" && $oldCaseData['closed'] != $dt['closed']) {
                $logstring = "id = " . $id .", closed =" . $dt['closed'];
                EventAuditLogger::instance()->newEvent("case-inactive", $_SESSION['authUser'], $_SESSION['authProvider'], 1, "$frmn: $logstring");
            }
        }
    }

    if($frmn == "form_cases" && ($mode == "save" || $mode == "updatenotes")) {
        if(!empty($id)) {
            $fieldList = array('case_manager' => '', 'rehab_field_1' => array(), 'rehab_field_2' => array());

            foreach($modules as $module) {
                if($module['codes'] != '') $chp_options = explode('|', $module['codes']);
                $field_prefix = $chp_options[1];

                if($module['option_id'] == "case_header") {
                    $data = array();
                    $casemanager_hidden_sec = isset($_REQUEST['tmp_' . $field_prefix . 'casemanager_hidden_sec']) ? $_REQUEST['tmp_' . $field_prefix . 'casemanager_hidden_sec'] : 0;

                    foreach ($fieldList as $fk => $fItem) {
                        $data[$fk] = isset($_REQUEST['tmp_' . $field_prefix . $fk]) ? $_REQUEST['tmp_' . $field_prefix . $fk] : $fItem;
                    }

                    if($casemanager_hidden_sec === "1") {
                        //Save PI Case Values
                        $isNeedToUpdate = wmtCase::generateRehabLog($id, $data, $field_prefix);
                        wmtCase::savePICaseManagmentDetails($id, $data, 1);

                        if($isNeedToUpdate !== false) {
                            wmtCase::logFormFieldValues(array(
                                'field_id' => 'rehab_field',
                                'form_name' => $frmn,
                                'form_id' => $id,
                                'new_value' => $isNeedToUpdate['new_value'],
                                'old_value' => $isNeedToUpdate['old_value'],
                                'pid' => isset($_REQUEST['pid']) ? $_REQUEST['pid'] : '',
                                'username' => $_SESSION['authUserID']
                            ));

                            // Set rehab plan & rehab progress
                            sqlStatement("UPDATE `form_cases` SET `vh_rehabplan` = rehabplan('" . $id . "'), `vh_rehabprogress` = COALESCE(NULLIF(rehabprogress('" . $id . "'), ''), NULL) WHERE `id` = ?", array($id));
                        }
                    } else {
                        wmtCase::savePICaseManagmentDetails($id, $data, 0);
                    }

                    //Handle Lawyer/Paralegal Contacts
                    $lpc_data = array();
                    $lpc_fieldList = array('lp_contact');
                    foreach ($lpc_fieldList as $lpc_k => $lpcItem) {
                        $lpc_data[$lpcItem] = isset($_REQUEST['tmp_' . $field_prefix . $lpcItem]) ? $_REQUEST['tmp_' . $field_prefix . $lpcItem] : "";
                    }
                    if(!empty($lpc_data) && !empty($id)) {
                        $c_notes = isset($_REQUEST[$field_prefix . 'notes']) ? $_REQUEST[$field_prefix . 'notes'] : "";
                        $c_emails = array_filter(explode(",",$c_notes));
                        $c_emails = array_map('trim',$c_emails);

                        $t_emails = $c_emails;

                        $lpContactData = wmtCase::getPICaseManagerData($id, 'lp_contact');
                        $lpList1 = array();
                        $lpList2 = array();

                        foreach ($lpContactData as $lpck => $lpcItem) {
                            if(isset($lpcItem['field_value']) && !empty($lpcItem['field_value'])) {
                                $lpList1[] = $lpcItem['field_value'];
                            }
                        }

                        if(isset($lpc_data['lp_contact']) && !empty($lpc_data['lp_contact'])) {
                            $lpList2 = $lpc_data['lp_contact'];
                        }

                        $diff1 = wmtCase::getArrayValDeff($lpList1, $lpList2);
                        $diff2 = wmtCase::getArrayValDeff($lpList2, $lpList1);
                        
                        $diffa1 = wmtCase::getAbookData($diff1);
                        $diffa2 = wmtCase::getAbookData($diff2);

                        if(!empty($diff1) || !empty($diff2)) {
                            foreach ($diff1 as $dak1 => $daI1) {
                                if(isset($diffa1['id_'.$daI1]) && !empty($diffa1['id_'.$daI1])) {
                                    $daItem1 = $diffa1['id_'.$daI1];

                                    if(isset($daItem1['email']) && !empty($daItem1['email'])) {
                                        if (($ky1 = array_search($daItem1['email'], $t_emails)) !== false) {
                                            unset($t_emails[$ky1]);
                                        }
                                    }
                                }
                            }

                            foreach ($diff2 as $dak2 => $daI2) {
                                if(isset($diffa2['id_'.$daI2]) && !empty($diffa2['id_'.$daI2])) {
                                    $daItem2 = $diffa2['id_'.$daI2];

                                    if(isset($daItem2['email']) && !empty($daItem2['email'])) {
                                        $t_emails[] = $daItem2['email'];
                                    }
                                }
                            }

                            if(isset($t_emails) && !empty($id)) {
                                $t_emails_str = implode(", ", $t_emails);
                                sqlStatement("UPDATE form_cases SET `notes` = ? WHERE `id` = ?", array($t_emails_str, $id));
                            }
                        }

                        //Save PI Case Managment Data
                        wmtCase::savePICaseManagmentDetails($id, $lpc_data, ($casemanager_hidden_sec === "1") ? 1 : 0);
                    }

                    //Handle Lawyer/Paralegal Contacts
                    $ai_data = array();
                    $ai_fieldList = array('ai_action_item', 'ai_owner', 'ai_status');

                    if(isset($_REQUEST['tmp_' . $field_prefix . 'ai_id'])) {
                        foreach ($_REQUEST['tmp_' . $field_prefix . 'ai_id'] as $ai => $aid) {
                            $ai_action_item = isset($_REQUEST['tmp_' . $field_prefix . 'ai_action_item'][$ai]) ? $_REQUEST['tmp_' . $field_prefix . 'ai_action_item'][$ai] : "";
                            $ai_owner = isset($_REQUEST['tmp_' . $field_prefix . 'ai_owner'][$ai]) ? $_REQUEST['tmp_' . $field_prefix . 'ai_owner'][$ai] : "";
                            $ai_status = isset($_REQUEST['tmp_' . $field_prefix . 'ai_status'][$ai]) ? $_REQUEST['tmp_' . $field_prefix . 'ai_status'][$ai] : "";

                            if(!empty($ai_action_item) && !empty($ai_owner) && !empty($ai_status)) {
                                $ai_data[] = array(
                                    'id' => $aid,
                                    'action_item' => $ai_action_item,
                                    'owner' => $ai_owner,
                                    'status' => $ai_status 
                                );
                            }
                        }
                    }

                    
                    $isNeedToUpdate = false;
                    $internalMessages = array();  
                    foreach ($ai_data as $aItem) {
                        if(isset($aItem['id']) && !empty($aItem['id'])) {
                            // Update
                            $aiItemId = $aItem['id'];
                            $aiData=sqlQuery("SELECT * from vh_action_items_details where id = ? order by id desc", array($aItem['id']));

                            $isNeedToUpdate = getActionItemDeff($aItem, $aiData);
                            if($isNeedToUpdate !== false) {
                                sqlStatement("UPDATE `vh_action_items_details` SET `action_item` = ?, `owner` = ? , `status` = ?, `updated_datetime` = now(), `updated_by` = ? WHERE `id` = ?", array($aItem['action_item'], $aItem['owner'], $aItem['status'], $_SESSION['authUserID'], $aItem['id']));
                            }
                        } else {
                            // Insert
                            $isNeedToUpdate = getActionItemDeff($aItem, array());
                            $aiItemId = sqlInsert("INSERT INTO `vh_action_items_details` (case_id, action_item, owner, status, updated_by) VALUES (?, ?, ?, ?, ?) ", array(
                                $id,
                                $aItem['action_item'],
                                $aItem['owner'],
                                $aItem['status'],
                                $_SESSION['authUserID']
                            ));

                            //if(!isset($internalMessages[$aItem['owner']])) $internalMessages[$aItem['owner']] = array();
                            //$internalMessages[$aItem['owner']][] = array('id' => $aiItemId, 'note' => strlen($aItem['action_item']) > 30 ? substr($aItem['action_item'], 0, 30) . "..." : $aItem['action_item']);

                        }
                    }

                    foreach ($internalMessages as $aowner => $ainotevalue) {
                        //addPNoteForAi($pid, $aowner, "", "Case Management", $id, $ainotevalue);
                    }
                }
            }
        }
    }
}

function getActionItemDeff($data = array(), $data1 = array()) {
    $fields = array('action_item', 'owner', 'status');
    $fvalues = array();
    $uStatus = false;

    foreach ($fields as $field) {
        if(isset($data[$field])) {
            if(empty($fvalues[$field])) $fvalues[$field] = array();

            $fvalues[$field]['new'] = isset($data[$field]) ? $data[$field] : "";
            $fvalues[$field]['old'] = isset($data1[$field]) ? $data1[$field] : "";

            if($data[$field] != $data1[$field]) {
                $uStatus = true;
            }
        }
    }

    return $uStatus === true ? $fvalues : false;
}

/*
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
*/