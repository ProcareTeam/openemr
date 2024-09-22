<?php

if(!isset($_SERVER['SERVER_NAME']) || empty($_SERVER['SERVER_NAME'])) {
    $_SERVER['REQUEST_URI']=$_SERVER['PHP_SELF'];
    $_SERVER['SERVER_NAME']='localhost';
    $_SERVER['HTTP_HOST'] = 'localhost';
    $_GET['site'] = 'default';
}
$backpic = "";
$ignoreAuth=1;

require_once(dirname( __FILE__, 3 ) . "/interface/globals.php");

use OpenEMR\Common\Crypto\CryptoGen;
use OpenEMR\Core\Header;

$mautic_host = $GLOBALS['mautic_host'];
$mautic_user = $GLOBALS['mautic_username'];
$mautic_contact_owner = $GLOBALS['mautic_contact_owner'];
$mautic_pass = $cryptoGen->decryptStandard($GLOBALS['mautic_password']);

$idb_host = $GLOBALS['idempiere_host'];
$idb_user = $GLOBALS['idempiere_db_user'];
$idb_pass = $cryptoGen->decryptStandard($GLOBALS['idempiere_db_password']);
$idb_port = $GLOBALS['idempiere_port'];
$idb = $GLOBALS['idempiere_db'];
$idb_client_id = $GLOBALS['idempiere_client_id'];

if(empty($mautic_host) || empty($mautic_user) || empty($mautic_pass) || empty($mautic_contact_owner)) {
    echo "Invalid Mautic Credential";
    exit();
}

if(empty($idb_host) || empty($idb_user) || empty($idb_pass) || empty($idb_port) || empty($idb) || empty($idb_client_id)) {
    echo "Invalid Idempiere Credential";
    exit();
}

global $idb_connection;
$idb_connection = pg_connect("host=$idb_host dbname=$idb user=$idb_user password=$idb_pass");
if (!$idb_connection) {
    echo "Idempiere Connection failed.";
}

//Create or update contact
function syncContact($pid, $body_params) {
    global $mautic_host, $mautic_user, $mautic_pass;

    $apiUrl = $mautic_host . "/api/contacts/" . $pid . "/pid/create";

    $ch = curl_init($apiUrl);
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($body_params));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

    // Set basic authentication header
    curl_setopt($ch, CURLOPT_HTTPHEADER, array(
        'Authorization: Basic ' . base64_encode($mautic_user . ':' . $mautic_pass),
        'Content-Type: application/x-www-form-urlencoded'
    ));
    $response = curl_exec($ch);
    $returnData = array();
    
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

    if (curl_errno($ch)) {
        $returnData = array("status" => false, "error" => curl_error($ch));
        return $returnData;
    }

    if ($httpCode === 401) {
        $returnData = array("status" => false, "error" => "Authentication failed: Unauthorized.");
        return $returnData;
    } elseif ($httpCode === 403) {
        $returnData = array("status" => false, "error" => "Authentication failed: Forbidden.");
        return $returnData;
    } elseif ($httpCode !== 200) {
        $returnData = array("status" => false, "error" => "Failed");
        return $returnData;
    }

    if(empty($response)) {
        $returnData = array("status" => false, "error" => "Empty responce");
        return $returnData;
    }

    $returnData = array("status" => true, "data" => json_encode($response, true));
    return $returnData;
}

//Write log into db
function dbLog($filename, $message, $iserror) {
    global $idb_connection, $idb_client_id;
    $check_error = json_encode($iserror); 

    if (!$idb_connection) {
        echo "Connection failed.";
    } else {
        $pgsql = "INSERT INTO Mautic_Contact_Log(ad_client_id, ad_org_id, created, createdby, date1, filename, help, isactive, iserror, mautic_contact_log_uu, updated, updatedby) VALUES ($1, 0,now(),100,now(), $2, $3, 'Y', $4,generate_uuid(),now(),100)";
        $result = pg_query_params($idb_connection, $pgsql, array($idb_client_id, $filename, $message, $check_error));
        if ($result) {
            echo "Row inserted successfully!";
        } else {
            echo "Insert failed.";
        }
    }
}

//Add Logs
function addLogs($message, $iserror) {
    $filename = "mauticSynccontacts";
    dbLog($filename, $message, $iserror);
}


