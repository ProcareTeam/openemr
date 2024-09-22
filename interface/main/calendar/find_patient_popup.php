<?php

/* Copyright (C) 2005-2007 Rod Roark <rod@sunsetsystems.com>
 *
 * This program is free software; you can redistribute it and/or
 * modify it under the terms of the GNU General Public License
 * as published by the Free Software Foundation; either version 2
 * of the License, or (at your option) any later version.
 */

/*
 *
 * This popup is called when adding/editing a calendar event
 *
 */

require_once('../../globals.php');
require_once("$srcdir/patient.inc.php");
require_once("$srcdir/OemrAD/oemrad.globals.php");

use OpenEMR\Common\Acl\AclMain;
use OpenEMR\Core\Header;
use OpenEMR\OemrAd\Utility;

$info_msg = "";

 // If we are searching, search.
 // first set $result to empty
$result = "";
if (!empty($_REQUEST['searchby']) && !empty($_REQUEST['searchparm'])) {
    $searchby = $_REQUEST['searchby'];
    $searchparm = trim($_REQUEST['searchparm']);

    if ($searchby == "Last") {
        $result = getPatientLnames("$searchparm", "*");
    } elseif ($searchby == "Phone") {                  //(CHEMED) Search by phone number
        // @VH: Replaced function for phone search. [V100033] 
        $result = getPatientPhones("$searchparm", "*");
    } elseif ($searchby == "ID") {
        $result = getPatientId("$searchparm", "*");
    } elseif ($searchby == "DOB") {
        $result = getPatientDOB(DateToYYYYMMDD($searchparm), "*");
    } elseif ($searchby == "SSN") {
        $result = getPatientSSN("$searchparm", "*");
    } elseif ($searchby == "Email") {
        // @VH: To search patient by email. [V100033]
        $result = Utility::getPatientEmail("$searchparm", "*");
    }
}
?>
<!DOCTYPE html>
<html>
<head>
    <?php Header::setupHeader(['common', 'datetime-picker', 'opener']); ?>
<title><?php echo htmlspecialchars(xl('Patient Finder'), ENT_NOQUOTES); ?></title>

<style>
form {
    padding: 0;
    margin: 0;
}
#searchCriteria {
    text-align: center;
    width: 100%;
    font-weight: bold;
    padding: 3px;
}
#searchResultsHeader {
    width: 100%;
    border-collapse: collapse;
}
#searchResults {
    width: 100%;
    border-collapse: collapse;
    background-color: var(--white);
    overflow: auto;
}

#searchResults tr {
    cursor: hand;
    cursor: pointer;
}
#searchResults td {
    /*font-size: 0.7em;*/
    border-bottom: 1px solid var(--light);
}
.billing {
    color: var(--danger);
    font-weight: bold;
}

/* for search results or 'searching' notification */
#searchstatus {
    font-weight: bold;
    font-style: italic;
    color: var(--black);
    text-align: center;
}
#searchspinner {
    display: inline;
    visibility: hidden;
}

/* highlight for the mouse-over */
.highlight {
    background-color: #336699;
    color: var(--white);
}
</style>

<!-- ViSolve: Verify the noresult parameter -->
<?php
if (isset($_GET["res"])) {
    echo '
<script>
    // Pass the variable to parent hidden type and submit
    opener.document.theform.resname.value = "noresult";
    opener.document.theform.submit();
    // Close the window
    window.self.close();
</script>';
}
?>
<!-- ViSolve: Verify the noresult parameter -->

</head>

<body class="body_top">
<div class="container-responsive">
<div id="searchCriteria" class="bg-light p-2 pt-3">
<form method='post' name='theform' id="theform" action='find_patient_popup.php?<?php if (isset($_GET['pflag'])) {
    echo "pflag=0"; } ?>'>
    <div class="form-row">
    <label for="searchby" class="col-form-label col-form-label-sm col"><?php echo htmlspecialchars(xl('Search by:'), ENT_NOQUOTES); ?></label>
   <select name='searchby' id='searchby' class="form-control form-control-sm col">
    <option value="Last"><?php echo htmlspecialchars(xl('Name'), ENT_NOQUOTES); ?></option>
    <!-- @VH: Search by email [V100033] -->
    <option value="Email"<?php if ($searchby == 'Email') {
            echo ' selected';
    } ?>><?php echo htmlspecialchars(xl('Email'), ENT_NOQUOTES); ?></option>
    <!-- (CHEMED) Search by phone number -->
    <option value="Phone"<?php if (!empty($searchby) && ($searchby == 'Phone')) {
        echo ' selected';
                         } ?>><?php echo htmlspecialchars(xl('Phone'), ENT_NOQUOTES); ?></option>
    <option value="ID"<?php if (!empty($searchby) && ($searchby == 'ID')) {
        echo ' selected';
                      } ?>><?php echo htmlspecialchars(xl('ID'), ENT_NOQUOTES); ?></option>
    <option value="SSN"<?php if (!empty($searchby) && ($searchby == 'SSN')) {
        echo ' selected';
                       } ?>><?php echo htmlspecialchars(xl('SSN'), ENT_NOQUOTES); ?></option>
    <option value="DOB"<?php if (!empty($searchby) && ($searchby == 'DOB')) {
        echo ' selected';
                       } ?>><?php echo htmlspecialchars(xl('DOB'), ENT_NOQUOTES); ?></option>
    </select>
    <label for="searchparm" class="col-form-label col-form-label-sm col"><?php echo htmlspecialchars(xl('for:'), ENT_NOQUOTES); ?></label>
   <input type='text' class="form-control form-control-sm col" id='searchparm' name='searchparm' size='12' value='<?php echo attr($_REQUEST['searchparm'] ?? ''); ?>' title='<?php echo htmlspecialchars(xl('If name, any part of lastname or lastname,firstname'), ENT_QUOTES); ?>' />
    <div class="col">
    <input class='btn btn-primary btn-sm' type='submit' id="submitbtn" value='<?php echo htmlspecialchars(xl('Search'), ENT_QUOTES); ?>' />
        <div id="searchspinner"><img src="<?php echo $GLOBALS['webroot'] ?>/interface/pic/ajax-loader.gif" /></div>
    </div>
    </div>
