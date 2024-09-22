<?php

namespace OpenEMR\OemrAd;

include_once(__DIR__ . "/../interface/globals.php");
include_once($GLOBALS['srcdir'] . "/patient.inc");

use Aws\S3\S3Client;
use Aws\S3\Exception\S3Exception;
use OpenEMR\Common\Crypto\CryptoGen;

class Utility {

	/*Constructor*/
	public function __construct() {
		
	}

	public static function getSectionValues($uid, $id = '') {
		$sql = "SELECT * FROM `preserve_section_values` WHERE uid = ? ";
		$binds = array($uid);

		if(!empty($id)) {
			$sql .= ' AND fl_name = ? ';
			$binds[] = $id;
		}

		$resultItems = array();
		$result = sqlStatementNoLog($sql, $binds);
		while ($result_data = sqlFetchArray($result)) {
			$isjson = self::isJson($result_data['value']);
			$value = $isjson === true ? json_decode($result_data['value'], true) : $result_data['value']; 

			$resultItems[$result_data['fl_name']] = $value;
		}

		return $resultItems;
	}

	public static function isJson($string) {
		json_decode($string);
		return (json_last_error() == JSON_ERROR_NONE);
	}

	public static function isSectionValuesExist($uid, $id = '') {
		$row = sqlQuery("SELECT * FROM `preserve_section_values` WHERE uid = ? AND fl_name = ? ", array($uid, $id));
		
		if(isset($row) && !empty($row)) {
			return $row;
		}

		return false;
	}

	public static function saveSectionValues($uid, $id, $value) {

		//Check is Value exists or not
		$isSectionValuesExist = self::isSectionValuesExist($uid, $id);
		$newValue = is_array($value) ? json_encode($value) : $value;

		if($isSectionValuesExist === false) {
			//Write new record
			$sql = "INSERT INTO `preserve_section_values` ( ";
			$sql .= "uid, fl_name, value ) VALUES (?, ?, ?) ";
				
			sqlInsert($sql, array($uid, $id, $newValue));

			return true;
		} else {

			//Update Record
			sqlStatementNoLog("UPDATE `preserve_section_values` SET value = ? WHERE fl_name = ? AND uid = ? ", array($newValue, $id, $uid));

			return true;
		}
		
		return false;
	}

	// @VH: [V100070]
	public static function saveFilterValueOfPatientTracker($uid, $data) {
		$fieldList = array('form_apptcat', 'form_apptstatus', 'form_facility', 'form_provider');
		foreach ($fieldList as $key => $item) {
			if(isset($data[$item])) {
				self::saveSectionValues($uid, 'flow_board_'.$item, $data[$item]);
			}
		}
	}

	public static function saveFilterValueOfTelmed($uid, $data) {
		$fieldList = array('form_provider');
		foreach ($fieldList as $key => $item) {
			if(isset($data[$item])) {
				self::saveSectionValues($uid, 'telmed_'.$item, $data[$item]);
			}
		}
	}

	public static function getPatientAlertInfo($pid = '') {
		$row = sqlQuery("SELECT * FROM `patient_data` WHERE id = ? ", array($pid));
		
		if(isset($row) && !empty($row)) {
			return $row;
		}

		return false;
	}

	public static function getAlertSVG() {
		return '<i class="fa fa-exclamation-triangle" aria-hidden="true"></i>';
	}

