<?php
/** ******************************************************************************************
 *	wmtNexmo.class.php
 *
 *	Copyright (c)2019 - Medical Technology Services <MDTechSvcs.com>
 *
 *	This program is free software: you can redistribute it and/or modify it under the 
 *  terms of the GNU General Public License as published by the Free Software Foundation, 
 *  either version 3 of the License, or (at your option) any later version.
 *
 *	This program is distributed in the hope that it will be useful, but WITHOUT ANY
 *	WARRANTY; without even the implied warranty of MERCHANTABILITY or FITNESS FOR A 
 *  PARTICULAR PURPOSE. DISTRIBUTOR IS NOT LIABLE TO USER FOR ANY DAMAGES, INCLUDING 
 *  COMPENSATORY, SPECIAL, INCIDENTAL, EXEMPLARY, PUNITIVE, OR CONSEQUENTIAL DAMAGES, 
 *  CONNECTED WITH OR RESULTING FROM THIS AGREEMENT OR USE OF THIS SOFTWARE.
 *
 *	See the GNU General Public License <http://www.gnu.org/licenses/> for more details.
 *
 *  @package wmt
 *  @subpackage sms
 *  @version 2.0.0
 *  @copyright Medical Technology Services
 *  @author Ron Criswell <ron.criswell@MDTechSvcs.com>
 *
 ******************************************************************************************** */

/**
 * All new classes are defined in the WMT namespace
 */
namespace wmt;

/**
 * Make sure standard utilities are loaded
 */
require_once($GLOBALS['srcdir']."/wmt-v3/wmt.globals.php");
require_once("$srcdir/OemrAD/oemrad.globals.php");

use OpenEMR\OemrAd\TwilioUtilitylib;

/**
 * Provides standardized processing for bidirectional sms messages.
 *
 * @package wmt
 * @subpackage nexmo
 *
 * @version 2.0.0
 * @since 2019-04-30
 * 
 */
class Nexmo {

	/** 
	 * Class constants
	 */	
	const API_URL = 'https://rest.nexmo.com/sms/json';
	const HTTP_GET = 'GET';
	const HTTP_POST = 'POST';
	const ACCEPTED_CODES = '200, 201, 202';

	/**
	 * Static variables
	 */
	private static $codes = [
		'1' => 'Throttled',
		'2' => 'Missing Parameters',
		'3' => 'Invalid Parameters',
		'4' => 'Invalid Credentials',
		'5' => 'Internal Error',
		'6' => 'Invalid Message',
		'7' => 'Number Barred',
		'8' => 'Partner Account Barred',
		'9' => 'Partner Quota Violation',
		'10' => 'Too Many Existing Binds',
		'11' => 'Account Not Enabled for HTTP',
		'12' => 'Message Too Lang',
		'14' => 'Invalid Signature',
		'15' => 'Invalid Sender Address',
		'22' => 'Invalid Network Code',
		'23' => 'Invalid Callback URL',
		'29' => 'Non-Whitelisted Destination',
		'32' => 'Signature API Secret Disallowed',
		'33' => 'Number De-activated',
		'99' => 'Caught Unknown Error'
	];
	
	/** 
	 * Class variables
	 */
	private $secret;
	private $token;
	private $from;
	private $to;
	private $content;
	
