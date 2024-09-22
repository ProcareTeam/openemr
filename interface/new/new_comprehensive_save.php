<?php

/**
 * new_comprehensive_save.php
 *
 * @package   OpenEMR
 * @link      http://www.open-emr.org
 * @author    Rod Roark <rod@sunsetsystems.com>
 * @author    Brady Miller <brady.g.miller@gmail.com>
 * @copyright Copyright (c) 2009-2017 Rod Roark <rod@sunsetsystems.com>
 * @copyright Copyright (c) 2018 Brady Miller <brady.g.miller@gmail.com>
 * @license   https://github.com/openemr/openemr/blob/master/LICENSE GNU General Public License 3
 */

require_once("../globals.php");
require_once("$srcdir/OemrAD/oemrad.globals.php");

use OpenEMR\Common\Csrf\CsrfUtils;
use OpenEMR\Services\ContactService;
use OpenEMR\Events\Patient\PatientBeforeCreatedAuxEvent;
use OpenEMR\OemrAd\EmailVerificationLib;
use OpenEMR\OemrAd\Demographicslib;

if (!CsrfUtils::verifyCsrfToken($_POST["csrf_token_form"])) {
    CsrfUtils::csrfNotVerified();
}

// Validation for non-unique external patient identifier.
$alertmsg = '';
if (!empty($_POST["form_pubpid"])) {
    $form_pubpid = trim($_POST["form_pubpid"]);
    $result = sqlQuery("SELECT count(*) AS count FROM patient_data WHERE " .
    "pubpid = ?", array($form_pubpid));
    if ($result['count']) {
        // Error, not unique.
        $alertmsg = xl('Warning: Patient ID is not unique!');
    }
}

require_once("$srcdir/pid.inc.php");
require_once("$srcdir/patient.inc.php");
require_once("$srcdir/options.inc.php");

// Update patient_data and employer_data:
// First, we prepare the data for insert into DB by querying the layout
// fields to see what valid fields we have to insert from the post we are receiving
$newdata = array();
$newdata['patient_data'] = array();
$newdata['employer_data'] = array();
$fres = sqlStatement("SELECT * FROM layout_options " .
  "WHERE form_id = 'DEM' AND (uor > 0 OR field_id = 'pubpid') AND field_id != '' " .
  "ORDER BY group_id, seq");
$addressFieldsToSave = array();
while ($frow = sqlFetchArray($fres)) {
    $data_type = $frow['data_type'];
    $field_id  = $frow['field_id'];
  // $value     = '';
    $colname   = $field_id;
    $tblname   = 'patient_data';
    if (strpos($field_id, 'em_') === 0) {
        $colname = substr($field_id, 3);
        $tblname = 'employer_data';
    }

  //get value only if field exist in $_POST (prevent deleting of field with disabled attribute)
    // TODO: why is this a different conditional than demographics_save.php...
    if ($data_type == 54) { // address list
        $addressFieldsToSave[$field_id] = get_layout_form_value($frow);
    } else if (isset($_POST["form_$field_id"]) || $field_id == "pubpid") {
        $value = get_layout_form_value($frow);
        $newdata[$tblname][$colname] = $value;
    }
}

// Use the global helper to use the PatientService to create a new patient
// The result contains the pid, so use that to set the global session pid
$pid = updatePatientData(null, $newdata['patient_data'], true);
if (empty($pid)) {
    die("Internal error: setpid(" . text($pid) . ") failed!");
}
setpid($pid);
if (!$GLOBALS['omit_employers']) {
    updateEmployerData($pid, $newdata['employer_data'], true);
}

// @VH: Save Changes [V100050][V100051]
EmailVerificationLib::updateEmailVerification($_POST);
Demographicslib::dem_after_save();
// End

if (!empty($addressFieldsToSave)) {
    // TODO: we would handle other types of address fields here, for now we will just go through and populate the patient
    // address information
    // TODO: how are error messages supposed to display if the save fails?
    foreach ($addressFieldsToSave as $field => $addressFieldData) {
        // if we need to save other kinds of addresses we could do that here with our field column...
        $contactService = new ContactService();
        $contactService->saveContactsForPatient($pid, $addressFieldData);
    }
}

