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
	const CANCELLED_STATUS_LIST = array(self::NO_DRIVERS_AVAILABLE_LABEL, self::DRIVER_CANCELED_LABEL, self::RIDER_CANCELED_LABEL, self::FAILED_LABEL, self::OFFERED_LABEL, self::EXPIRED_LABEL);

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
			$url = "https://sandbox-api.uber.com/v1/health/trips";
			//$url = $this->uberApiUrl . "/v1/health/trips";

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

				$httpCode = 200;
				$response = $this->dummyResponce()['create_trip2'];

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

			$url = "https://sandbox-api.uber.com/v1/health/trips/" . $request_id;
			//$url = $this->uberApiUrl . "/v1/health/trips/" . $request_id;

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

			$url = "https://sandbox-api.uber.com/v1/health/trips/" . $request_id;
			//$url = $this->uberApiUrl . "/v1/health/trips/" . $request_id;

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

				$httpCode = 200;
				$response = $this->dummyResponce()['get_trip2'];

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

    public function getAllHealthTripsDetails($qtrStr= "") {
    	$responceData = array();

    	try {

    		if (empty($this->ubAccessToken)) {
				throw new \Exception("Empty access_token");
			}

			$url = $this->uberApiUrl . "/v1/health/trips?limit=50&" . $qtrStr;

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

    public function getFullTripsDetails($next_key = "", $ic = 0) {
    	$tripListItems = $this->getAllHealthTripsDetails($next_key);
    	$retunItems = array();

    	if ($ic === 2) {
    		return array();
    	}

    	if (!empty($tripListItems)) {
    		if (!empty($tripListItems['trips'] ?? "")) {
	    		$retunItems = $tripListItems['trips'];
	    		print_r("\n" .count($retunItems));
	    	}

	    	if (!empty($tripListItems['next_key'] ?? "")) {
	    		print_r("\n".$tripListItems['next_key']);
	    		sleep(1);
	    		$ic++;
	    		$nextTripDetails = $this->getFullTripsDetails("start_key=" . $tripListItems['next_key'], $ic);

	    		if (!empty($nextTripDetails)) {
	    			$retunItems = array_merge($retunItems, $nextTripDetails);
	    		}
	    	}

	    	echo "\n--------------------------------";

    	}

    	return $retunItems;
    }

    public function fetchUberTrips() {
    	$tripListItems = $this->getFullTripsDetails("");

    	//print_r($tripListItems);
    	echo count($tripListItems);
    }

    // Dummy responce
    public function dummyResponce() {
    	return array(
    		'token' => '{"access_token": "abcdefg","token_type": "Bearer","expires_in": "<EXPIRY_IN_EPOCH>","scope": "<SPACE_DELIMITED_LIST_OF_SCOPES>"}',
    		'products' => '{"products":[{"product_id":"cbfbc5a1-c64f-4c16-a0e3-e7a64cba0f9f","display_name":"UberX","description":"Affordable, everyday rides","capacity":4,"price_details":{"base_fare":2.5,"cost_per_minute":0.18,"cost_per_mile":1.1,"currency_code":"USD"},"image_url":"https://d3i5bpy3jdd1l5.cloudfront.net/uberx.png","service_icon_url":"https://d3i5bpy3jdd1l5.cloudfront.net/uberx-icon.png"},{"product_id":"f9f9f9f9-c64f-4c16-a0e3-e7a64cba0f9f","display_name":"UberXL","description":"Rides for up to 6 people","capacity":6,"price_details":{"base_fare":3.5,"cost_per_minute":0.2,"cost_per_mile":1.5,"currency_code":"USD"},"image_url":"https://d3i5bpy3jdd1l5.cloudfront.net/uberxl.png","service_icon_url":"https://d3i5bpy3jdd1l5.cloudfront.net/uberxl-icon.png"}]}',
    		'rideestimedtime' => '{"fare":{"value":4.32,"fare_id":"d67b07577b3c86fd23e483d50c84e5152e550b6abb03cece4fc3793c0c068f2e","expires_at":1477285210,"display":"$4.32","currency_code":"USD"},"trip":{"distance_unit":"mile","duration_estimate":600,"distance_estimate":2.39},"pickup_estimate":4}',
    		'estimates' => '{"etas_unavailable":false,"fares_unavailable":false,"product_estimates":[{"estimate_info":{"fare":{"currency_code":"USD","display":"$11.96","expires_at":1558147984,"fare_breakdown":[{"name":"Base Fare","type":"base_fare","value":11.96}],"fare_id":"6e642142-27b8-49df-82e0-3d4a8633e79d","value":11.96},"fare_id":"6e642142-27b8-49df-82e0-3d4a8633e79d","pickup_estimate":4,"trip":{"distance_estimate":0.44,"distance_unit":"mile","duration_estimate":927,"travel_distance_estimate":0.45}},"product":{"background_image":"https://d1a3f4spazzrp4.cloudfront.net/car-types/productImageBackgrounds/package_x_bg.png","capacity":4,"description":"Affordable rides, all to yourself","display_name":"UberX","image":"https://d1a3f4spazzrp4.cloudfront.net/car-types/productImages/package_x_fg.png","parent_product_type_id":"6a8e56b8-914e-4b48-a387-e6ad21d9c00c","product_group":"uberx","product_id":"b8e5c464-5de2-4539-a35a-986d6e58f186","scheduling_enabled":true,"shared":false,"short_description":"UberX","upfront_fare_enabled":true,"vehicle_view_id":39,"cancellation":{"min_cancellation_fee":5,"cancellation_grace_period_threshold_sec":120}},"fulfillment_indicator":"GREEN","fulfillment_indicators":{"request_blocker":{"block_type":"NONE"},"availability_predictor":{"predictor_result":"GREEN"}}},{"estimate_info":{"fare":{"currency_code":"USD","display":"$11.96","expires_at":1558147984,"fare_breakdown":[{"name":"Base Fare","type":"base_fare","value":11.96}],"fare_id":"c143d9ab-57c5-4d35-981f-7a356a2f22e9","value":11.96},"fare_id":"c143d9ab-57c5-4d35-981f-7a356a2f22e9","pickup_estimate":8,"pricing_explanation":"Fares are higher due to increased demand","trip":{"distance_estimate":0.44,"distance_unit":"mile","duration_estimate":1178,"travel_distance_estimate":0.45}},"product":{"background_image":"https://d1a3f4spazzrp4.cloudfront.net/car-types/productImageBackgrounds/package_wav_bg.png","capacity":4,"description":"Wheelchair-accessible rides","display_name":"WAV","image":"https://d1a3f4spazzrp4.cloudfront.net/car-types/productImages/package_wav_fg.png","parent_product_type_id":"6a8e56b8-914e-4b48-a387-e6ad21d9c00c","product_group":"uberx","product_id":"3bca1cd3-df15-49d8-bd4f-93e014fc26ff","scheduling_enabled":true,"shared":false,"short_description":"WAV","upfront_fare_enabled":true,"vehicle_view_id":10000936,"cancellation":{"min_cancellation_fee":5,"cancellation_grace_period_threshold_sec":120}},"fulfillment_indicator":"YELLOW","fulfillment_indicators":{"request_blocker":{"block_type":"NONE"},"availability_predictor":{"predictor_result":"YELLOW"}}},{"estimate_info":{"fare":{"currency_code":"USD","display":"$28.00","expires_at":1574718553,"fare_breakdown":[{"name":"Base Fare","type":"base_fare","value":28}],"fare_id":"bbdce077-4088-449e-a898-a34df5c293c4","value":28},"fare_id":"bbdce077-4088-449e-a898-a34df5c293c4","no_cars_available":true,"trip":{"distance_estimate":0.44,"distance_unit":"mile","duration_estimate":1126,"travel_distance_estimate":0.45}},"product":{"background_image":"https://d1a3f4spazzrp4.cloudfront.net/car-types/productImageBackgrounds/package_blackCar_bg.png","capacity":4,"description":"Premium rides in luxury cars","display_name":"Black","image":"https://d1a3f4spazzrp4.cloudfront.net/car-types/productImages/package_blackCar_fg.png","parent_product_type_id":"f1faedb7-5825-484f-b236-24e2cd95ec23","product_group":"uberblack","product_id":"0e9d8dd3-ffec-4c2b-9714-537e6174bb88","scheduling_enabled":true,"shared":false,"short_description":"Black","upfront_fare_enabled":true,"vehicle_view_id":4,"cancellation":{"min_cancellation_fee":5,"cancellation_grace_period_threshold_sec":120}},"fulfillment_indicator":"GREEN","fulfillment_indicators":{"request_blocker":{"block_type":"NONE"},"availability_predictor":{"predictor_result":"GREEN"}}},{"estimate_info":{"fare_id":"f9f5d23e-3148-4632-8f61-727f654a265e","fare":{"fare_id":"f9f5d23e-3148-4632-8f61-727f654a265e","value":135.32,"currency_code":"USD","display":"$135.32","expires_at":1698073761,"fare_breakdown":[{"type":"base_fare","value":135.32,"name":"Base Fare"}],"hourly":{"tiers":[{"amount":135.32,"time_in_mins":120,"distance":30,"distance_unit":"mile","formatted_time_and_distance_package":"2 hrs/30 miles"},{"amount":195.32,"time_in_mins":180,"distance":45,"distance_unit":"mile","formatted_time_and_distance_package":"3 hrs/45 miles"},{"amount":255.32,"time_in_mins":240,"distance":60,"distance_unit":"mile","formatted_time_and_distance_package":"4 hrs/60 miles"}],"overage_rates":{"overage_rate_per_temporal_unit":1.4,"overage_rate_per_distance_unit":3.55,"temporal_unit":"TEMPORAL_UNIT_MINUTE","distance_unit":"mile","pricing_explainer":"Extra time will be charged to you at $1.40 per minute. Extra distance will be charged to you at  $3.55 per mi."}}},"trip":{"distance_unit":"mile","distance_estimate":2.71,"duration_estimate":0,"travel_distance_estimate":2.71}},"product":{"product_id":"aad1febe-9780-4b91-a92f-8f7c58d5cf54","display_name":"Black Hourly","description":"Luxury rides by the hour with professional drivers","short_description":"Black Hourly","product_group":"","image":"https://d1a3f4spazzrp4.cloudfront.net/car-types/haloProductImages/v1.1/Black_v1.png","upfront_fare_enabled":true,"shared":false,"capacity":4,"scheduling_enabled":true,"background_image":"https://d1a3f4spazzrp4.cloudfront.net/car-types/haloProductImageBackgrounds/imageBackground@3x_v0.png","vehicle_view_id":20035361,"parent_product_type_id":"5354dad7-2f82-405b-b99a-9ff8acd70223","reserve_info":{"enabled":true,"scheduled_threshold_minutes":120,"free_cancellation_threshold_minutes":60,"add_ons":[],"valid_until_timestamp":1698097557000},"advance_booking_type":"RESERVE","cancellation":{"min_cancellation_fee":5,"cancellation_grace_period_threshold_sec":120}}}]}',
    		'create_trip' => '{"code":"surge","message":"Fare is higher than normal, request again to confirm","metadata":{"fare_id":"dcf8a733-7f11-4544-a9c7-ddea71e46146","fare_display":"$12-15","multiplier":1.4,"expires_at":1501684062}}',
    		'create_trip1' => '{"eta":7,"guest":{"email":"joe@example.com","first_name":"Joe","guest_id":"OVmNxPG1ENYSwn8QonwLapF3ZJl2KJipkUc0AP3C74U=","last_name":"Example","phone_number":"+15551234567"},"product_id":"a1111c8c-c720-46c3-8534-2fcdd730040d","request_id":"def481ba-f21b-43f8-9ab3-5bdbc603af0c","status":"processing"}',
    		'create_trip2' => '{"eta":8,"guest":{"first_name":"Garland","guest_id":"2498b39a-f69e-51ba-a978-5e6e2ef76379","has_coordinator_consented":false,"last_name":"Boecking","locale":"en","phone_number":"+15127997890","unregistered_user_uuid":"d40dea0c-2d1c-5329-b4b9-e0e4b582a607"},"linked_request_id":"0da3bd5c-bc0a-4194-8e22-859fa34adbb6","product_id":"0b3f7b7f-6639-4c17-8671-553901bf2540","request_id":"6bd91b20-60ed-42af-b0c1-0c81b4aa7cba","status":"scheduled"}',
    		'get_trip1' => '{"call_enabled":true,"city_id":"4","client_fare":"$39.99","destination":{"address":"1015 W 39 1\/2 St, Austin, TX","latitude":30.3074427,"longitude":-97.7426443,"place":{"place_id":"cfba536e-848e-bfe0-7c9c-6e0308711a8d","provider":"uber_global_hotspots"},"subtitle":"Austin, TX","timezone":"America\/Chicago","title":"1015 W 39 1\/2 St"},"expense_code":"AUS - CENTRAL AUSTIN","fare":{"currency_code":"USD","value":39.99},"guest":{"first_name":"Natacia","guest_id":"8dd1fdb2-8764-56b9-a5aa-3436a7727b7f","has_coordinator_consented":false,"last_name":"Gillum","locale":"en","member_info":{"member_id":"85823","member_org_name":"Pro-Care Medical Center","member_org_uuid":"ec4a42ae-a4b6-46de-b771-b9c151528928","member_uuid":"80550423-dfb3-5084-b61a-824e46c2c91c","plan_id":""},"phone_number":"+18303851580","unregistered_user_uuid":"e99dd4f1-36ea-5166-af50-dd775ccb2bb0"},"linked_trip_details":{"request_id":"50ec8dee-f5c7-4556-a90f-4891acd43b8e-1","status":"scheduled","trip_leg_number":1},"location_uuid":"ec4a42ae-a4b6-46de-b771-b9c151528928","pickup":{"address":"4100 Hazy Hills Dr, Spicewood, TX 78669-6584, US","latitude":30.3712363683003,"longitude":-98.07007456188425,"place":{"place_id":"here:af:streetsection:p4ouUgAO4JmFgpBluV5aiA:CgcIBCCMj7kVEAEaBDQxMDA","provider":"here_places"},"subtitle":"Spicewood, TX 78669-6584, US","timezone":"America\/Chicago","title":"4100 Hazy Hills Dr"},"policy_uuid":"3e71e6e4-f032-4a4c-80e1-69a8e1db93a0","product":{"display_name":"UberX","parent_product_type_id":"6a8e56b8-914e-4b48-a387-e6ad21d9c00c","product_id":"a795cfc4-14cf-4bdb-b1b9-787812516ff5"},"request_id":"576e1749-f1dc-4ed3-ac60-8e53eef9fc3b-1","requester_group":{"name":""},"requester_name":"Karli Anderson","requester_uuid":"c4020a35-7684-42c5-8128-d28b22792587","rider_tracking_u_r_l":"https:\/\/trip.uber.com\/yLgnFHm","scheduling":{"pickup_time":1735822800000},"spend_cap_details":{"trip_creation_spend_cap_status":"NOT_EXCEEDED"},"status":"scheduled","surge_multiplier":1,"total_trip_legs":2,"trip_leg_number":0}',
    		'get_trip2' => '{"call_enabled":true,"city_id":"4","client_fare":"$39.99","destination":{"address":"1015 W 39 1\/2 St, Austin, TX","latitude":30.3074427,"longitude":-97.7426443,"place":{"place_id":"cfba536e-848e-bfe0-7c9c-6e0308711a8d","provider":"uber_global_hotspots"},"subtitle":"Austin, TX","timezone":"America\/Chicago","title":"1015 W 39 1\/2 St"},"expense_code":"AUS - CENTRAL AUSTIN","fare":{"currency_code":"USD","value":39.99},"guest":{"first_name":"Natacia","guest_id":"8dd1fdb2-8764-56b9-a5aa-3436a7727b7f","has_coordinator_consented":false,"last_name":"Gillum","locale":"en","member_info":{"member_id":"85823","member_org_name":"Pro-Care Medical Center","member_org_uuid":"ec4a42ae-a4b6-46de-b771-b9c151528928","member_uuid":"80550423-dfb3-5084-b61a-824e46c2c91c","plan_id":""},"phone_number":"+18303851580","unregistered_user_uuid":"e99dd4f1-36ea-5166-af50-dd775ccb2bb0"},"location_uuid":"ec4a42ae-a4b6-46de-b771-b9c151528928","pickup":{"address":"4100 Hazy Hills Dr, Spicewood, TX 78669-6584, US","latitude":30.3712363683003,"longitude":-98.07007456188425,"place":{"place_id":"here:af:streetsection:p4ouUgAO4JmFgpBluV5aiA:CgcIBCCMj7kVEAEaBDQxMDA","provider":"here_places"},"subtitle":"Spicewood, TX 78669-6584, US","timezone":"America\/Chicago","title":"4100 Hazy Hills Dr"},"policy_uuid":"3e71e6e4-f032-4a4c-80e1-69a8e1db93a0","product":{"display_name":"UberX","parent_product_type_id":"6a8e56b8-914e-4b48-a387-e6ad21d9c00c","product_id":"a795cfc4-14cf-4bdb-b1b9-787812516ff5"},"request_id":"576e1749-f1dc-4ed3-ac60-8e53eef9fc3b-12","requester_group":{"name":""},"requester_name":"Karli Anderson","requester_uuid":"c4020a35-7684-42c5-8128-d28b22792587","rider_tracking_u_r_l":"https:\/\/trip.uber.com\/yLgnFHm","scheduling":{"pickup_time":1735822800000},"spend_cap_details":{"trip_creation_spend_cap_status":"NOT_EXCEEDED"},"status":"scheduled","surge_multiplier":1,"total_trip_legs":1,"trip_leg_number":0}'
    	);
    }
}