</form>
</div>

<?php if (! isset($_REQUEST['searchparm'])) : ?>
<div id="searchstatus"><?php echo htmlspecialchars(xl('Enter your search criteria above'), ENT_NOQUOTES); ?></div>
<?php elseif (! is_countable($result)) : ?>
<div id="searchstatus" class="alert alert-danger rounded-0"><?php echo htmlspecialchars(xl('No records found. Please expand your search criteria.'), ENT_NOQUOTES); ?>
<br />
<!--VicarePlus :: If pflag is set the new patient create link will not be displayed -->
<a class="noresult" href='find_patient_popup.php?res=noresult'
    <?php
    if (isset($_GET['pflag']) || (!AclMain::aclCheckCore('patients', 'demo', '', array('write','addonly')))) {
        ?> style="display: none;"
        <?php
    }
    ?>  >
    <?php echo htmlspecialchars(xl('Click Here to add a new patient.'), ENT_NOQUOTES); ?></a>
</div>
<?php elseif (count($result) >= 100) : ?>
<div id="searchstatus" class="alert alert-danger rounded-0"><?php echo htmlspecialchars(xl('More than 100 records found. Please narrow your search criteria.'), ENT_NOQUOTES); ?></div>
<?php elseif (count($result) < 100) : ?>
<div id="searchstatus" class="alert alert-success rounded-0"><?php echo htmlspecialchars(count($result), ENT_NOQUOTES); ?> <?php echo htmlspecialchars(xl('records found.'), ENT_NOQUOTES); ?></div>
<?php endif; ?>

<?php if (isset($result)) : ?>
<table class="table table-sm">
<thead id="searchResultsHeader" class="head">
 <tr>
  <th class="srName"><?php echo htmlspecialchars(xl('Name'), ENT_NOQUOTES); ?></th>
  <?php
    // @VH: Search by email and phone number [V100033]
    if($searchby == 'Email') {
        ?>
        <th class="srEmail"><?php echo htmlspecialchars(xl('Email'), ENT_NOQUOTES); ?></th> <!-- (CHEMED) Search by email -->
        <?php
    } else {
        ?>
        <th class="srPhone"><?php echo htmlspecialchars(xl('Phone'), ENT_NOQUOTES); ?></th> <!-- (CHEMED) Search by phone number -->
        <?php
    }
  ?>
  <th class="srSS"><?php echo htmlspecialchars(xl('SS'), ENT_NOQUOTES); ?></th>
  <th class="srDOB"><?php echo htmlspecialchars(xl('DOB'), ENT_NOQUOTES); ?></th>
  <th class="srID"><?php echo htmlspecialchars(xl('ID'), ENT_NOQUOTES); ?></th>
 </tr>
