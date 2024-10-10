<?php
/**
 * FHIR Resource Controller example for handling and responding to
 *
 * @package   OpenEMR
 * @link      http://www.open-emr.org
 *
 * @author    Hardik Khatri
 */

namespace Vitalhealthcare\OpenEMR\Modules\Generic\Api;

require_once(dirname(__FILE__, 6) . "/main/calendar/modules/PostCalendar/pnincludes/Date/Calc.php");
require_once(dirname(__FILE__, 7) . "/library/encounter_events.inc.php");
require_once($GLOBALS['fileroot'] . "/controllers/C_Document.class.php");

use OpenEMR\Common\Http\HttpRestRequest;
use OpenEMR\Common\Http\HttpRestRouteHandler;
use OpenEMR\FHIR\R4\FHIRResource\FHIRBundle;
use OpenEMR\FHIR\R4\FHIRResource\FHIRBundle\FHIRBundleEntry;
use OpenEMR\RestControllers\RestControllerHelper;
use OpenEMR\Services\FHIR\FhirResourcesService;
use OpenEMR\Validators\ProcessingResult;
use Psr\Http\Message\ResponseInterface;
use RestConfig;
use Date_Calc;
use Mpdf\Mpdf;
use OpenEMR\Common\Crypto\CryptoGen;

//=================================================================
//  define constants used to make the code more readable
//=================================================================
define('_IS_SUNDAY', 0);
define('_IS_MONDAY', 1);
define('_IS_SATURDAY', 6);
define('_AM_VAL', 1);
define('_PM_VAL', 2);
define('_ACTION_DELETE', 4);
define('_ACTION_EDIT', 2);
define('_EVENT_TEMPLATE', 8);
define('_EVENT_TEMPORARY', -9);
define('_EVENT_APPROVED', 1);
define('_EVENT_QUEUED', 0);
define('_EVENT_HIDDEN', -1);
// $event_repeat
define('NO_REPEAT', 0);
define('REPEAT', 1);
define('REPEAT_ON', 2);
define('REPEAT_DAYS', 3);
// $event_repeat_freq
define('REPEAT_EVERY', 1);
define('REPEAT_EVERY_OTHER', 2);
define('REPEAT_EVERY_THIRD', 3);
define('REPEAT_EVERY_FOURTH', 4);
// $event_repeat_freq_type
if (!defined('REPEAT_EVERY_DAY')) {
    define('REPEAT_EVERY_DAY', 0);
}
if (!defined('REPEAT_EVERY_WEEK')) {
    define('REPEAT_EVERY_WEEK', 1);
}
if (!defined('REPEAT_EVERY_MONTH')) {
    define('REPEAT_EVERY_MONTH', 2);
}
if (!defined('REPEAT_EVERY_YEAR')) {
    define('REPEAT_EVERY_YEAR', 3);
}
if (!defined('REPEAT_EVERY_WORK_DAY')) {
    define('REPEAT_EVERY_WORK_DAY', 4);
}
// $event_repeat_on_num
define('REPEAT_ON_1ST', 1);
define('REPEAT_ON_2ND', 2);
define('REPEAT_ON_3RD', 3);
define('REPEAT_ON_4TH', 4);
define('REPEAT_ON_LAST', 5);
// $event_repeat_on_day
define('REPEAT_ON_SUN', 0);
define('REPEAT_ON_MON', 1);
define('REPEAT_ON_TUE', 2);
define('REPEAT_ON_WED', 3);
define('REPEAT_ON_THU', 4);
define('REPEAT_ON_FRI', 5);
define('REPEAT_ON_SAT', 6);
// $event_repeat_on_freq
define('REPEAT_ON_MONTH', 1);
define('REPEAT_ON_2MONTH', 2);
define('REPEAT_ON_3MONTH', 3);
define('REPEAT_ON_4MONTH', 4);
define('REPEAT_ON_6MONTH', 6);
define('REPEAT_ON_YEAR', 12);
// event sharing values
define('SHARING_PRIVATE', 0);
define('SHARING_PUBLIC', 1);
define('SHARING_BUSY', 2);
define('SHARING_GLOBAL', 3);
// $cat_type
define('TYPE_ON_PATIENT', 0);
define('TYPE_ON_PROVIDER', 1);
define('TYPE_ON_CLINIC', 2);
define('TYPE_ON_THERAPY_GROUP', 3);
// admin defines
define('_ADMIN_ACTION_APPROVE', 0);
define('_ADMIN_ACTION_HIDE', 1);
define('_ADMIN_ACTION_EDIT', 2);
define('_ADMIN_ACTION_VIEW', 3);
define('_ADMIN_ACTION_DELETE', 4);

class GenericRestController
{
    /**
     * @var CustomSkeletonFHIRResourceService
     */
    private $customSkeletonResourceService;

    /**
     * @var FhirResourcesService
     */
    private $fhirService;

    public function __construct()
    {
        $this->fhirService = new FhirResourcesService();
    }

    
    public function getFacilityProviderData(HttpRestRequest $request)
    {

        $result = $this->getFacilityProviderDataAll($request->getQueryParams());
        return $result;
    }

    public function getFacilityProviderDataAll($searchParams, $puuidBind = null)
    {
        $processingResult = new ProcessingResult();
        $resData = array(
            'Locations' => array(), 
            'Providers' => array(), 
            'Services' => array()
        );

        $facilityData = sqlStatement("SELECT * FROM facility f WHERE allowed_to_booked_online = 1", array());
        while ($frow = sqlFetchArray($facilityData)) {
            $resData['Locations'][] = array(
                'LocationID' => isset($frow['id']) ? $frow['id'] : "",
                'Header' => isset($frow['name1']) ? $frow['name1'] : "",
                'Description' => isset($frow['name']) ? $frow['name'] : "",
                'Street' => isset($frow['street']) ? $frow['street'] : "",
                'City' => isset($frow['city']) ? $frow['city'] : "",
                'State' => isset($frow['state']) ? $frow['state'] : "",
                'PostalCode' => isset($frow['postal_code']) ? $frow['postal_code'] : "",
                'Active' => "1"
            );
        }

        $providerData = sqlStatement("SELECT u.*, f.name, lo.title as physician_type_title FROM users u left join facility f on f.id = u.facility_id left join list_options lo on lo.option_id = u.physician_type and lo.list_id = 'physician_type' WHERE u.allowed_to_booked_online = 1", array());
        while ($prow = sqlFetchArray($providerData)) {

            $pname = array();
            if(!empty($prow['fname'])) $pname[] = $prow['fname'];
            if(!empty($prow['mname'])) $pname[] = $prow['mname'];
            if(!empty($prow['lname'])) $pname[] = $prow['lname'];

            $faData = array(
                'LocationID' => isset($prow['facility_id']) ? $prow['facility_id'] : "",
                'ProviderID' => isset($prow['id']) ? $prow['id'] : "",
                'Name' => !empty($pname) ? implode(" ", $pname) : "",
                'Provider Type' => isset($prow['physician_type_title']) ? $prow['physician_type_title'] : "",
                'Specialization' => isset($prow['specialization']) ? $prow['specialization'] : "",
                'Active' => isset($prow['active']) ? $prow['active'] : ""
            );

            $resData['Providers'][] = $faData;

            $otherFacilityData = sqlStatement("SELECT ope.* from openemr_postcalendar_events ope join openemr_postcalendar_categories opc on ope.pc_catid = opc.pc_catid where opc.pc_constant_id in ('in_office', 'out_of_office') and ope.pc_eventDate >= date(now()) and ope.pc_aid = ? and pc_facility != ? group by pc_facility order by ope.pc_eid desc", array($prow['id'], $prow['facility_id']));
            while ($ofrow = sqlFetchArray($otherFacilityData)) {
                $faData['LocationID'] = isset($ofrow['pc_facility']) ? $ofrow['pc_facility'] : "";
                $resData['Providers'][] = $faData;
            }
        }

        $servicesData = array();
        /*
        $servicesData = sqlStatement("SELECT u.* from users u WHERE u.allowed_to_booked_online = 1 AND u.user_services != ''", array());
        while ($srow = sqlFetchArray($servicesData)) {
            $uServices = isset($srow['user_services']) ? explode("|", $srow['user_services']) : array();
            $uServices = array_filter($uServices);
            $uServicesStr = !empty($uServices) ? "'" .implode("','", $uServices) . "'" : "";

            $soData = sqlStatement("SELECT lo.* from list_options lo where list_id = 'User_Services' and option_id IN (" . $uServicesStr . ")", array());
            while ($sorow = sqlFetchArray($soData)) {
                $resData['Services'][] = array(
                    'ProviderID' => isset($srow['id']) ? $srow['id'] : "",
                    'ServiceID' => isset($sorow['option_id']) ? $sorow['option_id'] : "",
                    'ServiceName' => isset($sorow['title']) ? $sorow['title'] : "",
                    'Active' => isset($sorow['activity']) ? $sorow['activity'] : ""
                );
            }
            
        }*/

        $processingResult->addData($resData);

        $responseBody = RestControllerHelper::handleProcessingResult($processingResult, null, 200);
        return $responseBody;
    }

    public function getDatesFromRange($start, $end, $format = 'Y-m-d') {
        $array = array();
        $interval = new \DateInterval('P1D');

        $realEnd = new \DateTime($end);
        $realEnd->add($interval);

        $period = new \DatePeriod(new \DateTime($start), $interval, $realEnd);

        foreach($period as $date) { 
            $array[] = $date->format($format); 
        }

        return $array;
    }

