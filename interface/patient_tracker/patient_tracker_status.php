<?php

/**
 * Patient Tracker Status Editor
 *
 * This allows entry and editing of current status for the patient from within patient tracker and updates the status on the calendar.
 * Contains a drop down for the Room information driven by the list Patient Flow Board Rooms.
 *
 * @package   OpenEMR
 * @link      http://www.open-emr.org
 * @author    Terry Hill <terry@lillysystems.com>
 * @author    Brady Miller <brady.g.miller@gmail.com>
 * @copyright Copyright (c) 2015 Terry Hill <terry@lillysystems.com>
 * @copyright Copyright (c) 2017-2018 Brady Miller <brady.g.miller@gmail.com>
 * @license   https://github.com/openemr/openemr/blob/master/LICENSE GNU General Public License 3
 */

require_once("../globals.php");
require_once("$srcdir/options.inc.php");
require_once("$srcdir/forms.inc.php");
require_once("$srcdir/encounter_events.inc.php");
require_once("$srcdir/patient_tracker.inc.php");
require_once("$srcdir/wmt-v2/case_functions.inc.php");

use OpenEMR\Common\Csrf\CsrfUtils;
use OpenEMR\Core\Header;

// @VH: Get pending form and order details
function getPendingItemsDetails($pc_eid) {
    ob_start();
    $form_eid = $pc_eid;
    $patient_info_status = false;
    $c_info_status = false;
    $pending_form_info_status = true;
    $pending_order_info_status = true;
    include_once($GLOBALS['incdir'] . "/main/calendar/ajax/calendar_ajax.php");
    $content = ob_get_clean();
    return $content;
}

if (!empty($_GET)) {
    if (!CsrfUtils::verifyCsrfToken($_GET["csrf_token_form"])) {
        CsrfUtils::csrfNotVerified();
    }
}

# Get the information for fields
$tracker_id = $_GET['tracker_id'];
// @VH: Set array element
$updateMultipleStatus = isset($_GET['update_mutiple_status']) && $_GET['update_mutiple_status'] == "1" ? true : false;
$original_tracker_id = $tracker_id;
$tracker_id = explode(",", $tracker_id);
if (empty($tracker_id)) {
    echo xlt("Something went wrong tracker id");
    exit();
}
// END
// @VH: Changed to mutiple items
$tresult = sqlStatement("SELECT patient_tracker.id as tracker_id, apptdate, appttime, patient_tracker_element.room AS lastroom, " .
                        "patient_tracker_element.status AS laststatus, eid, random_drug_test, encounter, pid " .
                        "FROM patient_tracker " .
                        "LEFT JOIN patient_tracker_element " .
                        "ON patient_tracker.id = patient_tracker_element.pt_tracker_id " .
                        "AND patient_tracker.lastseq = patient_tracker_element.seq " .
                        "WHERE patient_tracker.id IN (" . implode(',', $tracker_id) . ")", array());

// @VH: Added muitple status update
$trow = array();
while ($titems = sqlFetchArray($tresult)) {
    $trow[] = $titems;
}
// @VH: If mutiple allowed
$tkpid = '';
$appttime = '';
$apptdate = '';
$pceid = '';
$theroom = '';

if ($updateMultipleStatus === false && count($trow) > 0) {
$tkpid = $trow[0]['pid'];
$appttime = $trow[0]['appttime'];
$apptdate = $trow[0]['apptdate'];
$pceid = $trow[0]['eid'];
$theroom = '';
}

?>

<html>
    <head>
        <?php Header::setupHeader(['common','opener']); ?>
    </head>