	/**
	 * Constructor for the 'SMS' class which generates all types 
	 * of sms messages sent to the Nexmo servers.
	 *
	 * @param  string $from 	From phone number
	 * @throws \Exception		Missing data
	 * @return object 			Nexmo class
	 * 
	 */
	public function __construct($from='') {
		// Store "from" phone number
		if (empty($from)) $from = $GLOBALS['SMS_DEFAULT_FROM'];
		$this->from = preg_replace('/[^0-9]/', '', $from);
		
		// Retrieve the api information
		$this->token = $GLOBALS['SMS_NEXMO_APIKEY'];
		$this->secret = $GLOBALS['SMS_NEXMO_SECRET'];
		$this->notify = $GLOBALS['SMS_NOTIFY_HOURS'];
		$this->confirm = $GLOBALS['SMS_CONFIRM_HOURS'];

		if (empty($this->token))
			throw new \Exception("wmtNexmo:construct - no 'token' api key in Nexmo config");

		if (empty($this->secret))
			throw new \Exception("wmtNexmo:construct - no 'secret' api key in Nexmo config");

		if (empty($this->from))
			throw new \Exception("wmtNexmo:construct - no 'from' phone in Nexmo config");
		
		// Check if we are logging changes
		$this->logging = sqlQuery("SHOW TABLES LIKE 'openemr_postcalendar_log'");

		return;
	}

	/**
	 * Handle CURL response from Nexmo servers.
	 *
	 * @param string 	$result   The API response
	 * @param int		$httpCode The HTTP status code
	 *
	 * @throws \Exception		Error condition
	 * @return array			Result data
	 * 
	 */
	protected function handle($result, $httpCode) {
		// Check for non-OK statuses
		$codes = explode(",", static::ACCEPTED_CODES);
		if (!in_array($httpCode, $codes)) {
			if ($error = json_decode($result, true)) {
				$error = $error['error'];
			} else {
				$error = $result;
			}
			throw new \Exception($error);
		} else {
			return json_decode($result, true);
		}
	}
	
	/**
	 * Abstract CURL usage.
	 *
	 * @param array $data	Array of parameters
	 * @return array 		Decoded results
	 * 
	 */
	protected function curl($data) {
		// Force data object to array
		$data = $data ? (array) $data : $data;
		
		// Define header values
		$headers = [
			'Content-Type: application/json',
			'Accept: application/json'
		];
		
		// Set up client connection
		$ch = curl_init();
		curl_setopt($ch, CURLOPT_URL, static::API_URL);
		curl_setopt($ch, CURLOPT_HEADER, 0);
		curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
		curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
		
		// Specify the raw post data
		if ($data) {
			curl_setopt($ch, CURLOPT_POST, 1);
			curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
		}
		
		// Send data
		$result = curl_exec($ch);
		$errCode = curl_errno($ch);
		$errText = curl_error($ch);
		$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
		
		// Handle result
		return $this->handle($result, $httpCode);
	}
	
	/**
	 * Package message data for transmission
	 *
	 * @param array $message The message parameters
	 * @return array
	 * 
	 */
	public function sendMessage(array $message) {
		$message['api_key'] = $this->token;
		$message['api_secret'] = $this->secret;
		
		$response = $this->curl($message);
		
		return $response['messages'];
	}
	
	/**
	 * Process SMS response sent by the user to the SMS service
	 * in response to a message sent to the mobile device.
	 *
	 * @param int $toNumber application service number
	 * @param string $message content of the message
	 * @return string $msgid message identifier
	 * 
	 */
	public function smsTransmit($toNumber, &$content, $encoding='') {	
		// Initialize
		$msgid = '';
		$error = '';
		$status = 0;
		
		// Set message parameters
		unset($data);
		$data = array();
		$data['to'] = preg_replace('/[^0-9]/', '', $toNumber);
		$data['from'] = $this->from;
		$data['text'] = $content;
		if($encoding != 'text') $data['type'] = 'unicode';

		//Append Text
		$newContent = TwilioUtilitylib::appendExtraMessagePart($content, array(
			'rawToNumber' =>  $toNumber,
			'rawFromNumber' => $this->from,
			'toNumber' =>  $data['to'],
			'fromNumber' =>  $data['from']
		));

		if(!empty($newContent)) {
			$data['text'] = $newContent;
		}
		
		// Send the message
		try {
			$response = $this->sendMessage($data);
			$status = $response[0]['status'];
			$msgid = $response[0]['message-id'];
		
			if ($status != 0) {
				$error = 'Message rejected [' .$status. '] - ' .$response[0]['error-text'];
			}
		} catch (\Exception $e) {
			$status = $e->getCode();
			$error = 'Message rejected [' .$status. '] - ' .$e->getMessage();
		}
		
		// Record error 
		if (!empty($error)) {
			error_log('wmtNexmo::smsTransmit - ' . $error);
		}
		
		$ret = array('msgid' => $msgid,
		    'status' => $status,
		    'error' => $erro
		);
		return $ret;

	}
	
