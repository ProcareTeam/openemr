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

use OpenEMR\Common\Http\HttpRestRequest;
use OpenEMR\Common\Http\HttpRestRouteHandler;
use OpenEMR\FHIR\R4\FHIRResource\FHIRBundle;
use OpenEMR\FHIR\R4\FHIRResource\FHIRBundle\FHIRBundleEntry;
use OpenEMR\RestControllers\RestControllerHelper;
use OpenEMR\Services\FHIR\FhirResourcesService;
use OpenEMR\Validators\ProcessingResult;
use Psr\Http\Message\ResponseInterface;
use RestConfig;

class XiboRestController
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

    /**
     * Handles the response to the API request GET /fhir/CustomSkeletonResource and returns the FHIRBundle resource
     * that was found for the given request.  Any query search parameters are processed by this method.  If the method
     * is run in the patient context (as a logged in patient) it restricts the search to just that patient.
     * @param HttpRestRequest
     * @return FHIRBundle
     */
    public function firstapptlist(HttpRestRequest $request)
    {

        $result = $this->getfirstapptAll($request->getQueryParams());
        return $result;
    }

    public function getfirstapptAll($searchParams, $puuidBind = null)
    {
        $dateFrom = $searchParams['date_from'];
        $dateTo = $searchParams['date_to'];
        $appt_cat = isset($GLOBALS['oemr_xibo_appt_cat']) ? $GLOBALS['oemr_xibo_appt_cat'] : "";
        $appt_cat_list = !empty($appt_cat) ? array_map('trim', explode(",", $GLOBALS['oemr_xibo_appt_cat'])) : array();

        $processingResult = new ProcessingResult();

        if(empty($dateFrom) || empty($dateTo)) {
            $processingResult->addData(array());
        }

        $appt_ids = array();
        $lres1 = sqlStatement("SELECT count(ope1.pc_eid) as eid_count, CONCAT('[', GROUP_CONCAT(CONCAT('\"', ope1.pc_eid, '\"')), ']') as pc_eids, ope1.pc_pid as pc_pid, (SELECT IF(count(ope2.pc_eid) = 1 and ope2.pc_eid = ope1.pc_eid, 'TRUE', 'FALSE') from openemr_postcalendar_events ope2 where ope2.pc_pid = ope1.pc_pid and UNIX_TIMESTAMP(CONCAT(ope1.pc_eventDate,' ',ope1.pc_startTime)) >= UNIX_TIMESTAMP(CONCAT(ope2.pc_eventDate,' ',ope2.pc_startTime)) and ope2.pc_apptstatus not in ('x', '?', '%') and ope2.pc_catid not in ('2', '3', '4', '8', '11')) as is_first_appt from openemr_postcalendar_events ope1 where ope1.pc_apptstatus not in ('x', '?', '%') and ope1.pc_catid not in ('2', '3', '4', '8', '11') and CONCAT(ope1.pc_eventDate,' ',ope1.pc_startTime) BETWEEN '".$dateFrom."' AND '".$dateTo."' group by ope1.pc_pid order by ope1.pc_eid desc");

        while ($row1 = sqlFetchArray($lres1)) {
            if(isset($row1) && isset($row1['is_first_appt']) && $row1['is_first_appt'] === "TRUE") {
                $tmpeids = json_decode($row1['pc_eids'], true);

                if(is_array($tmpeids)) {
                    $appt_ids = array_merge($appt_ids, $tmpeids);
                }
            }
        }

        $where_str = '';
        if(!empty($appt_cat_list)) {
            $where_str .= " AND ope.pc_catid IN ('". implode("','", $appt_cat_list) ."')"; 
        }

        $lres=sqlStatement("SELECT ope.pc_eid as appt_id, ope.pc_apptstatus as appt_status, lo.title as appt_status_title, CONCAT(ope.pc_eventDate,' ',ope.pc_startTime) as appt_datetime, ope.pc_duration as appt_duration, ope.pc_catid as appt_catid, opc.pc_catname as appt_cat_name, ope.pc_aid as appt_provider_id, u.fname as provider_fname, u.mname as provider_mname, u.lname as provider_lname, ope.pc_facility as appt_facility, f.name as appt_facility_name, ope.pc_pid as appt_pid, pd.fname, pd.lname, pd.mname from openemr_postcalendar_events ope left join openemr_postcalendar_categories opc ON opc.pc_catid = ope.pc_catid left join users u on u.id = ope.pc_aid left join patient_data pd on pd.pid = ope.pc_pid left join list_options lo on lo.list_id = 'apptstat' and lo.option_id = ope.pc_apptstatus left join facility f on f.id = ope.pc_facility where ope.pc_pid != '' and ope.pc_eid IN ('". implode("','", $appt_ids) ."')" . $where_str);

        while ($row = sqlFetchArray($lres)) {
            $row['patient_name'] = "";
            $row['provider_name'] = "";

            if(!empty($row['fname']) && !empty($row['lname'])) {
                $row['patient_name'] = $row['fname'] .' '. substr($row['lname'],0,1) . '.';
            }

            if(!empty($row['provider_fname']) && !empty($row['provider_lname'])) {
                $row['provider_name'] = $row['provider_lname'] .', '. $row['provider_fname'];
            }

            // $tRow = array(
            //  'appt_id' => $row['appt_id'],
            //  'appt_patient_name' => $row['patient_name'],
            //  'appt_status_title' => $row['appt_status_title'],
            //  'appt_provider_id' => $row['appt_provider_id'],
            //  'appt_provider_name' => $row['provider_name'],
            //  'appt_datetime' => $row['appt_datetime'],
            //  'appt_cat_name' => $row['appt_cat_name'],
            //  'appt_facility' => $row['appt_facility'],
            //  'appt_facility_name' => $row['appt_facility_name'],
            // );

            $tRow = array(
                'appt_patient_name' => $row['patient_name'],
                'appt_datetime' => $row['appt_datetime'],
                'appt_facility' => $row['appt_facility'],
                'appt_facility_name' => $row['appt_facility_name'],
            );

            $results['data'][] = $tRow;
            $processingResult->addData($tRow);
        }

        $responseBody = RestControllerHelper::handleProcessingResult($processingResult, null, 200);
        return $responseBody;
    }
}