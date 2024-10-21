<?php

/**
 * addrbook_edit.php
 *
 * @package   OpenEMR
 * @link      http://www.open-emr.org
 * @author    Rod Roark <rod@sunsetsystems.com>
 * @author    Brady Miller <brady.g.miller@gmail.com>
 * @copyright Copyright (c) 2006-2010 Rod Roark <rod@sunsetsystems.com>
 * @copyright Copyright (c) 2018-2019 Brady Miller <brady.g.miller@gmail.com>
 * @license   https://github.com/openemr/openemr/blob/master/LICENSE GNU General Public License 3
 */

require_once("../globals.php");
require_once("$srcdir/options.inc.php");
require_once("$srcdir/OemrAD/oemrad.globals.php");

use OpenEMR\Common\Acl\AclMain;
use OpenEMR\Common\Csrf\CsrfUtils;
use OpenEMR\Common\Twig\TwigContainer;
use OpenEMR\Core\Header;
use OpenEMR\OemrAd\HubspotSync;
use OpenEMR\OemrAd\EmailVerificationLib;
use OpenEMR\OemrAd\WordpressWebservice;

if (!AclMain::aclCheckCore('admin', 'practice')) {
    echo (new TwigContainer(null, $GLOBALS['kernel']))->getTwig()->render('core/unauthorized.html.twig', ['pageTitle' => xl("Address Book")]);
    exit;
}

if (!empty($_POST)) {
    if (!CsrfUtils::verifyCsrfToken($_POST["csrf_token_form"])) {
        CsrfUtils::csrfNotVerified();
    }
}

// @VH: Care team communication type [V100079]
$care_team_communication_type = array(
    'email' => 'Email',
    'fax' => 'Fax',
    'postal_letter' => 'Postal Letter'
);

// Collect user id if editing entry
$userid = $_REQUEST['userid'] ?? '';

// Collect type if creating a new entry
$type = $_REQUEST['type'] ?? '';

$info_msg = "";

function invalue($name)
{
    if (empty($_POST[$name])) {
        return "''";
    }

    $fld = add_escape_custom(trim($_POST[$name]));
    return "'$fld'";
}

// @VH: Save Pi Storage preference values
function savepistoragepreference($userid)
{
    if (empty($userid)) {
        return;
    }

    $form_pharmacy = invalue('form_pharmacy');
    $form_behavioral_health = invalue('form_behavioral_health');
    $form_chiropractic_care = invalue('form_chiropractic_care');
    $form_communication = invalue('form_communication');
    $form_imaging = invalue('form_imaging');
    $form_neurology = invalue('form_neurology');
    $form_ortho = invalue('form_ortho');
    $form_pain_management = invalue('form_pain_management');

    $pistorage_preference_sql_row = sqlQuery("SELECT count(`id`) as total_count FROM `vh_pistorage_preference` WHERE `user_id` = ? ", array(trim($userid)));

    if (!empty($pistorage_preference_sql_row) && $pistorage_preference_sql_row['total_count'] > 0) {
        // Update record
        sqlStatement("UPDATE vh_pistorage_preference SET pharmacy = " . $form_pharmacy . ", behavioral_health = " . $form_behavioral_health . ", chiropractic_care = " . $form_chiropractic_care . ", communication = " . $form_communication . ", imaging = " . $form_imaging . ", neurology = " . $form_neurology . ", ortho = " . $form_ortho . ", pain_management = " . $form_pain_management . " WHERE user_id = ? ", array($userid));
    } else {
        // Insert record
        $pistorageid = sqlInsert("INSERT INTO vh_pistorage_preference (user_id, pharmacy, behavioral_health, chiropractic_care, communication, imaging, neurology, ortho, pain_management) VALUES (" . $userid . ", " . $form_pharmacy . ", " . $form_behavioral_health . ", " . $form_chiropractic_care . ", " . $form_communication . ", " . $form_imaging . ", " . $form_neurology . ", " . $form_ortho . ", " . $form_pain_management . ")");
    }
}

// @VH: Delete Pi Storage preference values
function deletepistoragepreference($userid)
{
    if (empty($userid)) {
        return;
    }

    // delete storage preference
    sqlStatement("DELETE FROM vh_pistorage_preference WHERE user_id = ? ", array($userid));
}

?>
<html>
<head>
<title><?php echo $userid ? xlt('Edit Entry') : xlt('Add New Entry') ?></title>
    <!-- @VH: added 'oemr_ad' -->
    <?php Header::setupHeader(['opener', 'oemr_ad']); ?>

<style>
.inputtext {
    padding-left: 2px;
    padding-right: 2px;
}
</style>

