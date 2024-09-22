<?php

require_once(dirname(__FILE__, 7) . "/interface/globals.php");
require_once "$srcdir/patient.inc";
require_once "$srcdir/options.inc.php";
require_once "$srcdir/patient_tracker.inc.php";
require_once "$srcdir/user.inc";
require_once "$srcdir/MedEx/API.php";
require_once($GLOBALS['srcdir'].'/OemrAD/oemrad.globals.php');

use OpenEMR\Common\Csrf\CsrfUtils;
use OpenEMR\Core\Header;
use OpenEMR\OemrAd\Utility;
use Vitalhealthcare\OpenEMR\Modules\Generic\Util\PropioUtils;
use Vitalhealthcare\OpenEMR\Modules\Generic\Util\ZoomUtils;
use Vitalhealthcare\OpenEMR\Modules\Generic\Controller\Route\PropioController;
use OpenEMR\OemrAd\ZoomIntegration;


if (!empty($_POST)) {
    if (!CsrfUtils::verifyCsrfToken($_POST["csrf_token_form"])) {
        CsrfUtils::csrfNotVerified();
    }
}

//Save Filter Value
Utility::saveFilterValueOfTelmed($_SESSION['authUserID'], $_POST);
setPreservedValuesForTelmed();

function setPreservedValuesForTelmed() {
    $fieldList = array('form_provider');
    $flItems = Utility::getSectionValues($_SESSION['authUserID']);

    foreach ($fieldList as $key => $item) {
        if(isset($flItems['telmed_'.$item]) && !empty($flItems['telmed_'.$item])) {
            if(!$_REQUEST[$item]) {
                $_REQUEST[$item] = $flItems['telmed_'.$item];
            }
        }
    }
}

$pageno = (isset($_REQUEST['page_no']) && !empty($_REQUEST['page_no'])) ? $_REQUEST['page_no'] : 1;
$hide_cancellations_appt = (isset($_REQUEST['hide_cancellations_appt']) && $_REQUEST['hide_cancellations_appt'] == "1") ? 1 : 0;
$limit = 50;
$from_date = date('Y-m-d');
$to_date = date('Y-m-d');
$from_time = '00:00';
$to_time = '23:59';

function pageDetails($results, $limit) {
    $total_records = $results['count'];
    $total_pages = ceil($total_records / $limit);

    return array(
        'total_records' => $total_records,
        'limit' => $limit,
        'total_pages' => $total_pages,
    );
}

function generatePagination($page_details, $pageno) {
    $pageList = array();
    $max = 5;
    if($pageno < $max)
        $sp = 1;
    elseif($pageno >= ($page_details['total_pages'] - floor($max / 2)) )
        $sp = $page_details['total_pages'] - $max + 1;
    elseif($pageno >= $max)
        $sp = $pageno  - floor($max/2);

    for($i = $sp; $i <= ($sp + $max -1);$i++) {
        if($i > $page_details['total_pages']) {
            continue;
        } else {
            $pageList[] = $i;
        }
    }

    if($page_details['total_pages'] > 1) {
    ?>
    <div class="paginationContainer">
    <ul class="pagination">
        <li class="page-item <?php if($pageno <= 1){ echo 'disabled'; } ?>">
            <a class="page-link" onclick="changePage('1')" /><?php echo xlt('First'); ?></a>
        <li class="page-item <?php if($pageno <= 1){ echo 'disabled'; } ?>">
            <a class="page-link" onclick="changePage('<?php echo ($pageno <= 1) ? '' : ($pageno - 1); ?>')" ><?php echo xlt('Prev'); ?></a>
        </li>
        <?php 
            foreach ($pageList as $page) {
                ?>
                <li class="page-item <?php if($page == $pageno){ echo 'active'; } ?>">
                    <a class="page-link" onclick="changePage('<?php echo $page; ?>')"><?php echo xlt($page); ?></a>
                </li>
                <?php
            }
        ?>
        <li class="page-item <?php if($pageno >= $page_details['total_pages']){ echo 'disabled'; } ?>">
            <a class="page-link" onclick="changePage('<?php echo ($pageno >= $page_details['total_pages']) ? '' : ($pageno + 1); ?>')" ><?php echo xlt('Next'); ?></a>
        </li>
        <li class="page-item <?php if($pageno >= $page_details['total_pages']){ echo 'disabled'; } ?>">
            <a class="page-link" onclick="changePage('<?php echo $page_details['total_pages'] ?>')" ><?php echo xlt('Last'); ?></a>
        </li>
    </ul>
    </div>
    <?php
    }
}

//Get Esign Class
function getESignClass($eid = null) {
    //global $appointments_signatures_data;
    $esign_class = 'not_locked';

    if(!empty($eid)) {
        $eData = fetch_appt_signatures_data_byId($eid);

        if($eData !== false && isset($eData['is_lock']) && $eData['is_lock'] == '1') {
            $esign_class = 'locked';
        }
        // if(isset($appointments_signatures_data['TID_'.$eid]) && $appointments_signatures_data['TID_'.$eid]['is_lock'] == '1') {
        //     $esign_class = 'locked';
        // }
    }    

    return $esign_class;
}

function fetch_appt_signatures_data_byId($eid) {
    if(!empty($eid)) {
        $eSql = "SELECT FE.encounter, E.id, E.tid, E.table, E.uid, U.fname, U.lname, E.datetime, E.is_lock, E.amendment, E.hash, E.signature_hash 
                FROM form_encounter FE 
                LEFT JOIN esign_signatures E ON (case when E.`table` ='form_encounter' then FE.encounter = E.tid else  FE.id = E.tid END)
                LEFT JOIN users U ON E.uid = U.id 
                WHERE FE.encounter = ? 
                ORDER BY E.datetime ASC";
        $result = sqlQuery($eSql, array($eid));
        return $result;
    }
    return false;
}

function zoomGetInMeetingUser($meeting_id = "") {
    $sql = "select m1.user_name, m1.meeting_in_time, m1.meeting_out_time, IF(m1.meeting_in_time is not null and m1.meeting_out_time is null,1,0) as in_meeting from (select distinct vzwe.user_name, (select vzwe1.event_ts from vh_zoom_webhook_event vzwe1 where vzwe1.user_name = vzwe.user_name and vzwe.meeting_id = vzwe1.meeting_id and vzwe1.event in ('meeting.participant_jbh_waiting', 'meeting.participant_joined') order by vzwe1.event_ts desc limit 1) as meeting_in_time, (select vzwe2.event_ts from vh_zoom_webhook_event vzwe2 where vzwe2.user_name = vzwe.user_name and vzwe.meeting_id = vzwe2.meeting_id and vzwe2.event in ('meeting.participant_jbh_waiting_left', 'meeting.participant_left') and meeting_in_time <=  vzwe2.event_ts order by vzwe2.event_ts desc limit 1) as meeting_out_time from vh_zoom_webhook_event vzwe where vzwe.meeting_id = ? and vzwe.event in ('meeting.participant_jbh_waiting', 'meeting.participant_joined')) as m1";
        
    $zulist = array();
    $za_result = sqlStatementNoLog($sql, array($meeting_id));
    while ($frow = sqlFetchArray($za_result)) {
        $zulist[] = $frow;
    }

    return $zulist;
}