	/**
	 * Process SMS delivery notification sent by the SMS service when a
	 * previous message has been delivered to the mobile device.
	 *
	 * @param int $msgid message identifier 
	 * @param int $fromNumber mobile device number
	 * @param int $toNumber application service number
	 * @param string $timestamp Y-m-d H:i:s
	 * @param string $event description of event
	 *
	 */
	public function smsDelivery($fromNumber, $toNumber, $msgId, $timestamp, $clientRef, $msgStatus) {
		
		// Clean numbers
		$toNumber = preg_replace("/[^0-9]/", "", $toNumber);
		$fromNumber = preg_replace("/[^0-9]/", "", $fromNumber);
		$msgStatus = strtoupper($msgStatus);
		
		// Validate time
		$delivered = (strtotime($timestamp) === false)? date('Y-m-d H:i') : date('Y-m-d H:i', strtotime($timestamp));
		
		
		// Retrieve original message
		$log_record = sqlQueryNoLog("SELECT * FROM `message_log` WHERE `msg_newid` LIKE ? AND `msg_to`=?", array($msgid, $toNumber));

		// Update existing record
		if (!empty($log_record['id'])) {
			sqlStatementNoLog("UPDATE `message_log` SET `delivered_time`=?, `delivered_status`=? WHERE `id`=?", array($delivered, $msgStatus, $log_record['id']));
		}
		
		return;
		
	}
	