<script>

 var type_options_js = Array();
    <?php
    // Collect the type options. Possible values are:
    // 1 = Unassigned (default to person centric)
    // 2 = Person Centric
    // 3 = Company Centric
    $sql = sqlStatement("SELECT option_id, option_value FROM list_options WHERE " .
    "list_id = 'abook_type' AND activity = 1");
    while ($row_query = sqlFetchArray($sql)) {
        echo "type_options_js[" . js_escape($row_query['option_id']) . "]=" . js_escape($row_query['option_value']) . ";\n";
    }
    ?>

 // Process to customize the form by type
 function typeSelect(a) {
   if(a=='ord_lab'){
      $('#cpoe_span').css('display','inline');
  } else {
       $('#cpoe_span').css('display','none');
       $('#form_cpoe').prop('checked', false);
  }
  if (type_options_js[a] == 3) {
   // Company centric:
   //   1) Hide the person Name entries
   //   2) Hide the Specialty entry
   //   3) Show the director Name entries
   $(".nameRow").hide();
   $(".specialtyRow").hide();
   $(".nameDirectorRow").show();
  }
  else {
   // Person centric:
   //   1) Hide the director Name entries
   //   2) Show the person Name entries
   //   3) Show the Specialty entry
   $(".nameDirectorRow").hide();
   $(".nameRow").show();
   $(".specialtyRow").show();
  }
 }

 // @VH: Changes [V100079]
 function checkCommunicationType() {
    var ct_type = $('#form_abook_type').val();

    if(ct_type == "Referral Source") {
        $('#ct_communication_container').show();
        $('#form_care_team_communication').prop('disabled', false);
    } else {
        $('#ct_communication_container').hide();
        $('#form_care_team_communication').prop('disabled', true);
    }
 }

 // @VH: Changes [V100079]
 function isCommunicationDataValid() {
    var cm_type = $('#form_care_team_communication').val();
        var form_email = $('input[name="form_email"]').val();
        var form_phonecell = $('input[name="form_phonecell"]').val();
        var form_fax = $('input[name="form_fax"]').val();

        var form_street = $('input[name="form_street"]').val();
        var form_city = $('input[name="form_city"]').val();
        var form_state = $('input[name="form_state"]').val();
        var form_zip = $('input[name="form_zip"]').val();

        var errorList = [];

        if(cm_type == "email") {
            if(form_email.trim() == "") {
               errorList.push("Please enter email."); 
            }
        } else if(cm_type == "sms") {
            if(form_phonecell.trim() == "") {
               errorList.push("Please enter mobile no."); 
            }
        } else if(cm_type == "fax") {
            if(form_fax.trim() == "") {
               errorList.push("Please enter fax number."); 
            }
        } else if(cm_type == "postal_letter") {
            if(form_street.trim() == "") {
               errorList.push("Please enter main address."); 
            }

            if(form_city.trim() == "") {
               errorList.push("Please enter city."); 
            }

            if(form_state.trim() == "") {
               errorList.push("Please enter state."); 
            }

            if(form_zip.trim() == "") {
               errorList.push("Please enter zip."); 
            }
        }


        if(errorList.length > 0) {
            var errorMsg = "Corresponding delivery information must be entered for the chosen care team delivery method\n\n";
            errorMsg = errorMsg + errorList.join("\n");
            return errorMsg
        }

        return true;
 }

 function validateForm() {
    let mailformat = /^\w+([\.-]?\w+)*@\w+([\.-]?\w+)*(\.\w{2,8})+$/;
    let email_val = document.querySelector('input[name="form_email"]').value;
    let npi_val = document.querySelector('input[name="form_npi"]').value;
    var statusVal = $('#form_email_hidden_verification_status').val();

    // @VH: [V100080]
    if(email_val != "" && !email_val.match(mailformat)) {
        alert("Please enter valid email.");
        return false;
    }

    // @VH: [V100080]
    if(email_val != "" && statusVal && statusVal == "0") {
        if (!confirm("Do you want to continue with unverified email?")) {
            return false;
        }
    }

    // @VH: Changes [V100079]
    var cData = isCommunicationDataValid();
    if(cData !== true) {
        alert(cData);
        return false;
    }

    if (email_val != "" || npi_val != "") {

        let ajax_action = "both";
        let isActive = document.querySelector('input[name="form_active"]');
        if(!isActive.checked) {
            ajax_action = "validate_npi";
        }

        // @VH: Check email or npi id is used by other user or not. [V100081]
        $.ajax({
            url: 'user_validate_ajax.php?action=' + ajax_action + '&id=<?php echo attr_url($userid) ?>',
            type: 'post',
            data: $("#theform").serialize(),
            success: function(response) {
                let responseJson = JSON.parse(response);
                if (responseJson.hasOwnProperty('status') && responseJson['status'] === true) {
                    alert(responseJson['msg']);
                    return false;
                }

                $('input[name="form_save"]').click();
            },
            error: function(jqXHR, textStatus, errorThrown) {
              // Handle AJAX request errors
              console.log('AJAX Error:', textStatus, errorThrown);
              // Optionally, display an error message to the user
            }
        });
        
    } else {
        // Submit form
        $('input[name="form_save"]').click();
    }

    return false;
 }

 $(document).ready(function(){

    // @VH: Check Communication Type [V100079]
    checkCommunicationType();

    $('#form_abook_type').change(function(){
        checkCommunicationType();
    });

    // $('#theform').submit(function() {
    //     var cData = isCommunicationDataValid();

    //     if(cData !== true) {
    //         alert(cData);
    //         return false;
    //     }

    //     return true;
    // });
 });
// End
</script>

<!-- @VH: Email verification [V100080] -->
<?php include_once("$srcdir/email_verification.js.php") ?>
<!-- END --> 

</head>

<body class="body_top">
<?php
 // If we are saving, then save and close the window.
 //
