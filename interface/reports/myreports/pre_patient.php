<?php 
require_once("../../globals.php");
require_once("$srcdir/patient.inc");
require_once "$srcdir/options.inc.php";

use OpenEMR\Common\Acl\AclMain;
use OpenEMR\Common\Csrf\CsrfUtils;
use OpenEMR\Common\Twig\TwigContainer;
use OpenEMR\Core\Header;

if (!AclMain::aclCheckCore('patients', 'med')) {
    echo (new TwigContainer(null, $GLOBALS['kernel']))->getTwig()->render('core/unauthorized.html.twig', ['pageTitle' => xl("Referrals")]);
    exit;
}

if (!empty($_POST)) {
    if (!CsrfUtils::verifyCsrfToken($_POST["csrf_token_form"])) {
        CsrfUtils::csrfNotVerified();
    }
}
$form_from_date = (isset($_POST['form_from_date'])) ? DateToYYYYMMDD($_POST['form_from_date']) : date('Y-m-d',strtotime("-3 months"));
$form_to_date = (isset($_POST['form_to_date'])) ? DateToYYYYMMDD($_POST['form_to_date']) : date('Y-m-d'); 
$pre_patient_destination = isset($_POST['pre_patient_destination']) ? $_POST['pre_patient_destination'] : '';
$pre_patient_status = isset($_POST['form_pre_patient_status']) ? $_POST['form_pre_patient_status'] : '';
?>
<html>
<head>
    <title><?php echo xlt('Pre-Patient'); ?></title>

    <?php Header::setupHeader(['datetime-picker', 'report-helper']); ?>
    <script type='text/javascript'><?php include($GLOBALS['srcdir'].'/wmt-v2/report_tools.inc.js'); ?></script>

    <script>
        <?php require($GLOBALS['srcdir'] . "/restoreSession.php"); ?>

        $(function () {
            oeFixedHeaderSetup(document.getElementById('mymaintable'));
            var win = top.printLogSetup ? top : opener.top;
            win.printLogSetup(document.getElementById('printbutton'));

            $('.datepicker').datetimepicker({
                <?php $datetimepicker_timepicker = false; ?>
                <?php $datetimepicker_showseconds = false; ?>
                <?php $datetimepicker_formatInput = true; ?>
                <?php require($GLOBALS['srcdir'] . '/js/xl/jquery-datetimepicker-2-5-4.js.php'); ?>
                <?php // can add any additional javascript settings to datetimepicker here; need to prepend first setting with a comma ?>
            });
        });

        function oldEvt(eventid) {
			dlgopen('<?php echo $GLOBALS['webroot']; ?>/interface/main/calendar/add_edit_event.php?eid=' + eventid, 'blank', 775, 500);
		}

        async function handlegotoCase(case_id, pid, section = '') {
			top.left_nav.closetab = false;
			handleSetPatientData(pid, function() {
				top.activateTabByName("case",true);
			});
			let sectionurl = section != "" ? '&sectionto='+section : '';
			top.navigateTab(top.webroot_url + '/interface/forms/cases/view.php?id='+case_id+'&pid='+pid+'&list_mode=list&list_popup=&popup=no&caller=patient' + sectionurl,"case", function () {
				top.activateTabByName("case",true);
			});
		}

        function handleSetPatientData(pid, callbackfun = null) {
			top.navigateTab(top.webroot_url + "/interface/patient_file/summary/demographics.php?set_pid=" + pid,"pat", function () {
			    if (callbackfun instanceof Function) {
					callbackfun();
				} else {
					top.activateTabByName("pat",true);
				}
			});
		}
    </script>

    <style>
        /* specifically include & exclude from printing */
        @media print {
            #report_parameters {
                visibility: hidden;
                display: none;
            }
            #report_parameters_daterange {
                visibility: visible;
                display: inline;
            }
            #report_results table {
               margin-top: 0px;
            }
        }

        /* specifically exclude some from the screen */
        @media screen {
            #report_parameters_daterange {
                visibility: hidden;
                display: none;
            }
        }
    </style>
