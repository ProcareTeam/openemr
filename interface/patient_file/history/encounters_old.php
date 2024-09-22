<?php

/**
 * Encounter list.
 *
 * @package   OpenEMR
 * @link      http://www.open-emr.org
 * @author    Roberto Vasquez <robertogagliotta@gmail.com>
 * @author    Brady Miller <brady.g.miller@gmail.com>
 * @author    Jerry Padgett <sjpadgett@gmail.com>
 * @copyright Copyright (c) 2015 Roberto Vasquez <robertogagliotta@gmail.com>
 * @copyright Copyright (c) 2018 Brady Miller <brady.g.miller@gmail.com>
 * @copyright Copyright (c) 2018-2021 Jerry Padgett <sjpadgett@gmail.com>
 * @license   https://github.com/openemr/openemr/blob/master/LICENSE GNU General Public License 3
 */

require_once(__DIR__ . "/../../globals.php");
require_once("$srcdir/forms.inc");
require_once("$srcdir/patient.inc");
require_once("$srcdir/lists.inc");
require_once(__DIR__ . "/../../../custom/code_types.inc.php");
if ($GLOBALS['enable_group_therapy']) {
    require_once("$srcdir/group.inc");
}
require_once($GLOBALS['fileroot'] . "/controllers/C_Document.class.php");
require_once("$srcdir/OemrAD/oemrad.globals.php");

use OpenEMR\Common\Acl\AclMain;
use OpenEMR\Billing\BillingUtilities;
use OpenEMR\Billing\InvoiceSummary;
use OpenEMR\Common\Csrf\CsrfUtils;
use OpenEMR\Core\Header;
use OpenEMR\Services\AppointmentService;
use OpenEMR\OemrAd\Utility;

$is_group = ($attendant_type == 'gid') ? true : false;

// "issue" parameter exists if we are being invoked by clicking an issue title
// in the left_nav menu.  Currently that is just for athletic teams.  In this
// case we only display encounters that are linked to the specified issue.
$issue = empty($_GET['issue']) ? 0 : 0 + $_GET['issue'];

 //maximum number of encounter entries to display on this page:
 // $N = 12;

 //Get the default encounter from Globals
 $default_encounter = $GLOBALS['default_encounter_view']; //'0'=clinical, '1' = billing

// Get relevant ACL info.
$auth_notes_a = AclMain::aclCheckCore('encounters', 'notes_a');
$auth_notes = AclMain::aclCheckCore('encounters', 'notes');
$auth_coding_a = AclMain::aclCheckCore('encounters', 'coding_a');
$auth_coding = AclMain::aclCheckCore('encounters', 'coding');
$auth_relaxed = AclMain::aclCheckCore('encounters', 'relaxed');
$auth_med = AclMain::aclCheckCore('patients', 'med');
$auth_demo = AclMain::aclCheckCore('patients', 'demo');
$glog_view_write = AclMain::aclCheckCore("groups", "glog", false, array('view', 'write'));

/* OEMR - Packet selection related functionality*/
$selected_packet = "All";
$edit_packet = 0;
$create_packet = 0;

$filter_document_ids = [];
$filter_encounter_ids = [];
$packets_items = [];
$edit_title = "";
//$packet_id =0;

$visit_history_items = array();

if(isset($_POST['action_mode'])) {
    if($_POST['action_mode'] == "save_packet") {

        $reqResponce = array();

        try {

            $packet_title = $_POST["packet_title"];
            $document_ids = $_POST["document_ids"];
            $form_encounter_ids = $_POST["form_encounter_ids"];

            $packetId = sqlInsert("INSERT into `vh_visit_history_packet` (`packet_title`, `pid`) values (?, ?)", array($packet_title, $pid));

            if(!empty($packetId)) {
                if(!empty($form_encounter_ids)) {
                    foreach ($form_encounter_ids as $encItem) {
                        // Save encounter items into visit history packet items
                        sqlInsert("INSERT into `vh_visit_history_packet_items` (`packet_id`, `type`, `item_id`) values (?, ?, ?)", array($packetId, 'encounter', $encItem));
                    }
                }

                if(!empty($document_ids)) {
                    foreach ($document_ids as $docItem) {
                        // Save document items into visit history packet items
                        sqlInsert("INSERT into `vh_visit_history_packet_items` (`packet_id`, `type`, `item_id`) values (?, ?, ?)", array($packetId, 'document', $docItem));
                    }
                }
            }

            $reqResponce['packet_id'] = $packetId;
            $reqResponce['message'] = xlt("Packet Created");

        } catch (\Throwable $e) {
            $reqResponce['error'] = $e->getMessage();
        }

        echo json_encode($reqResponce);

        exit();
    } else if($_POST['action_mode'] == "update_packet") {
        $reqResponce = array();

        try {

            if(!isset($_POST['packet_id']) || empty($_POST['packet_id'])) {
                throw new \Exception("Empty packet id");
            }

            if(!isset($_POST['packet_title']) || empty($_POST['packet_title'])) {
                throw new \Exception("Empty packet title");
            }

            $document_ids = $_POST["document_ids"];
            $form_encounter_ids = $_POST["form_encounter_ids"];

            $packetS = sqlStatement("UPDATE `vh_visit_history_packet` SET packet_title = ? WHERE id = ? ", array($_POST["packet_title"], $_POST['packet_id']));

            if(!empty($packetS) && !empty($_POST['packet_id'])) {

                // Delete items before update
                sqlStatement("DELETE from `vh_visit_history_packet_items` WHERE packet_id = ? ", array($_POST['packet_id']) );

                if(!empty($form_encounter_ids)) {
                    foreach ($form_encounter_ids as $encItem) {
                        // Save encounter items into visit history packet items
                        sqlInsert("INSERT into `vh_visit_history_packet_items` (`packet_id`, `type`, `item_id`) values (?, ?, ?)", array($_POST['packet_id'], 'encounter', $encItem));
                    }
                }

                if(!empty($document_ids)) {
                    foreach ($document_ids as $docItem) {
                        // Save document items into visit history packet items
                        sqlInsert("INSERT into `vh_visit_history_packet_items` (`packet_id`, `type`, `item_id`) values (?, ?, ?)", array($_POST['packet_id'], 'document', $docItem));
                    }
                }
            }

            $reqResponce['packet_id'] = $_POST['packet_id'];
            $reqResponce['message'] = xlt("Packet Updated");

        } catch (\Throwable $e) {
            $reqResponce['error'] = $e->getMessage();
        }

        echo json_encode($reqResponce);

        exit();
    } else if($_POST['action_mode'] == "delete_packet") {

        $reqResponce = array();

        try {

            if(!isset($_POST['packet_id']) || empty($_POST['packet_id'])) {
                throw new \Exception("Empty packet id");
            }

            $packetDelete = sqlStatement("DELETE FROM `vh_visit_history_packet` WHERE id = ? ", array($_POST['packet_id']) );

            if($packetDelete) {
                // Delete items
                sqlStatement("DELETE from `vh_visit_history_packet_items` WHERE packet_id = ? ", array($_POST['packet_id']) );
            }
 
            $reqResponce['message'] = xlt("Packet Deleted");

        } catch (\Throwable $e) {
            $reqResponce['error'] = $e->getMessage();
        }

        echo json_encode($reqResponce);

        exit();
    } else if($_POST['action_mode'] == "save_sequence") {
        $reqResponce = array();

        try {

            if(!isset($_POST['packet_id']) || empty($_POST['packet_id'])) {
                throw new \Exception("Empty packet id");
            }

            foreach($_POST as $key=>$val){
                if(str_contains($key, 'document_squ_no_')) {
                    $id = str_replace("document_squ_no_", "",$key);
                    sqlStatement("UPDATE `vh_visit_history_packet_items` SET seq = ? WHERE item_id = ? and packet_id = ?", array($val, $id, $_POST['packet_id']));
                } else if(str_contains($key, 'encounter_squ_no_')) {
                    $id = str_replace("encounter_squ_no_", "",$key);
                    sqlStatement("UPDATE `vh_visit_history_packet_items` SET seq = ? WHERE item_id = ? and packet_id = ?", array($val, $id, $_POST['packet_id']));
                }     
            }

            $reqResponce['message'] = xlt("Packet Seq Updated");

        } catch (\Throwable $e) {
            $reqResponce['error'] = $e->getMessage();
        }

        echo json_encode($reqResponce);

        exit();
    }
}


$currentPacketInfo = array();

if(isset($_POST["action_mode"]) && $_POST["action_mode"] == "edit_packet") {
    $edit_packet = 1;
    $create_packet = 0;
} else if(isset($_POST["action_mode"]) && $_POST["action_mode"] == "create_packet") {
    $edit_packet = 0;
    $create_packet = 1;
}

$packetHasSeq = false;
if(isset($_POST["packet_id"]) ) { 
    $selected_packet = $_POST["packet_id"];
    if($selected_packet !=="All") {

        $currentPacketInfo = sqlQuery("SELECT * from `vh_visit_history_packet` WHERE id =  ? ", array($selected_packet));

        if(!empty($currentPacketInfo)) {
            $edit_title = $currentPacketInfo['packet_title'];

            $packetItemsSql = sqlStatement("SELECT * from vh_visit_history_packet_items WHERE packet_id = ?", array($currentPacketInfo['id']));
            while ($packetItemsRow = sqlFetchArray($packetItemsSql)) {
                if(isset($packetItemsRow['type']) && $packetItemsRow['type'] == "encounter") {
                    $filter_encounter_ids[] = $packetItemsRow['item_id'];
                    $packets_items['enc_' . $packetItemsRow['item_id']] = $packetItemsRow;
                } else if(isset($packetItemsRow['type']) && $packetItemsRow['type'] == "document") {
                    $filter_document_ids[] = $packetItemsRow['item_id'];
                    $packets_items['doc_' . $packetItemsRow['item_id']] = $packetItemsRow;
                }

                if(isset($packetItemsRow['seq']) && $packetItemsRow['seq'] > 0) {
                    $packetHasSeq = true;
                }
            }
        }
    } 
}

// Packets info
$packetsSql =  sqlStatement("select * from vh_visit_history_packet where pid = ? order by created_date desc", array($pid));

/* OEMR - End */
 

$tmp = getPatientData($pid, "squad");
if (($tmp['squad'] ?? null) && ! AclMain::aclCheckCore('squads', $tmp['squad'])) {
    $auth_notes_a = $auth_notes = $auth_coding_a = $auth_coding = $auth_med = $auth_demo = $auth_relaxed = 0;
}

// Perhaps the view choice should be saved as a session variable.
//
$tmp = sqlQuery("select authorized from users " .
  "where id = ?", array($_SESSION['authUserID']));
$billing_view = ($tmp['authorized']) ? 0 : 1;
$enh_clinical_view = 0;

// OEMR - Sort direction & searchtext
$sortdirection = isset($_GET['sortdirection']) ? $_GET['sortdirection'] : "DESC";
$searchText = isset($_REQUEST['search_text']) && !empty($_REQUEST['search_text']) ? $_REQUEST['search_text'] : "";

if (isset($_GET['enh_clinical'])) {
    $enh_clinical_view = empty($_GET['enh_clinical']) ? 0 : 1;
    $billing_view = 0;
} else if (isset($_GET['billing'])) {
    $billing_view = empty($_GET['billing']) ? 0 : 1;
} else {
    $billing_view = ($default_encounter == 0) ? 0 : 1;
}

/* OEMR - Save current history view */
if (!isset($get_items_only)) {
if (isset($_GET['billing']) || isset($_GET['enh_clinical'])) {
    if($_GET['billing'] == "1") {
        Utility::saveSectionValues($_SESSION['authUserID'], 'visit_history_view', 'billing_view');
    } else if($_GET['enh_clinical'] == "1") {
        Utility::saveSectionValues($_SESSION['authUserID'], 'visit_history_view', 'enhanced_view');
    } else {
        Utility::saveSectionValues($_SESSION['authUserID'], 'visit_history_view', 'default_view');
    }
}

if (isset($_SESSION['authUserID']) && !empty($_SESSION['authUserID'])) {
    //Selection Values
    $defaultViewVal = Utility::getSectionValues($_SESSION['authUserID'], 'visit_history_view');

    if(!empty($defaultViewVal) && isset($defaultViewVal['visit_history_view']) && !empty($defaultViewVal['visit_history_view'])) {
        if($defaultViewVal['visit_history_view'] == "billing_view") {
            $billing_view = 1;
            $enh_clinical_view = 0;
        } else if($defaultViewVal['visit_history_view'] == "enhanced_view") {
            $billing_view = 0;
            $enh_clinical_view = 1;
        } else {
            $billing_view = 0;
            $enh_clinical_view = 0;
        }
    }
}
}


/* End */