if (!empty($_POST['form_save'])) {
 // Collect the form_abook_type option value
 //  (ie. patient vs company centric)
    $type_sql_row = sqlQuery("SELECT `option_value` FROM `list_options` WHERE `list_id` = 'abook_type' AND `option_id` = ? AND activity = 1", array(trim($_POST['form_abook_type'])));
    $option_abook_type = $type_sql_row['option_value'] ?? '';
 // Set up any abook_type specific settings
    if ($option_abook_type == 3) {
        // Company centric
        $form_title = invalue('form_director_title');
        $form_fname = invalue('form_director_fname');
        $form_lname = invalue('form_director_lname');
        $form_mname = invalue('form_director_mname');
        $form_suffix = invalue('form_director_suffix');
    } else {
        // Person centric
        $form_title = invalue('form_title');
        $form_fname = invalue('form_fname');
        $form_lname = invalue('form_lname');
        $form_mname = invalue('form_mname');
        $form_suffix = invalue('form_suffix');
    }

    if ($userid) {
        // @VH: Added ct_communication, active [V100079]
        $query = "UPDATE users SET " .
        "abook_type = "   . invalue('form_abook_type')   . ", " .
        "title = "        . $form_title                  . ", " .
        "fname = "        . $form_fname                  . ", " .
        "lname = "        . $form_lname                  . ", " .
        "mname = "        . $form_mname                  . ", " .
        "suffix = "       . $form_suffix                 . ", " .
        "specialty = "    . invalue('form_specialty')    . ", " .
        "organization = " . invalue('form_organization') . ", " .
        "valedictory = "  . invalue('form_valedictory')  . ", " .
        "assistant = "    . invalue('form_assistant')    . ", " .
        "federaltaxid = " . invalue('form_federaltaxid') . ", " .
        "upin = "         . invalue('form_upin')         . ", " .
        "npi = "          . invalue('form_npi')          . ", " .
        "taxonomy = "     . invalue('form_taxonomy')     . ", " .
        "cpoe = "         . invalue('form_cpoe')         . ", " .
        "email = "        . invalue('form_email')        . ", " .
        "email_direct = " . invalue('form_email_direct') . ", " .
        "url = "          . invalue('form_url')          . ", " .
        "street = "       . invalue('form_street')       . ", " .
        "streetb = "      . invalue('form_streetb')      . ", " .
        "city = "         . invalue('form_city')         . ", " .
        "state = "        . invalue('form_state')        . ", " .
        "zip = "          . invalue('form_zip')          . ", " .
        "street2 = "      . invalue('form_street2')      . ", " .
        "streetb2 = "     . invalue('form_streetb2')     . ", " .
        "city2 = "        . invalue('form_city2')        . ", " .
        "state2 = "       . invalue('form_state2')       . ", " .
        "zip2 = "         . invalue('form_zip2')         . ", " .
        "phone = "        . invalue('form_phone')        . ", " .
        "phonew1 = "      . invalue('form_phonew1')      . ", " .
        "phonew2 = "      . invalue('form_phonew2')      . ", " .
        "phonecell = "    . invalue('form_phonecell')    . ", " .
        "fax = "          . invalue('form_fax')          . ", " .
        "notes = "        . invalue('form_notes')        . ", " .
        "ct_communication = " . invalue('form_care_team_communication') . ", "  .
        "active = "       . (isset($_REQUEST['form_active']) ? 1 : 0)       . " "  .
        "WHERE id = '" . add_escape_custom($userid) . "'";
        sqlStatement($query);

        // @VH: Save Pi Storage preference values
        savepistoragepreference($userid);

        // @VH: Hubspot Handle update [V100082]
        HubspotSync::handleInSyncPrepare($userid, "UPDATE");
        
        // @VH: WordPress user update [V100082]
        WordpressWebservice::handleInSyncPrepare($userid, "UPDATE");
    } else {
        // @VH: Added ct_communication [V100079]
        $userid = sqlInsert("INSERT INTO users ( " .
        "username, password, authorized, info, source, " .
        "title, fname, lname, mname, suffix, " .
        "federaltaxid, federaldrugid, upin, facility, see_auth, active, npi, taxonomy, cpoe, " .
        "specialty, organization, valedictory, assistant, billname, email, email_direct, url, " .
        "street, streetb, city, state, zip, " .
        "street2, streetb2, city2, state2, zip2, " .
        "phone, phonew1, phonew2, phonecell, fax, notes, abook_type, ct_communication "            .
        ") VALUES ( "                        .
        "'', "                               . // username
        "'', "                               . // password
        "0, "                                . // authorized
        "'', "                               . // info
        "NULL, "                             . // source
        $form_title                   . ", " .
        $form_fname                   . ", " .
        $form_lname                   . ", " .
        $form_mname                   . ", " .
        $form_suffix                  . ", " .
        invalue('form_federaltaxid')  . ", " .
        "'', "                               . // federaldrugid
        invalue('form_upin')          . ", " .
        "'', "                               . // facility
        "0, "                                . // see_auth
        "1, "                                . // active
        invalue('form_npi')           . ", " .
        invalue('form_taxonomy')      . ", " .
        invalue('form_cpoe')          . ", " .
        invalue('form_specialty')     . ", " .
        invalue('form_organization')  . ", " .
        invalue('form_valedictory')   . ", " .
        invalue('form_assistant')     . ", " .
        "'', "                               . // billname
        invalue('form_email')         . ", " .
        invalue('form_email_direct')  . ", " .
        invalue('form_url')           . ", " .
        invalue('form_street')        . ", " .
        invalue('form_streetb')       . ", " .
        invalue('form_city')          . ", " .
        invalue('form_state')         . ", " .
        invalue('form_zip')           . ", " .
        invalue('form_street2')       . ", " .
        invalue('form_streetb2')      . ", " .
        invalue('form_city2')         . ", " .
        invalue('form_state2')        . ", " .
        invalue('form_zip2')          . ", " .
        invalue('form_phone')         . ", " .
        invalue('form_phonew1')       . ", " .
        invalue('form_phonew2')       . ", " .
        invalue('form_phonecell')     . ", " .
        invalue('form_fax')           . ", " .
        invalue('form_notes')         . ", " .
        invalue('form_abook_type')    . ", "  .
        invalue('form_care_team_communication')    . " " .
        ")");

        // @VH: Save Pi Storage preference values
        savepistoragepreference($userid);

        // @VH: Hubspot Handle Update [V100082]
        HubspotSync::handleInSyncPrepare($userid, "INSERT");

        // @VH: WordPress user update [V100082]
        WordpressWebservice::handleInSyncPrepare($userid, "INSERT");
    }

    // @VH: Save Changes [V100080]
    EmailVerificationLib::updateEmailVerification($_POST); 
} elseif (!empty($_POST['form_delete'])) {
    if ($userid) {

        // OEMR - Hubspot Handle Update [V100082]
        HubspotSync::handleInSyncPrepare($userid, "DELETE");

        // OEMR - WordPress user delete [V100082]
        WordpressWebservice::handleInSyncPrepare($userid, "DELETE");

        // @VH: Delete Pi Storage preference values
        deletepistoragepreference($userid);

       // Be careful not to delete internal users.
        sqlStatement("DELETE FROM users WHERE id = ? AND username = ''", array($userid));
    }
}