</head>
<body class="body_top pre_patient">
<span class='title'><?php echo xlt('Report'); ?> - <?php echo xlt('Pre-Patient'); ?></span>
<div id="report_parameters_daterange">
<?php echo text(oeFormatShortDate($form_from_date)) . " &nbsp; " . xlt('to{{Range}}') . " &nbsp; " . text(oeFormatShortDate($form_to_date)); ?>
</div>
<form name='theform' id='theform' method='post' action='pre_patient.php' onsubmit='return top.restoreSession()'>
<input type="hidden" name="csrf_token_form" value="<?php echo attr(CsrfUtils::collectCsrfToken()); ?>" />
<div id="report_parameters">
<input type='hidden' name='form_refresh' id='form_refresh' value=''/>
<table>
 <tr>
  <td width='800px'>
    <div style='float: left'>
    <table class='text'>
        <tr>
            <td class='col-form-label'>
                <?php echo xlt('From'); ?>:
            </td>
            <td>
               <input type='text' class='datepicker form-control' name='form_from_date' id="form_from_date" size='10' value='<?php echo attr(oeFormatShortDate($form_from_date)); ?>' />
            </td>
            <td class='col-form-label'>
                <?php echo xlt('To{{Range}}'); ?>:
            </td>
            <td>
               <input type='text' class='datepicker form-control' name='form_to_date' id="form_to_date" size='10' value='<?php echo attr(oeFormatShortDate($form_to_date)); ?>' />
            </td>
        </tr>
        <tr>
            <td class='col-form-label'>
                <?php echo xlt('Pre-Patient Destination'); ?>:
            </td>
            <td>
            <?php 
            $ures = sqlStatement("SELECT * FROM list_options where list_id = 'pre_patient_incoming' and activity=1 order by title");
            echo "<select multiple name='pre_patient_destination[]' id='pre_patient_destination' class='form-control' >";
            // echo "<option value=''>" . xlt("-- ALL --") . "</option>";
            echo "<option value=''";
            if(empty($pre_patient_destination[0])) {
                echo " selected='selected'";
            }
            echo ">" . xlt("-- ALL --") . "</option>";
            while ($urow = sqlFetchArray($ures)) {
                $utitle = text($urow['title']);
                $optionId = attr($urow['option_id']);
                echo "<option value='$optionId'";
                if(in_array($optionId, $pre_patient_destination)) {
                    echo " selected='selected'";
                }
                // if ($optionId == $pre_patient_destination) {
                //     echo " selected";
                // }
                echo ">$utitle</option>";
            }
            echo "</select>"; 
            ?>
              
            </td>
            <td class='col-form-label'>
                <?php echo xlt('Pre-Patient Status'); ?>:
            </td>
            <td>
            <?php 
                $ures = sqlStatement("SELECT * FROM list_options where list_id = 'pre_patient' and activity=1 order by title");
                echo "<select name='form_pre_patient_status[]' id='form_pre_patient_status' multiple class='form-control' >";
                // echo "<option value=''>" . xlt("-- ALL --") . "</option>";
                echo "<option value=''";
                if(in_array("", $pre_patient_status)) echo " selected='selected'";

                // if(empty($pre_patient_status[0])) {
                //     echo " selected='selected'";
                // }
                echo ">" . xlt("-- ALL --") . "</option>";
                while ($urow = sqlFetchArray($ures)) {
                    $utitle = text($urow['title']);
                    $optionId = attr($urow['option_id']);
                    echo "<option value='$optionId'";
                    if(empty($pre_patient_status)) {
                        if($optionId === "ppstat_1" || $optionId === "ppstat_3" || $optionId === "ppstat_4") {
                            echo " selected='selected'";
                        }
                        // echo " selected='selected'";
                    }
                    // if ($optionId == $pre_patient_status) {
                    //     echo " selected";
                    // }
                    if(in_array($optionId, $pre_patient_status)) {
                        echo " selected='selected'";
                    } 
                    echo ">$utitle</option>";
                }
                echo "</select>";
            ?>
            </td>
        </tr>
    </table>
    </div>
  </td>

  <td class='h-100' align='left' valign='middle'>
    <table class='w-100 h-100' style='border-left:1px solid;'>
        <tr>
            <td>
               <div class="text-center">
                    <div class="btn-group" role="group">
                        <a href='#' class='btn btn-secondary btn-save' onclick='$("#form_refresh").attr("value","true"); $("#theform").submit();'>
                            <?php echo xlt('Submit'); ?>
                        </a>
                        <?php if (!empty($_POST['form_refresh'])) { ?>
                        <a href='#' class='btn btn-secondary btn-print' id='printbutton'>
                                <?php echo xlt('Print'); ?>
                        </a>
                        <?php } ?>
                    </div>
               </div>
            </td>
        </tr>
    </table>
  </td>
 </tr>
