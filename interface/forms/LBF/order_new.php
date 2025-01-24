<?php

/**
 * LBF form.
 *
 * @package   OpenEMR
 * @link      http://www.open-emr.org
 * @author    Rod Roark <rod@sunsetsystems.com>
 * @author    Brady Miller <brady.g.miller@gmail.com>
 * @author    Jerry Padgett <sjpadgett@gmail.com>
 * @copyright Copyright (c) 2009-2022 Rod Roark <rod@sunsetsystems.com>
 * @copyright Copyright (c) 2019 Brady Miller <brady.g.miller@gmail.com>
 * @copyright Copyright (c) 2018-2020 Jerry Padgett <sjpadgett@gmail.com>
 * @license   https://github.com/openemr/openemr/blob/master/LICENSE GNU General Public License 3
 */

// since need this class before autoloader, need to manually include it and then set it in line below with use command
require_once(__DIR__ . "/../../../src/Common/Forms/CoreFormToPortalUtility.php");
use OpenEMR\Common\Forms\CoreFormToPortalUtility;

// block of code to securely support use by the patient portal
$patientPortalSession = CoreFormToPortalUtility::isPatientPortalSession($_GET);
if ($patientPortalSession) {
    $ignoreAuth_onsite_portal = true;
}

require_once("../../globals.php");
require_once("$srcdir/api.inc.php");
require_once("$srcdir/forms.inc.php");
require_once("$srcdir/options.inc.php");
require_once("$srcdir/patient.inc.php");
require_once($GLOBALS['fileroot'] . '/custom/code_types.inc.php');
require_once("$srcdir/FeeSheetHtml.class.php");

require_once("php/lbf_functions.php");
require_once("$srcdir/wmt-v2/wmtstandard.inc");

use OpenEMR\Common\Acl\AclMain;
use OpenEMR\Common\Csrf\CsrfUtils;
use OpenEMR\Core\Header;

$CPR = 4; // cells per row

$pprow = array();

$alertmsg = '';

// @VH: Changes
$submod = isset($_GET['submod']) ? $_GET['submod'] : '';
if(isset($submod) && !empty($submod) && $submod == 'iframe') {
    ?>
    <script type="text/javascript">
        window.parent.postMessage({
            'func': 'newOrderInit'
        }, "*");
    </script>
    <?php
}
// END

function end_cell()
{
    global $item_count, $historical_ids, $USING_BOOTSTRAP;
    if ($item_count > 0) {
        echo $USING_BOOTSTRAP ? "</div>" : "</td>";
        foreach ($historical_ids as $key => $dummy) {
            // If $USING_BOOTSTRAP this won't happen.
            $historical_ids[$key] .= "</td>";
        }
        $item_count = 0;
    }
}

function end_row()
{
    global $cell_count, $CPR, $historical_ids, $USING_BOOTSTRAP;
    end_cell();
    if ($USING_BOOTSTRAP) {
        if ($cell_count > 0 && $cell_count < $CPR) {
            // Create a cell occupying the remaining bootstrap columns.
            // BS columns will be less than 12 if $CPR is not 2, 3, 4, 6 or 12.
            $bs_cols_remaining = ($CPR - $cell_count) * intval(12 / $CPR);
            echo "<div class='$BS_COL_CLASS-$bs_cols_remaining'></div>";
        }
        if ($cell_count > 0) {
            echo "</div><!-- End BS row -->\n";
        }
    } else {
        if ($cell_count > 0) {
            for (; $cell_count < $CPR; ++$cell_count) {
                echo "<td class='border-top-0'></td>";
                foreach ($historical_ids as $key => $dummy) {
                    $historical_ids[$key] .= "<td class='border-top-0'></td>";
                }
            }
            foreach ($historical_ids as $key => $dummy) {
                echo $historical_ids[$key];
            }
            echo "</tr>\n";
        }
    }
    $cell_count = 0;
}

// @VH: Changes
function addRtoForm(
    $rto_id,
    $form_name,
    $form_id,
    $formdir,
    $pid,
    $authorized = "0",
    $date = "NOW()",
    $user = "",
    $group = "",
    $therapy_group = 'not_given'
) {

    global $attendant_type;
    if (!$user) {
        $user = $_SESSION['authUser'];
    }

    if (!$group) {
        $group = $_SESSION['authProvider'];
    }

    if ($therapy_group == 'not_given') {
        $therapy_group = $attendant_type == 'pid' ? null : $_SESSION['therapy_group'];
    }

    //print_r($_SESSION['therapy_group']);die;
        $arraySqlBind = array();
    $sql = "insert into form_order_layout (date, rto_id, form_name, form_id, pid, " .
        "user, groupname, authorized, formdir, therapy_group_id) values (";
    if ($date == "NOW()") {
        $sql .= "$date";
    } else {
        $sql .= "?";
                array_push($arraySqlBind, $date);
    }

    $sql .= ", ?, ?, ?, ?, ?, ?, ?, ?, ?)";
        array_push($arraySqlBind, $rto_id, $form_name, $form_id, $pid, $user, $group, $authorized, $formdir, $therapy_group);
    return sqlInsert($sql, $arraySqlBind);
}
// End

// $is_lbf is defined in trend_form.php and indicates that we are being
// invoked from there; in that case the current encounter is irrelevant.
$from_trend_form = !empty($is_lbf);
// Yet another invocation from somewhere other than encounter.
// don't show any action buttons.
$from_lbf_edit = isset($_GET['isShow']) ? 1 : 0;
$patient_portal = $ignoreAuth_onsite_portal ? 1 : 0;
$from_lbf_edit = $patient_portal ? 1 : $from_lbf_edit;
// This is true if the page is loaded into an iframe in add_edit_issue.php.
$from_issue_form = !empty($_REQUEST['from_issue_form']);

$formname = isset($_GET['formname']) ? $_GET['formname'] : '';
$formid = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$portalid = isset($_GET['portalid']) ? (int)$_GET['portalid'] : 0;
// know LBF origin
$form_origin = isset($_GET['formOrigin']) ? (int)$_GET['formOrigin'] : null;
$is_portal_module = $form_origin === 2;
$is_portal_dashboard = $form_origin === 1; // 0 is portal
// can be used to assign to existing encounter
$portal_form_pid = 0;
if ($form_origin !== null) {
    $portal_form_pid = sqlQuery(
        "SELECT id, pid FROM `onsite_documents` WHERE `encounter` != 0 AND `encounter` = ?",
        array($formid)
    )['pid'] ?? 0;
}
$is_core = !($portal_form_pid || $patient_portal || $is_portal_dashboard || $is_portal_module);

if ($patientPortalSession && !empty($formid)) {
    $pidForm = sqlQuery("SELECT `pid` FROM `forms` WHERE `form_id` = ? AND `formdir` = ?", [$formid, $formname])['pid'];
    if (empty($pidForm) || ($pidForm != $_SESSION['pid'])) {
        echo xlt("illegal Action");
        OpenEMR\Common\Session\SessionUtil::portalSessionCookieDestroy();
        exit;
    }
}

$visitid = (int)(empty($_GET['visitid']) ? $encounter : $_GET['visitid']);

/* @VH: Changes
// If necessary get the encounter from the forms table entry for this form.
if ($formid && !$visitid && $is_core) {
    $frow = sqlQuery(
        "SELECT pid, encounter FROM forms WHERE " .
        "form_id = ? AND formdir = ? AND deleted = 0",
        array($formid, $formname)
    );
    $visitid = (int)$frow['encounter'];
    if ($frow['pid'] != $pid) {
        die("Internal error: patient ID mismatch!");
    }
}

if (!$from_trend_form && !$visitid && !$from_lbf_edit && $is_core) {
    die("Internal error: we do not seem to be in an encounter!");
}
 END */

$grparr = array();
getLayoutProperties($formname, $grparr, '*');
$lobj = $grparr[''];
$formtitle = $lobj['grp_title'];
$formhistory = 0 + $lobj['grp_repeats'];
$grp_last_update = $lobj['grp_last_update'];

// When the layout specifies display of historical values of input fields,
// we abandon responsive design of the form and instead present it in a
// horizontally scrollable table. There seems no better way to show the
// history on small devices, and in this case the form will be designed for
// data entry on the left side anyway.
//
$USING_BOOTSTRAP = empty($formhistory);

if (!empty($lobj['grp_columns'])) {
    $CPR = (int)$lobj['grp_columns'];
}
if (!empty($lobj['grp_size'])) {
    $FONTSIZE = (int)$lobj['grp_size'];
}
if (!empty($lobj['grp_issue_type'])) {
    $LBF_ISSUE_TYPE = $lobj['grp_issue_type'];
}
if (!empty($lobj['grp_aco_spec'])) {
    $LBF_ACO = explode('|', $lobj['grp_aco_spec']);
}
if ($lobj['grp_services']) {
    $LBF_SERVICES_SECTION = $lobj['grp_services'] == '*' ? '' : $lobj['grp_services'];
}
if ($lobj['grp_products']) {
    $LBF_PRODUCTS_SECTION = $lobj['grp_products'] == '*' ? '' : $lobj['grp_products'];
}
if ($lobj['grp_diags']) {
    $LBF_DIAGS_SECTION = $lobj['grp_diags'] == '*' ? '' : $lobj['grp_diags'];
}

$LBF_REFERRALS_SECTION = !empty($lobj['grp_referrals']);

$LBF_SECTION_DISPLAY_STYLE = $lobj['grp_init_open'] ? 'block' : 'none';

// $LBF_ENABLE_SAVE_CLOSE = !empty($lobj['grp_save_close']);
$LBF_ENABLE_SAVE_CLOSE = !empty($GLOBALS['gbl_form_save_close']);

// Check access control.
if (!AclMain::aclCheckCore('admin', 'super') && !empty($LBF_ACO)) {
    $auth_aco_write = AclMain::aclCheckCore($LBF_ACO[0], $LBF_ACO[1], '', 'write');
    $auth_aco_addonly = AclMain::aclCheckCore($LBF_ACO[0], $LBF_ACO[1], '', 'addonly');
    // echo "\n<!-- '$auth_aco_write' '$auth_aco_addonly' -->\n"; // debugging
    if (!$auth_aco_write && !($auth_aco_addonly && !$formid)) {
        die(xlt('Access denied'));
    }
}

if (isset($LBF_SERVICES_SECTION) || isset($LBF_PRODUCTS_SECTION) || isset($LBF_DIAGS_SECTION)) {
    $fs = new FeeSheetHtml($pid, $visitid);
}

if (!$from_trend_form) {
    $fname = $GLOBALS['OE_SITE_DIR'] . "/LBF/" . check_file_dir_name($formname) . ".plugin.php";
    if (file_exists($fname)) {
        include_once($fname);
    }
}