	/**
	 * Process SMS response sent by the user to the SMS service
	 * in response to a message sent to the mobile device.
	 *
	 * @param int $msgId message identifier 
	 * @param int $fromNumber mobile device number
	 * @param int $toNumber application service number
	 * @param string $timestamp Y-m-d H:i:s
	 * @param string $event description of event
	 *
	 */
	public function smsReceived($fromNumber, $toNumber, $msgId, $timestamp, $message) {

		// Clean numbers
		$toNumber = preg_replace("/[^0-9]/", "", $toNumber);
		$fromNumber = preg_replace("/[^0-9]/", "", $fromNumber);

		// Look for matches
		$pids = null;

		$pParam = array();
    	$pParam["phone_cell"] = array("value" => $fromNumber, "condition" => "");
    	$pat_data = getPatientByCondition($pParam, "pid");
		
		if (array_key_exists('pid', $pat_data)) {
			$pids[] = $pat_data['pid'];
		} else if(count($pat_data) > 0) {
			foreach ($pat_data as $pItem) {
				$pids[] = $pItem['pid'];
			}
		}
		
		// Unique patient found
		$pid = (count($pids) == 1)? $pids[0] : '';
			
		// Appears to be a confirmation message
		// Appears to be a confirmation message
		$test = strtoupper($message);
		if ($test == "'C'" || $test == '"C"' || substr($test,0,1) == 'C' || substr($test,0,1) == 'Y') $test = 'C';
		if ($test == "YES" || $test == "OK" || $test == "OKAY" || $test == "SI") $test = 'C';
		if ($test == "'R'" || $test == '"R"' || substr($test,0,1) == 'R' || substr($test,0,1) == 'N') $test = 'R';
		if ($test == "NO") $test = 'R';

		$newMsgId = "";

		if (count($pids) > 0 && ($test == 'C' || $test == 'R') ) {
			
			// Make pid string
			$pid_list = implode(',', $pids);
			
			/* Fetch appointment(s) for patient(s)	
			$sql = "SELECT `pc_eid`, `pc_pid` FROM `openemr_postcalendar_events` ";
			$sql .= "WHERE `pc_pid` IN (" .$pid_list. ") AND `pc_apptstatus` LIKE 'SMSC' ";
			$sql .= "AND `pc_eventDate` > NOW() ORDER BY `pc_eventDate` LIMIT 1";
			$appt = sqlStatementNoLog($sql);
			*/
			
			$sql = "SELECT ope.`pc_pid`, ope.`pc_eid` FROM `message_log` ml ";
			$sql .= "LEFT JOIN `openemr_postcalendar_events` ope ON ml.`eid` = ope.`pc_eid` and ml.`pid` = ope.`pc_pid` ";
			$sql .= "WHERE ml.`type` LIKE 'SMS' AND ml.`direction` LIKE 'out' AND ml.`eid` IS NOT NULL ";
			$sql .= "AND ml.`event` LIKE 'CONFIRM_REQUEST' AND ope.`pc_eventDate` >= DATE(NOW()) AND ope.`pc_eid` IS NOT NULL ";
			$sql .= "AND ope.`pc_pid` IS NOT NULL AND ope.`pc_apptstatus` LIKE 'SMSC' AND ml.`msg_to` = ? ";
			$appt = sqlStatementNoLog($sql, array($fromNumber));
			
			// Unique appointment found
			if (sqlNumRows($appt) == 1) {

				// Inbound confirmation for unique appointment
				$appt_data = sqlFetchArray($appt);
				$this->apptConfirm($appt_data['pc_eid'], $appt_data['pc_pid'], $fromNumber, $toNumber, $msgId, $timestamp, $message);
			
			} else {
				
				// Unable to match unique appointment
				//$message ='Appointment update received for unknown appointment';
				$newMsgId = $this->logSMS('SMS_RECEIVED', $toNumber, $fromNumber, $pid, $msgId, $timestamp, 'SMS_RECEIVED', $message, 'in', true);
				
			}
			
		} else {
		
			// Inbound message from unknown patient
			$newMsgId = $this->logSMS('SMS_RECEIVED', $toNumber, $fromNumber, $pid, $msgId, $timestamp, 'SMS_RECEIVED', $message, 'in', true);
		
		}

		if ($pid != "" && !empty($newMsgId)) {
			TwilioUtilitylib::confirmApp(array(
				'pid' => $pid,
				'fromNumber' => $fromNumber,
				'reg_fromNumber' => $test_string,
				'msg_date' => $timestamp,
				'msg_id' => $newMsgId,
				'text' => $message
			));
		}
		
		return;
		
	}
	
	
	/**
	 * Send appointment notice SMS for appointment specified.
	 * A 'notice' is a send only used when appointment made.
	 *
	 * @param int $eid record identifier for the appointment
	 * @param int $template record identifier for the template
	 * @return string status of message transmission
	 */
	public function apptNotice($eid, $template) {

		// Save parameters
		$this->eid = $eid;
		
		// Validate appointment parameter
		if (empty($eid))
			throw new \Exception('wmtNexmo::ApptNotice - no appointment identifier provided');
		
		// Fetch appointment
		$appt = new Appt($eid);
		
		// Validate appointment date/time
		$appt_date = strtotime(substr($appt->pc_eventDate,0,10));
		if ($appt_date === false)
			throw new \Exception('wmtNexmo::ApptNotice - invalid appointment date/time in record');
		$appt_time = strtotime($appt->pc_startTime);
		if ($appt_time === false)
			throw new \Exception('wmtNexmo::ApptNotice - invalid appointment date/time in record');
			
		// Retrieve data
		$pat_data = Patient::getPidPatient($appt->pc_pid);
		
		// Verify if we should be sending SMS at all
		$toNumber = preg_replace("/[^0-9]/", '', $pat_data->phone_cell);
		if (strlen($toNumber) == 10) $toNumber = "1" . $toNumber;
		if (strlen($toNumber) != 11) $toNumber = '';
		if ($pat_data->hipaa_allowsms != 'YES' || empty($toNumber)) return false;
		
		// Validate template parameter
		if (empty($template))	
			throw new \Exception('wmtNexmo::ApptNotice - missing SMS template name');

		// Fetch template
		$template = Template::Lookup($template, $pat_data->language);
		
		// Fetch merge data
		$data = new Grab($pat_data->language);
		$data->loadData($appt->pc_pid, $appt->pc_aid, $appt->pc_facility, $appt->pc_eid);
		
		// Collect merge tag elements
		$elements = $data->getData();
		if ($appt->pc_alldayevent > 0) $elements['appt_time'] = "ALL DAY";
		
		// Perform data merge
		$template->Merge($elements);
		$content = $template->text_merged;
		
		// Transmit SMS message
		$result = $this->smsTransmit($toNumber, $content);
		$msgId = $result['msgid'];
		
		// Do updates as appropriate
		if ($msgId) {
			$status = 'MESSAGE_SENT';
			sqlStatementNoLog("UPDATE `openemr_postcalendar_events` SET `pc_apptstatus` = ? WHERE `pc_eid` = ?", array('SMSN', $eid));
		} else {
			$status = 'SEND FAILED';
		}
		
		// Record message
		$this->logSMS('APPT_NOTICE', $toNumber, $this->from, $pat_data->pid, $msgId, null, $status, $content, 'out', false);

		return;
		
	}
	