//Get Document List by Encounter ID
function getDocListByEncID($encounter, $raw_encounter_date, $pid, $raw_encounter_seq = 0)
{
    global $ISSUE_TYPES, $auth_med;

    $documents = getDocumentsByEncounter($pid, $encounter);
    if (!empty($documents) && count($documents) > 0) {
        foreach ($documents as $documentrow) {
            if ($auth_med) {
                $irow = sqlQuery("SELECT type, title, begdate FROM lists WHERE id = ? LIMIT 1", array($documentrow['list_id']));
                if ($irow) {
                    $tcode = $irow['type'];
                    if ($ISSUE_TYPES[$tcode]) {
                        $tcode = $ISSUE_TYPES[$tcode][2];
                    }
                    echo text("$tcode: " . $irow['title']);
                }
            } else {
                echo "(" . xlt('No access') . ")";
            }

            // Get the notes for this document and display as title for the link.
            $queryString = "SELECT date,note FROM notes WHERE foreign_id = ? ORDER BY date";
            $noteResultSet = sqlStatement($queryString, array($documentrow['id']));
            $note = '';
            while ($row = sqlFetchArray($noteResultSet)) {
                $note .= oeFormatShortDate(date('Y-m-d', strtotime($row['date']))) . " : " . $row['note'] . "\n";
            }
            $docTitle = ( $note ) ? $note : xl("View document");

            $docHref = $GLOBALS['webroot'] . "/controller.php?document&view&patient_id=" . attr_url($pid) . "&doc_id=" . attr_url($documentrow['id']);
            echo "<div class='text docrow' id='" . attr($documentrow['id']) . "'data-toggle='tooltip' data-placement='top' title='" . attr($docTitle) . "'>\n";
            echo "<a href='$docHref' onclick='top.restoreSession()' >" . xlt('Document') . ": " . text($documentrow['document_name'])  . '-' . $documentrow['id'] . ' (' . text(xl_document_category($documentrow['name'])) . ')' . "</a>";
            echo "</div>";
        }
    }
}

// This is called to generate a line of output for a patient document.
//
function showDocument(&$drow)
{
    global $ISSUE_TYPES, $auth_med, $visit_history_document_item, $get_items_only;

    $docdate = $drow['docdate'];

    // Assign document
    if (isset($get_items_only) && $get_items_only === true) {
        // $d = new Document($drow['id']);
        // $docObj = new C_Document();
        // $documentContent = $docObj->retrieve_action($d->foreign_id, $d->id, true, true, true);

        // Assign document data to visit history
        $visit_history_document_item = array(
            "type" => "document",
            "date" => text(oeFormatShortDate($docdate)),
            "document_id" => $drow['id'],
            "document_name" => $drow['document_name'],
            "document_category" => xl_document_category($drow['name']),
            "issue" => array(),
            "document" => ""
        );
    }

    // if doc is already tagged by encounter it already has its own row so return
    $doc_tagged_enc = $drow['encounter_id'];
    if ($doc_tagged_enc) {
        return;
    }

    echo "<tr class='text docrow' id='" . attr($drow['id']) . "'data-toggle='tooltip' data-placement='top' title='" . xla('View document') . "'>\n";

  // show date
    echo "<td>" . text(oeFormatShortDate($docdate)) . "</td>\n";

  // show associated issue, if any
    echo "<td>";
    if ($auth_med) {
        $irow = sqlQuery("SELECT type, title, begdate " .
        "FROM lists WHERE " .
        "id = ? " .
        "LIMIT 1", array($drow['list_id']));
        if ($irow) {
              $tcode = $irow['type'];
            if ($ISSUE_TYPES[$tcode]) {
                $tcode = $ISSUE_TYPES[$tcode][2];
            }
              echo text("$tcode: " . $irow['title']);

            // Assign document issue
            if (isset($get_items_only) && $get_items_only === true) {
                $visit_history_document_item['issue'][] = text("$tcode: " . $irow['title']);
            }
        }
    } else {
        echo "(" . xlt('No access') . ")";
    }
    echo "</td>\n";

  // show document name and category
    echo "<td colspan='3'>" .
    text(xl('Document') . ": " . $drow['document_name'] . '-' . $drow['id'] . ' (' . xl_document_category($drow['name']) . ')') .
    "</td>\n";
    echo "<td colspan='5'>&nbsp;</td>\n";
    echo "</tr>\n";
}

// This is called to generate a line of output for a ext billing patient document.
//
function showDocument1(&$drow, $edit_packet_, $create_packet_, $is_from_packet, $sequace_no)
{
    global $ISSUE_TYPES, $auth_med, $pid, $visit_history_document_item, $get_items_only;

    $docdate = $drow['docdate'];

    // Assign document
    if (isset($get_items_only) && $get_items_only === true) {
        // $d = new Document($drow['id']);
        // $docObj = new C_Document();
        // $documentContent = $docObj->retrieve_action($d->foreign_id, $d->id, true, true, true);

        // Assign document data to visit history
        $visit_history_document_item = array(
            "type" => "document",
            "date" => text(oeFormatShortDate($docdate)),
            "document_id" => $drow['id'],
            "document_file_name" => "",
            "document_name" => $drow['document_name'],
            "document_category" => xl_document_category($drow['name']),
            "document" => ""
        );
    }

    // if doc is already tagged by encounter it already has its own row so return
    $doc_tagged_enc = $drow['encounter_id'];
    if ($doc_tagged_enc) {
        return;
    }

    $docPath = isset($drow['document_name']) ? $drow['document_name'] : "";

    if(!empty($drow['cat_id'])) {
        $catTree = getCategoryPath($drow['cat_id']);
        unset($catTree[0]);
        $catTree[] = $drow['document_name'];

        $docPath = implode("/", $catTree);
    }

    // Assign document issue
    if (isset($get_items_only) && $get_items_only === true) {
        $visit_history_document_item['document_file_name'] = text($docPath);
    }

    echo "<tr >\n";
    echo "<td>";
    if($edit_packet_ == 1 && $is_from_packet== 1)
    {
        echo "<input type='checkbox' checked class='packet_checkbox' data-type='doc' style='display:none' name='document_ids[]' value='" . $drow['id'] . "'>";
    } else {
        echo "<input type='checkbox' class='packet_checkbox' data-type='doc' style='display:none' name='document_ids[]' value='" . $drow['id'] . "'>";
    }

    if(isset($_POST['packet_id']) && !empty($_POST['packet_id']) && $_POST['packet_id'] != "All") {
        if($edit_packet_ !== 1 && $create_packet_ !== 1) {
            echo "<input type='checkbox' class='sel_checkbox' data-type='doc' value='" . $drow['id'] . "'>";
        } 
    }

    if($edit_packet_ !== 1 && isset($_POST['packet_id']) && !empty($_POST['packet_id']) && $_POST['packet_id'] != "All") {
        echo " <input type='textbox' name='document_squ_no_" . $drow["id"] ."' id='document_squ_no_" . $drow["id"] ."' value='" . $sequace_no . "' class='vh_sequance_no form-control'  style='max-width:50px;' >";
    }
    echo "</td>";
    
    // show date
    echo "<td class='encrow' id='" . attr($rawdata) . "'>" . text(oeFormatShortDate($docdate)) . "</td>\n";

    // show document name and category
    echo "<td colspan='3' class='text docrow' id='" . attr($drow['id']) . "' data-toggle='tooltip' data-placement='top' title='" . xla('View document') . "'><div data-toggle='PopOverDocument' data-documentpid='" . $pid. "' data-documentid='" . $drow['id'] . "'  data-original-title='Document <i>Click or change focus to dismiss</i>'><input type='checkbox' class='doc_". $drow['id'] ."' name='documents[]' value='" . $drow['id'] . "' style='display:none;' >" .
    text(xl('Document') . ": " . $docPath) .
    "</div></td>\n";
    echo "<td colspan='5'>&nbsp;</td>\n";
    echo "</tr>\n";
}

function getCategoryPath($catId = '') {
    $catList = array();

    if(empty($catId)) {
        return $catList;
    }

    $catData = sqlQuery("SELECT c.* FROM categories c where id = ? order by id desc", array($catId));

    if(!empty($catData) && isset($catData['name'])) {
        $catList[] = $catData['name'];

        if(isset($catData['parent']) && !empty($catData['parent'])) {
            $parentCatList = getCategoryPath($catData['parent']);

            if(!empty($parentCatList)) {
                $catList = array_merge($parentCatList, $catList);
            }
        }
    }

    return $catList;
}

function generatePageElement($start, $pagesize, $billing, $issue, $text, $enh_clinical = 0, $linkid = '', $encpagestart = 0, $docpagestart = 0)
{
    if ($start < 0) {
        $start = 0;
    }
    $url = "encounters.php?pagestart=" . attr_url($start) . "&pagesize=" . attr_url($pagesize);
    $url .= "&billing=" . attr_url($billing);
    $url .= "&issue=" . attr_url($issue);

    if ($encpagestart > 0) {
        $url .= "&encpagestart=" . attr_url($encpagestart);
    }

    if ($docpagestart > 0) {
        $url .= "&docpagestart=" . attr_url($docpagestart);
    }

    if ($enh_clinical > 0) {
        $url .= "&enh_clinical=" . attr_url($enh_clinical);
    }

    echo "<a href='" . $url . "' onclick='top.restoreSession()' id='" . $linkid . "'>" . $text . "</a>";
}

function fetch_appt_signatures_data_byId($eid) {
    if(!empty($eid)) {
        $eSql = "SELECT FE.encounter, E.id, E.tid, E.table, E.uid, E.datetime, E.is_lock, E.amendment, E.hash, E.signature_hash 
                FROM form_encounter FE 
                LEFT JOIN esign_signatures E ON  FE.encounter = E.tid AND E.is_lock = 1
                WHERE FE.encounter = ?
                ORDER BY E.datetime ASC";
        $result = sqlQuery($eSql, array($eid));
        return $result;
    }
    return false;
}

?>
<!DOCTYPE html>
<html>
<head>
<!-- Main style sheet comes after the page-specific stylesheet to facilitate overrides. -->
<?php if ($_SESSION['language_direction'] == "rtl") { ?>
  <link rel="stylesheet" href="<?php echo $GLOBALS['themes_static_relative']; ?>/misc/rtl_encounters.css?v=<?php echo $GLOBALS['v_js_includes']; ?>" />
<?php } else { ?>
  <link rel="stylesheet" href="<?php echo $GLOBALS['themes_static_relative']; ?>/misc/encounters.css?v=<?php echo $GLOBALS['v_js_includes']; ?>" />
<?php } ?>
<!-- Not sure why we don't want this ui to be B.S responsive. -->
<?php Header::setupHeader(['no_textformat']); ?>

<script src="<?php echo $GLOBALS['webroot'] ?>/library/js/ajtooltip.js"></script>

<script>
// open dialog to edit an invoice w/o opening encounter.
function editInvoice(e, id) {
    e.stopPropagation();
    const url = './../../billing/sl_eob_invoice.php?id=' + encodeURIComponent(id);
    dlgopen(url, '', 'modal-lg', 750, false, '', {
        onClosed: 'reload'
    });
}

//function toencounter(enc, datestr) {
function toencounter(rawdata) {
    var parts = rawdata.split("~");
    var enc = parts[0];
    var datestr = parts[1];

    top.restoreSession();
    parent.left_nav.setEncounter(datestr, enc, window.name);
    
    // OEMR - Commented load encounter
    //parent.left_nav.loadFrame('enc22', window.name, 'patient_file/encounter/encounter_top.php?set_encounter=' + encodeURIComponent(enc));

    // OEMR - Load encounter in new tab
    top.navigateTab(top.webroot_url + '/interface/patient_file/encounter/encounter_top.php?set_encounter=' + encodeURIComponent(enc),"enc22", function () {
        top.activateTabByName("enc22",true);
    });
}

function todocument(docid) {
  h = '<?php echo $GLOBALS['webroot'] ?>/controller.php?document&view&patient_id=<?php echo attr_url($pid); ?>&doc_id=' + encodeURIComponent(docid);
  top.restoreSession();
  
  // OEMR - Commented load document
  //location.href = h;

  // OEMR - Load document in new tab
  top.navigateTab(h,"enc22", function () {
    top.activateTabByName("enc22",true);
  });
}

 // Helper function to set the contents of a div.
function setDivContent(id, content) {
    $("#"+id).html(content);
}

function changePageSize() {
    billing = $(this).attr("billing");
    //pagestart = $(this).attr("pagestart");
    pagestart = 0;
    issue = $(this).attr("issue");
    pagesize = $(this).val();

    //encpagestart = $(this).attr('encpagestart');
    //docpagestart = $(this).attr('docpagestart');

    // OEMR - clinical view
    enh_clinical = $(this).attr("enh_clinical");

    top.restoreSession();
    window.location.href = "encounters.php?billing=" + encodeURIComponent(billing) + "&issue=" + encodeURIComponent(issue) + "&pagestart=" + encodeURIComponent(pagestart) + "&pagesize=" + encodeURIComponent(pagesize) + "&enh_clinical=" + encodeURIComponent(enh_clinical);
}