<?php
if (!empty($_POST['statustype'])) {
    if (!CsrfUtils::verifyCsrfToken($_POST["csrf_token_form"])) {
        CsrfUtils::csrfNotVerified();
    }

    $status = $_POST['statustype'];
    if (strlen($_POST['roomnum']) != 0) {
         $theroom = $_POST['roomnum'];
    }

    // @VH: Get Variable [V100024]
    // @VH: allow muitple items update support.
    foreach ($trow as $ritem) {
    $tkpid = $ritem['pid'];
    $appttime = $ritem['appttime'];
    $apptdate = $ritem['apptdate'];
    $pceid = $ritem['eid'];
    // END
    //@VH: Get Variable
    $form_todaysEncounterIf = (isset($_POST['form_todaysEncounterIf']) && isset($_POST['form_todaysEncounterIf'][$ritem['tracker_id']]) && $_POST['form_todaysEncounterIf'][$ritem['tracker_id']] == '1') ? true : false;

    # Manage tracker status. Also auto create encounter, if applicable.
    if (!empty($tkpid)) {
        // if an encounter is found it is returned to be carried forward with status changes.
        // otherwise 0 which is table default.
        $is_tracker = is_tracker_encounter_exist($apptdate, $appttime, $tkpid, $pceid);
        if ($GLOBALS['auto_create_new_encounters'] && $apptdate == date('Y-m-d') && (is_checkin($status) == '1') && !$is_tracker) {
            # Gather information for encounter fields
            // @VH: Added pc_case, pc_title [V100024]
            $genenc = sqlQuery("select pc_catid as category, pc_hometext as reason, pc_aid as provider, pc_facility as facility, pc_billing_location as billing_facility, pc_case, pc_title " .
                      "from openemr_postcalendar_events where pc_eid =? ", array($pceid));
            // @VH: Replaced reason with pc_title and replaced function [V100024]
            $encounter = todaysEncounterEventCheck($tkpid, $apptdate, $genenc['pc_title'], $genenc['facility'], $genenc['billing_facility'], $genenc['provider'], $genenc['category'], false, $form_todaysEncounterIf);

            // @VH: Changes [V100024]
            if($encounter){
               // @VH: Alert message [V100024]
               $info_msg .= xl("New encounter created with id");
               $info_msg .= " $encounter for appt $pceid \\n";

               if (count($trow) === 1) {
                    // @VH: Get pending form and order details
                    $pendingDetails = getPendingItemsDetails($pceid);
                    if (!empty($pendingDetails)) {
                        $info_msg .= "\\n" . str_replace(['<br>', '<br />'], "\\n", $pendingDetails);
                    }
                }

               echo "Inserting For ($pceid) [$encounter] ($tkpid)<br>\n";
               echo "Case (".$genenc['pc_case'].")<br>\n";
                sqlInsert('INSERT INTO case_appointment_link SET '.
                'pc_eid = ?, encounter = ?, pid = ?, enc_case = ?  ON DUPLICATE KEY ' .
                'UPDATE encounter = ?, pid = ?, enc_case = ?', 
                array($pceid, $encounter, $tkpid, $genenc['pc_case'], 
                $encounter, $tkpid, $genenc['pc_case']));
                // addMiscBilling($encounter, $tkpid, $genenc['pc_case']);
           }
           // End

            # Capture the appt status and room number for patient tracker. This will map the encounter to it also.
            if (!empty($pceid)) {
                manage_tracker_status($apptdate, $appttime, $pceid, $tkpid, $_SESSION["authUser"], $status, $theroom, $encounter);
            }
        } else {
            # Capture the appt status and room number for patient tracker.
            if (!empty($pceid)) {
                manage_tracker_status($apptdate, $appttime, $pceid, $tkpid, $_SESSION["authUser"], $status, $theroom, $is_tracker);
            }
        }
    }

    }

    echo "<body>\n<script>\n";

    // @VH: Alert message show [V100024]
    if(!empty($info_msg)) {
        echo " alert(\"" . $info_msg . "\")\n";
    }

    echo " window.opener.document.flb.submit();\n";
    echo " dlgclose();\n";
    echo "</script></body></html>\n";
    exit();
}

// @VH: Wrap into is mutiple condition
if ($updateMultipleStatus === false) {
#get the patient name for display
$row = sqlQuery("select fname, lname " .
"from patient_data where pid =? limit 1", array($tkpid));
}
?>