	public static function handleScript($eid, $form_pid, $pid) {
		if($form_pid == $pid) {

			$result4 = sqlStatement("SELECT fe.encounter,fe.date,openemr_postcalendar_categories.pc_catname FROM form_encounter AS fe ".
    			" left join openemr_postcalendar_categories on fe.pc_catid=openemr_postcalendar_categories.pc_catid  WHERE fe.pid = ? order by fe.date desc", array($pid));

			?>
			<script type="text/javascript">
				EncounterDateArray=new Array;
			    CalendarCategoryArray=new Array;
			    EncounterIdArray=new Array;
			    Count=0;
		        <?php
		        if (sqlNumRows($result4)>0) {
		            while ($rowresult4 = sqlFetchArray($result4)) {
		        ?>
		        EncounterIdArray[Count]='<?php echo attr($rowresult4['encounter']); ?>';
			    EncounterDateArray[Count]='<?php echo attr(oeFormatShortDate(date("Y-m-d", strtotime($rowresult4['date'])))); ?>';
			    CalendarCategoryArray[Count]='<?php echo attr(xl_appt_category($rowresult4['pc_catname'])); ?>';
			            Count++;
			    <?php
			        }
			    }
			    ?>

			    // Get the left_nav window, and the name of its sibling (top or bottom) frame that this form is in.
			    // This works no matter how deeply we are nested.
			    var my_left_nav = top.left_nav;
			    var w = window;
			    for (; w.parent != top; w = w.parent);
			    var my_win_name = w.name;
			    my_left_nav.setPatientEncounter(EncounterIdArray,EncounterDateArray,CalendarCategoryArray);
			</script>
			<?php
		}
	}

	public static function prepareEncounterReportListData($res) {
	    $tmpPreparedData = array();
	    while ($result1 = sqlFetchArray($res)) {
	        if(isset($result1['encounter'])) {
	            if(!isset($tmpPreparedData['e'.$result1['encounter']])) {
	                $tmpPreparedData['e'.$result1['encounter']] = array();
	            }

	            if ($result1['form_name'] == "New Patient Encounter") {
	                $tmpPreparedData['e'.$result1['encounter']]['main'][] = $result1;
	            } else {
	                $tmpPreparedData['e'.$result1['encounter']]['other'][] = $result1;
	            }
	        }
	    }

	    $preparedResultData = array();
	    foreach ($tmpPreparedData as $tmpkey => $tmpItem) {
	        $mainItem = isset($tmpItem['main']) ? $tmpItem['main'] : array();
	        $otherItem = isset($tmpItem['other']) ? $tmpItem['other'] : array();

	        $mergeItem = array_merge($mainItem,$otherItem);

	        if(is_array($mergeItem)) {
	            $preparedResultData = array_merge($preparedResultData, $mergeItem);
	        }
	    }

	    return $preparedResultData;
	}

	/* find patient data by email [V100033] */
	public static function getPatientEmail($email = "%", $given = "pid, id, lname, fname, mname, providerID", $orderby = "lname ASC, fname ASC", $limit = "all", $start = "0") {
	    $col = $GLOBALS['wmt::use_email_direct'] ? 'email_direct' : 'email';
	    $sqlBindArray = array();
	    $where = "$col LIKE ? OR CONCAT(',',secondary_email,',') LIKE '%,$email%,'";
	    array_push($sqlBindArray, $email."%");
	    $sql="SELECT $given FROM patient_data WHERE $where ORDER BY $orderby";
	    if ($limit != "all") {
	        $sql .= " limit " . escape_limit($start) . ", " . escape_limit($limit);
	    }

	    $rez = sqlStatement($sql, $sqlBindArray);
	    for ($iter=0; $row=sqlFetchArray($rez); $iter++) {
	        $returnval[$iter]=$row;
	    }

	    _set_patient_inc_count($limit, count($returnval), $where, $sqlBindArray);
	    return $returnval;
	}

	//(CHEMED) Search by phone number
	// public static function getPatientPhones($phone = "%", $given = "pid, id, lname, fname, mname, providerID", $orderby = "lname ASC, fname ASC", $limit = "all", $start = "0") {
	//     $phone = preg_replace("/[[:punct:]]/", "", $phone);
	//     $phone_number = preg_replace('/^\+?1|\|1|\D/', '', ($phone));


	//     $sqlBindArray = array();
	//     $where = "REPLACE(REPLACE(phone_home, '-', ''), ' ', '') REGEXP ? ";
	//     array_push($sqlBindArray, $phone);
	    