function sortButton() {
    billing = $(this).attr("billing");
    //pagestart = $(this).attr("pagestart");
    pagestart = 0;
    issue = $(this).attr("issue");
    pagesize = $(this).val();

    // OEMR - clinical view
    enh_clinical = $(this).attr("enh_clinical");
    sortdirection = $(this).attr("sortdirection");

    top.restoreSession();
    window.location.href = "encounters.php?billing=" + encodeURIComponent(billing) + "&issue=" + encodeURIComponent(issue) + "&pagestart=" + encodeURIComponent(pagestart) + "&pagesize=" + encodeURIComponent(pagesize) + "&enh_clinical=" + encodeURIComponent(enh_clinical) + "&sortdirection=" + sortdirection;
}

window.onload = function() {
    $("#selPagesize").on("change", changePageSize);

    // OEMR - Sort button click
    $("#sortButton").on("click", sortButton);
}
</script>
</head>
<body>
<form method="post" action="encounters.php?enh_clinical=<?php echo attr($enh_clinical_view); ?>&billing=<?php echo attr($billing_view); ?>&issue=<?php echo $issue . $getStringForPage; ?>" class="form-inline">    
<div class="container-fluid mt-3" id="encounters"> <!-- large outer DIV -->
<div class="row">
    <div class="col-sm-4">
        <span class='title'>
            <?php
            if ($issue) {
                echo xlt('Past Encounters for') . ' ';
                $tmp = sqlQuery("SELECT title FROM lists WHERE id = ?", array($issue));
                echo text($tmp['title']);
            } else {
                //There isn't documents for therapy group yet
                echo $attendant_type == 'pid' ? xlt('Visit History') : xlt('Past Therapy Group Encounters');
            }
            ?>
        </span>
        <?php
            // Setup the GET string to append when switching between billing and clinical views.
            if (!($auth_notes_a || $auth_notes || $auth_coding_a || $auth_coding || $auth_med || $auth_relaxed) || ($is_group && !$glog_view_write)) {
                echo "<body>\n<html>\n";
                echo "<p>(" . xlt('Encounters not authorized') . ")</p>\n";
                echo "</body>\n</html>\n";
                exit();
            }

            $pagestart = 0;
            if (isset($_GET['pagesize'])) {
                $pagesize = $_GET['pagesize'];
            } else {
                if (array_key_exists('encounter_page_size', $GLOBALS)) {
                    $pagesize = $GLOBALS['encounter_page_size'];
                } else {
                    $pagesize = 0;
                }
            }
            if (isset($_GET['pagestart'])) {
                $pagestart = $_GET['pagestart'];
            } else {
                $pagestart = 0;
            }

            $encpagestart = 0;
            $docpagestart = 0;

            if (isset($_GET['encpagestart'])) {
                $encpagestart = $_GET['encpagestart'];
            }

            if (isset($_GET['docpagestart'])) {
                $docpagestart = $_GET['docpagestart'];
            }

            if ($selected_packet != "All" || $create_packet === 1) {
                $pagesize = 0;
            }

            $maxPageSize = ($encpagestart + $docpagestart + $pagesize);


            $getStringForPage = "&pagesize=" . attr_url($pagesize) . "&pagestart=" . attr_url($pagestart);

        ?>

        <?php if ($billing_view || $enh_clinical_view === 1) { ?>
                <a href='encounters.php?billing=0&issue=<?php echo $issue . $getStringForPage; ?>' class="btn btn-small btn-info" onclick='top.restoreSession()' style='font-size: 11px'><?php echo xlt('To Clinical View'); ?></a>
        <?php } else { ?>
                <a href='encounters.php?billing=1&issue=<?php echo $issue . $getStringForPage; ?>' class="btn btn-small btn-info" onclick='top.restoreSession()' style='font-size: 11px'><?php echo xlt('To Billing View'); ?></a>
        <?php } ?>

        <?php if ($enh_clinical_view === 0) { ?>
            <a href='encounters.php?enh_clinical=1&billing=0&issue=<?php echo $issue . $getStringForPage; ?>' class="btn btn-small btn-info" onclick='top.restoreSession()' style='font-size: 11px'><?php echo xlt('To Enhanced View'); ?></a>
        <?php } ?>
        <?php if($enh_clinical_view === 1) { ?>
            
        <?php } ?>
    </div>
    <div class="col-sm" style="font: size 11px;">
                    <span class="float-right ml-3">
                        <?php echo xlt('Results per page'); ?>:
                        <select  class="form-control" id="selPagesize" billing="<?php echo attr($billing_view); ?>" issue="<?php echo attr($issue); ?>" pagestart="<?php echo attr($pagestart); ?>" enh_clinical="<?php echo attr($enh_clinical_view); ?>" encpagestart="<?php echo attr($encpagestart); ?>" docpagestart="<?php echo attr($docpagestart); ?>" <?php echo $selected_packet != "All" || $create_packet === 1 ? "disabled" : ""; ?>>
                            <?php
                            $pagesizes = array(5, 10, 15, 20, 25, 50, 0);
                            for ($idx = 0, $idxMax = count($pagesizes); $idx < $idxMax; $idx++) {
                                echo "<option value='" . attr($pagesizes[$idx]) . "'";
                                if ($pagesize == $pagesizes[$idx]) {
                                    echo " selected='true'>";
                                } else {
                                    echo ">";
                                }
                                if ($pagesizes[$idx] == 0) {
                                    echo xlt('ALL');
                                } else {
                                    echo text($pagesizes[$idx]);
                                }
                                echo "</option>";
                            }
                            ?>
                        </select>
                    </span>
                    <?php if($enh_clinical_view === 1) { ?>
                        <span class="float-right">
                                <div class="input-group mb-3">
                                    <input type="text" name="search_text" class="form-control" value="<?php echo $searchText; ?>" placeholder="Search">
                                    <div class="input-group-append">
                                        <button type="submit" class="btn btn-primary" formnovalidate><i class="fa fa-search" aria-hidden="true"  ></i></button>
                                    </div>
                                </div>
                            
                        </span>
                        <?php if($edit_packet !== 1 && $create_packet !== 1) { ?>
                        <span class="float-right mr-3">
                            <a href="#" id="lnkCreatePacket"   class="btn btn-primary" ><?php echo xlt('Create Packet'); ?></a>
                            <?php if(isset($_POST['packet_id']) && !empty($_POST['packet_id']) && $_POST['packet_id'] != "All") { ?>
                            <button type="button" id="exportPDFBtn" name="exportPDFBtn" value="1" class="btn btn-primary" <?php echo $edit_packet === 1 ? 'disabled' : ''; ?> >
                                <?php echo xlt('Export PDF'); ?>
                                <div class="spinner-border spinner-border-sm" role="status" style="display: none;">
                                  <span class="sr-only"><?php echo xlt('Loading...'); ?></span>
                                </div>
                            </button>
                            <?php } ?>  
                        </span>  
                        <?php } ?>  
                    <?php } ?>    
                    
                    <?php if($enh_clinical_view === 1) { ?>
                        <span class="float-right mr-3" style="font-size:13px !important;">
                            <?php echo xlt('Packets'); ?>:
                            <div class="input-group" style="display: inline-flex;">
                                <select class="form-control" class="font-size:11px !important;" name="packet_id" id="packets_dropdown" onchange="change_packet_selection()">
                                    <?php 
                                        while ($row = sqlFetchArray($packetsSql) ) {
                                            $selected = $selected_packet == $row['id'] ? 'selected' : '';
                                            echo '<option value="'. $row['id'].'" ' . $selected . '>'. $row['packet_title']. '</option>';
                                        }
                                    ?>
                                    <?php echo $selected_packet== "All" ?  '<option value="All" selected>All</option>' :  '<option value="All">All</option>' ; ?>
                                </select>
                                <div class="input-group-append">
                                    <?php if($selected_packet != "All") :?>
                                    <a class="btn btn-primary" id="editPacket" data-id="<?php echo $selected_packet; ?>"  ><i class="fa fa-edit" help aria-hidden="true"  ></i></a>
                                    <a id="delete-packet" data-id="<?php echo $selected_packet; ?>" class="btn btn-danger"  ><i class="fa fa-trash" help aria-hidden="true"  ></i></a>        
                                    <?php endif; ?>
                                </div> 
                            </div>
                        </span>  
                    <?php } ?>    
    </div>   
</div>