    public function calculateEvents($days, $events, $viewtype = 'day', $cdate)
    {
      //
        $date = date('Ymd', strtotime($cdate));
        $cy = substr($date, 0, 4);
        $cm = substr($date, 4, 2);
        $cd = substr($date, 6, 2);

      // here the start_date value is set to whatever comes in
      // on postcalendar_getDate() which is not always the first
      // date of the days array -- JRM
        $start_date = "$cy-$cm-$cd";

      // here we've made the start_date equal to the first date
      // of the days array, makes sense, right? -- JRM
        $days_keys = array_keys($days);
        $start_date = $days_keys[0];
        $day_number = count($days_keys);

      // Optimization of the stop date to not be much later than required.
        $tmpsecs = strtotime($start_date);
        if ($viewtype == 'day') {
            $tmpsecs +=  3 * 24 * 3600;
        } elseif ($viewtype == 'week') {
            $tmpsecs +=  9 * 24 * 3600;
        } elseif ($viewtype == 'month') {
            if ($day_number > 35) {
                $tmpsecs = strtotime("+41 days", $tmpsecs); // Added for 6th row by epsdky 2017
            } else {
                $tmpsecs = strtotime("+34 days", $tmpsecs);
            }
        } else {
            $tmpsecs += 367 * 24 * 3600;
        }

        $last_date = date('Y-m-d', $tmpsecs);

        foreach ($events as $event) {
            $eventD = $event['pc_eventDate'];
            $eventS = $event['pc_startTime'];

            switch ($event['pc_recurrtype']) {
                //==============================================================
                //  Events that do not repeat only have a startday
                //==============================================================
                case NO_REPEAT:
                    if (isset($days[$event['pc_eventDate']])) {
                        array_push($days[$event['pc_eventDate']], $event);
                    }
                    break;

                //==============================================================
                //  Find events that repeat at a certain frequency
                //  Every,Every Other,Every Third,Every Fourth
                //  Day,Week,Month,Year,MWF,TR,M-F,SS
                //==============================================================
                case REPEAT:
                case REPEAT_DAYS:
                    // Stop date selection code modified and moved here by epsdky 2017 (details in commit)
                    if ($last_date > $event['pc_endDate']) {
                        $stop = $event['pc_endDate'];
                    } else {
                        $stop = $last_date;
                    }

                    list($esY,$esM,$esD) = explode('-', $event['pc_eventDate']);
                    $event_recurrspec = @unserialize($event['pc_recurrspec'], ['allowed_classes' => false]);

                    // if (checkEvent($event['pc_recurrtype'], $event_recurrspec)) {
                    //     break;
                    // }

                    $rfreq = $event_recurrspec['event_repeat_freq'];
                    $rtype = $event_recurrspec['event_repeat_freq_type'];
                    $exdate = $event_recurrspec['exdate']; // this attribute follows the iCalendar spec http://www.ietf.org/rfc/rfc2445.txt

                    // we should bring the event up to date to make this a tad bit faster
                    // any ideas on how to do that, exactly??? dateToDays probably.
                    $nm = $esM;
                    $ny = $esY;
                    $nd = $esD;
                    $occurance = Date_Calc::dateFormat($nd, $nm, $ny, '%Y-%m-%d');
                    while ($occurance < $start_date) {
                        $occurance =& __increment($nd, $nm, $ny, $rfreq, $rtype);
                        list($ny,$nm,$nd) = explode('-', $occurance);
                    }

                    while ($occurance <= $stop) {
                        if (isset($days[$occurance])) {
                            // check for date exceptions before pushing the event into the days array -- JRM
                            $excluded = false;
                            if (isset($exdate)) {
                                foreach (explode(",", $exdate) as $exception) {
                                    // occurrance format == yyyy-mm-dd
                                    // exception format == yyyymmdd
                                    if (preg_replace("/-/", "", $occurance) == $exception) {
                                        $excluded = true;
                                    }
                                }
                            }

                            // push event into the days array
                            if ($excluded == false) {
                                array_push($days[$occurance], $event);
                            }
                        }

                        $occurance =& __increment($nd, $nm, $ny, $rfreq, $rtype);
                        list($ny,$nm,$nd) = explode('-', $occurance);
                    }
                    break;

                //==============================================================
                //  Find events that repeat on certain parameters
                //  On 1st,2nd,3rd,4th,Last
                //  Sun,Mon,Tue,Wed,Thu,Fri,Sat
                //  Every N Months
                //==============================================================
                case REPEAT_ON:
                    // Stop date selection code modified and moved here by epsdky 2017 (details in commit)
                    if ($last_date > $event['pc_endDate']) {
                        $stop = $event['pc_endDate'];
                    } else {
                        $stop = $last_date;
                    }

                    list($esY,$esM,$esD) = explode('-', $event['pc_eventDate']);
                    $event_recurrspec = @unserialize($event['pc_recurrspec'], ['allowed_classes' => false]);

                    if (checkEvent($event['pc_recurrtype'], $event_recurrspec)) {
                        break;
                    }

                    $rfreq = $event_recurrspec['event_repeat_on_freq'];
                    $rnum  = $event_recurrspec['event_repeat_on_num'];
                    $rday  = $event_recurrspec['event_repeat_on_day'];
                    $exdate = $event_recurrspec['exdate']; // this attribute follows the iCalendar spec http://www.ietf.org/rfc/rfc2445.txt

                    //==============================================================
                    //  Populate - Enter data into the event array
                    //==============================================================
                    $nm = $esM;
                    $ny = $esY;
                    $nd = $esD;

                    if (isset($event_recurrspec['rt2_pf_flag']) && $event_recurrspec['rt2_pf_flag']) {
                        $nd = 1; // Added by epsdky 2016.
                    }

                    // $nd will sometimes be 29, 30 or 31 and if used in the mktime functions
                    // below a problem with overfow will occur so it is set to 1 to prevent this.
                    // (for rt2 appointments set prior to fix it remains unchanged). This can be done
                    // since $nd has no influence past the mktime functions - epsdky 2016.

                    // make us current
                    while ($ny < $cy) {
                        $occurance = date('Y-m-d', mktime(0, 0, 0, $nm + $rfreq, $nd, $ny));
                        list($ny,$nm,$nd) = explode('-', $occurance);
                    }

                    // populate the event array
                    while ($ny <= $cy) {
                        $dnum = $rnum; // get day event repeats on
                        do {
                            $occurance = Date_Calc::NWeekdayOfMonth($dnum--, $rday, $nm, $ny, $format = "%Y-%m-%d");
                        } while ($occurance === -1);

                        if (isset($days[$occurance]) && $occurance <= $stop) {
                            // check for date exceptions before pushing the event into the days array -- JRM
                            $excluded = false;
                            if (isset($exdate)) {
                                foreach (explode(",", $exdate) as $exception) {
                                    // occurrance format == yyyy-mm-dd
                                    // exception format == yyyymmdd
                                    if (preg_replace("/-/", "", $occurance) == $exception) {
                                        $excluded = true;
                                    }
                                }
                            }

                            // push event into the days array
                            if ($excluded == false) {
                                array_push($days[$occurance], $event);
                            }
                        }

                        $occurance = date('Y-m-d', mktime(0, 0, 0, $nm + $rfreq, $nd, $ny));
                        list($ny,$nm,$nd) = explode('-', $occurance);
                    }
                    break;
            } // <- end of switch($event['recurrtype'])
        } // <- end of foreach($events as $event)
        return $days;
    }