</table>
</div> <!-- end of parameters -->

<?php
if (!empty($_POST['form_refresh'])) {
    ?>

<div id="report_results">
<table class='table' width='98%' id='mymaintable'>
<thead class='thead-light'>
<th> <?php echo xlt('Patient Name'); ?> </th>
<th> <?php echo xlt('Pre-Patient Destination'); ?> </th>
<th> <?php echo xlt('Pre-Patient Status'); ?> </th>
<th> <?php echo xlt('Pre-patient Notes'); ?> </th>
<th> <?php echo xlt('Phone Number'); ?> </th>
<th> <?php echo xlt('Email'); ?> </th>
<th> <?php echo xlt('Cases'); ?> </th>
<th> <?php echo xlt('Appointments on Calendar'); ?> </th>
</thead>
<tbody>
    <?php
    if ($_POST['form_refresh']) {
        // $query = sqlStatement("select pid, CONCAT(CONCAT_WS(' ', IF(LENGTH(fname),fname,NULL)), ' (', pubpid ,')') as patient_name, 
        // pre_patient_status, pre_patient_destination, pre_patient_notes, phone_cell, email from patient_data 
        // where date BETWEEN NOW() - INTERVAL 30 DAY AND NOW() AND pre_patient_destination = ? AND pre_patient_status = ?", array($pre_patient_destination, $pre_patient_status));
        
        $sqlBindArray = array();
        if($_POST['form_from_date']) {
            $where = "created >= ? AND created <= ? ";
            array_push($sqlBindArray, $form_from_date. ' 00:00:00', $form_to_date.' 23:59:59');
        }
        if ($_POST['pre_patient_destination']) {
            if ($_POST['pre_patient_destination'] && !in_array("", $_POST['pre_patient_destination'])) {
                $where .= "AND pre_patient_destination IN ('".implode("','",$_POST['pre_patient_destination'])."') ";
              }
        }

        if ($_POST['form_pre_patient_status']) {
            if ($_POST['form_pre_patient_status'] && !in_array("", $_POST['form_pre_patient_status'])) {
                $where .= "AND pre_patient_status IN ('".implode("','",$_POST['form_pre_patient_status'])."') ";
            }
        }
        $query = "select pid, CONCAT(CONCAT_WS(' ', IF(LENGTH(fname),fname,NULL), IF(LENGTH(lname),lname,NULL)), ' (', pubpid ,')') as patient_name, 
        pre_patient_status, pre_patient_destination, pre_patient_notes, phone_cell, email from patient_data " .
        "WHERE 1=1 and $where ";
        $res = sqlStatement($query, $sqlBindArray);
        while ($row = sqlFetchArray($res)) {
            // If a facility is specified, ignore rows that do not match.
            if ($form_facility !== '') {
                if ($form_facility) {
                    if ($row['facility_id'] != $form_facility) {
                        continue;
                    }
                } else {
                    if (!empty($row['facility_id'])) {
                        continue;
                    }
                }
            }

            ?>
    <tr>
    <td>
    <?php 
        $getPatientid = $row['pid'];
        echo "<a href='javascript:goParentPid($getPatientid)'>".$row['patient_name']."</a>"; 
    ?>
    </td>
    <td>
        <?php
        $getDesc = sqlStatement("select title from list_options where option_id =?", array($row['pre_patient_destination']));
        $getDesc1 = sqlFetchArray($getDesc);
        echo text($getDesc1['title']) ;
        ?>
    </td>
    <td>
    <?php
        $getStatus = sqlStatement("select title from list_options where option_id =?", array($row['pre_patient_status']));
        $getStatus1 = sqlFetchArray($getStatus);
        echo text($getStatus1['title'])
    ?>
    </td>
    <td>
        <?php echo text($row['pre_patient_notes']); ?>
    </td>
    <td>
        <?php echo "<a href=\"#\" onclick= dlgopen('".$GLOBALS['webroot']."/interface/asterisk/makeCallThroughExtension.php?phone_number=".$row['phone_cell']."') >".$row['phone_cell']."</a>"; ?>
    </td>
    <td>
        <?php echo text($row['email']); ?>
    </td>
    <td>
    
            <?php 
            $getPatientCase = sqlStatement("select DISTINCT id, date, case_description, pid from form_cases where pid = ? ORDER BY id desc",array($row['pid']));
            $fieldHtml= array();
            if(sqlNumRows($getPatientCase) > 0) {
                while ($getPatientCase1 = sqlFetchArray($getPatientCase)) {
                    $getPayerResult = sqlStatement("SELECT ic1.name as ic_name1, ic2.name as ic_name2, ic3.name as ic_name3, fc.id as case_id, (select min(ope.pc_eventDate) from openemr_postcalendar_events ope where ope.pc_case = fc.id and ope.pc_apptstatus not in ('-','+','?','x','%') and ope.pc_pid = fc.pid) as first_visit_date, fc.injury_date,  CONCAT(CONCAT_WS(' ', IF(LENGTH(pd.fname),pd.fname,NULL), IF(LENGTH(pd.lname),pd.lname,NULL)), ' (', pd.pubpid ,')') as patient_name, fc.ins_data_id1, fc.ins_data_id2, fc.ins_data_id3, fc.pid, fc.comments, fc.bc_notes from form_cases fc
                                                left join patient_data pd on pd.pid = fc.pid
                                                left join insurance_data id1 on id1.id = fc.ins_data_id1 left join insurance_data id2 on id2.id = fc.ins_data_id2 left join insurance_data id3 on id3.id = fc.ins_data_id3
                                                left join insurance_companies ic1 on ic1.id = id1.provider left join insurance_companies ic2 on ic2.id = id2.provider left join insurance_companies ic3 on ic3.id = id3.provider where fc.id=".$getPatientCase1['id']." ");
                    $getPayer1 = sqlFetchArray($getPayerResult);

                    ?>
                    <table>
                        <tr>
                            <td>
                                <div>
                                    <a href="#!" onclick="handlegotoCase('<?php echo $getPatientCase1['id']; ?>','<?php echo $getPatientCase1['pid'] ?>');"><span><?php echo $getPatientCase1['id']; ?></span> - <span><?php echo !empty($getPatientCase1['case_description']) ? $getPatientCase1['case_description'] : "<i>Empty</i>"; ?></span></a>
                                </div>
                                <div>
                                    <ol class="m-0">
                                        <?php if(!empty($getPayer1['ic_name1'])) { ?> 
                                            <li><?php echo $getPayer1['ic_name1']; ?></li>
                                        <?php } ?>

                                        <?php if(!empty($getPayer1['ic_name2'])) { ?> 
                                            <li><?php echo $getPayer1['ic_name2']; ?></li>
                                        <?php } ?>

                                        <?php if(!empty($getPayer1['ic_name3'])) { ?> 
                                            <li><?php echo $getPayer1['ic_name3']; ?></li>
                                        <?php } ?>
                                    </ol>
                                </div>
                            </td>
                        </tr>
                    </table>
                    <?php

                    //$fieldHtml[] = "<a href=\"#!\" onclick=\"handlegotoCase('".$getPatientCase1['id']."','".$getPatientCase1['pid']."');\">".$getPatientCase1['id'] ." ".  $getPayer1['ic_name1'] . $getPayer1['ic_name2'] ." </a>";
                   
                }
            }
            if(!empty($fieldHtml)) {
                $fieldHtml = implode(", ", $fieldHtml);
            } else {
                $fieldHtml = "";
            } 
            echo $fieldHtml
            ?>
    </td>
    <td>
            <?php 
             $getPatientAppt = sqlStatement("select ope.pc_eid,TIMESTAMP(ope.pc_eventDate, ope.pc_startTime) as event_date_time, ope.pc_aid as provider_id, u.fname as provider_fname, u.mname as provider_mname, u.lname as provider_lname, CONCAT(CONCAT_WS(' ', IF(LENGTH(u.fname),u.fname,NULL), IF(LENGTH(u.lname),u.lname,NULL))) as provider_full_name, opc.pc_catname, lo.title as reason  from openemr_postcalendar_events ope left join openemr_postcalendar_categories opc on opc.pc_catid = ope.pc_catid left join users u on u.id = ope.pc_aid left join list_options lo on lo.list_id = 'apptstat' and lo.option_id = ope.pc_apptstatus where ope.pc_pid = ? and TIMESTAMP(ope.pc_eventDate, ope.pc_startTime) > now() order by TIMESTAMP(ope.pc_eventDate, ope.pc_startTime)",array($row['pid']));
             $fieldHtml= array();
             if(sqlNumRows($getPatientAppt) > 0) {
                 while ($getPatientAppt1 = sqlFetchArray($getPatientAppt)) {
                        $next_appt_time = isset($getPatientAppt1['event_date_time']) ? date('m/d',strtotime($getPatientAppt1['event_date_time'])) : "";
						$next_appt_provider_name = "";

						if(isset($getPatientAppt1['provider_fname']) && !empty($getPatientAppt1['provider_fname'])) {
							$next_appt_provider_name .= ucfirst(substr($getPatientAppt1['provider_fname'], 0, 1));
						}

						if(isset($getPatientAppt1['provider_mname']) && !empty($getPatientAppt1['provider_mname'])) {
							$next_appt_provider_name .= ucfirst(substr($getPatientAppt1['provider_mname'], 0, 1));
						}

						if(isset($getPatientAppt1['provider_lname']) && !empty($getPatientAppt1['provider_lname'])) {
							$next_appt_provider_name .= ucfirst(substr($getPatientAppt1['provider_lname'], 0, 1));
						}

						$fieldHtml[] = "<a href=\"#!\" onclick=\"oldEvt('".$getPatientAppt1['pc_eid']."');\">".$next_appt_provider_name. " " . $next_appt_time ."</a>";
                    
                 }
             }
             if(!empty($fieldHtml)) {
                 $fieldHtml = implode(", ", $fieldHtml);
             } else {
                 $fieldHtml = "";
             } 
             echo $fieldHtml;

            ?>
    </td>
   </tr>
            <?php
        }
    }
    ?>
</tbody>
</table>
</div> <!-- end of results -->
<?php } else { ?>
<div class='text'>
    <?php echo xlt('Please input search criteria above, and click Submit to view results.'); ?>
</div>
<?php } ?>
</form>
<script type="text/javascript">
	var curr_scrollYVal = 0;
	var prev_scrollYVal = 0;
	$(document).ready(function() {
		window.addEventListener("scroll", (event) => {
		  	curr_scrollYVal = $(window).scrollTop();
		});

		var observer = new MutationObserver(function(mutationsList, observer) {
		    for (var mutation of mutationsList){
		        if($(mutation.target).is(":visible")){
		        	if(prev_scrollYVal >= 0) {
		        		$(window).scrollTop(prev_scrollYVal);
		        	}
		        } else if(!$(mutation.target).is(":visible")){
		        	prev_scrollYVal = curr_scrollYVal
		        }
		    }
		});

		$('.frameDisplay iframe', parent.document).each(function(i, obj) {
			var cElement = $(obj).contents().find('body.pre_patient');
			if(cElement.length > 0){
				observer.observe(obj.parentElement, { attributes: true});
			}
		});
	});
</script>
</body>
</html>