<?php if($enh_clinical_view === 1) { ?>
<div id="createPacketForm" class="jumbotron jumbotron-fluid px-3 py-4 mt-3" style="display:none;">
<div class="row">
    <div class="col-sm">    
        <span>
            <input type="text" class="form-control" name="packet_title" value="<?php echo $edit_title; ?>"  id="packet_title" placeholder="Packet Name">
            <input type="button" value="Save" id="btnSave" name="create_packet" class="btn btn-small btn-primary">
            <input type="button" value="Update"  id="btnUpdate" name="update_packet" class="btn btn-small btn-primary">
            <input type="button" value="Cancel" id="btnCancel"  class="btn btn-secondary" >
            <input type="hidden" id="action_mode" name="action_mode" value="" />
        </span>
    </div>
</div>
</div>
<?php } ?>

    <br />
    <div class="table-responsive">
        <div class="alert alert-info" role="alert">
            <span><i><span style="font-size: 20px;color: red;font-weight: bold;">*</span> <?php echo xlt("not signed"); ?><i/></span>
        </div>
        <table class="table table-hover jumbotron py-4 mt-3">
            <thead>
                <tr class='text'>
                <?php if ($enh_clinical_view === 1) : ?>
                    <th>
                        <?php if($edit_packet !== 1 && isset($_POST['packet_id']) && !empty($_POST['packet_id']) && $_POST['packet_id'] != "All") { ?>
                            <button type="button" class="btn btn-primary btn-sm" id="updateSeqBtn"><i class="fa fa-sort" aria-hidden="true"></i> <?php echo xlt('Update'); ?></button>
                        <?php } ?>
                    </th>
                <?php endif; ?>    
                    <th scope="col">
                        <?php echo xlt('Date'); ?>
                        
                        <?php if($enh_clinical_view === 1) { ?>
                        <?php if($sortdirection == "DESC") { ?>
                            <button type="button" class="btn btn-sm" id="sortButton" billing="<?php echo attr($billing_view); ?>" issue="<?php echo attr($issue); ?>" pagestart="<?php echo attr($pagestart); ?>" enh_clinical="<?php echo attr($enh_clinical_view); ?>" sortdirection="ASC" encpagestart="<?php echo attr($encpagestart); ?>" docpagestart="<?php echo attr($docpagestart); ?>"><i class="fa fa-long-arrow-down" aria-hidden="true"></i></button>
                        <?php } else { ?>
                            <button type="button" class="btn btn-sm" id="sortButton" billing="<?php echo attr($billing_view); ?>" issue="<?php echo attr($issue); ?>" pagestart="<?php echo attr($pagestart); ?>" enh_clinical="<?php echo attr($enh_clinical_view); ?>" sortdirection="DESC" encpagestart="<?php echo attr($encpagestart); ?>" docpagestart="<?php echo attr($docpagestart); ?>"><i class="fa fa-long-arrow-up" aria-hidden="true"></i></button>
                        <?php } ?>
                        <?php } ?>
                    </th>

                    <!-- OEMR - enc billing view --> 
                    <?php if ($enh_clinical_view === 1) { ?>
                        <th scope="col"><?php echo xlt('File Name / Form'); ?></th>
                        <th scope="col"><?php echo xlt('Provider'); ?></th>
                        <th scope="col"><?php echo xlt('Encounter Type'); ?></th>
                        <th scope="col"><?php echo xlt('Facility'); ?></th>
                        <th scope="col"><?php echo xlt('Case Info'); ?></th>
                    <?php } else { ?>
                    <?php if ($billing_view) { ?>
                        <th class='billing_note' scope="col"><?php echo xlt('Billing Note'); ?></th>
                    <?php } else { ?>
                        <?php if ($attendant_type == 'pid' && !$issue) { // only for patient encounter and if listing for multiple issues?>
                            <th scope="col"><?php echo xlt('Issue'); ?></th>
                        <?php } ?>
                            <th scope="col"><?php echo xlt('Reason/Form'); ?></th>
                        <?php if ($attendant_type == 'pid') { ?>
                            <th scope="col"><?php echo xlt('Provider');    ?></th>
                        <?php } else { ?>
                            <th scope="col"><?php echo xlt('Counselors');    ?></th>
                        <?php } ?>
                    <?php } ?>

                    <?php if ($billing_view) { ?>
                    <th scope="col"><?php echo xlt('Code'); ?></th>
                    <th class='text-right' scope="col"><?php echo xlt('Chg'); ?></th>
                    <th class='text-right' scope="col"><?php echo xlt('Paid'); ?></th>
                    <th class='text-right' scope="col"><?php echo xlt('Adj'); ?></th>
                    <th class='text-right' scope="col"><?php echo xlt('Bal'); ?></th>
                    <?php } elseif ($attendant_type == 'pid') { ?>
                    <th colspan='5' scope="col"><?php echo ($GLOBALS['phone_country_code'] == '1') ? xlt('Billing') : xlt('Coding'); ?></th>
                    <?php } ?>

                    <?php if ($attendant_type == 'pid' && !$GLOBALS['ippf_specific']) { ?>
                    <th scope="col">&nbsp;<?php echo ($GLOBALS['weight_loss_clinic']) ? xlt('Payment') : xlt('Insurance'); ?></th>
                    <?php } ?>

                    <?php if ($GLOBALS['enable_group_therapy'] && !$billing_view && $therapy_group == 0) { ?>
                        <th scope="col"><?php echo xlt('Encounter type'); ?></th>
                    <?php }?>

                    <?php if ($GLOBALS['enable_follow_up_encounters']) { ?>
                        <th scope="col"></th>
                    <?php }?>

                    <?php if ($GLOBALS['enable_group_therapy'] && !$billing_view && $therapy_group == 0) { ?>
                        <th scope="col"><?php echo xlt('Group name'); ?></th>
                    <?php }?>

                    <?php if ($GLOBALS['enable_follow_up_encounters']) { ?>
                        <th scope="col"></th>
                    <?php }?>

                    <!-- OEMR - End of billing view --> 
                    <?php }?>
                </tr>
            </thead>

            <?php
            $drow = false;

            $encpagesize = 0;
            $docpagesize = 0;

            // OEMR - Sort order
            $lsQuery = "";
            $lQuery = "";
            $l1Query = "";
            $lOrderBy = "";
            if($edit_packet !== 1 && $enh_clinical_view === 1 && $packetHasSeq === true && $selected_packet !=="All") {
                $lsQuery = "vvhpi.seq, ";
                $lQuery = " LEFT JOIN vh_visit_history_packet_items vvhpi ON vvhpi.`type` = 'document' AND vvhpi.item_id = d.id AND vvhpi.packet_id = " . $selected_packet . " ";
                $l1Query = " LEFT JOIN vh_visit_history_packet_items vvhpi ON vvhpi.`type` = 'encounter' AND vvhpi.item_id = fe.id AND vvhpi.packet_id = " . $selected_packet . " ";

                $lOrderBy = "ORDER BY vvhpi.seq ASC ";
            }

            if (!$billing_view) {
            // Query the documents for this patient.  If this list is issue-specific
            // then also limit the query to documents that are linked to the issue.
                    
                $queryarr = array($pid);
                $query = "SELECT " . $lsQuery . " d.id, d.type, d.url, d.name as document_name, coalesce(d.docdate,d.date) as docdate, d.list_id, d.encounter_id, c.name, c.id as cat_id ";
                $from = " FROM documents AS d JOIN categories_to_documents AS cd JOIN categories AS c " . $lQuery . " WHERE " .
                         " d.foreign_id = ? AND cd.document_id = d.id AND c.id = cd.category_id ";
                if ($issue) {
                    $from .= "AND d.list_id = ? ";
                    $queryarr[] = $issue;
                }
                
                // OEMR - Document search by filter
                if (!empty($filter_param ?? array()) && is_array($filter_param)) {
                    if (!empty($filter_param['date_start'] ?? "") && !empty($filter_param['date_end'] ?? "")) {
                       $from .= " AND date(d.docdate) BETWEEN '" . $filter_param['date_start'] . "' AND '" . $filter_param['date_end'] . "' ";
                    } else if (!empty($filter_param['date_start'] ?? "") && empty($filter_param['date_end'] ?? "")) {
                        $from .= " AND date(d.docdate) >= date('" . $filter_param['date_start'] . "') ";
                    } else if (empty($filter_param['date_start'] ?? "") && !empty($filter_param['date_end'] ?? "")) {
                        $from .= " AND date(d.docdate) <= date('" . $filter_param['date_end'] . "') ";
                    }
                }

                // OEMR - Document search by name
                if (isset($searchText) && !empty($searchText)) {

                    $w11 = array();
                    foreach (explode(" ", $searchText) as $sValue) {
                        if(!empty($sValue)) {
                            $w11[] = "CONCAT(get_doc_category_tree(c.id), '/', d.name) LIKE '%" . $sValue . "%'";
                        }
                    }

                    if(!empty($w11)) $w11 = " (" . implode(" OR ",  $w11) . ") "; 

                    //$query .= "AND ( d.name like '%" . $searchText . "%' ";
                    $from .= " AND (" . $w11 . ") ";
                }
                if($selected_packet != "All" && $edit_packet== 0)
                {
                    if(count($filter_document_ids)> 0)
                        $from .= "AND d.id in (" . implode(",", $filter_document_ids) . ") ";    
                    else    
                    $from .= "AND d.id in (0) ";
                }


                $doccountQuery = "SELECT COUNT(*) as c " . $from;
                $doccountRes = sqlStatement($doccountQuery, $queryarr);
                $doccount = sqlFetchArray($doccountRes);
                $docnumRes = $doccount['c'];

                $query = $query . $from;

                if(!empty($lOrderBy)) {
                    $query .= $lOrderBy;
                } else {
                    $query .= "ORDER BY d.docdate " . $sortdirection . ", d.id " . $sortdirection;
                }

                if ($pagesize > 0) {
                    $query .= " LIMIT " . escape_limit($docpagestart > 0 ? $docpagestart : $pagestart) . "," . escape_limit($pagesize);
                }

                $dres = sqlStatement($query, $queryarr);

                $drow = sqlFetchArray($dres);
            }

            // $count = 0;

            $sqlBindArray = array();
            if ($attendant_type == 'pid') {
                $from = "FROM form_encounter AS fe " .
                    "JOIN forms AS f ON f.pid = fe.pid AND f.encounter = fe.encounter AND " .
                    "f.formdir = 'newpatient' AND f.deleted = 0 ";
            } else {
                $from = "FROM form_groups_encounter AS fe " .
                    "JOIN forms AS f ON f.therapy_group_id = fe.group_id AND f.encounter = fe.encounter AND " .
                    "f.formdir = 'newGroupEncounter' AND f.deleted = 0 ";
            }

            if(!empty($l1Query)) {
                $from .= $l1Query;
            }
            
            if ($issue) {
                $from .= " JOIN issue_encounter AS ie ON ie.pid = ? AND " .
                "ie.list_id = ? AND ie.encounter = fe.encounter ";
                array_push($sqlBindArray, $pid, $issue);
            }
            if($selected_packet != "All" && $edit_packet== 0)
            {
                if(count($filter_encounter_ids)> 0)
                    $from .= " LEFT JOIN users AS u ON u.id = fe.provider_id WHERE fe.id in (" . implode(",", $filter_encounter_ids) . ") ";
                else
                    $from .= " LEFT JOIN users AS u ON u.id = fe.provider_id WHERE fe.id in (0) ";
            }
            elseif ($attendant_type == 'pid') {
                $from .= " LEFT JOIN users AS u ON u.id = fe.provider_id WHERE fe.pid = " . $pid;
                
            } else {
                $from .= " LEFT JOIN users AS u ON u.id = fe.provider_id WHERE fe.group_id = ? ";
                $sqlBindArray[] = $_SESSION['therapy_group'];
            }

            // OEMR - Document search by filter
            if (isset($filter_param) && !empty($filter_param) && is_array($filter_param)) {
                if (isset($filter_param['date_start']) && !empty($filter_param['date_start']) && isset($filter_param['date_end']) && !empty($filter_param['date_end'])) {
                    $from .= " AND date(fe.date) BETWEEN '" . $filter_param['date_start'] . "' AND '" . $filter_param['date_end'] . "' ";
                } else if (!empty($filter_param['date_start'] ?? "") && empty($filter_param['date_end'] ?? "")) {
                    $from .= " AND date(fe.date) >= date('" . $filter_param['date_start'] . "') ";
                } else if (empty($filter_param['date_start'] ?? "") && !empty($filter_param['date_end'] ?? "")) {
                    $from .= " AND date(fe.date) <= date('" . $filter_param['date_end'] . "') ";
                }
            }

                // OEMR - Document search by name
            if (isset($searchText) && !empty($searchText)) {
                
                $w1 = array();
                $w2 = array();
                $w3 = array();
                $w4 = array();
                $w5 = array();
                $w6 = array();

                foreach (explode(" ", $searchText) as $sValue) {
                    if(!empty($sValue)) {
                        $w1[] = "fc.case_description like '%" . $sValue . "%'";
                        $w2[] = "f.name like '%" . $sValue . "%'";
                        $w3[] = "opc.pc_catname like '%" . $sValue . "%'";
                        $w4[] = "CONCAT(u.lname, ', ', u.fname, ' ', u.mname) like '%" . $sValue . "%'";
                        $w5[] = "fe.reason like '%" . $sValue . "%'";
                        $w6[] = "form_name like '%" . $sValue . "%'";
                    }
                }

                if(!empty($w1)) $w1 = " (" . implode(" OR ",  $w1) . ") "; 
                if(!empty($w2)) $w2 = " (" . implode(" OR ",  $w2) . ") "; 
                if(!empty($w3)) $w3 = " (" . implode(" OR ",  $w3) . ") ";
                if(!empty($w4)) $w4 = " (" . implode(" OR ",  $w4) . ") "; 
                if(!empty($w5)) $w5 = " (" . implode(" OR ",  $w5) . ") "; 
                if(!empty($w6)) $w6 = " (" . implode(" OR ",  $w6) . ") ";


                $from .= " AND ( exists (SELECT fc.case_description from case_appointment_link cal join form_cases fc on fc.id = (IF(cal.pc_eid > 0, (SELECT ope.pc_case from openemr_postcalendar_events ope where ope.pc_eid = cal.pc_eid), cal.enc_case)) WHERE cal.encounter = fe.encounter and " . $w1 . ") OR exists (SELECT f.name from facility f where f.id = fe.facility_id and " . $w2 . ") OR exists (SELECT opc.pc_catname from openemr_postcalendar_categories opc where opc.pc_catid = fe.pc_catid and " . $w3 . " ) OR " . $w4 . " OR " . $w5 . " ";

                $encounter_search_sql = " OR exists (SELECT 1 from forms WHERE encounter = fe.encounter and deleted = 0 and " . $w6 . " ";
                if ($attendant_type == 'pid') {
                    $encounter_search_sql .= " and pid= " . $pid . " and therapy_group_id IS NULL) ";
                } else {
                    $encounter_search_sql .= " and therapy_group_id = " . $therapy_group . " and pid IS NULL) ";
                }

                if(!empty($encounter_search_sql)) {
                    $from .= $encounter_search_sql;
                }

                $from .= " )";
            }
                
            $query = "SELECT " . $lsQuery . " fe.*, f.user, u.fname, u.mname, u.lname " . $from . " ";

            if(!empty($lOrderBy)) {
                $query .= $lOrderBy;
            } else {
                $query .= " ORDER BY fe.date " . $sortdirection . ", fe.id " . $sortdirection;
            }

            $countQuery = "SELECT COUNT(*) as c " . $from;

            $countRes = sqlStatement($countQuery, $sqlBindArray);
             
            $count = sqlFetchArray($countRes);
            $numRes = $count['c'];

            if ($pagesize > 0) {
                $query .= " LIMIT " . escape_limit($encpagestart > 0 ? $encpagestart : $pagestart) . "," . escape_limit($pagesize);
            }
            $upper  = $pagestart + $pagesize;
            if (($upper > $numRes) || ($pagesize == 0)) {
                $upper = $numRes;
            }

            if (($pagesize > 0) && ($pagestart > 0)) {
                generatePageElement($pagestart - $pagesize, $pagesize, $billing_view, $issue, "&lArr;" . htmlspecialchars(xl("Prev"), ENT_NOQUOTES) . " ", $enh_clinical_view, "plink", ($encpagestart - ($_REQUEST['encpagesize'] ?? 0)), ($docpagestart - ($_REQUEST['docpagesize'] ?? 0)));
            }
            echo ($pagestart + 1) . "-" . $upper . " " . htmlspecialchars(xl('of'), ENT_NOQUOTES) . " " . $numRes;
            if (($pagesize > 0) && (($pagestart + $pagesize) < ($numRes + $docnumRes))) {
                generatePageElement($pagestart + $pagesize, $pagesize, $billing_view, $issue, " " . htmlspecialchars(xl("Next"), ENT_NOQUOTES) . "&rArr;", $enh_clinical_view, "nlink");
            }
            $res4 = sqlStatement($query, $sqlBindArray);

            $sequace_no = 10;
            while ($result4 = sqlFetchArray($res4)) {
                    // Visit history encounter item
                    $visit_history_encounter_item = array();

                    if($enh_clinical_view === 1) {
                        $visit_history_encounter_item = array(
                            "type" => "encounter",
                            "encounter_id" => 0,
                            "date" => "",
                            "reason" => "",
                            "form" => array(),
                            "provider" => "",
                            "encounter_type" => "",
                            "facility" => "",
                            "case_description" => "",
                            "case_payer" => "",
                            "pdf_data" => ""
                        );
                    }

                    // $href = "javascript:window.toencounter(" . $result4['encounter'] . ")";
                    $reason_string = "";
                    $auth_sensitivity = true;

                    $raw_encounter_date = '';

                    $raw_encounter_date = date("Y-m-d", strtotime($result4["date"]));
                    $encounter_date = date("D F jS", strtotime($result4["date"]));

                    // OEMR - Encounter seq
                    $raw_encounter_seq = 0;
                    $raw_encounter_seq = isset($result4["seq"]) ? $result4["seq"] : 0;

                    //fetch acl for given pc_catid
                    $postCalendarCategoryACO = AclMain::fetchPostCalendarCategoryACO($result4['pc_catid']);
                if ($postCalendarCategoryACO) {
                    $postCalendarCategoryACO = explode('|', $postCalendarCategoryACO);
                    $authPostCalendarCategory = AclMain::aclCheckCore($postCalendarCategoryACO[0], $postCalendarCategoryACO[1]);
                } else { // if no aco is set for category
                    $authPostCalendarCategory = true;
                }

                $eData = fetch_appt_signatures_data_byId($result4['encounter']);
                $signedText = !empty($eData) && isset($eData['is_lock']) && $eData['is_lock'] == "1" ? '' : ' <span style="font-size: 20px;color: red;font-weight: bold;">*</span>';

                if (!empty($result4["reason"])) {
                    $reason_string .= text($result4["reason"]) . $signedText . "<br />\n";
                }

                    // else
                    //   $reason_string = "(No access)";

                if ($result4['sensitivity']) {
                    $auth_sensitivity = AclMain::aclCheckCore('sensitivities', $result4['sensitivity']);
                    if (!$auth_sensitivity || !$authPostCalendarCategory) {
                        $reason_string = "(" . xlt("No access") . ")";
                    }
                }

                    // This generates document lines as appropriate for the date order.
                while ($drow && (($packetHasSeq === false && $raw_encounter_date && $drow['docdate'] > $raw_encounter_date) || ($packetHasSeq === true && isset($drow['seq']) && $drow['seq'] < $raw_encounter_seq) )) {

                    // Visit history document item
                    global $visit_history_document_item;

                    if (($pagesize > 0 && ($encpagestart + $docpagestart) < $maxPageSize) || $pagesize == 0) {
                        // OEMR - Show enh billing view
                        if($enh_clinical_view === 1) {
                            $is_from_packet = 0;
                            if(count($filter_document_ids)> 0 )
                            {
                                if(in_array($drow['id'], $filter_document_ids))
                                    $is_from_packet = 1;
                            }
                            
                            $temp_doc_sequace_no = isset($packets_items['doc_' . $drow['id']]['seq']) && !empty($packets_items['doc_' . $drow['id']]['seq']) ? $packets_items['doc_' . $drow['id']]['seq'] : $sequace_no;

                            showDocument1($drow, $edit_packet, $create_packet, $is_from_packet, $temp_doc_sequace_no);
                            $sequace_no = $sequace_no + 10;
                        } else {
                            showDocument($drow);
                        }

                        if ($pagesize > 0) {
                            $docpagesize++;
                            $docpagestart++;
                        }
                    }

                    // Assign visit history document items 
                    if(!empty($visit_history_document_item)) {
                        $visit_history_items[] = $visit_history_document_item;
                    }

                    $drow = sqlFetchArray($dres);
                }

                if ($pagesize > 0) {
                    if (($encpagestart + $docpagestart) >= $maxPageSize) {
                        continue;
                    } else {
                        $encpagesize++;
                        $encpagestart++;
                    }
                }

                    // Fetch all forms for this encounter, if the user is authorized to see
                    // this encounter's notes and this is the clinical view.
                    $encarr = array();
                    $encounter_rows = 1;
                if (
                    !$billing_view && $auth_sensitivity && $authPostCalendarCategory &&
                        ($auth_notes_a || ($auth_notes && $result4['user'] == $_SESSION['authUser']))
                ) {
                    $attendant_id = $attendant_type == 'pid' ? $pid : $therapy_group;
                    
                    // OEMR - Get encounter in ascending order 
                    if($enh_clinical_view === 1) {
                        $encarr = getFormByEncounter($attendant_id, $result4['encounter'], "formdir, user, form_name, form_id, deleted", "", "date asc");
                    } else {
                        $encarr = getFormByEncounter($attendant_id, $result4['encounter'], "formdir, user, form_name, form_id, deleted");
                    }

                    $encounter_rows = count($encarr);
                }
                
                    $rawdata = $result4['encounter'] . "~" . oeFormatShortDate($raw_encounter_date);

                    
                    // OEMR - Enh Clinical view
                    if($enh_clinical_view === 1)
                    {
                        echo "<tr class=''>\n";

                        echo "<td>\n";
                        if($edit_packet == 1 && in_array($result4['id'], $filter_encounter_ids))
                        {
                            if(in_array($result4['id'], $filter_encounter_ids))
                            {
                                echo "<input type='checkbox' checked class='packet_checkbox' style='display:none;' name='form_encounter_ids[]' value='" . $result4['id'] . "'>";
                            }
                        } else {
                            echo "<input type='checkbox' class='packet_checkbox' style='display:none;' name='form_encounter_ids[]' value='" . $result4['id'] . "'>";
                        }

                        // OEMR - Sel Item checkbox
                        if(isset($_POST['packet_id']) && !empty($_POST['packet_id']) && $_POST['packet_id'] != "All") {
                            if($edit_packet !== 1 && $create_packet !== 1) {
                                echo "<input type='checkbox' class='sel_checkbox' data-type='encounter' value='" . $result4['encounter'] . "'>";
                            }
                        }

                        // OEMR - Packet Item Seq
                        if($edit_packet !== 1 && isset($_POST['packet_id']) && !empty($_POST['packet_id']) && $_POST['packet_id'] != "All") {
                            $temp_sequace_no = isset($packets_items['enc_' . $result4['id']]['seq']) && !empty($packets_items['enc_' . $result4['id']]['seq']) ? $packets_items['enc_' . $result4['id']]['seq'] : $sequace_no;
                            echo " <input type='textbox' name='encounter_squ_no_" . $result4["id"] ."' id='encounter_squ_no_" . $result4["id"] ."' value='" . $temp_sequace_no . "'  class='vh_sequance_no form-control' style='max-width:50px;'>";
                        }
                        
                        echo "</td>\n";

                        // show encounter date
                        echo "<td class='encrow align-top' id='" . attr($rawdata) . "'   data-toggle='tooltip' data-placement='top' title='" . attr(xl('View encounter') . ' ' . $pid . "." . $result4['encounter']) . "'>" . text(oeFormatShortDate($raw_encounter_date)) . "</td>\n";

                        // Assign date
                        if (isset($get_items_only) && $get_items_only === true) {
                            $visit_history_encounter_item['date'] = text(oeFormatShortDate($raw_encounter_date));
                        }

                        $sequace_no = $sequace_no + 10;
                    }
                    else{
                        echo "<tr class='encrow text' id='" . attr($rawdata) . "'>\n";
                        
                        // show encounter date
                        echo "<td class='align-top'  data-toggle='tooltip' data-placement='top' title='" . attr(xl('View encounter') . ' ' . $pid . "." . $result4['encounter']) . "'>" . text(oeFormatShortDate($raw_encounter_date)) . "</td>\n";

                        // Assign date
                        if (isset($get_items_only) && $get_items_only === true) {
                            $visit_history_encounter_item['date'] = text(oeFormatShortDate($raw_encounter_date));
                        }
                    }


                // OEMR - Start of enh billing view
                if($enh_clinical_view === 1) {
                    // show encounter reason/title
                    echo "<td class='encrow text' id='" . attr($rawdata) . "'>" . $reason_string;

                    // Assign reason
                    if (isset($get_items_only) && $get_items_only === true) {
                        $visit_history_encounter_item['encounter_id'] = text($result4['encounter']);
                        $visit_history_encounter_item['reason'] = text(strip_tags(preg_replace("/\r\n|\r|\n/", "", $reason_string)));
                        //$visit_history_doc_param = array();
                    }

                    //Display the documents tagged to this encounter
                    getDocListByEncID($result4['encounter'], $raw_encounter_date, $pid, $raw_encounter_seq);

                    echo "<div class='pl-2'>";

                    // Now show a line for each encounter form, if the user is authorized to
                    // see this encounter's notes.

                    foreach ($encarr as $enc) {
                        if ($enc['formdir'] == 'newpatient' || $enc['formdir'] == 'newGroupEncounter') {

                            // OEMR - Select encounter checkbox
                            echo "<input type='checkbox' class='enci_". $result4['encounter'] ."' name='encounter_" . $enc['formdir'] . "_" . $enc['form_id'] . "' value='" . $result4['encounter'] . "' style='display:none;' >";

                            // // Assign form
                            // if (isset($get_items_only) && $get_items_only === true && count($encarr) > 1) {
                            //     $visit_history_doc_param[$enc['formdir'] . "_" . $enc['form_id']] = $result4['encounter'];
                            // }

                            continue;
                        }

                        // skip forms whose 'deleted' flag is set to 1 --JRM--
                        if ($enc['deleted'] == 1) {
                            continue;
                        }

                        // Skip forms that we are not authorized to see. --JRM--
                        // pardon the wonky logic
                        $formdir = $enc['formdir'];
                        if (
                            ($auth_notes_a) ||
                            ($auth_notes && $enc['user'] == $_SESSION['authUser']) ||
                            ($auth_relaxed && ($formdir == 'sports_fitness' || $formdir == 'podiatry'))
                        ) {
                        } else {
                            continue;
                        }

                        // Show the form name.  In addition, for the specific-issue case show
                        // the data collected by the form (this used to be a huge tooltip
                        // but we did away with that).
                        //
                        $formdir = $enc['formdir'];
                        if ($issue) {
                            echo text(xl_form_title($enc['form_name']));
                            echo "<br />";
                            echo "<div class='encreport pl-2'>";
                    // Use the form's report.php for display.  Forms with names starting with LBF
                    // are list-based forms sharing a single collection of code.
                            if (substr($formdir, 0, 3) == 'LBF') {
                                include_once($GLOBALS['incdir'] . "/forms/LBF/report.php");
                                lbf_report($pid, $result4['encounter'], 2, $enc['form_id'], $formdir);
                            } else {
                                include_once($GLOBALS['incdir'] . "/forms/$formdir/report.php");
                                call_user_func($formdir . "_report", $pid, $result4['encounter'], 2, $enc['form_id']);
                            }
                            echo "</div>";

                            // Assign encounter form
                            if (isset($get_items_only) && $get_items_only === true) {
                                $visit_history_encounter_item['form'][] = xl_form_title($enc['form_name']);
                                //$visit_history_doc_param[$enc['formdir'] . "_" . $enc['form_id']] = $result4['encounter'];
                            }

                        } else {
                            $formDiv = "<div ";
                            $formDir = attr($formdir);
                            $formEnc = attr($result4['encounter']);
                            $formId = attr($enc['form_id']);
                            $formPid = attr($pid);
                            if (hasFormPermission($enc['formdir'])) {
                                $formDiv .= "data-toggle='PopOverReport' data-placement='bottom' data-formpid='$formPid' data-formdir='$formDir' data-formenc='$formEnc' data-formid='$formId' ";
                            }
                            $formDiv .= "data-original-title='" . text(xl_form_title($enc['form_name'])) . " <i>" . xla("Click or change focus to dismiss") . "</i>'>";
                            $formDiv .= text(xl_form_title($enc['form_name']));
                            $formDiv .= "</div>";

                            // OEMR - Form check
                            $formDiv .= "<input type='checkbox' class='enci_". $result4['encounter'] ."' name='encounter_" . $enc['formdir'] . "_" . $enc['form_id'] . "' value='" . $result4['encounter'] . "' style='display:none;' >";

                            echo $formDiv;

                            // Assign encounter form
                            if (isset($get_items_only) && $get_items_only === true) {
                                $visit_history_encounter_item['form'][] = xl_form_title($enc['form_name']);
                                //$visit_history_doc_param[$enc['formdir'] . "_" . $enc['form_id']] = $result4['encounter'];
                            }
                        }
                    } // end encounter Forms loop

                    echo "</div>";
                    echo "</td>\n";

                    // // Assign doc param form
                    // if (isset($get_items_only) && $get_items_only === true) {
                    //     $visit_history_encounter_item['pdf_data'] = $visit_history_doc_param;
                    // }

                    if ($attendant_type == 'pid') {
                        // show user (Provider) for the encounter
                        $provname = 'Unknown';
                        if (!empty($result4['lname']) || !empty($result4['fname'])) {
                            $provname = $result4['lname'];
                            if (!empty($result4['fname']) || !empty($result4['mname'])) {
                                $provname .= ', ' . $result4['fname'] . ' ' . $result4['mname'];
                            }
                        }
                        echo "<td class='encrow' id='" . attr($rawdata) . "'>" . text($provname) . "</td>\n";

                        // Assign provider
                        if (isset($get_items_only) && $get_items_only === true) {
                            $visit_history_encounter_item['provider'] = text($provname);
                        }

                        // for therapy group view
                    } else {
                        $counselors = '';
                        foreach (explode(',', $result4['counselors']) as $userId) {
                            $counselors .= getUserNameById($userId) . ', ';
                        }
                        $counselors = rtrim($counselors, ", ");
                        echo "<td class='encrow' id='" . attr($rawdata) . "'>" . text($counselors) . "</td>\n";

                        // Assign provider
                        if (isset($get_items_only) && $get_items_only === true) {
                            $visit_history_encounter_item['provider'] = text($counselors);
                        }
                    }

                    // Calendar category
                    $calendar_category = (new AppointmentService())->getOneCalendarCategory($result4['pc_catid']);

                    echo "<td class='encrow text' id='" . attr($rawdata) . "'>" . $calendar_category[0]['pc_catname'] . ( !empty($result4['reason']) ? " - " . $result4['reason'] : "" ) . "</td>\n";

                    // Assign Encounter Type
                    if (isset($get_items_only) && $get_items_only === true) {
                        $visit_history_encounter_item['encounter_type'] = text($calendar_category[0]['pc_catname'] . ( !empty($result4['reason']) ? " - " . $result4['reason'] : "" ));
                    }

                    // Facility name
                    $facilityres = sqlQuery("select f.name as facility_name from facility as f where f.id = ?", array($result4['facility_id']));

                    echo "<td class='encrow text' id='" . attr($rawdata) . "'>" . $facilityres['facility_name'] . "</td>\n";

                    // Assign Facility
                    if (isset($get_items_only) && $get_items_only === true) {
                        $visit_history_encounter_item['facility'] = text($facilityres['facility_name']);
                    }

                    // Case description
                    $desc = " - ";
                    $case_link = sqlQuery('SELECT * FROM case_appointment_link WHERE encounter = ?', array($result4['encounter']));
                    if(!isset($case_link['pc_eid'])) $case_link['pc_eid'] = '';
                    if(!isset($case_link['enc_case'])) $case_link['enc_case'] = '';
                    if($case_link['pc_eid']) {
                        $sql = 'SELECT oe.pc_case, c.*, c.id AS case_id, users.*, ic.name as ins_name FROM ' .
                        'openemr_postcalendar_events AS oe LEFT JOIN form_cases AS c ' .
                        'ON (oe.pc_case = c.id) LEFT JOIN users ON (c.employer = users.id) left join insurance_data id on id.id = c.ins_data_id1 left join insurance_companies ic on ic.id = id.provider ' .
                        'WHERE oe.pc_eid = ?';
                        $case = sqlQuery($sql, array($case_link['pc_eid']));
                    } else if($case_link['enc_case']) {
                        $sql = 'SELECT c.*, c.id AS case_id, users.*, ic.name as ins_name FROM ' .
                        'form_cases AS c LEFT JOIN users ON (c.employer = users.id) left join insurance_data id on id.id = c.ins_data_id1 left join insurance_companies ic on ic.id = id.provider ' .
                        'WHERE c.id = ?';
                        $case = sqlQuery($sql, array($case_link['enc_case']));
                    }

                    if($case['case_description']) $desc = $case['case_description'];

                    echo "<td class='encrow text' id='" . attr($rawdata) . "'><span>Case Description: " . $desc . "</span><br/><span>Payer: " . $case['ins_name'] . "</span> </td>\n";

                    // Assign Facility
                    if (isset($get_items_only) && $get_items_only === true) {
                        $visit_history_encounter_item['case_description'] = text($desc);
                        $visit_history_encounter_item['case_payer'] = text($case['ins_name']);
                    }

                } else {

                if ($billing_view) {
                    // Show billing note that you can click on to edit.
                    $feid = $result4['id'] ? $result4['id'] : 0; // form_encounter id
                    echo "<td class='align-top' >";
                    echo "<div id='note_" . attr($feid) . "'>";
                    echo "<div id='" . attr($feid) . "'data-toggle='tooltip' data-placement='top' title='" . xla('Click to edit') . "' class='text billing_note_text border-0'>";
                    echo $result4['billing_note'] ? nl2br(text($result4['billing_note'])) : '<button type="button" class="btn btn-primary btn-add btn-sm">' . xlt('Add') . '</button>';
                    echo "</div>";
                    echo "</div>";
                    echo "</td>\n";

                    //  *************** end billing view *********************
                } else {
                    if ($attendant_type == 'pid' && !$issue) { // only for patient encounter and if listing for multiple issues
                        // show issues for this encounter
                        echo "<td>";
                        if ($auth_med && $auth_sensitivity && $authPostCalendarCategory) {
                            $ires = sqlStatement("SELECT lists.type, lists.title, lists.begdate " .
                                                "FROM issue_encounter, lists WHERE " .
                                                "issue_encounter.pid = ? AND " .
                                                "issue_encounter.encounter = ? AND " .
                                                "lists.id = issue_encounter.list_id " .
                                                "ORDER BY lists.type, lists.begdate", array($pid,$result4['encounter']));
                            for ($i = 0; $irow = sqlFetchArray($ires); ++$i) {
                                if ($i > 0) {
                                    echo "<br />";
                                }
                                $tcode = $irow['type'];
                                if ($ISSUE_TYPES[$tcode]) {
                                    $tcode = $ISSUE_TYPES[$tcode][2];
                                }
                                    echo text("$tcode: " . $irow['title']);

                                // Assign issue
                                if (isset($get_items_only) && $get_items_only === true) {
                                    $visit_history_encounter_item['issue'][] = text("$tcode: " . $irow['title']);
                                }
                            }
                        } else {
                            echo "(" . xlt('No access') . ")";
                        }
                        echo "</td>\n";
                    } // end if (!$issue)

                    // show encounter reason/title
                    echo "<td>" . $reason_string;

                    // Assign reason
                    if (isset($get_items_only) && $get_items_only === true) {
                        $visit_history_encounter_item['reason'] = text(strip_tags(preg_replace("/\r\n|\r|\n/", "", $reason_string)));
                    }

                    //Display the documents tagged to this encounter
                    getDocListByEncID($result4['encounter'], $raw_encounter_date, $pid, $raw_encounter_seq);

                    echo "<div class='pl-2'>";

                    // Now show a line for each encounter form, if the user is authorized to
                    // see this encounter's notes.

                    foreach ($encarr as $enc) {
                        if ($enc['formdir'] == 'newpatient' || $enc['formdir'] == 'newGroupEncounter') {
                            continue;
                        }

                        // skip forms whose 'deleted' flag is set to 1 --JRM--
                        if ($enc['deleted'] == 1) {
                            continue;
                        }

                        // Skip forms that we are not authorized to see. --JRM--
                        // pardon the wonky logic
                        $formdir = $enc['formdir'];
                        if (
                            ($auth_notes_a) ||
                            ($auth_notes && $enc['user'] == $_SESSION['authUser']) ||
                            ($auth_relaxed && ($formdir == 'sports_fitness' || $formdir == 'podiatry'))
                        ) {
                        } else {
                            continue;
                        }

                        // Show the form name.  In addition, for the specific-issue case show
                        // the data collected by the form (this used to be a huge tooltip
                        // but we did away with that).
                        //
                        $formdir = $enc['formdir'];
                        if ($issue) {
                            echo text(xl_form_title($enc['form_name']));
                            echo "<br />";
                            echo "<div class='encreport pl-2'>";
                    // Use the form's report.php for display.  Forms with names starting with LBF
                    // are list-based forms sharing a single collection of code.
                            if (substr($formdir, 0, 3) == 'LBF') {
                                include_once($GLOBALS['incdir'] . "/forms/LBF/report.php");
                                lbf_report($pid, $result4['encounter'], 2, $enc['form_id'], $formdir);
                            } else {
                                include_once($GLOBALS['incdir'] . "/forms/$formdir/report.php");
                                call_user_func($formdir . "_report", $pid, $result4['encounter'], 2, $enc['form_id']);
                            }
                            echo "</div>";

                            // Assign enbcounter form
                            if (isset($get_items_only) && $get_items_only === true) {
                                $visit_history_encounter_item['form'][] = xl_form_title($enc['form_name']);
                            }
                        } else {
                            $formDiv = "<div ";
                            $formDir = attr($formdir);
                            $formEnc = attr($result4['encounter']);
                            $formId = attr($enc['form_id']);
                            $formPid = attr($pid);
                            if (hasFormPermission($enc['formdir'])) {
                                $formDiv .= "data-toggle='PopOverReport' data-placement='top' data-formpid='$formPid' data-formdir='$formDir' data-formenc='$formEnc' data-formid='$formId' ";
                            }
                            $formDiv .= "data-original-title='" . text(xl_form_title($enc['form_name'])) . " <i>" . xla("Click or change focus to dismiss") . "</i>'>";
                            $formDiv .= text(xl_form_title($enc['form_name']));
                            $formDiv .= "</div>";
                            echo $formDiv;

                            // Assign enbcounter form
                            if (isset($get_items_only) && $get_items_only === true) {
                                $visit_history_encounter_item['form'][] = text(xl_form_title($enc['form_name']));
                            }
                        }
                    } // end encounter Forms loop

                    echo "</div>";
                    echo "</td>\n";

                    if ($attendant_type == 'pid') {
                        // show user (Provider) for the encounter
                        $provname = 'Unknown';
                        if (!empty($result4['lname']) || !empty($result4['fname'])) {
                            $provname = $result4['lname'];
                            if (!empty($result4['fname']) || !empty($result4['mname'])) {
                                $provname .= ', ' . $result4['fname'] . ' ' . $result4['mname'];
                            }
                        }
                        echo "<td>" . text($provname) . "</td>\n";

                        // Assign provider
                        if (isset($get_items_only) && $get_items_only === true) {
                            $visit_history_encounter_item['provider'] = text($provname);
                        }

                        // for therapy group view
                    } else {
                        $counselors = '';
                        foreach (explode(',', $result4['counselors']) as $userId) {
                            $counselors .= getUserNameById($userId) . ', ';
                        }
                        $counselors = rtrim($counselors, ", ");
                        echo "<td>" . text($counselors) . "</td>\n";

                        // Assign provider
                        if (isset($get_items_only) && $get_items_only === true) {
                            $visit_history_encounter_item['provider'] = text($counselors);
                        }
                    }
                } // end not billing view

                    //this is where we print out the text of the billing that occurred on this encounter
                    $thisauth = $auth_coding_a;
                if (!$thisauth && $auth_coding) {
                    if ($result4['user'] == $_SESSION['authUser']) {
                        $thisauth = $auth_coding;
                    }
                }
                    $coded = "";
                    $arid = 0;
                if ($thisauth && $auth_sensitivity && $authPostCalendarCategory) {
                    $binfo = array('', '', '', '', '');
                    if ($subresult2 = BillingUtilities::getBillingByEncounter($pid, $result4['encounter'], "code_type, code, modifier, code_text, fee")) {
                        // Get A/R info, if available, for this encounter.
                        $arinvoice = array();
                        $arlinkbeg = "";
                        $arlinkend = "";
                        if ($billing_view) {
                                $tmp = sqlQuery("SELECT id FROM form_encounter WHERE " .
                                            "pid = ? AND encounter = ?", array($pid, $result4['encounter']));
                                $arid = (int) $tmp['id'];
                            if ($arid) {
                                $arinvoice = InvoiceSummary::arGetInvoiceSummary($pid, $result4['encounter'], true);
                            }
                            if ($arid) {
                                $arlinkbeg = "<a onclick='editInvoice(event, " . attr_js($arid) . ")" . "'" . " class='text' style='color:#00cc00'>";
                                $arlinkend = "</a>";
                            }
                        }

                        // Throw in product sales.
                        $query = "SELECT s.drug_id, s.fee, d.name " .
                        "FROM drug_sales AS s " .
                        "LEFT JOIN drugs AS d ON d.drug_id = s.drug_id " .
                        "WHERE s.pid = ? AND s.encounter = ? " .
                        "ORDER BY s.sale_id";
                        $sres = sqlStatement($query, array($pid,$result4['encounter']));
                        while ($srow = sqlFetchArray($sres)) {
                            $subresult2[] = array('code_type' => 'PROD',
                            'code' => 'PROD:' . $srow['drug_id'], 'modifier' => '',
                            'code_text' => $srow['name'], 'fee' => $srow['fee']);
                        }

                        // This creates 5 columns of billing information:
                        // billing code, charges, payments, adjustments, balance.
                        foreach ($subresult2 as $iter2) {
                            // Next 2 lines were to skip diagnoses, but that seems unpopular.
                            // if ($iter2['code_type'] != 'COPAY' &&
                            //   !$code_types[$iter2['code_type']]['fee']) continue;
                            $title = $iter2['code_text'];
                            $codekey = $iter2['code'];
                            $codekeydisp = $iter2['code_type'] . " - " . $iter2['code'];
                            if ($iter2['code_type'] == 'COPAY') {
                                $codekey = 'CO-PAY';
                                $codekeydisp = xl('CO-PAY');
                            }
                            if ($iter2['modifier']) {
                                $codekey .= ':' . $iter2['modifier'];
                                $codekeydisp .= ':' . $iter2['modifier'];
                            }

                            $codekeydisp = $codekeydisp;

                            if ($binfo[0]) {
                                $binfo[0] .= '<br />';
                            }
                            if ($issue && !$billing_view) {
                            // Single issue clinical view: show code description after the code.
                                $binfo[0] .= $arlinkbeg . text($codekeydisp) . " " . text($title) . $arlinkend;
                            } else {
                            // Otherwise offer the description as a tooltip.
                                $binfo[0] .= "<span data-toggle='tooltip' data-placement='top' title='" . attr($title) . "'>" . $arlinkbeg . text($codekeydisp) . $arlinkend . "</span>";
                            }
                            if ($billing_view) {
                                if ($binfo[1]) {
                                    for ($i = 1; $i < 5; ++$i) {
                                        $binfo[$i] .= '<br />';
                                    }
                                }
                                if (empty($arinvoice[$codekey])) {
                                    // If no invoice, show the fee.
                                    if ($arlinkbeg) {
                                        $binfo[1] .= '&nbsp;';
                                    } else {
                                        $binfo[1] .= text(oeFormatMoney($iter2['fee']));
                                    }

                                    for ($i = 2; $i < 5; ++$i) {
                                        $binfo[$i] .= '&nbsp;';
                                    }
                                } else {
                                    $binfo[1] .= text(oeFormatMoney($arinvoice[$codekey]['chg'] + ($arinvoice[$codekey]['adj'] ?? null)));
                                    $binfo[2] .= text(oeFormatMoney($arinvoice[$codekey]['chg'] - $arinvoice[$codekey]['bal']));
                                    $binfo[3] .= text(oeFormatMoney($arinvoice[$codekey]['adj'] ?? null));
                                    $binfo[4] .= text(oeFormatMoney($arinvoice[$codekey]['bal']));
                                    unset($arinvoice[$codekey]);
                                }
                            }
                        } // end foreach

                        // Pick up any remaining unmatched invoice items from the accounting
                        // system.  Display them in red, as they should be unusual.
                        // Except copays aren't unusual but displaying them in red
                        // helps billers spot them quickly :)
                        if (!empty($arinvoice)) {
                            foreach ($arinvoice as $codekey => $val) {
                                if ($binfo[0]) {
                                    for ($i = 0; $i < 5; ++$i) {
                                        $binfo[$i] .= '<br />';
                                    }
                                }
                                for ($i = 0; $i < 5; ++$i) {
                                    $binfo[$i] .= "<p class='text-danger'>";
                                }
                                $binfo[0] .= text($codekey);
                                $binfo[1] .= text(oeFormatMoney($val['chg'] + $val['adj']));
                                $binfo[2] .= text(oeFormatMoney($val['chg'] - $val['bal']));
                                $binfo[3] .= text(oeFormatMoney($val['adj']));
                                $binfo[4] .= text(oeFormatMoney($val['bal']));
                                for ($i = 0; $i < 5; ++$i) {
                                    $binfo[$i] .= "</font>";
                                }
                            }
                        }
                    } // end if there is billing

                    echo "<td class='text'>" . $binfo[0] . "</td>\n";

                    // Assign billing
                    if (isset($get_items_only) && $get_items_only === true && !empty($binfo[0])) {
                        $visit_history_encounter_item['billing'][] = text(strip_tags($binfo[0]));
                    }

                    for ($i = 1; $i < 5; ++$i) {
                        echo "<td class='text-right'>" . $binfo[$i] . "</td>\n";

                        // Assign billing
                        if (isset($get_items_only) && $get_items_only === true && !empty($binfo[$i])) {
                            $visit_history_encounter_item['billing'][] = text(strip_tags($binfo[$i]));
                        }
                    }
                } /* end if authorized */ else {
                    echo "<td class='text align-top' colspan='5' rowspan='" . attr($encounter_rows) . "'>(" . xlt("No access") . ")</td>\n";
                }

                    // show insurance
                if ($attendant_type == 'pid' && !$GLOBALS['ippf_specific']) {
                    $insured = oeFormatShortDate($raw_encounter_date);
                    if ($auth_demo) {
                        $responsible = -1;
                        if ($arid) {
                                $responsible = InvoiceSummary::arResponsibleParty($pid, $result4['encounter']);
                        }
                        $subresult5 = getInsuranceDataByDate($pid, $raw_encounter_date, "primary");
                        if (!empty($subresult5["provider_name"])) {
                            $style = $responsible == 1 ? " style='color: var(--danger)'" : "";
                            $insured = "<span class='text'$style>&nbsp;" . xlt('Primary') . ": " .
                            text($subresult5["provider_name"]) . "</span><br />\n";
                        }
                        $subresult6 = getInsuranceDataByDate($pid, $raw_encounter_date, "secondary");
                        if (!empty($subresult6["provider_name"])) {
                            $style = $responsible == 2 ? " style='color: var(--danger)'" : "";
                            $insured .= "<span class='text'$style>&nbsp;" . xlt('Secondary') . ": " .
                            text($subresult6["provider_name"]) . "</span><br />\n";
                        }
                        $subresult7 = getInsuranceDataByDate($pid, $raw_encounter_date, "tertiary");
                        if ($subresult6 && !empty($subresult7["provider_name"])) {
                            $style = $responsible == 3 ? " style='color: var(--danger)'" : "";
                            $insured .= "<span class='text'$style>&nbsp;" . xlt('Tertiary') . ": " .
                            text($subresult7["provider_name"]) . "</span><br />\n";
                        }
                        if ($responsible == 0) {
                            $insured .= "<span class='text' style='color: var(--danger)'>&nbsp;" . xlt('Patient') .
                                        "</span><br />\n";
                        }

                        // Assign insurance
                        if (isset($get_items_only) && $get_items_only === true && !empty($binfo[$i])) {
                            $visit_history_encounter_item['insurance'][] = text(strip_tags($insured));
                        }
                    } else {
                        $insured = " (" . xlt("No access") . ")";
                    }

                    echo "<td>" . $insured . "</td>\n";
                }

                if ($GLOBALS['enable_group_therapy'] && !$billing_view && $therapy_group == 0) {
                    $encounter_type = sqlQuery("SELECT pc_catname, pc_cattype FROM openemr_postcalendar_categories where pc_catid = ?", array($result4['pc_catid']));
                    echo "<td>" . xlt($encounter_type['pc_catname']) . "</td>\n";
                }

                if ($GLOBALS['enable_follow_up_encounters']) {
                    $symbol = ( !empty($result4['parent_encounter_id']) ) ? '<span class="fa fa-fw fa-undo p-1"></span>' : null;

                    echo "<td> " . $symbol . " </td>\n";
                }

                if ($GLOBALS['enable_group_therapy'] && !$billing_view && $therapy_group == 0) {
                    $group_name = ($encounter_type['pc_cattype'] == 3 && is_numeric($result4['external_id'])) ? getGroup($result4['external_id'])['group_name']  : "";
                    echo "<td>" . text($group_name) . "</td>\n";
                }


                if ($GLOBALS['enable_follow_up_encounters']) {
                    $encounterId = ( !empty($result4['parent_encounter_id']) ) ? $result4['parent_encounter_id'] : $result4['id'];
                    echo "<td> <div style='z-index: 9999'>  <a href='#' class='btn btn-sm btn-primary' onclick='createFollowUpEncounter(event," . attr_js($encounterId) . ")'><span>" . xlt('Create follow-up encounter') . "</span></a> </div></td>\n";
                }

                // OEMR - End of billing view
                }

                echo "</tr>\n";

                // Assign visit history encounter items 
                if(!empty($visit_history_encounter_item)) {
                    $visit_history_items[] = $visit_history_encounter_item;
                }

            } // end while

            // Dump remaining document lines if count not exceeded.
            while ($drow /* && $count <= $N */) {
                // Visit history document item
                global $visit_history_document_item;

                if (($pagesize > 0 && ($encpagestart + $docpagestart) < $maxPageSize) || $pagesize == 0) { 
                    // OEMR - Show ext billing view
                    if($enh_clinical_view === 1) {
                        $is_from_packet = 0;
                        if(count($filter_document_ids)> 0 )
                        {
                            if(in_array($drow['id'], $filter_document_ids))
                                $is_from_packet = 1;
                        }

                        $temp_doc_sequace_no = isset($packets_items['doc_' . $drow['id']]['seq']) && !empty($packets_items['doc_' . $drow['id']]['seq']) ? $packets_items['doc_' . $drow['id']]['seq'] : $sequace_no;

                        showDocument1($drow, $edit_packet, $create_packet, $is_from_packet, $temp_doc_sequace_no);
                        $sequace_no = $sequace_no + 10;
                    } else {
                        showDocument($drow);
                    }

                    if ($pagesize > 0) {
                        $docpagesize++;
                        $docpagestart++;
                    }
                }

                // Assign visit history document items 
                if(!empty($visit_history_document_item)) {
                    $visit_history_items[] = $visit_history_document_item;
                }

                $drow = sqlFetchArray($dres);
            }
            ?>

        </table>
    </div>

