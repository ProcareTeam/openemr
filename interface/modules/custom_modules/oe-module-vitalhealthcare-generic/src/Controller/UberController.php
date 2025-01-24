<?php

namespace Vitalhealthcare\OpenEMR\Modules\Generic\Controller;

@include_once("$srcdir/patient.inc");

use OpenEMR\Common\Crypto\CryptoGen;

class UberController
{

	private $ubClientId;
	private $ubClientSecret;
	private $ubAccessToken;

	const TODAY_IN_PROCESSING_LABEL = "today_in_progress";
	const TODAY_UPCOMING_LABEL = "today_upcoming";
	const TODAY_COMPLETED_LABEL = "today_completed";
	const FUTURE_ACTIVITY_LABEL = "future_activity";
	const PAST_ACTIVITY_LABEL = "past_activity";

	const NO_DRIVERS_AVAILABLE_LABEL = "no_drivers_available";
	const ACCEPTED_LABEL = "accepted";
	const ARRIVING_LABEL = "arriving";
	const IN_PROGRESS_LABEL = "in_progress";
	const DRIVER_CANCELED_LABEL = "driver_canceled";
	const RIDER_CANCELED_LABEL = "rider_canceled";
	const COMPLETED_LABEL = "completed";
	const SCHEDULED_LABEL = "scheduled";
	const FAILED_LABEL = "failed";
	const OFFERED_LABEL = "offered";
	const EXPIRED_LABEL = "expired";
	const DRIVER_REDISPATCHED_LABEL = "driver_redispatched";

	const UPCOMING_STATUS_LIST = array();
	const IN_PROGRESS_STATUS_LIST = array(self::IN_PROGRESS_LABEL);
	const COMPLETED_STATUS_LIST = array(self::COMPLETED_LABEL);
	const CANCELLED_STATUS_LIST = array(self::NO_DRIVERS_AVAILABLE_LABEL, self::DRIVER_CANCELED_LABEL, self::RIDER_CANCELED_LABEL, self::FAILED_LABEL, self::OFFERED_LABEL, EXPIRED_LABEL);
	const GOOGLE_MAP_KEY = 'AIzaSyAkva4wBBRFzCbShT5_auGTo9CQ9MxRHek';


	const STATUS_DESCRIPTION = array(
		"processing" => "The request is matching to the most efficient available driver.",
		"no_drivers_available" => "The request was unfulfilled because no drivers were available.",
		"accepted" => "The request has been accepted by a driver and is “en route” to the start location (i.e. start_latitude and start_longitude).",
		"arriving" => "The driver has arrived or will be shortly.",
		"in_progress" => "The request is “en route” from the start location to the end location.",
		"driver_canceled" => "The request has been canceled by the driver.",
		"rider_canceled" => "The request canceled by rider.",
		"completed" => "The request has been completed by the driver.",
		"scheduled" => "The request is scheduled to be dispatched at a later time.",
		"failed" => "The request failed.",
		"offered" => "The deferred ride is actively redeemable by a rider.",
		"expired" => "The deferred ride has expired and cannot be redeemed by the rider.",
		"driver_redispatched" => "The driver is resipatched and the driver should be arriving shortly.",
		"coordinator_canceled" => "The request was canceled by the Coordinator.",
		"guest_rider_canceled" => "The request was canceled by the Rider.",
	);


	public function __construct()
    {	
    	$this->uberApiUrl = "https://api.uber.com";
        $this->ubClientId = $GLOBALS['ub_client_id'];

        $cryptoGen = new CryptoGen();
        $this->ubClientSecret = $cryptoGen->decryptStandard($GLOBALS['ub_client_secret']);

        $this->ubAccessToken = "";

        // Generate token
        $this->generateToken();
    }

