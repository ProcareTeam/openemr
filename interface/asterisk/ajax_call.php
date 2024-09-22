<?php
require_once("../globals.php");
require_once("$srcdir/options.inc.php");
require_once("$srcdir/patient.inc");
require_once("$srcdir/wmt-v2/wmtstandard.inc");
require_once("$srcdir/wmt-v2/wmt.msg.inc");
require_once("$srcdir/OemrAD/oemrad.globals.php");
require_once($GLOBALS['fileroot'] . "/interface/reports/idempiere_pat_ledger_fun.php");	

use OpenEMR\Common\Crypto\CryptoGen;
use OpenEMR\Core\Header;
use OpenEMR\OemrAd\Caselib;

//$data = isset($_REQUEST['data']) ? json_decode($_REQUEST['data']) : array();
$json = file_get_contents('php://input');
$jsonObj = isset($json) && !empty($json) ? json_decode($json, true) : array();

$action = isset($jsonObj['action']) ? $jsonObj['action'] : "";
$callerdetails = isset($jsonObj['data']) ? $jsonObj['data'] : array();
$patient_pid = isset($jsonObj['data']['pid']) ? $jsonObj['data']['pid'] : "";

function getHtmlString($text) {
    return addslashes(htmlspecialchars($text));
}

function getCodeCountData($case_id = array()) {
    $dataSet = array();
    $case_id_str = "'".implode("','",$case_id)."'";

    if(!empty($case_id)) {
        $esql = sqlStatement("SELECT count(cal.encounter) as total_count, cal.enc_case as case_id, b.code_type from case_appointment_link cal left join form_encounter fe on fe.encounter = cal.encounter left join billing b on b.encounter = fe.encounter where ((b.code_type = 'CPT4' and b.code like '7%') or (b.code_type = 'HCPCS' and b.code = 'E0720')) and cal.enc_case IN (".$case_id_str.") and b.activity = 1 group by cal.enc_case, b.code_type");

        while ($enrow = sqlFetchArray($esql)) {
            if(!isset($dataSet['case_'.$enrow['case_id']])) {
                $dataSet['case_'.$enrow['case_id']] = array();
            }

            $dataSet['case_'.$enrow['case_id']][] = $enrow;
        }
    }

    return $dataSet;
}

function getIdempierePatientBalance($connection, $pid) {
    $balanceData = array();
    if(empty($pid)) return $balanceData;
    $balances = get_idempiere_patient_balance($connection, $pid);
    $patient_balance_due = isset($balances['patientResponsibility']) ? $balances['patientResponsibility'] : 0;
    $total_balance_due = isset($balances['overallBalance']) ? $balances['overallBalance'] : 0;
    $insurance_balance_due = ($total_balance_due - $patient_balance_due);
    $balanceData = array(
        'patient_balance_due' => $patient_balance_due,
        'insurance_balance_due' => $insurance_balance_due,
        'total_balance_due' => $total_balance_due
    );
    return $balanceData;
}