</div> <!-- end 'encounters' large outer DIV -->
</form>
<script>
// jQuery stuff to make the page a little easier to use
function createFollowUpEncounter(event, encId){
    event.stopPropagation();
    var data = {
        encounterId: encId,
        mode: 'follow_up_encounter'
    };
    top.window.parent.newEncounter(data);
}

$(function () {
    $(".encrow").on("mouseover", function() { $(this).toggleClass("highlight"); });
    $(".encrow").on("mouseout", function() { $(this).toggleClass("highlight"); });
    $(".encrow").on("click", function() { toencounter(this.id); });

    $(".docrow").on("mouseover", function() { $(this).toggleClass("highlight"); });
    $(".docrow").on("mouseout", function() { $(this).toggleClass("highlight"); });
    $(".docrow").on("click", function() { todocument(this.id); });

    $(".billing_note_text").on("mouseover", function() { $(this).toggleClass("billing_note_text_highlight"); });
    $(".billing_note_text").on("mouseout", function() { $(this).toggleClass("billing_note_text_highlight"); });
    $(".billing_note_text").on("click", function(evt) {
        evt.stopPropagation();
        const url = 'edit_billnote.php?feid=' + encodeURIComponent(this.id);
        dlgopen(url, '', 'modal-sm', 350, false, '', {
            onClosed: 'reload',
        });
    });

    async function updateSeq() {
        document.querySelector("#action_mode").value = "save_sequence";

        const updatePacketSeq = await $.ajax({
            type: "POST",
            url: "encounters.php",
            data: $(".form-inline").serializeArray(),
            success: function (data) {

                document.querySelector("#action_mode").value = "";

                if(data != "") {
                    let dataObject = JSON.parse(data);

                    if(dataObject.hasOwnProperty('error')) {
                        alert(dataObject['error']);
                        return false;
                    }

                    if(dataObject.hasOwnProperty('message')) {
                        //alert(dataObject['message']);
                        //window.location.reload();
                    }
                }
            }
        });

        document.querySelector("#action_mode").value = "";
    }

    <?php if ($encpagestart || $docpagestart) { ?>
    const nlinkElement = document.getElementById('nlink');
    if (nlinkElement) {
        var nhref = nlinkElement.getAttribute('href');

        // Append new query string
        var updatedHref = nhref + (nhref.includes('?') ? '&' : '?') + "encpagestart=" + <?php echo js_escape($encpagestart) ?> + "&docpagestart=" + <?php echo js_escape($docpagestart) ?> + "&docpagesize=" + <?php echo js_escape($docpagesize) ?> + "&encpagesize=" + <?php echo js_escape($encpagesize) ?>;

        // Update the href attribute with the modified URL
        nlinkElement.setAttribute('href', updatedHref);
    }
    <?php } ?>

    // OEMR - Update Seq
    $("#updateSeqBtn").on("click", async function(evt) {
        await updateSeq();
        window.location.reload();
    });

    // OEMR - Export pdf
    $("#exportPDFBtn").on("click", async function(evt) {
        let formSerializeData = $(".form-inline").serializeArray();
        let paramData = {};

        $(this).find('.spinner-border').show();
        $(this).attr("disabled", "disabled");

        let sortedData = {}

        $('.sel_checkbox').each(function( itemIndex ) {
            if($(this).is(":checked") === true) {
                let enciValue = $(this).val();
                let seltype = $(this).data( "type" );
                let sortItem = $(this).parent().find('.vh_sequance_no');
                let sortValue =  itemIndex;

                if(sortItem.length > 0) {
                    sortValue = sortItem.val() != "" ? sortItem.val() : itemIndex;
                }

                let selItems = {}

                if(seltype == "encounter") {
                    $('.enci_' + enciValue).each(function( enciIndex ) {
                        var eleName = $(this).attr('name');
                        var hasPrefix = new RegExp('^' + "encounter_").test($(this).attr('name'));
                        if(hasPrefix) {
                            eleName = eleName.replace("encounter_", "");
                        }

                        selItems[eleName] = $(this).val();
                    });
                } else if(seltype == "doc") {
                    $('.doc_' + enciValue).each(function( docIndex ) {
                        var eleName = $(this).attr('name');
                        //selItems["documents"] = $(this).val();
                        selItems['doc_' + enciValue] = $(this).val();
                    });
                }

                sortedData[sortValue] = {
                    type : seltype,
                    items : selItems
                }
            }
        });

        $.each(sortedData, function(skey, svalue) {
            if(sortedData[skey]) {
                const ddItem = sortedData[skey];

                if(ddItem['type'] == "encounter") {
                    if(ddItem.hasOwnProperty('items')) {
                        $.each(ddItem['items'], function(sikey, sivalue) {
                            paramData[sikey] = sivalue;
                        });
                    }
                } else if(ddItem['type'] == "doc") {
                    if(ddItem.hasOwnProperty('items')) {
                        $.each(ddItem['items'], function(sikey, sivalue) {
                            // if(sikey == "documents") {
                            //     if(!paramData.hasOwnProperty('documents')) {
                            //         paramData['documents'] = [];
                            //     }

                            //     paramData['documents'].push(sivalue);
                            // }
                            paramData[sikey] = sivalue;
                        });
                    }
                }
            }
        });

        if(Object.keys(paramData).length === 0) {
            alert("Please select items");

            $(this).find('.spinner-border').hide();
            $(this).removeAttr("disabled");

            return false;
        }

        paramData = { ... {
            'pdf' : "1",
            'include_demographics' : "demographics"
        }, 
        ...paramData };

        let exportStatus = false;

        const exportPDFResponce = await $.ajax({
            type: "POST",
            url: "<?php echo $GLOBALS['webroot']; ?>/interface/patient_file/report/custom_report.php",
            data: paramData,
            xhrFields: {
                responseType: 'blob'
            },
            success: function (data) {
                if(data != "") {
                    var a = document.createElement('a');
                    var url = window.URL.createObjectURL(data);
                    a.href = url;
                    a.download = 'visit_history.pdf';
                    document.body.append(a);
                    a.click();
                    a.remove();
                    window.URL.revokeObjectURL(url);
                    //window.location.reload();

                    exportStatus = true;
                } else {
                    alert("Something wrong");
                }
            }
        });
        
        if(exportStatus === true) {
            await updateSeq();
            window.location.reload();
        }

        $(this).find('.spinner-border').hide();
        $(this).removeAttr("disabled");
    });

    // OEMR - sel checkbox change
    $(".sel_checkbox").change(function() {
        const encValue = $(this).val();
        const atype = $(this).data( "type" );

        if(atype == "encounter") {
            if($(this).is(":checked") === true) {
                $(".enci_" + encValue).attr('checked','checked');
                $("#encounter_squ_no_" + encValue).removeAttr("disabled"); 
            } else {
                $(".enci_" + encValue).removeAttr('checked');
                $("#encounter_squ_no_" + encValue).attr('disabled','disabled');
            }
        } else if(atype == "doc") {
            if($(this).is(":checked") === true) {
                $(".doc_" + encValue).attr('checked','checked'); 
                $("#document_squ_no_" + encValue).removeAttr("disabled"); 
            } else {
                $(".doc_" + encValue).removeAttr('checked');
                $("#document_squ_no_" + encValue).attr('disabled','disabled');
            }
        }
    });
});