// If Save was clicked, save the info.
//
if (
    !empty($_POST['bn_save']) ||
    !empty($_POST['bn_save_print']) ||
    !empty($_POST['bn_save_continue']) ||
    !empty($_POST['bn_save_checkout']) ||
    !empty($_POST['bn_save_close'])
) {
    $newid = 0;
    if (!$formid) {
        // Creating a new form. Get the new form_id by inserting and deleting a dummy row.
        // This is necessary to create the form instance even if it has no native data.
        $newid = sqlInsert("INSERT INTO lbf_data " .
            "( field_id, field_value ) VALUES ( '', '' )");
        sqlStatement("DELETE FROM lbf_data WHERE form_id = ? AND " .
            "field_id = ''", array($newid));
        // @VH: Replaced with rtoform
        addRtoForm($visitid, $formtitle, $newid, $formname, $pid, $userauthorized);
    }

    $my_form_id = $formid ? $formid : $newid;

    // @VH: Set previous form data before delete ldb form data for reset furture after form data set
    $prevData = array();
    $oldFieldData = array();

    // @VH: Changes
    if ($formid) { // delete existing form data

        $prevres = sqlStatement("SELECT * FROM lbf_data WHERE form_id = ? ", array($formid));
        while ($prevrow = sqlFetchArray($prevres)) {
            $prevData[] = $prevrow;
            $oldFieldData[$prevrow['field_id']] = $prevrow['field_value'];
        }

        //$query = "DELETE FROM lbf_data WHERE form_id = ? ";
        //sqlStatement($query, array($formid));
    }

    //@VH: Update Deleted Status
    sqlStatement(
        "UPDATE form_order_layout SET deleted = 0 WHERE formdir = ? AND form_id = ? AND deleted = 1",
        array($formname, $my_form_id)
    );
    // End

    //Update Deleted Status
    /* OEMR - Changes */
    sqlStatement(
        "UPDATE form_order_layout SET deleted = 0 WHERE formdir = ? AND form_id = ? AND deleted = 1",
        array($formname, $my_form_id)
    );

    // If there is an issue ID, update it in the forms table entry.
    if (isset($_POST['form_issue_id'])) {
        // @VH: Replaced table
        sqlStatement(
            "UPDATE form_order_layout SET issue_id = ? WHERE formdir = ? AND form_id = ? AND deleted = 0",
            array($_POST['form_issue_id'], $formname, $my_form_id)
        );
    }

    // If there is a provider ID, update it in the form_order_layout table entry.
    if (isset($_POST['form_provider_id'])) {
        // @VH: Replaced table
        sqlStatement(
            "UPDATE form_order_layout SET provider_id = ? WHERE formdir = ? AND form_id = ? AND deleted = 0",
            array($_POST['form_provider_id'], $formname, $my_form_id)
        );
    }

    // @VH: Save previous readonly and unused field data
    if (!empty($formid)) {
        $ehres = sqlStatement("SELECT * FROM layout_options " .
            "WHERE form_id = ? AND field_id != '' AND (uor = 0 || edit_options LIKE '%0%' ) " .
            "ORDER BY group_id, seq", array($formname));

        while ($ehrow = sqlFetchArray($ehres)) {
            $field_id = $ehrow['field_id'];
            $data_type = $ehrow['data_type'];

            if (!empty($field_id)) {
                foreach ($prevData as $prevFieldItems) {
                    if (isset($prevFieldItems['field_id']) && !empty($prevFieldItems['field_id']) && !empty($prevFieldItems['field_value'])) {

                        if ($prevFieldItems['field_id'] == $ehrow['field_id'] && !empty($prevFieldItems['field_value'])) {

                            sqlStatement(
                                "INSERT INTO lbf_data " .
                                "( form_id, field_id, field_value ) VALUES ( ?, ?, ? )",
                                array($formid, $ehrow['field_id'], $prevFieldItems['field_value'])
                            );
                        }
                    }
                }
            }
        }
    }
    // End

    $newhistorydata = array();
    $sets = "";
    $fres = sqlStatement("SELECT * FROM layout_options " .
        "WHERE form_id = ? AND uor > 0 AND field_id != '' AND " .
        "edit_options != 'H' AND edit_options NOT LIKE '%0%' " .
        "ORDER BY group_id, seq", array($formname));

    // @VH: Handle Checked Sections List
    $c_grp_ids = array();
    foreach ($_POST as $pk => $p_val) {
        if(substr($pk, 0, 11) === "form_cb_lbf") {
            $grpInx = substr($pk, 11);

            if(!empty($grpInx) && $p_val == "1") {
                $c_grp_ids[] = $grpInx;
            }
        }
    }
    // END

    // @VH: Current field values
    $currFieldData = array();

    while ($frow = sqlFetchArray($fres)) {
        $field_id = $frow['field_id'];
        $data_type = $frow['data_type'];
        // If the field was not in the web form, skip it.
        // Except if it's checkboxes, if unchecked they are not returned.
        //
        // if ($data_type != 21 && !isset($_POST["form_$field_id"])) continue;
        //
        // The above statement commented out 2015-01-12 because a LBF plugin might conditionally
        // disable a field that is not applicable, and we need the ability to clear out the old
        // garbage in there so it does not show up in the "report" view of the data.  So we will
        // trust that it's OK to clear any field that is defined in the layout but not returned
        // by the form.
        //
        if ($data_type == 31) {
            continue; // skip static text fields
        }
        $value = get_layout_form_value($frow);

        // @VH: Change
        // //Make values empty if section is unchecked
        // $fgroup_id = $frow['group_id'];
        // if(!in_array($fgroup_id, $c_grp_ids)) {
        //     $value = '';
        // }

        // If edit option P or Q, save to the appropriate different table and skip the rest.
        $source = $frow['source'];
        if ($source == 'D' || $source == 'H') {
            // Save to patient_data, employer_data or history_data.
            if ($source == 'H') {
                // Do not call updateHistoryData() here! That would create multiple rows
                // in the history_data table for a single form save.
                $newhistorydata[$field_id] = $value;
            } elseif (strpos($field_id, 'em_') === 0) {
                $field_id = substr($field_id, 3);
                $new = array($field_id => $value);
                updateEmployerData($pid, $new);
            } else {
                $esc_field_id = escape_sql_column_name($field_id, array('patient_data'));
                sqlStatement(
                    "UPDATE patient_data SET `$esc_field_id` = ? WHERE pid = ?",
                    array($value, $pid)
                );
            }

            continue;
        } elseif ($source == 'E') {
            // Save to shared_attributes. Can't delete entries for empty fields because with the P option
            // it's important to know when a current empty value overrides a previous value.
            // @VH: Commented code
            // sqlStatement(
            //     "REPLACE INTO shared_attributes SET " .
            //     "pid = ?, encounter = ?, field_id = ?, last_update = NOW(), " .
            //     "user_id = ?, field_value = ?",
            //     array($pid, $visitid, $field_id, $_SESSION['authUserID'], $value)
            // );
            continue;
        } elseif ($source == 'V') {
            // Save to form_encounter.
            // @VH: Commented code
            // $esc_field_id = escape_sql_column_name($field_id, array('form_encounter'));
            // sqlStatement(
            //     "UPDATE form_encounter SET `$esc_field_id` = ? WHERE " .
            //     "pid = ? AND encounter = ?",
            //     array($value, $pid, $visitid)
            // );
            continue;
        }

        // It's a normal form field, save to lbf_data.
        if ($formid) { // existing form
            if ($value === '') {
                $query = "DELETE FROM lbf_data WHERE " .
                    "form_id = ? AND field_id = ?";
                sqlStatement($query, array($formid, $field_id));
            } else {
                $query = "REPLACE INTO lbf_data SET field_value = ?, " .
                    "form_id = ?, field_id = ?";
                sqlStatement($query, array($value, $formid, $field_id));

                // @VH: Assign
                $currFieldData[$field_id] = $value;
            }
        } else { // new form
            if ($value !== '') {
                sqlStatement(
                    "INSERT INTO lbf_data " .
                    "( form_id, field_id, field_value ) VALUES ( ?, ?, ? )",
                    array($newid, $field_id, $value)
                );

                // @VH: Assign

                $currFieldData[$field_id] = $value;
            }
        }
    } // end while save

    // Save any history data that was collected above.
    if (!empty($newhistorydata)) {
        updateHistoryData($pid, $newhistorydata);
    }

    if ($portalid) {
        // Delete the request from the portal.
        $result = cms_portal_call(array('action' => 'delpost', 'postid' => $portalid));
        if ($result['errmsg']) {
            die(text($result['errmsg']));
        }
    }

    if (isset($fs)) {
        $bill = is_array($_POST['form_fs_bill']) ? $_POST['form_fs_bill'] : null;
        $prod = is_array($_POST['form_fs_prod']) ? $_POST['form_fs_prod'] : null;
        $alertmsg = $fs->checkInventory($prod);
        // If there is an inventory error then no services or products will be saved, and
        // the form will be redisplayed with an error alert and everything else saved.
        if (!$alertmsg) {
            $fs->save($bill, $prod, null, null);
            $fs->updatePriceLevel($_POST['form_fs_pricelevel']);
        }
    }

    if (!$alertmsg && !empty($_POST['bn_save_close'])) {
        $alertmsg = FeeSheet::closeVisit($pid, $visitid);
    }

    if (!$formid) {
        $formid = $newid;
    }

    if (!empty($formid) && !empty($formname)) {
        // @VH: Change Filter Field
        // TEST: 'LBF_chiro_rehab' => 'Complaint2',
        $fieldList = array(
            'LBF_external_referral' => 'EXTREF9898876',
            'LBF_imagingorder' => 'img1_20',
            'LBF_internal_referral' => 'typeintref198',
            'LBFsurgeryorder' => 'SO10'
        );
        foreach($fieldList as $itemformname => $fieldid) {
            if ($itemformname == $formname) {
            if (isset($currFieldData[$fieldid])) {
                $oldV = $oldFieldData[$fieldid] ?? '';
                $newV = $currFieldData[$fieldid] ?? '';
                $isNeedToLog = ($newV !== $oldV) ? true : false;
                if($isNeedToLog === true) {
                    $sql = "INSERT INTO `form_value_logs` ( field_id, form_name, new_value, old_value, pid, form_id, username ) VALUES (?, ?, ?, ?, ?, ?, ?) ";
                    sqlInsert($sql, array(
                        $fieldid,
                        "form_rto|" . $formname,
                        $newV,
                        $oldV,
                        $pid,
                        $formid,
                        $_SESSION['authUserID']
                    ));
                }
            }
            }
        }
    }


    if (!$alertmsg && !$from_issue_form && empty($_POST['bn_save_continue'])) {
        // Support custom behavior at save time, such as going to another form.
        if (function_exists($formname . '_save_exit')) {
            if (call_user_func($formname . '_save_exit')) {
                exit;
            }
        }

        // @VH: Changes
        if(isset($submod) && !empty($submod) && $submod == 'popup') {
            ?>
            <!DOCTYPE html>
            <html>
            <head>
                <title>Redirecting</title>
                <?php Header::setupHeader(['opener', 'common', 'datetime-picker', 'jquery-ui',]); ?>
                <?php
                    // @VH: Changes
                    sqlStatement("UPDATE form_order_layout SET form_name = ?, formdir = ? WHERE rto_id = ? AND  form_id = ? AND pid = ? ", array($formtitle, $formname, $visitid, $formid, $pid));

                    if (!empty($_POST['bn_save']) && !empty($visitid)) {
                        ?>
                        <script type="text/javascript">
                            function closePopup(pid, formname, visitid, formid) {
                                if (opener.closed || ! opener.lbfFormPopup)
                                alert("<?php echo htmlspecialchars( xl('The destination form was closed; I cannot act on your selection.'), ENT_QUOTES); ?>");
                                else
                                opener.lbfFormPopup(pid, formname, visitid, formid);
                                window.close();
                                return false;
                            }

                            closePopup('<?php echo $pid; ?>', '<?php echo $formname; ?>', '<?php echo $visitid; ?>', '<?php echo $formid; ?>');
                        </script>
                        <?php
                    }
                    // End 
                ?>
            </head>
            <body>
            </body>
            </html>
            <?PHP
        }
        // END

        formHeader("Redirecting....");
        // If Save and Print, write the JavaScript to open a window for printing.
        if (!empty($_POST['bn_save_print'])) {
            echo "<script>\n" .
                "top.restoreSession();\n" .
                "window.open('$rootdir/forms/LBF/printable.php?" .
                "formname=" . attr_url($formname) .
                "&formid=" . attr_url($formid) .
                "&visitid=" . attr_url($visitid) .
                "&patientid=" . attr_url($pid) .
                "', '_blank');\n" .
                "</script>\n";
        }
        
        // @VH: Changes
        if(isset($submod) && !empty($submod) && $submod == 'iframe') {
            $visitid = isset($_REQUEST['visitid']) ? $_REQUEST['visitid'] : '';

            if (!empty($_POST['bn_save']) && !empty($visitid)) {
                ?>
                <script type="text/javascript">
                    window.parent.postMessage({
                        'func': 'saveData',
                        'rto_id': '<?php echo $visitid; ?>'
                    }, "*");
                </script>
                <?php
            }
        } else {
            formJump();
        }
        // END

        formFooter();
        exit;
    }
}
?>
<!DOCTYPE html>
<html>
<head>
    <?php Header::setupHeader(['opener', 'common', 'datetime-picker', 'select2']); ?>

    <style>

        div.section {
            border: 1px solid var(--primary);
            margin: 0 0 0 0.8125rem;
            padding: 0.4375rem;
        }

        .RS {
            border-style: solid;
            border-width: 0 0 1px 0;
            border-color: var(--gray600);
        }

        .RO {
            border-width: 1px solid var(--gray600) !important;
        }

        .linkcolor {
            color: blue;
        }

    </style>

    <?php include_once("{$GLOBALS['srcdir']}/options.js.php"); ?>

    <!-- LiterallyCanvas support -->
    <?php echo lbf_canvas_head(); ?>
    <?php echo signer_head(); ?>

    <script>

        // Support for beforeunload handler.
        var somethingChanged = false;

        function verifyCancel() {
            if (somethingChanged) {
                if (!confirm(<?php echo xlj('You have unsaved changes. Do you really want to close this form?'); ?>)) {
                    return false;
                }
            }
            somethingChanged = false;
            parent.closeTab(window.name, false);
        }

        $(function () {

            if (window.tabbify) {
                tabbify();
            }
            if (window.checkSkipConditions) {
                checkSkipConditions();
            }

            $(".select-dropdown").select2({
                theme: "bootstrap4",
                <?php require($GLOBALS['srcdir'] . '/js/xl/select2.js.php'); ?>
            });
            if (typeof error !== 'undefined') {
                if (error) {
                    alertMsg(error);
                }
            }

            $(".iframe_medium").on('click', function (e) {
                e.preventDefault();
                e.stopPropagation();
                let url = $(this).attr('href');
                url = encodeURI(url);
                dlgopen('', '', 950, 650, '', '', {
                    buttons: [
                        {text: <?php echo xlj('Close'); ?>, close: true, style: 'default btn-sm'}
                    ],
                    type: 'iframe',
                    url: url
                });
            });

            // Support for beforeunload handler.
            $('.lbfdata input, .lbfdata select, .lbfdata textarea').change(function () {
                somethingChanged = true;
            });
            window.addEventListener("beforeunload", function (e) {
                if (somethingChanged && !top.timed_out) {
                    var msg = <?php echo xlj('You have unsaved changes.'); ?>;
                    e.returnValue = msg;     // Gecko, Trident, Chrome 34+
                    return msg;              // Gecko, WebKit, Chrome <34
                }
            });

            $('.datepicker').datetimepicker({
                <?php $datetimepicker_timepicker = false; ?>
                <?php $datetimepicker_showseconds = false; ?>
                <?php $datetimepicker_formatInput = true; ?>
                <?php $datetimepicker_minDate = false; ?>
                <?php $datetimepicker_maxDate = false; ?>
                <?php require($GLOBALS['srcdir'] . '/js/xl/jquery-datetimepicker-2-5-4.js.php'); ?>
                <?php // can add any additional javascript settings to datetimepicker here; need to prepend first setting with a comma ?>
            });
            $('.datetimepicker').datetimepicker({
                <?php $datetimepicker_timepicker = true; ?>
                <?php $datetimepicker_showseconds = false; ?>
                <?php $datetimepicker_formatInput = true; ?>
                <?php $datetimepicker_minDate = false; ?>
                <?php $datetimepicker_maxDate = false; ?>
                <?php require($GLOBALS['srcdir'] . '/js/xl/jquery-datetimepicker-2-5-4.js.php'); ?>
                <?php // can add any additional javascript settings to datetimepicker here; need to prepend first setting with a comma ?>
            });
            $('.datepicker-past').datetimepicker({
                <?php $datetimepicker_timepicker = false; ?>
                <?php $datetimepicker_showseconds = false; ?>
                <?php $datetimepicker_formatInput = true; ?>
                <?php $datetimepicker_minDate = false; ?>
                <?php $datetimepicker_maxDate = '+1970/01/01'; ?>
                <?php require($GLOBALS['srcdir'] . '/js/xl/jquery-datetimepicker-2-5-4.js.php'); ?>
                <?php // can add any additional javascript settings to datetimepicker here; need to prepend first setting with a comma ?>
            });
            $('.datetimepicker-past').datetimepicker({
                <?php $datetimepicker_timepicker = true; ?>
                <?php $datetimepicker_showseconds = false; ?>
                <?php $datetimepicker_formatInput = true; ?>
                <?php $datetimepicker_minDate = false; ?>
                <?php $datetimepicker_maxDate = '+1970/01/01'; ?>
                <?php require($GLOBALS['srcdir'] . '/js/xl/jquery-datetimepicker-2-5-4.js.php'); ?>
                <?php // can add any additional javascript settings to datetimepicker here; need to prepend first setting with a comma ?>
            });
            $('.datepicker-future').datetimepicker({
                <?php $datetimepicker_timepicker = false; ?>
                <?php $datetimepicker_showseconds = false; ?>
                <?php $datetimepicker_formatInput = true; ?>
                <?php $datetimepicker_minDate = '-1970/01/01'; ?>
                <?php $datetimepicker_maxDate = false; ?>
                <?php require($GLOBALS['srcdir'] . '/js/xl/jquery-datetimepicker-2-5-4.js.php'); ?>
                <?php // can add any additional javascript settings to datetimepicker here; need to prepend first setting with a comma ?>
            });
            $('.datetimepicker-future').datetimepicker({
                <?php $datetimepicker_timepicker = true; ?>
                <?php $datetimepicker_showseconds = false; ?>
                <?php $datetimepicker_formatInput = true; ?>
                <?php $datetimepicker_minDate = '-1970/01/01'; ?>
                <?php $datetimepicker_maxDate = false; ?>
                <?php require($GLOBALS['srcdir'] . '/js/xl/jquery-datetimepicker-2-5-4.js.php'); ?>
                <?php // can add any additional javascript settings to datetimepicker here; need to prepend first setting with a comma ?>
            });
        });

        var mypcc = <?php echo js_escape($GLOBALS['phone_country_code']); ?>;

        // Supports customizable forms.
        function divclick(cb, divid) {
            var divstyle = document.getElementById(divid).style;
            if (cb.checked) {
                divstyle.display = 'block';
            } else {
                divstyle.display = 'none';
            }
            return true;
        }

        // The ID of the input element to receive a found code.
        var current_sel_name = '';

        // This is for callback by the find-code popup.
        // Appends to or erases the current list of related codes.
        function set_related(codetype, code, selector, codedesc) {
            var f = document.forms[0];
            <?php if (isset($fs)) { ?>
            // This is the case of selecting a code for the Fee Sheet:
            if (!current_sel_name) {
                if (code) {
                    $.getScript('<?php echo $GLOBALS['web_root'] ?>/library/ajax/code_attributes_ajax.php' +
                        '?codetype=' + encodeURIComponent(codetype) +
                        '&code=' + encodeURIComponent(code) +
                        '&selector=' + encodeURIComponent(selector) +
                        '&pricelevel=' + encodeURIComponent(f.form_fs_pricelevel ? f.form_fs_pricelevel.value : "") +
                        '&csrf_token_form=' + <?php echo js_url(CsrfUtils::collectCsrfToken()); ?>);
                }
                return '';
            }
            <?php } ?>
            // frc will be the input element containing the codes.
            // frcd, if set, will be the input element containing their descriptions.
            var frc = f[current_sel_name];
            var frcd;
            var matches = current_sel_name.match(/^(.*)__desc$/);
            if (matches) {
                frcd = frc;
                frc = f[matches[1]];
            }
            // Allow only one code in a field unless edit option E is present.
            var s = '';
            var sd = '';
            if ((' ' + frc.className + ' ').indexOf(' EditOptionE ') > -1) {
                s = frc.value;
                sd = frcd ? frcd.value : s;
            }
            //
            if (code) {
                if (s.length > 0) {
                    s += ';';
                    sd += ';';
                }
                s += codetype + ':' + code;
                sd += codedesc;
            } else {
                s = '';
                sd = '';
            }
            frc.value = s;
            if (frcd) frcd.value = sd;
            return '';
        }

        // This invokes the "dynamic" find-code popup.
        function sel_related(elem, codetype) {
            current_sel_name = elem ? elem.name : '';
            var url = '<?php echo $rootdir ?>/patient_file/encounter/find_code_dynamic.php';
            if (codetype) url += '?codetype=' + encodeURIComponent(codetype);

            // @VH: Set patient for patient filter
            let patient_filter_id = elem.getAttribute('data-patientfilter');
            if (codetype && patient_filter_id != null) url += '&patient_id=' + patient_filter_id;
            if (!codetype && patient_filter_id != null) url += '?patient_id=' + patient_filter_id;

            dlgopen(url, '_blank', 800, 500);
        }

        // Compute the length of a string without leading and trailing spaces.
        function trimlen(s) {
            var i = 0;
            var j = s.length - 1;
            for (; i <= j && s.charAt(i) == ' '; ++i) ;
            for (; i <= j && s.charAt(j) == ' '; --j) ;
            if (i > j) return 0;
            return j + 1 - i;
        }

        // This capitalizes the first letter of each word in the passed input
        // element.  It also strips out extraneous spaces.
        function capitalizeMe(elem) {
            var a = elem.value.split(' ');
            var s = '';
            for (var i = 0; i < a.length; ++i) {
                if (a[i].length > 0) {
                    if (s.length > 0) s += ' ';
                    s += a[i].charAt(0).toUpperCase() + a[i].substring(1);
                }
            }
            elem.value = s;
        }

        // Validation logic for form submission.
        // Added prevent restoreSession for remotes
        var submitButtonName = '';

        function validate(f, restore = true) {
            var errMsgs = new Array();
            <?php generate_layout_validation($formname); ?>
            // Validation for Fee Sheet stuff. Skipping this because CV decided (2015-11-03)
            // that these warning messages are not appropriate for layout based visit forms.
            //
            // if (window.jsLineItemValidation && !jsLineItemValidation(f)) return false;

            if (submitButtonName == 'bn_save_close') {
                // For "Save and Close Visit" we check for unsaved form data in the sibling iframes.
                for (var i = 0; i < parent.frames.length; ++i) {
                    var w = parent.frames[i];
                    var tmpId = w.name;
                    if (tmpId.indexOf('enctabs-') == 0 && tmpId != window.name) {
                        if (typeof w.somethingChanged !== 'undefined' && w.somethingChanged) {
                            alert(<?php echo xlj('Hold on! You have unsaved changes in another form. Please just Save this form and then complete the other one.'); ?>);
                            return false;
                        }
                    }
                }
            }

            somethingChanged = false; // turn off "are you sure you want to leave"
            if (restore) {
                top.restoreSession();
            }

            return errMsgs.length == 0;
        }

        // Called to open the data entry form of a specified encounter form instance.
        // TBD: Move this to TabsWrapper.class.php.
        function openLBFEncounterForm(formdir, formname, formid) {
            top.restoreSession();
            var url = '<?php echo "$rootdir/patient_file/encounter/view_form.php?formname=" ?>' +
                encodeURIComponent(formdir) + '&id=' + encodeURIComponent(formid);
            parent.twAddFrameTab('enctabs', formname, url);
            return false;
        }

        function openLBFNewForm(formdir, formname) {
            top.restoreSession();
            var url = '<?php echo "$rootdir/patient_file/encounter/load_form.php?formname=" ?>' +
                encodeURIComponent(formdir);
            parent.twAddFrameTab('enctabs', formname, url);
        }

        <?php
        if (isset($fs)) {
        // jsLineItemValidation() function for the fee sheet stuff.
            echo $fs->jsLineItemValidation('form_fs_bill', 'form_fs_prod');
            ?>

        // Add a service line item.
        function fs_append_service(code_type, code, desc, price) {
            var telem = document.getElementById('fs_services_table');
            var lino = telem.rows.length - 1;
            var trelem = telem.insertRow(telem.rows.length);
            trelem.innerHTML =
                "<td class='text border-top-0'>" + code + "&nbsp;</td>" +
                "<td class='text border-top-0'>" + desc + "&nbsp;</td>" +
                "<td class='text border-top-0'>" +
                "<select class='form-control' name='form_fs_bill[" + lino + "][provid]'>" +
                "<?php echo addslashes($fs->genProviderOptionList('-- ' . xl('Default') . ' --')) ?>" +
                "</select>&nbsp;" +
                "</td>" +
                "<td class='text border-top-0 text-right'>" + price + "&nbsp;</td>" +
                "<td class='text border-top-0 text-right'>" +
                "<input type='checkbox' name='form_fs_bill[" + lino + "][del]' value='1' />" +
                "<input type='hidden' name='form_fs_bill[" + lino + "][code_type]' value='" + code_type + "' />" +
                "<input type='hidden' name='form_fs_bill[" + lino + "][code]'      value='" + code + "' />" +
                "<input type='hidden' name='form_fs_bill[" + lino + "][price]'     value='" + price + "' />" +
                "</td>";
        }

        // Add a product line item.
        function fs_append_product(code_type, code, desc, price, warehouses) {
            var telem = document.getElementById('fs_products_table');
            if (!telem) {
                alert(<?php echo xlj('A product was selected but there is no product section in this form.'); ?>);
                return;
            }
            var lino = telem.rows.length - 1;
            var trelem = telem.insertRow(telem.rows.length);
            trelem.innerHTML =
                "<td class='text border-top-0'>" + desc + "&nbsp;</td>" +
                "<td class='text border-top-0'>" +
                "<select class='form-control' name='form_fs_prod[" + lino + "][warehouse]'>" + warehouses + "</select>&nbsp;" +
                "</td>" +
                "<td class='text border-top-0 text-right'>" +
                "<input type='text' class='form-control' name='form_fs_prod[" + lino + "][units]' size='3' value='1' />&nbsp;" +
                "</td>" +
                "<td class='text border-top-0 text-right'>" + price + "&nbsp;</td>" +
                "<td class='text border-top-0 text-right'>" +
                "<input type='checkbox' name='form_fs_prod[" + lino + "][del]'     value='1' />" +
                "<input type='hidden'   name='form_fs_prod[" + lino + "][drug_id]' value='" + code + "' />" +
                "<input type='hidden'   name='form_fs_prod[" + lino + "][price]'   value='" + price + "' />" +
                "</td>";
        }

        // Add a diagnosis line item.
        function fs_append_diag(code_type, code, desc) {
            var telem = document.getElementById('fs_diags_table');
            // Adding 1000 because form_fs_bill[] is shared with services and we want to avoid collisions.
            var lino = telem.rows.length - 1 + 1000;
            var trelem = telem.insertRow(telem.rows.length);
            trelem.innerHTML =
                "<td class='text border-top-0'>" + code + "&nbsp;</td>" +
                "<td class='text border-top-0'>" + desc + "&nbsp;</td>" +
                "<td class='text border-top-0 text-right'>" +
                "<input type='checkbox' name='form_fs_bill[" + lino + "][del]' value='1' />" +
                "<input type='hidden' name='form_fs_bill[" + lino + "][code_type]' value='" + code_type + "' />" +
                "<input type='hidden' name='form_fs_bill[" + lino + "][code]'      value='" + code + "' />" +
                "<input type='hidden' name='form_fs_bill[" + lino + "][price]'     value='" + 0 + "' />" +
                "</td>";
        }

        // Respond to clicking a checkbox for adding (or removing) a specific service.
        function fs_service_clicked(cb) {
            var f = cb.form;
            // The checkbox value is a JSON array containing the service's code type, code, description,
            // and price for each price level.
            var a = JSON.parse(cb.value);
            if (!cb.checked) {
                // The checkbox was UNchecked.
                // Find last row with a matching code_type and code and set its del flag.
                var telem = document.getElementById('fs_services_table');
                var lino = telem.rows.length - 2;
                for (; lino >= 0; --lino) {
                    var pfx = "form_fs_bill[" + lino + "]";
                    if (f[pfx + "[code_type]"].value == a[0] && f[pfx + "[code]"].value == a[1]) {
                        f[pfx + "[del]"].checked = true;
                        break;
                    }
                }
                return;
            }
            $.getScript('<?php echo $GLOBALS['web_root'] ?>/library/ajax/code_attributes_ajax.php' +
                '?codetype=' + encodeURIComponent(a[0]) +
                '&code=' + encodeURIComponent(a[1]) +
                '&pricelevel=' + encodeURIComponent(f.form_fs_pricelevel.value) +
                '&csrf_token_form=' + <?php echo js_url(CsrfUtils::collectCsrfToken()); ?>);
        }

        // Respond to clicking a checkbox for adding (or removing) a specific product.
        function fs_product_clicked(cb) {
            var f = cb.form;
            // The checkbox value is a JSON array containing the product's code type, code and selector.
            var a = JSON.parse(cb.value);
            if (!cb.checked) {
                // The checkbox was UNchecked.
                // Find last row with a matching product ID and set its del flag.
                var telem = document.getElementById('fs_products_table');
                var lino = telem.rows.length - 2;
                for (; lino >= 0; --lino) {
                    var pfx = "form_fs_prod[" + lino + "]";
                    if (f[pfx + "[code_type]"].value == a[0] && f[pfx + "[code]"].value == a[1]) {
                        f[pfx + "[del]"].checked = true;
                        break;
                    }
                }
                return;
            }
            $.getScript('<?php echo $GLOBALS['web_root'] ?>/library/ajax/code_attributes_ajax.php' +
                '?codetype=' + encodeURIComponent(a[0]) +
                '&code=' + encodeURIComponent(a[1]) +
                '&selector=' + encodeURIComponent(a[2]) +
                '&pricelevel=' + encodeURIComponent(f.form_fs_pricelevel.value) +
                '&csrf_token_form=' + <?php echo js_url(CsrfUtils::collectCsrfToken()); ?>);
        }

        // Respond to clicking a checkbox for adding (or removing) a specific diagnosis.
        function fs_diag_clicked(cb) {
            var f = cb.form;
            // The checkbox value is a JSON array containing the diagnosis's code type, code, description.
            var a = JSON.parse(cb.value);
            if (!cb.checked) {
                // The checkbox was UNchecked.
                // Find last row with a matching code_type and code and set its del flag.
                var telem = document.getElementById('fs_diags_table');
                var lino = telem.rows.length - 2 + 1000;
                for (; lino >= 0; --lino) {
                    var pfx = "form_fs_bill[" + lino + "]";
                    if (f[pfx + "[code_type]"].value == a[0] && f[pfx + "[code]"].value == a[1]) {
                        f[pfx + "[del]"].checked = true;
                        break;
                    }
                }
                return;
            }
            $.getScript('<?php echo $GLOBALS['web_root'] ?>/library/ajax/code_attributes_ajax.php' +
                '?codetype=' + encodeURIComponent(a[0]) +
                '&code=' + encodeURIComponent(a[1]) +
                '&pricelevel=' + encodeURIComponent(f.form_fs_pricelevel ? f.form_fs_pricelevel.value : "") +
                '&csrf_token_form=' + <?php echo js_url(CsrfUtils::collectCsrfToken()); ?>);
        }

        // Respond to selecting a package of codes.
        function fs_package_selected(sel) {
            var f = sel.form;
            // The option value is an encoded string of code types and codes.
            if (sel.value) {
                $.getScript('<?php echo $GLOBALS['web_root'] ?>/library/ajax/code_attributes_ajax.php' +
                    '?list=' + encodeURIComponent(sel.value) +
                    '&pricelevel=' + encodeURIComponent(f.form_fs_pricelevel ? f.form_fs_pricelevel.value : "") +
                    '&csrf_token_form=' + <?php echo js_url(CsrfUtils::collectCsrfToken()); ?>);
            }
            sel.selectedIndex = 0;
        }

        // This is called back by code_attributes_ajax.php to complete the appending of a line item.
        function code_attributes_handler(codetype, code, desc, price, warehouses) {
            if (codetype == 'PROD') {
                fs_append_product(codetype, code, desc, price, warehouses);
            } else if (codetype == 'ICD9' || codetype == 'ICD10') {
                fs_append_diag(codetype, code, desc);
            } else {
                fs_append_service(codetype, code, desc, price);
            }
        }

        function warehouse_changed(sel) {
            if (!confirm(<?php echo xlj('Do you really want to change Warehouse?'); ?>)) {
                // They clicked Cancel so reset selection to its default state.
                for (var i = 0; i < sel.options.length; ++i) {
                    sel.options[i].selected = sel.options[i].defaultSelected;
                }
            }
        }

        <?php } // end if (isset($fs))

        if (function_exists($formname . '_javascript')) {
            call_user_func($formname . '_javascript');
        }
        ?>

    </script>

    <!-- @VH: Change -->
    <?php if(isset($submod) && !empty($submod)) { ?>
        <script type="text/javascript">
            // create an Observer instance
            const resizeObserver = new ResizeObserver(entries => {
                window.parent.postMessage({
                    'func': 'onResizeIframe'
                }, "*");
            });

            window.addEventListener('DOMContentLoaded', function(e) {
                // start observing a DOM node
                resizeObserver.observe(document.querySelector('body > .container'));
            });
        </script>
    <?php } ?>

    <style type="text/css">
        .configLink {
            text-transform: none!important;
            margin-right: 10px;
        }
        .global_copy_container, .sub_section_copy_container {
            display: inline-block;
            float: right;
            font-weight: normal;
        }

        #global_request_data, .request_data {
            display: none;
        }
    </style>

    <script type="text/javascript">
        // This invokes the find-addressbook popup.
        function add_doc_popup(section_id = '', formname = '', encounter = '', pid = '') {
            var url = '<?php echo $GLOBALS['webroot']; ?>/interface/forms/LBF/php/lbf_select_encounter.php'+'?pid='+pid+'&section_id='+section_id+'&formname='+formname+'&encounter='+encounter;
            let title = "<?php echo xlt('Select Encounter'); ?>";
            dlgopen(url, 'selectEncounter', 600, 400, '', title);
        }

        async function globalCopy(event, pid, formname, encounter, section_id) {
            event.preventDefault();
            event.stopPropagation();

            add_doc_popup(section_id, formname, encounter, pid);
        }

        async function setEncounter(section_id, encounter_id, form_id, pid, c_action) {
            await fetchExtExam(encounter_id, form_id, pid, section_id);
        }

        async function fetchExtExam(encounterId, id, pid, section_id = 'global') {
            var msg = "Load data from selected encounter form into this form? \\n\\n Current Data in this form will be overwritten.";

            var confirmBox = confirm(msg);

            if(confirmBox != true) {
                return false;
            }

            if(section_id != 'global') {
                var inputVals = $('#'+section_id+'_request_data').val();
            } else {
                var inputVals = $('#global_request_data').val();
            }

            var valObj = {};
            if(inputVals != '') {
                valObj = JSON.parse(inputVals);
            }

            if(section_id == 'global') {
                valObj['section_id'] = 'global';
            }

            valObj['encounter_id'] = encounterId;
            valObj['f_id'] = id;

            const result = await $.ajax({
                type: "POST",
                url: "<?php echo $GLOBALS['webroot']; ?>/interface/forms/LBF/ajax/fetch_lbf_form.php",
                datatype: "json",
                data: valObj
            });

            if(result != '' && confirmBox == true) {
                var resultObj = JSON.parse(result);

                if(section_id == 'global') {
                    extexam[section_id](resultObj['formData'], resultObj['group_check_list']);
                } else {
                    extexam.global(resultObj['formData'], resultObj['group_check_list'], section_id);
                }
            }
        }

        var extexam = {};

        extexam.global = function(data, list_data = [], sectionId = '') {
            $.each(data, function(section, fields){
                if(list_data['form_cb_'+section]) {
                    if(list_data['form_cb_'+section] == 1) {
                        $('input[name="'+'form_cb_'+section+'"]').prop('checked', true);
                        $('#div_'+section).css("display", "block");
                    } else {
                        $('input[name="'+'form_cb_'+section+'"]').prop('checked', false);
                        $('#div_'+section).css("display", "none");
                    }
                }

                if(sectionId != '') {
                    if(section == sectionId) {
                        setFielValue(fields, section);
                    }
                } else {
                    setFielValue(fields, section);
                }   
            });
        }

        var setFielValue = function(data, section) {
            if(data && section) {
                $.each(data, function(k, field){
                    if(field['data_type'] == '21') {
                        var chValues = field['currentvalue'].split('|');
                        var eleStr = [];
                        
                        $('#div_'+section+' [name^="form_'+field['field_id']+'["]').prop( "checked", false );

                        $.each(chValues, function(chk, chVal){
                            eleStr.push('#div_'+section+' [name="form_'+field['field_id']+'['+chVal+']"]');
                        });

                        var eStr = eleStr.join(', ');
                        var ele = $(eStr);
                        setInputVal(ele, '1');
                    } else if(field['data_type'] == '22') {
                        var tlValues = field['currentvalue'].split('|');
                        $.each(tlValues, function(tlk, tlVal){
                            var tlVals = tlVal.split(':');

                            var tlele = $('#div_'+section+' [name="form_'+field['field_id']+'['+tlVals[0]+']"]');
                            setInputVal(tlele, tlVals[1]);
                        });
                    } else if(field['data_type'] == '25') {
                        $('#div_'+section+' [name^="check_'+field['field_id']+'["]').prop( "checked", false );
                        
                        var tcheleStr = [];
                        var tchValues = field['currentvalue'].split('|');
                        
                        $.each(tchValues, function(tchk, tchVal){
                            var tchVals = tchVal.split(':');

                            var chele = $('#div_'+section+' [name="check_'+field['field_id']+'['+tchVals[0]+']"]');
                            setInputVal(chele, tchVals[1]);

                            var tchele = $('#div_'+section+' [name="form_'+field['field_id']+'['+tchVals[0]+']"]');
                            setInputVal(tchele, tchVals[2]);
                        });

                    } else if(field['data_type'] == '34') {
                        $('#div_'+section+' #form_'+field['field_id']+'_div').html(field['currentvalue']);
                        var ele1 = $('#div_'+section+' [name="form_'+field['field_id']+'"]');
                        setInputVal(ele1, field['currentvalue']);
                    } else if(field['data_type'] == '36') {
                        var smValues = field['currentvalue'].split('|');
                        var ele = $('#div_'+section+' [name="form_'+field['field_id']+'[]"]');
                        setInputVal(ele, smValues);
                    } else {
                        var ele = $('#div_'+section+' [name="form_'+field['field_id']+'"]');
                        setInputVal(ele, field['currentvalue']);
                    }
                });
            }
        }

        var setInputVal = function(ele, value) {
            //console.log(value);
            if(ele.length > 0) {
                if($(ele).is("input:text")) {
                    ele.val(value);
                } else if($(ele).is("select")) {
                    $(ele).val(value);
                    //$(ele).val(value).change();
                } else if($(ele).is("select [multiple='multiple']")) {
                    $(ele).val(value);
                } else if($(ele).is("textarea")) {
                    $(ele).val(value);
                } else if($(ele).is("input:checkbox")) {
                    $.each(ele, function(inx, c_ele){
                        if($(c_ele).val() == value) {
                            $(c_ele).prop( "checked", true );
                        } else {
                            $(c_ele).prop( "checked", false );
                        }
                    });
                } else if($(ele).is("input:radio")) {
                    $.each(ele, function(inx, c_ele){
                        if($(c_ele).val() == value) {
                            $(c_ele).prop( "checked", true );
                        } else {
                            $(c_ele).prop( "checked", false );
                        }
                    });
                }
            }
        }
    </script>
    <!-- End -->