    public function getSlotTimeData(HttpRestRequest $request) {
        $searchParams = $request->getQueryParams();
        $processingResult = new ProcessingResult();
        $resData = array(
            '1' => array()
        );
        $slotTime = array();

        $dateFrom = $searchParams['StartDate'];
        $dateTo = $searchParams['EndDate'];

        $paramDates = $this->getDatesFromRange($dateFrom, $dateTo);
        $providerId = $searchParams['ProviderID'];
        $locationId = isset($searchParams['LocationID']) ? $searchParams['LocationID'] : "";
        $providerName = "";
        $calendarInterval = 30;

        if(isset($searchParams['Interval'])) $calendarInterval = $searchParams['Interval'];

        if(!empty($providerId) && is_array($paramDates)) {
            $providerData = sqlQuery("SELECT CONCAT(CONCAT_WS(' ', IF(LENGTH(u.fname),u.fname,NULL), IF(LENGTH(u.lname),u.lname,NULL))) as provider_name, 30 as calendar_interval from users u where id = ?", array($providerId));
            if(!empty($providerData) && isset($providerData['provider_name'])) $providerName = $providerData['provider_name'];
            //if(!empty($providerData) && isset($providerData['calendar_interval'])) $calendarInterval = $providerData['calendar_interval'];

            $whereDateStr = array();
            $binds = array($providerId);
            $whereStr1 = "";

            if(!empty($locationId)) {
                $whereStr1 .= " and pc_facility = " . $locationId;
            }

            foreach ($paramDates as $pDate) {
                $whereDateStr[] = "((ope.pc_eventDate = ? and ope.pc_endDate = '0000-00-00' ) OR (ope.pc_eventDate <= ? and ope.pc_endDate >= ?))";
                $binds[] = $pDate;
                $binds[] = $pDate;
                $binds[] = $pDate;
            }

            if(!empty($whereDateStr)) {
                $whereDateStr = implode(" OR ", $whereDateStr);
                $whereDateStr = " AND ( " . $whereDateStr . " ) ";
            }

            $apptSqlQuery = sqlStatement("select ope.*, opc.pc_cattype, opc.pc_catname from openemr_postcalendar_events ope left join openemr_postcalendar_categories opc on opc.pc_catid = ope.pc_catid where ope.pc_aid = ? " . $whereStr1 . " " . $whereDateStr . " order by ope.pc_eid desc",  $binds);

            $allApptList = array();
            while ($arow = sqlFetchArray($apptSqlQuery)) {
                unset($arow['uuid']);

                foreach ($paramDates as $pDate) {
                    $appt_pc_eventDate = strtotime($arow['pc_eventDate']);
                    $appt_pc_endDate = strtotime($arow['pc_endDate']);
                    $pDateTime = strtotime($pDate);

                    if(($appt_pc_eventDate == $pDateTime && $arow['pc_endDate'] == '0000-00-00') || ( $appt_pc_eventDate <= $pDateTime &&  $appt_pc_endDate >= $pDateTime )) {

                        if(!isset($allApptList[$pDate])) {
                            $allApptList[$pDate] = array();
                        }

                        // Assign
                        $allApptList[$pDate][] = $arow;
                    }

                }
            }

            foreach ($paramDates as $pDate) {
                //$binds = array($providerId, $pDate, $pDate, $pDate);

                //$whereStr = "";
                //$whereStr1 = "";
                //if(!empty($locationId)) {
                //    $whereStr1 .= " and pc_facility = " . $locationId;
                    //$binds[] = $locationId;
                //}

                //$apptData = sqlStatement("select ope.*, opc.pc_cattype, opc.pc_catname from openemr_postcalendar_events ope left join openemr_postcalendar_categories opc on opc.pc_catid = ope.pc_catid where ope.pc_aid = ? and ((ope.pc_eventDate = ? and ope.pc_endDate = '0000-00-00' " . $whereStr1 . " ) OR (ope.pc_eventDate <= ? and ope.pc_endDate >= ?)) ".$whereStr." order by ope.pc_eid desc", $binds);

                $inBlocks = array();
                $outBlocks = array();
                $otherBlocks = array();
                $apptData = array();

                list($sm,$sd,$sy) = explode('/', date('m/d/Y', strtotime($pDate)));
                list($em,$ed,$ey) = explode('/', date('m/d/Y', strtotime($pDate)));
                $days = array();
                $sday = Date_Calc::dateToDays($sd, $sm, $sy);
                $eday = Date_Calc::dateToDays($ed, $em, $ey);
                 
                for ($cday = $sday; $cday <= $eday; $cday++) {
                    $d = Date_Calc::daysToDate($cday, '%d');
                    $m = Date_Calc::daysToDate($cday, '%m');
                    $y = Date_Calc::daysToDate($cday, '%Y');
                    $store_date = Date_Calc::dateFormat($d, $m, $y, '%Y-%m-%d');
                    $days[$store_date] = array();
                }

                // while ($arow = sqlFetchArray($apptData)) {
                //     $appData[] = $arow;
                // }

                $apptData = isset($allApptList[$pDate]) ? $allApptList[$pDate] : array();
                
                $newData = $this->calculateEvents($days, $apptData, 'day', $pDate);

                foreach ($newData as $dDate => $dItems) {
                    foreach ($dItems as $apptrow) {
                        if($apptrow['pc_cattype'] == '1') {
                            if($apptrow['pc_catname'] == "In Office") {
                                $recurrspec = isset($apptrow['pc_recurrspec']) ? unserialize($apptrow['pc_recurrspec']) : array();
                                $exDate = isset($recurrspec['exdate']) && !empty($recurrspec['exdate']) ? explode(",", $recurrspec['exdate']) : array();

                                if(in_array(date('Ymd', strtotime($pDate)), $exDate)) {
                                    continue;
                                }
                                
                                $inBlocks[] = $apptrow;
                            }

                            if($apptrow['pc_catname'] == "Out Of Office") {
                                $outBlocks[] = $apptrow;
                            }

                            if($apptrow['pc_catname'] == "Lunch" || $apptrow['pc_catname'] == "Vacation" || $apptrow['pc_catname'] == "Reserved") {
                                $otherBlocks[] = $apptrow;
                            }
                        } else {
                            $otherBlocks[] = $apptrow;
                        }
                    }
                }

                $schedule_end = $GLOBALS['schedule_end'];
                $eTime = ''; 
                for ($minutes = $GLOBALS['calendar_interval']; $minutes < 60; $minutes += $GLOBALS['calendar_interval']) {
                    $eTime = $schedule_end . ':' . $minutes .':'. '00';
                }

                $sTime = '';
                foreach ($inBlocks as $ibk => $iItem) {
                    if(isset($iItem['pc_startTime'])) {
                        if(empty($sTime)) $sTime = $iItem['pc_startTime'];
                        if(date('H:i:s', strtotime($iItem['pc_startTime'])) < date('H:i:s', strtotime($sTime))) $sTime = $iItem['pc_startTime'];
                    }
                }

                foreach ($outBlocks as $obk => $oItem) {
                    if(isset($oItem['pc_startTime'])) {
                        if(empty($eTime)) $eTime = $oItem['pc_startTime'];
                        if(date('H:i:s', strtotime($oItem['pc_startTime'])) < date('H:i:s', strtotime($eTime))) $eTime = $oItem['pc_startTime'];
                    }
                }

                $otBlocks = array();
                foreach ($otherBlocks as $otk => $otItem) {
                    if(isset($otBlocks[strtotime($otItem['pc_startTime'])])) {
                        $cItem = $otBlocks[strtotime($otItem['pc_startTime'])];
                        $cSTime = $cItem['pc_startTime'];    
                        $cETime = $cItem['pc_endTime'];

                        if(date('H:i:s', strtotime($cETime)) < date('H:i:s', strtotime($otItem['pc_endTime']))) {
                            $otBlocks[strtotime($otItem['pc_startTime'])] = $otItem;
                        }

                        continue;
                    }


                    $otBlocks[strtotime($otItem['pc_startTime'])] = $otItem;      
                }

                unset($otherBlocks);
                ksort($otBlocks);

                $allocatedSlotTime = array();
                if(!empty($sTime) && !empty($eTime)) {
                    foreach ($otBlocks as $otItem) {
                        $sti = isset($otItem['pc_startTime']) ? $otItem['pc_startTime'] : '';
                        $eti = isset($otItem['pc_endTime']) ? $otItem['pc_endTime'] : '';

                        if(!empty($sti) && !empty($eti)) {
                            foreach ($otBlocks as $otItem_1) {
                                if($otItem['pc_eid'] == $otItem_1['pc_eid']) continue;

                                $sti_1 = isset($otItem_1['pc_startTime']) ? $otItem_1['pc_startTime'] : '';
                                $eti_1 = isset($otItem_1['pc_endTime']) ? $otItem_1['pc_endTime'] : '';

                                if(!empty($sti_1) && !empty($eti_1)) {
                                    if(date('H:i:s', strtotime($sti)) < date('H:i:s', strtotime($sti_1)) && date('H:i:s', strtotime($eti)) > date('H:i:s', strtotime($sti_1)) && date('H:i:s', strtotime($eti)) < date('H:i:s', strtotime($eti_1))) {
                                        $eti = $eti_1;
                                    }

                                  if(date('H:i:s', strtotime($sti)) < date('H:i:s', strtotime($eti_1)) && date('H:i:s', strtotime($eti)) > date('H:i:s', strtotime($eti_1)) && date('H:i:s', strtotime($sti)) > date('H:i:s', strtotime($sti_1))) {
                                        $sti = $sti_1;
                                  } 

                                  
                                }
                            }
                        }

                        if(!empty($sti) && !empty($eti)) {
                            $allocatedSlotTime[$sti."_".$eti] = array('start' => $sti, 'end' => $eti);
                        }
                    }

                    foreach ($allocatedSlotTime as $akey => $aItem) {
                        $a_sti = isset($aItem['start']) ? $aItem['start'] : '';
                        $a_eti = isset($aItem['end']) ? $aItem['end'] : '';

                        if(!empty($a_sti) && !empty($a_eti)) {
                            $a_sti_t = date('H:i:s', strtotime($a_sti));
                            $a_eti_t = date('H:i:s', strtotime($a_eti));

                            foreach ($allocatedSlotTime as $akey_1 => $aItem_1) {
                                $a_sti_1 = isset($aItem_1['start']) ? $aItem_1['start'] : '';
                                $a_eti_1 = isset($aItem_1['end']) ? $aItem_1['end'] : '';

                                if(!empty($a_sti_1) && !empty($a_eti_1)) {
                                    $a_sti_t_1 = date('H:i:s', strtotime($a_sti_1));
                                    $a_eti_t_1 = date('H:i:s', strtotime($a_eti_1));

                                    if(($a_sti_t_1 < $a_sti_t && $a_eti_t_1 > $a_sti_t) && ($a_sti_t_1 <= $a_eti_t && $a_eti_t_1 >= $a_eti_t)) {
                                        unset($allocatedSlotTime[$akey]);
                                    } else if ($a_sti_t < $a_sti_t_1 && $a_eti_t > $a_sti_t_1 && $a_eti_t < $a_eti_t_1) {

                                        $allocatedSlotTime[$a_sti . "_" . $a_eti_1] = array('start' => $a_sti, 'end' => $a_eti_1);

                                        unset($allocatedSlotTime[$akey]);
                                        unset($allocatedSlotTime[$akey_1]);

                                    } else if($a_sti_t < $a_eti_t_1 && $a_eti_t > $a_eti_t_1 && $a_sti_t > $a_sti_t_1) {

                                        $allocatedSlotTime[$a_sti_t_1 . "_" . $a_eti] = array('start' => $a_sti_t_1, 'end' => $a_eti);

                                        unset($allocatedSlotTime[$akey]);
                                        unset($allocatedSlotTime[$akey_1]);
                                    }
                                }

                            }
                        }
                    }
                    
                    // echo '<pre>';
                    // print_r($allocatedSlotTime);
                    // echo '</pre>';

                    $unallocatedSlotTime = array();

                    $sDateTime = date('Y-m-d H:i:s', strtotime($pDate ." ".$sTime));
                    $eDateTime = date('Y-m-d H:i:s', strtotime($pDate ." ".$eTime));

                    if(!empty($sDateTime) && !empty($eDateTime)) {
                        $nSlotStartTime = $sDateTime;
                        foreach ($allocatedSlotTime as $asItem) {
                            $aendtime = preg_match("/^(?:2[0-3]|[01][0-9]):[0-5][0-9]:[0-5][0-9]$/", $asItem['end']) ? $asItem['end'] : '23:59:00';
                            $aslst = date('Y-m-d H:i:s', strtotime($pDate ." ".$asItem['start']));
                            $aslet = date('Y-m-d H:i:s', strtotime($pDate ." ".$aendtime));

                            if(!empty($aslst) && !empty($aslet)) {
                               $unallocatedSlotTime[] = array('start' => $nSlotStartTime, 'end' => $aslst);
                               $nSlotStartTime = $aslet;
                            }
                        }

                        if(!empty($unallocatedSlotTime) && $nSlotStartTime <= $eDateTime) {
                            $unallocatedSlotTime[] = array('start' => $nSlotStartTime, 'end' => $eDateTime);
                        }
                    }

                    $interval = isset($calendarInterval) ? ($calendarInterval * 60) : 900; // 15min;
                    $mininterval = $interval / 60;

                    $nslt = strtotime($sDateTime);
                    for ($slt=$nslt + $interval; $slt <= strtotime($eDateTime); $slt += $interval) { 
                        $slst = date('Y-m-d H:i:s', $nslt);
                        $slet = date('Y-m-d H:i:s', $slt);

                        foreach ($unallocatedSlotTime as $unItem) {
                            $aslst = date('Y-m-d H:i:s', strtotime($unItem['start']));
                            $aslet = date('Y-m-d H:i:s', strtotime($unItem['end']));

                            if(($aslst <= $slst && $aslet > $slst) || ($aslst < $slet && $aslet > $slet)) {
                                $sdiff = strtotime($aslst) - strtotime($slst);
                                $ediff = strtotime($slet) - strtotime($aslet);
                                $tslst = $slst;
                                $tslet = $slet;
                                $uStatus = false;

                                if($interval > $sdiff && $sdiff > 0) {
                                    $tslst = $aslst;
                                    $uStatus = true;
                                }

                                if($interval > $ediff && $ediff > 0) {
                                    $tslet = $aslet;
                                    $uStatus = true;
                                }

                                $slotTime[] = array('start' => date('Y-m-d\TH:i:s', strtotime($tslst)), 'end' => date('Y-m-d\TH:i:s', strtotime($tslet)));
                            }
                        }

                        if(empty($unallocatedSlotTime)) {
                            $slotTime[] = array('start' => date('Y-m-d\TH:i:s', strtotime($slst)), 'end' => date('Y-m-d\TH:i:s', strtotime($slet)));
                        }

                        $nslt = $slt;
                    }

                }
            }
        }

        if(isset($resData["1"])) {
            $resData["1"][] = array('Label' => $providerName, 'date' => $slotTime, 'timeslot' => $calendarInterval);
        }

        $processingResult->addData($resData);

        $responseBody = RestControllerHelper::handleProcessingResult($processingResult, null, 200);
        return $responseBody;
    }