if($action == "patient_details") {
	foreach($callerdetails as $callsonext) {
		$callernum = $callsonext['ConnectedLineNum'];
        //$callNumList = [$callernum];
        $callNum = preg_replace('/^\+?1|\|1|\D/', '', $callernum, 1);

        // patient data
        $res2 = sqlStatement("select pid, CONCAT(CONCAT_WS(' ', IF(LENGTH(pd.fname),pd.fname,NULL), IF(LENGTH(pd.lname),pd.lname,NULL)), ' (', pd.pubpid ,')') as patient_name, DOB as date_of_birth, alert_info, phone_cell from patient_data pd where TRIM(LEADING '1' FROM replace(replace(replace(replace(replace(replace(pd.phone_cell,' ',''),'(','') ,')',''),'-',''),'/',''),'+','')) LIKE ? order by id desc",array($callNum.""));

        // Get user details
        $row8 = sqlStatement("select u.*, CONCAT(CONCAT_WS(' ', IF(LENGTH(u.fname),u.fname,NULL), IF(LENGTH(u.lname),u.lname,NULL))) as u_name FROM users u where TRIM(LEADING '1' FROM replace(replace(replace(replace(replace(replace(phone,' ',''),'(','') ,')',''),'-',''),'/',''),'+','')) LIKE ? or TRIM(LEADING '1' FROM replace(replace(replace(replace(replace(replace(fax,' ',''),'(','') ,')',''),'-',''),'/',''),'+','')) LIKE ? or TRIM(LEADING '1' FROM replace(replace(replace(replace(replace(replace(phonew1,' ',''),'(','') ,')',''),'-',''),'/',''),'+','')) LIKE ? or TRIM(LEADING '1' FROM replace(replace(replace(replace(replace(replace(phonew2,' ',''),'(','') ,')',''),'-',''),'/',''),'+','')) LIKE ? or TRIM(LEADING '1' FROM replace(replace(replace(replace(replace(replace(phonecell,' ',''),'(','') ,')',''),'-',''),'/',''),'+','')) LIKE ?",array($callNum."", $callNum."", $callNum."", $callNum."", $callNum.""));

        if (sqlNumRows($res2) > 0) {
        	while ($pdata = sqlFetchArray($res2)) {
        		// Mostrecent Section
        		$row2 = sqlStatement("select TIMESTAMP(ope.pc_eventDate, ope.pc_startTime) as event_date_time, ope.pc_aid as provider_id, u.fname as provider_fname, u.mname as provider_mname, u.lname as provider_lname, CONCAT(CONCAT_WS(' ', IF(LENGTH(u.fname),u.fname,NULL), IF(LENGTH(u.lname),u.lname,NULL))) as provider_full_name, opc.pc_catname, lo.title as reason from openemr_postcalendar_events ope left join openemr_postcalendar_categories opc on opc.pc_catid = ope.pc_catid left join users u on u.id = ope.pc_aid left join list_options lo on lo.list_id = 'apptstat' and lo.option_id = ope.pc_apptstatus where ope.pc_pid = ? and TIMESTAMP(ope.pc_eventDate, ope.pc_startTime) < now() order by TIMESTAMP(ope.pc_eventDate, ope.pc_startTime) desc limit 10",array($pdata['pid']));

        		$row3 = sqlStatement("select TIMESTAMP(ope.pc_eventDate, ope.pc_startTime) as event_date_time, ope.pc_aid as provider_id, u.fname as provider_fname, u.mname as provider_mname, u.lname as provider_lname, CONCAT(CONCAT_WS(' ', IF(LENGTH(u.fname),u.fname,NULL), IF(LENGTH(u.lname),u.lname,NULL))) as provider_full_name, opc.pc_catname, lo.title as reason  from openemr_postcalendar_events ope left join openemr_postcalendar_categories opc on opc.pc_catid = ope.pc_catid left join users u on u.id = ope.pc_aid left join list_options lo on lo.list_id = 'apptstat' and lo.option_id = ope.pc_apptstatus where ope.pc_pid = ? and TIMESTAMP(ope.pc_eventDate, ope.pc_startTime) > now() order by TIMESTAMP(ope.pc_eventDate, ope.pc_startTime) desc limit 10",array($pdata['pid']));

        		$row6 = sqlStatement("select id, rto_ordered_by, rto_action, rto_status, rto_resp_user, rto_date, rto_case,rto_notes from form_rto where pid = ? and rto_status not in ('x', 'sc85') order by id",array($pdata['pid']));

        		$row7 = sqlStatement("select DISTINCT pid, rto_case from form_rto where pid = ? and rto_case IS NOT NULL AND rto_case != '' order by rto_case",array($pdata['pid']));

        		?>
	        	<div class="row mb-4">
                    <div class="col-6">
	                	<!-- Patient Details -->
	                    <section class="card mb-2">
	                        <div class="card-body p-1">
	                            <h6 class="card-title mb-0 d-flex p-1 justify-content-between">
	                                <a class="text-left font-weight-bolder" href="#" data-toggle="collapse" data-target="#" aria-expanded="true" aria-controls=""><?php echo xl('Patient Details') ?></a>
	                            </h6>
	                            <div id="patient_details" class="card-text collapse show">
                                    <div class="clearfix pt-2">
                                        <div class="table-responsive">
        	                                <table class="table table-sm table-striped">
        	                                    <thead>
        	                                        <tr>
        	                                            <th scope="col"><?php echo xl('Patient Name') ?></th>
        	                                            <th scope="col"><?php echo xl('Date of Birth') ?></th>
        	                                            <th scope="col"><?php echo xl('Alert Info') ?></th>
        	                                        </tr>
        	                                    </thead>
        	                                    <tbody>
        	                                        <?php
        	                                        	//while ($getPatientInfo1 = sqlFetchArray($res2)) {
        	                                                $getPatientid= $pdata['pid'];
                                                            echo "<tr>";
        	                                                echo "<td ><a href='javascript:goParentPid($getPatientid)'>".$pdata['patient_name']."</a></td>"; 
        	                                                echo "<td>".$pdata['date_of_birth']."</td>";  
        	                                                echo "<td>".$pdata['alert_info']."</td>"; 
        	                                                echo "</tr>"; 
        	                                            //}  
        	                                        ?>
        	                                    </tbody>
        	                                </table>
                                        </div>
                                    </div>
	                            </div>
	                        </div>
	                    </section>

	                    <!-- Most Recent Appt -->
	                    <section class="card mb-2  ">
                            <div class="card-body p-1">
                                <h6 class="card-title mb-0 d-flex p-1 justify-content-between">
                                    <a class="text-left font-weight-bolder" href="#" data-toggle="collapse" data-target="#" aria-expanded="true" aria-controls=""><?php echo xl('Most Recent Appointment') ?></a>
                                </h6>
                                <div id="most_recent_appt" class="card-text collapse show">
                                    <div class="clearfix pt-2">
                                        <div class="table-responsive">
                                        <?php if(sqlNumRows($row2) > 0) { ?>
                                            <table class="table table-sm table-striped">
                                                <thead>
                                                    <tr>
                                                        <th scope="col"><?php echo xl('Event Date Time') ?></th>
                                                        <th scope="col"><?php echo xl('Provider Name'); ?></th>
                                                        <th scope="col"><?php echo xl('Appt Category'); ?></th>
                                                        <th scope="col"><?php echo xl('Status'); ?></th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    <?php
                                                        while ($getMostRecentAppt1 = sqlFetchArray($row2)) {
                                                            echo "<tr>";
                                                            echo "<td>".text(oeFormatDateTime($getMostRecentAppt1['event_date_time'], "global", true))."</td>";   
                                                            echo "<td>".$getMostRecentAppt1['provider_full_name']."</td>"; 
                                                            echo "<td>".$getMostRecentAppt1['pc_catname']. "</td>";  
                                                            echo "<td>".$getMostRecentAppt1['reason']."</td>"; 
                                                            echo "</tr>";
                                                        }
                                                    ?>
                                                </tbody>
                                            </table>
                                        <?php } else { ?>
                                           <p class="msg"><?php echo xl('No most recent appt'); ?></p>
                                        <?php } ?>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </section>

                        <!-- Nearest Upcoming Appointment -->
                        <section class="card mb-2  ">
                            <div class="card-body p-1">
                                <h6 class="card-title mb-0 d-flex p-1 justify-content-between">
                                    <a class="text-left font-weight-bolder" href="#" data-toggle="collapse" data-target="#" aria-expanded="true" aria-controls=""><?php echo xl('Nearest Upcoming Appointment') ?></a>
                                </h6>
                                <div id="nearest_upcoming_appt" class="card-text collapse show" style="">
                                    <div class="clearfix pt-2">
                                        <div class="table-responsive">
                                        <?php if(sqlNumRows($row3) > 0) {?>
                                        <table class="table table-sm table-striped">
                                            <thead>
                                                <tr>
                                                    <th scope="col"><?php echo xl('Event Date Time') ?></th>
                                                    <th scope="col"><?php echo xl('Provider ID') ?></th>
                                                    <th scope="col"><?php echo xl('Provider Name') ?></th>
                                                    <th scope="col"><?php echo xl('Appt Category') ?></th>
                                                    <th scope="col"><?php echo xl('Reason') ?></th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php
                                                    while ($getNearestUpcomingAppt1 = sqlFetchArray($row3)) {
                                                    	echo "</tr>";
                                                        echo "<td>".text(oeFormatDateTime($getNearestUpcomingAppt1['event_date_time'], "global", true))."</td>"; 
                                                        echo "<td>".$getNearestUpcomingAppt1['provider_id']."</td>";  
                                                        echo "<td>".$getNearestUpcomingAppt1['provider_full_name']."</td>"; 
                                                        echo "<td>".$getNearestUpcomingAppt1['pc_catname']. "</td>";  
                                                        echo "<td>".$getNearestUpcomingAppt1['reason']."</td>"; 
                                                        echo "</tr>";
                                                    }
                                                ?>
                                            </tbody>
                                        </table>
                                        <?php } else { ?>
                                        <p class="msg"><?php echo xl('No nearest upcoming appt') ?></p>
                                        <?php } ?>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </section> 

                        <!-- Patient Balances -->
                        <section class="card mb-2">
                            <div class="card-body p-1">
                                <h6 class="card-title mb-0 d-flex p-1 justify-content-between">
                                    <a class="text-left font-weight-bolder" href="#" data-toggle="collapse" data-target="#" aria-expanded="true" aria-controls=""><?php echo xl('Current Patient Balances') ?></a>
                                </h6>
                                <div id="patient_balance_<?php echo $pdata['pid']; ?>" class="card-text collapse show" style="">
                                	<div class="py-3">
                                		<center>
                                			<div class="spinner-border" role="status">
		                                		<span class="sr-only">Loading...</span>
		                                	</div>
                                		</center>
                                	</div>
                                </div>
                            </div>
                        </section>

                    </div>
                    <div class="col-6">
                        <!-- List of payers associated with patient -->
                        <section class="card mb-2 ">
                            <div class="card-body p-1">
                                <h6 class="card-title mb-0 d-flex p-1 justify-content-between">
                                    <a class="text-left font-weight-bolder" href="#" data-toggle="collapse" data-target="#" aria-expanded="true" aria-controls=""><?php echo xl('List of payers associated with patient') ?></a>
                                </h6>
                                <div id="list_of_payers_<?php echo $pdata['pid']; ?>" class="card-text collapse show" style="">
                                    <div class="py-3">
                                        <center>
                                            <div class="spinner-border" role="status">
                                                <span class="sr-only">Loading...</span>
                                            </div>
                                        </center>
                                    </div>
                                </div>
                            </div>
                        </section>

                        <!-- Orders Details --> 
                        <section class="card mb-2  ">
                            <div class="card-body p-1">
                                <h6 class="card-title mb-0 d-flex p-1 justify-content-between">
                                    <a class="text-left font-weight-bolder" href="#" data-toggle="collapse" data-target="#" aria-expanded="true" aria-controls=""><?php echo xl('Orders pending to schedule') ?></a>
                                </h6>
                                <div id="uncompletedAndNoncancelledOrders" class="card-text collapse show" style="">
                                    <div class="clearfix pt-2">
                                        <div class="table-responsive">
                                        <?php if(sqlNumRows($row6) > 0) {?>
                                        <table class="table table-sm table-striped">
                                            <thead>
                                                <tr>
                                                    <th scope="col"><?php echo xl('Order Type') ?></th>
                                                    <th scope="col"><?php echo xl('Order By') ?></th>
                                                    <th scope="col"><?php echo xl('Status') ?></th>
                                                    <th scope="col"><?php echo xl('Assigned To') ?></th>
                                                    <th scope="col"><?php echo xl('Order Date') ?></th>
                                                    <th scope="col"><?php echo xl('Order Case') ?></th>
                                                    <th scope="col"><?php echo xl('Notes') ?></th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php
                                                    while ($getOrderInfo1 = sqlFetchArray($row6)) {

                                                        $action = ListLook($getOrderInfo1['rto_action'],'RTO_Action');

                                                        $r_status = ListLook($getOrderInfo1['rto_status'], 'RTO_Status');
                                                        echo "<tr>"; 
                                                        echo "<td>".$action."</td>"; 
                                                        echo "<td>".$getOrderInfo1['rto_ordered_by']."</td>";
                                                        echo "<td>".$r_status."</td>"; 
                                                        echo "<td>".$getOrderInfo1['rto_resp_user']."</td>"; 
                                                        echo "<td>".$getOrderInfo1['rto_date']."</td>";  
                                                        echo "<td>".$getOrderInfo1['rto_case']."</td>"; 
                                                        echo "<td>".$getOrderInfo1['rto_notes']."</td>"; 
                                                        echo "</tr>"; 
                                                    }  
                                                ?>
                                            </tbody>
                                        </table>
                                        <?php  } else { ?>
                                            <p class="msg"><?php echo xl('No orders pending to schedule') ?></p>
                                        <?php } ?>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </section>

                        <!-- Uncompleted Case Management Action Items -->
                        <section class="card mb-2  ">
                            <div class="card-body p-1">
                                <h6 class="card-title mb-0 d-flex p-1 justify-content-between">
                                    <a class="text-left font-weight-bolder" href="#" data-toggle="collapse" data-target="#" aria-expanded="true" aria-controls=""><?php echo xl('Uncompleted Case Management Action Items') ?></a>
                                </h6>
                                <div id="uncompletedCaseManagementItems" class="card-text collapse show" style="">
                                <div class="clearfix pt-2">
                                    <div class="table-responsive">    
                                    <?php if(sqlNumRows($row7) > 0) {?>
                                        <table class="table table-sm table-striped">
                                            <thead>
                                                <tr>
                                                    <th scope="col"><?php echo xl('Case ID') ?></th>
                                                    <th scope="col"><?php echo xl('Action Item') ?></th>
                                                    <th scope="col"><?php echo xl('Owner') ?></th>
                                                    <th scope="col"><?php echo xl('Status') ?></th>
                                                    <th scope="col"><?php echo xl('Created Date Time') ?></th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php
                                                    while ($frow7 = sqlFetchArray($row7)) {
                                                    $getUnCompletedCases = sqlStatement("select vaid.case_id, vaid.action_item, vaid.owner, vaid.status, vaid.created_datetime from vh_action_items_details vaid where status  = 'pending' and case_id = ? and TIMESTAMP(created_datetime) < now()",$frow7['rto_case']);
                                                    while ($getUnCompletedCasesList1 = sqlFetchArray($getUnCompletedCases)) {
                                                        echo "<tr>"; 
                                                        echo "<td>".$getUnCompletedCasesList1['case_id']."</td>"; 
                                                        echo "<td>".$getUnCompletedCasesList1['action_item']."</td>"; 
                                                        echo "<td>".$getUnCompletedCasesList1['owner']."</td>"; 
                                                        echo "<td>".$getUnCompletedCasesList1['status']."</td>";  
                                                        echo "<td>".$getUnCompletedCasesList1['created_datetime']."</td>"; 
                                                        echo "</tr>"; 
                                                    }
                                                    }  
                                                ?>
                                            </tbody>
                                        </table>
                                        <?php } else { ?>
                                            <p class="msg"><?php echo xl('No uncompleted case management action items') ?></p>
                                        <?php } ?>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </section> 
                    </div>
	            </div>
                <hr/>

	            <script type="text/javascript">
	            	jQuery(document).ready(function() {
	            		fetchsection('#patient_balance_<?php echo $pdata['pid']; ?>', 'patient_balance', <?php echo json_encode(array('pid' => $pdata['pid'])); ?>);
	            		fetchsection('#rehab_plan_<?php echo $pdata['pid']; ?>', 'rehab_plan', <?php echo json_encode(array('pid' => $pdata['pid'])); ?>);
	            		fetchsection('#list_of_payers_<?php echo $pdata['pid']; ?>', 'list_of_payers', <?php echo json_encode(array('pid' => $pdata['pid'])); ?>);
	            	});
	            </script>
        		<?php
        	}

        } else if (sqlNumRows($row8) > 0) {
            $case_list = [];
            $user_org_list = [];

        	while ($udata = sqlFetchArray($row8)) {
        		$getLiabilityPayers = sqlStatement("select det.case_id from vh_pi_case_management_details det,form_cases fc where det.case_id = fc.id and det.field_name ='lp_contact' and det.isActive=1 and det.field_value=?",$udata['id']);

                $user_org_list[] = $udata['organization'];

                //$case_list = array();
        		while ($frow10 = sqlFetchArray($getLiabilityPayers)) {
                    $case_list[] = $frow10['case_id'];
        		}
        	}

            ?>
            <div class="mb-4">
            <!-- User Details -->
            <section class="card mb-2">
                <div class="card-body p-1">
                    <h6 class="card-title mb-0 d-flex p-1 justify-content-between">
                        <a class="text-left font-weight-bolder" href="#" data-toggle="collapse" data-target="#" aria-expanded="true" aria-controls=""><?php echo xl('Caller Details') ?></a>
                    </h6>
                    <div id="patient_details" class="card-text collapse show">
                        <div class="clearfix pt-2">
                            <div class="table-responsive">
                                <table class="table table-sm table-striped">
                                    <thead>
                                        <tr>
                                            <!-- <th scope="col"><?php echo xl('User Name') ?></th> -->
                                            <th scope="col"><?php echo xl('Organization') ?></th>
                                            <!-- <th scope="col"><?php echo xl('Type') ?></th> -->
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php
                                            //while ($getPatientInfo1 = sqlFetchArray($res2)) {
                                                echo "<tr>";
                                                // echo "<td>".$udata['u_name']."</td>"; 
                                                echo "<td>".implode(", ", $user_org_list)."</td>";  
                                                // echo "<td>".$udata['abook_type']."</td>"; 
                                                echo "</tr>"; 
                                            //}  
                                        ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </section>
            <section class="card mb-2">
                <div class="card-body p-1">
                    <h6 class="card-title mb-0 d-flex p-1 justify-content-between">
                        <a class="text-left font-weight-bolder" href="#" data-toggle="collapse" data-target="#" aria-expanded="true" aria-controls=""><?php echo xl('Case Manager Report') ?></a>
                    </h6>
                    <div id="case_manager_report_items_1" class="card-text collapse show" style="">
                        <form id="cmr_iframe_form_1" action="<?php echo $GLOBALS['web_root'] . "/interface/reports/myreports/case_manager_report.php?default_report=1"; ?>" method="post" target="cmr_iframe_1" style="display: none;">
                            <input type="text" name="report_element" value='case_manager_report_items_1' />
                            <input type="text" name="case_list" value='<?php echo json_encode($case_list); ?>' />
                        </form>
                        <iframe name="cmr_iframe_1" src="" style="width:100%; height: 0; border: 0px;"></iframe>
                        <div class="py-3 iframe-loader">
                            <center>
                                <div class="spinner-border" role="status">
                                    <span class="sr-only">Loading...</span>
                                </div>
                            </center>
                        </div>
                    </div>
                </div>
            </section>
            <hr/>
            </div>
            <script type="text/javascript">
                jQuery(document).ready(function() {
                    document.getElementById('cmr_iframe_form_1').submit();
                });
            </script>
            <?php
        } else {
            echo $callernum." Phone Number doesn't match any Patient Data or Liability Payer Data";
        } 
	}
} else if($action == "patient_balance") {
	?>
    <div class="clearfix pt-2">
        <div class="table-responsive">
        	<table class="table table-sm table-striped">
                <thead>
                    <tr>
                        <th scope="col"><?php echo xl('Patient Balance Due') ?></th>
                        <th scope="col"><?php echo xl('Insurance Balance Due') ?></th>
                        <th scope="col"><?php echo xl('Total Balance Due') ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php
        			$pid = $patient_pid;
        			$balanceData = getIdempierePatientBalance($idempiere_connection, $pid);
        			$patient_balance_due = isset($balanceData['patient_balance_due']) ? $balanceData['patient_balance_due'] : 0;
        			$insurance_balance_due = isset($balanceData['insurance_balance_due']) ? $balanceData['insurance_balance_due'] : 0;
        			$total_balance_due = isset($balanceData['total_balance_due']) ? $balanceData['total_balance_due'] : 0;
        			echo "<tr>"; 
        			echo "<td>".number_format(($patient_balance_due), 2, '.', ',')."</td>"; 
        			echo "<td>".number_format(($insurance_balance_due), 2, '.', ',')."</td>";  
        			echo "<td>".number_format(($total_balance_due), 2, '.', ',')."</td>"; 
        			echo "</tr>"; 
                    ?>
                </tbody>
            </table>
        </div>
    </div>
	<?php
} else if($action == "rehab_plan") {
	$row5 = sqlStatement("select DISTINCT id, pid from form_cases where pid = ? order by id desc limit 1",array($patient_pid));
    ?>
    <div class="clearfix pt-2">
    <div class="table-responsive">
    <?php
	if(sqlNumRows($row5) > 0) { ?>
        <table class="table table-sm table-striped">
            <thead>
                <tr>
                    <th scope="col"><?php echo xl('Case Number') ?></th>
                    <th scope="col"><?php echo xl('Rehab Plan') ?></th>
                </tr>
            </thead>
            <tbody>
                <?php
                	while ($getRehabPlan1 = sqlFetchArray($row5)) {
                        $getAllCases = sqlStatement("select rehabplan(?) as rehab_plan",array($getRehabPlan1['id']));
                        $getrehabplan2 = sqlFetchArray($getAllCases);
                        echo "<tr>";
                        echo "<td>".$getRehabPlan1['id']."</td>"; 
                        echo "<td>".$getrehabplan2['rehab_plan']."</td>"; 
                        echo "</tr>";
                    }
                ?>
            </tbody>
        </table>
    <?php } else { ?>
        <p class="msg"><?php echo xl('No rehab plan for this patient') ?></p>
    <?php } ?> 
    </div>
    </div>
    <?php
} else if($action == "list_of_payers") {

    $row44 = sqlStatement("select fc.id, fc.ins_data_id1, fc.ins_data_id2, fc.ins_data_id3 from form_cases fc where fc.pid = ? order by id desc", array($patient_pid));

	//$row4 = sqlStatement("select id.id, id.provider, id.plan_name,id.policy_number,id.group_number, ic.name from insurance_data id left join insurance_companies ic on ic.id = id.provider where id.pid = ? and id.provider != ''",array($patient_pid));

    ?>
    <div class="clearfix pt-2">
    <div class="table-responsive">
    <?php if(sqlNumRows($row44) > 0) { ?>
        <table id="list_of_payers_table_<?php echo $patient_pid ?>" class="table table-sm table-striped list_of_payers_table">
            <thead>
                <th><?php echo xl('Case Number'); ?></th>
            </thead>
            <tbody>
                <?php
                    while ($patientCaseList = sqlFetchArray($row44)) {
                        $insList = array();
                        if(!empty($patientCaseList['ins_data_id1'])) $insList[] = $patientCaseList['ins_data_id1'];
                        if(!empty($patientCaseList['ins_data_id2'])) $insList[] = $patientCaseList['ins_data_id2'];
                        if(!empty($patientCaseList['ins_data_id3'])) $insList[] = $patientCaseList['ins_data_id3'];

                        if(empty($insList)) continue;

                        $row4 = sqlStatement("select id.id, id.provider, id.plan_name,id.policy_number,id.group_number, ic.name from insurance_data id left join insurance_companies ic on ic.id = id.provider where id.pid = ? and id.provider != '' and id.id in ('". implode("','", $insList) ."') ",array($patient_pid));

                        $rehabplanData = sqlStatement("select rehabplan(?) as rehab_plan",array($patientCaseList['id']));
                        $rehabplanRow = sqlFetchArray($rehabplanData);

                        $rehabprogressData = sqlStatement("select rehabplan(?) as rehab_progress",array($patientCaseList['id']));
                        $rehabprogressRow = sqlFetchArray($rehabprogressData);

                        // Rehab Progress
                        $caseManagerData = Caselib::piCaseManagerFormData($patientCaseList['id'], '');
                        $lbfFormDataItems = Caselib::getRehabProgressLBFData(array($patientCaseList['id']));
                        $lbfFormData = isset($lbfFormDataItems['case_' . $patientCaseList['id']]) ? $lbfFormDataItems['case_' . $patientCaseList['id']] : array();

                        if(!empty($lbfFormData)) {
                            $finalDataSet = array(
                                "PT" => isset($lbfFormData['pt']) ? $lbfFormData['pt'] : 0,
                                "LD" => isset($lbfFormData['ld']) ? $lbfFormData['ld'] : 0,
                                "CD" => isset($lbfFormData['cd']) ? $lbfFormData['cd'] : 0,
                                "DD" => isset($lbfFormData['dd']) ? $lbfFormData['dd'] : 0
                            );
                        }

                        $isPiCaseLiable = Caselib::isLiablePiCaseByCase($patientCaseList['id'], $pid);
                        
                        if($isPiCaseLiable === true) {
                            $rehabPlanData = Caselib::getRehabPlanDataByCase($patientCaseList['id'], $caseManagerData);
                            $rehabPlanItems = array();

                            foreach ($rehabPlanData as $rpd => $rpdItem) {
                                $apptCount = isset($finalDataSet[$rpdItem['id']]) ? $finalDataSet[$rpdItem['id']] : 0;

                                $rehabPlanItems[] = $rpdItem['id'] . " " . $apptCount . "/" . $rpdItem['value_sum'];
                            }

                            if(!empty($rehabPlanItems)) {
                                $rehabPlanItems = implode(", ", $rehabPlanItems);
                            } else {
                                $rehabPlanItems = "";
                            }
                        }
                        /* Rehab Plan*/


                        if(sqlNumRows($row4) > 0) {
                            echo "<tr data-key='". attr($patientCaseList['id']) ."' class='details'>"; 
                            echo "<td class='details-control'><a href=\"#!\" onclick=\"handlegotoCase('".$patientCaseList['id']."','".$patient_pid."');\"><b>".$patientCaseList['id']."</b></a></td>";  
                            echo "</tr>";

                            echo "<tr class='rowdetail row-details-" . attr($patientCaseList['id']) . " show'>";
                            echo "<td class='no-padding row-details-tr p-2 mb-2 bg-light'>";

                            //while ($casepayerList = sqlFetchArray($row4)) {

                                ?>
                                 <table class="table table-sm table-striped mb-2">
                                    <thead class="thead-light">
                                        <tr>
                                            <th scope="col"><?php echo xl('Payer Name') ?></th>
                                            <th scope="col"><?php echo xl('Plan Name') ?></th>
                                            <th scope="col"><?php echo xl('Policy Number') ?></th>
                                            <th scope="col"><?php echo xl('Group Number') ?></th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php
                                            while ($getPayersAssociatedWithPatient1 = sqlFetchArray($row4)) {
                                                echo "<tr class='bg-white'>"; 
                                                echo "<td>".$getPayersAssociatedWithPatient1['name']."</td>";  
                                                echo "<td>".$getPayersAssociatedWithPatient1['plan_name']."</td>"; 
                                                echo "<td>".$getPayersAssociatedWithPatient1['policy_number']. "</td>";  
                                                echo "<td>".$getPayersAssociatedWithPatient1['group_number']."</td>"; 
                                                echo "</tr>"; 
                                            }
                                        ?>
                                    </tbody>
                                </table>

                                <table class='row_details_table table table-sm table-borderless mb-2'>
                                    <tbody>
                                        <tr>
                                            <td class='p-0' width='100'><span><?php echo xl('Rehab Plan'); ?>:</span></td>
                                            <td class='p-0'><span><?php echo !empty($rehabplanRow['rehab_plan']) ? $rehabplanRow['rehab_plan'] : "<i>Empty</i>"; ?></span></td>

                                            <td class='p-0' width='130'><span><?php echo xl('Rehab Progress'); ?>:</span></td>
                                            <td class='p-0'><span><?php echo isset($rehabPlanItems) ? $rehabPlanItems : "<i>Empty</i>"; ?></span></td>
                                        </tr>
                                    </tbody>
                                </table>

                                <?php

                            echo "</td>";
                            echo "</tr>"; 
                        }
                    }
                ?>
            </tbody>
        </table>
    <?php } ?>
    </div>
    </div>
    <?php
}

exit();
?>