</head>

<body class="body_top"<?php if ($from_issue_form) {
    echo " style='background-color:var(--white)'"; } ?>>
    <!-- Set as a container until xl breakpoint then make fluid. -->
    <div class="container-xl">
        <?php
        // form-inline is more consistent with the fact that LBFs are not designed for
        // small devices. In particular we prefer horizontal arrangement of multiple
        // items in the same row and column.
        // @VH: form action value changed added some more param to form
        echo "<form method='post' class='form-inline' " .
            "action='$rootdir/forms/LBF/order_new.php?formname=" . attr_url($formname) . "&id=" . attr_url($formid) . "&portalid=" . attr_url($portalid) . "&formOrigin=" . attr_url($form_origin) . "&isPortal=" . attr_url($patient_portal) . "&visitid=".$visitid."&submod=".$submod."' " .
            "onsubmit='return validate(this)'>\n";
        ?>
        <!-- row width will size to col content width -->
        <!-- We need all possible viewport width sjp w-100 -->
        <div class="row w-100 overflow-auto">
            <div class="col-12">
                <?php
                $cmsportal_login = '';
                $portalres = false;

                if (!$from_trend_form) {
                    // @VH: Table name and Query change
                    $enrow = sqlQuery("SELECT p.fname, p.mname, p.lname, p.cmsportal_login " .
                        " FROM " .
                        "form_order_layout AS f, patient_data AS p WHERE " .
                        "p.pid = ? AND f.pid = p.pid AND f.rto_id = ? AND " .
                        "f.formdir = ? AND f.deleted = 0 " .
                        " LIMIT 1", array($pid, $visitid, $formname)); ?>
                    <div class="row">
                        <div class="col-12">
                            <h3>
                                <?php echo text($formtitle);
                                if ($is_core) {
                                    // @VH: Changes
                                    if(!empty($enrow['fname']) || !empty($enrow['mname']) || !empty($enrow['lname'])) {
                                        echo xlt('for') . ' ' . text($enrow['fname']) . ' ' . text($enrow['mname']) . ' ' . text($enrow['lname']);
                                    }
                                    //echo ' ' . xlt('on') . ' ' . text(oeFormatShortDate(substr($enrow['date'], 0, 10))); 
                                    // END
                                } ?>
                            </h3>
                            <?php
                            // @VH: table name change and query change
                            $firow = sqlQuery(
                                "SELECT issue_id, provider_id FROM form_order_layout WHERE " .
                                "formdir = ? AND form_id = ? AND deleted = 0",
                                array($formname, $formid)
                            );
                            $form_issue_id = empty($firow['issue_id']) ? 0 : intval($firow['issue_id']);
                            $default = empty($firow['provider_id']) ? ($_SESSION['authUserID'] ?? null) : intval($firow['provider_id']);

                            if (!$patient_portal) {
                                // Provider selector.
                                echo "&nbsp;&nbsp;";
                                echo xlt('Provider') . ": ";
                                echo "<select class='form-control' name='form_provider_id'>";
                                echo FeeSheetHtml::genProviderOptionList(
                                    '-- ' . xl("Please Select") . ' --',
                                    ($form_provider_id ?? '')
                                );
                                echo "</select>\n";
                            }

                            // If appropriate build a drop-down selector of issues of this type for this patient.
                            // We skip this if in an issue form tab because removing and adding visit form tabs is
                            // beyond the current scope of that code.
                            if (!empty($LBF_ISSUE_TYPE) && !$from_issue_form) {
                                echo "&nbsp;&nbsp;";
                                $query = "SELECT id, title, date, begdate FROM lists WHERE pid = ? AND type = ? " .
                                    "ORDER BY COALESCE(begdate, date) DESC, id DESC";
                                $ires = sqlStatement($query, array($pid, $LBF_ISSUE_TYPE));
                                echo "<select name='form_issue_id'>\n";
                                echo " <option value='0'>-- " . xlt('Select Case') . " --</option>\n";
                                while ($irow = sqlFetchArray($ires)) {
                                    $issueid = $irow['id'];
                                    $issuedate = oeFormatShortDate(empty($irow['begdate']) ? $irow['date'] : $irow['begdate']);
                                    echo " <option value='" . attr($issueid) . "'";
                                    if ($issueid == $form_issue_id) {
                                        echo " selected";
                                    }
                                    echo ">" . text("$issuedate " . $irow['title']) . "</option>\n";
                                }
                                echo "</select>\n";
                            }
                            ?>
                        </div>
                    </div>
                    <?php $cmsportal_login = $enrow['cmsportal_login'] ?? '';
                } // end not from trend form
                ?>

                <!-- This is where a chart might display. -->
                <div id="chart"></div>
                <?php
                $shrow = getHistoryData($pid);

                $TOPCPR = empty($grparr['']['grp_columns']) ? 4 : $grparr['']['grp_columns'];

                $fres = sqlStatement("SELECT * FROM layout_options " .
                    "WHERE form_id = ? AND uor > 0 " .
                    "ORDER BY group_id, seq", array($formname));
                $cell_count = 0;
                $item_count = 0;
                // $display_style = 'block';

                // This string is the active group levels. Each leading substring represents an instance of nesting.
                $group_levels = '';

                // This indicates if </table> will need to be written to end the fields in a group.
                $group_table_active = false;

                // This is an array keyed on forms.form_id for other occurrences of this
                // form type.  The maximum number of such other occurrences to display is
                // in list_options.option_value for this form's list item.  Values in this
                // array are work areas for building the ending HTML for each displayed row.
                //
                $historical_ids = array();

                // True if any data items in this form can be graphed.
                $form_is_graphable = false;

                $condition_str = '';

                // @VH: Process Before Save [V10001]
                preProcessData($pid);

                while ($frow = sqlFetchArray($fres)) {
                    $this_group = $frow['group_id'];
                    $titlecols = $frow['titlecols'];
                    $datacols = $frow['datacols'];
                    $data_type = $frow['data_type'];
                    $field_id = $frow['field_id'];
                    $list_id = $frow['list_id'];
                    $edit_options = $frow['edit_options'];
                    $source = $frow['source'];
                    $jump_new_row = isOption($edit_options, 'J');
                    $prepend_blank_row = isOption($edit_options, 'K');

                    $CPR = empty($grparr[$this_group]['grp_columns']) ? $TOPCPR : $grparr[$this_group]['grp_columns'];

                    $graphable = isOption($edit_options, 'G') !== false;
                    if ($graphable) {
                        $form_is_graphable = true;
                    }

                    if (isOption($edit_options, 'EP') && $patient_portal) {
                        continue;
                    }

                    // Accumulate action conditions into a JSON expression for the browser side.
                    accumActionConditions($frow, $condition_str);

                    $currvalue = '';

                    if (isOption($edit_options, 'H') !== false) {
                        // This data comes from static history
                        if (isset($shrow[$field_id])) {
                            $currvalue = $shrow[$field_id];
                        }
                    } else {
                        if (!$formid && $portalres) {
                            // Copying CMS Portal form data into this field if appropriate.
                            $currvalue = cms_field_to_lbf($data_type, $field_id, $portalres['fields']);
                        }

                        if ($currvalue === '') {
                            $currvalue = lbf_current_value($frow, $formid, (!empty($is_lbf)) ? 0 : $encounter);
                        }

                        if ($currvalue === false) {
                            continue; // column does not exist, should not happen
                        }

                        // Handle "P" edit option to default to the previous value of a form field.
                        if (!$from_trend_form && empty($currvalue) && isOption($edit_options, 'P') !== false) {
                            if ($source == 'F' && !$formid) {
                                // Form attribute for new form, get value from most recent form instance.
                                // Form attributes of existing forms are expected to have existing values.
                                // @VH: Commented code
                                // $tmp = sqlQuery(
                                //     "SELECT encounter, form_id FROM forms WHERE " .
                                //     "pid = ? AND formdir = ? AND deleted = 0 " .
                                //     "ORDER BY date DESC LIMIT 1",
                                //     array($pid, $formname)
                                // );
                                // if (!empty($tmp['encounter'])) {
                                //     $currvalue = lbf_current_value($frow, $tmp['form_id'], $tmp['encounter']);
                                // }
                            } elseif ($source == 'E') {
                                // Visit attribute, get most recent value as of this visit.
                                // Even if the form already exists for this visit it may have a readonly value that only
                                // exists in a previous visit and was created from a different form.
                                // @VH: Commented code
                                // $tmp = sqlQuery(
                                //     "SELECT sa.field_value FROM form_encounter AS e1 " .
                                //     "JOIN form_encounter AS e2 ON " .
                                //     "e2.pid = e1.pid AND (e2.date < e1.date OR (e2.date = e1.date AND e2.encounter <= e1.encounter)) " .
                                //     "JOIN shared_attributes AS sa ON " .
                                //     "sa.pid = e2.pid AND sa.encounter = e2.encounter AND sa.field_id = ? " .
                                //     "WHERE e1.pid = ? AND e1.encounter = ? " .
                                //     "ORDER BY e2.date DESC, e2.encounter DESC LIMIT 1",
                                //     array($field_id, $pid, $visitid)
                                // );
                                // if (isset($tmp['field_value'])) {
                                //     $currvalue = $tmp['field_value'];
                                // }
                            }
                        } // End "P" option logic.
                    }

                    // @VH: If field option type is Unused (Readonly) hide the field if value is not empty [2024073101]
                    if(isset($frow['uor']) && $frow['uor'] == "4") {
                        if (empty($currvalue)) {
                            continue;
                        }
                    }

                    $this_levels = $this_group;
                    $i = 0;
                    $mincount = min(strlen($this_levels), strlen($group_levels));
                    while ($i < $mincount && $this_levels[$i] == $group_levels[$i]) {
                        ++$i;
                    }
                    // $i is now the number of initial matching levels.

                    // If ending a group or starting a subgroup, terminate the current row and its table.
                    if ($group_table_active && ($i != strlen($group_levels) || $i != strlen($this_levels))) {
                        end_row();
                        echo $USING_BOOTSTRAP ? " </div>\n" : " </table>\n";
                        $group_table_active = false;
                    }

                    // Close any groups that we are done with.
                    while (strlen($group_levels) > $i) {
                        $gname = $grparr[$group_levels]['grp_title'];
                        $group_levels = substr($group_levels, 0, -1); // remove last character
                        // No div for an empty group name.
                        if (strlen($gname)) {
                            echo "</div>\n";
                        }
                    }

                    // If there are any new groups, open them.
                    while ($i < strlen($this_levels)) {
                        end_row();
                        if ($group_table_active) {
                            echo $USING_BOOTSTRAP ? " </div>\n" : " </table>\n";
                            $group_table_active = false;
                        }
                        $group_levels .= $this_levels[$i++];
                        $grouprow = $grparr[substr($group_levels, 0, $i)];
                        $gname = $grouprow['grp_title'];
                        $subtitle = xl_layout_label($grouprow['grp_subtitle']);
                        // Compute a short unique identifier for this group.
                        $group_seq = 'lbf' . $group_levels;
                        $group_name = $gname;

                        $display_style = $grouprow['grp_init_open'] ? 'block' : 'none';

                        // @VH: Hide/Show group section [V10001]
                        if (strlen($gname)) {
                            if(isset($group_check_list['form_cb_'.$group_seq])) {
                                if($group_check_list['form_cb_'.$group_seq] === 1) {
                                    $display_style = 'block';
                                } else {
                                    $display_style = 'none';
                                }
                            }
                        }
                        // END

                        // If group name is blank, no checkbox or div.
                        if (strlen($gname)) {
                            // <label> was inheriting .justify-content-center from .form-inline,
                            // dunno why but we fix that here.
                            // @VH: Added Style changes for checkbox [V10007]
                            echo "<br /><span style='display: grid;grid-template-columns: 1fr auto;'><label class='mb-1 justify-content-start' role='button'><input class='mr-1' type='checkbox' name='form_cb_" . attr($group_seq) . "' value='1' " . "onclick='return divclick(this," . attr_js('div_' . $group_seq) . ");'";
                            if ($display_style == 'block') {
                                echo " checked";
                            }
                            echo " /><strong>" . text(xl_layout_label($group_name)) . "</strong></label></span>\n";
                            // table-responsive removed below because it added a scrollbar regardless of screen width.
                            echo "<div id='div_" . attr($group_seq) . "' class='section clearfix' style='display:" . attr($display_style) . ";'>\n";
                        }

                        $group_table_active = true;


                        $historical_ids = array();

                        if ($USING_BOOTSTRAP) {
                            echo " <div class='container-fluid lbfdata'>\n";
                            if ($subtitle) {
                                // There is a group subtitle so show it.
                                $bs_cols = $CPR * intval(12 / $CPR);
                                echo "<div class='row mb-2'>";
                                echo "<div class='$BS_COL_CLASS-$bs_cols font-weight-bold text-primary'>" . text($subtitle) . "</div>";
                                echo "</div>\n";
                            }
                        } else {
                            echo " <table cellspacing='0' cellpadding='0' class='border-0 lbfdata'>\n";
                            if ($subtitle) {
                                // There is a group subtitle so show it.
                                echo "<tr><td class='font-weight-bold border-top-0 text-primary' colspan='" . attr($CPR) . "'>" . text($subtitle) . "</td></tr>\n";
                                echo "<tr><td class='font-weight-bold border-top-0' style='height:0.3125rem;' colspan='" . attr($CPR) . "'></td></tr>\n";
                            }

                            // Initialize historical data array and write date headers.
                            if ($formhistory > 0) {
                                echo " <tr>";
                                echo "<td colspan='" . attr($CPR) . "' class='font-weight-bold border-top-0 text-right'>";
                                if (empty($is_lbf)) {
                                    // Including actual date per IPPF request 2012-08-23.
                                    echo text(oeFormatShortDate(substr($enrow['date'], 0, 10)));
                                    echo ' (' . xlt('Current') . ')';
                                }

                                echo "&nbsp;</td>\n";
                                // @VH: Query change
                                $hres = sqlStatement(
                                    "SELECT f.form_id " .
                                    "FROM form_order_layout AS f WHERE " .
                                    "f.pid = ? AND f.formdir = ? AND " .
                                    "f.form_id != ? AND f.deleted = 0  AND " .
                                    "fe.pid = f.pid AND fe.encounter = f.encounter " .
                                    "ORDER BY fe.date DESC, f.encounter DESC, f.date DESC " .
                                    "LIMIT ?",
                                    array($pid, $formname, $formid, $formhistory)
                                );
                                // For some readings like vitals there may be multiple forms per encounter.
                                // We sort these sensibly, however only the encounter date is shown here;
                                // at some point we may wish to show also the data entry date/time.
                                while ($hrow = sqlFetchArray($hres)) {
                                    // @VH: Commented code
                                    // echo "<td colspan='" . attr($CPR) . "' class='font-weight-bold border-top-0 text-right'>&nbsp;" .
                                    //     text(oeFormatShortDate(substr($hrow['date'], 0, 10))) . "</td>\n";
                                    $historical_ids[$hrow['form_id']] = '';
                                }

                                echo " </tr>";
                            }
                        }
                    }

                    // Handle starting of a new row.
                    if (($titlecols > 0 && $cell_count >= $CPR) || $cell_count == 0 || $prepend_blank_row || $jump_new_row) {
                        end_row();

                        if ($USING_BOOTSTRAP) {
                            $tmp = 'form-row';
                            if ($prepend_blank_row) {
                                $tmp .= ' mt-3';
                            }
                            if (isOption($edit_options, 'RS')) {
                                $tmp .= ' RS';
                            } elseif (isOption($edit_options, 'RO')) {
                                $tmp .= ' RO';
                            }
                            echo "<div class='$tmp'>";
                        } else {
                            if ($prepend_blank_row) {
                                echo "<tr><td class='text border-top-0' colspan='" . attr($CPR) . "'>&nbsp;</td></tr>\n";
                            }
                            if (isOption($edit_options, 'RS')) {
                                echo " <tr class='RS'>";
                            } elseif (isOption($edit_options, 'RO')) {
                                echo " <tr class='RO'>";
                            } else {
                                echo " <tr>";
                            }
                        }
                        // Clear historical data string.
                        foreach ($historical_ids as $key => $dummy) {
                            $historical_ids[$key] = '';
                        }
                    }

                    if ($item_count == 0 && $titlecols == 0) {
                        $titlecols = 1;
                    }

                    // First item is on the "left-border"
                    $leftborder = true;

                    // Handle starting of a new label cell.
                    if ($titlecols > 0) {
                        end_cell();
                        $tmp = ' text-wrap';
                        if (isOption($edit_options, 'SP')) {
                            $datacols = 0;
                            $titlecols = $CPR;
                            $tmp = '';
                        }
                        $tmp .= ($frow['uor'] == 2) ? ' required' : ' font-weight-bold';
                        if ($graphable) {
                            $tmp .= ' graph';
                        }
                        if ($USING_BOOTSTRAP) {
                            $bs_cols = $titlecols * intval(12 / $CPR);
                            // @VH: added class to fix layout issue [V10007]
                            echo "<div class='$BS_COL_CLASS-$bs_cols col-md-3 pt-1$tmp' ";
                            // This ID is used by action conditions and also show_graph().
                            echo "id='label_id_" . attr($field_id) . "'";
                            echo ">";
                        } else {
                            echo "<td class='border-top-0 align-top$tmp' colspan='" . attr($titlecols) . "'";
                            if ($cell_count > 0) {
                                echo " style='padding-left: 0.8125rem'";
                            }
                            // This ID is used by action conditions and also show_graph().
                            echo " id='label_id_" . attr($field_id) . "'";
                            echo ">";

                            foreach ($historical_ids as $key => $dummy) {
                                $historical_ids[$key] .= "<td colspan='" . attr($titlecols) . "' class='text border-top-0 align-top text-nowrap'>";
                            }
                        }
                        $cell_count += $titlecols;
                    }

                    ++$item_count;

                    // This gets a font-weight-bold class so removed strong
                    if ($frow['title']) {
                        $tmp = xl_layout_label($frow['title']);
                        echo text($tmp);
                        // Append colon only if label does not end with punctuation.
                        if (strpos('?!.,:-=', substr($tmp, -1, 1)) === false) {
                            echo ':';
                        }
                    } else {
                        echo "&nbsp;";
                    }

                    // @VH: Add Snippets buttin if field type is 3 (TextArea) [V10009]
                    if($data_type == 3) {
                        $frmdir = 'LBF';
                        $use_break = TRUE;
                        $field_prefix = 'form_';
                        $field_name = $field_id;
                        $form_module = $field_id;
                        include(FORM_BUTTONS . 'btn_snippets.inc.php');
                    }
                    // END

                    // Note the labels are not repeated in the history columns.

                    // Handle starting of a new data cell.
                    if ($datacols > 0) {
                        end_cell();
                        $tmp = ' text';
                        if (isOption($edit_options, 'DS')) {
                            $tmp .= ' RS';
                        } else if (isOption($edit_options, 'DO')) {
                            $tmp .= ' RO';
                        }
                        if ($USING_BOOTSTRAP) {
                            $bs_cols = $datacols * intval(12 / $CPR);
                            // @VH: added class to fix layout issue [V10007]
                            echo "<div class='$BS_COL_CLASS-$bs_cols col-md-5 pt-1$tmp' ";
                            // This ID is used by action conditions and also show_graph().
                            echo "id='value_id_" . attr($field_id) . "'";
                            echo ">";
                        } else {
                            echo "<td colspan='" . attr($datacols) . "' class='border-top-0 align-top$tmp'";
                            echo " id='value_id_" . attr($field_id) . "'";
                            if ($cell_count > 0) {
                                echo " style='padding-left: 0.4375rem'";
                            }
                            echo ">";
                            foreach ($historical_ids as $key => $dummy) {
                                $historical_ids[$key] .= "<td colspan='" . attr($datacols) . "' class='text border-top-0 align-top text-right'>";
                            }
                        }
                        $cell_count += $datacols;
                    }
                    ++$item_count;

                    // Skip current-value fields for the display-only case.
                    if (!$from_trend_form) {
                        if ($frow['edit_options'] == 'H') {
                            echo generate_display_field($frow, $currvalue);
                        } else {
                            $frow['smallform'] = ' form-control-sm mw-100';
                            generate_form_field($frow, $currvalue);
                        }
                    }

                    // Append to historical data of other dates for this item.
                    foreach ($historical_ids as $key => $dummy) {
                        $value = lbf_current_value($frow, $key, 0);
                        $historical_ids[$key] .= generate_display_field($frow, $value);
                    }
                }

                // Close all open groups.
                if ($group_table_active) {
                    end_row();
                    echo $USING_BOOTSTRAP ? " </div>\n" : " </table>\n";
                    $group_table_active = false;
                }
                while (strlen($group_levels)) {
                    $gname = $grparr[$group_levels]['grp_title'];
                    $group_levels = substr($group_levels, 0, -1); // remove last character
                    // No div for an empty group name.
                    if (strlen($gname)) {
                        echo "</div>\n";
                    }
                }

                $display_style = $LBF_SECTION_DISPLAY_STYLE;

                if (isset($LBF_SERVICES_SECTION) || isset($LBF_DIAGS_SECTION)) {
                    $fs->loadServiceItems();
                }

                if (isset($LBF_SERVICES_SECTION)) {
                    // Create the checkbox and div for the Services Section.
                    echo "<br /><span class='font-weight-bold'><input type='checkbox' name='form_cb_fs_services' value='1' " .
                        "onclick='return divclick(this, \"div_fs_services\");'";
                    if ($display_style == 'block') {
                        echo " checked";
                    }
                    echo " />&nbsp;<strong>" . xlt('Services') . "</strong></span>\n";
                    echo "<div id='div_fs_services' class='section' style='display:" . attr($display_style) . ";'>\n";
                    echo "<center>\n";
                    // $display_style = 'none';

                    // If there are associated codes, generate a checkbox for each one.
                    if ($LBF_SERVICES_SECTION) {
                        echo "<table class='w-100' cellpadding='0' cellspacing='0'>\n";
                        $cols = 3;
                        $tdpct = (int)(100 / $cols);
                        $count = 0;
                        $relcodes = explode(';', $LBF_SERVICES_SECTION);
                        foreach ($relcodes as $codestring) {
                            if ($codestring === '') {
                                continue;
                            }
                            $codes_esc = attr($codestring);
                            $cbval = $fs->genCodeSelectorValue($codestring);
                            if ($count % $cols == 0) {
                                if ($count) {
                                    echo " </tr>\n";
                                }
                                echo " <tr>\n";
                            }
                            echo "  <td class='border-top-0' width='" . attr($tdpct) . "%'>";
                            echo "<input type='checkbox' id='form_fs_services[$codes_esc]' " .
                                "onclick='fs_service_clicked(this)' value='" . attr($cbval) . "'";
                            if ($fs->code_is_in_fee_sheet) {
                                echo " checked";
                            }
                            list($codetype, $code) = explode(':', $codestring);
                            $title = lookup_code_descriptions($codestring);
                            $title = empty($title) ? $code : xl_list_label($title);
                            echo " />" . text($title);
                            echo "</td>\n";
                            ++$count;
                        }
                        if ($count) {
                            echo " </tr>\n";
                        }
                        echo "</table>\n";
                    }

                    // A row for Search, Add Package, Main Provider.
                    $ctype = $GLOBALS['ippf_specific'] ? 'MA' : '';
                    echo "<p class='font-weight-bold'>";
                    echo "<input type='button' value='" . xla('Search Services') . "' onclick='sel_related(null," . attr_js($ctype) . ")' />&nbsp;&nbsp;\n";
                    $fscres = sqlStatement("SELECT * FROM fee_sheet_options ORDER BY fs_category, fs_option");
                    if (sqlNumRows($fscres)) {
                        $last_category = '';
                        echo "<select class='form-control' onchange='fs_package_selected(this)'>\n";
                        echo " <option value=''>" . xlt('Add Package') . "</option>\n";
                        while ($row = sqlFetchArray($fscres)) {
                            $fs_category = $row['fs_category'];
                            $fs_option = $row['fs_option'];
                            $fs_codes = $row['fs_codes'];
                            if ($fs_category !== $last_category) {
                                if ($last_category) {
                                    echo " </optgroup>\n";
                                }
                                echo " <optgroup label='" . xla(substr($fs_category, 1)) . "'>\n";
                                $last_category = $fs_category;
                            }
                            echo " <option value='" . attr($fs_codes) . "'>" . xlt(substr($fs_option, 1)) . "</option>\n";
                        }
                        if ($last_category) {
                            echo " </optgroup>\n";
                        }
                        echo "</select>&nbsp;&nbsp;\n";
                    }
                    $tmp_provider_id = $fs->provider_id ? $fs->provider_id : 0;
                    if (!$tmp_provider_id && $userauthorized) {
                        // Default to the logged-in user if they are a provider.
                        $tmp_provider_id = $_SESSION['authUserID'];
                    }
                    echo xlt('Main Provider') . ": ";
                    echo "<select class='form-control' name='form_fs_provid'>";
                    echo FeeSheetHtml::genProviderOptionList(
                        ' ',
                        $tmp_provider_id
                    );
                    echo "</select>\n";
                    echo "\n";
                    echo "</p>\n";

                    // Generate a line for each service already in this FS.
                    echo "<table cellpadding='0' cellspacing='2' id='fs_services_table'>\n";
                    echo " <tr>\n";
                    echo "  <td class='border-top-0 font-weight-bold' colspan='2'>" . xlt('Services Provided') . "&nbsp;</td>\n";
                    echo "  <td class='border-top-0 font-weight-bold'>" . xlt('Provider') . "&nbsp;</td>\n";
                    echo "  <td class='border-top-0 font-weight-bold text-right'>" . xlt('Price') . "&nbsp;</td>\n";
                    echo "  <td class='border-top-0 font-weight-bold text-right'>" . xlt('Delete') . "</td>\n";
                    echo " </tr>\n";
                    foreach ($fs->serviceitems as $lino => $li) {
                        // Skip diagnoses; those would be in the Diagnoses section below.
                        if ($code_types[$li['codetype']]['diag']) {
                            continue;
                        }
                        echo " <tr>\n";
                        echo "  <td class='border-top-0 text'>" . text($li['code']) . "&nbsp;</td>\n";
                        echo "  <td class='border-top-0 text'>" . text($li['code_text']) . "&nbsp;</td>\n";
                        echo "  <td class='border-top-0 text'>" .
                            $fs->genProviderSelect(
                                "form_fs_bill[$lino][provid]",
                                '-- ' . xl("Default") . ' --',
                                $li['provid']
                            ) .
                            "  &nbsp;</td>\n";
                        echo "  <td class='border-top-0 text text-right'>" . text(oeFormatMoney($li['price'])) . "&nbsp;</td>\n";
                        echo "  <td class='border-top-0 text text-right'>\n" .
                            "   <input type='checkbox' name='form_fs_bill[" . attr($lino) . "][del]' " .
                            "value='1'" . ($li['del'] ? " checked" : "") . " />\n";
                        foreach ($li['hidden'] as $hname => $hvalue) {
                            echo "   <input type='hidden' name='form_fs_bill[" . attr($lino) . "][" . attr($hname) . "]' value='" . attr($hvalue) . "' />\n";
                        }
                        echo "  </td>\n";
                        echo " </tr>\n";
                    }
                    echo "</table>\n";
                    echo "</center>\n";
                    echo "</div>\n";
                } // End Services Section

                if (isset($LBF_PRODUCTS_SECTION)) {
                    // Create the checkbox and div for the Products Section.
                    echo "<br /><span class='font-weight-bold'><input type='checkbox' name='form_cb_fs_products' value='1' " .
                        "onclick='return divclick(this, \"div_fs_products\");'";
                    if ($display_style == 'block') {
                        echo " checked";
                    }
                    echo " />&nbsp;<strong>" . xlt('Products') . "</strong></span>\n";
                    echo "<div id='div_fs_products' class='section' style='display:" . attr($display_style) . ";'>\n";
                    echo "<center>\n";
                    // $display_style = 'none';

                    // If there are associated codes, generate a checkbox for each one.
                    if ($LBF_PRODUCTS_SECTION) {
                        echo "<table class='w-100' cellpadding='0' cellspacing='0'>\n";
                        $cols = 3;
                        $tdpct = (int)(100 / $cols);
                        $count = 0;
                        $relcodes = explode(';', $LBF_PRODUCTS_SECTION);
                        foreach ($relcodes as $codestring) {
                            if ($codestring === '') {
                                continue;
                            }
                            $codes_esc = attr($codestring);
                            $cbval = $fs->genCodeSelectorValue($codestring);
                            if ($count % $cols == 0) {
                                if ($count) {
                                    echo " </tr>\n";
                                }
                                echo " <tr>\n";
                            }
                            echo "  <td class='border-top-0' width='" . attr($tdpct) . "%'>";
                            echo "<input type='checkbox' id='form_fs_products[$codes_esc]' " .
                                "onclick='fs_product_clicked(this)' value='" . attr($cbval) . "'";
                            if ($fs->code_is_in_fee_sheet) {
                                echo " checked";
                            }
                            list($codetype, $code) = explode(':', $codestring);
                            $crow = sqlQuery(
                                "SELECT name FROM drugs WHERE " .
                                "drug_id = ? ORDER BY drug_id LIMIT 1",
                                array($code)
                            );
                            $title = empty($crow['name']) ? $code : xl_list_label($crow['name']);
                            echo " />" . text($title);
                            echo "</td>\n";
                            ++$count;
                        }
                        if ($count) {
                            echo " </tr>\n";
                        }
                        echo "</table>\n";
                    }

                    // A row for Search
                    $ctype = $GLOBALS['ippf_specific'] ? 'MA' : '';
                    echo "<p class='font-weight-bold'>";
                    echo "<input type='button' value='" . xla('Search Products') . "' onclick='sel_related(null,\"PROD\")' />&nbsp;&nbsp;";
                    echo "</p>\n";

                    // Generate a line for each product already in this FS.
                    echo "<table cellpadding='0' cellspacing='2' id='fs_products_table'>\n";
                    echo " <tr>\n";
                    echo "  <td class='border-top-0 font-weight-bold'>" . xlt('Products Provided') . "&nbsp;</td>\n";
                    echo "  <td class='border-top-0 font-weight-bold'>" . xlt('Warehouse') . "&nbsp;</td>\n";
                    echo "  <td class='border-top-0 font-weight-bold text-right'>" . xlt('Quantity') . "&nbsp;</td>\n";
                    echo "  <td class='border-top-0 font-weight-bold text-right'>" . xlt('Price') . "&nbsp;</td>\n";
                    echo "  <td class='border-top-0 font-weight-bold text-right'>" . xlt('Delete') . "</td>\n";
                    echo " </tr>\n";
                    $fs->loadProductItems();
                    foreach ($fs->productitems as $lino => $li) {
                        echo " <tr>\n";
                        echo "  <td class='border-top-0 text'>" . text($li['code_text']) . "&nbsp;</td>\n";
                        echo "  <td class='border-top-0 text'>" .
                            $fs->genWarehouseSelect("form_fs_prod[$lino][warehouse]", '', $li['warehouse'], false, $li['hidden']['drug_id'], true) .
                            "  &nbsp;</td>\n";
                        echo "  <td class='border-top-0 text text-right'>" .
                            "<input class='form-control' type='text' name='form_fs_prod[" . attr($lino) . "][units]' size='3' value='" . attr($li['units']) . "' />" .
                            "&nbsp;</td>\n";
                        echo "  <td class='border-top-0 text text-right'>" . text(oeFormatMoney($li['price'])) . "&nbsp;</td>\n";
                        echo "  <td class='border-top-0 text text-right'>\n" .
                            "   <input type='checkbox' name='form_fs_prod[" . attr($lino) . "][del]' " .
                            "value='1'" . ($li['del'] ? " checked" : "") . " />\n";
                        foreach ($li['hidden'] as $hname => $hvalue) {
                            echo "   <input type='hidden' name='form_fs_prod[" . attr($lino) . "][" . attr($hname) . "]' value='" . attr($hvalue) . "' />\n";
                        }
                        echo "  </td>\n";
                        echo " </tr>\n";
                    }
                    echo "</table>\n";
                    echo "</center>\n";
                    echo "</div>\n";
                } // End Products Section

                if (isset($LBF_DIAGS_SECTION)) {
                    // Create the checkbox and div for the Diagnoses Section.
                    echo "<br /><span class='font-weight-bold'><input type='checkbox' name='form_cb_fs_diags' value='1' " .
                        "onclick='return divclick(this, \"div_fs_diags\");'";
                    if ($display_style == 'block') {
                        echo " checked";
                    }
                    echo " />&nbsp;<b>" . xlt('Diagnoses') . "</b></span>\n";
                    echo "<div id='div_fs_diags' class='section' style='display:" . attr($display_style) . ";'>\n";
                    echo "<center>\n";
                    // $display_style = 'none';

                    // If there are associated codes, generate a checkbox for each one.
                    if ($LBF_DIAGS_SECTION) {
                        echo "<table class='w-100' cellpadding='0' cellspacing='0'>\n";
                        $cols = 3;
                        $tdpct = (int)(100 / $cols);
                        $count = 0;
                        $relcodes = explode(';', $LBF_DIAGS_SECTION);
                        foreach ($relcodes as $codestring) {
                            if ($codestring === '') {
                                continue;
                            }
                            $codes_esc = attr($codestring);
                            $cbval = $fs->genCodeSelectorValue($codestring);
                            if ($count % $cols == 0) {
                                if ($count) {
                                    echo " </tr>\n";
                                }
                                echo " <tr>\n";
                            }
                            echo "  <td class='border-top-0' width='" . attr($tdpct) . "%'>";
                            echo "<input type='checkbox' id='form_fs_diags[$codes_esc]' " .
                                "onclick='fs_diag_clicked(this)' value='" . attr($cbval) . "'";
                            if ($fs->code_is_in_fee_sheet) {
                                echo " checked";
                            }
                            list($codetype, $code) = explode(':', $codestring);
                            $title = lookup_code_descriptions($codestring);
                            $title = empty($title) ? $code : xl_list_label($title);
                            echo " />" . text($title);
                            echo "</td>\n";
                            ++$count;
                        }
                        if ($count) {
                            echo " </tr>\n";
                        }
                        echo "</table>\n";
                    }

                    // A row for Search.
                    $ctype = collect_codetypes('diagnosis', 'csv');
                    echo "<p class='font-weight-bold'>";
                    echo "<input type='button' class='btn btn-primary' value='" . xla('Search Diagnoses') . "' onclick='sel_related(null," . attr_js($ctype) . ")' />";
                    echo "</p>\n";

                    // Generate a line for each diagnosis already in this FS.
                    echo "<table cellpadding='0' cellspacing='2' id='fs_diags_table'>\n";
                    echo " <tr>\n";
                    echo "  <td class='border-top-0 font-weight-bold' colspan='2'>" . xlt('Diagnosis') . "&nbsp;</td>\n";
                    echo "  <td class='border-top-0 font-weight-bold text-right'>" . xlt('Delete') . "</td>\n";
                    echo " </tr>\n";
                    foreach ($fs->serviceitems as $lino => $li) {
                        // Skip anything that is not a diagnosis; those are in the Services section above.
                        if (!$code_types[$li['codetype']]['diag']) {
                            continue;
                        }
                        echo " <tr>\n";
                        echo "  <td class='border-top-0 text'>" . text($li['code']) . "&nbsp;</td>\n";
                        echo "  <td class='border-top-0 text'>" . text($li['code_text']) . "&nbsp;</td>\n";
                        // The Diagnoses section shares the form_fs_bill array with the Services section.
                        echo "  <td class='border-top-0 text text-right'>\n" .
                            "   <input type='checkbox' name='form_fs_bill[" . attr($lino) . "][del]' " .
                            "value='1'" . ($li['del'] ? " checked" : "") . " />\n";
                        foreach ($li['hidden'] as $hname => $hvalue) {
                            echo "   <input type='hidden' name='form_fs_bill[" . attr($lino) . "][" . attr($hname) . "]' value='" . attr($hvalue) . "' />\n";
                        }
                        echo "  </td>\n";
                        echo " </tr>\n";
                    }
                    echo "</table>\n";
                    echo "</center>\n";
                    echo "</div>\n";
                } // End Diagnoses Section

                if ($LBF_REFERRALS_SECTION) {
                    // Create the checkbox and div for the Referrals Section.
                    echo "<br /><span class='bold'><input type='checkbox' name='form_cb_referrals' value='1' " .
                        "onclick='return divclick(this, \"div_referrals\");'";
                    if ($display_style == 'block') {
                        echo " checked";
                    }
                    echo " />&nbsp;<b>" . xlt('Referrals') . "</b></span>\n";
                    echo "<div id='div_referrals' class='section' style='display:" . attr($display_style) . ";'>\n";
                    echo "<center>\n";
                    // $display_style = 'none';

                    // Generate a table row for each referral in the visit.
                    echo "<table cellpadding='0' cellspacing='5' id='referrals_table'>\n";
                    echo " <tr>\n";
                    echo "  <td class='bold'>" . xlt('Date') . "&nbsp;</td>\n";
                    echo "  <td class='bold'>" . xlt('Type') . "&nbsp;</td>\n";
                    echo "  <td class='bold'>" . xlt('Reason') . "&nbsp;</td>\n";
                    echo "  <td class='bold'>" . xlt('Referred To') . "&nbsp;</td>\n";
                    echo "  <td class='bold'>" . xlt('Requested Service') . "</td>\n";
                    echo " </tr>\n";

                    $refres = sqlStatement(
                        "SELECT f.form_id, " .
                        "d1.field_value AS refer_external, " .
                        "d2.field_value AS body, " .
                        "lo.title AS refer_type, " .
                        "ut.organization, " .
                        "CONCAT(ut.fname,' ', ut.lname) AS referto_name, " .
                        "d4.field_value AS refer_related_code, " .
                        "d5.field_value AS refer_date " .
                        "FROM forms AS f " .
                        "LEFT JOIN lbf_data AS d1 ON d1.form_id = f.form_id AND d1.field_id = 'refer_external' " .
                        "LEFT JOIN list_options AS lo ON list_id = 'reftype' and option_id = d1.field_value " .
                        "LEFT JOIN lbf_data AS d2 ON d2.form_id = f.form_id AND d2.field_id = 'body' " .
                        "LEFT JOIN lbf_data AS d3 ON d3.form_id = f.form_id AND d3.field_id = 'refer_to' " .
                        "LEFT JOIN users AS ut ON ut.id = d3.field_value " .
                        "LEFT JOIN lbf_data AS d4 ON d4.form_id = f.form_id AND d4.field_id = 'refer_related_code' " .
                        "LEFT JOIN lbf_data AS d5 ON d5.form_id = f.form_id AND d5.field_id = 'refer_date' " .
                        "WHERE " .
                        "f.pid = ? AND f.encounter = ? AND f.formdir = 'LBFref' AND f.deleted = 0 " .
                        "ORDER BY refer_date, f.form_id",
                        array($pid, $encounter)
                    );

                    while ($refrow = sqlFetchArray($refres)) {
                        $svcstring = '';
                        if (!empty($refrow['refer_related_code'])) {
                            // Get referred services.
                            $relcodes = explode(';', $refrow['refer_related_code']);
                            foreach ($relcodes as $codestring) {
                                if ($codestring === '') {
                                    continue;
                                }
                                ++$svccount;
                                list($codetype, $code) = explode(':', $codestring);
                                $rrow = sqlQuery(
                                    "SELECT code_text FROM codes WHERE " .
                                    "code_type = ? AND code = ? " .
                                    "ORDER BY active DESC, id ASC LIMIT 1",
                                    array($code_types[$codetype]['id'], $code)
                                );
                                $code_text = empty($rrow['code_text']) ? '' : $rrow['code_text'];
                                if ($svcstring) {
                                    $svcstring .= '<br />';
                                }
                                $svcstring .= text("$code: $code_text");
                            }
                        }
                        echo " <tr style='cursor:pointer;cursor:hand' " .
                            "onclick=\"openLBFEncounterForm('LBFref', 'Referral', " .
                            attr_js($refrow['form_id']) . ")\">\n";
                        echo "  <td class='text linkcolor'>" . text(oeFormatShortDate($refrow['refer_date'])) . "&nbsp;</td>\n";
                        echo "  <td class='text linkcolor'>" . text($refrow['refer_type']) . "&nbsp;</td>\n";
                        echo "  <td class='text linkcolor'>" . text($refrow['body']) . "&nbsp;</td>\n";
                        echo "  <td class='text linkcolor'>" . text($refrow['organization'] ? $refrow['organization'] : $refrow['referto_name']) . "&nbsp;</td>\n";
                        echo "  <td class='text linkcolor'>" . $svcstring . "&nbsp;</td>\n";
                        echo " </tr>\n";
                    }

                    echo " <tr style='cursor:pointer;cursor:hand' onclick=\"openLBFNewForm('LBFref', 'Referral')\">\n";
                    echo "  <td class='bold linkcolor' colspan='5'>" . xlt('Create New Referral') . "</td>\n";
                    echo " </tr>\n";
                    echo "</table>\n";
                    echo "</center>\n";
                    echo "</div>\n";
                } // End Referrals Section

                ?>
                <br />
                <div class='row'>
                    <div class='col-12'>
                        <div class="btn-group">
                            <?php
                            if (!$from_trend_form && !$from_lbf_edit && $is_core) {
                                // Generate price level selector if we are doing services or products.
                                if (isset($LBF_SERVICES_SECTION) || isset($LBF_PRODUCTS_SECTION)) {
                                    echo xlt('Price Level') . ": ";
                                    echo $fs->generatePriceLevelSelector('form_fs_pricelevel');
                                    echo "&nbsp;&nbsp;";
                                }
                                ?>
                                <button type="submit" class="btn btn-primary btn-save" name="bn_save"
                                    onclick='submitButtonName = this.name;'
                                    value="<?php echo xla('Save'); ?>">
                                    <?php echo xlt('Save'); ?>
                                </button>

                                <button type='submit' class="btn btn-secondary" name='bn_save_continue'
                                    onclick='submitButtonName = this.name;'
                                    value='<?php echo xla('Save and Continue') ?>'>
                                    <?php echo xlt('Save and Continue'); ?>
                                </button>

                                <?php if ($LBF_ENABLE_SAVE_CLOSE) { ?>
                                    <button type='submit' class="btn btn-secondary" name='bn_save_close'
                                        onclick='submitButtonName = this.name;'
                                        value='<?php echo xla('Save and Close Visit') ?>'>
                                        <?php echo xlt('Save and Close Visit'); ?>
                                    </button>
                                <?php } ?>

                                <?php
                                if (!$from_issue_form) {
                                    ?>
                                    <button type='submit' class="btn btn-secondary" name='bn_save_print'
                                        onclick='submitButtonName = this.name;'
                                        value='<?php echo xla('Save and Print') ?>'>
                                        <?php echo xlt('Save and Print'); ?>
                                    </button>
                                    <?php
                                    if (function_exists($formname . '_additional_buttons')) {
                                        // Allow the plug-in to insert more action buttons here.
                                        call_user_func($formname . '_additional_buttons');
                                    }

                                    if ($form_is_graphable) {
                                        ?>
                                        <button type='button' class="btn btn-secondary btn-graph"
                                            onclick="top.restoreSession();location='../../patient_file/encounter/trend_form.php?formname=<?php echo attr_url($formname); ?>'">
                                            <?php echo xlt('Show Graph') ?>
                                        </button>
                                        &nbsp;
                                        <?php
                                    } // end form is graphable
                                    ?>
                                    <button type='button' class="btn btn-secondary btn-cancel" onclick="verifyCancel()">
                                        <?php echo xlt('Cancel'); ?>
                                    </button>
                                    <?php
                                } // end not from issue form
                                ?>
                                <?php
                            } elseif (!$from_lbf_edit && $is_core) { // $from_trend_form is true but lbf edit doesn't want button
                                ?>
                                <button type='button' class="btn btn-secondary btn-back" onclick='window.history.back();'>
                                    <?php echo xlt('Back') ?>
                                </button>
                                <?php
                            } // end from trend form
                            ?>
                        </div>
                    </div>
                </div>
                <hr>

                <?php if (!$from_trend_form) { // end row and container divs ?>
                    <p style='text-align:center' class='small'>
                        <?php echo text(xl('Rev.') . ' ' . substr($grp_last_update ?? '', 0, 10)); ?>
                    </p>

                <?php } ?>

                <input type='hidden' name='from_issue_form' value='<?php echo attr($from_issue_form); ?>' />
                <?php if (!$is_core) {
                    echo '<input type="hidden" name="csrf_token_form" value="' . CsrfUtils::collectCsrfToken() . '" />';
                    echo "\n<input type='hidden' name='bn_save_continue' value='set' />\n";
                } ?>

                <!-- include support for the list-add selectbox feature -->
                <?php require $GLOBALS['fileroot'] . "/library/options_listadd.inc.php"; ?>

                <!-- @VH: Scripts -->
                <script src="<?php echo $GLOBALS['webroot']; ?>/library/wmt-v2/wmtpopup.js" type="text/javascript"></script>
                <script src="<?php echo $GLOBALS['webroot']; ?>/library/wmt-v2/wmt.forms.js" type="text/javascript"></script>
                <!-- END -->

                <script>
                    // Array of action conditions for the checkSkipConditions() function.
                    var skipArray = [
                        <?php echo $condition_str; ?>
                    ];

                    <?php echo $date_init; ?>
                    <?php
                    if (function_exists($formname . '_javascript_onload')) {
                        call_user_func($formname . '_javascript_onload');
                    }

                    if ($alertmsg) {
                        echo "alert(" . js_escape($alertmsg) . ");\n";
                    }
                    ?>
                    /*
                    * Setup channel with portal
                    * Mainly for form submission from remote for now.
                    * umm you never know!
                    * */
                    <?php if (empty($is_core)) { ?>
                    $(function () {
                        window.addEventListener("message", (e) => {
                            if (event.origin !== window.location.origin) {
                                signerAlertMsg(<?php echo xlj("Request is not same origin!") ?>, 15000);
                                return false;
                            }
                            if (e.data.submitForm === true) {
                                let pass = validate(document.forms[0], false);
                                if (pass) {
                                    signerAlertMsg(<?php echo xlj("Working on request.") ?>, 5000, 'info');
                                    e.preventDefault();
                                    document.forms[0].submit();
                                } else {
                                    signerAlertMsg(<?php echo xlj("Form validation failed. Fix any errors and resubmit.") ?>);
                                    return false;
                                }
                            } else if (e.data.submitForm === 'history') {
                                e.preventDefault();
                                let portal_form_pid = Number(<?php echo js_escape($portal_form_pid); ?>);
                                if (portal_form_pid) {
                                    signerAlertMsg(<?php echo xlj("Charting History.") ?>, 5000, 'info');
                                } else {
                                    signerAlertMsg(<?php echo xlj("Sorry. Can not update history.") ?>, 8000, 'danger');
                                    return false;
                                }
                                top.restoreSession();
                                document.forms[0].action = "<?php echo $rootdir; ?>/patient_file/history/history_save.php?requestPid=" + encodeURIComponent(portal_form_pid);
                                document.forms[0].onsubmit = "";
                                document.forms[0].submit();
                            }
                        });
                    });
                    <?php }
                    if (empty($is_core) && !empty($_POST['bn_save_continue'])) { ?>
                    /* post event to portal with current formid from save/edit action */
                    parent.postMessage({formid:<?php echo attr($formid) ?>}, window.location.origin);
                    <?php } ?>
                </script>

            </div>
        </div>
        </form>
    </div><!-- end container -->
</body>
</html>