	/**
	 * Send appointment notice SMS for appointment specified.
	 * A 'notice' is a send only used when appointment made.
	 *
	 * @param int $eid record identifier for the appointment
	 * @param int $template record identifier for the template
	 * @return string status of message transmission
	 */
	public function apptReminder($eid, $template, $type='N') {

		// Save parameters
		$this->eid = $eid;
		
		// Validate processing type
		if ($type != 'N' && $type != 'C') $type = 'N';  // default to reminder
		
		// Validate appointment parameter
		if (empty($eid))
			throw new \Exception('wmtNexmo::ApptReminder - no appointment identifier provided');
		
		// Fetch appointment
		$appt = new Appt($eid);
		
		// Validate appointment date/time
		$appt_date = strtotime($appt->pc_eventDate);
		if (!($appt_date))
			throw new \Exception('wmtNexmo::ApptReminder - invalid appointment date/time in record');
		$appt_time = strtotime($appt->pc_startTime);
		if (!($appt_time))
			throw new \Exception('wmtNexmo::ApptReminder - invalid appointment date/time in record');
			
		// Retrieve data
		$pat_data = Patient::getPidPatient($appt->pc_pid);
		
		// Verify if we should be sending SMS at all
		$toNumber = preg_replace("/[^0-9]/", '', $pat_data->phone_cell);
		if (strlen($toNumber) == 10) $toNumber = "1" . $toNumber;
		if (strlen($toNumber) != 11) $toNumber = '';
		if ($pat_data->hipaa_allowsms != 'YES' || empty($toNumber)) return false;
		
		// Validate template parameter
		if (empty($template))	
			throw new \Exception('wmtNexmo::ApptNotice - missing SMS template name');

		// Fetch template
		$template = Template::Lookup($template, $pat_data->language);
		
		// Fetch merge data
		$data = new Grab($pat_data->language);
		$data->loadData($appt->pc_pid, $appt->pc_aid, $appt->pc_facility, $appt->pc_eid);
		
		// Collect merge tag elements
		$elements = $data->getData();
		if ($appt->pc_alldayevent > 0) $elements['appt_time'] = "ALL DAY";
		
		// Perform data merge
		$template->Merge($elements);
		$content = $template->text_merged;
		
		// Transmit SMS message
		$result = $this->smsTransmit($toNumber, $content);
		$msgId = $result['msgid'];
		
		// Do updates as appropriate
		if ($msgId) {
			$status = 'MESSAGE_SENT';
			if ($type == 'C') { // only change status for confirmation request
				sqlStatementNoLog("UPDATE `openemr_postcalendar_events` SET `pc_apptstatus` = ? WHERE `pc_eid` = ?", array('SMS'.$type, $eid));
			}
		} else {
			$status = 'SEND FAILED';
		}
		
		// Record message
		$event = ($type == 'N')? 'APPT_REMINDER' : 'CONFIRM_REQUEST';
		$this->logSMS($event, $toNumber, $this->from, $pat_data->pid, $msgId, null, $status, $content, 'out', false);

		return;
		
	}
	