/**
 * Parse demographics data to listeners who want data that is not directly available in
 * the patient_data table on update
 */
$GLOBALS["kernel"]->getEventDispatcher()->dispatch(new PatientBeforeCreatedAuxEvent($pid, $_POST), PatientBeforeCreatedAuxEvent::EVENT_HANDLE, 10);

// @VH: Change [V100047]
if(isset($_POST['updateallpayer'])) $updateallpayer = $_POST['updateallpayer'];

if(isset($updateallpayer) && $updateallpayer == true) {
    $payercount = isset($_POST['ipayercount']) ? $_POST['ipayercount'] : 1;
    for ($i=1; $i <= $payercount ; $i++) {
        $type = "primary";
        $provider = filter_input(INPUT_POST, "i".$i."provider");
        $policy_number = filter_input(INPUT_POST, "i".$i."policy_number");
        $group_number = filter_input(INPUT_POST, "i".$i."group_number");
        $claim_number = filter_input(INPUT_POST, "i".$i."claim_number");
        $plan_name = filter_input(INPUT_POST, "i".$i."plan_name");
        $subscriber_lname = filter_input(INPUT_POST, "i".$i."subscriber_lname");
        $subscriber_mname = filter_input(INPUT_POST, "i".$i."subscriber_mname");
        $subscriber_fname = filter_input(INPUT_POST, "i".$i."subscriber_fname");
        $subscriber_relationship = filter_input(INPUT_POST, "form_i".$i."subscriber_relationship");
        $subscriber_ss = filter_input(INPUT_POST, "i".$i."subscriber_ss");
        $subscriber_DOB = DateToYYYYMMDD(filter_input(INPUT_POST, "i".$i."subscriber_DOB"));
        $subscriber_street = filter_input(INPUT_POST, "i".$i."subscriber_street");
        $subscriber_postal_code = filter_input(INPUT_POST, "i".$i."subscriber_postal_code");
        $subscriber_city = filter_input(INPUT_POST, "i".$i."subscriber_city");
        $subscriber_state = filter_input(INPUT_POST, "form_i".$i."subscriber_state");
        $subscriber_country = filter_input(INPUT_POST, "form_i".$i."subscriber_country");
        $subscriber_phone = filter_input(INPUT_POST, "i".$i."subscriber_phone");
        $subscriber_employer = filter_input(INPUT_POST, "i".$i."subscriber_employer");
        $subscriber_employer_street = filter_input(INPUT_POST, "i".$i."subscriber_employer_street");
        $subscriber_employer_city = filter_input(INPUT_POST, "i".$i."subscriber_employer_city");
        $subscriber_employer_postal_code = filter_input(INPUT_POST, "i".$i."subscriber_employer_postal_code");
        $subscriber_employer_state = filter_input(INPUT_POST, "form_i".$i."subscriber_employer_state");
        $subscriber_employer_country = filter_input(INPUT_POST, "form_i".$i."subscriber_employer_country");
        $copay = filter_input(INPUT_POST, "i".$i."copay");
        $subscriber_sex = filter_input(INPUT_POST, "form_i".$i."subscriber_sex");
        $effective_date = DateToYYYYMMDD(filter_input(INPUT_POST, "i".$i."effective_date"));
        $accept_assignment = filter_input(INPUT_POST, "i".$i."accept_assignment");
        $policy_type = filter_input(INPUT_POST, "i".$i."policy_type");
        $payer_inactive = filter_input(INPUT_POST, "i".$i."payer_inactive");
        $payerid = filter_input(INPUT_POST, "i".$i."payerid");

        /*OEMR - Change*/
        if(empty($effective_date)) {
            $eData = sqlStatement("SELECT id, `type`, `date` from insurance_data id where pid = ? and `date` IS NOT NULL order by id desc", array($pid));
            $eList = array();
            while($eRow = sqlFetchArray($eData)) { if(isset($eRow['date']) && !empty($eRow['date'])) { $eList[] = strtotime($eRow['date']); }
            }

            if(!empty($eList)) {
                $dayCount = 0;
                $effective_date = DateToYYYYMMDD(date('m/d/Y',strtotime(date('Y-m-d') . " +".$dayCount." day")));
                while (in_array(strtotime(date('Y-m-d') . " +".$dayCount." day"), $eList)) {
                    ++$dayCount;
                    $effective_date = DateToYYYYMMDD(date('m/d/Y',strtotime(date('Y-m-d') . " +".$dayCount." day")));
                }
            } else {
               $effective_date = DateToYYYYMMDD(date('m/d/Y')); 
            }
        }
        /* End */

        if($provider == "" && $payerid == "") continue;
        
        saveInsuranceData(
            array(
                "payerid" => $payerid,
                "pid" => $pid,
                "type" => $type,
                "provider" => $provider,
                "policy_number" => $policy_number,
                "group_number" => $group_number,
                "claim_number" => $claim_number,
                "plan_name" => $plan_name,
                "subscriber_lname" => $subscriber_lname,
                "subscriber_mname" => $subscriber_mname,
                "subscriber_fname" => $subscriber_fname,
                "subscriber_relationship" => $subscriber_relationship,
                "subscriber_ss" => $subscriber_ss,
                "subscriber_DOB" => $subscriber_DOB,
                "subscriber_street" => $subscriber_street,
                "subscriber_postal_code" => $subscriber_postal_code,
                "subscriber_city" => $subscriber_city,
                "subscriber_state" => $subscriber_state,
                "subscriber_country" => $subscriber_country,
                "subscriber_phone" => $subscriber_phone,
                "subscriber_employer" => $subscriber_employer,
                "subscriber_employer_street" => $subscriber_employer_street,
                "subscriber_employer_city" => $subscriber_employer_city,
                "subscriber_employer_postal_code" => $subscriber_employer_postal_code,
                "subscriber_employer_state" => $subscriber_employer_state,
                "subscriber_employer_country" => $subscriber_employer_country,
                "copay" => $copay,
                "subscriber_sex" => $subscriber_sex,
                "effective_date" => $effective_date,
                "accept_assignment" => $accept_assignment,
                "policy_type" => $policy_type,
                "payer_inactive" => $payer_inactive === "1" ? 1 : 0
            )
        );
    }
} else {

$i1dob = DateToYYYYMMDD(filter_input(INPUT_POST, "i1subscriber_DOB"));
$i1date = DateToYYYYMMDD(filter_input(INPUT_POST, "i1effective_date"));

newHistoryData($pid);
// no need to save insurance for simple demos
if (!$GLOBALS['simplified_demographics']) {
    newInsuranceData(
        $pid,
        "primary",
        filter_input(INPUT_POST, "i1provider"),
        filter_input(INPUT_POST, "i1policy_number"),
        filter_input(INPUT_POST, "i1group_number"),
        filter_input(INPUT_POST, "i1plan_name"),
        filter_input(INPUT_POST, "i1subscriber_lname"),
        filter_input(INPUT_POST, "i1subscriber_mname"),
        filter_input(INPUT_POST, "i1subscriber_fname"),
        filter_input(INPUT_POST, "form_i1subscriber_relationship"),
        filter_input(INPUT_POST, "i1subscriber_ss"),
        $i1dob,
        filter_input(INPUT_POST, "i1subscriber_street"),
        filter_input(INPUT_POST, "i1subscriber_postal_code"),
        filter_input(INPUT_POST, "i1subscriber_city"),
        filter_input(INPUT_POST, "form_i1subscriber_state"),
        filter_input(INPUT_POST, "form_i1subscriber_country"),
        filter_input(INPUT_POST, "i1subscriber_phone"),
        filter_input(INPUT_POST, "i1subscriber_employer"),
        filter_input(INPUT_POST, "i1subscriber_employer_street"),
        filter_input(INPUT_POST, "i1subscriber_employer_city"),
        filter_input(INPUT_POST, "i1subscriber_employer_postal_code"),
        filter_input(INPUT_POST, "form_i1subscriber_employer_state"),
        filter_input(INPUT_POST, "form_i1subscriber_employer_country"),
        filter_input(INPUT_POST, 'i1copay'),
        filter_input(INPUT_POST, 'form_i1subscriber_sex'),
        $i1date,
        filter_input(INPUT_POST, 'i1accept_assignment')
    );

    //Dont save more than one insurance since only one is allowed / save space in DB
    if (!$GLOBALS['insurance_only_one']) {
        $i2dob = DateToYYYYMMDD(filter_input(INPUT_POST, "i2subscriber_DOB"));
        $i2date = DateToYYYYMMDD(filter_input(INPUT_POST, "i2effective_date"));

        newInsuranceData(
            $pid,
            "secondary",
            filter_input(INPUT_POST, "i2provider"),
            filter_input(INPUT_POST, "i2policy_number"),
            filter_input(INPUT_POST, "i2group_number"),
            filter_input(INPUT_POST, "i2plan_name"),
            filter_input(INPUT_POST, "i2subscriber_lname"),
            filter_input(INPUT_POST, "i2subscriber_mname"),
            filter_input(INPUT_POST, "i2subscriber_fname"),
            filter_input(INPUT_POST, "form_i2subscriber_relationship"),
            filter_input(INPUT_POST, "i2subscriber_ss"),
            $i2dob,
            filter_input(INPUT_POST, "i2subscriber_street"),
            filter_input(INPUT_POST, "i2subscriber_postal_code"),
            filter_input(INPUT_POST, "i2subscriber_city"),
            filter_input(INPUT_POST, "form_i2subscriber_state"),
            filter_input(INPUT_POST, "form_i2subscriber_country"),
            filter_input(INPUT_POST, "i2subscriber_phone"),
            filter_input(INPUT_POST, "i2subscriber_employer"),
            filter_input(INPUT_POST, "i2subscriber_employer_street"),
            filter_input(INPUT_POST, "i2subscriber_employer_city"),
            filter_input(INPUT_POST, "i2subscriber_employer_postal_code"),
            filter_input(INPUT_POST, "form_i2subscriber_employer_state"),
            filter_input(INPUT_POST, "form_i2subscriber_employer_country"),
            filter_input(INPUT_POST, 'i2copay'),
            filter_input(INPUT_POST, 'form_i2subscriber_sex'),
            $i2date,
            filter_input(INPUT_POST, 'i2accept_assignment')
        );

        $i3dob = DateToYYYYMMDD(filter_input(INPUT_POST, "i3subscriber_DOB"));
        $i3date = DateToYYYYMMDD(filter_input(INPUT_POST, "i3effective_date"));

        newInsuranceData(
            $pid,
            "tertiary",
            filter_input(INPUT_POST, "i3provider"),
            filter_input(INPUT_POST, "i3policy_number"),
            filter_input(INPUT_POST, "i3group_number"),
            filter_input(INPUT_POST, "i3plan_name"),
            filter_input(INPUT_POST, "i3subscriber_lname"),
            filter_input(INPUT_POST, "i3subscriber_mname"),
            filter_input(INPUT_POST, "i3subscriber_fname"),
            filter_input(INPUT_POST, "form_i3subscriber_relationship"),
            filter_input(INPUT_POST, "i3subscriber_ss"),
            $i3dob,
            filter_input(INPUT_POST, "i3subscriber_street"),
            filter_input(INPUT_POST, "i3subscriber_postal_code"),
            filter_input(INPUT_POST, "i3subscriber_city"),
            filter_input(INPUT_POST, "form_i3subscriber_state"),
            filter_input(INPUT_POST, "form_i3subscriber_country"),
            filter_input(INPUT_POST, "i3subscriber_phone"),
            filter_input(INPUT_POST, "i3subscriber_employer"),
            filter_input(INPUT_POST, "i3subscriber_employer_street"),
            filter_input(INPUT_POST, "i3subscriber_employer_city"),
            filter_input(INPUT_POST, "i3subscriber_employer_postal_code"),
            filter_input(INPUT_POST, "form_i3subscriber_employer_state"),
            filter_input(INPUT_POST, "form_i3subscriber_employer_country"),
            filter_input(INPUT_POST, 'i3copay'),
            filter_input(INPUT_POST, 'form_i3subscriber_sex'),
            $i3date,
            filter_input(INPUT_POST, 'i3accept_assignment')
        );
    }
}

}
?>
<html>
<body>
<script>
<?php
if ($alertmsg) {
    echo "alert(" . js_escape($alertmsg) . ");\n";
}

  echo "window.location='$rootdir/patient_file/summary/demographics.php?" .
    "set_pid=" . attr_url($pid) . "&is_new=1';\n";
?>
</script>

</body>
</html>

