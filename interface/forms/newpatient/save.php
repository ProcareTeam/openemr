<?php

/**
 * Encounter form save script.
 *
 * @package   OpenEMR
 * @link      http://www.open-emr.org
 * @author    Roberto Vasquez <robertogagliotta@gmail.com>
 * @author    Brady Miller <brady.g.miller@gmail.com>
 * @copyright Copyright (c) 2015 Roberto Vasquez <robertogagliotta@gmail.com>
 * @copyright Copyright (c) 2019 Brady Miller <brady.g.miller@gmail.com>
 * @license   https://github.com/openemr/openemr/blob/master/LICENSE GNU General Public License 3
 */

require_once(__DIR__ . "/../../globals.php");
require_once("$srcdir/forms.inc.php");
require_once("$srcdir/encounter.inc.php");
require_once("$srcdir/wmt-v2/case_functions.inc.php");

use OpenEMR\Common\Acl\AclMain;
use OpenEMR\Common\Csrf\CsrfUtils;
use OpenEMR\Services\CodeTypesService;
use OpenEMR\Services\EncounterService;
use OpenEMR\Services\FacilityService;
use OpenEMR\Services\ListService;

if (!CsrfUtils::verifyCsrfToken($_POST["csrf_token_form"])) {
    CsrfUtils::csrfNotVerified();
}

$facilityService = new FacilityService();
$encounterService = new EncounterService();

if ($_POST['mode'] == 'new' && ($GLOBALS['enc_service_date'] == 'hide_both' || $GLOBALS['enc_service_date'] == 'show_edit')) {
    $date = (new DateTime())->format('Y-m-d H:i:s');
} elseif ($_POST['mode'] == 'update' && ($GLOBALS['enc_service_date'] == 'hide_both' || $GLOBALS['enc_service_date'] == 'show_new')) {
    $enc_from_id = sqlQuery("SELECT `encounter` FROM `form_encounter` WHERE `id` = ?", [intval($_POST['id'])]);
    $enc = $encounterService->getEncounterById($enc_from_id['encounter']);
    $enc_data = $enc->getData();
    $date = $enc_data[0]['date'];
} else {
    $date = isset($_POST['form_date']) ? DateTimeToYYYYMMDDHHMMSS($_POST['form_date']) : null;
}
$defaultPosCode = $encounterService->getPosCode($_POST['facility_id']);
$onset_date = isset($_POST['form_onset_date']) ? DateTimeToYYYYMMDDHHMMSS($_POST['form_onset_date']) : null;
$sensitivity = $_POST['form_sensitivity'] ?? null;
$pc_catid = $_POST['pc_catid'] ?? null;
$facility_id = $_POST['facility_id'] ?? null;
$billing_facility = $_POST['billing_facility'] ?? '';
$reason = $_POST['reason'] ?? null;
$mode = $_POST['mode'] ?? null;
$referral_source = $_POST['form_referral_source'] ?? null;
$class_code = $_POST['class_code'] ?? '';
$pos_code = (empty($_POST['pos_code'])) ? $defaultPosCode : $_POST['pos_code'];
$in_collection = $_POST['in_collection'] ?? null;
$parent_enc_id = $_POST['parent_enc_id'] ?? null;
$encounter_provider = $_POST['provider_id'] ?? null;
$referring_provider_id = $_POST['referring_provider_id'] ?? null;
//save therapy group if exist in external_id column
$external_id = isset($_POST['form_gid']) ? $_POST['form_gid'] : '';
$ordering_provider_id = $_POST['ordering_provider_id'] ?? null;

$discharge_disposition = $_POST['discharge_disposition'] ?? null;
$discharge_disposition = $discharge_disposition != '_blank' ? $discharge_disposition : null;

$facilityresult = $facilityService->getById($facility_id);
$facility = $facilityresult['name'];

// @VH: Set and prepare provider, supervisor and case field data for save [V100012]
// And if case is not selected and 'force_new' value set then use recent case
$provider_id = $_POST['provider_id'] ?? null;
$supervisor_id = $_POST['supervisor_id'] ?? null;
$case_id = $_POST['form_case'] ?? null;
if(!$case_id || $case_id == '0') {
  $case_id = mostRecentCase($pid, $_POST['force_new']);
}
// End

$normalurl = "patient_file/encounter/encounter_top.php";

