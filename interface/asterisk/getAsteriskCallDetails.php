<?php
   exit();
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

    $am_host = $GLOBALS['asterisk_manager_host'];
    $am_user = $GLOBALS['asterisk_manager_user'];
    $am_pass = $cryptoGen->decryptStandard($GLOBALS['asterisk_manager_password']);

    $socket = fsockopen($am_host,"5038", $errno, $errstr, $timeout);

    fputs($socket, "Action: Login\r\n");
    fputs($socket, "UserName: $am_user\r\n");
    fputs($socket, "Secret: $am_pass\r\n\r\n");
    fputs($socket, "Action: CoreShowChannels\r\n\r\n");
    fputs($socket, "Action: Logoff\r\n\r\n");

    $wrets = '';
    $callerdetails1 = [];

    if (!is_resource($socket)) exit();

    while (!feof($socket)) {
        $wrets .= fread($socket, 4096);
    }
    fclose($socket);

    $extension = sqlQuery("SELECT `extension` FROM `user_extension` WHERE `username` = ? ORDER BY id DESC LIMIT 1", array($_SESSION['authUser']));
    $channels = explode("\r\n\r\n", $wrets);

    foreach ($channels as $channel) {
    // Split each channel's details into an associative array
        $channelData = [];
        $lines = explode("\r\n", $channel);
        foreach ($lines as $line) {
            $parts = explode(':', $line, 2);
            if (count($parts) === 2) {
                $channelData[$parts[0]] = trim($parts[1]);
            }
        }
        if (isset($channelData['Channel'])) {
            if($channelData['ChannelStateDesc'] == 'Up' && $channelData['CallerIDNum'] == $extension['extension']) {
                ?>
                <script type="text/javascript">console.log("Working")</script>
                <?php
                // @VH - Asterisk Change
                $callerdetails1[] = $channelData;
                //End
            }
        }
    }
    // @VH - Asterisk Change
    $callerdetails = array_unique($callerdetails1);
    // End 

    $availability = sqlQuery("SELECT `availability` FROM `user_extension` WHERE `username` = ? ORDER BY id DESC LIMIT 1", array($_SESSION['authUser']));
    if($availability['availability'] == "Available") { 
        if($callerdetails) {
            foreach($callerdetails as $callsonext) {
                $callernum = $callsonext['ConnectedLineNum'];
                $countryCode = '+1';
                $len = strlen($countryCode);
                $callerNumWithoutCountryCode ="";
                if(substr($callernum, 0, $len) === $countryCode) {
                    $callerNumWithoutCountryCode = preg_replace('/^\+?1|\|1|\D/', '', $callernum, 1);
                }
                $res2 = sqlStatement("select pid, phone_cell from patient_data pd where phone_cell = ? or phone_cell = ? order by id desc",array($callernum, $callerNumWithoutCountryCode));
                if (sqlNumRows($res2)) {
                    $getPatientInfo = $getMostRecentAppt = $getNearestUpcomingAppt = $getPayersAssociatedWithPatient = $getRehabPlan = $getOrderInfo = $getUnCompletedCasesList = $getListOfLiabilityPayers = $getCancelledAppt = [];
                    while ($frow3 = sqlFetchArray($res2)) {
                        $getIncomingCall[] = $frow3;
                        $pid = $frow3['pid'];
                        // print_r($pid);
                        ?>
                       
                        <?php
                        $row1 = sqlStatement("select pid, CONCAT(CONCAT_WS(' ', IF(LENGTH(pd.fname),pd.fname,NULL), IF(LENGTH(pd.lname),pd.lname,NULL)), ' (', pd.pubpid ,')') as patient_name, DOB as date_of_birth, alert_info from patient_data pd where pid = ? order by id desc",array($frow3['pid']));
                        $row2 = sqlStatement("select TIMESTAMP(ope.pc_eventDate, ope.pc_startTime) as event_date_time, ope.pc_aid as provider_id, u.fname as provider_fname, u.mname as provider_mname, u.lname as provider_lname, CONCAT(CONCAT_WS(' ', IF(LENGTH(u.fname),u.fname,NULL), IF(LENGTH(u.lname),u.lname,NULL))) as provider_full_name, opc.pc_catname, lo.title as reason from openemr_postcalendar_events ope left join openemr_postcalendar_categories opc on opc.pc_catid = ope.pc_catid left join users u on u.id = ope.pc_aid left join list_options lo on lo.list_id = 'apptstat' and lo.option_id = ope.pc_apptstatus where ope.pc_pid = ? and TIMESTAMP(ope.pc_eventDate, ope.pc_startTime) < now() order by TIMESTAMP(ope.pc_eventDate, ope.pc_startTime)",array($frow3['pid']));
                        $row3 = sqlStatement("select TIMESTAMP(ope.pc_eventDate, ope.pc_startTime) as event_date_time, ope.pc_aid as provider_id, u.fname as provider_fname, u.mname as provider_mname, u.lname as provider_lname, CONCAT(CONCAT_WS(' ', IF(LENGTH(u.fname),u.fname,NULL), IF(LENGTH(u.lname),u.lname,NULL))) as provider_full_name, opc.pc_catname, lo.title as reason  from openemr_postcalendar_events ope left join openemr_postcalendar_categories opc on opc.pc_catid = ope.pc_catid left join users u on u.id = ope.pc_aid left join list_options lo on lo.list_id = 'apptstat' and lo.option_id = ope.pc_apptstatus where ope.pc_pid = ? and TIMESTAMP(ope.pc_eventDate, ope.pc_startTime) > now() order by TIMESTAMP(ope.pc_eventDate, ope.pc_startTime)",array($frow3['pid']));
                        $row4 = sqlStatement("select id.*, ic.name from insurance_data id left join insurance_companies ic on ic.id = id.provider where id.pid = ? and id.provider != ''",array($frow3['pid']));
                        
                        // @VH - Asterisk Change
                        $row5 = sqlStatement("select DISTINCT id, pid from form_cases where pid = ?",array($frow3['pid']));
                        // $row5 = sqlStatement("select DISTINCT pid, rto_case from form_rto where pid = ? and rto_case IS NOT NULL AND rto_case != '' order by rto_case",array($frow3['pid']));
                        $row6 = sqlStatement("select * from form_rto where pid = ? and rto_status not in ('x', 'sc85') order by id",array($frow3['pid']));
                        $row7 = sqlStatement("select DISTINCT pid, rto_case from form_rto where pid = ? and rto_case IS NOT NULL AND rto_case != '' order by rto_case",array($frow3['pid']));
                        $row8 = sqlStatement("select id FROM users where phone = ? or fax=? or phonew1=? or phonew2=? or phonecell=?",array($callernum, $callernum, $callernum, $callernum, $callernum));
                        $row11 = sqlStatement("select TIMESTAMP(ope.pc_eventDate, ope.pc_startTime) as event_date_time, ope.pc_aid as provider_id, u.fname as provider_fname, u.mname as provider_mname, u.lname as provider_lname, CONCAT(CONCAT_WS(' ', IF(LENGTH(u.fname),u.fname,NULL), IF(LENGTH(u.lname),u.lname,NULL))) as provider_full_name, opc.pc_catname, lo.title as reason  from openemr_postcalendar_events ope left join openemr_postcalendar_categories opc on opc.pc_catid = ope.pc_catid left join users u on u.id = ope.pc_aid left join list_options lo on lo.list_id = 'apptstat' and lo.option_id = ope.pc_apptstatus where ope.pc_pid = ".$frow3['pid']." and ope.pc_apptstatus in ('%','x','?') and TIMESTAMP(ope.pc_eventDate, ope.pc_startTime) < now() order by TIMESTAMP(ope.pc_eventDate, ope.pc_startTime)");
                        //End

                        while ($frow1 = sqlFetchArray($row1)) {
                            $getPatientInfo[] = $frow1;
                        }
                        while ($frow2 = sqlFetchArray($row2)) {
                            $getMostRecentAppt[] = $frow2;
                        }
                        while ($frow3_1 = sqlFetchArray($row3)) {
                            $getNearestUpcomingAppt[] = $frow3_1;
                        }
                        while ($frow4 = sqlFetchArray($row4)) {
                            $getPayersAssociatedWithPatient[] = $frow4;
                        }

                        // @VH - Asterisk Change
                        while ($frow5 = sqlFetchArray($row5)) { 
                            // print_r($frow5);
                            $getRehabPlan[] = $frow5;
                        }
                        while ($frow6 = sqlFetchArray($row6)) {
                            $getOrderInfo[] = $frow6;
                        }
                        while ($frow7 = sqlFetchArray($row7)) {
                            $getUnCompletedCases = sqlStatement("select * from vh_action_items_details vaid where status  = 'pending' and case_id = ? and TIMESTAMP(created_datetime) < now()",$frow7['rto_case']);
                            while ($frow8 = sqlFetchArray($getUnCompletedCases)) {
                                $getUnCompletedCasesList[] = $frow8;
                            }
                        }
                        while ($frow9 = sqlFetchArray($row8)) {
                            $getLiabilityPayers = sqlStatement("select * from vh_pi_case_management_details det,form_cases fc where det.case_id = fc.id and det.field_name ='lp_contact' and det.isActive=1 and det.field_value=?",$frow9['id']);
                            while ($frow10 = sqlFetchArray($getLiabilityPayers)) {
                                $getListOfLiabilityPayers[] = $frow10;
                            }
                        }
                        while ($frow11 = sqlFetchArray($row11)) {
                            $getCancelledAppt[] = $frow11;
                        }
                        //End 
                    }
                }
                else {
                    echo $callernum." Phone Number doesn’t match any Patient Data";
                } 
            }
            if($getPatientInfo != NULL) {
            ?>
                <div class="tables">
                    <div class= "p1">
                        <section class="card mb-2  ">
                            <div class="card-body p-1">
                                <h6 class="card-title mb-0 d-flex p-1 justify-content-between">
                                    <a class="text-left font-weight-bolder" href="#" data-toggle="collapse" data-target="#" aria-expanded="true" aria-controls="">Patient Details</a>
                                </h6>
                                <div id="patient_details" class="card-text collapse show" style="">
                                    <table class="text table msg-table tableRowHighLight dataTable no-footer m-0">
                                        <thead class="thead-dark">
                                            <tr>
                                                <th scope="col">Patient Name</th>
                                                <th scope="col">Date of Birth</th>
                                                <th scope="col">Alert Info</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <tr>
                                            <?php
                                                foreach($getPatientInfo as $getPatientInfo1) {
                                                    $getPatientid= $getPatientInfo1['pid'];
                                                    echo "<td ><a href='javascript:goParentPid($getPatientid)'>".$getPatientInfo1['patient_name']."</a></td>"; 
                                                    echo "<td>".$getPatientInfo1['date_of_birth']."</td>";  
                                                    echo "<td>".$getPatientInfo1['alert_info']."</td>"; 
                                                    echo "</tr>"; 
                                                }  
                                            ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </section>  
                        <section class="card mb-2  ">
                            <div class="card-body p-1">
                                <h6 class="card-title mb-0 d-flex p-1 justify-content-between">
                                    <a class="text-left font-weight-bolder" href="#" data-toggle="collapse" data-target="#" aria-expanded="true" aria-controls="">Most Recent Appointment</a>
                                </h6>
                                <div id="most_recent_appt" class="card-text collapse show" style="">
                                    <?php if($getMostRecentAppt != NULL) {?>
                                        <table class="text table msg-table tableRowHighLight dataTable no-footer m-0">
                                            <thead class="thead-dark">
                                                <tr>
                                                    <th scope="col">Event Date Time</th>
                                                    <th scope="col">Provider ID</th>
                                                    <th scope="col">Provider Name</th>
                                                    <th scope="col">Appt Category</th>
                                                    <th scope="col">Reason</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <tr>
                                                <?php
                                                    foreach($getMostRecentAppt as $getMostRecentAppt1) {
                                                        echo "<td>".text(oeFormatDateTime($getMostRecentAppt1['event_date_time'], "global", true))."</td>"; 
                                                        echo "<td>".$getMostRecentAppt1['provider_id']."</td>";  
                                                        echo "<td>".$getMostRecentAppt1['provider_full_name']."</td>"; 
                                                        echo "<td>".$getMostRecentAppt1['pc_catname']. "</td>";  
                                                        echo "<td>".$getMostRecentAppt1['reason']."</td>"; 
                                                        echo "</tr>";
                                                    }
                                                ?>
                                            </tbody>
                                        </table>
                                        <?php
                                        }
                                        else {
                                        ?>
                                        <p class="msg"><?php echo "No most recent appt";?></p>
                                    <?php } ?>
                                </div>
                            </div>
                        </section>
                        <section class="card mb-2  ">
                            <div class="card-body p-1">
                                <h6 class="card-title mb-0 d-flex p-1 justify-content-between">
                                    <a class="text-left font-weight-bolder" href="#" data-toggle="collapse" data-target="#" aria-expanded="true" aria-controls="">Nearest Upcoming Appointment</a>
                                </h6>
                                <div id="nearest_upcoming_appt" class="card-text collapse show" style="">
                                    <?php if($getNearestUpcomingAppt != NULL) {?>
                                        <table class="text table msg-table tableRowHighLight dataTable no-footer m-0">
                                            <thead class="thead-dark">
                                                <tr>
                                                    <th scope="col">Event Date Time</th>
                                                    <th scope="col">Provider ID</th>
                                                    <th scope="col">Provider Name</th>
                                                    <th scope="col">Appt Category</th>
                                                    <th scope="col">Reason</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <tr>
                                                <?php
                                                    foreach($getNearestUpcomingAppt as $getNearestUpcomingAppt1) {
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
                                        <?php
                                    }
                                    else {
                                    ?>
                                        <p class="msg"><?php echo "No nearest upcoming appt";?></p>
                                    <?php } ?>
                                </div>
                            </div>
                        </section> 
                        <section class="card mb-2  ">
                            <div class="card-body p-1">
                                <h6 class="card-title mb-0 d-flex p-1 justify-content-between">
                                    <a class="text-left font-weight-bolder" href="#" data-toggle="collapse" data-target="#" aria-expanded="true" aria-controls="">Current Patient Balances</a>
                                </h6>
                                <div id="patient_balance" class="card-text collapse show" style="">
                                    <table class="text table msg-table tableRowHighLight dataTable no-footer m-0">
                                        <thead class="thead-dark">
                                            <tr>
                                                <th scope="col">Patient Balance Due</th>
                                                <th scope="col">Insurance Balance Due</th>
                                                <th scope="col">Total Balance Due</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <tr>
                                            <?php
                                                foreach($getIncomingCall as $incomingCall) {
                                                    $pid = $incomingCall['pid'];
                                                    $balanceData = getIdempierePatientBalance($idempiere_connection, $pid);
                                                    $patient_balance_due = isset($balanceData['patient_balance_due']) ? $balanceData['patient_balance_due'] : 0;
                                                    $insurance_balance_due = isset($balanceData['insurance_balance_due']) ? $balanceData['insurance_balance_due'] : 0;
                                                    $total_balance_due = isset($balanceData['total_balance_due']) ? $balanceData['total_balance_due'] : 0;
                                                    echo "<td>".$patient_balance_due."</td>"; 
                                                    echo "<td>".$insurance_balance_due."</td>";  
                                                    echo "<td>".$total_balance_due."</td>"; 
                                                    echo "</tr>"; 
                                                }
                                            ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </section>  

                        <!-- OEMR - Asterisk Change -->
                        <section class="card mb-2  ">
                            <div class="card-body p-1">
                                <h6 class="card-title mb-0 d-flex p-1 justify-content-between">
                                    <a class="text-left font-weight-bolder" href="#" data-toggle="collapse" data-target="#" aria-expanded="true" aria-controls="">Rehab Plan</a>
                                </h6>
                                <div id="rehab_plan" class="card-text collapse show" style="">
                                    <?php if($getRehabPlan != NULL) {?>
                                        <table class="text table msg-table tableRowHighLight dataTable no-footer m-0">
                                            <thead class="thead-dark">
                                                <tr>
                                                    <th scope="col">Case Number</th>
                                                    <th scope="col">Rehab Plan</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <tr>
                                                <?php
                                                     foreach( $getRehabPlan as  $getRehabPlan1) {
                                                        $getAllCases = sqlStatement("select rehabplan(?) as rehab_plan",array($getRehabPlan1['id']));
                                                        $getrehabplan2 = sqlFetchArray($getAllCases);
                                                        echo "<td>".$getRehabPlan1['id']."</td>"; 
                                                        echo "<td>".$getrehabplan2['rehab_plan']."</td>"; 
                                                        echo "</tr>";
                                                    }
                                                ?>
                                            </tbody>
                                        </table>
                                        <?php
                                    }
                                    else {
                                    ?>
                                        <p class="msg"><?php echo "No rehab plan for this patient";?></p>
                                    <?php } ?>
                                </div>
                            </div>
                        </section>  
                        <section class="card mb-2  ">
                            <div class="card-body p-1">
                                <h6 class="card-title mb-0 d-flex p-1 justify-content-between">
                                    <a class="text-left font-weight-bolder" href="#" data-toggle="collapse" data-target="#" aria-expanded="true" aria-controls="">Liability Payers</a>
                                </h6>
                                <div id="getListofLiabilityPayers" class="card-text collapse show" style="">
                                    <?php if($getListOfLiabilityPayers != NULL) {?>
                                        <div id="case_manager_report_container" class="table-responsive">
                                        <table id='case_manager_report' class='text table table-sm dataTable no-footer' style="width: 100%;">
                                        <thead class="thead-light">
                                            <tr class="dt-hasChild">
                                                <!-- <th></th> -->
                                                <th>Case Number</th>
                                                <th>Date 1st visit</th>
                                                <th>Date of injury</th>
                                                <th>Patient Name</th>
                                            </tr>
                                        </thead>
                                        <tbody>       
                                       <?php
                                        foreach( $getListOfLiabilityPayers as  $getListOfLiabilityPayers1) {                                                            
                                            $rehab_process = sqlStatement("SELECT fc.id as case_id, (select min(ope.pc_eventDate) from openemr_postcalendar_events ope where ope.pc_case = fc.id and ope.pc_apptstatus not in ('-','+','?','x','%') and ope.pc_pid = fc.pid) as first_visit_date, fc.injury_date,  CONCAT(CONCAT_WS(' ', IF(LENGTH(pd.fname),pd.fname,NULL), IF(LENGTH(pd.lname),pd.lname,NULL)), ' (', pd.pubpid ,')') as patient_name, fc.ins_data_id1, fc.ins_data_id2, fc.ins_data_id3, fc.pid, fc.comments, fc.bc_notes from form_cases fc
                                                left join patient_data pd on pd.pid = fc.pid
                                                join vh_pi_case_management_details vpcmd on vpcmd.case_id = fc.id and vpcmd.field_name = 'case_manager' and vpcmd.field_index = 0 and vpcmd.isActive = 1 
                                                left join insurance_data id1 on id1.id = fc.ins_data_id1 left join insurance_data id2 on id2.id = fc.ins_data_id2 left join insurance_data id3 on id3.id = fc.ins_data_id3
                                                left join insurance_companies ic1 on ic1.id = id1.provider left join insurance_companies ic2 on ic2.id = id2.provider left join insurance_companies ic3 on ic3.id = id3.provider where fc.id=".$getListOfLiabilityPayers1['case_id']." ");
                                            $rehab_process1 = sqlFetchArray( $rehab_process);
                                            $lbfFormDataItems = Caselib::getRehabProgressLBFData($rehab_process1['case_id']);
                                           
                                            if(isset($lbfFormDataItems['case_'.$rehab_process1['case_id']])) {
                                                $rehab_process1['lbf_data'] = $lbfFormDataItems['case_'.$rehab_process1['case_id']];
                                            }
                                            $codeCountDataItems = getCodeCountData($rehab_process1['case_id']);
                                            $codeCountDataCaseItems = $codeCountDataItems['case_'.$rehab_process1['case_id']];
                                            $caseManagerData = Caselib::piCaseManagerFormData($rehab_process1['case_id'], '');
                                            $isPiCaseLiable = Caselib::isLiablePiCaseByCase($rehab_process1['case_id'], $rehab_process1['pid'], $rehab_process1);
                                        
                                            foreach ($codeCountDataCaseItems as $cki => $codeCountDataItem) {
                                                if(isset($codeCountDataItem['code_type'])) {
                                                    if($codeCountDataItem['code_type'] == "CPT4") {
                                                        // print_r($codeCountDataItem);
                                                        $rehab_process1['xray_data'] = $codeCountDataItem;
                                                    }
                                        
                                                    if($codeCountDataItem['code_type'] == "HCPCS") {
                                                        $rehab_process1['tens_data'] = $codeCountDataItem;
                                                    }
                                                }
                                            }
                                            $case_id = "<a href=\"#!\" onclick=\"handlegotoCase('".$rehab_process1['case_id']."','".$rehab_process1['pid']."');\">". $rehab_process1['case_id'] . "</a>";
                                            $bcNotesStatusListClass = array(
                                                'reque_22144' => 'text_red',
                                                'affys_only' => 'text_red',
                                                'stil_treat' => 'text_red',
                                                'mis_note' => 'text_red',
                                                'pend_pymt' => 'text_red',
                                                'in_audit' => 'text_red',
                                                'Sent113' => 'text_green',
                                                'Updated' => 'text_green',
                                                'Interim' => 'text_green'
                                            );
                                        
                                            $bc_notes_val = isset($rehab_process1['bc_notes']) ? $rehab_process1['bc_notes'] : "";
                                            $tooltip_html = "";
                                            $req_class = "text_blue";
                                        
                                            if(!empty($bc_notes_val)) {
                                                $nq_filter = ' AND option_id = "'.$bc_notes_val.'"';
                                                $listOptions = LoadList('Case_Billing_Notes', 'active', 'seq', '', $nq_filter);
                                                $req_class = (isset($bcNotesStatusListClass[$bc_notes_val])) ? $bcNotesStatusListClass[$bc_notes_val] : 'text_blue';
                                                if(!empty($listOptions)) {
                                                    $bc_option_title = $listOptions[0] && isset($listOptions[0]['title']) ? $listOptions[0]['title'] : "";
                                                    $tooltip_html = $bc_option_title;
                                                }
                                            }
                                        
                                            if(!empty($tooltip_html)) {
                                                $patient_name = "<a href=\"#!\" class='$req_class' onclick=\"goParentPid('".$rehab_process1['pid']."');\"><span data-toggle='tooltip' class='tooltip_text $req_class' title=''>". $rehab_process1['patient_name'] . "<div class='hidden_content'style='display:none;'>".$tooltip_html."</div></span></a>";
                                            } else {
                                                $patient_name = "<a href=\"#!\" class='linktext $req_class' onclick=\"goParentPid('".$rehab_process1['pid']."');\">". $rehab_process1['patient_name'] . "</a>";
                                            }
                                            $insIds = array();
                                            $law_firm = array();
                                        
                                            for ($ins_i=1; $ins_i <= 3; $ins_i++) { 
                                                if(isset($rehab_process1['ins_data_id'.$ins_i]) && $rehab_process1['ins_data_id'.$ins_i] != "") {
                                                    $insIds[] = $rehab_process1['ins_data_id'.$ins_i];
                                                }
                                            }
                                        
                                            $liableInsList = Caselib::getLiableInsData($insIds, $rehab_process1['pid']);
                                        
                                            if(isset($liableInsList)) {
                                                foreach ($liableInsList as $lk => $lItem) {
                                                    $law_firm[] = "<span>".$lItem['name']."</span>";
                                                }
                                            }
                                        
                                            if(!empty($law_firm)) {
                                                $law_firm = implode(", ", $law_firm);
                                            } else {
                                                $law_firm = "";
                                            }
                                            $next_appt = array();
                                        
                                            $nextAppts = Caselib::getFutureAppt($rehab_process1['case_id'], $rehab_process1['pid']);
                                        
                                            if(isset($nextAppts)) {
                                                foreach ($nextAppts as $nak => $naItem) {
                                                    $next_appt_time = isset($naItem['event_date_time']) ? date('m/d',strtotime($naItem['event_date_time'])) : "";
                                                    $next_appt_provider_name = "";
                                        
                                                    if(isset($naItem['provider_fname']) && !empty($naItem['provider_fname'])) {
                                                        $next_appt_provider_name .= ucfirst(substr($naItem['provider_fname'], 0, 1));
                                                    }
                                        
                                                    if(isset($naItem['provider_mname']) && !empty($naItem['provider_mname'])) {
                                                        $next_appt_provider_name .= ucfirst(substr($naItem['provider_mname'], 0, 1));
                                                    }
                                        
                                                    if(isset($naItem['provider_lname']) && !empty($naItem['provider_lname'])) {
                                                        $next_appt_provider_name .= ucfirst(substr($naItem['provider_lname'], 0, 1));
                                                    }
                                        
                                                    $next_appt[] = "<a href=\"#!\" onclick=\"oldEvt('".$naItem['pc_eid']."');\">".$next_appt_provider_name. " " . $next_appt_time ."</a>";
                                                }
                                            }
                                        
                                            if(!empty($next_appt)) {
                                                $next_appt = implode(", ", $next_appt);
                                            } else {
                                                $next_appt = "";
                                            }
                                        
                                            $cancelled_appt = array();
                                        
                                            $prevCanceledAppts = Caselib::getPreviousCanceledAppt($rehab_process1['case_id'], $rehab_process1['pid']);
                                        
                                            if(isset($prevCanceledAppts)) {
                                                foreach ($prevCanceledAppts as $nak => $naItem) {
                                                    $prev_appt_time = isset($naItem['event_date_time']) ? date('m/d',strtotime($naItem['event_date_time'])) : "";
                                                    $prev_appt_provider_name = "";
                                                    $tooltip_html = "";
                                        
                                                    if(isset($naItem['provider_fname']) && !empty($naItem['provider_fname'])) {
                                                        $prev_appt_provider_name .= ucfirst(substr($naItem['provider_fname'], 0, 1));
                                                    }
                                        
                                                    if(isset($naItem['provider_mname']) && !empty($naItem['provider_mname'])) {
                                                        $prev_appt_provider_name .= ucfirst(substr($naItem['provider_mname'], 0, 1));
                                                    }
                                        
                                                    if(isset($naItem['provider_lname']) && !empty($naItem['provider_lname'])) {
                                                        $prev_appt_provider_name .= ucfirst(substr($naItem['provider_lname'], 0, 1));
                                                    }
                                        
                                                    $apptStatus = "";
                                                    if(isset($naItem['pc_apptstatus']) && !empty($naItem['pc_apptstatus'])) {
                                                        $apptStatus = Caselib::ListLook($naItem['pc_apptstatus'],'apptstat');
                                                    }
                                        
                                                    $tooltip_html .= "<div><span><b>Status</b>: ".$apptStatus."</span></div>";
                                        
                                                    $cancelled_appt[] = "<a href=\"#!\" onclick=\"oldEvt('".$naItem['pc_eid']."');\"><span data-toggle='tooltip' class='tooltip_text' title=''>".$prev_appt_provider_name. " " . $prev_appt_time ."<div class='hidden_content'style='display:none;'>".$tooltip_html."</div></span></a>";
                                                }
                                            }
                                        
                                            if(!empty($cancelled_appt)) {
                                                $cancelled_appt = implode(", ", $cancelled_appt);
                                            } else {
                                                $cancelled_appt = "";
                                            }
                                            $medical = "";
                                        
                                            $medicalData = Caselib::getMedicalDataOfCase($rehab_process1['case_id']);
                                        
                                            if(!empty($medicalData)) {
                                                $next_appt_time = isset($medicalData['event_date_time']) ? date('m/d',strtotime($medicalData['event_date_time'])) : "";
                                                $next_appt_provider_name = "";
                                        
                                                if(isset($medicalData['provider_fname']) && !empty($medicalData['provider_fname'])) {
                                                    $next_appt_provider_name .= ucfirst(substr($medicalData['provider_fname'], 0, 1));
                                                }
                                        
                                                if(isset($medicalData['provider_mname']) && !empty($medicalData['provider_mname'])) {
                                                    $next_appt_provider_name .= ucfirst(substr($medicalData['provider_mname'], 0, 1));
                                                }
                                        
                                                if(isset($medicalData['provider_lname']) && !empty($medicalData['provider_lname'])) {
                                                    $next_appt_provider_name .= ucfirst(substr($medicalData['provider_lname'], 0, 1));
                                                }
                                        
                                                $medical = "<a href=\"#!\" onclick=\"oldEvt('".$medicalData['pc_eid']."');\">".$next_appt_provider_name. " " . $next_appt_time ."</a>";
                                            } else {
                                                $medical = 'N';
                                            }
                                            $xray = "";
                                        
                                            $isApptsAvailable = isset($rehab_process1['xray_data']) ? $rehab_process1['xray_data'] : array('total_count' => 0);
                                            $xray = ($isApptsAvailable !== false && $isApptsAvailable['total_count'] > 0) ? 'Y' : 'N';
                                            $tens = "";
                                        
                                            $isApptsAvailable1 = isset($rehab_process1['tens_data']) ? $rehab_process1['tens_data'] : array('total_count' => 0);
                                            $tens = ($isApptsAvailable1 !== false && $isApptsAvailable1['total_count'] > 0) ? 'Y' : 'N';
                                            $rehabplan = "";
                                        
                                            if($caseManagerData && $isPiCaseLiable === true) {
                                                $oldFieldValue = array();
                                        
                                                if(isset($caseManagerData['tmp_rehab_field_1']) && isset($caseManagerData['tmp_rehab_field_2'])) {
                                                    $oldR1Field = $caseManagerData['tmp_rehab_field_1'];
                                                    $oldR2Field = $caseManagerData['tmp_rehab_field_2'];
                                        
                                                    for ($old_i=0; $old_i < count($oldR1Field); $old_i++) { 
                                                        $oldFieldValue[] = $oldR1Field[$old_i] ."". $oldR2Field[$old_i];
                                                    }
                                                }
                                                $rehabplan = !empty($oldFieldValue) ? getHtmlString(implode(", ", $oldFieldValue)) : "";
                                            }
                                            $rehabprogress = array();
                                            $finalDataSet = array();
                                        
                                            $lbfFormData = isset($rehab_process1['lbf_data']) ? $rehab_process1['lbf_data'] : array();
                                            if(!empty($lbfFormData)) {
                                                $finalDataSet = array(
                                                    "PT" => isset($lbfFormData['pt']) ? $lbfFormData['pt'] : 0,
                                                    "LD" => isset($lbfFormData['ld']) ? $lbfFormData['ld'] : 0,
                                                    "CD" => isset($lbfFormData['cd']) ? $lbfFormData['cd'] : 0,
                                                    "DD" => isset($lbfFormData['dd']) ? $lbfFormData['dd'] : 0
                                                );
                                            }
                                        
                                            if($isPiCaseLiable === true) {
                                                $rehabPlanData = Caselib::getRehabPlanDataByCase($rehab_process1['case_id'], $caseManagerData);
                                                foreach ($rehabPlanData as $rpd => $rpdItem) {
                                                    $apptCount = isset($finalDataSet[$rpdItem['id']]) ? $finalDataSet[$rpdItem['id']] : 0;
                                                    $rehabprogress[] = $rpdItem['id'] . " " . $apptCount . "/" . $rpdItem['value_sum'];
                                                }
                                            }
                                        
                                            if(!empty( $rehabprogress)) {
                                                $rehabprogress = implode(", ",  $rehabprogress);
                                            } else {
                                                $rehabprogress = "";
                                            }
                                        
                                            $orders = array();
                                        
                                            $ordersData = Caselib::getOrdesByCase($rehab_process1['case_id']);
                                        
                                            $orderStatusListClass = array(
                                                'p' => 'text_red',
                                                'misinfo' => 'text_red',
                                                'pendins' => 'text_red',
                                                'pendpi' => 'text_red',
                                                'UCRijw$' => 'text_red',
                                                'ssss' => 'text_green',
                                                'ssss142' => 'text_green',
                                                's' => 'text_green',
                                                'c' => 'text_green',
                                                'deny' => 'text_black',
                                                'x' => 'text_black',
                                                'Patdec' => 'text_black',
                                                'PU88935' => 'text_black',
                                            );
                                        
                                            if(isset($ordersData)) {
                                                foreach ($ordersData as $odk => $odItem) {
                                                    $rto_action_title = isset($odItem['rto_action_title']) ? $odItem['rto_action_title'] : "";
                                                    $rto_date = (isset($odItem['date']) && !empty($odItem['date'])) ? date('m/d/Y', strtotime($odItem['date'])) : "";
                                                    $rto_status = isset($odItem['rto_status']) ? $odItem['rto_status'] : "";
                                                    $rto_class = (isset($orderStatusListClass[$rto_status])) ? $orderStatusListClass[$rto_status] : '';
                                                    $rto_status_title = isset($odItem['rto_status_title']) ? $odItem['rto_status_title'] : "";
                                                    $tooltip_html = "";
                                        
                                                    $patientData = getPatientData($odItem['pid'], "fname, mname, lname, pubpid, billing_note, DATE_FORMAT(DOB,'%Y-%m-%d') as DOB_YMD");
                                                    $patientName = addslashes(htmlspecialchars($patientData['fname'] . ' ' . $patientData['lname']));
                                                    $patientDOB = xl('DOB') . ': ' . addslashes(oeFormatShortDate($patientData['DOB_YMD'])) . ' ' . xl('Age') . ': ' . getPatientAge($patientData['DOB_YMD']);
                                                    $patientPubpid = $patientData['pubpid'];
                                        
                                                    if($rto_status_title != "") {
                                                        $tooltip_html .= "<div><span><b>Status</b>: ".$rto_status_title."</span></div>";
                                                    }
                                                    ob_start();
                                                    getRTOSummary($odItem['id'], $odItem['pid'], $odItem);
                                                    $orderSummaryHtml = ob_get_clean();
                                        
                                                    if($orderSummaryHtml != "") {
                                                        $tooltip_html .= "<div><b>Summary</b>: ".$orderSummaryHtml."</div>";
                                                    }
                                        
                                                    $orders[] = "<a href=\"#!\" onclick=\"handleGoToOrder('".$odItem['id']."','".$odItem['pid']."','".$patientPubpid."','".$patientName."','".$patientDOB."')\"><span data-toggle='tooltip' class='$rto_class tooltip_text' title=''>".$rto_action_title." ".$rto_date."<div class='hidden_content'style='display:none;'>".$tooltip_html."</div></span></a>";
                                                }
                                                
                                            }
                                        
                                            if(!empty($orders)) {
                                                $orders = implode(", ", $orders);
                                            } else {
                                                $orders = "";
                                            }
                                            $notes = isset($rehab_process1['comments']) ? $rehab_process1['comments'] : "";
                                            $data15k = Caselib::get15kThresholdData($rehab_process1['case_id']);
                                            $thresold = isset($data15k['reported_date_whencrossed15k']) ? $data15k['reported_date_whencrossed15k'] : "";
                                    
                                            $action_items = "";
                                        
                                            $aiItem = array();
                                            if(!empty($rehab_process1['case_id'])) {
                                                $aiResult = sqlStatement("SELECT * from vh_action_items_details where case_id = ? and status = 'pending' order by id asc", $rehab_process1['case_id']);
                                                while ($airow = sqlFetchArray($aiResult)) {
                                                    $ai_action_item = isset($airow['action_item']) ? $airow['action_item'] : "Empty";
                                                    $ai_owner = isset($airow['owner']) ? $airow['owner'] : "Empty";
                                                    $ai_status = isset($airow['status']) ? $airow['status'] : "Empty";
                                                    $ai_created_datetime = isset($airow['created_datetime']) ? $airow['created_datetime'] : "Empty";
                                                    $aiItem[] = $ai_action_item ." - ". $ai_owner ." - ". $ai_status ." - ". $ai_created_datetime;
                                                }
                                            }
                                        
                                            if(!empty($aiItem)) {
                                                $action_items = implode(", ",$aiItem);
                                            }
                                          
                                            echo "<tr class='dt-hasChild shown'>";
                                            // echo "<td class='dt-control-all dt-control'></td>";
                                            echo "<td>". $case_id."</td>"; 
                                            echo "<td>". $rehab_process1['first_visit_date']."</td>"; 
                                            echo "<td>". $rehab_process1['injury_date']."</td>"; 
                                            echo "<td>". $patient_name."</td>";
                                            echo "</tr>";
                                                        ?>
                                            <tr  class="no-padding row-details-tr p-3 mb-2 bg-light"><td class="no-padding row-details-tr p-3 mb-2 bg-light" colspan="8"><div><table class='row_details_table text table table-sm table-borderless mb-0'><tbody>
                                                <tr>
                                                    <td width='120'><span>Law firm:</span></td>
                                                    <td><div><?= $law_firm ?></div></td>
                                                    <td width='120'><span>Next Appts:</span></td>
                                                            <td><div><?= $next_appt ?></div></td>
                                                        </tr>
                                                         <tr>
                                                            <td width='120'><span>Rehab Plan:</span></td>
                                                            <td><div><?= $rehabplan ?></div></td>
                                                            <td width='120'><span>Cancelled Appts:</span></td>
                                                            <td><div><?= $cancelled_appt ?></div></td>
                                                        </tr>
                                                        <tr>
                                                            <td width='120' height='10'><span>Rehab Progress:</span></td>
                                                            <td><div><?= $rehabprogress ?></div></td>
                                                            <td width='120'><span>Case Note:</span></td>
                                                            <td rowspan='2'><div class='textcontentbox'>
                                                            <div class='content case_note_val_container'><?= $notes ?></div>
                                                        </div></td>
                                                        </tr>
                                                        <tr>
                                                            <td width='120'><span>Orders:</span></td>
                                                            <td valign='top'><div><?= $orders ?></div></td>
                                                        </tr>
                                                        <tr>
                                                            <td width='120'><span>Action Items:</span></td>
                                                            <td valign='top'><div><?= $action_items ?></div></td>
                                                            <td width='120'><span>$15k Threshold:</span></td>
                                                            <td valign='top'><div><?= $thresold ?></div></td>
                                                        </tr>
                                                        
                                                    </tbody></table> <div></td> </tr>
                                                    <?php

                                                     }
                                                        ?>
                                            </tbody>
                                        </table>
                                    </div>
                                        
                                        <?php
                                    }
                                    else {
                                    ?>
                                        <p class="msg"><?php echo "No liabilities payer associated";?></p>
                                    <?php } ?>
                                </div>
                            </div>
                        </section> 
                        <!-- End                    -->
                    </div>
                    <div class="p2">
                        <section class="card mb-2  ">
                            <div class="card-body p-1">
                                <h6 class="card-title mb-0 d-flex p-1 justify-content-between">
                                    <a class="text-left font-weight-bolder" href="#" data-toggle="collapse" data-target="#" aria-expanded="true" aria-controls="">List of payers associated with patient</a>
                                </h6>
                                <div id="list_of_payers" class="card-text collapse show" style="">
                                    <?php if($getPayersAssociatedWithPatient != NULL) {?>
                                        <table class="text table msg-table tableRowHighLight dataTable no-footer m-0">
                                            <thead class="thead-dark">
                                                <tr>
                                                    <th scope="col">ID</th>
                                                    <th scope="col">Provider ID</th>
                                                    <th scope="col">Plan Name</th>
                                                    <th scope="col">Policy Number</th>
                                                    <th scope="col">Group Number</th>
                                                    <th scope="col">Payer Name</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <tr>
                                                <?php
                                                    foreach($getPayersAssociatedWithPatient as $getPayersAssociatedWithPatient1) {
                                                        echo "<td>".$getPayersAssociatedWithPatient1['id']."</td>"; 
                                                        echo "<td>".$getPayersAssociatedWithPatient1['provider']."</td>";  
                                                        echo "<td>".$getPayersAssociatedWithPatient1['plan_name']."</td>"; 
                                                        echo "<td>".$getPayersAssociatedWithPatient1['policy_number']. "</td>";  
                                                        echo "<td>".$getPayersAssociatedWithPatient1['group_number']."</td>"; 
                                                        echo "<td>".$getPayersAssociatedWithPatient1['name']."</td>"; 
                                                        echo "</tr>"; 
                                                    }
                                                ?>
                                            </tbody>
                                        </table>
                                    <?php
                                    }
                                    else {
                                    ?>
                                        <p class="msg"><?php echo "No payers associated with patient";?></p>
                                    <?php } ?>
                                </div>
                            </div>
                        </section>

                        <!-- OEMR - Asterisk Change -->
                        <section class="card mb-2  ">
                            <div class="card-body p-1">
                                <h6 class="card-title mb-0 d-flex p-1 justify-content-between">
                                    <a class="text-left font-weight-bolder" href="#" data-toggle="collapse" data-target="#" aria-expanded="true" aria-controls="">Orders pending to schedule</a>
                                </h6>
                                <div id="uncompletedAndNoncancelledOrders" class="card-text collapse show" style="">
                                <?php if($getOrderInfo != NULL) {?>
                                    <table class="text table msg-table tableRowHighLight dataTable no-footer m-0">
                                        <thead class="thead-dark">
                                            <tr>
                                                <th scope="col">Order Id</th>
                                                <th scope="col">Order By</th>
                                                <th scope="col">Status</th>
                                                <th scope="col">Assigned To</th>
                                                <th scope="col">Order Date</th>
                                                <th scope="col">Order Case</th>
                                                <th scope="col">Notes</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <tr>
                                            <?php
                                                foreach($getOrderInfo as $getOrderInfo1) {
                                                    echo "<td>".$getOrderInfo1['id']."</td>"; 
                                                    echo "<td>".$getOrderInfo1['rto_ordered_by']."</td>";
                                                    echo "<td>".$getOrderInfo1['rto_status']."</td>"; 
                                                    echo "<td>".$getOrderInfo1['rto_resp_user']."</td>"; 
                                                    echo "<td>".$getOrderInfo1['rto_date']."</td>";  
                                                    echo "<td>".$getOrderInfo1['rto_case']."</td>"; 
                                                    echo "<td>".$getOrderInfo1['rto_notes']."</td>"; 
                                                    echo "</tr>"; 
                                                }  
                                            ?>
                                        </tbody>
                                    </table>
                                    <?php 
                                        }
                                        else {
                                        ?>
                                        <p class="msg"><?php echo "No orders pending to schedule";?></p>
                                    <?php } ?>
                                </div>
                            </div>
                        </section>  
                        <section class="card mb-2  ">
                            <div class="card-body p-1">
                                <h6 class="card-title mb-0 d-flex p-1 justify-content-between">
                                    <a class="text-left font-weight-bolder" href="#" data-toggle="collapse" data-target="#" aria-expanded="true" aria-controls="">Uncompleted Case Management Action Items</a>
                                </h6>
                                <div id="uncompletedCaseManagementItems" class="card-text collapse show" style="">
                                <?php if($getUnCompletedCasesList != NULL) {?>
                                    <table class="text table msg-table tableRowHighLight dataTable no-footer m-0">
                                        <thead class="thead-dark">
                                            <tr>
                                                <th scope="col">Case ID</th>
                                                <th scope="col">Action Item</th>
                                                <th scope="col">Owner</th>
                                                <th scope="col">Status</th>
                                                <th scope="col">Created Date Time</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <tr>
                                            <?php
                                                foreach($getUnCompletedCasesList as $getUnCompletedCasesList1) {
                                                    echo "<td>".$getUnCompletedCasesList1['case_id']."</td>"; 
                                                    echo "<td>".$getUnCompletedCasesList1['action_item']."</td>"; 
                                                    echo "<td>".$getUnCompletedCasesList1['owner']."</td>"; 
                                                    echo "<td>".$getUnCompletedCasesList1['status']."</td>";  
                                                    echo "<td>".$getUnCompletedCasesList1['created_datetime']."</td>"; 
                                                    echo "</tr>"; 
                                                }  
                                            ?>
                                        </tbody>
                                    </table>
                                    <?php
                                    }
                                    else {
                                    ?>
                                    <p class="msg"><?php echo "No uncompleted case management action items";?></p>
                                <?php } ?>
                                </div>
                            </div>
                        </section> 
                        <!-- End -->
                    </div>
                </div>
                <!-- OEMR Asterisk Change - Will keep either this link or table for Get Uncomepleted and Non-Cancelled Orders and Get Uncomepleted Case Management Action Items table given above-->
                <!-- <a class="btn btn-primary" onclick="top.restoreSession();top.RTop.location = '<?php echo $web_root ?>/interface/asterisk/getUncompletedAndNonCancelledOrders.php?pid=<?= $pid?>'"><?php echo xlt('Get Uncomepleted and Non-Cancelled Orders'); ?></a> -->
                <!-- <a class="btn btn-primary" onclick="top.restoreSession();top.RTop.location = '<?php echo $web_root ?>/interface/asterisk/getUncompletedCaseManagementActionItems.php?pid=<?= $pid?>'"><?php echo xlt('Get Uncomepleted Case Management Action Items'); ?></a> -->
                <!-- End -->
                <?php
            }
        }
        else {
            echo "Idle - No current call information";
        }
    }
    else {
        echo "You are unavailable to view incoming call details";
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

?>
<style>
    .tables {
        display: grid;
        grid-auto-flow: column;
        grid-gap: 30px;
    }

    .tables .p1 table ,
    .tables .p2 table {
        display: inline-table;
    }

    .msg {
        padding-left: 8px;
    }
    
    .tables .card-title {
        padding-top: 10px !important;
        padding-bottom: 5px !important;
    }

    .tables .p-1 {
        padding: 0.5rem !important;
    }
    .dataTable tr.shown td.dt-control:before, .dataTable tr.shown th .dt-control:before, .dataTable tr.shown th.dt-control-all:before {
        font-family: "Font Awesome 6 Free";
        content: "\f078" !important;
        display: inline-block;
        vertical-align: middle;
        font-weight: 900;
        padding-right: 3px;
        vertical-align: middle;
        background-color: transparent !important;
        color: #000;
        border-radius: 0px;
        width: 100%;
        height: auto;
        box-shadow: none;
        margin: auto;
        text-align: center;
        border: 0px;
    }
    .dataTable td.dt-control, .dataTable tr th .dt-control, .dataTable tr.shown td.dt-control, .dataTable tr.shown th .dt-control {
        background: none;
        cursor: pointer !important;
    }
		/*DataTable Style*/
	table.dataTable {
		font-size: 14px;
		width: calc(100% - 16px);
		position: relative;
	}
    .dataTable .defaultValueText {
    	opacity: 0.3;
    }
</style>