	//     $where .= "OR TRIM(LEADING '1' FROM replace(replace(replace(replace(replace(replace(phone_cell,' ',''),'(','') ,')',''),'-',''),'/',''),'+','')) LIKE ? ";
	//     array_push($sqlBindArray, $phone_number."%");

	//     $where .= "OR CONCAT(',',replace(replace(replace(replace(replace(replace(secondary_phone_cell,' ',''),'(','') ,')',''),'-',''),'/',''),'+',''),',') LIKE ? ";
	//     array_push($sqlBindArray, "%,1".$phone_number."%,");

	//     $where .= "OR CONCAT(',',replace(replace(replace(replace(replace(replace(secondary_phone_cell,' ',''),'(','') ,')',''),'-',''),'/',''),'+',''),',') LIKE ? ";
	//     array_push($sqlBindArray, "%,".$phone_number."%,");
	    

	//     $sql="SELECT $given FROM patient_data WHERE $where ORDER BY $orderby";
	//     if ($limit != "all") {
	//         $sql .= " limit " . escape_limit($start) . ", " . escape_limit($limit);
	//     }

	//     $rez = sqlStatement($sql, $sqlBindArray);
	//     for ($iter=0; $row=sqlFetchArray($rez); $iter++) {
	//         $returnval[$iter]=$row;
	//     }

	//     _set_patient_inc_count($limit, count($returnval), $where, $sqlBindArray);
	//     return $returnval;
	// }

	//public static function getMultiTextInputElement($frow, $field_value = '', $rmBtn = false) {
	    // global $edit_options, $lbfchange;

	    // $field_id = $frow['field_id'];
	    // $list_id  = $frow['list_id'];
	    // $field_id_esc = text($field_id);
	    // $smallform = isset($frow['smallform']) ? $frow['smallform'] : "";
	    // $description = (isset($frow['description']) ? htmlspecialchars(xl_layout_label($frow['description']), ENT_QUOTES) : '');
	    // // Support using the description as a placeholder
	    // $placeholder = (isOption($edit_options, 'DAP') === true) ? " placeholder='{$description}' " : '';
	    // $btnSize = ($smallform) ? "btn-sm" : "";

	    // $tmp = $lbfchange;
	    // if (isOption($edit_options, 'C') !== false) {
	    //     $tmp .= "capitalizeMe(this);";
	    // } elseif (isOption($edit_options, 'U') !== false) {
	    //     $tmp .= "this.value = this.value.toUpperCase();";
	    // }

	    /*
	    Author: Hardik Khatri
	    Description: Email Verification changes
	    */
	    /*if (isOption($edit_options, 'EMV') !== false) {
	        global $pid;
	        $pid = ($frow['blank_form'] ?? null) ? 0 : $pid;
	        $emvStatus = EmailVerificationLib::emailVerificationData($pid, $field_id_esc, $field_value);
	        $smallform .= " emv-form-control";
	        echo "<div class='emv-input-group-container' data-initemail='{$currescaped}' data-initstatus='{$emvStatus}' data-id='form_{$field_id_esc}'><div class='input-group'>";
	    }*/

	    /*
	    Author: Hardik Khatri
	    Description: Mask phone number value.
	    */
	    // if (isOption($frow['edit_options'], 'MP') !== false) {
	    //     $smallform .= " maskPhone";
	    // }

	    /*
	    Author: Hardik Khatri
	    Description: Validate phone number.
	    */
	    // if (isOption($frow['edit_options'], 'MPV') !== false) {
	    //     $mpValidation = " data-validate='validatePhoneNumber;'";
	    // }

	    // $tmpOnChange = !empty($tmp) ? " onchange='$tmp'" : "";

	    // $mIRemoveBtn = "<button type='button' data-id='$field_Id' class='btn btn-secondary $btnSize mb-1 ' onclick='removeMoreInput(this)'><i class='fa fa-times' aria-hidden='true'></i></button>";