    public function getVisithistory(HttpRestRequest $request) {
        global $srcdir, $get_items_only;

        $searchParams = $request->getQueryParams();
        $processingResult = new ProcessingResult();

        try {

            // Check PatientId
            if(!isset($searchParams['PatientId']) || empty($searchParams['PatientId'])) {
                throw new \Exception("Emtpy PatientId");
            }

            // Check DateFrom
            if(!isset($searchParams['DateFrom']) || empty($searchParams['DateFrom'])) {
                //throw new \Exception("Emtpy DateFrom");
            }

            // Check DateTo
            if(!isset($searchParams['DateTo']) || empty($searchParams['DateTo'])) {
                //throw new \Exception("Emtpy DateTo");
            }

            $pdata = sqlQuery("SELECT pd.pid, pd.pubpid from patient_data pd where pd.pubpid = ?", array($searchParams['PatientId']));

            // If patient data empty
            if(empty($pdata)) {
                throw new \Exception("Patient data not found");
            }

            $pid = $pdata['pid'];
            $_GET['enh_clinical'] = 1;
            $attendant_type = "pid";
            $get_items_only = true;

            $filter_param = array();

            if(isset($searchParams['DateFrom']) && !empty($searchParams['DateFrom'])) {
                $filter_param['date_start'] = $searchParams['DateFrom'];
            }

            if(isset($searchParams['DateTo']) && !empty($searchParams['DateTo'])) {
                $filter_param['date_end'] = $searchParams['DateTo'];
            }

            if (isset($searchParams['PageSize'])) {
                $_GET['pagesize'] = $searchParams['PageSize'];
            } else {
                $_GET['pagesize'] = 0;
            }

            if (isset($searchParams['SortDirection'])) {
                $_GET['sortdirection'] = $searchParams['SortDirection'];
            }

            // $filter_param = array(
            //     'date_start' => $searchParams['DateFrom'],
            //     'date_end' => $searchParams['DateTo']
            // );

            // Fetch visit history items data
            ob_start();
            require_once(dirname(__FILE__, 7) . "/interface/patient_file/history/encounters.php");
            $c = ob_get_clean();

            //$visit_history_items = $this->generateFormPDF($visit_history_items, $pid);

            foreach ($visit_history_items as $vhk => $vhItem) {

                if (isset($vhItem['type']) && isset($vhItem['encounter_id']) && $vhItem['type'] == "encounter") {
                    $eSql = "SELECT FE.encounter, E.id, E.tid, E.table, E.uid, E.datetime, E.is_lock, E.amendment, E.hash, E.signature_hash FROM form_encounter FE LEFT JOIN esign_signatures E ON  FE.encounter = E.tid AND E.is_lock = 1 WHERE FE.encounter = ? ORDER BY E.datetime ASC";
                    $esign_result = sqlQuery($eSql, array($vhItem['encounter_id'] ?? ""));

                    $vhItem['is_signed'] = !empty($esign_result) && isset($esign_result['is_lock']) && $esign_result['is_lock'] == "1" ? true : false;
                }

                // Add visit history item data
                $processingResult->addData($vhItem);
            }

        } catch (\Exception $e) {
            // Add Internal error
            $processingResult->addInternalError($e->getMessage());
        }

        $responseBody = RestControllerHelper::handleProcessingResult($processingResult, null, 200);
        return $responseBody;
    }