    public function generateToken() {
    	$errMsg = "";

		try {

			if (empty($this->ubClientId)) {
				throw new \Exception("Empty client_id");
			}

			if (empty($this->ubClientSecret)) {
				throw new \Exception("Empty client_secret");
			}

			$cryptoGen = new CryptoGen();

			$scopeList = "health";
			$tokenData = sqlQuery("SELECT client_id, access_token, `scope`, expires_in, (created_date  + INTERVAL expires_in SECOND) AS expires_at, IF(NOW() > (created_date  + INTERVAL expires_in SECOND) - INTERVAL 1 DAY, 'Expiring Soon', 'Not Expiring Soon') AS status from vh_uber_health_token where `scope` = ? AND `client_id`  = ?;", array($scopeList, $this->ubClientId ?? ''));

			if (!empty($tokenData)) {
				if (!empty($tokenData['access_token']) && $tokenData['status'] == "Not Expiring Soon") {
					$this->ubAccessToken = $cryptoGen->decryptStandard($tokenData['access_token']);
					return true;
				} else {
					$this->ubAccessToken = "";
				}
			}

			// Prepare the POST fields
			$data = [
			    'client_id' => $this->ubClientId,
			    'client_secret' => $this->ubClientSecret,
			    'grant_type' => 'client_credentials',
			    'scope' => $scopeList
			];

			// Initialize cURL session
			$ch = curl_init();

			// Set cURL options
			curl_setopt($ch, CURLOPT_URL, 'https://auth.uber.com/oauth/v2/token');
			curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
			curl_setopt($ch, CURLOPT_POST, 1);
			curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));

			// Execute the request and capture the response
			$response = curl_exec($ch);

			if(curl_errno($ch)) {
				throw new \Exception(curl_error($ch));
			} else {
				// Decode the JSON response
    			$response_data = json_decode($response, true);

    			if(isset($response_data['access_token'])) {
			        // Successfully obtained access token
			        $this->ubAccessToken = $response_data['access_token'];

			        $tmpAccessToken = $cryptoGen->encryptStandard($response_data['access_token']);

					if (!empty($tokenData)) {
						sqlStatementNoLog("UPDATE `vh_uber_health_token` SET `access_token` = ?, `expires_in` = ?, `created_date` = NOW() WHERE `scope` = ? AND `client_id`  = ?", array($tmpAccessToken, $response_data['expires_in'] ?? '', $scopeList, $this->ubClientId ?? ''));
					} else {
						sqlInsert("INSERT INTO `vh_uber_health_token` ( `client_id`, `access_token`, `scope`, `expires_in` ) VALUES (?, ?, ?, ?) ", array($this->ubClientId, $tmpAccessToken, $response_data['scope'] ?? '', $response_data['expires_in'] ?? ''));
					}

			    } else {
			        // Handle errors (e.g., invalid client credentials)
			        throw new \Exception($response_data['error_description']);
			    }
			}

