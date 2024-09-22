<?php

/**
 * LBF form.
 *
 * @package   OpenEMR
 * @link      http://www.open-emr.org
 * @author    Rod Roark <rod@sunsetsystems.com>
 * @author    Brady Miller <brady.g.miller@gmail.com>
 * @copyright Copyright (c) 2009-2019 Rod Roark <rod@sunsetsystems.com>
 * @copyright Copyright (c) 2019 Brady Miller <brady.g.miller@gmail.com>
 * @license   https://github.com/openemr/openemr/blob/master/LICENSE GNU General Public License 3
 */

require_once(dirname(__FILE__) . '/../../globals.php');
require_once($GLOBALS["srcdir"] . "/api.inc.php");
include_once($GLOBALS["srcdir"] . "/wmt-v2/printvisit.class.php");

use OpenEMR\Common\Acl\AclMain;

// This function is invoked from printPatientForms in report.inc.php
// when viewing a "comprehensive patient report".  Also from
// interface/patient_file/encounter/forms.php.

function lbf_report($pid, $encounter, $cols, $id, $formname, $no_wrap = false)
{
    // @VH: added into global list [V100010][V100011]
    global $CPR, $doNotPrintField, $addStyle;
    require_once($GLOBALS["srcdir"] . "/options.inc.php");

    $grparr = array();
    getLayoutProperties($formname, $grparr, '*');
    // Check access control.
    if (!empty($grparr['']['grp_aco_spec'])) {
        $LBF_ACO = explode('|', $grparr['']['grp_aco_spec']);
    }
    if (!AclMain::aclCheckCore('admin', 'super') && !empty($LBF_ACO)) {
        if (!AclMain::aclCheckCore($LBF_ACO[0], $LBF_ACO[1])) {
            die(xlt('Access denied'));
        }
    }

    $arr = array();
    $shrow = getHistoryData($pid);
    $fres = sqlStatement("SELECT * FROM layout_options " .
    "WHERE form_id = ? AND uor > 0 " .
    "ORDER BY group_id, seq", array($formname));
    while ($frow = sqlFetchArray($fres)) {
        $field_id  = $frow['field_id'];
        $currvalue = '';
        if (isOption($frow['edit_options'], 'H') !== false) {
            if (isset($shrow[$field_id])) {
                $currvalue = $shrow[$field_id];
            }
        } else {
            $currvalue = lbf_current_value($frow, $id, $encounter);
            if ($currvalue === false) {
                continue; // should not happen
            }
        }

        // For brevity, skip fields without a value.
        if ($currvalue === '') {
            continue;
        }

        // @VH: If Do not print field option is set then don't print field on patient report [V100010] 
        if(isset($doNotPrintField) && $doNotPrintField === true) {
            if(isOption($frow['edit_options'], 'X') === true) {
                continue;
            }
        }
        // END

        // $arr[$field_id] = $currvalue;
        // A previous change did this instead of the above, not sure if desirable? -- Rod
        // $arr[$field_id] = wordwrap($currvalue, 30, "\n", true);
        // Hi Rod content width issue in Encounter Summary - epsdky
        // Also had it not wordwrap nation notes which breaks it since it splits
        //  html tags apart - brady
        if ($no_wrap || ($frow['data_type'] == 34 || $frow['data_type'] == 25)) {
            $arr[$field_id] = $currvalue;
        } else {
            // @VH: RPG, WMT  changed from 30 to 150 to improve LBF display
            $arr[$field_id] = wordwrap($currvalue, 150, "\n", true);
        }
    }

    // @VH: Fixed form reporting design issue [V100011]   
    if($addStyle !== false) {
        $addStyle = false;
    ?>
    <style type="text/css">
        .report_table {
            border-collapse: collapse;
        }
        .border-bottom {
            border-bottom: 1px solid #000;
        }
        .border-top {
            border-top: 1px solid #000;
        }
    </style>
    <?php
    }
    // END

    // @VH: Added class to fix form reporting design issue [V100011]
    echo "<table class='report_table'>\n";
    display_layout_rows($formname, $arr);
    echo "</table>\n";
}