//Log Responce into database
function logResponce($responce) {
    $filename = "mauticSynccontacts";
    $action = ($responce['action'] != undefined) ? $responce['action'] : null;
    
    if($responce['errors'] != undefined && $responce['errors'][0]['message'] != undefined ){
        //addLogs($responce['errors'][0]['message'], true);
        dbLog($filename, $responce['errors'][0]['message'], true);
    } else if($responce['contact'] != undefined && $responce['contact']['id']) {
        $contact_id = $responce['contact']['id'];
        $message = "Contact Id: " . $contact_id;
        
        if($action == "UPDATE") {
            $message = "Updated Contact (Id: " . $contact_id . ")";
        } else if($action == "CREATE") {
            $message = "Created Contact (Id: " . $contact_id . ")";
        }

        dbLog($filename, $message, false);
    } else {
        $message = "Unidentified responce.";

        if($action == "UPDATE") {
            $message = "Update: Unidentified responce.";
        } else if($action == "CREATE") {
            $message = "Create: Unidentified responce.";
        }

        dbLog($filename, $message, true);
    }
}

function getTagsForContact($appointment_provider_form_label = "", $insurance_multiple_label = array(), $appointment_location_form_label = "") {
    $tags = array();

    if(!empty($appointment_provider_form_label)) {
        $tags[] = capitalizeTheFirstLetterOfEachWord($appointment_provider_form_label);
    }

    if(!empty($appointment_location_form_label)) {
        $tags[] = capitalizeTheFirstLetterOfEachWord($appointment_location_form_label);
    }

    if(!empty($insurance_multiple_label) && is_array($insurance_multiple_label)) {
        foreach ($insurance_multiple_label as $iml) {
            $tags[] = capitalizeTheFirstLetterOfEachWord($iml);
        } 
    }

    return $tags;
}

function capitalizeTheFirstLetterOfEachWord($words = array()) {
    $words = strtolower($words);
    $separateWord = explode(" ", $words);

    for ($i = 0; $i < count($separateWord); $i++) {
      $separateWord[$i] = ucfirst($separateWord[$i]);
    }

    return implode(" ", $separateWord);
}

function updateSynccontactsData($id = "", $param = array()) {
    if(!empty($id) && !empty($param)) {
        $binds = array();
        $updatesetStr = array();

        foreach ($param as $columnkey => $columnvalue) {
            $updatesetStr[] =  $columnkey . " = ? ";
            $binds[] = $columnvalue;
        }

        $updatesetStr = implode(", ", $updatesetStr);
        $binds[] = $id;

        if(!empty($updatesetStr)) {
            sqlQueryNoLog("UPDATE `vh_mautic_synccontacts` SET " . $updatesetStr . " WHERE id = ? ", $binds);
        }
    }
}

//Get State/Region Details
function getStateDetails($stateVal) {
    global $idb_connection;
    if(!empty($stateVal)) {
        $pgsql = "select description from c_region where name = '" . $stateVal . "' limit 1";
        $pgsqlresult = pg_query($idb_connection, $pgsql);
        $description = "";
        
        while ($row = pg_fetch_assoc($pgsqlresult)) {
            if(isset($row['description'])) {
                $description = $row['description'];
            }
        }

        return $description;
    }
    return false;
}

function getProId($providerVal = "") { 
    global $idb_connection;
    if(!empty($providerVal)) {
        $pgsql = "select cb.value, cb.name from PC_Provider_IDs prov,C_BPartner cb where prov.C_BPartner_ID=cb.C_BPartner_ID and prov.PC_NPI = '" . $providerVal . "'";
        $pgsqlresult = pg_query($idb_connection, $pgsql);
        $providerId = "";
        
        while ($row = pg_fetch_assoc($pgsqlresult)) {
            if(isset($row['value'])) {
                $providerId = $row['value'];
            }
        }

        return $providerId;
    }
    return false;
}

function getInsurnceId($insVal, $insNameVal = "") {
    global $idb_connection, $idb_client_id;

    if(!empty($insVal)) {
        $pgsql = "SELECT C_Bpartner_ID, cb.VH_OEMR_UniqueKey, cb.value FROM C_BPartner cb,C_BP_Group grp WHERE cb.AD_Client_ID= " . $idb_client_id . " AND cb.C_BP_Group_ID = grp.C_BP_Group_ID AND UPPER(cb.VH_OEMR_UniqueKey)=UPPER('" . $insVal . "') AND grp.VH_bpgrp_type='PAY'";
        $pgsqlresult = pg_query($idb_connection, $pgsql);
        $insId = "";
        
        while ($row = pg_fetch_assoc($pgsqlresult)) {
            if(isset($row['value'])) {
                $insId = $row['value'];
            }
        }

        if(empty($insId) && !empty($insNameVal)) {
            $pgsql1 = "SELECT C_Bpartner_ID, cb.VH_OEMR_UniqueKey, cb.value FROM C_BPartner cb,C_BP_Group grp WHERE cb.AD_Client_ID= " . $idb_client_id . " AND cb.C_BP_Group_ID = grp.C_BP_Group_ID AND UPPER(cb.Name)=UPPER('" . $insNameVal . "') AND grp.VH_bpgrp_type='PAY'";
            $pgsqlresult1 = pg_query($idb_connection, $pgsql1);
            while ($row1 = pg_fetch_assoc($pgsqlresult1)) {
                if(isset($row1['value'])) {
                    $insId = $row1['value'];
                }
            }
        }

        return $insId;
    }
    return false;
}