if(!isset($_REQUEST['form_provider'])) $_REQUEST['form_provider'] = array("all");

if (!is_null($_REQUEST['form_from_date'] ?? null)) {
    $from_date = DateToYYYYMMDD($_REQUEST['form_from_date']);
}

if (!is_null($_REQUEST['form_to_date'] ?? null)) {
    $to_date = DateToYYYYMMDD($_REQUEST['form_to_date']);
}

if (!is_null($_REQUEST['form_from_time'] ?? null)) {
    $from_time = date('H:i', strtotime($_REQUEST['form_from_time']));
}

if (!is_null($_REQUEST['form_to_time'] ?? null)) {
    $to_time = date('H:i', strtotime($_REQUEST['form_to_time']));
}

$from_date_time = "";
$to_date_time = "";
if(!empty($from_date) && !empty($from_time)) $from_date_time = $from_date ." ". $from_time;
if(!empty($to_date) && !empty($to_time)) $to_date_time = $to_date ." ". $to_time;

$lres = sqlStatement("SELECT option_id, title FROM list_options WHERE list_id = ? AND activity=1", array('apptstat'));
while ($lrow = sqlFetchArray($lres)) {
    // if exists, remove the legend character
    if ($lrow['title'][1] == ' ') {
        $splitTitle = explode(' ', $lrow['title']);
        array_shift($splitTitle);
        $title = implode(' ', $splitTitle);
    } else {
        $title = $lrow['title'];
    }
    $statuses_list[$lrow['option_id']] = $title;
}