$(function () {
    $('[data-toggle="tooltip"]').tooltip();
    // Report tooltip where popover will stay open for 30 seconds
    // or mouse leaves popover or user clicks anywhere in popover.
    $('body').popover({
        sanitize: false,
        title: function () {
            return this.innerHTML;
        },
        content: function () {
            let el = this;
            if (typeof el.dataset == 'undefined') {
                return xl("Report Unavailable");
            }
            let url = "encounters_ajax.php?ptid=" + encodeURIComponent(el.dataset.formpid) +
                "&encid=" + encodeURIComponent(el.dataset.formenc) +
                "&formname=" + encodeURIComponent(el.dataset.formdir) +
                "&formid=" + encodeURIComponent(el.dataset.formid) +
                "&csrf_token_form=" + <?php echo js_url(CsrfUtils::collectCsrfToken()); ?>;
            let fetchedReport;
            $.ajax({
                url: url,
                method: "GET",
                async: false,
                beforeSend: top.restoreSession,
                success: function (report) {
                    fetchedReport = report;
                }
            });
            return fetchedReport;
        },
        selector: '[data-toggle="PopOverReport"]',
        boundary: "window",
        animation: false,
        placement: "bottom",
        trigger: "hover",
        html: true,
        delay: {"show": 300, "hide": 30000},
        template: '<div class="container"><div class="popover" style="max-width:fit-content;max-height:fit-content;" role="tooltip"><div class="arrow"></div><h3 class="popover-header bg-dark text-light"></h3><div class="popover-body bg-light text-dark"></div></div></div>'
    });
    // Report tooltip where popover will stay open for 30 seconds
    // or mouse leaves popover or user clicks anywhere in popover.
    // this will allow user to enter popover report view and scroll if report
    // height is overflowed. Poporver will eiter close when mouse leaves view
    // or user clicks anywhere in view.
    $('[data-toggle="PopOverReport"]').on('show.bs.popover', function () {
        let elements = $('[aria-describedby^="popover"]');
        let thisOne = this.dataset.formid;
        let thisTitle = this.dataset.formdir;
        for (i = 0; i < elements.length; ++i) {
            if (thisOne === elements[i].dataset.formid && thisTitle === elements[i].dataset.formdir) {
                continue;
            }
            $(elements[i]).popover('hide');
        }
    });

    $('[data-toggle="PopOverReport"]').on('shown.bs.popover', function () {

        // set event listeners
        $('.popover').click(function (e) {
            $('[data-toggle="PopOverReport"]').popover('hide');
        }).mouseleave(function (e) {
            timeoutObj = setTimeout(function () {
                $('[data-toggle="PopOverReport"]').popover('hide');
            }, 100);
        });
    });

    $('[data-toggle="PopOverReport"]').on('mouseleave', function () {
        var _this = this;
        setTimeout(function () {
            if (!$('.popover:hover').length) {
              $(_this).popover('hide');
            }
        }, 100);
    });

    /* OEMR - Document preview tooltip */
    $('#encounters').popover({
        sanitize: false,
        title: function () {
            return this.innerHTML;
        },
        content: function () {
            let el = this;
            if (typeof el.dataset == 'undefined') {
                return xl("Report Unavailable");
            }

            return '<iframe style="border: 0px" type="image/jpeg" src="<?php echo $GLOBALS['webroot']; ?>/controller.php?document&amp;retrieve&amp;patient_id=' + encodeURIComponent(el.dataset.documentpid) + '&amp;document_id='  + encodeURIComponent(el.dataset.documentid) +  '&amp;as_file=false" onload="iframeLoaded(this)"></iframe>';
        },
        selector: '[data-toggle="PopOverDocument"]',
        boundary: "window",
        animation: false,
        placement: "auto",
        trigger: "hover focus",
        html: true,
        delay: {"show": 300, "hide": 30000},
        template: '<div class="container"><div class="popover" style="max-width:fit-content;max-height:fit-content;" role="tooltip"><div class="arrow"></div><h3 class="popover-header bg-dark text-light"></h3><div class="popover-body bg-light text-dark"></div></div></div>'
    });

    // Report tooltip where popover will stay open for 30 seconds
    // or mouse leaves popover or user clicks anywhere in popover.
    // this will allow user to enter popover report view and scroll if report
    // height is overflowed. Poporver will eiter close when mouse leaves view
    // or user clicks anywhere in view.
    $('[data-toggle="PopOverDocument"]').on('show.bs.popover', function () {
        let elements = $('[aria-describedby^="popover"]');
        let thisOne = this.dataset.documentid;
        for (i = 0; i < elements.length; ++i) {
            if (thisOne === elements[i].dataset.documentid) {
                continue;
            }
            $(elements[i]).popover('hide');
        }
    });

    $('[data-toggle="PopOverDocument"]').on('shown.bs.popover', function () {

        // set event listeners
        $('.popover').click(function (e) {
            $('[data-toggle="PopOverDocument"]').popover('hide');
        }).mouseleave(function (e) {
            timeoutObj = setTimeout(function () {
                $('[data-toggle="PopOverDocument"]').popover('hide');
            }, 100);
        });
    });

    $('[data-toggle="PopOverDocument"]').on('mouseleave', function () {
        var _this = this;
        setTimeout(function () {
            if (!$('.popover:hover').length) {
              $(_this).popover('hide');
            }
        }, 100);
    });

    /* End */
});