if (!empty($_POST['form_save']) || !empty($_POST['form_delete'])) {
  // Close this window and redisplay the updated list.
    echo "<script>\n";
    if ($info_msg) {
        echo " alert(" . js_escape($info_msg) . ");\n";
    }

    echo " window.close();\n";
    echo " if (opener.refreshme) opener.refreshme();\n";
    echo "</script></body></html>\n";
    exit();
}

if ($userid) {
    $row = sqlQuery("SELECT * FROM users WHERE id = ?", array($userid));
}

if ($type) { // note this only happens when its new
  // Set up type
    $row['abook_type'] = $type;
}

?>

<script>
 $(function () {
  // customize the form via the type options
  typeSelect(<?php echo js_escape($row['abook_type'] ?? null); ?>);
  if(typeof abook_type != 'undefined' && abook_type == 'ord_lab') {
    $('#cpoe_span').css('display','inline');
   }
 });
</script>

<form method='post' name='theform' id="theform" action='addrbook_edit.php?userid=<?php echo attr_url($userid) ?>'>
<input type="hidden" name="csrf_token_form" value="<?php echo attr(CsrfUtils::collectCsrfToken()); ?>" />
<?php if (AclMain::aclCheckCore('admin', 'practice')) { // allow choose type option if have admin access ?>
<div class="form-row">
    <div class='col-2'>
        <label class="font-weight-bold col-form-label col-form-label-sm"><?php echo xlt('Type'); ?>:</label>
    </div>
    <div class="col">
        <?php echo generate_select_list('form_abook_type', 'abook_type', ($row['abook_type'] ?? null), '', 'Unassigned', 'form-control-sm', 'typeSelect(this.value)'); ?>
    </div>
</div>
<?php } // end of if has admin access ?>

<div class="form-row nameRow my-1">
    <div class="col-auto">
        <label for="title" class="font-weight-bold col-form-label col-form-label-sm"><?php echo xlt('Name'); ?>:</label>
    </div>
    <div class="col-auto">
        <?php generate_form_field(array('data_type' => 1,'field_id' => 'title','smallform' => 'true','list_id' => 'titles','empty_title' => ' '), ($row['title'] ?? '')); ?>
    </div>
    <div class="col-auto">
        <label for="form_lname" class="font-weight-bold col-form-label col-form-label-sm"><?php echo xlt('Last{{Name}}'); ?>:</label>
    </div>
    <div class="col-auto">
        <input type='text' size='10' name='form_lname' class='form-control form-control-sm inputtext' maxlength='50' value='<?php echo attr($row['lname'] ?? ''); ?>'/>
    </div>
    <div class="col-auto">
        <label for="form_fname" class="font-weight-bold col-form-label col-form-label-sm"><?php echo xlt('First{{Name}}'); ?>:</label>
    </div>
    <div class="col-auto">
        <input type='text' size='10' name='form_fname' class='form-control form-control-sm inputtext' maxlength='50' value='<?php echo attr($row['fname'] ?? ''); ?>' />
    </div>
    <div class="col-auto">
        <label for="form_mname" class="font-weight-bold col-form-label col-form-label-sm"><?php echo xlt('Middle{{Name}}'); ?>:</label>
    </div>
    <div class="col-auto">
        <input type='text' size='4' name='form_mname' class='form-control form-control-sm inputtext' maxlength='50' value='<?php echo attr($row['mname'] ?? ''); ?>' />
    </div>
    <div class="col-auto">
        <label for="form_suffix" class="font-weight-bold col-form-label col-form-label-sm"><?php echo xlt('Suffix'); ?>:</label>
    </div>
    <div class="col-auto">
        <input type='text' size='4' name='form_suffix' class='form-control form-control-sm inputtext' maxlength='50' value='<?php echo attr($row['suffix'] ?? ''); ?>' />
    </div>