    public function getVisithistoryItem(HttpRestRequest $request) {
        global $srcdir, $get_items_only;

        $searchParams = $request->getQueryParams();
        $requestBodyParam = $request->getRequestBodyJSON();
        $processingResult = new ProcessingResult();

        try {

            $itemData = array();

            // Check Type
            if (empty($searchParams['Type'] ?? "")) {
                throw new \Exception("Emtpy Type");
            }

            // Check Id
            if ($searchParams['Type'] != "batch") {
                if (empty($searchParams['Id'] ?? 0)) {
                    throw new \Exception("Emtpy Id");
                }
            }

            // Check items
            if ($searchParams['Type'] == "batch") {
                if (empty($searchParams['PatientId'] ?? 0)) {
                    throw new \Exception("Emtpy PatientID");
                }

                if (empty($requestBodyParam['items'] ?? 0)) {
                    throw new \Exception("Emtpy Items");
                }
            }

            if ($searchParams['Type'] == "batch") {
                // Get patient data
                $pdata = sqlQuery("SELECT pd.pid, pd.pubpid from patient_data pd where pd.pubpid = ?", array($searchParams['PatientId']));

                if (empty($pdata) || empty($pdata['pid'])) {
                    throw new \Exception("Patient Not Found");
                }

                // Set document item
                $itemData = array(
                    "type" => "batch",
                    "pdf_data" => array()
                );

                $formCount = 0;

                foreach ($requestBodyParam['items'] as $bItem) {
                    if (isset($bItem->type) && isset($bItem->id)) {
                        // Get encounter data
                        if ($bItem->type == "encounter") {
                            // Get encounter data
                            $encounterData = sqlQuery("SELECT fe.* FROM form_encounter AS fe WHERE fe.encounter = ? ORDER BY fe.date DESC, fe.id;", array($bItem->id));

                            if (!empty($encounterData)) {
                                $encarr = getFormByEncounter($encounterData["pid"] ?? 0, $encounterData["encounter"] ?? 0, "formdir, user, form_name, form_id, deleted", "", "date asc");

                                foreach ($encarr as $enc) {
                                    if ($enc['formdir'] == 'newpatient' || $enc['formdir'] == 'newGroupEncounter') {
                                        $itemData["pdf_data"][$enc['formdir'] . "_" . $enc['form_id']] = $encounterData['encounter'];
                                        $formCount++;
                                        continue;
                                    }

                                    $itemData["pdf_data"][$enc['formdir'] . "_" . $enc['form_id']] = $encounterData['encounter'];

                                    $formCount++;
                                }
                            }

                        } else if ($bItem->type == "document") {
                            if (!empty($bItem->id)) {
                                $itemData["pdf_data"]['doc_' . $bItem->id] = $bItem->id;
                                $formCount++;
                            }
                        }
                    }
                }

                if ($formCount > 0) {
                    $itemData = $this->generateFormPDF(array($itemData), $pdata['pid'] ?? 0);
                    $itemData = count($itemData) == 1 ? $itemData[0] : array();
                } else {
                    $itemData["pdf_data"] = "";
                }

            } else if ($searchParams['Type'] == "document") {
                $d = new \Document($searchParams['Id']);
                $docObj = new \C_Document();
                $documentContent = $docObj->retrieve_action($d->foreign_id, $d->id, true, true, true);

                if(empty($d->id) || empty($documentContent)) {
                    // If document not exits
                    throw new \Exception("Item not found");
                }

                // Set document item
                $itemData = array(
                    "type" => "document",
                    "document" => "data:" . $d->mimetype . ";base64," . base64_encode($documentContent)
                );
            } else if ($searchParams['Type'] == "encounter") {
                // Get encounter data
                $encounterData = sqlQuery("SELECT fe.* FROM form_encounter AS fe WHERE fe.encounter = ? ORDER BY fe.date DESC, fe.id;", array($searchParams['Id']));

                if (empty($encounterData)) {
                    // If encounter not found
                    throw new \Exception("Encounter Item not found");
                }

                $encarr = getFormByEncounter($encounterData["pid"] ?? 0, $encounterData["encounter"] ?? 0, "formdir, user, form_name, form_id, deleted", "", "date asc");

                // Set document item
                $itemData = array(
                    "type" => "encounter",
                    "pdf_data" => array()
                );

                $formCount = 0;
                foreach ($encarr as $enc) {

                    if ($enc['formdir'] == 'newpatient' || $enc['formdir'] == 'newGroupEncounter') {
                        $itemData["pdf_data"][$enc['formdir'] . "_" . $enc['form_id']] = $encounterData['encounter'];
                        $formCount++;
                        continue;
                    }

                    $itemData["pdf_data"][$enc['formdir'] . "_" . $enc['form_id']] = $encounterData['encounter'];

                    $formCount++;
                }

                if ($formCount > 0) {
                    $itemData = $this->generateFormPDF(array($itemData), $encounterData["pid"] ?? 0);
                    $itemData = count($itemData) == 1 ? $itemData[0] : array();
                } else {
                    $itemData["pdf_data"] = "";
                }
            }

            // Add visit history item data
            $processingResult->addData($itemData);

        } catch (\Exception $e) {
            // Add Internal error
            $processingResult->addInternalError($e->getMessage());
        }

        // Clear all previously set headers by sending a new header with the same name
        header_remove(); // This function removes all previously set headers

        if (!empty($searchParams['Watermark']) && $searchParams['Watermark'] == "1" ) {

            $watermarkText = $GLOBALS['pc_watermark_text'] ?? "";

            $file_path = $GLOBALS['OE_SITE_DIR'] . '/documents/temp/doc_' . date("YmdHis") . $searchParams['Id'] . ".tmp";

            if (!empty($watermarkText)) {

                $base64_content = "";

                if (isset($itemData['type'])) {
                    if ($itemData['type'] == "document") {
                        $base64_content = $itemData['document']; 
                    } else if ($itemData['type'] == "encounter" || $itemData['type'] == "batch") {
                        $base64_content = $itemData['pdf_data'];
                    }
                }

                // Extract the MIME type from the data URI
                preg_match('/^data:(.*?);base64,/', $base64_content, $matches);

                if (isset($matches[1])) {
                    // Remove the data URI prefix
                    $base64_content = preg_replace('/^data:[^;]+;base64,/', '', $base64_content);

                    $mimeToFileType = [
                        'application/pdf' => 'PDF'
                    ];

                    $mimeImageFileType = [
                        'image/jpeg' => 'Image',
                        'image/png' => 'Image',
                        'image/gif' => 'Image'
                    ];

                    // Identify file type
                    if (array_key_exists($matches[1], $mimeToFileType)) {

                        $file_path1 = $GLOBALS['OE_SITE_DIR'] . '/documents/temp/pdf_' . date("YmdHisu") . $searchParams['Id'] . ".pdf";

                        // Put content
                        file_put_contents($file_path1, base64_decode($base64_content));

                        $needToGeneratePDF = false;

                        try {
                            // Initialize mPDF object
                            $mpdf1 = new mPDF();
                            $mpdf1->setSourceFile($file_path1);

                            // Output the PDF directly to the browser
                            $mpdf1->Output('', 'S');
                        } catch (\setasign\Fpdi\PdfParser\CrossReference\CrossReferenceException $e) {
                            if ($e->getCode() == "267") {
                                $needToGeneratePDF = true;
                            }
                        }

                        // PDF Version
                        if($needToGeneratePDF === true) {
                            $file_path2 = $GLOBALS['OE_SITE_DIR'] . '/documents/temp/pdf_1_' . date("YmdHisu") . $searchParams['Id'] . ".pdf";

                            // Excute new file
                            shell_exec('gs -dBATCH -dNOPAUSE -q -sDEVICE=pdfwrite -sOutputFile="'.$file_path2.'" "'.$file_path1.'"'); 

                            unlink($file_path1);

                            // Set path
                            $file_path1 = $file_path2;
                        }

                        // Initialize mPDF object
                        $mpdf = new mPDF();

                        // Set the source PDF file
                        //$mpdf->SetImportUse(); // Required to import pages
                        $pageCount = $mpdf->setSourceFile($file_path1);

                        // Add all pages
                        for ($i = 1; $i <= $pageCount; $i++) {
                            $mpdf->AddPage();
                            $tplId = $mpdf->ImportPage($i);
                            $mpdf->UseTemplate($tplId);

                            // Add watermark text
                            $mpdf->SetWatermarkText($watermarkText); // Watermark text
                            $mpdf->showWatermarkText = true; // Show watermark text
                            $mpdf->watermark_font = 'Arial';
                            $mpdf->watermarkTextAlpha  = 0.1; // Watermark text transparency (0 to 1)
                        }

                        // Output the PDF with watermark
                        $wmPdfString = $mpdf->Output($GLOBALS['OE_SITE_DIR'] . '/documents/temp/out_doc_' . date("YmdHis") . $searchParams['Id'] . ".pdf", 'S');

                        if (!empty($wmPdfString)) {
                            $wmPdfString = "data:" . $matches[1] . ";base64," . base64_encode($wmPdfString);

                            if ($itemData['type'] == "document") {
                                $itemData['document'] = $wmPdfString; 
                            } else if ($itemData['type'] == "encounter" || $itemData['type'] == "batch") {
                                $itemData['pdf_data'] = $wmPdfString;
                            }
                        }

                        unlink($file_path1);

                    } else if (array_key_exists($matches[1], $mimeImageFileType)) {

                        // Remove the data URI prefix
                        $base64_content = preg_replace('/^data:[^;]+;base64,/', '', $base64_content);

                        $angle = 45;
                        $opacity = 50;

                        // Load the source image
                        $sourceImage = imagecreatefromstring(base64_decode($base64_content));
                        if (!$sourceImage) {
                            die('Error: Unable to open source image');
                        }

                        $fontFile = $GLOBALS['fileroot'] . "/interface/modules/custom_modules/oe-module-vitalhealthcare-generic/font/arial.ttf";

                        // Calculate image dimensions and position for centered text watermark
                        $imageWidth = imagesx($sourceImage);
                        $imageHeight = imagesy($sourceImage);
                        
                        // Determine font size based on image dimensions (adjust multiplier as needed)
                        //$fontSize = min($imageWidth, $imageHeight) * 0.15; // Adjust multiplier for appropriate font size relative to image size
                        $fontSize = $this->calculateFontSize($imageWidth, $imageHeight, $watermarkText);

                        // Allocate text color
                        $textColor = imagecolorallocatealpha($sourceImage, 200, 200, 200, 127 * (100 - $opacity) / 100);

                        $textBox = imagettfbbox($fontSize, 0, $fontFile, $watermarkText);

                        $textWidth = abs($textBox[4] - $textBox[0]); // Calculate the width of the text box
                        $textHeight = abs($textBox[5] - $textBox[1]); // Calculate the height of the text box
                        
                        // Adjust text position to center based on rotated dimensions
                        $angleRadians = deg2rad($angle);
                        $rotatedTextWidth = $textWidth * cos($angleRadians) + $textHeight * sin($angleRadians);
                        $rotatedTextHeight = $textWidth * sin($angleRadians) + $textHeight * cos($angleRadians);

                        // Calculate the center of the image
                        $centerX = $imageWidth / 2;
                        $centerY = $imageHeight / 2;
                        
                        // Calculate the position to start drawing the text
                        $textX = $centerX - ($rotatedTextWidth / 2) + ($fontSize /2);
                        $textY = $centerY + ($rotatedTextHeight / 2);
                        
                        // Add text watermark to image
                        imagettftext($sourceImage, $fontSize, $angle, $textX, $textY, $textColor, $fontFile, $watermarkText);

                        ob_start();

                        // Save the modified image to the output path
                        imagepng($sourceImage);

                        $wmImageContent = ob_get_clean();

                        // Encode the image content to base64
                        $wmbase64Image = base64_encode($wmImageContent);
                        
                        // Free up memory
                        imagedestroy($sourceImage);
                        imagedestroy($rotatedImage);

                        if (!empty($wmbase64Image)) {
                            $wmbase64Image = "data:image/png;base64," . $wmbase64Image;

                            if ($itemData['type'] == "document") {
                                $itemData['document'] = $wmbase64Image; 
                            }
                        }

                    }
                }
            }

            $result = file_put_contents($file_path, json_encode($itemData));

            header('Content-Type: application/octet-stream');
            header("Content-Transfer-Encoding: Binary"); 
            header("Content-disposition: attachment; filename=\"file.json\"");

            readfile($file_path);
            unlink($file_path);

            exit();
        }

        $responseBody = RestControllerHelper::handleProcessingResult($processingResult, null, 200);
        return $responseBody;
    }