	    // $mIInputEle = "<input type='text'" . 
	    //             " class='form-control mti-form-control {$smallform}' " .
	    //             " data-id='$field_id_esc'" . 
	    //             " data-title='".$frow['title']."'" .  
	    //             " size='{$fldlength}'" . 
	    //             " {$string_maxlength}" . 
	    //             " {$placeholder}" .
	    //             " title='{$description}'" .
	    //             " " . $mpValidation . 
	    //             " " . $tmpOnChange . 
	    //             " value='" . trim($field_value) . "'" .
	    //             "/>";

	    /*
	    Author: Hardik Khatri
	    Description: Email Verification changes
	    */
	    /*if (isOption($edit_options, 'EMV') !== false) {
	        if($emvStatus === 1) {
	            $statusElement = "<i class='fa fa-check-circle email-verification-icon-successful' aria-hidden='true'></i>";
	        } else {
	            $statusElement = "<i class='fa fa-times-circle email-verification-icon-failed' aria-hidden='true'></i>";
	        }

	        echo "<div class='input-group-append'><input type='hidden' name='form_{$field_id_esc}_hidden_verification_status' value='{$vStatusFlag}'' id='form_{$field_id_esc}_hidden_verification_status' class='hidden_verification_status' /><button type='button' id='form_{$field_id_esc}_btn_verify_email' class='btn btn-primary btn-sm btn_verify_email mb-1'>Verify</button></div>";
	        echo "</div><div class='status-icon-container'>{$statusElement}</div></div>";
	    }*/


	    // $mICloneEle = "<div class='input-group'>" . $mIInputEle . "<div class='input-group-append'>" . $mIRemoveBtn . "</div></div>";
	    // $mIElement = "<div class='mti-itemcontainer'>" . $mIInputEle . "</div>";

	    // if($rmBtn === true) { $mIElement = $mICloneEle; }

	    // return $mIElement;
	//}

	public static function getHtmlString($text) {
		return addslashes(htmlspecialchars($text));
	}