</div>

<!-- @VH: Added Active field [V100079] -->
<div class="form-row activeRow my-1">
    <div class="col-12">
        <label for="form_active" class="font-weight-bold col-form-label col-form-label-sm"><?php echo xlt('Active'); ?>:</label>
        <input type="checkbox" name="form_active" <?php echo ($row["active"]) ? " checked" : ""; ?>>
    </div>
</div>
<!-- END -->

<div class="form-row specialtyRow my-1">
    <div class="col-2">
        <label for="form_specialty" class="font-weight-bold col-form-label col-form-label-sm"><?php echo xlt('Specialty'); ?>:</label>
    </div>
    <div class="col">
        <input type='text' size='40' name='form_specialty' maxlength='250' value='<?php echo attr($row['specialty'] ?? ''); ?>' class='form-control form-control-sm inputtext w-100' />
    </div>
</div>

<div class="form-row my-1">
    <div class="col-2">
        <label for="form_organization" class="font-weight-bold col-form-label col-form-label-sm"><?php echo xlt('Organization'); ?>:</label>
    </div>
    <div class="col">
        <input type='text' size='40' name='form_organization' maxlength='250' value='<?php echo attr($row['organization'] ?? ''); ?>' class='form-control form-control-sm inputtext' />
    <span id='cpoe_span' style="display:none;">
        <input type='checkbox' title="<?php echo xla('CPOE'); ?>" name='form_cpoe' id='form_cpoe' value='1' <?php echo (!empty($row['cpoe']) && ($row['cpoe'] == '1')) ? "CHECKED" : ""; ?>/>
        <label for='form_cpoe' class="font-weight-bold"><?php echo xlt('CPOE'); ?></label>
   </span>
    </div>
</div>
<div class="nameDirectorRow">
    <label for="director_title" class="font-weight-bold col-form-label col-form-label-sm"><?php echo xlt('Director Name'); ?>:</label>
    <div class="form-row my-1">
        <div class="col-auto">
            <?php
            generate_form_field(array('data_type' => 1,'field_id' => 'director_title','smallform' => 'true','list_id' => 'titles','empty_title' => ' '), ($row['title'] ?? ''));
            ?>
        </div>
        <div class="col-auto">
            <label for="form_director_lname" class="font-weight-bold col-form-label col-form-label-sm"><?php echo xlt('Last{{Name}}'); ?>:</label>
        </div>
        <div class="col-auto">
            <input type='text' size='10' name='form_director_lname' class='form-control form-control-sm inputtext' maxlength='50' value='<?php echo attr($row['lname'] ?? ''); ?>'/>
        </div>
        <div class="col-auto">
            <label for="form_director_fname" class="font-weight-bold col-form-label col-form-label-sm"><?php echo xlt('First{{Name}}'); ?>:</label>
        </div>
        <div class="col-auto">
            <input type='text' size='10' name='form_director_fname' class='form-control form-control-sm inputtext' maxlength='50' value='<?php echo attr($row['fname'] ?? ''); ?>' />
        </div>
        <div class="col-auto">
            <label for="form_director_mname" class="font-weight-bold col-form-label col-form-label-sm"><?php echo xlt('Middle{{Name}}'); ?>:</label>
        </div>
        <div class="col-auto">
            <input type='text' size='4' name='form_director_mname' class='form-control form-control-sm inputtext' maxlength='50' value='<?php echo attr($row['mname'] ?? ''); ?>' />
        </div>
        <div class="col-auto">
            <label for="form_director_suffix" class="font-weight-bold col-form-label col-form-label-sm"><?php echo xlt('Suffix'); ?>:</label>
        </div>
        <div class="col-auto">
            <input type='text' size='4' name='form_director_suffix' class='form-control form-control-sm inputtext' maxlength='50' value='<?php echo attr($row['suffix'] ?? ''); ?>' />
        </div>
    </div>
</div>

<div class="form-row my-1">
    <div class="col-2">
        <label for="form_valedictory" class="font-weight-bold col-form-label col-form-label-sm"><?php echo xlt('Valedictory'); ?>:</label>
    </div>
    <div class="col">
        <input type='text' size='40' name='form_valedictory' maxlength='250' value='<?php echo attr($row['valedictory'] ?? ''); ?>' class='form-control form-control-sm inputtext' />
    </div>
</div>

<!-- @VH: Added care team communication field [V100079] -->
<div id="ct_communication_container" class="form-row my-1">
    <div class="col-4">
        <label for="form_valedictory" class="font-weight-bold col-form-label col-form-label-sm"><?php echo xlt('Default Care team Communication'); ?>:</label>
    </div>
    <div class="col">
        <select name="form_care_team_communication" id="form_care_team_communication" class="form-control form-control-sm">
            <option value="">Unassigned</option>
            <?php
                foreach ($care_team_communication_type as $ct => $cType) {
                    if($row['ct_communication'] == $ct) {
                        echo '<option value="'.$ct.'" selected>'.$cType.'</option>';   
                    } else {
                        echo '<option value="'.$ct.'">'.$cType.'</option>';
                    }
                }
            ?>
        </select>
    </div>
</div>
<!-- End -->