	/**
	 * Process appointment confirmation associated with the provided refid.
	 *
	 * @param int $eid record identifier for the appointment
	 * @param int $template record identifier for the template
	 * @return string status of message transmission
	 */
	public function apptConfirm($eid, $pid, $fromNumber, $toNumber, $msgId, $timestamp, $message) {
		
		// Save parameters
		$this->eid = $eid;
		$this->pid = $pid;
		
		// Get patient language
		$pat_data = Patient::getPidPatient($pid);
		$lang = (empty($pat_data->language))? 'English' : $pat_data->language;
		
		// Log received record
		$this->logSMS('CONFIRM_RESPONSE', $toNumber, $fromNumber, $pid, $msgId, $timestamp, 'MESSAGE_RECEIVED', $message, 'in', true);
		
		// Fetch appointment
		$appt = new Appt($eid);
		
		// Validate appointment date/time
		$appt_date = strtotime(substr($appt->pc_eventDate, 0, 10));
		if ($appt_date === false)
			throw new \Exception('wmtNexmo::ApptNotice - invalid appointment date/time in record');
		$appt_time = strtotime($appt->pc_startTime);
		if ($appt_time === false)
			throw new \Exception('wmtNexmo::ApptNotice - invalid appointment date/time in record');
			
		// Process response
		$response = strtoupper(substr($message,0,1));
		if ($appt->pc_apptstatus != 'SMSC' && $appt->pc_apptstatus != 'SMSN') {
			
			// Process confirmation
			$response = '';
			$event = 'INVALID_STATUS';
 			$template = Template::Lookup('appt_sms_invalid', $lang);
 			
		} elseif ( $response == 'C') {
			
			// Process confirmation
			$event = 'APPT_CONFIRMED';
 			$template = Template::Lookup('appt_sms_confirmed', $lang);
 			
		} elseif ( $response == 'R' ) {

			// Process reschedule request
			$event = 'APPT_RESCHEDULE';
 			$template = Template::Lookup('appt_sms_reschedule', $lang);
		
		} else {
			
			// Process invalid response
			$response = '';
			$event = 'INVALID_RESPONSE';
 			$template = Template::Lookup('appt_sms_reject', $lang);
			
		}
		
		// Retrieve data
		$pat_data = Patient::getPidPatient($pid);
		
		// Fetch merge data
		$data = new Grab($pat_data->language);
		$data->loadData($appt->pc_pid, $appt->pc_aid, $appt->pc_facility, $appt->pc_eid);
		
		// Collect merge tag elements
		$elements = $data->getData();
		if ($appt->pc_alldayevent > 0) $elements['appt_time'] = "ALL DAY";
		
		// Perform data merge
		$template->Merge($elements);
		$content = $template->text_merged;
		
		// Set message parameters
		$message = array();
		$message['to'] = preg_replace('/[^0-9]/', '', array($fromNumber));
		$message['from'] = preg_replace('/[^0-9]/', '', $this->from);
		$message['content'] = $template->text_merged;
		
		// Transmit SMS message (use fromNumber since responding)
		$result = $this->smsTransmit($fromNumber, $content);
		$msgId = $result['msgid'];
		
		// Do updates as appropriate
		if ($msgId) {
			$status = 'MESSAGE_SENT';
			
			// Confirmed
			if ($response == 'C') {
				sqlStatementNoLog("UPDATE `openemr_postcalendar_events` SET `pc_apptstatus` = ? WHERE `pc_eid` = ?", array('CON', $eid));
			}
			
			// Reschedule
			if ($response == 'R') {
 				// Update appointment record status 
				sqlStatementNoLog("UPDATE `openemr_postcalendar_events` SET `pc_apptstatus` = ? WHERE `pc_eid` = ?", array('SMSR', $eid));
			
	 			// Create an internal message 
				$note = "\n" . $pat_data->format_name ." (pid: ". $pid .") ";
				$note .= "has requested that their appointment for ";
				$note .= strftime("%A, %B %e, %G", $appt_date) . " at ";
				$note .= ($appt->pc_alldayevent > 0) ? "ALL DAY" : date('h:ia', $appt_time);
				$note .= " be cancelled and rescheduled.";
				$date = date('Y-m-d H:i:s');
	 			sqlInsert("INSERT INTO `pnotes` SET `pid`=?, `body`=?, `date`=?, `user`='SYSTEM', `groupname`='Default', `activity`=1, `authorized`=0, `title`='ReSchedule' , `assigned_to`='GRP:appt_cancel', `message_status`='New'", array($pid, $note, $date));
			}
		} else {
			$status = 'SEND FAILED';
		}
		
		// Record message (sending to fromNumber since it is a response)
		$this->logSMS($event, $fromNumber, $this->from, $pat_data->pid, $msgId, null, $status, $content, 'out', false);

		return;
		
	}
	
	
	/**
	 * Send portal lab notice SMS for new results.
	 *
	 * @param int $template record identifier for the template
	 * @return string status of message transmission
	 */
	public function labNotice($pid, $template) {
		
		// Get patient
		$pat_data = Patient::getPidPatient($pid);
		
		// Verify if we should be sending SMS at all
		$toNumber = preg_replace("/[^0-9]/", '', $pat_data->phone_cell);
		if (strlen($toNumber) == 10) $toNumber = "1" . $toNumber;
		if (strlen($toNumber) != 11) $toNumber = '';
		if ($pat_data->hipaa_allowsms != 'YES' || empty($toNumber)) return false;
		
		// Validate template parameter
		if (empty($template))	
			throw new \Exception('wmtNexmo::LabNotice - missing SMS template name');

		// Fetch template
		$template = Template::Lookup($template, $pat_data->language);
		
		// Fetch merge data
		$data = new Grab($pat_data->language);
		$data->loadData($pat_data->pid, $pat_data->providerID);
		
		// Collect merge tag elements
		$elements = $data->getData();
		
		// Perform data merge
		$template->Merge($elements);
		$content = $template->text_merged;
		
		// Transmit SMS message
		$result = $this->smsTransmit($toNumber, $content);
		$msgId = $result['msgid'];
		
		// Record message
		$this->logSMS('LAB_NOTICE', $toNumber, $this->from, $pat_data->pid, $msgId, null, $status, $content, 'out', false);

		return;
		
	}
	