	// OEMR - ICS File Change
	public static function generateICSFileLink($icsLinkData = array()) {
		
		$s3bucketkey = $GLOBALS['S3_BUCKET_KEY'];
		$cryptoGen = new CryptoGen();
		$s3bucketsecretkey = $cryptoGen->decryptStandard($GLOBALS['S3_BUCKET_SECRET_KEY']);
		$s3bucketname = $GLOBALS['S3_BUCKET_NAME'];
		$s3bucketregion = $GLOBALS['S3_BUCKET_REGION'];
		$s3bucketversion = $GLOBALS['S3_BUCKET_VERSION'];
		$dowloadics = $GLOBALS['ics_attachment'];
		$ics_file_link = "";

		// OEMR - ICS File Change to generate signed URL
		try {

		if($dowloadics == "1") {
			$event_timestamp = strtotime($icsLinkData['event_date']);
			$formattedDate = date("Ymd", $event_timestamp);
			$start_timestamp = strtotime($icsLinkData['starttime']);
			$timeInMinute = date("H:i A", $start_timestamp);
			$starttimeformat = date("His", $start_timestamp);
			$end_timestamp = strtotime($icsLinkData['endtime']);
			$endtimeformat = date("His", $end_timestamp);
			$descFormatDate = date("m/d/Y", $event_timestamp);

			$fac_record = sqlQueryNoLog("SELECT `name`, `street` FROM `facility` WHERE `id` = ?", array((int)$icsLinkData['facility']));
			if (isset($fac_record['name'])) $facility_name = $fac_record['name'];
			if (isset($fac_record['street'])) $facility_address = $fac_record['street'];

			$prv_record = sqlQueryNoLog("SELECT `fname`, `mname`, `lname` FROM `users` WHERE `id` = ?", array($icsLinkData['formProvider']));
			if (isset($prv_record['lname'])) $provider_name = substr($prv_record['fname'],0,1) .'. '. $prv_record['lname'];
			$cat_record = sqlQueryNoLog("SELECT `pc_catname` FROM `openemr_postcalendar_categories` WHERE `pc_catid` = ?", array($icsLinkData['formCategory']));
			if (isset($cat_record['pc_catname'])) $category = $cat_record['pc_catname'];
			$description = "Your new patient appointment at Pro-Care Medical Centers is : ".$descFormatDate." - ".$timeInMinute." , ".$category." with ".$provider_name." at ".$facility_address;

			$glres = sqlQuery("SELECT gl_name, gl_index, gl_value FROM globals WHERE gl_name = 'gbl_time_zone' ORDER BY gl_name, gl_index");
		
			$todaystamp = gmdate('Ymd\THis\Z');
			$S3filename = "Appointment_".md5($icsLinkData['pc_eid']).".ics";
			$uid1 = date('Ymd').'T'.date('His').rand().'@gcalendar';

			$startdate = $formattedDate.'T'. $starttimeformat;
			$endDate = $formattedDate.'T'. $endtimeformat;
			$ics_content[0]  = "BEGIN:VCALENDAR";
			$ics_content[1] = "PRODID:-//Google Inc//Google Calendar 70.9054//EN";
			$ics_content[2] = "VERSION:2.0";
			$ics_content[3] = "BEGIN:VEVENT";
			$ics_content[4] = "UID:" . $uid1;
			$ics_content[5] = "DTSTAMP:" . $todaystamp;
			$ics_content[6] = "DESCRIPTION:" . $description;
			$ics_content[7] = "DTSTART;TZID=\"".$glres['gl_value']."\":" . $startdate;
			$ics_content[8] = "DTEND;TZID=\"".$glres['gl_value']."\":" . $endDate;
			$ics_content[9] = "LOCATION:" . $facility_address;
			$ics_content[10] = "SUMMARY:" . $icsLinkData['formTitle'];
			$ics_content[11] = "ORGANIZER;CN=".$GLOBALS['openemr_name']." - ".$provider_name.":mailto:".$GLOBALS['EMAIL_SEND_FROM'];
			$ics_content[12] = "END:VEVENT";
			$ics_content[13] = "END:VCALENDAR";
				
			$ics_content = implode("\r\n", $ics_content);

			$s3 = new \Aws\S3\S3Client([
				'region'  => $s3bucketregion,
				'version' => $s3bucketversion,
				'credentials' => [
					'key'    => $s3bucketkey,
					'secret' => $s3bucketsecretkey,
				]
			]);     
			
			$result = $s3->putObject([
				'Bucket' => $s3bucketname,
				'Key'    => $S3filename,
				'Body' => $ics_content,
				'ContentType' => 'text/calendar',
				'ACL'    => 'private'     //Added          
			]);

			if(!empty($result) && isset($result['ObjectURL']) && !empty($result['ObjectURL'])) {
				// $ics_file_link = $result['ObjectURL'];

				
					$cmd = $s3->getCommand('GetObject', [
					  'Bucket' => $s3bucketname,
					  'Key'    => $S3filename
					]);
		  
					$ics_file_link =  $s3->createPresignedRequest($cmd, '+168 hours')->getUri()->__toString();
			}
		}

		} catch (\Aws\S3\Exception\S3Exception $e) {
		    return;
		} catch (Exception $e) {
			return;
		}
		//End
	
		return $ics_file_link;
	}

	public static function addUpdateICSLink($icsdata = array()) {
		$dowloadics = $GLOBALS['ics_attachment'];

		if($dowloadics == "1" && !empty($icsdata['icslink']) && !empty($icsdata['pc_eid'])) {
			sqlStatement("UPDATE openemr_postcalendar_events SET ics_file = ? WHERE pc_eid = ?", array($icsdata['icslink'], $icsdata['pc_eid']));
		}
	}
	// End
}