<div class="form-row my-1">
    <div class="col-2">
        <label for="form_phone" class="font-weight-bold col-form-label col-form-label-sm"><?php echo xlt('Home Phone'); ?>:</label>
    </div>
    <div class="col">
        <input type='text' size='11' name='form_phone' value='<?php echo attr($row['phone'] ?? ''); ?>' maxlength='30' class='form-control form-control-sm inputtext' />
    </div>
    <div class="col-2">
        <label for="form_phonecell" class="font-weight-bold col-form-label col-form-label-sm"><?php echo xlt('Mobile'); ?>:</label>
    </div>
    <div class="col">
        <input type='text' size='11' name='form_phonecell' maxlength='30' value='<?php echo attr($row['phonecell'] ?? ''); ?>' class='form-control form-control-sm inputtext' />
    </div>
</div>
<div class="form-row my-1">
    <div class="col-2">
        <label for="form_phonew1" class="font-weight-bold col-form-label col-form-label-sm"><?php echo xlt('Work Phone'); ?>:</label>
    </div>
    <div class="col">
        <input type='text' size='11' name='form_phonew1' value='<?php echo attr($row['phonew1'] ?? ''); ?>' maxlength='30' class='form-control form-control-sm inputtext' />
    </div>
    <div class="col-1">
        <label class="font-weight-bold col-form-label col-form-label-sm"><?php echo xlt('2nd'); ?>:</label>
    </div>
    <div class="col">
        <input type='text' size='11' name='form_phonew2' value='<?php echo attr($row['phonew2'] ?? ''); ?>' maxlength='30' class='form-control form-control-sm inputtext' />
    </div>
    <div class="col-1">
        <label class="font-weight-bold col-form-label col-form-label-sm"><?php echo xlt('Fax'); ?>:</label>
    </div>
    <div class="col">
        <input type='text' size='11' name='form_fax' value='<?php echo attr($row['fax'] ?? ''); ?>' maxlength='30' class='form-control form-control-sm inputtext' />
    </div>
</div>

<div class="form-row my-1">
    <div class="col-2">
        <label for="form_assistant" class="font-weight-bold col-form-label col-form-label-sm"><?php echo xlt('Assistant'); ?>:</label>
    </div>
    <div class="col-10">
        <input type='text' size='40' name='form_assistant' maxlength='250' value='<?php echo attr($row['assistant'] ?? ''); ?>' class='form-control form-control-sm inputtext w-100' />
    </div>
</div>

<div class="form-row my-1">
    <div class="col-2">
        <label for="form_email" class="font-weight-bold col-form-label col-form-label-sm"><?php echo xlt('Email'); ?>:</label>
    </div>
    <div class='col-10'>
        <!-- @VH: Email verification field changes [V100080] -->
        <?php $emvStatus = EmailVerificationLib::getEmailVerificationData($row['email']); ?>
        <div class="emv-input-group-container" data-initemail="<?php echo attr($row['email'] ?? ''); ?>" data-initstatus="<?php echo $emvStatus; ?>" data-id="form_email">
            <div class="input-group">
                <input type='text' size='40' id="form_email" name='form_email' maxlength='250' value='<?php echo attr($row['email'] ?? ''); ?>' class='form-control form-control-sm inputtext mw-100' />

                <div class="input-group-append">
                    <input type="hidden" name="form_email_hidden_verification_status" value="" id="form_email_hidden_verification_status" class="hidden_verification_status">
                    <button type="button" id="form_email_btn_verify_email" class="btn btn-primary btn-sm btn_verify_email mb-1"><?php echo xlt('Verify'); ?></button>
                </div>
            </div>
            <div class="status-icon-container"></div>
        </div>
        <!-- END -->
    </div>
</div>

<!-- @VH: Hide 'Trusted Email' field -->
<div class="form-row my-1" style="display:none;">
    <div class="col-2">
        <label for="form_email_direct" class="font-weight-bold col-form-label col-form-label-sm"><?php echo xlt('Trusted Email'); ?>:</label>
    </div>
    <div class="col-10">
        <input type='text' size='40' name='form_email_direct' maxlength='250' value='<?php echo attr($row['email_direct'] ?? ''); ?>' class='form-control form-control-sm inputtext' />
    </div>
</div>

<div class="form-row my-1">
    <div class="col-2">
        <label for="form_url" class="font-weight-bold col-form-label col-form-label-sm"><?php echo xlt('Website'); ?>:</label>
    </div>
    <div class="col-10">
        <input type='text' size='40' name='form_url' maxlength='250' value='<?php echo attr($row['url'] ?? ''); ?>' class='form-control form-control-sm inputtext' />
    </div>
</div>

<div class="form-row my-1 align-items-center">
    <div class="col-2">
        <label for="form_street form_streetb" class="font-weight-bold col-form-label col-form-label-sm"><?php echo xlt('Main Address'); ?>:</label>
    </div>
    <div class="col-10">
        <input type='text' size='40' name='form_street' maxlength='60' value='<?php echo attr($row['street'] ?? ''); ?>' class='form-control form-control-sm inputtext mb-1' placeholder="<?php echo xla('Address Line 1'); ?>" />
        <input type='text' size='40' name='form_streetb' maxlength='60' value='<?php echo attr($row['streetb'] ?? ''); ?>' class='form-control form-control-sm inputtext mt-1' placeholder="<?php echo xla('Address Line 2'); ?>" />
    </div>
</div>