if (!($_REQUEST['flb_table'] ?? null)) {
    ?>
<html>
<head>
    <meta name="author" content="OpenEMR: MedExBank" />
    <?php Header::setupHeader(['datetime-picker', 'opener', 'jquery', 'jquery-ui', 'datatables', 'datatables-colreorder', 'datatables-bs', 'oemr_ad']); ?>
    <title><?php echo xlt('Telemedicine Waiting Room'); ?></title>
    <script>
        <?php require_once "$srcdir/restoreSession.php"; ?>
    </script>

    <?php if ($_SESSION['language_direction'] == "rtl") { ?>
      <link rel="stylesheet" href="<?php echo $GLOBALS['themes_static_relative']; ?>/misc/rtl_bootstrap_navbar.css?v=<?php echo $GLOBALS['v_js_includes']; ?>" />
    <?php } else { ?>
      <link rel="stylesheet" href="<?php echo $GLOBALS['themes_static_relative']; ?>/misc/bootstrap_navbar.css?v=<?php echo $GLOBALS['v_js_includes']; ?>" />
    <?php } ?>

    <script src="<?php echo $GLOBALS['web_root']; ?>/interface/main/messages/js/reminder_appts.js?v=<?php echo $v_js_includes; ?>"></script>

    <!-- OEMR - Change -->
    <script type="text/javascript">
        function goToEncounter(pid, pubpid, pname, enc, dobstr) {
            top.restoreSession();
            loadpatient(pid,enc);
        }

        // used to display the patient demographic and encounter screens
        function loadpatient(newpid, enc) {
            if ($('#setting_new_window').val() === 'checked') {
                document.fnew.patientID.value = newpid;
                document.fnew.encounterID.value = enc;
                document.fnew.submit();
            }
            else {
                if (enc > 0) {
                    top.RTop.location = "<?php echo $GLOBALS['webroot']; ?>/interface/patient_file/summary/demographics.php?set_pid=" + newpid + "&set_encounterid=" + enc;
                }
                else {
                    top.RTop.location = "<?php echo $GLOBALS['webroot']; ?>/interface/patient_file/summary/demographics.php?set_pid=" + newpid;
                }
            }
        }
    </script>
    <!-- End -->

    <style type="text/css">
        td.details-control::after {
            font-family: "Font Awesome 6 Free";
            content: "\f078";
            display: inline-block;
            vertical-align: middle;
            font-weight: 900;
            float: right;
            padding-right: 3px;
            vertical-align: middle;
        }
        tr.details td.details-control::after {
            font-family: "Font Awesome 6 Free";
            content: "\f077";
            display: inline-block;
            vertical-align: middle;
            font-weight: 900;
            float: right;
            padding-right: 3px;
            vertical-align: middle;
        }
        td .tdDetailsContainer {
            display: grid;
            grid-template-columns: 1fr auto;
        }
        .rowdetail {
            display: none;
        }
        .rowdetail.show {
            display: table-row !important;
        }
    </style>
</head>
<body>
    <div class="container mt-3">
        <div id="flb_selectors" style="display:<?php echo attr($setting_selectors); ?>;">
            <h2 class="text-center"><?php echo xlt('Telemedicine Waiting Room'); ?></h2>
            <div class="jumbotron p-4">
                <div class="showRFlow text-center" id="show_flows" name="kiosk_hide">
                        <div name="div_response" id="div_response" class="nodisplay"></div>
                        <form name="flb" id="flb" method="post">
                            <input type='hidden' name='page_no' id='page_no' value='<?php echo $pageno; ?>' />
                            <input type='hidden' name='hide_cancellations_appt' id='hide_cancellations_appt' value='<?php echo $hide_cancellations_appt; ?>' />
                            <div class="row">
                                <input type="hidden" name="csrf_token_form" value="<?php echo attr(CsrfUtils::collectCsrfToken()); ?>" />
                                <div class="col-4 nowrap row">
                                    <?php
                                      // Build a drop-down list of ACTIVE providers.
                                        $query = "SELECT id, lname, fname FROM users WHERE " .
                                          "authorized = 1  AND active = 1 AND username > '' ORDER BY lname, fname"; #(CHEMED) facility filter
                                        $ures = sqlStatement($query);
                                        while ($urow = sqlFetchArray($ures)) {
                                            $provid = $urow['id'];
                                            ($select_provs ?? null) ? $select_provs : $select_provs = '';
                                            $select_provs .= "    <option value='" . attr($provid) . "'";
                                            if (isset($_REQUEST['form_provider']) && in_array($provid, $_REQUEST['form_provider'])) {
                                                $select_provs .= " selected";
                                            } elseif (!isset($_REQUEST['form_provider']) && $_SESSION['userauthorized'] && $provid == $_SESSION['authUserID']) {
                                                $select_provs .= " selected";
                                            }
                                            $select_provs .= ">" . text($urow['lname']) . ", " . text($urow['fname']) . "\n";
                                            ($count_provs ?? null) ? $count_provs : $count_provs = 0;
                                            $count_provs++;
                                        }
                                        ?>
                                      <!-- Provider Section -->
                                      <label class="col-form-label col-sm-3 text-right" for="flow_from"><?php echo xlt('Provider'); ?>:</label>
                                      <div class="col-sm-9">
                                          <select class="form-control form-control-sm" id="form_provider" name="form_provider[]" <?php
                                            if ($count_provs < '2') {
                                                echo "disabled";
                                            }
                                            ?> onchange="refineMe('provider');" multiple>
                                              <option value="all" <?php echo in_array("all", $_REQUEST['form_provider']) ? "selected" : "" ?>><?php echo xlt('All Providers'); ?></option>

                                              <?php
                                                echo $select_provs;
                                                ?>
                                          </select>
                                      </div>
                                </div>

                                <?php
                                if ($GLOBALS['ptkr_date_range'] == '1') {
                                    $type = 'date';
                                    $style = '';
                                } else {
                                    $type = 'hidden';
                                    $style = 'display:none;';
                                } ?>
                                <div class="col-8 nowrap row" style="<?php echo $style; ?>">
                                    <label class="col-form-label col-sm-4 text-right" for="flow_from"><?php echo xlt('DateTime From'); ?>:</label>
                                    <div class="col-sm-8">
                                        <div class="input-group">
                                          <input type="text" id="form_from_date" name="form_from_date" class="form-control form-control-sm datepicker" value="<?php echo attr(oeFormatShortDate($from_date)); ?>" style="max-width: 150px;">
                                          <input type="text" id="form_from_time" name="form_from_time" class="form-control form-control-sm timepicker" value="<?php echo attr(oeFormatShortDate($from_time)); ?>" style="max-width: 80px;">
                                        </div>
                                    </div>

                                    <label class="col-form-label col-sm-4 text-right" for="flow_from"><?php echo xlt('DateTime To'); ?>:</label>
                                    <div class="col-sm-8">
                                        <div class="input-group">
                                          <input type="text" id="form_to_date" name="form_to_date" class="form-control form-control-sm datepicker" value="<?php echo attr(oeFormatShortDate($to_date)); ?>" style="max-width: 150px;">
                                          <input type="text" id="form_to_time" name="form_to_time" class="form-control form-control-sm timepicker" value="<?php echo attr(oeFormatShortDate($to_time)); ?>" style="max-width: 80px;">
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="row">
                                <div class="col-sm-12 mt-3 mx-auto">
                                  <!-- OEMR - Change -->
                                  <button id="filter_submit" type="button" class="btn btn-primary btn-sm btn-filter" onclick="changePage('1', true)"><?php echo xlt('Filter'); ?></button>
                                  <input type="hidden" id="kiosk" name="kiosk" value="<?php echo attr($_REQUEST['kiosk'] ?? ''); ?>" />
                                </div>
                            </div>
                        </form>
                    
                </div>
            </div>
        </div>
    <div class="row-fluid">
        <div class="col-md-12">
            <div class="text-center row mx-auto divTable">
                <div class="col-sm-12" id="loader">
                    <div class="spinner-border" role="status">
                        <span class="sr-only"><?php echo xlt('Loading data'); ?>...</span>
                    </div>
                    <h2><?php echo xlt('Loading data'); ?>...</h2>
                </div>
                <div id="flb_table" name="flb_table" class="w-100"></div>
<?php } else {

        // Propio Controller
        $propioController = new PropioController();

        $apptCat = array_map('trim', explode(",", $GLOBALS['zoom_appt_category']));

        $sql = "select ope.pc_eid, opc.pc_catid, opc.pc_catname as catname, ope.pc_eventDate, ope.pc_endDate, ope.pc_startTime, ope.pc_endTime, ope.pc_duration, ope.pc_recurrtype, ope.pc_recurrspec, ope.pc_recurrfreq, ope.pc_title, ope.pc_hometext, ope.pc_apptstatus, ope.pc_room, ope.pc_pid, CONCAT(CONCAT_WS(' ', IF(LENGTH(u.fname),u.fname,NULL), IF(LENGTH(u.lname),u.lname,NULL))) as provider_full_name, t.id, t.date, t.apptdate, t.appttime, t.eid, t.pid, t.original_user, t.encounter, t.lastseq, t.random_drug_test, t.drug_screen_completed, q.pt_tracker_id, q.start_datetime, q.room, q.status, q.seq, q.user, s.toggle_setting_1, s.toggle_setting_2, s.option_id, pd.fname, pd.mname, pd.lname, pd.pid, pd.pubpid, pd.phone_home, pd.phone_cell ";
        $sql1 = "from openemr_postcalendar_events ope join openemr_postcalendar_categories opc on opc.pc_catid = ope.pc_catid join patient_data pd on pd.pid = ope.pc_pid LEFT OUTER JOIN patient_tracker AS t ON t.pid = ope.pc_pid AND t.apptdate = ope.pc_eventDate AND t.appttime = ope.pc_starttime AND t.eid = ope.pc_eid LEFT OUTER JOIN patient_tracker_element AS q ON q.pt_tracker_id = t.id AND q.seq = t.lastseq LEFT OUTER JOIN list_options AS s ON s.list_id = 'apptstat' AND s.option_id = q.status AND s.activity = 1 left join users u on u.id = ope.pc_aid where opc.pc_catid in ('" . implode("','", $apptCat) . "') ";

        if(isset($_REQUEST['form_provider']) && !empty($_REQUEST['form_provider']) && !in_array("all", $_REQUEST['form_provider'])) {
            $sql1 .= " and ope.pc_aid in ('". implode("','", $_REQUEST['form_provider']) ."')";
        }

        if($hide_cancellations_appt === 1) {
            $sql1 .= " and ope.pc_apptstatus not in ('%', 'x') ";
        }

        if(!empty($from_date_time) && !empty($to_date_time)) {
            $sql1 .= " and concat(ope.pc_eventDate, ' ', ope.pc_startTime) >= '".$from_date_time."' and concat(ope.pc_eventDate, ' ', ope.pc_startTime) <= '".$to_date_time."'";
        }

        $order_by = " order by ope.pc_eventDate, ope.pc_startTime";
        if($limit != null && isset($pageno)) {
            $page_offset = ($pageno-1) * $limit;
            $order_by .=" LIMIT ". $limit . " OFFSET ". $page_offset;
        }

        $sql2 = "select count(ope.pc_eid) as count, ope.pc_apptstatus " . $sql1;
        $sql1 = $sql . $sql1 . $order_by;


        $userListResponceData = ZoomIntegration::getZoomUser("?page_size=300");
        $userListData = $userListResponceData['users'] ?? array();
        $accountTypeList = array(
            "1" => "Basic",
            "2" => "Licensed",
            "3" => "On-prem"
        );

        $fres = sqlStatement($sql1, array());

        $appointments = array();
        while($frow = sqlFetchArray($fres)) {
            $zoom_row = sqlQuery("SELECT e.*, u.fname, u.mname, u.lname, za.`m_id` as `zm_id`, za.`start_url` as `zm_start_url`, za.`join_url` as `zm_join_url`, za.`password` as `zm_password`, za.`host_email` as `zm_host_email`  " .
              "FROM openemr_postcalendar_events AS e " .
              "LEFT JOIN `zoom_appointment_events` as za ON za.`pc_eid` = e.`pc_eid` " .
              "LEFT OUTER JOIN users AS u ON u.id = e.pc_informant " .
              "WHERE e.pc_eid = ?", array($frow['pc_eid']));

            $frow['zm_id'] = isset($zoom_row['zm_id']) ? $zoom_row['zm_id'] : "";
            $frow['zm_start_url'] = isset($zoom_row['zm_start_url']) ? $zoom_row['zm_start_url'] : "";
            $frow['zm_join_url'] = isset($zoom_row['zm_join_url']) ? $zoom_row['zm_join_url'] : "";
            $frow['zm_host_email'] = isset($zoom_row['zm_host_email']) ? $zoom_row['zm_host_email'] : "";
            $frow['zm_type'] = "";

            if (is_array($userListData)) {
                foreach ($userListData as $userItem) {
                    if (!empty($userItem['type']) && !empty($userItem['email']) && $userItem['email'] === $frow['zm_host_email']) {
                        if (isset($accountTypeList[$userItem['type']])) {
                            $frow['zm_type'] = $accountTypeList[$userItem['type']];
                        }
                    }
                }
            }

            $appointments[] = $frow;
        }

        $totalEvents = sqlQuery($sql2, array());
        $page_details = pageDetails($totalEvents, $limit);
        
        $appointments_status = array('count_all' => $page_details['total_records']);
        $as_res = sqlStatement($sql2 . " group by ope.pc_apptstatus", array());
        while ($asevent = sqlFetchArray($as_res)) {
            $appointments_status[$asevent['pc_apptstatus']] = $asevent['count'];
        }


        ?>
        <div class="col-sm-12 text-center m-1">
            <div class=" d-sm-block">
                <span id="status_summary">
                    <?php
                    $statuses_output = "<span class='text badge badge-light'><em>" . xlt('Total patients') . ':</em> ' . text($appointments_status['count_all']) . "</span>";
                    unset($appointments_status['count_all']);
                    foreach ($appointments_status as $status_symbol => $count) {
                        $statuses_output .= " | <span><em>" . text(xl_list_label($statuses_list[$status_symbol])) . ":</em> <span class='badge badge-light'>" . text($count) . "</span></span>";
                    }
                    echo $statuses_output;
                    ?>
                </span>
            </div>
            <div id="pull_kiosk_right" class="text-right">
                <span class="fa-stack fa-lg" id="flb_caret" onclick="toggleSelectors();" title="<?php echo xla('Show/Hide the Selection Area'); ?>" style="color:<?php echo $color = ($setting_selectors == 'none') ? 'var(--danger)' : 'var(--black)'; ?>;">
                    <i class="far fa-square fa-stack-2x"></i>
                    <i id="print_caret" class='fa fa-caret-<?php echo $caret = ($setting_selectors == 'none') ? 'down' : 'up'; ?> fa-stack-1x'></i>
                </span>

                <a class="btn btn-primary btn-setting" data-toggle="collapse" href="#collapseSetting">
                    <?php echo xlt('Setting'); ?>
                </a>
                <a class='btn btn-primary btn-refresh' id='refreshme'><?php echo xlt('Refresh'); ?></a>
                
                <?php if($hide_cancellations_appt === 1) { ?>
                    <a class='btn btn-primary btn-filter' id='show_cancellations_appt_btn'><?php echo xlt('Show Cancellations'); ?></a>
                <?php } else { ?>
                    <a class='btn btn-primary btn-filter' id='hide_cancellations_appt_btn'><?php echo xlt('Hide Cancellations'); ?></a>
                <?php } ?>
            
                <a class='btn btn-primary btn-print' onclick="print_FLB();"> <?php echo xlt('Print'); ?></a>
                <a class='btn btn-primary' onclick="kiosk_FLB();"> <?php echo xlt('Kiosk'); ?></a>
                <div class="collapse mt-2 mb-2" id="collapseSetting">
                    <input type='checkbox' name='setting_new_window' id='setting_new_window' value='<?php echo attr($setting_new_window); ?>' <?php echo attr($setting_new_window); ?> />
                    <?php echo xlt('Open Patient in New Window'); ?>
                </div>
            </div>
        </div>
        <div class="table-responsive mt-3">
            <table id="table_result" class="table table-bordered">
                <thead class="table-primary">
                    <tr class="small font-weight-bold text-center">

                        <td class="details-control dehead text-center" style="max-width:150px;" name="kiosk_hide"></td>

                        <!-- OEMR - Commeted -->
                        <?php //if ($GLOBALS['ptkr_show_pid']) { ?>
                            <!-- <td class="dehead text-center text-ovr-dark" name="kiosk_hide">
                                <?php //echo xlt('PID'); ?>
                            </td> -->
                        <?php //} ?>
                        <td class="dehead text-center" style="max-width:150px;" name="kiosk_hide">
                            <?php echo xlt('PID'); ?>
                        </td>
                        <!-- End -->

                        <td class="dehead text-center text-ovr-dark" style="max-width: 150px;" name="kiosk_hide">
                            <?php echo xlt('Patient'); ?>
                        </td>

                        <?php if ($GLOBALS['ptkr_show_encounter']) { ?>
                            <td class="dehead text-center text-ovr-dark" name="kiosk_hide">
                                <?php echo xlt('Encounter'); ?>
                            </td>
                        <?php } ?>

                        <?php if ($GLOBALS['ptkr_date_range'] == '1') { ?>
                            <td class="dehead text-center text-ovr-dark" name="kiosk_hide">
                                <?php echo xlt('Appt Date'); ?>
                            </td>
                        <?php } ?>
                        <td class="dehead text-center text-ovr-dark" name="kiosk_hide">
                            <?php echo xlt('Appt Time'); ?>
                        </td>
                        <td class="dehead text-center text-ovr-dark" name="kiosk_hide">
                            <?php echo xlt('Arrive Time'); ?>
                        </td>
                        <td class="dehead text-center  d-sm-table-cell text-ovr-dark" name="kiosk_hide">
                            <?php echo xlt('Appt Status'); ?>
                        </td>
                        <td class="dehead text-center text-ovr-dark" name="kiosk_hide">
                            <?php echo xlt('Visit Type'); ?>
                        </td>
                        <td class="dehead text-center d-sm-table-cell text-ovr-dark" name="kiosk_hide">
                            <?php echo xlt('Provider'); ?>
                        </td>
                    </tr>
                </thead>
                <tbody>
                <?php
                $prev_appt_date_time = "";
                foreach ($appointments as $apptKey => $appointment) {
                    $date_appt = $appointment['pc_eventDate'];
                    $date_squash = str_replace("-", "", $date_appt);

                    $ptname = $appointment['lname'] . ', ' . $appointment['fname'] . ' ' . $appointment['mname'];
                    // OEMR - Change
                    $patientName = $appointment['fname'] . ' ' . $appointment['lname'];
                    $ptname_short = $appointment['fname'][0] . " " . $appointment['lname'][0];
                    $appt_enc = $appointment['encounter'];
                    $appt_eid = (!empty($appointment['eid'])) ? $appointment['eid'] : $appointment['pc_eid'];
                    $appt_pid = (!empty($appointment['pid'])) ? $appointment['pid'] : $appointment['pc_pid'];

                    /* OEMR - Changes */
                    if ($appt_enc != 0 && $appt_pid != 0) {
                        $patientData = getPatientData($appt_pid, "fname, mname, lname, pubpid, billing_note, DATE_FORMAT(DOB,'%Y-%m-%d') as DOB_YMD");
                    }
                    /* End */

                    if ($appt_pid == 0) {
                        continue; // skip when $appt_pid = 0, since this means it is not a patient specific appt slot
                    }
                    $status = (!empty($appointment['status']) && (!is_numeric($appointment['status']))) ? $appointment['status'] : $appointment['pc_apptstatus'];
                    $appt_room = (!empty($appointment['room'])) ? $appointment['room'] : $appointment['pc_room'];
                    $appt_time = (!empty($appointment['appttime'])) ? $appointment['appttime'] : $appointment['pc_startTime'];
                    $tracker_id = $appointment['id'];
                    // reason for visit
                    if ($GLOBALS['ptkr_visit_reason']) {
                        $reason_visit = $appointment['pc_hometext'];
                    }
                    $newarrive = collect_checkin($tracker_id);
                    $newend = collect_checkout($tracker_id);
                    $colorevents = (collectApptStatusSettings($status));
                    $bgcolor = $colorevents['color'] . '!important';
                    $statalert = $colorevents['time_alert'];

                    $waitingRoomList = !empty($appointment['zm_id']) ? ZoomUtils::zoomGetUserCountOnWaitingRoom($appointment['zm_id']) : array();
                    $meetingUserList = !empty($appointment['zm_id']) ? ZoomUtils::zoomGetMeetingUserList($appointment['zm_id']) : array();

                    echo '<tr data-apptstatus="' . attr($appointment['pc_apptstatus']) . '"
                            data-apptcat="' . attr($appointment['pc_catid']) . '"
                            data-facility="' . attr($appointment['pc_facility']) . '"
                            data-provider="' . attr($appointment['uprovider_id']) . '"
                            data-pid="' . attr($appointment['pc_pid']) . '"
                            data-pname="' . attr($ptname) . '" 
                            data-key="'. attr($apptKey) .'"
                            class="text-small"
                            style="background-color:' . attr($bgcolor) . ';" >';

                    ?>

                    <td class="details-control" name="kiosk_hide"></td>

                    <!-- OEMR - Change -->
                    <td class="detail hidden-xs" align="center" name="kiosk_hide">
                        <?php echo text($appointment['pubpid']); ?>
                    </td>
                    <!-- End -->
                    <td class="detail text-center" name="kiosk_hide">
                        <a href="#" onclick="return topatient(<?php echo attr_js($appt_pid); ?>,<?php echo attr_js($appt_enc); ?>)">
                            <?php echo text($ptname); ?></a>
                    </td>

                    <?php if ($GLOBALS['ptkr_show_encounter']) { ?>
                        <td class="detail text-center" name="kiosk_hide">
                            <!-- OEMR - Change -->
                            <?php if ($appt_enc != 0) { ?>
                                <a href="#" class="<?php echo getESignClass(text($appt_enc)); ?>" onclick='handleGoToEncounter("<?php echo $appt_pid; ?>", "<?php echo text($appointment['pubpid']); ?>", "<?php echo htmlspecialchars($patientName, ENT_QUOTES); ?>", "<?php echo text($appt_enc); ?>", "<?php echo xl('DOB') . ': ' . addslashes(oeFormatShortDate($patientData['DOB_YMD'])) . ' ' . xl('Age') . ': ' . getPatientAge($patientData['DOB_YMD']) ?>")'><?php echo text($appt_enc); ?></a>
                            <?php } ?>
                            <!-- End -->
                        </td>
                    <?php } ?>
                    <?php if ($GLOBALS['ptkr_date_range'] == '1') { ?>
                        <td class="detail text-center" name="kiosk_hide">
                            <?php echo text(oeFormatShortDate($appointment['pc_eventDate']));
                            ?>
                            <?php if(isset($appointment['pc_hometext']) && !empty($appointment['pc_hometext'])): ?>
                                <span style="white-space: pre-line;" title="<?php echo text($appointment['pc_hometext']); ?>"><i class="fa fas fa-exclamation-circle ml-1"></i></span> 
                                <?php endif;?>
                        </td>
                    <?php } ?>

                    <td class="detail text-center" name="kiosk_hide">
                        <?php echo text(oeFormatTime($appt_time)); ?>
                    </td>
                    <td class="detail text-center" name="kiosk_hide">
                        <?php
                        if ($newarrive) {
                            echo text(oeFormatTime($newarrive));
                        }
                        ?>
                    </td>
                    <td class="detail text-center" name="kiosk_hide">
                        <?php if (empty($tracker_id)) { //for appt not yet with tracker id and for recurring appt ?>
                        <a class="btn btn-primary btn-sm" onclick="return calendarpopup(<?php echo attr_js($appt_eid) . "," . attr_js($date_squash); // calls popup for add edit calendar event?>)">
                        <?php } else { ?>
                            <a class="btn btn-primary btn-s" onclick="return bpopup(<?php echo attr_js($tracker_id); // calls popup for patient tracker status?>)">
                        <?php } ?>
                        <?php
                        if ($appointment['room'] > '') {
                            echo text(getListItemTitle('patient_flow_board_rooms', $appt_room));
                        } else {
                            echo text(getListItemTitle("apptstat", $status)); // drop down list for appointment status
                        }
                        ?>
                        </a>
                    </td>
                    <td class="detail text-center" name="kiosk_hide">
                        <?php echo xlt($appointment['pc_title']); ?>
                    </td>
                    <td class="detail text-center" name="kiosk_hide">
                        <?php echo xlt($appointment['provider_full_name']); ?>
                        <?php echo !empty($appointment['zm_type']) ? " (" . $appointment['zm_type'] . ") " : ""; ?>
                    </td>

                    <?php

                    echo "</tr>";
                    echo "<tr class='rowdetail row-details-" . attr($apptKey) . "' >";
                    ?>
                    <td colspan="12" name="kiosk_hide">
                        <?php 
                        //if(isset($appointment['zm_start_url']) && !empty($appointment['zm_start_url'])) {
                            echo '<div class="tdDetailsContainer">';
                            echo '<div>';
                            echo '<span>' . xla("Zoom Meeting (Provider)::") . ' </span>';
                            echo isset($appointment['zm_start_url']) && !empty($appointment['zm_start_url']) ? '<span><a href="'.$appointment['zm_start_url'].'" target="_blank">'. xla('Join meeting link') . '</a></span>' : '<i>None</i>';
                            echo '</br><span>' . xla("Zoom Meeting (Patient):") . ' </span>';
                            echo isset($appointment['zm_join_url']) && !empty($appointment['zm_join_url']) ? '<a href="javascript:void(0);" onclick="sel_communication_type('.$appt_eid.', '.$appt_pid.');" >'.xla("Send Meeting Details").'</a>' : '<i>None</i>';
                            echo '</div>';

                            echo '<div>';

                            // Propio Render Element
                            echo '<div id="propio_container_'.$appointment['zm_id'].'">';
                            $propioController->renderPropioElement($appointment['zm_id'], $appt_eid);
                            echo '</div>';

                            echo '</div>';
                            echo '</div>';

                            echo '<div class="mt-2 mb-3">';
                            if(!empty($waitingRoomList) && isset($waitingRoomList['waiting_user_count']) && $waitingRoomList['waiting_user_count'] > 0) {
                                echo '<button type="button" class="btn btn-warning btn-sm mr-1">'.xla("In Waiting").' ('.$waitingRoomList['waiting_user_count'].') </button>';
                            }

                            if(isset($meetingUserList['items'])) {
                                foreach ($meetingUserList['items'] as $userItem) {
                                    $btnClass = $userItem['is_in_meeting'] === 1 ? 'btn-success' : 'btn-danger';
                                    $userName = !empty($userItem['user_name']) ? $userItem['user_name'] : $userItem['user_id'];
                                
                                    echo '<button type="button" class="btn '.$btnClass.' btn-sm mr-1">'. $userName . ' (' . ($userItem['is_host'] === 1 ? 'Host' : 'Patient') . ') </button>';
                                }
                            }
                            
                            echo '</div>';
                        //}
                        ?>
                    </td>
                    <?php
                    echo "</tr>";
                } ?>
                </tbody>
            </table>
        </div>
        <?php

} //end of second !$_REQUEST['flb_table']