    public function getVisithistoryItemAsStream(HttpRestRequest $request) {
        global $srcdir, $get_items_only;

        $searchParams = $request->getQueryParams();
        $processingResult = new ProcessingResult();

        try {

            $itemData = array();

            // Check Type
            if (empty($searchParams['Type'] ?? "")) {
                throw new \Exception("Emtpy Type");
            }

            // Check Id
            if (empty($searchParams['Id'] ?? 0)) {
                throw new \Exception("Emtpy Id");
            }

            if ($searchParams['Type'] == "document") {
                $d = new \Document($searchParams['Id']);
                $docObj = new \C_Document();
                $documentContent = $docObj->retrieve_action($d->foreign_id, $d->id, true, true, true);

                if(empty($d->id) || empty($documentContent)) {
                    // If document not exits
                    throw new \Exception("Item not found");
                }

                // Set document item
                $itemData = array(
                    "type" => "document",
                    "document" => "data:" . $d->mimetype . ";base64," . base64_encode($documentContent)
                );
            } else if ($searchParams['Type'] == "encounter") {
                // Get encounter data
                $encounterData = sqlQuery("SELECT fe.* FROM form_encounter AS fe WHERE fe.encounter = ? ORDER BY fe.date DESC, fe.id;", array($searchParams['Id']));

                if (empty($encounterData)) {
                    // If encounter not found
                    throw new \Exception("Encounter Item not found");
                }

                $encarr = getFormByEncounter($encounterData["pid"] ?? 0, $encounterData["encounter"] ?? 0, "formdir, user, form_name, form_id, deleted", "", "date asc");

                // Set document item
                $itemData = array(
                    "type" => "encounter",
                    "pdf_data" => array()
                );

                $formCount = 0;
                foreach ($encarr as $enc) {

                    if ($enc['formdir'] == 'newpatient' || $enc['formdir'] == 'newGroupEncounter') {
                        $itemData["pdf_data"][$enc['formdir'] . "_" . $enc['form_id']] = $encounterData['encounter'];
                        $formCount++;
                        continue;
                    }

                    $itemData["pdf_data"][$enc['formdir'] . "_" . $enc['form_id']] = $encounterData['encounter'];

                    $formCount++;
                }

                if ($formCount > 0) {
                    $itemData = $this->generateFormPDF(array($itemData), $encounterData["pid"] ?? 0);
                    $itemData = count($itemData) == 1 ? $itemData[0] : array();
                } else {
                    $itemData["pdf_data"] = "";
                }
            }

            // Add visit history item data
            //$processingResult->addData($itemData);

        } catch (\Exception $e) {
            // Add Internal error
            //$processingResult->addInternalError($e->getMessage());
        }

        // Clear all previously set headers by sending a new header with the same name
        header_remove(); // This function removes all previously set headers

        //$responseBody = RestControllerHelper::handleProcessingResult($processingResult, null, 200);
        //return $responseBody;

        $watermarkText = $GLOBALS['pc_watermark_text'] ?? "";

        $file_path = $GLOBALS['OE_SITE_DIR'] . '/documents/temp/doc_' . date("YmdHis") . $searchParams['Id'] . ".tmp";

        if (!empty($watermarkText)) {

            $base64_content = "";

            if (isset($itemData['type'])) {
                if ($itemData['type'] == "document") {
                    $base64_content = $itemData['document']; 
                } else if ($itemData['type'] == "encounter") {
                    $base64_content = $itemData['pdf_data'];
                }
            }

            // Extract the MIME type from the data URI
            preg_match('/^data:(.*?);base64,/', $base64_content, $matches);

            if (isset($matches[1])) {
                // Remove the data URI prefix
                $base64_content = preg_replace('/^data:[^;]+;base64,/', '', $base64_content);

                $mimeToFileType = [
                    'application/pdf' => 'PDF'
                ];

                $mimeImageFileType = [
                    'image/jpeg' => 'Image',
                    'image/png' => 'Image',
                    'image/gif' => 'Image'
                ];

                // Identify file type
                if (array_key_exists($matches[1], $mimeToFileType)) {

                    $file_path1 = $GLOBALS['OE_SITE_DIR'] . '/documents/temp/pdf_' . date("YmdHis") . $searchParams['Id'] . ".pdf";

                    // Put content
                    file_put_contents($file_path1, base64_decode($base64_content));

                    // Initialize mPDF object
                    $mpdf = new mPDF();

                    // Set the source PDF file
                    //$mpdf->SetImportUse(); // Required to import pages
                    $pageCount = $mpdf->setSourceFile($file_path1);

                    // Add all pages
                    for ($i = 1; $i <= $pageCount; $i++) {
                        $mpdf->AddPage();
                        $tplId = $mpdf->ImportPage($i);
                        $mpdf->UseTemplate($tplId);

                        // Add watermark text
                        $mpdf->SetWatermarkText($watermarkText); // Watermark text
                        $mpdf->showWatermarkText = true; // Show watermark text
                        $mpdf->watermark_font = 'Arial';
                        $mpdf->watermarkTextAlpha  = 0.1; // Watermark text transparency (0 to 1)
                    }

                    // Output the PDF with watermark
                    $wmPdfString = $mpdf->Output($GLOBALS['OE_SITE_DIR'] . '/documents/temp/out_doc_' . date("YmdHis") . $searchParams['Id'] . ".pdf", 'S');

                    if (!empty($wmPdfString)) {
                        $wmPdfString = "data:" . $matches[1] . ";base64," . base64_encode($wmPdfString);

                        if ($itemData['type'] == "document") {
                            $itemData['document'] = $wmPdfString; 
                        } else if ($itemData['type'] == "encounter") {
                            $itemData['pdf_data'] = $wmPdfString;
                        }
                    }

                    unlink($file_path1);

                } else if (array_key_exists($matches[1], $mimeImageFileType)) {

                    // Remove the data URI prefix
                    $base64_content = preg_replace('/^data:[^;]+;base64,/', '', $base64_content);

                    $angle = 45;
                    $opacity = 50;

                    // Load the source image
                    $sourceImage = imagecreatefromstring(base64_decode($base64_content));
                    if (!$sourceImage) {
                        die('Error: Unable to open source image');
                    }

                    $fontFile = $GLOBALS['fileroot'] . "/interface/modules/custom_modules/oe-module-vitalhealthcare-generic/font/arial.ttf";

                    // Calculate image dimensions and position for centered text watermark
                    $imageWidth = imagesx($sourceImage);
                    $imageHeight = imagesy($sourceImage);
                    
                    // Determine font size based on image dimensions (adjust multiplier as needed)
                    //$fontSize = min($imageWidth, $imageHeight) * 0.15; // Adjust multiplier for appropriate font size relative to image size
                    $fontSize = $this->calculateFontSize($imageWidth, $imageHeight, $watermarkText);

                    // Allocate text color
                    $textColor = imagecolorallocatealpha($sourceImage, 200, 200, 200, 127 * (100 - $opacity) / 100);

                    $textBox = imagettfbbox($fontSize, 0, $fontFile, $watermarkText);

                    $textWidth = abs($textBox[4] - $textBox[0]); // Calculate the width of the text box
                    $textHeight = abs($textBox[5] - $textBox[1]); // Calculate the height of the text box
                    
                    // Adjust text position to center based on rotated dimensions
                    $angleRadians = deg2rad($angle);
                    $rotatedTextWidth = $textWidth * cos($angleRadians) + $textHeight * sin($angleRadians);
                    $rotatedTextHeight = $textWidth * sin($angleRadians) + $textHeight * cos($angleRadians);

                    // Calculate the center of the image
                    $centerX = $imageWidth / 2;
                    $centerY = $imageHeight / 2;
                    
                    // Calculate the position to start drawing the text
                    $textX = $centerX - ($rotatedTextWidth / 2) + ($fontSize /2);
                    $textY = $centerY + ($rotatedTextHeight / 2);
                    
                    // Add text watermark to image
                    imagettftext($sourceImage, $fontSize, $angle, $textX, $textY, $textColor, $fontFile, $watermarkText);

                    ob_start();

                    // Save the modified image to the output path
                    imagepng($sourceImage);

                    $wmImageContent = ob_get_clean();

                    // Encode the image content to base64
                    $wmbase64Image = base64_encode($wmImageContent);
                    
                    // Free up memory
                    imagedestroy($sourceImage);
                    imagedestroy($rotatedImage);

                    if (!empty($wmbase64Image)) {
                        $wmbase64Image = "data:image/png;base64," . $wmbase64Image;

                        if ($itemData['type'] == "document") {
                            $itemData['document'] = $wmbase64Image; 
                        }
                    }

                }
            }
        }

        $result = file_put_contents($file_path, json_encode($itemData));

        header('Content-Type: application/octet-stream');
        header("Content-Transfer-Encoding: Binary"); 
        header("Content-disposition: attachment; filename=\"file.json\"");

        readfile($file_path);
        unlink($file_path);

        exit();
    }