// OEMR - Iframe image load
function iframeLoaded(ele) {
    if(ele) {
        let imgE = ele.contentWindow.document.body.querySelector('img');
        let iframeHeight = "85vh";
        let iframeWidth = "660px";
        let iframeMaxWidth = "660px";
        let iframeMaxHeight = "800px";

        if(imgE) {
            iframeHeight = imgE.height + "px";
            iframeWidth = imgE.width + "px";
            iframeMaxWidth = "100%";
        }

        ele.style.height = iframeHeight;
        ele.style.maxHeight = iframeMaxHeight;

        ele.style.width  = iframeWidth;
        ele.style.maxWidth  = iframeMaxWidth;
    } 
}

// OEMR - Packet selection changes
function change_packet_selection() {
    $(".form-inline").submit();
}

function show_createButtonOptions() {
    $("#createPacketForm").toggle();
    $("#lnkCreatePacket").toggle();
    $(".packet_checkbox").toggle();
    $("#btnUpdate").hide();
    $("#btnSave").show();
}

$(document).on('click', '#lnkCreatePacket', function(e) {
    document.querySelector("#packets_dropdown").value = "All";
    document.querySelector("#action_mode").value = "create_packet";
    document.querySelector('form.form-inline').submit();
});

$(document).on('click', '#editPacket', function(e) {
    document.querySelector("#action_mode").value = "edit_packet";
    document.querySelector('form.form-inline').submit();
		
});