</thead>
<tbody id="searchResults">
    <?php
    if (is_countable($result)) {
        foreach ($result as $iter) {
            $iterpid   = $iter['pid'];
            $iterlname = $iter['lname'];
            $iterfname = $iter['fname'];
            $itermname = $iter['mname'];
            $iterdob   = $iter['DOB'];

            // @VH: Alert Info [V100033]
            $iterAlertInfo = isset($iter['alert_info']) ? $iter['alert_info'] : "";

            // @VH: Email List [V100033]
            $emailList = array();
            $col = $GLOBALS['wmt::use_email_direct'] ? 'email_direct' : 'email';
            if(!empty($iter[$col])) {
                $emailList[] = $iter[$col];
            }

            if(!empty($iter['secondary_email'])) {
                $secondary_email_list = explode(",",$iter['secondary_email']);
                $emailList = array_merge($secondary_email_list, $emailList);
            }
            $emailStr = implode(", ", $emailList);

            // @VH: Phone List [V100033]
            $phoneList = array();
            if(!empty($iter['phone_home'])) {
                $phoneList[] = $iter['phone_home'];
            }

            if(!empty($iter['phone_cell'])) {
                $phoneList[] = $iter['phone_cell'];
            }

            if(!empty($iter['secondary_phone_cell'])) {
                $secondary_phone_list = explode(",",$iter['secondary_phone_cell']);
                $phoneList = array_merge($phoneList, $secondary_phone_list);
            }
            $phoneStr = implode(", ", $phoneList);
            // END

            // If billing note exists, then it gets special coloring and an extra line of output
            // in the 'name' column.
            $trClass = "oneresult";
            if (!empty($iter['billing_note'])) {
                $trClass .= " billing";
            }

            // @VH: Added data-field attribute and added iterdob value to id attribute.
            echo " <tr class='" . $trClass . "' data-field='result_obj_".$iterpid."' id='" .
            htmlspecialchars($iterpid . "~" . $iterlname . "~" . $iterfname . "~" . $iterdob . "~" . $iterAlertInfo, ENT_QUOTES) . "'>";
            echo "  <td class='srName'>" . htmlspecialchars($iterlname . ", " . $iterfname . " " . $itermname, ENT_NOQUOTES);
            if (!empty($iter['billing_note'])) {
                echo "<br />" . htmlspecialchars($iter['billing_note'], ENT_NOQUOTES);
            }

            // @VH: Result object [V100033] 
            echo "<textarea name='result_obj_".$iterpid."' id='result_obj_".$iterpid."' style='display:none;' >". base64_encode(json_encode($iter)) ."</textarea>";

            echo "</td>\n";
            
            // @VH: Search by email and phone changes. [V100033]
            if($searchby == 'Email') {
                echo "  <td class='srEmail' width='180'>" . $emailStr . "</td>\n"; //(CHEMED) Search by email number
            } else {
                echo "  <td class='srPhone' width='180'>" . $phoneStr . "</td>\n"; //(CHEMED) Search by phone number
            }
            // END

            echo "  <td class='srSS'>" . htmlspecialchars($iter['ss'], ENT_NOQUOTES) . "</td>\n";
            echo "  <td class='srDOB'>" . htmlspecialchars($iter['DOB'], ENT_NOQUOTES) . "</td>\n";
            echo "  <td class='srID'>" . htmlspecialchars($iter['pubpid'], ENT_NOQUOTES) . "</td>\n";
            echo " </tr>";
        }
    }
    ?>
</tbody>
</table>

<?php endif; ?>

<script>

// jQuery stuff to make the page a little easier to use

$(function () {
    $("#searchparm").focus();
    $(".oneresult").mouseover(function() { $(this).toggleClass("highlight"); });
    $(".oneresult").mouseout(function() { $(this).toggleClass("highlight"); });
    $(".oneresult").click(function() { SelectPatient(this); });
    //ViSolve
    $(".noresult").click(function () { SubmitForm(this);});

    //$(".event").dblclick(function() { EditEvent(this); });
    $("#theform").submit(function() { SubmitForm(this); });

    $('select[name="searchby"]').on('change', function () {
        if($(this).val() === 'DOB'){
            $('#searchparm').datetimepicker({
                <?php $datetimepicker_timepicker = false; ?>
                <?php $datetimepicker_showseconds = false; ?>
                <?php $datetimepicker_formatInput = true; ?>
                <?php require($GLOBALS['srcdir'] . '/js/xl/jquery-datetimepicker-2-5-4.js.php'); ?>
                <?php // can add any additional javascript settings to datetimepicker here; need to prepend first setting with a comma ?>
            });
        } else {
            $('#searchparm').datetimepicker("destroy");
        }
    });
});

// @VH: Added alert_info, pdata [V100033]
function selpid(pid, lname, fname, dob, alert_info, pdata) {
    // @VH: Changes.
    if (!opener.closed && opener.setCaseGuarantor) {
        opener.setCaseGuarantor(pid, lname, fname, dob);
        // var oloc = opener.location.href;
        // var res = String(oloc).match(/forms\/cases/);

        self.close();
    }

    if (opener.closed || ! opener.setpatient) {
        alert("<?php echo htmlspecialchars(xl('The destination form was closed; I cannot act on your selection.'), ENT_QUOTES); ?>");
    } else {
        // @VH: Added alert_info, pdata [V100033]
        opener.setpatient(pid, lname, fname, dob, alert_info, pdata);
    }
    dlgclose();
    return false;
}

// show the 'searching...' status and submit the form
var SubmitForm = function(eObj) {
    $("#submitbtn").css("disabled", "true");
    $("#searchspinner").css("visibility", "visible");
    return true;
}


// another way to select a patient from the list of results
// parts[] ==>  0=PID, 1=LName, 2=FName, 3=DOB
var SelectPatient = function (eObj) {
    objID = eObj.id;
    var parts = objID.split("~");
    
    // @VH: Get values sel patient. [V100033]
    var fieldId = eObj.getAttribute('data-field');
    var selectedFieldValue = document.getElementById(fieldId).value; 

    var decodedBase64 = (selectedFieldValue != "") ? atob(selectedFieldValue) : "";
    var decodedJson = (decodedBase64 != "") ? JSON.parse(decodedBase64) : {};

    // @VH: Changed argument [V100033]
    return selpid(parts[0], parts[1], parts[2], parts[3], parts[4], decodedJson);
}

</script>

</div>
</body>
</html>