			// Close the cURL session
			curl_close($ch);

		} catch (\Throwable $e) {
			$errMsg = $e->getMessage();
		}
    }

    public function getEstimedTime($payload = array()) {
    	$responceData = array();

    	try {

    		if (empty($this->ubAccessToken)) {
				throw new \Exception("Empty access_token");
			}

			// Uber Estimate Time URL (v1.2 endpoint for time estimate)
			$url = $this->uberApiUrl . "/v1/health/trips/estimates";

			// Initialize cURL session
			$ch = curl_init();

			// Set cURL options
			curl_setopt($ch, CURLOPT_URL, $url);
			curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
			curl_setopt($ch, CURLOPT_HTTPHEADER, [
				"Content-Type: application/json",  // Indicate the content type is JSON
			    "Authorization: Bearer $this->ubAccessToken"  // Pass the access token in the Authorization header
			]);

			curl_setopt($ch, CURLOPT_POST, true);  // Set the request method to POST
			curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));  // Attach the JSON data to the POST request

			// Execute the cURL request and capture the response
			$response = curl_exec($ch);

			// Check for cURL errors
			if (curl_errno($ch)) {
				throw new \Exception(curl_error($ch));
			} else {
				// Get the HTTP response code
				$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

				// // Decode the JSON response
			    $resData = json_decode($response, true);

				// Check if the HTTP response code is not valid (e.g., not 200 OK)
				if ($httpCode != 200) {
					if (isset($resData['message'])) {
						throw new \Exception($resData['message']);
					} else {
					    // If the response code is not 200, throw an exception
					    throw new \Exception('Request failed with status code: ' . $httpCode);
					}
				}

				$responceData = $resData;
			}

			// Close the cURL session
			curl_close($ch);

		} catch (\Throwable $e) {
			throw new \Exception($e->getMessage());
		}

		return $responceData;
    }

    public function getZones($lat = "", $lng = "") {
    	$responceData = array();

    	try {

    		if (empty($this->ubAccessToken)) {
				throw new \Exception("Empty access_token");
			}

			// Uber Estimate Time URL (v1.2 endpoint for time estimate)
			$url = $this->uberApiUrl . "/v1/health/zones?latitude=" . $lat . "&longitude=" . $lng;

			// Initialize cURL session
			$ch = curl_init();

			// Set cURL options
			curl_setopt($ch, CURLOPT_URL, $url);
			curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
			curl_setopt($ch, CURLOPT_HTTPHEADER, [
			    "Authorization: Bearer $this->ubAccessToken"  // Pass the access token in the Authorization header
			]);

			// Execute the cURL request and capture the response
			$response = curl_exec($ch);

			// Check for cURL errors
			if (curl_errno($ch)) {
				throw new \Exception(curl_error($ch));
			} else {
				// Get the HTTP response code
				$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

				// // Decode the JSON response
			    $resData = json_decode($response, true);

				// Check if the HTTP response code is not valid (e.g., not 200 OK)
				if ($httpCode != 200) {
					if (isset($resData['message'])) {
						throw new \Exception($resData['message']);
					} else {
					    // If the response code is not 200, throw an exception
					    throw new \Exception('Request failed with status code: ' . $httpCode);
					}
				}

				$responceData = $resData;
			}

			// Close the cURL session
			curl_close($ch);

		} catch (\Throwable $e) {
			throw new \Exception($e->getMessage());
		}

		return $responceData;
    }

    public function createHealthTrip($payload = array()) {
    	$responceData = array();

    	$errorcodeList = array(
    		"fare_expired" => 10001,
    		"surge" => 10002
    	);

    	try {

    		if (empty($this->ubAccessToken)) {
				throw new \Exception("Empty access_token");
			}

			// Uber Estimate Time URL (v1.2 endpoint for time estimate)
			//$url = "https://sandbox-api.uber.com/v1/health/trips";
			$url = $this->uberApiUrl . "/v1/health/trips";

			// Initialize cURL session
			$ch = curl_init();

			// Set cURL options
			curl_setopt($ch, CURLOPT_URL, $url);
			curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
			curl_setopt($ch, CURLOPT_HTTPHEADER, [
				"Content-Type: application/json",  // Indicate the content type is JSON
			    "Authorization: Bearer $this->ubAccessToken"  // Pass the access token in the Authorization header
			]);

			curl_setopt($ch, CURLOPT_POST, true);  // Set the request method to POST
			curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));  // Attach the JSON data to the POST request

			// Execute the cURL request and capture the response
			$response = curl_exec($ch);

			// Check for cURL errors
			if (curl_errno($ch)) {
				throw new \Exception(curl_error($ch));
			} else {
				// Get the HTTP response code
				$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

				// Decode the JSON response
			    $resData = json_decode($response, true);

				// Check if the HTTP response code is not valid (e.g., not 200 OK)
				if ($httpCode != 200) {
					if (isset($resData['message'])) {
						throw new \Exception($resData['message'], $errorcodeList[$resData['code']] ?? 0);
					} else {
					    // If the response code is not 200, throw an exception
					    throw new \Exception('Request failed with status code: ' . $httpCode);
					}
				}

				$responceData = $resData;
			}

			// Close the cURL session
			curl_close($ch);

    	} catch (\Throwable $e) {
			throw new \Exception($e->getMessage(), $e->getCode());
		}

		return $responceData;
    }

    public function cancelHealthTripDetails($request_id = "") {
    	$responceData = array();

    	try {

    		if (empty($this->ubAccessToken)) {
				throw new \Exception("Empty access_token");
			}

			if (empty($request_id)) {
				throw new \Exception("Empty request id");
			}

			//$url = "https://sandbox-api.uber.com/v1/health/trips/" . $request_id;
			$url = $this->uberApiUrl . "/v1/health/trips/" . $request_id;

			// Initialize cURL session
			$ch = curl_init();

			// Set cURL options
			curl_setopt($ch, CURLOPT_URL, $url);
			curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
			curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "DELETE");
			curl_setopt($ch, CURLOPT_HTTPHEADER, [
				"Content-Type: application/json",  // Indicate the content type is JSON
			    "Authorization: Bearer $this->ubAccessToken"  // Pass the access token in the Authorization header
			]);

			// Execute the cURL request and capture the response
			$response = curl_exec($ch);

			// Check for cURL errors
			if (curl_errno($ch)) {
				throw new \Exception(curl_error($ch));
			} else {
				// Get the HTTP response code
				$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

				// Decode the JSON response
			    $resData = json_decode($response, true);

				// Check if the HTTP response code is not valid (e.g., not 200 OK)
				if ($httpCode != 200) {
					if (isset($resData['message'])) {
						throw new \Exception($resData['message']);
					} else {
					    // If the response code is not 200, throw an exception
					    throw new \Exception('Request failed with status code: ' . $httpCode);
					}
				}

				$responceData = array(
			    	"message" => "cancelled"
			    );
			}

			// Close the cURL session
			curl_close($ch);

		} catch (\Throwable $e) {
			throw new \Exception($e->getMessage(), $e->getCode());
		}

		return $responceData;
    }

    public function getHealthTripDetails($request_id = "") {
    	$responceData = array();

    	try {

    		if (empty($this->ubAccessToken)) {
				throw new \Exception("Empty access_token");
			}

			if (empty($request_id)) {
				throw new \Exception("Empty request id");
			}

			//$url = "https://sandbox-api.uber.com/v1/health/trips/" . $request_id;
			$url = $this->uberApiUrl . "/v1/health/trips/" . $request_id;

			// Initialize cURL session
			$ch = curl_init();

			// Set cURL options
			curl_setopt($ch, CURLOPT_URL, $url);
			curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
			curl_setopt($ch, CURLOPT_HTTPHEADER, [
				"Content-Type: application/json",  // Indicate the content type is JSON
			    "Authorization: Bearer $this->ubAccessToken"  // Pass the access token in the Authorization header
			]);

			// Execute the cURL request and capture the response
			$response = curl_exec($ch);

			// Check for cURL errors
			if (curl_errno($ch)) {
				throw new \Exception(curl_error($ch));
			} else {
				// Get the HTTP response code
				$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

				// // Decode the JSON response
			    $resData = json_decode($response, true);

				// Check if the HTTP response code is not valid (e.g., not 200 OK)
				if ($httpCode != 200) {
					if (isset($resData['message'])) {
						throw new \Exception($resData['message']);
					} else {
					    // If the response code is not 200, throw an exception
					    throw new \Exception('Request failed with status code: ' . $httpCode);
					}
				}

				$responceData = $resData;
			}

			// Close the cURL session
			curl_close($ch);

		} catch (\Throwable $e) {
			throw new \Exception($e->getMessage(), $e->getCode());
		}

		return $responceData;
    }

    public function prepareDataForLog($tripdetails = array(), $tripdetails1 = array()) {
    	$response = array();

    	$guestDetails = $tripdetails['guest'] ?? array();
    	if (empty($guestDetails)) {
    		$guestDetails = $tripdetails1['guest'] ?? array();
    	}

    	$linkedTripDetails = $tripdetails['linked_trip_details'] ?? array();
    	$pickupDetails = $tripdetails['pickup'] ?? array();
    	$destinationDetails = $tripdetails['destination'] ?? array();

    	$rider_phone_number = preg_replace('/\D/', '', $guestDetails['phone_number'] ?? "");


    	$response['request_id'] = $tripdetails['request_id'] ?? "";
    	if (empty($response['request_id'])) {
    		$response['request_id'] = $tripdetails1['request_id'] ?? "";
    	}

    	$response['linked_request_id'] = $linkedTripDetails['request_id'] ?? "";
    	if (empty($response['linked_request_id'])) {
    		$response['linked_request_id'] = $tripdetails1['linked_request_id'] ?? "";
    	}


    	$response['rider_first_name'] = $guestDetails['first_name'] ?? NULL;
    	$response['rider_last_name'] = $guestDetails['last_name'] ?? NULL;
    	$response['rider_phone_number'] = $rider_phone_number ?? NULL;

    	$response['pickup_lat'] = $pickupDetails['latitude'] ?? 0;
    	$response['pickup_lng'] = $pickupDetails['longitude'] ?? 0;
    	$response['pickup_address'] = $pickupDetails['address'] ?? NULL;

    	$response['dropoff_lat'] = $destinationDetails['latitude'] ?? 0;
    	$response['dropoff_lng'] = $destinationDetails['longitude'] ?? 0;
    	$response['dropoff_address'] = $destinationDetails['address'] ?? NULL;

    	$response['trip_type'] = NULL;
    	if (!empty($tripdetails["total_trip_legs"])) {
    		if ($tripdetails["total_trip_legs"] > 1) {
    			$response['trip_type'] = "roundtrip";
    		} else if ($tripdetails["total_trip_legs"] == 1) {
    			$response['trip_type'] = "onewaytrip";
    		}
    	}

    	$response['trip_leg_number'] = $tripdetails["trip_leg_number"] ?? 0;

    	$response['trip_status'] = $tripdetails["status"] ?? NULL;
    	if (empty($response['trip_status'])) {
    		$response['trip_status'] = $tripdetails1['status'] ?? NULL;
    	}

    	// Schedule datetime
    	$response['trip_schedule_date'] = NULL;
    	$response['trip_schedule_time'] = NULL;

    	if (isset($tripdetails['deferred_ride_options']) && !empty($tripdetails['deferred_ride_options']['pickup_day'] ?? "")) {
    		$response['trip_schedule_date'] = $tripdetails['deferred_ride_options']['pickup_day'] ?? NULL; 
		} else if (isset($tripdetails['scheduling']) && !empty($tripdetails['scheduling']['pickup_time'] ?? "")) {
			$formatedDateVal = date("Y-m-d", $tripdetails['scheduling']['pickup_time'] / 1000);
			$formatedTimeVal = date("H:i:s", $tripdetails['scheduling']['pickup_time'] / 1000);

			if (!empty($formatedDateVal)) { 
				$response['trip_schedule_date'] = $formatedDateVal; 
			}

			if (!empty($formatedTimeVal)) { 
				$response['trip_schedule_time'] = $formatedTimeVal; 
			}
		} else if (!empty($tripResponce['request_time'] ?? "")) {
			$formatedDateVal = date("Y-m-d", $tripResponce['request_time'] / 1000);
			$formatedTimeVal = date("H:i:s", $tripResponce['request_time'] / 1000);

			if (!empty($formatedDateVal)) { 
				$response['trip_schedule_date'] = $formatedDateVal; 
			}

			if (!empty($formatedTimeVal)) { 
				$response['trip_schedule_time'] = $formatedTimeVal; 
			}
		}


    	$response['trip_response'] = !empty($tripdetails) ? json_encode($tripdetails) : NULL;

    	return $response;
    }

    public function insertTripHistroyLog($request_id = "", $event = "", $event_value = "") {
    	if (!empty($request_id)) {
    		sqlInsert("INSERT INTO `vh_uber_health_trips_log` (`request_id`, `event`, `event_value`) VALUES (?, ?, ?)", array($request_id, $event, $event_value));
    	}
    }

    public function saveTripDetails($request_id = "") {
    	$responceData = array();

    	try {

    		// Get and fetch trip details.
			$fullTripDetails = $this->getHealthTripDetails($request_id);

			if (empty($fullTripDetails)) {
				throw new \Exception("Unable to fetch trip details");
			}

			// Prepare data for log
			$preparedDataForLog =$this->prepareDataForLog($fullTripDetails);

			$tripData = sqlQuery("SELECT count(vuht.id) as total_count from vh_uber_health_trips vuht where vuht.request_id  = ?", array($request_id));

			if (!empty($tripData) && $tripData['total_count'] > 0) {
				echo "UPDATE DATA";
				$updateBinds = array(
					$preparedDataForLog['rider_first_name'] ?? NULL,
					$preparedDataForLog['rider_last_name'] ?? NULL,
					$preparedDataForLog['rider_phone_number'] ?? NULL,
					$preparedDataForLog["pickup_lat"] ?? 0,
					$preparedDataForLog["pickup_lng"] ?? 0,
					$preparedDataForLog["pickup_address"] ?? NULL,
					$preparedDataForLog["dropoff_lat"] ?? 0,
					$preparedDataForLog["dropoff_lng"] ?? 0,
					$preparedDataForLog["dropoff_address"] ?? NULL,
					$preparedDataForLog["trip_type"] ?? NULL,
					$preparedDataForLog["trip_leg_number"] ?? 0,
					$preparedDataForLog["trip_status"] ?? NULL,
					$preparedDataForLog["linked_request_id"] ?? NULL,
					$preparedDataForLog["trip_schedule_date"] ?? NULL,
					$preparedDataForLog["trip_schedule_time"] ?? NULL,
					$preparedDataForLog["trip_response"] ?? NULL,
					$request_id ?? $preparedDataForLog['request_id'],
				);

				// Update
				$tripUpdateLog = sqlQueryNoLog("UPDATE `vh_uber_health_trips` SET `rider_first_name` = ?, `rider_last_name` = ?, `rider_phone_number` = ?, `pickup_lat` = ?, `pickup_lng` = ?, `pickup_address` = ?, `dropoff_lat` = ?, `dropoff_lng` = ?, `dropoff_address` = ?, `trip_type` = ?, `trip_leg_number` = ?, `trip_status` = ?, `linked_request_id` = ?, `trip_schedule_date` = ?, `trip_schedule_time` = ?, `trip_response` = ? WHERE request_id = ? ", $updateBinds, true);

				// Responce trip log
				$this->insertTripHistroyLog($request_id, "status-change-" . $preparedDataForLog["trip_status"], json_encode($fullTripDetails));

			} else {
				
				$pids = array();
				if (!empty($preparedDataForLog['rider_phone_number'])) {
					$riderPhoneNumber = preg_replace("/[^0-9]/", "", $preparedDataForLog['rider_phone_number']);

					$pParam = array();
	    			$pParam["phone_cell"] = array("value" => $riderPhoneNumber, "condition" => "");
	    			$pat_data = @getPatientByCondition($pParam, "pid");

	    			if (!empty($pat_data) && is_array($pat_data) && array_key_exists('pid', $pat_data)) {
						$pids[] = $pat_data['pid'];
					} else if(is_array($pat_data) && count($pat_data) > 0) {
						foreach ($pat_data as $pItem) {
							$pids[] = $pItem['pid'];
						}
					}
				}

				// Unique patient found
				$pid = ( is_array($pids) && count($pids) == 1) ? $pids[0] : 0;

				$tripLog = sqlInsert("INSERT INTO `vh_uber_health_trips` ( 
					request_id, 
					pid,
					user_id,
					rider_first_name,
					rider_last_name,
					rider_phone_number,
					pickup_lat,
					pickup_lng,
					pickup_address,
					dropoff_lat,
					dropoff_lng,
					dropoff_address,
					trip_type,
					trip_leg_number,
					trip_status, 
					linked_request_id,
					trip_schedule_date,
					trip_schedule_time,
					trip_request_payload,
					trip_response 
				) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?) ", array(
					$preparedDataForLog['request_id'] ?? "",
					$pid, 
					(int)($_SESSION['authUserID'] ?? 0),
					$preparedDataForLog['rider_first_name'] ?? NULL,
					$preparedDataForLog['rider_last_name'] ?? NULL,
					$preparedDataForLog['rider_phone_number'] ?? NULL,
					$preparedDataForLog["pickup_lat"] ?? 0,
					$preparedDataForLog["pickup_lng"] ?? 0,
					$preparedDataForLog["pickup_address"] ?? NULL,
					$preparedDataForLog["dropoff_lat"] ?? 0,
					$preparedDataForLog["dropoff_lng"] ?? 0,
					$preparedDataForLog["dropoff_address"] ?? NULL,
					$preparedDataForLog["trip_type"] ?? NULL,
					$preparedDataForLog["trip_leg_number"] ?? 0,
					$preparedDataForLog["trip_status"] ?? NULL,
					$preparedDataForLog["linked_request_id"] ?? NULL,
					$preparedDataForLog["trip_schedule_date"] ?? NULL,
					$preparedDataForLog["trip_schedule_time"] ?? NULL,
					NULL,
					$preparedDataForLog["trip_response"] ?? NULL,
				));

				// Responce trip log
				$this->insertTripHistroyLog($request_id, "webhook-created", json_encode($fullTripDetails));
			}

    	} catch (\Throwable $e) {
			throw new \Exception($e->getMessage(), $e->getCode());
		}
    }

    public static function getPatientAddress($patientData = array()) {
		$patientaddress = array();

		if (!empty($patientData)) {
			if (!empty($patientData)) {
				if (!empty($patientData['street'])) {
					$patientaddress[] = $patientData['street'];
				}

				if (!empty($patientData['city'])) {
					$patientaddress[] = $patientData['city'];
				}

				if (!empty($patientData['state'])) {
					$patientaddress[] = $patientData['state'] ." ". $patientData['postal_code'];
				}
			}
		}

		$patientaddress = implode(", ", $patientaddress);

		return $patientaddress;
	}

	public static function getFacilityAddress($facility_id = "") {
		$returnItems = array();
		if (!empty($facility_id)) {
			$facilityData = sqlQuery("SELECT * FROM `facility` f WHERE f.id = ? ", array($facility_id));
			$facilityData = array($facilityData);
		} else {
			$res = sqlStatement("SELECT * FROM `facility` f", array());
			$facilityData = array();
			while($row = sqlFetchArray($res)) {
				$facilityData[] = $row;
			}
		}

		if (!empty($facilityData)) {
			foreach ($facilityData as $facilityDataItem) {
				$facilityaddress = array();
				$facilityName = "";

				if (!empty($facilityDataItem['street'])) {
					$facilityaddress[] = $facilityDataItem['street'];
				}

				if (!empty($facilityDataItem['city'])) {
					$facilityaddress[] = $facilityDataItem['city'];
				}

				if (!empty($facilityDataItem['state'])) {
					$facilityaddress[] = $facilityDataItem['state'] ." ". $facilityDataItem['postal_code'];
				}

				if (!empty($facilityDataItem['name'])) {
					$facilityName = $facilityDataItem['name'];
				}

				$facilityaddress = implode(", ", $facilityaddress);
				$returnItems[] = array("name" => $facilityName, "address" => $facilityaddress);
			}
		}


		return $returnItems;
	}

	public static function getGeolocationDetails($address = '') {
		try {

			// Create a URL for the API request
			$geocodeUrl = 'https://maps.googleapis.com/maps/api/geocode/json?address=' . urlencode($address) . '&key=' . UberController::GOOGLE_MAP_KEY;

			// Initialize cURL session
			$ch = curl_init();

			// Set cURL options
			curl_setopt($ch, CURLOPT_URL, $geocodeUrl);
			curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);

			// Execute the request and get the response
			$response = curl_exec($ch);

			// Close cURL session
			curl_close($ch);

			// Decode the JSON response
			$responseData = json_decode($response, true);

			// Check for cURL errors
			if (curl_errno($ch)) {
				throw new \Exception(curl_error($ch));
			} else {

				if ($responseData['status'] != "OK" || !isset($responseData['results'][0]['geometry']) || !isset($responseData['results'][0]['geometry']['location']) || !isset($responseData['results'][0]['address_components'])) {
					throw new \Exception("Unable to fetch details");
				}

				$addressComponentStatus = 0;

				foreach ($responseData['results'][0]['address_components'] as $addressComponentItems) {
					if (!empty($addressComponentItems) && isset($addressComponentItems['types']) && !empty($addressComponentItems['long_name'] ?? "")) {
						if (in_array("subpremise", $addressComponentItems['types'])) {
							$addressComponentStatus++;
						} else if (in_array("street_number", $addressComponentItems['types'])) {
							$addressComponentStatus++;
						} else if (in_array("route", $addressComponentItems['types'])) {
							$addressComponentStatus++;
						}
					}
				}

				if ($addressComponentStatus !== 3 && $addressComponentStatus !== 2) {
					throw new \Exception("Not valid address");
				}
				

				$geometryDetails = $responseData['results'][0]['geometry']['location'];

				return $geometryDetails;
			}

			// Close the cURL session
			curl_close($ch);

		} catch (\Throwable $e) {
			throw new \Exception($e->getMessage(), $e->getCode());
		}

		return false;
	}

	public static function getDistancematrix($origins = '', $destinations = '') {
		try {

			if (empty($origins) || empty($destinations)) {
				throw new \Exception("Valid Addresses not available.");
			}

			// Create a URL for the API request
			$geocodeUrl = 'https://maps.googleapis.com/maps/api/distancematrix/json?origins=' . urlencode($origins) . '&destinations=' . urlencode($destinations) . '&key=' . UberController::GOOGLE_MAP_KEY;

			// Initialize cURL session
			$ch = curl_init();

			// Set cURL options
			curl_setopt($ch, CURLOPT_URL, $geocodeUrl);
			curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);

			// Execute the request and get the response
			$response = curl_exec($ch);

			// Close cURL session
			curl_close($ch);

			// Decode the JSON response
			$responseData = json_decode($response, true);

			// Check for cURL errors
			if (curl_errno($ch)) {
				throw new \Exception(curl_error($ch));
			} else {

				if ($responseData['status'] != "OK" || !isset($responseData['rows'][0]['elements'])) {
					throw new \Exception("Unable to fetch details");
				}
				
				return $responseData['rows'][0]['elements'];
			}

			// Close the cURL session
			curl_close($ch);

		} catch (\Throwable $e) {
			throw new \Exception($e->getMessage(), $e->getCode());
		}

		return false;
	}

	public static function saveGeoLocation($formatted_address = '', $lat = 0, $lng = 0) {
		$isLocationExists = sqlQuery("SELECT count(id) as total_count FROM `vh_addresses_geocode` WHERE formatted_address = ? LIMIT 1", array($formatted_address ?? ''));

		if (!empty($isLocationExists) && $isLocationExists['total_count'] == 0) {
			return sqlInsert(
				"INSERT INTO `vh_addresses_geocode` ( formatted_address, lat, lng ) VALUES (?, ?, ?) ", 
				array(
					$formatted_address ?? '',
					$lat ?? '',
					$lng ?? ''
				)
			);
		}

		return false;
	}
}