<div class="form-row my-1">
    <div class="col-2">
        <label for="form_city" class="font-weight-bold col-form-label col-form-label-sm"><?php echo xlt('City'); ?>:</label>
    </div>
    <div class="col">
        <input type='text' size='10' name='form_city' maxlength='30' value='<?php echo attr($row['city'] ?? ''); ?>' class='form-control form-control-sm inputtext' placeholder="<?php echo xla('City'); ?>" />
    </div>
    <div class="col-2">
        <label for="form_state" class="font-weight-bold col-form-label col-form-label-sm"><?php echo xlt('State') . "/" . xlt('county'); ?>:</label>
    </div>
    <div class="col">
        <?php echo generate_select_list('form_state', 'state', ($row['state'] ?? null), '', 'Unassigned', 'form-control-sm', 'typeSelect(this.value)'); ?>
    </div>
    <div class="col-2">
        <label for="form_zip" class="font-weight-bold col-form-label col-form-label-sm"><?php echo xlt('Postal code'); ?>:</label>
    </div>
    <div class="col">
        <input type='text' size='10' name='form_zip' maxlength='20' value='<?php echo attr($row['zip'] ?? ''); ?>' class='form-control form-control-sm inputtext' placeholder="<?php echo xla('Postal code'); ?>" />
    </div>
</div>

<div class="form-row my-1 align-items-center">
    <div class="col-2">
        <label for="form_street2 form_streetb2" class="font-weight-bold col-form-label col-form-label-sm"><?php echo xlt('Alt Address'); ?>:</label>
    </div>
    <div class="col-10">
        <input type='text' size='40' name='form_street2' maxlength='60' value='<?php echo attr($row['street2'] ?? ''); ?>' class='form-control form-control-sm mb-1 inputtext' placeholder="<?php echo xla('Address Line 1'); ?>" />
        <input type='text' size='40' name='form_streetb2' maxlength='60' value='<?php echo attr($row['streetb2'] ?? ''); ?>' class='form-control form-control-sm mt-1 inputtext' placeholder="<?php echo xla('Address Line 2'); ?>" />
    </div>
</div>

<div class="form-row my-1">
    <div class="col-2">
        <label for="form_city2" class="font-weight-bold col-form-label col-form-label-sm"><?php echo xlt('Alt City'); ?>:</label>
    </div>
    <div class="col-auto">
        <input type='text' size='10' name='form_city2' maxlength='30' value='<?php echo attr($row['city2'] ?? ''); ?>' class='form-control form-control-sm inputtext' placeholder="<?php echo xla('Alt City'); ?>" />
    </div>
    <div class="col-auto">
        <label for="form_state2" class="font-weight-bold col-form-label col-form-label-sm"><?php echo xlt('Alt State') . "/" . xlt('county'); ?>:</label>
    </div>
    <div class="col-auto">
    <?php echo generate_select_list('form_state2', 'state', ($row['state2'] ?? null), '', 'Unassigned', 'form-control-sm', 'typeSelect(this.value)'); ?>
    </div>
    <div class="col-auto">
        <label for="form_zip2" class="font-weight-bold col-form-label col-form-label-sm"><?php echo xlt('Alt Postal code'); ?>:</label>
    </div>
    <div class="col-auto">
        <input type='text' size='10' name='form_zip2' maxlength='20' value='<?php echo attr($row['zip2'] ?? ''); ?>' class='form-control form-control-sm inputtext' placeholder="<?php echo xla('Alt Postal code'); ?>" />
    </div>
</div>

<div class="form-row my-1">
    <div class="col-auto">
        <label for="form_upin" class="font-weight-bold col-form-label col-form-label-sm"><?php echo xlt('UPIN'); ?>:</label>
    </div>
    <div class="col-auto">
        <input type='text' size='6' name='form_upin' maxlength='6' value='<?php echo attr($row['upin'] ?? ''); ?>' class='form-control form-control-sm inputtext' />
   </div>
   <div class="col-auto">
        <label for="form_npi" class="font-weight-bold col-form-label col-form-label-sm"><?php echo xlt('NPI'); ?>:</label>
   </div>
   <div class="col-auto">
        <input type='text' size='10' name='form_npi' maxlength='10' value='<?php echo attr($row['npi'] ?? ''); ?>' class='form-control form-control-sm inputtext' />
   </div>
   <div class="col-auto">
        <label for="form_federaltaxid" class="font-weight-bold col-form-label col-form-label-sm"><?php echo xlt('TIN'); ?>:</label>
   </div>
   <div class="col-auto">
        <input type='text' size='10' name='form_federaltaxid' maxlength='10' value='<?php echo attr($row['federaltaxid'] ?? ''); ?>' class='form-control form-control-sm inputtext' />
    </div>
    <div class="col-auto">
        <label for="form_taxonomy" class="font-weight-bold col-form-label col-form-label-sm"><?php echo xlt('Taxonomy'); ?>:</label>
    </div>
   <div class="col-auto">
        <input type='text' size='10' name='form_taxonomy' maxlength='10' value='<?php echo attr($row['taxonomy'] ?? ''); ?>' class='form-control form-control-sm inputtext' />
   </div>
</div>
<div class="form-group">
    <label for="form_notes" class="font-weight-bold col-form-label col-form-label-sm"><?php echo xlt('Notes'); ?>:</label>
    <textarea rows='3' cols='40' name='form_notes' wrap='virtual' class='form-control inputtext w-100'><?php echo text($row['notes'] ?? '') ?></textarea>
</div>