// OEMR - Pagination
generatePagination($page_details, $pageno);

if (!($_REQUEST['flb_table'] ?? null)) { ?> 
<?php echo myLocalJS(); ?>
            </div>
        </div>
    </div>
</div>
</body>
</html>
<?php
} //end of second !$_REQUEST['flb_table']

exit;

function myLocalJS()
{
    ?>
    <script>
        var auto_refresh = null;

        //this can be refined to redact HIPAA material using @media print options.
        window.parent.$("[name='flb']").attr('allowFullscreen', 'true');
        $("[name='kiosk_hide']").show();
        $("[name='kiosk_show']").hide();

        /* OEMR - Changes */
        function changePage(page_no, init = false) {
            if(page_no && page_no != '') {
                let errorMsg = [];

                if($("#form_from_date").val() == "" || $("#form_from_time").val() == "") errorMsg.push("Enter valid \"DateTime From\"");
                if($("#form_to_date").val() == "" || $("#form_to_time").val() == "") errorMsg.push("Enter valid \"DateTime To\"");

                if(errorMsg.length > 0) {
                    alert(errorMsg.join("\n"));
                    return false;
                }

                if(init === true) $("#hide_cancellations_appt").val("0");

                $('#page_no').val(page_no);
                $('#flb').submit();
            }
        }
        /* End */

        function print_FLB() {
            window.print();
        }

        /**
         * This function refreshes the whole flb_table according to our to/from dates.
         */
        function refreshMe(fromTimer) {

            if (typeof fromTimer === 'undefined' || !fromTimer) {
                //Show loader in the first loading or manual loading not by timer
                $("#flb_table").html('');
                $('#loader').show();
                skip_timeout_reset = 0;
            } else {
                skip_timeout_reset = 1;
            }

            var startRequestTime = Date.now();
            top.restoreSession();
            // OEMR - added page_no
            var posting = $.post('../patient_tracker/telemedicine_waiting_room.php', {
                flb_table: '1',
                form_from_date: $("#form_from_date").val(),
                form_to_date: $("#form_to_date").val(),
                form_from_time: $("#form_from_time").val(),
                form_to_time: $("#form_to_time").val(),
                form_provider: $("#form_provider").val(),
                kiosk: $("#kiosk").val(),
                skip_timeout_reset: skip_timeout_reset,
                csrf_token_form: <?php echo js_escape(CsrfUtils::collectCsrfToken()); ?>,
                page_no: $("#page_no").val(),
                hide_cancellations_appt: $("#hide_cancellations_appt").val()
            }).done(
                function (data) {
                    //minimum 400 ms of loader (In the first loading or manual loading not by timer)
                    if((typeof fromTimer === 'undefined' || !fromTimer) && Date.now() - startRequestTime < 400 ){
                        setTimeout(drawTable, 500, data);
                    } else {
                        drawTable(data)
                    }
                });
        }

        function drawTable(data) {

            $('#loader').hide();
            $("#flb_table").html(data);
            if ($("#kiosk").val() === '') {
            $("[name='kiosk_hide']").show();
            $("[name='kiosk_show']").hide();
            } else {
            $("[name='kiosk_hide']").hide();
            $("[name='kiosk_show']").show();
            }

            refineMe();

            initTableButtons();
            initDataTable();
        }

        function refreshme() {
            // Just need this to support refreshme call from the popup used for recurrent appt
            refreshMe();
        }

        /**
         * This function hides all then shows only the flb_table rows that match our selection, client side.
         * It is called on initial load, on refresh and 'onchange/onkeyup' of a Telemedicine Waiting Room parameter.
         */
        function refineMe() {
            // OEMR - Return
            return true;

            var apptcatV = $("#form_apptcat").val();
            var apptstatV = $("#form_apptstatus").val();
            var facV = $("#form_facility").val();
            var provV = $("#form_provider").val();
            var pidV = String($("#form_patient_id").val());
            var pidRE = new RegExp(pidV, 'g');
            var pnameV = $("#form_patient_name").val();
            var pnameRE = new RegExp(pnameV, 'ig');

            //and hide what we don't want to show
            $('#flb_table tbody tr').hide().filter(function () {
                var d = $(this).data();
                meets_cat = (apptcatV === '') || (apptcatV == d.apptcat);
                meets_stat = (apptstatV === '') || (apptstatV == d.apptstatus);
                meets_fac = (facV === '') || (facV == d.facility);
                meets_prov = (provV === '') || (provV == d.provider);
                meets_pid = (pidV === '');
                if ((pidV > '') && pidRE.test(d.pid)) {
                    meets_pid = true;
                }
                meets_pname = (pnameV === '');
                if ((pnameV > '') && pnameRE.test(d.pname)) {
                    meets_pname = true;
                }
                return meets_pname && meets_pid && meets_cat && meets_stat && meets_fac && meets_prov;
            }).show();
        }

        // popup for patient tracker status
        function bpopup(tkid) {
            top.restoreSession();
            dlgopen('<?php echo $GLOBALS['webroot']; ?>/interface/patient_tracker/patient_tracker_status.php?tracker_id=' + encodeURIComponent(tkid) + '&csrf_token_form=' + <?php echo js_url(CsrfUtils::collectCsrfToken()); ?>, '_blank', 500, 250);
            return false;
        }

        // popup for calendar add edit
        function calendarpopup(eid, date_squash) {
            top.restoreSession();
            dlgopen('<?php echo $GLOBALS['webroot']; ?>/interface/main/calendar/add_edit_event.php?eid=' + encodeURIComponent(eid) + '&date=' + encodeURIComponent(date_squash), '_blank', 775, 500);
            return false;
        }

        // used to display the patient demographic and encounter screens
        function topatient(newpid, enc) {
            if ($('#setting_new_window').val() === 'checked') {
                openNewTopWindow(newpid, enc);
            }
            else {
                top.restoreSession();
                if (enc > 0) {
                    top.RTop.location = "<?php echo $GLOBALS['webroot']; ?>/interface/patient_file/summary/demographics.php?set_pid=" + encodeURIComponent(newpid) + "&set_encounterid=" + encodeURIComponent(enc);
                }
                else {
                    top.RTop.location = "<?php echo $GLOBALS['webroot']; ?>/interface/patient_file/summary/demographics.php?set_pid=" + encodeURIComponent(newpid);
                }
            }
        }

        function doCALLback(eventdate, eid, pccattype) {
            $("#progCALLback_" + eid).parent().removeClass('js-blink-infinite').css('animation-name', 'none');
            $("#progCALLback_" + eid).removeClass("hidden");
            clearInterval(auto_refresh);
        }

        // opens the demographic and encounter screens in a new window
        function openNewTopWindow(newpid, newencounterid) {
            document.fnew.patientID.value = newpid;
            document.fnew.encounterID.value = newencounterid;
            top.restoreSession();
            document.fnew.submit();
        }

        function kiosk_FLB() {
            $("#kiosk").val('1');
            $("[name='kiosk_hide']").hide();
            $("[name='kiosk_show']").show();

            var i = document.getElementById("flb_table");
            // go full-screen
            if (i.requestFullscreen) {
                i.requestFullscreen();
            } else if (i.webkitRequestFullscreen) {
                i.webkitRequestFullscreen();
            } else if (i.mozRequestFullScreen) {
                i.mozRequestFullScreen();
            } else if (i.msRequestFullscreen) {
                i.msRequestFullscreen();
            }
            // refreshMe();
        }

        function KioskUp() {
            var kv = $("#kiosk").val();
            if (kv == '0') {
                $("#kiosk").val('1');
                $("[name='kiosk_hide']").show();
                $("[name='kiosk_show']").hide();
            } else {
                $("#kiosk").val('0');
                $("[name='kiosk_hide']").hide();
                $("[name='kiosk_show']").show();
            }
        }

        $(function () {
            refreshMe();
            $("#kiosk").val('');
            $("[name='kiosk_hide']").show();
            $("[name='kiosk_show']").hide();

            onresize = function () {
                var state = 1 >= outerHeight - innerHeight ? "fullscreen" : "windowed";
                if (window.state === state) return;
                window.state = state;
                var event = document.createEvent("Event");
                event.initEvent(state, true, true);
                window.dispatchEvent(event);
            };

            ["fullscreenchange", "webkitfullscreenchange", "mozfullscreenchange", "msfullscreenchange"].forEach(
                eventType => document.addEventListener(eventType, KioskUp, false)
            );

            <?php if ($GLOBALS['pat_trkr_timer'] != '0') { ?>
                var reftime = <?php echo js_escape($GLOBALS['pat_trkr_timer']); ?>;
                var parsetime = reftime.split(":");
                parsetime = (parsetime[0] * 60) + (parsetime[1] * 1) * 1000;
                if (auto_refresh) clearInteral(auto_refresh);
                auto_refresh = setInterval(function () {
                    // OEMR - Wrap with in condition
                    if(getActiveTab() == "flb1") {
                        refreshMe(true) // this will run after every parsetime seconds
                    }
                }, parsetime);
            <?php } ?>

            $('.js-blink-infinite').each(function () {
                // set up blinking text
                var elem = $(this);
                setInterval(function () {
                    if (elem.css('visibility') === 'hidden') {
                        elem.css('visibility', 'visible');
                    } else {
                        elem.css('visibility', 'hidden');
                    }
                }, 500);
            });
            // toggle of the check box status for drug screen completed and ajax call to update the database
            $('body').on('click', '.drug_screen_completed', function () {
                top.restoreSession();
                if (this.checked) {
                    testcomplete_toggle = "true";
                } else {
                    testcomplete_toggle = "false";
                }
                $.post("../../library/ajax/drug_screen_completed.php", {
                    trackerid: this.id,
                    testcomplete: testcomplete_toggle,
                    csrf_token_form: <?php echo js_escape(CsrfUtils::collectCsrfToken()); ?>
                });
            });

            $('#filter_submit').click(function (e) {
                e.preventDefault;
                refreshMe();
            });

            $('[data-toggle="tooltip"]').tooltip();

            $('.datepicker').datetimepicker({
                <?php $datetimepicker_timepicker = false; ?>
                <?php $datetimepicker_showseconds = false; ?>
                <?php $datetimepicker_formatInput = true; ?>
                <?php require($GLOBALS['srcdir'] . '/js/xl/jquery-datetimepicker-2-5-4.js.php'); ?>
                <?php // can add any additional javascript settings to datetimepicker here; need to prepend first setting with a comma ?>
            });

            $('.timepicker').datetimepicker({
                datepicker: false,
                step: 15,
                format: 'H:i'
            });

            jQuery(document).on('click', '#table_result thead td.details-control', function () {
                let tr = jQuery(this).closest('tr');
                let mode = 0;
                if(!tr.hasClass("details")) {
                    tr.addClass('details');
                    mode = 1;
                } else {
                    tr.removeClass('details');
                    mode = 2;
                }

                $('#table_result tbody td.details-control').each(function( ind ) {
                    toggleRowDetails($(this), mode);
                });
            });

            jQuery(document).on('click', '#table_result tbody td.details-control', function () {
                toggleRowDetails($(this));
            });
        });

        function initTableButtons() {
            $('#refreshme').click(function () {
                refreshMe();
                refineMe();
            });

            $('#hide_cancellations_appt_btn').click(function (e) {
                e.preventDefault;
                $('#hide_cancellations_appt').val("1");
                changePage('1');
            });

            $('#show_cancellations_appt_btn').click(function (e) {
                e.preventDefault;
                $('#hide_cancellations_appt').val("0");
                changePage('1');
            });
        }

        initTableButtons();
        initDataTable();

        /* OEMR - Added function */
        function getActiveTab() {
            var tabName = "";
            for(var tabIdx=0;tabIdx<top.app_view_model.application_data.tabs.tabsList().length;tabIdx++){
                var curTab=top.app_view_model.application_data.tabs.tabsList()[tabIdx];
                
                if(curTab.visible()) {
                    tabName = curTab.name();
                }
            }
            return tabName;
        }

        function initDataTable() {
            $('#table_result thead td.details-control').click();
        }

        function toggleRowDetails(el, mode = 0) {
            let tr = jQuery(el).closest('tr');
            let childkey = tr.data('key');
            let child_tr = jQuery('.row-details-'+childkey);

            if(!tr.hasClass("details") || mode == 1) {
                tr.addClass('details');
                child_tr.addClass('show');
            } else if(tr.hasClass("details") || mode == 2) {
                tr.removeClass('details');
                child_tr.removeClass('show');
            }
            
        }

        // This invokes popup to send zoom details.
        function sel_communication_type(eid, pid) {
            var url = "<?php echo $GLOBALS['webroot'] . '/interface/main/calendar/php/zoom_popup.php?eid='; ?>"+ eid + "&pid=" + pid;

            let title = '<?php echo xlt('Select'); ?>';
            dlgopen(url, 'selectCommunicationType', 400, 200, '', title);
        }

        async function setCommunicationType(obj) {
            if(obj && obj.eid) {
                var data = {};

                data['eid'] = obj.eid;
                data['selectedType'] = [];
                
                if(obj.email && obj.email == 1) {
                    data['selectedType'].push('email');
                }

                if(obj.sms && obj.sms == 1) {
                    data['selectedType'].push('sms');
                }

                sendJoinUrlDetails(data);
            }
        }

        async function sendJoinUrlDetails(data) {
            const result = await $.ajax({
                type: "POST",
                url: "<?php echo $GLOBALS['webroot'] .'/interface/main/calendar/ajax/send_zoom_details.php'; ?>",
                datatype: "json",
                data: data
            });

            if(result) {
                resultObj = JSON.parse(result);

                if(resultObj['message']) {
                    alert(resultObj['message']);
                }
            }
        }
        /* End */

        function sel_propio(appt_id) {
            let title = '<?php echo xlt('Propio Request'); ?>';
            dlgopen('../../public/index.php?action=propio_popup&appt_id='+appt_id, 'propio_popup', 650, 400, '', title);
        }

        var appt_id = "";

        function dispalyPropioElement(meetingId, apptId) {
            $.ajax({
                type: "POST",
                url: '../../public/index.php',
                dataType: "html",
                async: false,
                data: { action : 'ajax_propio', 'meetingId' : meetingId, 'apptId' : apptId, 'mode' : 'fetch' },
                success: function (data) {
                    let propioContainer  = $('#propio_container_'+meetingId);
                    propioContainer.html(data);
                },
                error: function (data, errorThrown) {
                    //alert(data.responseText);
                }
            });
        }

        function handlePropio(ev, mode = '') {
            let propioId = $(ev).data('propioid');
            let apptId = $(ev).data('apptid');
            let mId = $(ev).data('mid');

            if(mode == "") {
                alert("Something wrong");
                return false;
            }

            if(mode == "request") {
                appt_id = apptId;
                sel_propio(apptId);
            } else if(mode == "cancel") {
                $.ajax({
                    type: "POST",
                    url: '../../public/index.php',
                    dataType: "json",
                    async: false,
                    data: { action : 'ajax_propio', 'propioId' : propioId, 'mode' : 'cancel' },
                    success: function (data) {
                        if(data.hasOwnProperty('id')) {
                            dispalyPropioElement(mId, apptId);
                            alert('Success');
                        }
                    },
                    error: function (data, errorThrown) {
                        dispalyPropioElement(mId, apptId);
                        alert(data.responseText);
                    }
                });
            } else if(mode == "complete") {
                $.ajax({
                    type: "POST",
                    url: '../../public/index.php',
                    dataType: "json",
                    async: false,
                    data: { action : 'ajax_propio', 'propioId' : propioId, 'mode' : 'complete' },
                    success: function (data) {
                        if(data.hasOwnProperty('id')) {
                            dispalyPropioElement(mId, apptId);
                            alert('Success');
                        }
                    },
                    error: function (data, errorThrown) {
                        dispalyPropioElement(mId, apptId);
                        alert(data.responseText);
                    }
                });
            }
        }

        function setpropio(languageId, conferenceId, joinLink, apptId) {
            if(languageId != "" && conferenceId != "" && joinLink != "") {
                $.ajax({
                    type: "POST",
                    url: '../../public/index.php',
                    dataType: "json",
                    async: false,
                    data: { languageId: languageId, conferenceId: conferenceId, joinLink : joinLink, action : 'ajax_propio', 'mode' : 'request' },
                    success: function (data) {
                        if(data.hasOwnProperty('id')) {
                            dispalyPropioElement(conferenceId, apptId);
                            alert('Success');
                        }
                    },
                    error: function (data, errorThrown) {
                        dispalyPropioElement(conferenceId, apptId);
                        alert(data.responseText);
                    }
                });
            }
        }
    </script>
<?php }
?>