$nexturl = $normalurl;

// @VH: Commented original line [V100012]
//$provider_id = $_SESSION['authUserID'] ? $_SESSION['authUserID'] : 0;
$provider_id = $encounter_provider ? $encounter_provider : $provider_id;

$encounter_type = $_POST['encounter_type'] ?? '';
$encounter_type_code = null;
$encounter_type_description = null;
// we need to lookup the codetype and the description from this if we have one
if (!empty($encounter_type)) {
    $listService = new ListService();
    $option = $listService->getListOption('encounter-types', $encounter_type);
    $encounter_type_code = $option['codes'] ?? null;
    if (!empty($encounter_type_code)) {
        $codeService = new CodeTypesService();
        $encounter_type_description = $codeService->lookup_code_description($encounter_type_code) ?? null;
    } else {
        // we don't have any codes installed here so we will just use the encounter_type
        $encounter_type_code = $encounter_type;
        $encounter_type_description = $option['title'];
    }
}

if ($mode == 'new') {
    $encounter = generate_id();
    // @VH: added 'supervisor_id' to param list for save [V100013]
    $data = [
        'date' => $date,
        'onset_date' => $onset_date,
        'reason' => $reason,
        'facility' => $facility,
        'pc_catid' => $pc_catid,
        'facility_id' => $facility_id,
        'billing_facility' => $billing_facility,
        'sensitivity' => $sensitivity,
        'referral_source' => $referral_source,
        'pid' => $pid,
        'encounter' => $encounter,
        'pos_code' => $pos_code,
        'class_code' => $class_code,
        'external_id' => $external_id,
        'parent_encounter_id' => $parent_enc_id,
        'provider_id' => $provider_id,
        'discharge_disposition' => $discharge_disposition,
        'referring_provider_id' => $referring_provider_id,
        'encounter_type_code' => $encounter_type_code,
        'encounter_type_description' => $encounter_type_description,
        'in_collection' => $in_collection,
        'ordering_provider_id' => $ordering_provider_id,
        'supervisor_id' => $supervisor_id
    ];

    $col_string = implode(" = ?, ", array_keys($data)) . " = ?";
    $sql = sprintf("INSERT INTO form_encounter SET %s", $col_string);
    $enc_id = sqlInsert($sql, array_values($data));

    addForm($encounter, "New Patient Encounter", $enc_id, "newpatient", $pid, $userauthorized, $date);
} elseif ($mode == 'update') {
    $id = $_POST["id"];
    $result = sqlQuery("SELECT encounter, sensitivity FROM form_encounter WHERE id = ?", array($id));
    if ($result['sensitivity'] && !AclMain::aclCheckCore('sensitivities', $result['sensitivity'])) {
        die(xlt("You are not authorized to see this encounter."));
    }

    $encounter = $result['encounter'];
    // See view.php to allow or disallow updates of the encounter date.
    $datepart = "";
    $sqlBindArray = array();
    if (AclMain::aclCheckCore('encounters', 'date_a')) {
        $datepart = "date = ?, ";
        $sqlBindArray[] = $date;
    }
    // @VH: added 'supervisor_id' to param list for update [V100013]
    array_push(
        $sqlBindArray,
        $onset_date,
        $provider_id,
        $reason,
        $facility,
        $pc_catid,
        $facility_id,
        $billing_facility,
        $sensitivity,
        $referral_source,
        $class_code,
        $pos_code,
        $discharge_disposition,
        $referring_provider_id,
        $encounter_type_code,
        $encounter_type_description,
        $in_collection,
        $ordering_provider_id,
        $supervisor_id,
        $id
    );
    $col_string = implode(" = ?, ", [
        'onset_date',
        'provider_id',
        'reason',
        'facility',
        'pc_catid',
        'facility_id',
        'billing_facility',
        'sensitivity',
        'referral_source',
        'class_code',
        'pos_code',
        'discharge_disposition',
        'referring_provider_id',
        'encounter_type_code',
        'encounter_type_description',
        'in_collection',
        'ordering_provider_id',
        'supervisor_id'
    ]) . " =?";
    sqlStatement("UPDATE form_encounter SET $datepart $col_string WHERE id = ?", $sqlBindArray);
} else {
    die("Unknown mode '" . text($mode) . "'");
}

setencounter($encounter);

