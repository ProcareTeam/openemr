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
use OpenEMR\Common\Crypto\CryptoGen;

class ZoomRestController
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

    public function zoomWebHook(HttpRestRequest $request)
    {
        if ($_SERVER['REQUEST_METHOD'] == 'POST'){
            $data = file_get_contents("php://input");
            $decode = json_decode($data, true);

            if ('endpoint.url_validation' == $decode['event']) {
                $response = $this->zoom_url_validate($decode);
                echo json_encode($response);
                exit();
            } else {
                $zoom_request_payload = isset($decode["payload"]) ? $decode["payload"] : array();
                $zoom_request_object = isset($zoom_request_payload["object"]) ? $zoom_request_payload["object"] : array();
                $zoom_request_participant = isset($zoom_request_object["participant"]) ? $zoom_request_object["participant"] : array();

                $zoom_meeting_id = isset($zoom_request_object["id"]) ? $zoom_request_object["id"] : "";
                $zoom_participant_name = isset($zoom_request_participant["user_name"]) ? $zoom_request_participant["user_name"] : "";
                $zoom_participant_user_id = isset($zoom_request_participant["user_id"]) ? $zoom_request_participant["user_id"] : "";
                $zoom_participant_userid = isset($zoom_request_participant["participant_user_id"]) ? $zoom_request_participant["participant_user_id"] : "";
                $zoom_participant_join_time = isset($zoom_request_participant["join_time"]) ? $zoom_request_participant["join_time"] : "";
                $zoom_participant_leave_time = isset($zoom_request_participant["leave_time"]) ? $zoom_request_participant["leave_time"] : "";
                $zoom_participant_uuid = isset($zoom_request_participant["participant_uuid"]) ? $zoom_request_participant["participant_uuid"] : "";
                
                
                $zoom_meeting_data = sqlQueryNoLog("SELECT * from zoom_appointment_events zae where m_id = ? order by id desc;", array($zoom_meeting_id));

                if(!empty($zoom_meeting_data)) {

                    // if(empty($zoom_participant_name) && !empty($zoom_participant_userid)) {
                    //     require_once($GLOBALS['srcdir'] . "/OemrAD/classes/mdZoomIntegration.class.php");
                    //     $userData = \OpenEMR\OemrAd\ZoomIntegration::getZoomUser($zoom_participant_userid);
                    //     if(isset($userData) && !empty($userData) && is_array($userData)) {
                    //         $zoom_participant_name = isset($userData['display_name']) ? $userData['display_name'] : "";
                    //     }
                    // }

                    $sql = "INSERT INTO `vh_zoom_webhook_event` ( meeting_id, event, user_name, user_id, participant_uuid, join_time, leave_time, event_ts, payload ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?) ";
                        
                    sqlInsert($sql, array(
                        $zoom_meeting_id,
                        $decode['event'],
                        $zoom_participant_name,
                        $zoom_participant_user_id,
                        $zoom_participant_uuid,
                        !empty($zoom_participant_join_time) ? date("Y-m-d H:i:s", strtotime($zoom_participant_join_time . ' GMT')) : NULL,
                        !empty($zoom_participant_leave_time) ? date("Y-m-d H:i:s", strtotime($zoom_participant_leave_time . ' GMT')) : NULL,
                        date("Y-m-d H:i:s", $decode['event_ts'] / 1000),
                        serialize($decode)
                    ));
                }
            }

            // Log data 
            //$this->wh_log(json_encode($decode));
        }
        exit();
    }

    private function zoom_url_validate($parameters)
    {
        $cryptoGen = new CryptoGen();

        $plainToken = $parameters['payload']['plainToken'];
        $encryptedToken = hash_hmac("sha256", $plainToken,  $cryptoGen->decryptStandard($GLOBALS['zoom_secret_token']));
        return ["plainToken" => $plainToken, "encryptedToken" => $encryptedToken];
    }

    private function wh_log($log_msg) {
        $log_filename = dirname(__FILE__, 7) . "/library/OemrAD/log";

        if (!file_exists($log_filename))
        {
            // create directory/folder uploads.
            mkdir($log_filename, 0777, true);
        }
        $log_file_data = $log_filename.'/log_' . date('d-M-Y') . '.log';
        file_put_contents($log_file_data, $log_msg . "\n", FILE_APPEND);
    }
}