//Get Appointment Location Details
function getAppointmentLocation($locationVal) {
    if(!empty($locationVal)) {
        $locationData = sqlQuery("SELECT name, id from facility f WHERE f.id = ? limit 1", array($locationVal));

        return $locationData;
    }
    return array();
}

// Get AppointmentLocationId
function getAppointmentLocationId($locationVal = "") { 
    global $idb_connection, $idb_client_id;
    if(!empty($locationVal)) {
        $pgsql = "select ca.value,ca.name from C_Activity ca where ca.vh_oemr_uniquekey = '" . $locationVal . "' And ad_client_id = '" . $idb_client_id . "'";
        $pgsqlresult = pg_query($idb_connection, $pgsql);
        $locationId = "";
        
        while ($row = pg_fetch_assoc($pgsqlresult)) {
            if(isset($row['value'])) {
                $locationId = $row['value'];
            }
        }

        return $locationId;
    }
    return false;
}

//Get recent appointment data
function getRecentApptData($data = array()) {
    global $idb_connection, $idb_client_id; 

    $pid = isset($data['pid']) ? $data['pid'] : "";
    $returnData = array();

    if(empty($data)) {
        return $returnData;
    }

    $binds = array();
    $whereStr = array();

    if(!empty($pid)) {
        $whereStr[] = " ope.pc_pid = $pid ";
        $binds[] = $pid;
    }

    $whereStr = implode(" AND ", $whereStr);
    if(!empty($whereStr)) $whereStr = " AND " . $whereStr;

    $apptItem = sqlQuery("SELECT ope.* from openemr_postcalendar_events ope where ope.pc_apptstatus not in ('x','?','%') " . $whereStr . " order by ope.pc_eventdate desc limit 1;");

    if(!empty($apptItem)) {
        unset($apptItem['uuid']);

        $returnData = $apptItem;

        $caseItem = sqlQuery("SELECT fc.id, fc.provider_id, ic.id as ins_id, ic.name as ins_name, u.fname as provider_fname, u.lname as provider_lname, u.npi from form_cases fc left join insurance_data id on id.id = fc.ins_data_id1 left join insurance_companies ic on ic.id = id.provider left join users u on u.id = fc.provider_id where fc.id = ? ", array($apptItem['pc_case']));

        if(!empty($caseItem)) {
            $providerName = array();
            if(!empty($caseItem['provider_fname'])) $providerName[] = $caseItem['provider_fname'];
            if(!empty($caseItem['provider_lname'])) $providerName[] = $caseItem['provider_lname'];
            $providerName = implode(" ", $providerName);

            $returnData['appointment_insurance_for_label'] = isset($caseItem['ins_name']) ? trim($caseItem['ins_name']) : "";
            $returnData['appointment_insurance_for'] = trim(getInsurnceId($caseItem['ins_id'], $returnData['appointment_insurance_for_label']));

            $returnData['appointment_provider_form'] = getProId($caseItem['npi']);
            $returnData['appointment_provider_form_label'] = $providerName;

            // Get Facility Data
            $facilityData = getAppointmentLocation($apptItem["pc_facility"]);

            $returnData['appointment_location_form'] = getAppointmentLocationId($facilityData['id']);
            $returnData['appointment_location_form_label'] = isset($facilityData['name']) ? $facilityData['name'] : "";
            
        }
    }

    return $returnData;
}