	/**
	 * Queue an SMS for transmitting through the background process.
	 *
	 * @param int $pid record identifier for the patient
	 * @param string $to phone number to send to
	 * @pamam string $msg message contents
	 * @param string $type of message
	 * @param string $status default of 'queued' but can set another if necessary
	 * @return boolean status of queue entry
	 */
	public function queueSMS($pid, $msg, $status = 'Queued') {
	    
	    // Validate patient id parameter
	    if (empty($pid))
	        throw new \Exception('wmtNexmo::queueSMS - no patient identifier provided');
	    
        // Validate patient id parameter
        if (empty($msg))
	            throw new \Exception('wmtNexmo::queueSMS - no message content provided');
        
        self::logSMS('SMS Blast', $this->to, $this->from, $pid, '', '', $status, $msg, 'out', true);
	                    
        return;
	                    
	}
	
	
	/**
	 * Create the SMS text for appointment and template specified.
	 * Intended to be used for things like the blast where the messages are all queued
	 * to be processed and throttled in the background.
	 *
	 * @param int $eid record identifier for the appointment
	 * @param int $template record identifier for the template
	 * @return string message text
	 */
	public function createSMSText($eid='', $pid='', $template='', $raw = FALSE) {
	    
	    // Save parameters
	    $this->eid = $eid;
	    
	    if($pid) {
	        $pat_data = Patient::getPidPatient($pid);
	    } else {
	       // Validate appointment parameter
	       if (empty($eid))
	           throw new \Exception('wmtNexmo::ApptReminder - no appointment identifier provided');
	       $appt = new Appt($eid);
	       $pid = $appt->pc_pid;
	       $pat_data = Patient::getPidPatient($pid);
	    }
	    
        // Retrieve data
        $this->pat_data = $pat_data;
        
        // Fetch merge data
        $data = new Grab($pat_data->language);

        $data->loadAppointment($eid);
        $data->loadPatient($pid, $pat_data);
        $data->loadData(NULL, $data->pc_aid, $data->pc_facility);
	                
        // Verify if we should be sending SMS at all
        $toNumber = preg_replace("/[^0-9]/", '', $pat_data->phone_cell);
        if (strlen($toNumber) == 10) $toNumber = "1" . $toNumber;
        if (strlen($toNumber) != 11 && strlen($toNumber) != 12) $toNumber = '';
        if ($pat_data->hipaa_allowsms != 'YES' || empty($toNumber)) return false;
        $this->to = $toNumber;
	                
        // Validate template parameter
        if (empty($template))
             throw new \Exception('wmtNexmo::ApptNotice - missing SMS template name');
	                    
        // Fetch template
        $template = Template::Lookup($template, $pat_data->language);
	                    
        // Collect merge tag elements
        $elements = $data->getData();
        if ($appt->pc_alldayevent > 0) $elements['appt_time'] = "ALL DAY";
                   
        // Perform data merge
        $template->Merge($elements, $raw);
        $content = $template->text_merged;
                    
        return $content;
	                    
	}
	