$(document).on('click', '#btnSave', async function(e){
    const formTitleValue = $('#packet_title').val();

    if(formTitleValue == "") {
        alert("Please enter packet name");
        return;
    }

    document.querySelector("#action_mode").value = "save_packet";

    const createPacket = await $.ajax({
        type: "POST",
        url: "encounters.php",
        data: $(".form-inline").serializeArray(),
        success: function (data) {

            document.querySelector("#action_mode").value = "";

            if(data != "") {
                let dataObject = JSON.parse(data);

                if(dataObject.hasOwnProperty('error')) {
                    alert(dataObject['error']);
                    return false;
                }

                if(dataObject.hasOwnProperty('message')) {
                    alert(dataObject['message']);

                    $("#action_mode").val("");
                    $("#packets_dropdown").append($("<option></option>").attr("value", dataObject['packet_id']).text(""));
                    $("#packets_dropdown").val(dataObject['packet_id']);
                    $('form.form-inline').submit();
                }
            }
        }
    });
});

$(document).on('click', '#btnUpdate', async function(e){
    const formTitleValue = $('#packet_title').val();

    if(formTitleValue == "") {
        alert("Please enter packet name");
        return;
    }

    document.querySelector("#action_mode").value = "update_packet";

    const updatePacket = await $.ajax({
        type: "POST",
        url: "encounters.php",
        data: $(".form-inline").serializeArray(),
        success: function (data) {

            document.querySelector("#action_mode").value = "";

            if(data != "") {
                let dataObject = JSON.parse(data);

                if(dataObject.hasOwnProperty('error')) {
                    alert(dataObject['error']);
                    return false;
                }

                if(dataObject.hasOwnProperty('message')) {
                    alert(dataObject['message']);
                    //window.location.href = window.location.href;

                    $("#action_mode").val("");
                    $("#packets_dropdown").val(dataObject['packet_id']);
                    $('form.form-inline').submit();
                }
            }
        }
    });
});

$(document).on('click', '#delete-packet', async function(e) {
    if (confirm('Are you sure you want to permanently delete this packet?')) {
        
        document.querySelector("#action_mode").value = "delete_packet";

        const deletePacket = await $.ajax({
            type: "POST",
            url: "encounters.php",
            data: $(".form-inline").serializeArray(),
            success: function (data) {

                document.querySelector("#action_mode").value = "";

                if(data != "") {
                    let dataObject = JSON.parse(data);

                    if(dataObject.hasOwnProperty('error')) {
                        alert(dataObject['error']);
                        return false;
                    }

                    if(dataObject.hasOwnProperty('message')) {
                        alert(dataObject['message']);
                        window.location.href = window.location.href;
                    }
                }
            }
        });

        document.querySelector("#action_mode").value = "";
    }
});

$(document).on('click', '#btnCancel', function(e){
    change_packet_selection();
});

 
<?php if($edit_packet == 1): ?>
    $(document).ready( function() { 
        show_createButtonOptions();
        $("#btnSave").hide();
        $("#btnUpdate").show();
    }); 
<?php endif;?>

<?php if($create_packet == 1): ?>
    $(document).ready( function() { 
        show_createButtonOptions();
        $("#btnSave").show();
        $("#btnUpdate").hide();
    }); 
<?php endif;?>


<?php if(isset($_REQUEST['packet_id']) && !empty($_REQUEST['packet_id']) && $_REQUEST['packet_id'] != "All") { ?>
    $(document).ready( function() {
        $('.sel_checkbox').each(function( itemIndex ) {
            $(this).trigger('click');
        });
    });
<?php } ?>
// OEMR - End

</script>
</body>
</html>