// Mautci Contact Sync
$sql = sqlStatement("SELECT vms.* from vh_mautic_synccontacts vms where vms.status = 0 order by created_date ASC");
while ($syncrow = sqlFetchArray($sql)) {

    $row_id = isset($syncrow['id']) ? $syncrow['id'] : "";
    
    try {

        $row_tablename = isset($syncrow['tablename']) ? $syncrow['tablename'] : "";
        $row_uniqueid = isset($syncrow['uniqueid']) ? $syncrow['uniqueid'] : "";
        $pc_pid = isset($syncrow['pid']) ? $syncrow['pid'] : "";

        if(empty($row_tablename) || empty($row_uniqueid) || empty($pc_pid)) {
            continue;
        }

        // Get Patient data
        $patientData = sqlQueryNoLog("SELECT * from patient_data pd where pid = ? limit 1", array($pc_pid));

        $pubpid = isset($patientData["pubpid"]) ? $patientData["pubpid"] : "";
        if(empty($pubpid)) {
            continue;
        }

        $mapped_gender = isset($patientData["sex"]) ? $patientData["sex"] : "";
        $email_consent = isset($patientData["hipaa_allowemail"]) ? $patientData["hipaa_allowemail"]: "n";
        $sms_consent = isset($patientData["hipaa_allowsms"]) ? $patientData["hipaa_allowsms"]: "n";

        if(!empty($email_consent) && $email_consent == "YES") {
            $email_consent = "y";
        } else {
            $email_consent = "n";
        }

        if(!empty($sms_consent) && $sms_consent == "YES") {
            $sms_consent = "y";
        } else {
            $sms_consent = "n";
        }

        // Get State Details
        $stateRes = getStateDetails($patientData["state"]);
        $state_label = "";

        if($stateRes !== false && $stateRes != null && $stateRes != "") {
            $state_label = $stateRes;
        }

        if(in_array($row_tablename, array("openemr_postcalendar_events", "patient_data", "form_cases"))) {

            // Get recent_appointment_date
            $recentAppt = getRecentApptData(array("pid" => $pc_pid));

            if(empty($recentAppt)) {
                throw new \Exception("Empty appt data");
            }

            $insurance_multiple_label = array();

            // Generate tags
            $contact_tags = getTagsForContact($recentAppt['appointment_provider_form_label'], $insurance_multiple_label, $recentAppt['appointment_location_form_label']);


            $body_params = array(
                "message_type" => "DFT",
                "firstname" => isset($patientData["fname"]) ? $patientData["fname"] : "",
                "lastname" => isset($patientData["lname"]) ? $patientData["lname"] : "",
                "email" => isset($patientData["email_direct"]) ? $patientData["email_direct"] : "",
                "phone" => isset($patientData["phone_home"]) ? $patientData["phone_home"] : "",
                "mobile" => isset($patientData["phone_cell"]) ? $patientData["phone_cell"] : "",
                "phone_biz" => isset($patientData["phone_biz"]) ? $patientData["phone_biz"] : "",
                "gender" => $mapped_gender,
                "birthday" => isset($patientData["DOB"]) ? $patientData["DOB"] : "",
                "address1" => isset($patientData["street"]) ? $patientData["street"] : "",
                "address2" => "",
                "city" => isset($patientData["city"]) ? $patientData["city"] : "",
                "state" => $state_label,
                "zipcode" => isset($patientData["postal_code"]) ? $patientData["postal_code"] : "",
                "country" => "United States",
                "pid" => $pubpid,
                "recent_appointment_date" => $recentAppt['pc_eventDate'] ." ". $recentAppt['pc_startTime'],
                "appointment_insurance_for" => isset($recentAppt['appointment_insurance_for']) ? $recentAppt['appointment_insurance_for'] : "",
                "appointment_insurance_for_label" => isset($recentAppt['appointment_insurance_for_label']) ? $recentAppt['appointment_insurance_for_label'] : "",
                "appointment_provider_form" => isset($recentAppt['appointment_provider_form']) ? $recentAppt['appointment_provider_form'] : "",
                "appointment_provider_form_label" => isset($recentAppt['appointment_provider_form_label']) ? $recentAppt['appointment_provider_form_label'] : "",
                "appointment_location_form" => isset($recentAppt['appointment_location_form']) ? $recentAppt['appointment_location_form'] : "",
                "appointment_location_form_label" => isset($recentAppt['appointment_location_form_label']) ? $recentAppt['appointment_location_form_label'] : "",
                "tags" => $contact_tags,
                "email_consent" => $email_consent,
                "sms_consent" => $sms_consent,
                "owner" => $mautic_contact_owner
            );

            if(empty($body_params) || empty($pc_pid) || empty($pubpid)) {
                throw new \Exception("Empty param data or pid");
            }

            // Sync contact details to mautic
            $syncContactData = syncContact($pubpid, $body_params);

            if(!empty($syncContactData) && $syncContactData['status'] === true) {

                // Update Row Status
                updateSynccontactsData($row_id, array("status" => "1", "sent_date" => date("Y-m-d H:i:s")));

            } else {
                throw new \Exception($syncContactData['error']);
            }
        }

    } catch (\Throwable $e) {
        // Add error log
        addLogs($e->getMessage(), true);

        // Update Error Status
        updateSynccontactsData($row_id, array("status" => "2", "sent_date" => date("Y-m-d H:i:s")));
    }
    
}

// Close pg sql connection
pg_close($idb_connection); 
