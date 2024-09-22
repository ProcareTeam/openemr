<?php

require_once("../../globals.php");
require_once($GLOBALS['srcdir'].'/calendar.inc');
require_once($GLOBALS['srcdir'].'/../interface/main/calendar/includes/pnAPI.php');
require_once($GLOBALS['srcdir'].'/../interface/main/calendar/php/calendar_fun.php');

use OpenEMR\Services\Utils\DateFormatterUtils;

$form_hour = isset($_REQUEST['form_hour']) ? $_REQUEST['form_hour'] : "";
$form_minute = isset($_REQUEST['form_minute']) ? $_REQUEST['form_minute'] : "";
$form_date = isset($_REQUEST['form_date']) ? $_REQUEST['form_date'] : "";
$form_provider = isset($_REQUEST['form_provider']) ? $_REQUEST['form_provider'] : "";
$form_ampm = isset($_REQUEST['form_ampm']) ? $_REQUEST['form_ampm'] : "";

$response = array();

if(empty($form_hour) || empty($form_minute) || empty($form_date) || empty($form_provider)) {
	return json_encode($response);
}

$fdate = $form_date ." ".$form_hour.":".$form_minute.":00"." ".$form_ampm;
$dateFormat = DateFormatterUtils::getShortDateFormat() . " " . DateFormatterUtils::getTimeFormat(true);

if(!empty($fdate)) $form_date_obj = \DateTime::createFromFormat($dateFormat, $fdate);

$starting_date = $form_date_obj->format("m/d/Y");
$ending_date = $form_date_obj->format("m/d/Y");
$viewtype = 'day';
$provIDs = array($form_provider);

// start PN
pnInit();

pnModAvailable('PostCalendar');
pnModLoad('PostCalendar', 'user');

$A_EVENTS =& postcalendar_userapi_pcGetEvents(array('start' => $starting_date,'end' => $ending_date, 'viewtype' => $viewtype, 'provider_id' => $provIDs));
$inEvents = getBlockData($A_EVENTS);
$defFacility = getDefaultFacility1($provIDs, $starting_date, $inEvents);

foreach ($defFacility as $provider => $pItems) {
	foreach ($pItems as $pKey => $pItem) {
		$eStartTime = DateTime::createFromFormat('Y-m-d H:i:s', $pItem['startTime']);
		$eEndTime = DateTime::createFromFormat('Y-m-d H:i:s', $pItem['endTime']);

		if($eStartTime <= $form_date_obj && $eEndTime >= $form_date_obj) {
			$response['facility'] = $pItem['facility'];
			break;
		}
	}
}

echo json_encode($response);
exit();