	/**
	 * The 'logSMS' method stores a copy of the messages which are exchanged
	 * along with any result parameters which may be returned.
	 */
	public function logSMS($event, $toNumber, $fromNumber, $pid, $msgId, $timestamp, $msg_status, $message, $direction='in', $active=true, $raw_data='') {

		// Create log entry
		$binds = array();
		$binds[] = $event;
		$binds[] = ($active)? '1' : '0';
		$binds[] = $direction;
		$binds[] = 'Pro-Care Bi-Directional';
		$binds[] = $_SESSION['authUserID'];
		$binds[] = (empty($pid))? $this->pid : $pid;
		$binds[] = (empty($this->eid)) ? null : $this->eid;
		$binds[] = $toNumber;
		$binds[] = $fromNumber;
		$binds[] = $msgId; // message id of current message
		$binds[] = (empty($timestamp)) ? date('Y-m-d H:i:s') : $timestamp;
		$binds[] = $msg_status;
		$binds[] = $message;
		$binds[] = $raw_data;

		// Store log record
		$sql = "INSERT INTO `message_log` SET ";
		$sql .= "type='SMS', event=?, activity=?, direction=?, gateway=?, userid=?, pid=?, eid=?, msg_to=?, msg_from=?, msg_newid=?, msg_time=?, msg_status=?, message=?, `raw_data`=?";
		//sqlStatementNoLog($sql, $binds);
		return sqlInsert($sql, $binds);
	}
}