    public function getPatientLedger(HttpRestRequest $request) {
        $searchParams = $request->getQueryParams();
        $processingResult = new ProcessingResult();

        try {

            $c = $this->generateLeaderData($searchParams);
            extract($c);

            if ($searchParams['Action'] == "list") {
                $processingResult->setData(array(
                    'cols' => $colsList ?? array(),
                    'items' => $rowData ?? array()
                ));
            } else if ($searchParams['Action'] == "cases") {
                $processingResult->setData(array(
                    'cases' => getCaseDropdown($idempiere_connection, $_REQUEST['chartNumber'])
                ));
            } else if ($searchParams['Action'] == "balance") {

                if (substr($GLOBALS['ledger_begin_date'], 0, 1) == 'Y') {
                    $last_year = mktime(0, 0, 0, date('m'), date('d'), date('Y')-3);
                } elseif (substr($GLOBALS['ledger_begin_date'], 0, 1) == 'M') {
                    $ledger_time = substr($GLOBALS['ledger_begin_date'], 1, 1);
                    $last_year = mktime(0, 0, 0, date('m')-$ledger_time, date('d'), date('Y'));
                } elseif (substr($GLOBALS['ledger_begin_date'], 0, 1) == 'D') {
                    $ledger_time = substr($GLOBALS['ledger_begin_date'], 1, 1);
                    $last_year = mktime(0, 0, 0, date('m'), date('d')-$ledger_time, date('Y'));
                }

                $form_from_date = date('d/m/Y', $last_year);
                $form_to_date = date('d/m/Y');

                $processingResult->setData(array(
                    'default_from_date' => $form_from_date,
                    'default_to_date' => $form_to_date,
                    'cases' => getCaseDropdown($idempiere_connection, $_REQUEST['chartNumber']),
                    'details' => $balances
                ));
            } else if ($searchParams['Action'] == "details") {
                //$processingResult->setData($rowData);
                $processingResult->setData(array(
                    'cols' => $_REQUEST['type'] == "payment" ? $paymentColsList1 : $chargeColsList1,
                    'items' => $rowData ?? array(),
                    'html' => $preparedHTML ?? ''
                ));
            } else if ($searchParams['Action'] == "print") {

                ob_start();
                ?>
                <style>
                    table.printTable, table.subTableContainer, table.childTable {
                        font-size: 09px;
                        width: 100%;
                        border-collapse: collapse;
                    }

                    table.printTable th,  
                    table.printTable td {
                        border: 1px solid black !important;
                        padding: 4px !important;
                    }

                    table.printTable td.rowDetails {
                      padding: 0px !important;
                      border-top: 0px !important;
                      border-bottom: 0px !important;
                    }

                    table.printTable table.subTableContainer,
                    table.printTable table.subTableContainer td {
                      padding: 0px !important;
                      border: 0px solid !important;
                    }

                    table.printTable table.subTableContainer table.childTable th,
                    table.printTable table.subTableContainer table.childTable td {
                      border: 1px solid !important;
                      padding: 4px !important;
                    }

                    table.printTable table.subTableContainer table.childTable th.lastCol,
                    table.printTable table.subTableContainer table.childTable td.lastCol,
                    table.printTable table.subTableContainer table.childTable td.emptyRow {
                      border-right: 0px solid !important;
                    }

                    table.printTable table.subTableContainer table.childTable thead th {
                      border-top: 0px solid !important;
                    }

                    table.printTable table.subTableContainer table.childTable td.emptyRow,
                    table.printTable table.subTableContainer table.childTable tr.lastRow td {
                      border-bottom: 0px solid !important;
                    }

                    table.printTable tr.lastRow td.rowDetails {
                      border-bottom: 1px solid !important;
                    }

                    .subViewTitle {
                        display: none !important;
                    }

                    .green {
                        color: green;
                    }
                    .red {
                        color: red;
                    }

                    .dateContainer {
                        text-align: right;
                        vertical-align: text-top;
                    }

                    #printBalances .balance-label {
                        font-size: 16px;
                    }

                    #printBalances .balance-label > span > b {
                        font-weight: normal;
                    }

                </style>
                <?php

                $htmlContent = ob_get_clean();
                $htmlContent .= $html_content;

                $file_path = $GLOBALS['OE_SITE_DIR'] . '/documents/temp/leadger_' . date("YmdHis") . $searchParams['Id'] . ".pdf";

                // Initialize mPDF object
                $config_mpdf = array(
                    'tempDir' => $GLOBALS['MPDF_WRITE_DIR'],
                    'mode' => $GLOBALS['pdf_language'],
                    'format' => $GLOBALS['pdf_size'],
                    'default_font_size' => '9',
                    'default_font' => 'dejavusans',
                    'margin_left' => $GLOBALS['pdf_left_margin'],
                    'margin_right' => $GLOBALS['pdf_right_margin'],
                    'margin_top' => $GLOBALS['pdf_top_margin'] * 1.5,
                    'margin_bottom' => $GLOBALS['pdf_bottom_margin'] * 1.5,
                    'margin_header' => $GLOBALS['pdf_top_margin'],
                    'margin_footer' => $GLOBALS['pdf_bottom_margin'],
                    'orientation' => $GLOBALS['pdf_layout'],
                    'shrink_tables_to_fit' => 1,
                    'use_kwt' => true,
                    'autoScriptToLang' => true,
                    'keep_table_proportions' => true
                );
                $pdf = new mPDF($config_mpdf);

                if (!empty($searchParams['Watermark']) && $searchParams['Watermark'] == "1" ) {
                    $watermarkText = $GLOBALS['pc_watermark_text'] ?? "";
                    // Add watermark text
                    $pdf->SetWatermarkText($watermarkText); // Watermark text
                    $pdf->showWatermarkText = true; // Show watermark text
                    $pdf->watermark_font = 'Arial';
                    $pdf->watermarkTextAlpha  = 0.1; // Watermark text transparency (0 to 1)
                }

                $pdf->writeHTML($htmlContent);
                $pdfContent = $pdf->Output($file_path, "S");

                $processingResult->setData(array(
                    'pdf_data' => !empty($pdfContent) ? "data:application/pdf;base64," . base64_encode($pdfContent) : ''
                ));
            }

        } catch (\Exception $e) {
            // Add Internal error
            $processingResult->addInternalError($e->getMessage());
        }

        $responseBody = RestControllerHelper::handleProcessingResult($processingResult, null, 200);
        return $responseBody;
    }

    public function generateLeaderData($searchParams) {
        global $ad_client_id;

        if ($searchParams['Action'] == "cases") {
            // Check Type
            if (empty($searchParams['ChartNumber'] ?? "")) {
                throw new \Exception("Emtpy Chart Number");
            }

            $_REQUEST['page'] = "cases";
            $_REQUEST['chartNumber'] = $searchParams['ChartNumber'] ?? "";

        } else if ($searchParams['Action'] == "balance") {
            // Check Type
            if (empty($searchParams['ChartNumber'] ?? "")) {
                throw new \Exception("Emtpy Chart Number");
            }

            $_REQUEST['page'] = "balances";
            $_REQUEST['chartNumber'] = $searchParams['ChartNumber'] ?? "";
            $_REQUEST['form_extra_case_filter'] = $searchParams['Case'] ?? "";
            $_REQUEST['form_show_patientresponsibilityforcharge'] =  $searchParams['PatientResponsibilityForCharge'] ?? "";

        } else if ($searchParams['Action'] == "list") {

            // Check Type
            if (empty($searchParams['Type'] ?? "")) {
                throw new \Exception("Emtpy Type");
            }

            // Check Type
            if (empty($searchParams['ChartNumber'] ?? "")) {
                throw new \Exception("Emtpy Chart Number");
            }

            // Check FromDate
            if (empty($searchParams['FromDate'] ?? "")) {
                throw new \Exception("Emtpy From Date");
            }

            // Check ToDate
            if (empty($searchParams['ToDate'] ?? "")) {
                throw new \Exception("Emtpy To Date");
            }

            $_REQUEST['page'] = "datatable";
            $_REQUEST['form_extra_payment_filter'] = $searchParams['Type'] ?? "";
            $_REQUEST['chartNumber'] = $searchParams['ChartNumber'] ?? "";
            $_REQUEST['form_from_date'] = $searchParams['FromDate'] ?? "";
            $_REQUEST['form_to_date'] = $searchParams['ToDate'] ?? "";
            $_REQUEST['form_extra_case_filter'] = $searchParams['Case'] ?? "";
            $_REQUEST['form_show_patientresponsibilityforcharge'] =  $searchParams['PatientResponsibilityForCharge'] ?? "";

        } else if ($searchParams['Action'] == "details") {

            // Check Type
            if (empty($searchParams['EntryNumber'] ?? "")) {
                throw new \Exception("Emtpy EntryNumber");
            }

            $_REQUEST['page'] = "rowdetails";
            $_REQUEST['entryNumber'] = $searchParams['EntryNumber'] ?? "";
            $_REQUEST['type'] = $searchParams['Type'] ?? "";

        } else if ($searchParams['Action'] == "print") {

            // Check Type
            if (empty($searchParams['Type'] ?? "")) {
                throw new \Exception("Emtpy Type");
            }

            // Check Type
            if (empty($searchParams['ChartNumber'] ?? "")) {
                throw new \Exception("Emtpy Chart Number");
            }

            // Check FromDate
            if (empty($searchParams['FromDate'] ?? "")) {
                throw new \Exception("Emtpy From Date");
            }

            // Check ToDate
            if (empty($searchParams['ToDate'] ?? "")) {
                throw new \Exception("Emtpy To Date");
            }

            $_REQUEST['page'] = "print";
            $_REQUEST['form_extra_payment_filter'] = $searchParams['Type'] ?? "";
            $_REQUEST['chartNumber'] = $searchParams['ChartNumber'] ?? "";
            $_REQUEST['form_from_date'] = $searchParams['FromDate'] ?? "";
            $_REQUEST['form_to_date'] = $searchParams['ToDate'] ?? "";
            $_REQUEST['form_extra_case_filter'] = $searchParams['Case'] ?? "";
            $_REQUEST['form_show_patientresponsibilityforcharge'] =  $searchParams['PatientResponsibilityForCharge'] ?? "";
        } else {
            throw new \Exception("Something went wrong");
        }

        ob_start();
        $cryptoGen = new CryptoGen();
        require dirname(__FILE__, 7) . "/interface/reports/idempiere_pat_ledger_ajax.php";
        $c = ob_get_clean();

        if ($searchParams['Action'] == "print") {

        ob_start();

        $type_form = 1;
        $patient = sqlQuery("SELECT * from patient_data WHERE pubpid=?", array($_REQUEST['chartNumber']));
        $pat_dob = $patient['DOB'];
        $pat_name = $patient['fname']. ' ' . $patient['lname'];

        $form_from_date = $_REQUEST['form_from_date'];
        $form_to_date = $_REQUEST['form_to_date'];

        $chartNumber = $_REQUEST['chartNumber'];
        $form_extra_case_filter = $_REQUEST['form_extra_case_filter'];
        $balances = calculateBalance($idempiere_connection, $chartNumber, $form_extra_case_filter); 
        ?>
          <div id="report_header">
            <table width="100%"  border="0" cellspacing="0" cellpadding="0">
              <tr><td colspan="2">&nbsp;</td></tr>
              <tr>
                <td colspan="2" class="title" align="center" ><?php echo $GLOBALS['openemr_name']; ?></td>
              </tr>
              <tr><td colspan="2">&nbsp;</td></tr>
              <tr><td colspan="2">&nbsp;</td></tr>
              <tr>
                <td class="headerDetailsContainer">
                    <?php echo '<div>'; ?>
                    <?php echo xlt('Patient Name: ')?>:
                    <?php echo text($pat_name); ?>
                    <?php echo '</div>'; ?>

                    <?php echo '<div>'; ?>
                    <?php echo xlt('DOB: ')?>:
                    <?php echo text($pat_dob);?>
                    <?php echo '</div>'; ?>
                </td>
                <td class="dateContainer">
                    <?php
                        $header_form_to_date = xl('For Dates: ') . ': ' . oeFormatShortDate($form_from_date) . ' - ' . oeFormatShortDate($form_to_date);
                        echo text($header_form_to_date);
                    ?>
                </td>
              </tr>
              <tr><td colspan="2">&nbsp;</td></tr>
              <tr>
                <td colspan="2">
                    <div id="printBalances">
                        <div>
                          <div class='balance-label'>
                            <span><b>Overall Balance:</b></span>
                            <span><?php echo $balances['overallBalance'] ? number_format(($balances['overallBalance']), 2, '.', ',') : '0'; ?></span>
                          </div>
                          <!-- <div class='balance-label'>
                            <span><b>OverAll UnAll Amt:</b></span>
                            <span><?php //echo $balances['overAllUnAllocatedAmt'] ? number_format(($balances['overAllUnAllocatedAmt']), 2, '.', ',') : '0'; ?></span>
                          </div> -->
                        </div>
                        <div>
                          <div class='balance-label'>
                            <span><b>Case Billed:</b></span>
                            <span><?php echo $balances['caseBilled'] ? number_format(($balances['caseBilled']), 2, '.', ',') : '0'; ?></span>
                          </div>
                          <div class='balance-label'>
                            <span><b>Case Paid Amt: </b></span>
                            <span><?php echo $balances['casePaidAmt'] ? number_format(($balances['casePaidAmt']), 2, '.', ',') : '0'; ?></span>
                          </div>
                        </div>
                        <div>
                          <div class='balance-label'>
                            <span><b>Case Balance: </b></span>
                            <span><?php echo $balances['caseBalance'] ? number_format(($balances['caseBalance']), 2, '.', ',') : '0'; ?></span>
                          </div>
                        </div>
                        <!-- <div>
                            <div class='balance-label'>
                                <span><b>Case Adj Amt:</b></span>
                                <span><?php //echo $balances['caseAdjAmt'] ? number_format(($balances['caseAdjAmt']), 2, '.', ',') : '0'; ?></span>
                            </div> 
                            <div class='balance-label'>
                                <span><b>Case UnAll Amt:</b></span>
                                <span><?php //echo $balances['caseUnAllocatedAmt'] ? number_format(($balances['caseUnAllocatedAmt']), 2, '.', ',') : '0'; ?></span>
                            </div> 
                        </div> -->
                        <!-- <div>
                        </div>
                        <div>
                          <div class='balance-label'>
                            <span><b>Patients responsibility for the case:</b></span>
                            <span><?php //echo $balances['patientResponsibility'] ? number_format(($balances['patientResponsibility']), 2, '.', ',') : '0'; ?></span>
                          </div>
                        </div> -->
                    </div>
                </td>
              </tr>
            </table>
            <br/>
          </div>
          
        <?php

        $bc = ob_get_clean();
        $c = $bc . $c;
        }

        $responceData = array('html_content' => $c);

        if (isset($colsList)) $responceData['colsList'] = $colsList;
        if (isset($rowData)) $responceData['rowData'] = $rowData;
        if (isset($idempiere_connection)) $responceData['idempiere_connection'] = $idempiere_connection;
        if (isset($balances)) $responceData['balances'] = $balances;
        if (isset($paymentColsList1)) $responceData['paymentColsList1'] = $paymentColsList1;
        if (isset($chargeColsList1)) $responceData['chargeColsList1'] = $chargeColsList1;
        if (isset($rowData)) $responceData['rowData'] = $rowData;
        if (isset($preparedHTML)) $responceData['preparedHTML'] = $preparedHTML;

        return $responceData;
    }

    public function generateFormPDF($items, $patientId = 0) {
        global $srcdir, $pid, $web_root, $css_header, $doNotPrintField, $OE_SITE_DIR, $PDF_OUTPUT, $iter, $newordermode, $hidebutton, $form_id;

        if (!empty($patientId)) {
            $pid = $patientId;
        }

        $tGetData = $_GET;
        unset($_GET);

        // Store the current working directory
        $originalDir = getcwd();

        // Change the current working directory to resolve include paths
        chdir(dirname(__FILE__, 7) . "/interface/patient_file/report/");

        foreach ($items as $ikey => $item) {
            if(isset($item['pdf_data']) && is_array($item['pdf_data']) && !empty($item['pdf_data'])) {

                unset($_POST);

                $_POST['pdf'] = 1;
                //$_POST['include_demographics'] = 'demographics';
                $_GET = array();
                foreach ($item['pdf_data'] as $fKey => $fValue) {
                    $_POST[$fKey] = $fValue;
                }

                $temp_pdf_output = $GLOBALS['pdf_output'];
                $GLOBALS['pdf_output'] = "S";

                $doNotPrintField = true;
                
                // Fetch visit history items pdf
                ob_start();
                require dirname(__FILE__, 7) . "/interface/patient_file/report/custom_report.php";
                $cc = ob_get_clean();

                $doNotPrintField = false;

                $GLOBALS['pdf_output'] = $temp_pdf_output;

                //$file_path1 = $GLOBALS['OE_SITE_DIR'] . '/documents/temp/pdf_' . date("YmdHis") . $searchParams['Id'] . ".pdf";
                $c = $pdf->Output('', "S");

                if(!empty($c)) {
                    $items[$ikey]['pdf_data'] = "data:application/pdf;base64," . base64_encode($c);
                } else {
                    $items[$ikey]['pdf_data'] = "";
                }
            } else {
                $items[$ikey]['pdf_data'] = "";
            }
        }

        // Restore the original working directory
        chdir($originalDir);

        $_GET = $tGetData;

        return $items;
    }

    // Function to calculate font size based on image dimensions and text length
    public function calculateFontSize($imageWidth, $imageHeight, $text) {
        // Adjust multiplier for appropriate font size relative to image size and text length
        $baseFontSize = min($imageWidth, $imageHeight) * 0.1; // Adjust as needed
        $textLength = strlen($text);
        
        // Calculate font size based on text length
        $fontSize = $baseFontSize / max(1, $textLength / 10); // Adjust divisor for desired scaling
        
        return max(10, $fontSize); // Ensure minimum font size (adjust as needed)
    }
}