// Update the list of issues associated with this encounter.
if (!empty($_POST['issues']) && is_array($_POST['issues'])) {
    sqlStatement("DELETE FROM issue_encounter WHERE " .
        "pid = ? AND encounter = ?", array($pid, $encounter));
    foreach ($_POST['issues'] as $issue) {
        $query = "INSERT INTO issue_encounter ( pid, list_id, encounter ) VALUES (?,?,?)";
        sqlStatement($query, array($pid, $issue, $encounter));
    }
}

// @VH: Save case field value [V100012]
if($case_id) {
    $exists = sqlQuery('SELECT * FROM case_appointment_link WHERE ' .
        'enc_case = ?', array($case_id));
    if(!isset($exists['pid'])) $exists['pid'] = '';
    if($exists['pid']) {
        $msg = 'Patient Mis-Match - Case ['.$case_id.'] Is Currently Linked To PID ('.$exists['pid'].') And This Encounter is PID -'.$pid.'-';
        if($pid != $exists['pid']) die($msg);
    }
    $exists = sqlQuery('SELECT id, pid FROM form_cases WHERE ' .
        'id = ?', array($case_id));
    if(!isset($exists['pid'])) $exists['pid'] = '';
    if($exists['pid']) {
        $msg = 'Patient Mis-Match - Case ['.$case_id.'] Is Currently Attached To PID ('.$exists['pid'].') And This Encounter is PID -'.$pid.'-';
        if($pid != $exists['pid']) die($msg);
    }
    sqlInsert('INSERT INTO case_appointment_link SET enc_case = ?, '.
        'encounter = ?, pid = ? ON DUPLICATE KEY UPDATE enc_case = ?',
        array($case_id, $encounter, $pid, $case_id));
    $link = sqlQuery('SELECT * FROM case_appointment_link WHERE ' .
        'encounter = ?', array($encounter));
    if($link['pc_eid'] && ($link['pc_pid'] == $pid || !$link['pc_pid'])) {
        sqlStatement('UPDATE openemr_postcalendar_events SET pc_case ' .
            '= ? WHERE pc_eid = ?', array($case_id, $link['pc_eid']));
    }
}
// End

$result4 = sqlStatement("SELECT fe.encounter,fe.date,openemr_postcalendar_categories.pc_catname FROM form_encounter AS fe " .
    " left join openemr_postcalendar_categories on fe.pc_catid=openemr_postcalendar_categories.pc_catid  WHERE fe.pid = ? order by fe.date desc", array($pid));
?>
<html>
<body>
    <script>
        EncounterDateArray = Array();
        CalendarCategoryArray = Array();
        EncounterIdArray = Array();
        Count = 0;
        <?php
        if (sqlNumRows($result4) > 0) {
            while ($rowresult4 = sqlFetchArray($result4)) {
                ?>
        EncounterIdArray[Count] =<?php echo js_escape($rowresult4['encounter']); ?>;
        EncounterDateArray[Count] =<?php echo js_escape(oeFormatShortDate(date("Y-m-d", strtotime($rowresult4['date'])))); ?>;
        CalendarCategoryArray[Count] =<?php echo js_escape(xl_appt_category($rowresult4['pc_catname'])); ?>;
        Count++;
                <?php
            }
        }
        ?>

        // Get the left_nav window, and the name of its sibling (top or bottom) frame that this form is in.
        // This works no matter how deeply we are nested

        var my_left_nav = top.left_nav;
        var w = window;
        for (; w.parent != top; w = w.parent) ;
        var my_win_name = w.name;
        my_left_nav.setPatientEncounter(EncounterIdArray, EncounterDateArray, CalendarCategoryArray);
        top.restoreSession();
        <?php if ($mode == 'new') { ?>
        my_left_nav.setEncounter(<?php echo js_escape(oeFormatShortDate($date)) . ", " . js_escape($encounter) . ", window.name"; ?>);
        // Load the tab set for the new encounter, w is usually the RBot frame.
        w.location.href = '<?php echo "$rootdir/patient_file/encounter/encounter_top.php"; ?>';
        <?php } else { // not new encounter ?>
        // Always return to encounter summary page.
        window.location.href = '<?php echo "$rootdir/patient_file/encounter/forms.php"; ?>';
        <?php } // end if not new encounter ?>

    </script>
</body>
</html>