<!-- @VH: Storage of preferences section function  -->
<?php
    $pistorage_preference_sql_row = sqlQuery("SELECT * FROM `vh_pistorage_preference` WHERE `user_id` = ? ", array(trim($userid)));
    if (empty($pistorage_preference_sql_row)) $pistorage_preference_sql_row = array();
?>
<span class="text"><b><?php echo xla('Preferences'); ?></b></span>
<hr class="m-1 mb-2">

<div class="form-row my-1">
    <div class="col-6">
        <label for="form_pharmacy" class="font-weight-bold col-form-label col-form-label-sm"><?php echo xlt('Pharmacy'); ?>:</label>
        <input type="text" name="form_pharmacy" maxlength="250" value="<?php echo $pistorage_preference_sql_row['pharmacy'] ?? ""; ?>" class="form-control form-control-sm inputtext w-100">
    </div>

    <div class="col-6">
        <label for="form_behavioral_health" class="font-weight-bold col-form-label col-form-label-sm"><?php echo xlt('Behavioral Health - procare or atty choice'); ?>:</label>
        <input type="text" name="form_behavioral_health" maxlength="250" value="<?php echo $pistorage_preference_sql_row['behavioral_health'] ?? ""; ?>" class="form-control form-control-sm inputtext w-100">
    </div>
</div>

<div class="form-row my-1">
    <div class="col-6">
        <label for="form_chiropractic_care" class="font-weight-bold col-form-label col-form-label-sm"><?php echo xlt('Chiropractic care'); ?>:</label>
        <select name="form_chiropractic_care" id="form_chiropractic_care" class="form-control form-control-sm" title="">
            <option value="yes" <?php echo $pistorage_preference_sql_row['chiropractic_care'] && $pistorage_preference_sql_row['chiropractic_care'] == "yes" ? "selected" : ""; ?> ><?php echo xlt('Yes'); ?></option>
            <option value="no" <?php echo $pistorage_preference_sql_row['chiropractic_care'] && $pistorage_preference_sql_row['chiropractic_care'] == "no" ? "selected" : ""; ?>><?php echo xlt('No'); ?></option>
        </select>
    </div>

    <div class="col-6">
        <label for="form_communication" class="font-weight-bold col-form-label col-form-label-sm"><?php echo xlt('Communication'); ?>:</label>
        <select name="form_communication" id="form_communication" class="form-control form-control-sm" title="">
            <option value="email" <?php echo $pistorage_preference_sql_row['communication'] && $pistorage_preference_sql_row['communication'] == "email" ? "selected" : ""; ?> ><?php echo xlt('Email'); ?></option>
            <option value="phone_call" <?php echo $pistorage_preference_sql_row['communication'] && $pistorage_preference_sql_row['communication'] == "phone_call" ? "selected" : ""; ?>><?php echo xlt('Phone call'); ?></option>
        </select>
    </div>
</div>

<div class="form-row my-1">
    <div class="col-6">
        <label for="form_imaging" class="font-weight-bold col-form-label col-form-label-sm"><?php echo xlt('Imaging - Procare LOP, Longhorn, AHI, other'); ?>:</label>
        <input type="text" name="form_imaging" maxlength="250" value="<?php echo $pistorage_preference_sql_row['imaging'] ?? ""; ?>" class="form-control form-control-sm inputtext w-100">
    </div>
    <div class="col-6">
        <label for="form_neurology" class="font-weight-bold col-form-label col-form-label-sm"><?php echo xlt('Neurology - Procare or atty choice'); ?>:</label>
        <input type="text" name="form_neurology" maxlength="250" value="<?php echo $pistorage_preference_sql_row['neurology'] ?? ""; ?>" class="form-control form-control-sm inputtext w-100">
    </div>
</div>

<div class="form-row my-1">
    <div class="col-6">
        <label for="form_ortho" class="font-weight-bold col-form-label col-form-label-sm"><?php echo xlt('Ortho - Procare or atty choice'); ?>:</label>
        <input type="text" name="form_ortho" maxlength="250" value="<?php echo $pistorage_preference_sql_row['ortho'] ?? ""; ?>" class="form-control form-control-sm inputtext w-100">
    </div>
    <div class="col-6">
        <label for="form_pain_management" class="font-weight-bold col-form-label col-form-label-sm"><?php echo xlt('Pain Management - Procare or atty choice'); ?>:</label>
        <input type="text" name="form_pain_management" maxlength="250" value="<?php echo $pistorage_preference_sql_row['pain_management'] ?? ""; ?>" class="form-control form-control-sm inputtext w-100">
    </div>
</div>

<br />
<br/>
<!-- END -->

<!-- @VH: Added duplicate save button to check validation before submit form data [V100081][V100080] -->
<input type='button' class='btn btn-primary' id='form_save_btn' onclick="validateForm()" value='<?php echo xla('Save'); ?>' />

<!-- @VH: Make existing save button hidden [V100081][V100080] -->
<input type='submit' class='btn btn-primary' name='form_save' value='<?php echo xla('Save'); ?>' style="display: none;" />

<?php if ($userid && !$row['username']) { ?>
&nbsp;
<input type='submit' class='btn btn-danger' name='form_delete' value='<?php echo xla('Delete'); ?>' />
<?php } ?>

&nbsp;
<input type='button' class='btn btn-secondary' value='<?php echo xla('Cancel'); ?>' onclick='window.close()' />
</p>
</form>
<?php    $use_validate_js = 1;?>
<?php validateUsingPageRules($_SERVER['PHP_SELF']);?>
</body>
</html>