<!-- @VH: Check encounter [V100024] -->
<script type="text/javascript">
    async function todaysEncounterIf() {
        <?php
        // @VH: allow muitple items update support.
        foreach ($trow as $ritem) {
        $tkpid = $ritem['pid'];
        $appttime = $ritem['appttime'];
        $apptdate = $ritem['apptdate'];
        $pceid = $ritem['eid'];
        // END
        ?>
        var f = document.forms[0];
        var responce = await $.ajax({
            url: '<?php echo $GLOBALS['webroot'] . "/interface/main/calendar/ajax/add_edit_event_ajax.php?type=check&eid=$pceid"; ?>',
            type: 'POST',
            contentType: 'application/x-www-form-urlencoded',
            data: {
                form_appttime: '<?php echo $appttime; ?>',
                form_date: '<?php echo $apptdate; ?>',
                form_pid: '<?php echo $tkpid; ?>',
                form_action: 'save',
                form_apptstatus: document.querySelector('#statustype').value
            }
        });

        var resObj = JSON.parse(responce);
        if(resObj.encounter == true) {
            <?php 
            // @VH: Wrap into condition
            if ($updateMultipleStatus === false) { 
            ?>

            if (confirm("An encounter already exists for this patient for this day, do you still want to create a new encounter?")) {
                $('#form_todaysEncounterIf_<?php echo $ritem['tracker_id']; ?>').val("1");
            } else {
                $('#form_todaysEncounterIf_<?php echo $ritem['tracker_id']; ?>').val("0");
            }

            <?php } else { ?>
            if (confirm("An encounter already exists for this patient for this day, do you still want to create a new encounter? (Appt: <?php echo $pceid; ?>)")) {
                $('#form_todaysEncounterIf_<?php echo $ritem['tracker_id']; ?>').val("1");
            } else {
                $('#form_todaysEncounterIf_<?php echo $ritem['tracker_id']; ?>').val("0");
            }
            <?php } ?>
        }

        <?php
        // @VH: END
        }
        ?>
    }

    async function formSubmitBtn() {
        await todaysEncounterIf();

        // Submit form
        document.getElementById("form_note").submit();
    }
</script>
<!-- END -->

<body>
    <div class="container mt-3">
        <div class="row">
            <div class="col-12">
                <?php 
                // @VH: Wrap into is mutiple condition
                if ($updateMultipleStatus === false) {
                ?>
                <h2><?php echo xlt('Change Status for') . " " . text($row['fname']) . " " . text($row['lname']); ?></h2>
                <?php } else { ?>
                <h2><?php echo xlt('Change Status for Appts'); ?></h2>
                <?php } ?>
            </div>
        </div>
        <!-- @VH: Changed id -->
        <form id="form_note" method="post" action="patient_tracker_status.php?tracker_id=<?php echo attr_url($original_tracker_id) ?>&csrf_token_form=<?php echo attr_url(CsrfUtils::collectCsrfToken()); ?>" enctype="multipart/form-data" >
            <input type="hidden" name="csrf_token_form" value="<?php echo attr(CsrfUtils::collectCsrfToken()); ?>" />

            <!-- @VH: Added "form_todaysEncounterIf" and "form_todaysTherapyGroupEncounterIf" input field. [V100024] -->
            <?php 
            foreach ($trow as $trowitem) {
                ?>
                <input type="hidden" name="form_todaysEncounterIf[<?php echo $trowitem['tracker_id']; ?>]" id="form_todaysEncounterIf_<?php echo $trowitem['tracker_id']; ?>" value="0">
                <?php
            }
            ?>

            <div class="form-group">
                <label for="statustype"><?php echo xlt('Status Type'); ?></label>
                <?php echo generate_select_list('statustype', 'apptstat', $updateMultipleStatus === false && count($trow) > 0  ? $trow[0]['laststatus'] : '', xl('Status Type')); ?>
            </div>
            <div class="form-group">
                <label for="roomnum"><?php  echo xlt('Exam Room Number'); ?></label>
                <?php echo generate_select_list('roomnum', 'patient_flow_board_rooms', $updateMultipleStatus === false && count($trow) > 0 ? $trow[0]['lastroom'] : '', xl('Exam Room Number')); ?>
            </div>
            <div class="position-override">
                <div class="btn-group" role="group">
                    <!-- @VH: Replace onclick [V100024] -->
                    <button type="button" class='btn btn-primary btn-save btn-sm' onclick='formSubmitBtn()'><?php echo xlt('Save')?></button>
                    <button type="button" class='btn btn-secondary btn-cancel btn-sm' onclick="dlgclose();" ><?php echo xlt('Cancel'); ?></button>
                </div>
            </div>
        </form>
    </div>
</body>
</html>

