<?php

require_once(dirname(__FILE__, 7) . "/interface/globals.php");
require_once("$srcdir/options.inc.php");
require_once("$srcdir/patient.inc");
require_once("$srcdir/calendar.inc");

use OpenEMR\Common\Acl\AclMain;
use Vitalhealthcare\OpenEMR\Modules\Generic\Controller\UberController;
use OpenEMR\Core\Header;
//use OpenEMR\OemrAd\Smslib;

$form_pid = isset($_REQUEST['form_pid']) ? $_REQUEST['form_pid'] : "";
$form_eid = isset($_REQUEST['form_eid']) && !empty($_REQUEST['form_eid']) ? $_REQUEST['form_eid'] : "";
$form_default_date = isset($_REQUEST['default_date']) && !empty($_REQUEST['default_date']) ? $_REQUEST['default_date'] : "";
$facility_id = isset($_REQUEST['facility_id']) ? $_REQUEST['facility_id'] : "";
$trip_request_id = isset($_REQUEST['trip_request_id']) ? $_REQUEST['trip_request_id'] : "";
$request_mode = isset($_REQUEST['request_mode']) ? $_REQUEST['request_mode'] : "";

function getPatientDetails($form_pid = "") {
	if (empty($form_pid)) {
		return false;
	}

	// Get patient data
	$patientData = getPatientData($form_pid, "pid, fname, lname, mname, street, postal_code, city, state, country_code, phone_cell");
	if (count($patientData) == 1) $patientData = $patientData[0];

	$returnData = array();

	if (!empty($patientData)) {
		$returnData['firstname'] = $patientData['fname'] ?? "";
		$returnData['lastname'] = $patientData['lname'] ?? "";
		$returnData['phonenumber'] = $patientData['phone_cell'] ?? "";

		// Get Patient Address
		$defaultLocationName = getPatientAddress($patientData);
		$dropoffGeocode = !empty($defaultLocationName) ? getAddressGeocode($defaultLocationName) : array();

		if (!empty($dropoffGeocode) && !empty($dropoffGeocode['lat']) && !empty($dropoffGeocode['lng'])) {
			$returnData['location'] = array(
				"lat" => (float) $dropoffGeocode['lat'],
				"lng" => (float) $dropoffGeocode['lng']
			);
		}

		if (!empty($defaultLocationName)) {
			$returnData['location_name'] = $defaultLocationName;
		}
	}

	return $returnData;
}

function getFacilityDetails($facility_id = "") {
	if (empty($facility_id)) {
		return false;
	}

	$returnData = array();
	$defaultLocationName = getFacilityAddress($facility_id);

	if (!empty($defaultLocationName)) {
		$returnData['location_name'] = $defaultLocationName;
	}

	$pickupGeocode = getAddressGeocode($defaultLocationName);
	if (!empty($pickupGeocode) && !empty($pickupGeocode['lat']) && !empty($pickupGeocode['lng'])) {
		$returnData['location'] = array(
			"lat" => (float) $pickupGeocode['lat'],
			"lng" => (float) $pickupGeocode['lng']
		);
	}

	return $returnData;
}

function getDefaultDateTimePicker() {
	ob_start();
	$datetimepicker_timepicker = false;
	$datetimepicker_showseconds = false;
	$datetimepicker_formatInput = true;
	require($GLOBALS['srcdir'] . '/js/xl/jquery-datetimepicker-2-5-4.js.php');
	$datetimepickerparam = ob_get_clean();

	$datetimepickerparam = "{" . $datetimepickerparam . "}";

	// 1. Remove trailing commas in objects
	$validJson = preg_replace('/,(\s*[}\]])/', '$1', $datetimepickerparam);
	// 1. Replace single quotes around string values with double quotes
	$validJson = preg_replace("/'([^']+)'/", '"$1"', $validJson);  // This replaces single quotes around values

	$validJson = preg_replace_callback('/([a-zA-Z0-9_]+)(\s*:\s*)/', function($matches) {
	    return '"' . $matches[1] . '" : ';  // Add quotes around keys
	}, $validJson);

	return json_decode($validJson, true);
}

// Function to convert seconds to MM:SS format
function convertSecondsToMMSS($seconds) {
    $minutes = floor($seconds / 60);  // Get the number of minutes
    $remaining_seconds = $seconds % 60;  // Get the remaining seconds
    return sprintf("%02d:%02d", $minutes, $remaining_seconds); // Format as MM:SS
}

function getTimeStampsec($datetimeStr = "") {
	$timestampInMilliseconds = "";

	// Get the current time zone from the PHP configuration
	$currentTimezone = new DateTimeZone(date_default_timezone_get());

	// Create a DateTime object using the current time zone
	$dateTime = new DateTime($datetimeStr, $currentTimezone);

	// Get the Unix timestamp (in seconds)
	$timestampInSeconds = $dateTime->getTimestamp();

	// Convert to milliseconds
	return $timestampInSeconds * 1000;
}

function getPatientAddress($patientData = array()) {
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

function getFacilityAddress($facility_id) {
	$facilityaddress = array();

	if (!empty($facility_id)) {
		$facilityData = sqlQuery("SELECT * FROM `facility` f WHERE f.id = ? ", array($facility_id));

		if (!empty($facilityData)) {
			if (!empty($facilityData['street'])) {
				$facilityaddress[] = $facilityData['street'];
			}

			if (!empty($facilityData['city'])) {
				$facilityaddress[] = $facilityData['city'];
			}

			if (!empty($facilityData['state'])) {
				$facilityaddress[] = $facilityData['state'] ." ". $facilityData['postal_code'];
			}
		}
	}

	$facilityaddress = implode(", ", $facilityaddress);

	return $facilityaddress;
}

function getAddressGeocode($formatted_address = "") {
	$geocodeDetails = sqlQuery("SELECT * FROM `vh_addresses_geocode` WHERE formatted_address = ? LIMIT 1", array($formatted_address));

	if (empty($geocodeDetails)) {
		return array();
	}

	return $geocodeDetails;
}

function prepareDefaultData($request_id = "") {
	global $todayDate, $currentTime;

	$initialTripObject = array(
		"riderFirstName" => "",
		"riderLastName" => "",
		"riderPhoneNumber" => "",
		"tripType" => "oneway",
		"oneway" => array(
			"startLocationName" => "",
			"startLocation" => null,
			"endLocationName" => "",
	    	"endLocation" => null,
	    	"whenToRide" => "futuretrip",
	    	"scheduleType" => "flexible",
	    	"flexibleRideDate" => $todayDate,
	    	"futureRideDate" => $todayDate,
	    	"futureRideTime" => $currentTime,
	    	"messageToDriver" => "",
	    	"vehicleTypeOptions" => null,
	    	"vehicleType" => ""
		),
		"roundtrip" => array(
			"first_leg" => array(
				"startLocationName" => "",
				"startLocation" => null,
				"endLocationName" => "",
		    	"endLocation" => null,
		    	"whenToRide" => "futuretrip",
		    	"scheduleType" => "flexible",
		    	"flexibleRideDate" => $todayDate,
		    	"futureRideDate" => $todayDate,
		    	"futureRideTime" => $currentTime,
		    	"messageToDriver" => "",
		    	"vehicleTypeOptions" => null,
		    	"vehicleType" => ""
			),
			"return_leg" => array(
				"startLocationName" => "",
				"startLocation" => null,
				"endLocationName" => "",
		    	"endLocation" => null,
		    	"whenToRide" => "futuretrip",
		    	"scheduleType" => "flexible",
		    	"flexibleRideDate" => $todayDate,
		    	"futureRideDate" => $todayDate,
		    	"futureRideTime" => $currentTime,
		    	"messageToDriver" => "",
		    	"vehicleTypeOptions" => null,
		    	"vehicleType" => ""
			)
		)
	);

	if (!empty($request_id)) {
		$tripDetailsData = sqlQuery("SELECT vuht.* from vh_uber_health_trips vuht where vuht.request_id  = ?", array($request_id));

		// Get Default date time param
		$defaultDateParam = getDefaultDateTimePicker();
		$defaultDateFormat = !empty($defaultDateParam['format'] ?? "") ? $defaultDateParam['format'] : "Y-m-d";
		
		if (!empty($tripDetailsData['trip_response'] ?? "")) {
			$tripResponceObj = json_decode($tripDetailsData['trip_response'], true);
			$guestDetails = $tripResponceObj['guest'] ?? array();
			$pickupDetails = $tripResponceObj["pickup"] ?? array();
			$destinationDetails = $tripResponceObj["destination"] ?? array();
			$deferredRideOptions = $tripResponceObj['deferred_ride_options'] ?? array();
			$schedulingOptions = $tripResponceObj['scheduling'] ?? array();

			if (isset($tripResponceObj["total_trip_legs"]) && $tripResponceObj["total_trip_legs"] == "1") {
				$initialTripObject["tripType"] = "oneway";
			} else {
				$initialTripObject["tripType"] = "roundtrip";
			}

			if (!empty($guestDetails['first_name'] ?? "")) {
				$initialTripObject["riderFirstName"] = $guestDetails['first_name'];
			}

			if (!empty($guestDetails['last_name'] ?? "")) {
				$initialTripObject["riderLastName"] = $guestDetails['last_name'];
			}

			if (!empty($guestDetails['phone_number'] ?? "")) {
				$initialTripObject["riderPhoneNumber"] = $guestDetails['phone_number'];
			}

			$whenToRideVal = "pickupnow";
			$scheduleTypeVal = "flexible";
			$flexibleRideDate = "";
			$futureRideDate = "";
			$futureRideTime = "";

			if (!empty($deferredRideOptions) || !empty($schedulingOptions)) {
				$whenToRideVal = "futuretrip";

				if (!empty($deferredRideOptions)) {
					if (!empty($deferredRideOptions['expiration_time_m_s'])) {
		    			$flexibleRideDate = date($defaultDateFormat, strtotime($deferredRideOptions['pickup_day']));
					}
				}

				if (!empty($schedulingOptions)) {
					$scheduleTypeVal = "schedule";

					if (!empty($schedulingOptions['pickup_time'])) {
		    			$futureRideDate = date($defaultDateFormat, $schedulingOptions['pickup_time'] / 1000);
		    			$futureRideTime = date("H:i", $schedulingOptions['pickup_time'] / 1000);
					}
				}
			}

			if ($initialTripObject["tripType"] == "oneway") {
				$initialTripObject["oneway"]["startLocationName"] = $pickupDetails['address'];
				$initialTripObject["oneway"]["startLocation"] = array(
					"lat" => $pickupDetails['latitude'] ?? 0, 
					"lng" => $pickupDetails['longitude'] ?? 0
				);

				$initialTripObject["oneway"]["endLocationName"] = $destinationDetails['address'];
				$initialTripObject["oneway"]["endLocation"] = array(
					"lat" => (float) $destinationDetails['latitude'] ?? 0, 
					"lng" => (float) $destinationDetails['longitude'] ?? 0
				);
				$initialTripObject["oneway"]["whenToRide"] = $whenToRideVal; 
				$initialTripObject["oneway"]["scheduleType"] = $scheduleTypeVal;

				$initialTripObject["oneway"]["flexibleRideDate"] = $flexibleRideDate;
				$initialTripObject["oneway"]["futureRideDate"] = $futureRideDate;
				$initialTripObject["oneway"]["futureRideTime"] = $futureRideTime;


				if (!empty($tripResponceObj['note_for_driver'] ?? "")) {
					$initialTripObject["oneway"]["messageToDriver"] = $guestDetails['note_for_driver'];
				}

			} else if ($initialTripObject["tripType"] == "roundtrip") {
			}
		}
	}

	return $initialTripObject;
}

if (isset($_REQUEST['action']) && $_REQUEST['action'] == "estimates") {
	$response = array();

	try {

		if (empty($_REQUEST['path'] ?? '') || empty($_REQUEST['path'] ?? '')) {
			throw new \Exception("Wrong paramater missing.");
		}

		if (($_REQUEST['path'] == "oneway" && empty($_REQUEST['oneway'])) || ($_REQUEST['path'] == "roundtrip.first_leg" && empty($_REQUEST['roundtrip']['first_leg'])) || ($_REQUEST['path'] == "roundtrip.return_leg" && empty($_REQUEST['roundtrip']['return_leg'])) ) {
			throw new \Exception("Wrong paramater missing.");
		}

		$tripDetails = array();
		$timestampInMilliseconds = "";

		if ($_REQUEST['path'] == "oneway") {
			$tripDetails = $_REQUEST['oneway'] ?? array();
		} else if ($_REQUEST['path'] == "roundtrip.first_leg") {
			$tripDetails = $_REQUEST['roundtrip']['first_leg'] ?? array();
		} else if ($_REQUEST['path'] == "roundtrip.return_leg") {
			$tripDetails = $_REQUEST['roundtrip']['return_leg'] ?? array();
		}

		if (empty($tripDetails)) {
			throw new \Exception("Empty trip details");
		}

		if (empty($tripDetails['pickup_location_lat'] ?? '') || empty($tripDetails['pickup_location_lng'] ?? '')) {
			throw new \Exception("Pickup location not valid.");
		}

		if (empty($tripDetails['dropoff_location_lat'] ?? '') || empty($tripDetails['dropoff_location_lng'] ?? '')) {
			throw new \Exception("Dropoff location not valid.");
		}

		if (empty($tripDetails['when_to_ride'] ?? '') || empty($tripDetails['when_to_ride'] ?? '')) {
			throw new \Exception("When to ride details not valid.");
		}

		if ($tripDetails['when_to_ride'] == "futuretrip") {
			if ($tripDetails['schedule_type'] == "flexible" && empty($tripDetails['flexible_ride_date'] ?? '')) {
				throw new \Exception("Flexible ride not valid valid.");
			} else if ($tripDetails['schedule_type'] == "schedule" && (empty($tripDetails['future_ride_date'] ?? '') || empty($tripDetails['future_ride_time'] ?? ''))) {
				throw new \Exception("Future ride details not valid.");
			}
		}

		// Create uber controller
		$ubController = new UberController();

		$estimedPayload = array(
			"pickup" => array(
				"latitude" => (float) $tripDetails['pickup_location_lat'] ?? '',
        		"longitude" => (float) $tripDetails['pickup_location_lng'] ?? ''
			),
			"dropoff" => array(
				"latitude" => (float) $tripDetails['dropoff_location_lat'] ?? '',
        		"longitude" => (float) $tripDetails['dropoff_location_lng'] ?? ''
			)
		);

		if ($tripDetails['when_to_ride'] == "futuretrip") {

			if ($tripDetails['schedule_type'] == "schedule" && $tripDetails['future_ride_date'] != "" && $tripDetails['future_ride_time'] != "") {
				$timestampInMilliseconds = getTimeStampsec($tripDetails['future_ride_date'] ." ". $tripDetails['future_ride_time']);

				$estimedPayload['scheduling'] = array(
					"pickup_time" => $timestampInMilliseconds ?? ''
				);
			} else if ($tripDetails['schedule_type'] == "flexible" && $tripDetails['flexible_ride_date'] != "") {
				$estimedPayload['deferred_ride_options'] = array(
					"pickup_day" => date("Y-m-d", strtotime($tripDetails['flexible_ride_date'])) ?? ''
				);
			}
		}

		// Get product list
		$estimedTimeList = $ubController->getEstimedTime($estimedPayload);

		if (empty($estimedTimeList)) {
			throw new \Exception("Unable to fetch estimates.");
		}

		if (isset($estimedTimeList['fares_unavailable']) && $estimedTimeList['fares_unavailable'] === true) {
			throw new \Exception("Unable to fetch fares details.");
		}

		$productEstimates = array();

		foreach ($estimedTimeList['product_estimates'] as $productEstimate) {
			if (!empty($productEstimate)) {
				if (!isset($productEstimate['estimate_info']) || !isset($productEstimate['product'])) {
					continue;
				}

				if (empty($productEstimate['estimate_info']['fare_id']) || (empty($productEstimate['estimate_info']['fare']) && empty($productEstimate['estimate_info']['estimate']) ) ) {
					continue;
				}

				if (isset($productEstimate['estimate_info']['no_cars_available']) && $productEstimate['estimate_info']['no_cars_available'] === true) {
					continue;
				}

				$optionTitle = "";
				$optionTitle1 = "";
				$optionTitle2 = "";

				if (isset($productEstimate['product']['display_name'])) {
					//$optionTitle = "<span class='productname'>" . $productEstimate['product']['display_name'] . "</span>";
					$optionTitle = $productEstimate['product']['display_name'] . " - ";
				}

				if (isset($productEstimate['estimate_info']['pickup_estimate']) && isset($productEstimate['estimate_info']['pickup_estimate'])) {
					//$optionTitle1 = "In " . $productEstimate['estimate_info']['pickup_estimate'] . " mins";
					$optionTitle .= "In " . $productEstimate['estimate_info']['pickup_estimate'] . " mins";
				}

				if (isset($productEstimate['estimate_info']['trip']) && isset($productEstimate['estimate_info']['trip']['duration_estimate']) && !empty($productEstimate['estimate_info']['trip']['duration_estimate'])) {
					//$optionTitle1 .= " • Estimated drop-off: " . convertSecondsToMMSS($productEstimate['estimate_info']['trip']['duration_estimate']);
					$optionTitle .= " • Estimated drop-off: " . convertSecondsToMMSS($productEstimate['estimate_info']['trip']['duration_estimate']);
				}

				if (!empty($optionTitle1)) $optionTitle1 = "<span class='estimate_info'>" . $optionTitle1 . "</span>";

				if (isset($productEstimate['estimate_info']['fare']) && isset($productEstimate['estimate_info']['fare']['display'])) {
					//$optionTitle2 = "<span class='estimated_fare'>" . $productEstimate['estimate_info']['fare']['currency_code'] . " " . $productEstimate['estimate_info']['fare']['display'] . "</span>";

					$optionTitle .= " • Estimated fare: " . $productEstimate['estimate_info']['fare']['currency_code'] . " " . $productEstimate['estimate_info']['fare']['display'];
				}

				//$optionTitle = "<div class='sel2-vehicle-container'><div><div>" . $optionTitle . "</div><div>" . $optionTitle1 . "</div></div><div>" .$optionTitle2. "</div></div>";

				$productEstimates[] = array(
					"name" => $optionTitle,
					"id" => $productEstimate['estimate_info']['fare_id'] . "~" . $productEstimate['product']['product_id']
				);
			}
		}

		$response = $productEstimates;

	} catch (\Throwable $e) {
		http_response_code(400); // Bad Request

		$response = array(
			"error" => 1,
			"message" => $e->getMessage()
		);
	}
	
	echo json_encode($response);
	exit();
} else if (isset($_REQUEST['action']) && ($_REQUEST['action'] == "create_trip" || $_REQUEST['action'] == "update_trip")) {
	$response = array();

	try {

		$patientPid = $form_pid ?? 0;

		if (empty($_REQUEST['rider_first_name'] ?? '') || empty($_REQUEST['rider_first_name'] ?? '')) {
			throw new \Exception("Rider name is required");
		}

		if (empty($_REQUEST['trip_type'] ?? '') || empty($_REQUEST['trip_type'] ?? '')) {
			throw new \Exception("Please select trip type.");
		}

		if ($_REQUEST['trip_type'] == "oneway" && isset($_REQUEST['oneway'])) {
			$tripTypeDetails = array(
				"oneway" => array(
					"name" => "Oneway",
					"items" => $_REQUEST['oneway']
				)
			);
		} else if ($_REQUEST['trip_type'] == "roundtrip" && isset($_REQUEST['roundtrip'])) {
			$tripTypeDetails = array();

			if (isset($_REQUEST['roundtrip']['first_leg'])) {
				$tripTypeDetails["first_leg"] = array(
					"name" => "First Leg",
					"items" => $_REQUEST['roundtrip']['first_leg']
				);
			} 
			if (isset($_REQUEST['roundtrip']['return_leg'])) {
				$tripTypeDetails["return_leg"] = array(
					"name" => "Return Leg",
					"items" => $_REQUEST['roundtrip']['return_leg']
				);
			}
		}

		if (empty($tripTypeDetails)) {
			throw new \Exception("Empty trip details");
		}

		foreach ($tripTypeDetails as $tripTypeDetailItem) {
			if (isset($tripTypeDetailItem["name"]) && $tripTypeDetailItem["items"]) {
				$tripDetails = $tripTypeDetailItem["items"];
				$typeFieldName = $tripTypeDetailItem["name"];

				if (empty($tripDetails['pickup_location_name'] ?? '') || empty($tripDetails['pickup_location_lat'] ?? '') || empty($tripDetails['pickup_location_lng'] ?? '')) {
					throw new \Exception($typeFieldName . " - Pickup location not valid.");
				}

				if (empty($tripDetails['dropoff_location_name'] ?? '') || empty($tripDetails['dropoff_location_lat'] ?? '') || empty($tripDetails['dropoff_location_lng'] ?? '')) {
					throw new \Exception($typeFieldName . " - Dropoff location not valid.");
				}

				if (empty($tripDetails['when_to_ride'] ?? '') || empty($tripDetails['when_to_ride'] ?? '')) {
					throw new \Exception($typeFieldName . " - When to ride details not valid.");
				}

				if (empty($tripDetails['vehicle_type'] ?? '')) {
					throw new \Exception($typeFieldName . " - Please select vehicle type.");
				}

				if ($tripDetails['when_to_ride'] == "futuretrip") {
					if ($tripDetails['schedule_type'] == "schedule" && (empty($tripDetails['future_ride_date'] ?? '') || empty($tripDetails['future_ride_time'] ?? ''))) {
						throw new \Exception($typeFieldName . "- Future ride details not valid.");
					} else if ($tripDetails['schedule_type'] == "flexible" && (empty($tripDetails['flexible_ride_date'] ?? ''))) {
						throw new \Exception($typeFieldName . "- Future ride details not valid.");
					}
				}

			}
		}

		$cleanedPhoneNumber = preg_replace('/\D/', '', $_REQUEST['rider_phone_number'] ?? '');
		if (empty($cleanedPhoneNumber)) {
			throw new \Exception("Rider phone number is required");
		}

		// Set "sender" phone number
		//$contactsNofity = preg_replace('/[^0-9]/', '', Smslib::getDefaultFromNo());

		$tripPayload = array(
			"guest" => array(
				"first_name" => $_REQUEST['rider_first_name'] ?? '',
				"last_name" => $_REQUEST['rider_last_name'] ?? '',
				"phone_number" => "+" . $cleanedPhoneNumber
			)
		);

		if ($_REQUEST['trip_type'] == "oneway") {
			$onewayTripDetails = isset($tripTypeDetails['oneway']['items']) ? $tripTypeDetails['oneway']['items'] : array();

			$vehicleType = explode("~", $onewayTripDetails['vehicle_type']);
			if (count($vehicleType) !== 2) {
				throw new \Exception("Oneway - Selected Vehicle type value not valid");
			}

			$fareId = $vehicleType[0] ?? "";
			$productId = $vehicleType[1] ?? "";

			$tripPayload["product_id"] = $productId;
			$tripPayload["fare_id"] = $fareId;

			$tripPayload["pickup"] = array(
				"latitude" => (float) $onewayTripDetails['pickup_location_lat'] ?? '',
				"longitude" => (float) $onewayTripDetails['pickup_location_lng'] ?? '',
				"address" => $onewayTripDetails['pickup_location_name'] ?? ''
			);
			$tripPayload["dropoff"] = array(
				"latitude" => (float) $onewayTripDetails['dropoff_location_lat'] ?? '',
				"longitude" => (float) $onewayTripDetails['dropoff_location_lng'] ?? '',
				"address" => $onewayTripDetails['dropoff_location_name'] ?? ''
			);

			if (!empty($onewayTripDetails['message_to_driver'] ?? '')) {
				$tripPayload["note_for_driver"] = $onewayTripDetails['message_to_driver'] ?? '';
			}

			if ($onewayTripDetails['when_to_ride'] == "futuretrip") {
				$timestampInMilliseconds = "";

				if ($onewayTripDetails['schedule_type'] == "schedule" && $onewayTripDetails['future_ride_date'] != "" && $onewayTripDetails['future_ride_time'] != "") {
					$timestampInMilliseconds = getTimeStampsec($onewayTripDetails['future_ride_date'] ." ". $onewayTripDetails['future_ride_time']);

					if (!empty($timestampInMilliseconds)) {
						$tripPayload['scheduling'] = array(
							"pickup_time" => $timestampInMilliseconds ?? ''
						);
					}
				} else if ($onewayTripDetails['schedule_type'] == "flexible" && $onewayTripDetails['flexible_ride_date'] != "") {
					$tripPayload['deferred_ride_options'] = array(
						"pickup_day" => date("Y-m-d", strtotime($onewayTripDetails['flexible_ride_date'])) ?? ''
					);
				}
			}
		} else if ($_REQUEST['trip_type'] == "roundtrip") {

			$firstlegTripDetails = isset($tripTypeDetails['first_leg']['items']) ? $tripTypeDetails['first_leg']['items'] : array();
			$returnlegTripDetails = isset($tripTypeDetails['return_leg']['items']) ? $tripTypeDetails['return_leg']['items'] : array();

			$flvehicleType = explode("~", $firstlegTripDetails['vehicle_type']);
			if (count($flvehicleType) !== 2) {
				throw new \Exception("First leg - Selected Vehicle type value not valid");
			}

			$flfareId = $flvehicleType[0] ?? "";
			$flproductId = $flvehicleType[1] ?? "";

			$tripPayload["product_id"] = $flproductId;
			$tripPayload["fare_id"] = $flfareId;

			$tripPayload["pickup"] = array(
				"latitude" => (float) $firstlegTripDetails['pickup_location_lat'] ?? '',
				"longitude" => (float) $firstlegTripDetails['pickup_location_lng'] ?? '',
				"address" => $firstlegTripDetails['pickup_location_name'] ?? ''
			);
			$tripPayload["dropoff"] = array(
				"latitude" => (float) $firstlegTripDetails['dropoff_location_lat'] ?? '',
				"longitude" => (float) $firstlegTripDetails['dropoff_location_lng'] ?? '',
				"address" => $firstlegTripDetails['dropoff_location_name'] ?? ''
			);

			if (!empty($firstlegTripDetails['message_to_driver'] ?? '')) {
				$tripPayload["note_for_driver"] = $firstlegTripDetails['message_to_driver'] ?? '';
			}

			if ($firstlegTripDetails['when_to_ride'] == "futuretrip") {
				$fltimestampInMilliseconds = "";
				
				if ($firstlegTripDetails['schedule_type'] == "schedule" && $firstlegTripDetails['future_ride_date'] != "" && $firstlegTripDetails['future_ride_time'] != "") {
					$fltimestampInMilliseconds = getTimeStampsec($firstlegTripDetails['future_ride_date'] ." ". $firstlegTripDetails['future_ride_time']);

					if (!empty($fltimestampInMilliseconds)) {
						$tripPayload['scheduling'] = array(
							"pickup_time" => $fltimestampInMilliseconds ?? ''
						);
					}
				} else if ($firstlegTripDetails['schedule_type'] == "flexible" && $firstlegTripDetails['flexible_ride_date'] != "") {
					$tripPayload['deferred_ride_options'] = array(
						"pickup_day" => date("Y-m-d", strtotime($firstlegTripDetails['flexible_ride_date'])) ?? ''
					);
				}
			}

			if (!empty($returnlegTripDetails)) {
				$rlvehicleType = explode("~", $returnlegTripDetails['vehicle_type']);
				if (count($rlvehicleType) !== 2) {
					throw new \Exception("First leg - Selected Vehicle type value not valid");
				}

				$rlfareId = $rlvehicleType[0] ?? "";
				$rlproductId = $rlvehicleType[1] ?? "";

				$returnLegTripPayload = array(
					"product_id" => $rlproductId,
					"fare_id" => $rlfareId,
					"start_location" => array(
						"latitude" => (float) $returnlegTripDetails['pickup_location_lat'] ?? '',
						"longitude" => (float) $returnlegTripDetails['pickup_location_lng'] ?? '',
						"address" => $returnlegTripDetails['pickup_location_name'] ?? ''
					),
					"end_location" => array(
						"latitude" => (float) $returnlegTripDetails['dropoff_location_lat'] ?? '',
						"longitude" => (float) $returnlegTripDetails['dropoff_location_lng'] ?? '',
						"address" => $returnlegTripDetails['dropoff_location_name'] ?? ''
					)
				);

				if (!empty($returnlegTripDetails['message_to_driver'] ?? '')) {
					$returnLegTripPayload["note_for_driver"] = $returnlegTripDetails['message_to_driver'] ?? '';
				}

				if ($returnlegTripDetails['when_to_ride'] == "futuretrip") {
					$rltimestampInMilliseconds = "";
					
					if ($returnlegTripDetails['schedule_type'] == "schedule" && $returnlegTripDetails['future_ride_date'] != "" && $returnlegTripDetails['future_ride_time'] != "") {
						$rltimestampInMilliseconds = getTimeStampsec($returnlegTripDetails['future_ride_date'] ." ". $returnlegTripDetails['future_ride_time']);

						if (!empty($rltimestampInMilliseconds)) {
							$returnLegTripPayload['scheduling'] = array(
								"pickup_time" => $rltimestampInMilliseconds ?? ''
							);
						}
					} else if ($returnlegTripDetails['schedule_type'] == "flexible" && $returnlegTripDetails['flexible_ride_date'] != "") {
						$returnLegTripPayload['deferred_ride_options'] = array(
							"pickup_day" => date("Y-m-d", strtotime($returnlegTripDetails['flexible_ride_date'])) ?? ''
						);
					}
				}

				$tripPayload["return_trip_params"] = $returnLegTripPayload;
			}
		}

		// Create uber controller
		$ubController = new UberController();

		if ($_REQUEST['action'] == "create_trip") {
			// Create Health trip
			$createResponce = $ubController->createHealthTrip($tripPayload);

			if (empty($createResponce)) {
				throw new \Exception("Unable to create trip something went wrong.");
			}

			if (!isset($createResponce['request_id']) || empty($createResponce['request_id'])) {
				throw new \Exception("Unable to create trip something went wrong 'request_id' missing.");
			}

			if (!empty($createResponce['request_id'])) {
				// Created trip log
				$ubController->insertTripHistroyLog($createResponce['request_id'], "created-trip-request", json_encode($tripPayload));
				$ubController->insertTripHistroyLog($createResponce['request_id'], "created-trip-responce", json_encode($createResponce));

				$fullTripDetailsFirstLeg = array();
				$fullTripDetailsReturnLeg = array();

				try {
					$flTripdetails = $ubController->getHealthTripDetails($createResponce['request_id']);
					if (!empty($flTripdetails) && !empty($flTripdetails['request_id'])) {
						$fullTripDetailsFirstLeg = $flTripdetails;
					}

					if (!empty($createResponce['linked_trip_details'])) {
						$rlTripdetails = $ubController->getHealthTripDetails($createResponce['linked_trip_details']);
						if (!empty($rlTripdetails) && !empty($rlTripdetails['request_id'])) {
							$fullTripDetailsReturnLeg = $rlTripdetails;
						}
					}
				} catch (\Throwable $e) {
				}

				if ($_REQUEST['trip_type'] == "oneway" || $_REQUEST['trip_type'] == "roundtrip") {
					// Prepare data for log
					$flPreparedDataForLog = $ubController->prepareDataForLog($fullTripDetailsFirstLeg, $createResponce);

					$tripLog = sqlInsert("INSERT INTO `vh_uber_health_trips` ( 
						request_id, 
						pid,
						user_id,
						eid,
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
					) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?) ", array(
						$flPreparedDataForLog['request_id'] ?? $createResponce['request_id'],
						$patientPid, 
						(int)($_SESSION['authUserID'] ?? 0),
						!empty($form_eid) ? $form_eid : NULL,
						$flPreparedDataForLog['rider_first_name'] ?? NULL,
						$flPreparedDataForLog['rider_last_name'] ?? NULL,
						$flPreparedDataForLog['rider_phone_number'] ?? NULL,
						$flPreparedDataForLog["pickup_lat"] ?? 0,
						$flPreparedDataForLog["pickup_lng"] ?? 0,
						$flPreparedDataForLog["pickup_address"] ?? NULL,
						$flPreparedDataForLog["dropoff_lat"] ?? 0,
						$flPreparedDataForLog["dropoff_lng"] ?? 0,
						$flPreparedDataForLog["dropoff_address"] ?? NULL,
						$flPreparedDataForLog["trip_type"] ?? NULL,
						$flPreparedDataForLog["trip_leg_number"] ?? 0,
						$flPreparedDataForLog["trip_status"] ?? NULL,
						$flPreparedDataForLog["linked_request_id"] ?? NULL,
						$flPreparedDataForLog["trip_schedule_date"] ?? NULL,
						$flPreparedDataForLog["trip_schedule_time"] ?? NULL,
						!empty($tripPayload) ? json_encode($tripPayload) : NULL,
						$flPreparedDataForLog["trip_response"] ?? NULL,
					));

					// Responce trip log
					$ubController->insertTripHistroyLog($createResponce['request_id'], "status-change-" . $flPreparedDataForLog["trip_status"], json_encode($fullTripDetailsFirstLeg));
				}

				if (!empty($createResponce['linked_request_id'] ?? "") && $_REQUEST['trip_type'] == "roundtrip") {
					// Prepare data for log
					$rlPreparedDataForLog = $ubController->prepareDataForLog($fullTripDetailsReturnLeg, $createResponce);

					$tripLog = sqlInsert("INSERT INTO `vh_uber_health_trips` ( 
						request_id, 
						pid,
						user_id,
						eid,
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
					) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?) ", array(
						$rlPreparedDataForLog['request_id'] ?? $createResponce['linked_request_id'],
						$patientPid, 
						(int)($_SESSION['authUserID'] ?? 0),
						!empty($form_eid) ? $form_eid : NULL,
						$rlPreparedDataForLog['rider_first_name'] ?? NULL,
						$rlPreparedDataForLog['rider_last_name'] ?? NULL,
						$rlPreparedDataForLog['rider_phone_number'] ?? NULL,
						$rlPreparedDataForLog["pickup_lat"] ?? 0,
						$rlPreparedDataForLog["pickup_lng"] ?? 0,
						$rlPreparedDataForLog["pickup_address"] ?? NULL,
						$rlPreparedDataForLog["dropoff_lat"] ?? 0,
						$rlPreparedDataForLog["dropoff_lng"] ?? 0,
						$rlPreparedDataForLog["dropoff_address"] ?? NULL,
						$rlPreparedDataForLog["trip_type"] ?? NULL,
						$rlPreparedDataForLog["trip_leg_number"] ?? 0,
						$rlPreparedDataForLog["trip_status"] ?? NULL,
						$rlPreparedDataForLog["linked_request_id"] ?? NULL,
						$rlPreparedDataForLog["trip_schedule_date"] ?? NULL,
						$rlPreparedDataForLog["trip_schedule_time"] ?? NULL,
						!empty($tripPayload) ? json_encode($tripPayload) : NULL,
						$rlPreparedDataForLog["trip_response"] ?? NULL,
					));

					// Responce trip log
					$ubController->insertTripHistroyLog($createResponce['request_id'], "status-change-" . $rlPreparedDataForLog["trip_status"], json_encode($rlPreparedDataForLog));
				}

				if ($tripLog) {
					$response = array(
						"data" => $createResponce,
						"message" => "Trip Request Id: " . $createResponce['request_id'] . "\nTrip Status: " . $createResponce['status']
					);
				}
			}
		}

	} catch (\Throwable $e) {
		http_response_code(400); // Bad Request

		$response = array(
			"error" => 1,
			"message" => $e->getMessage(),
			"code" => $e->getCode()
		);
	}

	echo json_encode($response);
	exit();
} else if (isset($_REQUEST['action']) && $_REQUEST['action'] == "geocode") {
	$response = array();

	try {

		if (empty($_REQUEST['formatted_address'] ?? '')) {
			throw new \Exception("'formatted_address' required");
		}

		if (empty($_REQUEST['lat'] ?? '') || empty($_REQUEST['lng'] ?? '')) {
			throw new \Exception("Invalid 'lat' or 'lng' values.");
		}

		$isLocationExists = sqlQuery("SELECT count(id) as total_count FROM `vh_addresses_geocode` WHERE formatted_address = ? LIMIT 1", array($_REQUEST['formatted_address'] ?? ''));

		if (!empty($isLocationExists) && $isLocationExists['total_count'] == 0) {
			$in_sql = sqlInsert(
				"INSERT INTO `vh_addresses_geocode` ( formatted_address, lat, lng ) VALUES (?, ?, ?) ", 
				array(
					$_REQUEST['formatted_address'] ?? '',
					$_REQUEST['lat'] ?? '',
					$_REQUEST['lng'] ?? ''
				)
			);

			if (!empty($in_sql)) {
				$response['data'] = $in_sql;
			}
		}
	} catch (\Throwable $e) {
		http_response_code(400); // Bad Request

		$response = array(
			"error" => 1,
			"message" => $e->getMessage()
		);
	}

	echo json_encode($response);
	exit();
} else if (isset($_REQUEST['action']) && $_REQUEST['action'] == "fetch_patient") {
	$response = array();

	try {

		if (empty($_REQUEST['form_pid'] ?? '')) {
			throw new \Exception("Pid is required");
		}

		// Get patient data
		$patientData = getPatientDetails($_REQUEST['form_pid']);
		
		if (empty($patientData)) {
			throw new \Exception("Not valid patient data.");
		}

		$response = $patientData;

	} catch (\Throwable $e) {
		http_response_code(400); // Bad Request

		$response = array(
			"error" => 1,
			"message" => $e->getMessage()
		);
	}

	echo json_encode($response);
	exit();
} else if (isset($_REQUEST['action']) && $_REQUEST['action'] == "fetch_facility") {
	$response = array();

	try {

		if (empty($_REQUEST['form_facility_id'] ?? '')) {
			throw new \Exception("Facility id is required");
		}

		// Get facility data
		$facilityData = getFacilityDetails($_REQUEST['form_facility_id']);
		
		if (empty($facilityData)) {
			throw new \Exception("Not valid facility data.");
		}

		$response = $facilityData;

	} catch (\Throwable $e) {
		http_response_code(400); // Bad Request

		$response = array(
			"error" => 1,
			"message" => $e->getMessage()
		);
	}

	echo json_encode($response);
	exit();
}

// Get Default date time param
$defaultDateParam = getDefaultDateTimePicker();
$todayDate = "";
if (!empty($defaultDateParam) && is_array($defaultDateParam) && isset($defaultDateParam['format']) && !empty($defaultDateParam['format'])) {
	$todayDate = date($defaultDateParam['format']);
}

if (!empty($form_default_date)) {
	$todayDate = date($defaultDateParam['format'], strtotime($form_default_date));
}

$currentTime = date("H:i");

// Prepare default data
$initialTripObject = prepareDefaultData($trip_request_id);

if (empty($trip_request_id)) {
	if (!empty($form_pid)) {
		// Get patient data
		$patientData = getPatientDetails($form_pid);

		if (!empty($patientData)) {
			$initialTripObject['riderFirstName'] = $patientData['firstname'] ?? "";
			$initialTripObject['riderLastName'] = $patientData['lastname'] ?? "";
			$initialTripObject['riderPhoneNumber'] = $patientData['phonenumber'] ?? "";

			// Default
			$defaultendLocationName = $patientData['location_name'] ?? "";
			$defaultendLocation = $patientData['location'] ?? array();

			if (!empty($defaultendLocationName)) {
				$initialTripObject['oneway']['startLocationName'] = $defaultendLocationName;
				$initialTripObject['roundtrip']['first_leg']['startLocationName'] = $defaultendLocationName;
				$initialTripObject['roundtrip']['return_leg']['endLocationName'] = $defaultendLocationName;
			}

			if (!empty($defaultendLocationName) && !empty($defaultendLocation)) {
				$initialTripObject['oneway']['startLocation'] = $defaultendLocation;
				$initialTripObject['roundtrip']['first_leg']['startLocation'] = $defaultendLocation;
				$initialTripObject['roundtrip']['return_leg']['endLocation'] = $defaultendLocation;
			}
		}
	}

	// Default Address load
	if (!empty($facility_id)) {
		$fData = getFacilityDetails($facility_id);

		if (!empty($fData)) {
			$defaultstartLocationName = $fData['location_name'] ?? "";
			$defaultstartLocation = $fData['location'] ?? array();

			if (!empty($defaultstartLocationName)) {
				$initialTripObject['oneway']['endLocationName'] = $defaultstartLocationName;
				$initialTripObject['roundtrip']['first_leg']['endLocationName'] = $defaultstartLocationName;
				$initialTripObject['roundtrip']['return_leg']['startLocationName'] = $defaultstartLocationName;
			}

			if (!empty($defaultstartLocationName) && !empty($defaultstartLocation)) {
				$initialTripObject['oneway']['endLocation'] = $defaultstartLocation;
				$initialTripObject['roundtrip']['first_leg']['endLocation'] = $defaultstartLocation;
				$initialTripObject['roundtrip']['return_leg']['startLocation'] = $defaultstartLocation;
			}
		}
	}
}

?>
<!DOCTYPE html>
<html>
<head>
	<meta charset="utf-8">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	
	<?php Header::setupHeader(['common', 'opener', 'knockout', 'datetime-picker', 'select2']); ?>

	<title><?php echo xlt('Uber'); ?></title>

	<script src="https://maps.googleapis.com/maps/api/js?key=?&libraries=places&v=beta" async defer></script>

	<script type="text/javascript">
		let map, directionsService, directionsRenderer, startAutocomplete, endAutocomplete, startMarker, endMarker, uberView;
		var defaultDataSet = {
			startLocationName : "",
			startLocation : null,
			endLocationName : "",
			endLocation : null
		};
		var initialTripObject = {};
		// Flag to control if we should skip notifying subscribers
		var skipNotification = false;

        const uberLightMapStyle = [
            {
                "elementType": "geometry",
                "stylers": [
                    {
                        "color": "#ffffff"  // Light background color
                    }
                ]
            },
            {
                "elementType": "labels.icon",
                "stylers": [
                    {
                        "visibility": "off"
                    }
                ]
            },
            {
                "elementType": "labels.text.fill",
                "stylers": [
                    {
                        "color": "#7c8dad"  // Darker text color
                    }
                ]
            },
            {
                "elementType": "labels.text.stroke",
                "stylers": [
                    {
                        "color": "#ffffff"  // White text stroke
                    }
                ]
            },
            {
                "featureType": "landscape",
                "elementType": "geometry",
                "stylers": [
                    {
                        "color": "#e6e9ec"  // Light land color
                    }
                ]
            },
            {
                "featureType": "road",
                "elementType": "geometry",
                "stylers": [
                    {
                    	"visibility": "simplified",
                        "color": "#ffffff"  // White roads for light mode
                    }
                ]
            },
            {
                "featureType": "road",
                "elementType": "labels.text.fill",
                "stylers": [
                    {
                        "color": "#5c5c5c"  // Dark gray labels
                    }
                ]
            },
            {
                "featureType": "road.highway",
                "elementType": "geometry",
                "stylers": [
                    {
                        "color": "#a6b5db"  // Bright color for highways
                    }
                ]
            },
            {
                "featureType": "road.highway",
                "elementType": "geometry.stroke",
                "stylers": [
                    {
                        "color": "#a6b5db"  // Stroke color for highways
                    }
                ]
            },
            {
                "featureType": "water",
                "elementType": "geometry",
                "stylers": [
                    {
                        "color": "#acd5f5"  // Light blue for water
                    }
                ]
            },
            {
                "featureType": "poi",
                "elementType": "geometry",
                "stylers": [
                    {
                        "color": "#e0e0e0"  // Light color for Points of Interest
                    }
                ]
            },
            {
                "featureType": "poi.park",
                "elementType": "geometry",
                "stylers": [
                    {
                        "color": "#a7dfb6"  // Park areas are greener
                    }
                ]
            },
        ];

        function generateAutoCompleteField(inputElement = null, path = '', type = '') {
        	// Autocomplete for Start Location
            let autocomplete = new google.maps.places.Autocomplete(inputElement);
            autocomplete.setFields(['place_id', 'geometry', 'name', 'formatted_address']);
            autocomplete.addListener('place_changed', function() {
                const place = autocomplete.getPlace();

                if (!place.geometry) {
                    console.log("Place details not available for input: " + place1.name);
                    return;
                }

                if (type == "start") {
	                // Set values
	                uberView.getFieldValue(path, false).startLocationName(place.name);
	        		uberView.getFieldValue(path, false).startLocation({ lat: place.geometry.location.lat(), lng: place.geometry.location.lng() });
        		} else {
        			// Set values
	                uberView.getFieldValue(path, false).endLocationName(place.name);
	        		uberView.getFieldValue(path, false).endLocation({ lat: place.geometry.location.lat(), lng: place.geometry.location.lng() });
        		}
            });

            return autocomplete;
        }

        function initEstimatedTrip() {
        	// For oneway
			if (uberView.tripType() == "oneway") {
				getTripsEstimates('oneway');
			} else if (uberView.tripType() == "roundtrip") {
				getTripsEstimates('roundtrip.first_leg');
				getTripsEstimates('roundtrip.return_leg');
			}
        }

        function initFutureSection() {
        	// Destroy the existing datetimepicker instance
      		$('.future_ride_date').datetimepicker('destroy');
        	$('.future_ride_date').datetimepicker(<?php echo json_encode($defaultDateParam) ?>);

        	// Destroy the existing datetimepicker instance
      		$('.future_ride_date').datetimepicker('destroy');
        	$('.future_ride_date').datetimepicker(<?php echo json_encode($defaultDateParam) ?>);

        	$('.future_ride_time').datetimepicker('destroy');
        	$('.future_ride_time').datetimepicker({
        		format: 'H:i',  // Time format (24-hour format)
		        datepicker: false,  // Disable the date picker
        		step: 5  // Set step interval for the time picker (e.g., 5-minute intervals)
        	});
        }

        function initAutoCompleteField() {
        	// Generate autocomplate
            if (uberView.tripType() == "oneway") {
            	generateAutoCompleteField(document.getElementById('oneway-start-input'), 'oneway', 'start');
            	generateAutoCompleteField(document.getElementById('oneway-end-input'), 'oneway', 'end');
        	} else if (uberView.tripType() == "roundtrip") {
            	generateAutoCompleteField(document.getElementById('roundtrip-firstleg-start-input'), 'roundtrip.first_leg', 'start');
            	generateAutoCompleteField(document.getElementById('roundtrip-firstleg-end-input'), 'roundtrip.first_leg', 'end');
            	generateAutoCompleteField(document.getElementById('roundtrip-returnleg-start-input'), 'roundtrip.return_leg', 'start');
            	generateAutoCompleteField(document.getElementById('roundtrip-returnleg-end-input'), 'roundtrip.return_leg', 'end');
        	}
        }

        // This script will initialize the Google Places Autocomplete API
        function initMap() {
        	uberView = new UberViewModel();
	    	ko.applyBindings(uberView);

	    	
	    	// Initialize the map
            map = new google.maps.Map(document.getElementById('map'), {
                zoom: 15,
                center: uberView.defaultLocation(),
                styles: uberLightMapStyle,
                mapTypeControl: false,  // Disable the Map/Satellite view button
                streetViewControl: false,  // Disable Street View control
                zoomControl: true,  // Enable zoom control (the plus and minus buttons)
                zoomControlOptions: {
                    position: google.maps.ControlPosition.RIGHT_BOTTOM  // Place the zoom buttons on the right center
                },
                gestureHandling: 'cooperative',
                mapTypeId: google.maps.MapTypeId.ROADMAP,
                disableDefaultUI: true,  // Disable all default UI elements (including camera controls)
                tilt: 0  // Set tilt to 0 (no tilt), keeping the map flat
            });

            // Initialize Directions Service and Renderer
            directionsService = new google.maps.DirectionsService();
            directionsRenderer = new google.maps.DirectionsRenderer({
                polylineOptions: {
                    strokeColor: 'black',  // Set the route polyline color to black
                    strokeOpacity: 1.0,
                    strokeWeight: 5
                },
                suppressMarkers: true
            });
            directionsRenderer.setMap(map);
            // END

            // Generate autocomplate
            initAutoCompleteField();

            uberView.oneway.startLocation.subscribe(updateRoute);
            uberView.oneway.startLocationName.subscribe(function() {
            	updateLocationName('oneway', 'start');
            });

            uberView.oneway.endLocation.subscribe(updateRoute);
            uberView.oneway.endLocationName.subscribe(function() {
            	updateLocationName('oneway', 'end');
            });

            uberView.oneway.startLocation.subscribe(function() { getTripsEstimates('oneway'); });
        	uberView.oneway.endLocation.subscribe(function() { getTripsEstimates('oneway'); });
        	uberView.oneway.whenToRide.subscribe(function() { getTripsEstimates('oneway'); });
        	uberView.oneway.scheduleType.subscribe(function() { getTripsEstimates('oneway'); });
        	uberView.oneway.flexibleRideDate.subscribe(function() { getTripsEstimates('oneway'); });
        	uberView.oneway.futureRideDate.subscribe(function() { getTripsEstimates('oneway'); });
        	uberView.oneway.futureRideTime.subscribe(function() { getTripsEstimates('oneway'); });

        	uberView.roundtrip.first_leg.startLocation.subscribe(function() {
            	uberView.roundtrip.return_leg.endLocationName(uberView.roundtrip.first_leg.startLocationName());
            	uberView.roundtrip.return_leg.endLocation(uberView.roundtrip.first_leg.startLocation());

        		getTripsEstimates('roundtrip.first_leg');
        	});
        	uberView.roundtrip.first_leg.endLocation.subscribe(function() {
        		uberView.roundtrip.return_leg.startLocationName(uberView.roundtrip.first_leg.endLocationName());
            	uberView.roundtrip.return_leg.startLocation(uberView.roundtrip.first_leg.endLocation());

        		getTripsEstimates('roundtrip.first_leg'); 
        	});
        	uberView.roundtrip.first_leg.whenToRide.subscribe(function() { getTripsEstimates('roundtrip.first_leg'); });
        	uberView.roundtrip.first_leg.scheduleType.subscribe(function() { getTripsEstimates('roundtrip.first_leg'); });
        	uberView.roundtrip.first_leg.flexibleRideDate.subscribe(function() { getTripsEstimates('roundtrip.first_leg'); });
        	uberView.roundtrip.first_leg.futureRideDate.subscribe(function() { getTripsEstimates('roundtrip.first_leg'); });
        	uberView.roundtrip.first_leg.futureRideTime.subscribe(function() { getTripsEstimates('roundtrip.first_leg'); });

        	uberView.roundtrip.return_leg.startLocation.subscribe(function() { getTripsEstimates('roundtrip.return_leg'); });
        	uberView.roundtrip.return_leg.endLocation.subscribe(function() { getTripsEstimates('roundtrip.return_leg'); });
        	uberView.roundtrip.return_leg.whenToRide.subscribe(function() { getTripsEstimates('roundtrip.return_leg'); });
        	uberView.roundtrip.return_leg.scheduleType.subscribe(function() { getTripsEstimates('roundtrip.return_leg'); });
        	uberView.roundtrip.return_leg.flexibleRideDate.subscribe(function() { getTripsEstimates('roundtrip.return_leg'); });
        	uberView.roundtrip.return_leg.futureRideDate.subscribe(function() { getTripsEstimates('roundtrip.return_leg'); });
        	uberView.roundtrip.return_leg.futureRideTime.subscribe(function() { getTripsEstimates('roundtrip.return_leg'); });

        	// Update return leg route
        	uberView.roundtrip.first_leg.startLocation.subscribe(updateRoute);
        	uberView.roundtrip.first_leg.endLocation.subscribe(updateRoute);
        	uberView.roundtrip.return_leg.startLocation.subscribe(updateRoute);
        	uberView.roundtrip.return_leg.endLocation.subscribe(updateRoute);
        	// End

        	// Check location name empty or not
            uberView.roundtrip.first_leg.startLocationName.subscribe(function() {
            	updateLocationName('roundtrip.first_leg', 'start');
            });

            uberView.roundtrip.first_leg.endLocationName.subscribe(function() {
            	updateLocationName('roundtrip.first_leg', 'end');
            });

            uberView.roundtrip.return_leg.startLocationName.subscribe(function() {
            	updateLocationName('roundtrip.return_leg', 'start');
            });
            
            uberView.roundtrip.return_leg.endLocationName.subscribe(function() {
            	updateLocationName('roundtrip.return_leg', 'end');
            });
            // End

        	// Estimated cost

        	// Subscribe
            uberView.tripType.subscribe(function() {
            	// Flag to control if we should skip notifying subscribers
            	skipNotification = true;

            	// Generate autocomplate
            	initAutoCompleteField();

            	clearMapRoute();
				// Reset Intial To Values
				uberView.resetToInitialValues();

				// Flag to control if we should skip notifying subscribers
				skipNotification = false;

				initEstimatedTrip();
				initFutureSection();
            });

        	uberView.isDefaultValueSet.subscribe(function() {
        		// Set default location for oneway
        		if (uberView.isDefaultValueSet() === 0) {
        			// Flag to control if we should skip notifying subscribers
            		skipNotification = true;

            		if (defaultDataSet.hasOwnProperty('startLocationName') && defaultDataSet['startLocationName'] != "") {
	        			initialTripObject['oneway']['startLocationName'] = defaultDataSet['startLocationName'];
	        			initialTripObject['roundtrip']['first_leg']['startLocationName'] = defaultDataSet['startLocationName'];
	        			initialTripObject['roundtrip']['return_leg']['endLocationName'] = defaultDataSet['startLocationName'];
	        		} 
	        		if (defaultDataSet.hasOwnProperty('startLocation') && defaultDataSet['startLocation'] != null) {
	        			initialTripObject['oneway']['startLocation'] = defaultDataSet['startLocation'];
	        			initialTripObject['roundtrip']['first_leg']['startLocation'] = defaultDataSet['startLocation'];
	        			initialTripObject['roundtrip']['return_leg']['endLocation'] = defaultDataSet['startLocation'];
	        		} 
	        		if (defaultDataSet.hasOwnProperty('endLocationName') && defaultDataSet['endLocationName'] != "") {
	        			initialTripObject['oneway']['endLocationName'] = defaultDataSet['endLocationName'];
	        			initialTripObject['roundtrip']['first_leg']['endLocationName'] = defaultDataSet['endLocationName'];
	        			initialTripObject['roundtrip']['return_leg']['startLocationName'] = defaultDataSet['endLocationName'];
	        		} 
	        		if (defaultDataSet.hasOwnProperty('endLocation') && defaultDataSet['endLocation'] != null) {
	        			initialTripObject['oneway']['endLocation'] = defaultDataSet['endLocation'];
	        			initialTripObject['roundtrip']['first_leg']['endLocation'] = defaultDataSet['endLocation'];
	        			initialTripObject['roundtrip']['return_leg']['startLocation'] = defaultDataSet['endLocation'];
	        		}

            		if (uberView.tripType() == "oneway") {
	        			if (defaultDataSet.hasOwnProperty('startLocationName') && defaultDataSet['startLocationName'] != "") {
	        				uberView.oneway.startLocationName(defaultDataSet['startLocationName']);
	        			}

	        			if (defaultDataSet.hasOwnProperty('startLocation') && defaultDataSet['startLocation'] != null) {
	        				uberView.oneway.startLocation(defaultDataSet['startLocation']);
	        			}

	        			if (defaultDataSet.hasOwnProperty('endLocationName') && defaultDataSet['endLocationName'] != "") {
	        				uberView.oneway.endLocationName(defaultDataSet['endLocationName']);
	        			}

	        			if (defaultDataSet.hasOwnProperty('endLocation') && defaultDataSet['endLocation'] != null) {
	        				uberView.oneway.endLocation(defaultDataSet['endLocation']);
	        			}
        			} else if (uberView.tripType() == "roundtrip") {
        				// Set location value for first_leg
        				if (defaultDataSet.hasOwnProperty('startLocationName') && defaultDataSet['startLocationName'] != "") {
	        				uberView.roundtrip.first_leg.startLocationName(defaultDataSet['startLocationName']);
	        			}

	        			if (defaultDataSet.hasOwnProperty('startLocation') && defaultDataSet['startLocation'] != null) {
	        				uberView.roundtrip.first_leg.startLocation(defaultDataSet['startLocation']);
	        			}

	        			if (defaultDataSet.hasOwnProperty('endLocationName') && defaultDataSet['endLocationName'] != "") {
	        				uberView.roundtrip.first_leg.endLocationName(defaultDataSet['endLocationName']);
	        			}

	        			if (defaultDataSet.hasOwnProperty('endLocation') && defaultDataSet['endLocation'] != null) {
	        				uberView.roundtrip.first_leg.endLocation(defaultDataSet['endLocation']);
	        			}

	        			// Set location value for return_leg
        				if (defaultDataSet.hasOwnProperty('startLocationName') && defaultDataSet['startLocationName'] != "") {
	        				uberView.roundtrip.return_leg.startLocationName(defaultDataSet['endLocationName']);
	        			}

	        			if (defaultDataSet.hasOwnProperty('startLocation') && defaultDataSet['startLocation'] != null) {
	        				uberView.roundtrip.return_leg.startLocation(defaultDataSet['endLocation']);
	        			}

	        			if (defaultDataSet.hasOwnProperty('endLocationName') && defaultDataSet['endLocationName'] != "") {
	        				uberView.roundtrip.return_leg.endLocationName(defaultDataSet['startLocationName']);
	        			}

	        			if (defaultDataSet.hasOwnProperty('endLocation') && defaultDataSet['endLocation'] != null) {
	        				uberView.roundtrip.return_leg.endLocation(defaultDataSet['startLocation']);
	        			}
        			}

        			// Flag to control if we should skip notifying subscribers
					skipNotification = false;

        			updateRoute();
					initEstimatedTrip();
					initFutureSection();

					uberView.setIsLoading(false);
        		}
        	});

        	// Set value status
        	uberView.isDefaultValueSet(uberView.isDefaultValueSet() + 1);
        	uberView.isDefaultValueSet(uberView.isDefaultValueSet() + 1);

        	const startFormattedAddress = '<?php echo $initialTripObject['oneway']['startLocationName']; ?>';
        	const endFormattedAddress = '<?php echo $initialTripObject['oneway']['endLocationName']; ?>';

        	// Load default values for pickup & dropoff address
        	<?php if (!empty($initialTripObject['oneway']['startLocationName']) && empty($initialTripObject['oneway']['startLocation'])) { ?>
        	  // Initialize the Geocoder
		      const geocoder = new google.maps.Geocoder();

		      // Perform geocode (convert address to Lat/Lng)
		      geocoder.geocode({ address: startFormattedAddress }, function(results, status) {
		        if (status === google.maps.GeocoderStatus.OK) {
		          	// Get the latitude and longitude from the geocode result
		          	const lat = results[0].geometry.location.lat();
		          	const lng = results[0].geometry.location.lng();

	        	  	defaultDataSet['startLocationName'] = startFormattedAddress;
	        	  	if (lat != "" && lng != "") {
	        	  		defaultDataSet['startLocation'] = { "lat": lat, "lng": lng };

	        	  		// Save geocode
	          			saveGeocode(startFormattedAddress, lat, lng);
	        		}
		        } else {
		          console.log("Geocode failed: " + status);
		        }

		        // Set value status
        	    uberView.isDefaultValueSet(uberView.isDefaultValueSet() - 1);
		      });
        	<?php } else { ?>
        		// Set value status
        	    uberView.isDefaultValueSet(uberView.isDefaultValueSet() - 1);
        	<?php } ?>

        	<?php if (!empty($initialTripObject['oneway']['endLocationName']) && empty($initialTripObject['oneway']['endLocation'])) { ?>
        	  // Initialize the Geocoder
		      const geocoder1 = new google.maps.Geocoder();

		      // Perform geocode (convert address to Lat/Lng)
		      geocoder1.geocode({ address: endFormattedAddress }, function(results, status) {
		        if (status === google.maps.GeocoderStatus.OK) {
		          	// Get the latitude and longitude from the geocode result
		          	const lat = results[0].geometry.location.lat();
		          	const lng = results[0].geometry.location.lng();

	        	  	defaultDataSet['endLocationName'] = endFormattedAddress;
	        	  	if (lat != "" && lng != "") {
	        	  		defaultDataSet['endLocation'] = { "lat": lat, "lng": lng };

	        	  		// Save geocode
	          			saveGeocode(endFormattedAddress, lat, lng);
	        		}
		        } else {
		          console.log("Geocode failed: " + status);
		        }

		        // Set value status
        	    uberView.isDefaultValueSet(uberView.isDefaultValueSet() - 1);
		      });
        	<?php } else { ?>
        		// Set value status
        	    uberView.isDefaultValueSet(uberView.isDefaultValueSet() - 1);
        	<?php } ?>
        	// END

        	//updateRoute();
        	//getTripsEstimates();
        }

        // Clear marker
        function clearMarker() {
        	// Clear Start Marker
            if (startMarker) {
                startMarker.setMap(null); // Remove the old end marker if exists
            }

            // Clear End Marker
            if (endMarker) {
                endMarker.setMap(null); // Remove the old end marker if exists
            }
        }

        function clearMapRoute() {
        	// Clear Marker on map
        	clearMarker();

        	if (directionsRenderer) {
        		// Clear directions and hide any existing route
				directionsRenderer.setDirections({ routes: [] });
			}

            if (map) {
            	// If geolocation fails, center map to (0, 0)
	        	map.setCenter(uberView.defaultLocation());
	       		map.setZoom(11);  // Show the whole world
	        }
        }

        function updateRoute() {
        	let startCoords = null;
        	let endCoords = null;

        	if (uberView.tripType() == "oneway") {
	        	startCoords = uberView.oneway.startLocation();
	        	endCoords = uberView.oneway.endLocation();
	        } else if (uberView.tripType() == "roundtrip") {
        		startCoords = uberView.roundtrip.first_leg.startLocation();
	        	endCoords = uberView.roundtrip.first_leg.endLocation();
        	}

        	if (endCoords != null && startCoords != null) {
	        	// Request directions using latitudes and longitudes
	            const request = {
	                origin: { lat: startCoords.lat, lng: startCoords.lng },
	                destination: { lat: endCoords.lat, lng: endCoords.lng },
	                travelMode: google.maps.TravelMode.DRIVING
	            };

	            directionsService.route(request, function(result, status) {
	                if (status === google.maps.DirectionsStatus.OK) {

	                    // Clear Marker
		     			clearMarker();

	                    // Set Direction
	                    directionsRenderer.setDirections(result);

                        // Place Start Marker
                        startMarker = placeMarker('START', result.routes[0].legs[0].start_location, uberView.oneway.startLocationName());

                        // Place End Marker
                        endMarker = placeMarker('END', result.routes[0].legs[0].end_location, uberView.oneway.endLocationName());

                        // Zoom to fit the route bounds
	                    const route = result.routes[0];

	                } else {
	                    alert('Directions request failed due to ' + status);
	                }
	            });
        	} else if (endCoords == null && startCoords != null){
        		// Set the map to the starting location only
		      	map.setCenter(startCoords);
		     	map.setZoom(14); // Reset zoom if necessary

		     	// Clear Marker
		     	clearMarker();

		      	// Clear directions and hide any existing route
		      	directionsRenderer.setDirections({ routes: [] });

		        // Place Start Marker
                startMarker = placeMarker('START', startCoords, uberView.oneway.startLocationName());
        	} else if (startCoords == null && endCoords != null){
        		// Set the map to the starting location only
		      	map.setCenter(endCoords);
		     	map.setZoom(14); // Reset zoom if necessary

		     	// Clear Marker
		     	clearMarker();

		      	// Clear directions and hide any existing route
		      	directionsRenderer.setDirections({ routes: [] });

		      	// Place End Marker
                endMarker = placeMarker('END', endCoords, uberView.oneway.endLocationName());
        	} else {
        		clearMapRoute();
        	}
        }

        // Place marker
        function placeMarker(type, location, title = "") {
        	// Add custom start and end markers
            const startIcon = {
              url: 'http://maps.google.com/mapfiles/ms/icons/blue-dot.png', // Custom icon for the start
              scaledSize: new google.maps.Size(40, 40),
            };

        	const marker = new google.maps.Marker({
                position: location,
                map: map,
                title: title,
                icon: startIcon,
            });

            return marker;
        }

        function updateLocationName(path = '', type = 'start') {
        	if (type == 'start' && uberView.getFieldValue(path, false).startLocationName() == "") {
        		uberView.getFieldValue(path, false).startLocation(null);
        	} else if (type == 'end' && uberView.getFieldValue(path, false).endLocationName() == "") {
        		uberView.getFieldValue(path, false).endLocation(null);
        	}

        	if (uberView.getFieldValue(path, false).startLocation() === null || uberView.getFieldValue(path, false).endLocation() === null) {
        		uberView.getFieldValue(path, false).vehicleTypeOptions(null);
        		uberView.getFieldValue(path, false).vehicleType("");
        	}
        }

        function clearLocationName(path = '', type = 'start') {
        	if (type == 'start') {
        		uberView.getFieldValue(path, false).startLocationName("");
        	} else if (type == 'end') {
        		uberView.getFieldValue(path, false).endLocationName("");
        	}
        }

        function updateWhenToTrip(type = '', path = '') {
        	if (type != "") {
        		// Flag to control if we should skip notifying subscribers
            	skipNotification = true;
            	uberView.getFieldValue(path, false).flexibleRideDate(uberView.defaultTodayDate);
        		uberView.getFieldValue(path, false).futureRideDate(uberView.defaultTodayDate);
        		uberView.getFieldValue(path, false).futureRideTime(uberView.defaultCurrentTime);
        		uberView.getFieldValue(path, false).whenToRide(type);

        		// Flag to control if we should skip notifying subscribers
            	skipNotification = false;

            	// For oneway
				getTripsEstimates(path);
        		
        	} 

        	initFutureSection();
        }

        function updateScheduleTypeTrip(path = '', type = '') {
			if (type != "") {
        		// Flag to control if we should skip notifying subscribers
            	skipNotification = true;
            	uberView.getFieldValue(path, false).flexibleRideDate(uberView.defaultTodayDate);
        		uberView.getFieldValue(path, false).futureRideDate(uberView.defaultTodayDate);
        		uberView.getFieldValue(path, false).futureRideTime(uberView.defaultCurrentTime);
        		uberView.getFieldValue(path, false).scheduleType(type);

        		// Flag to control if we should skip notifying subscribers
            	skipNotification = false;

            	// For oneway
				getTripsEstimates(path);
        		
        	}

        	initFutureSection();

			return true;
	    }

        function getTripsEstimates(path = '', callbackfun = null) {

        	if (skipNotification === true) {
        		return false;
        	}

        	setTimeout(function() {

        	var formData = $("#uber_trip").serialize(); // Serialize the form data

        	if (uberView.getFieldValue(path, false).whenToRide() == "" || uberView.getLocationLat(path + '.startLocation')() == "" || uberView.getLocationLng(path + '.startLocation')() == "" ||  uberView.getLocationLat(path + '.endLocation')() == "" || uberView.getLocationLng(path + '.endLocation')() == "") {
        		return false;
        	}

        	if (uberView.getFieldValue(path, false).whenToRide() == "futuretrip") {
	        	if (uberView.getFieldValue(path, false).scheduleType() == "schedule" && (uberView.getFieldValue(path, false).futureRideDate() == "" || uberView.getFieldValue(path, false).futureRideTime() == "")) {
	        		return false;
	        	} else if (uberView.getFieldValue(path, false).scheduleType() == "flexible" && uberView.getFieldValue(path, false).flexibleRideDate() == "") {
	        		return false;
	        	}
        	}

        	// Set is loading true
        	uberView.setIsLoading(true);

            // Send the AJAX POST request
            $.ajax({
                url: 'uber_estimatetime.php?action=estimates&path=' + path, // The URL where you want to send the request
                type: 'POST',
                data: formData,  // The form data
                success: function (response) {
                	try {
	                	if (response != '') {
	                		let responseJson = JSON.parse(response);
	                		if (Array.isArray(responseJson)) {
	                			//uberView.vehicleTypeOptions(responseJson);
	                			uberView.getFieldValue(path, false).vehicleTypeOptions(responseJson);
	                		} else {
	                			// Clear Vehicle Type Options
	                			//uberView.vehicleTypeOptions([]);
	                			uberView.getFieldValue(path, false).vehicleTypeOptions(null);
	                		}
	                	}
	                } catch (e) {
	                	alert("Something went wrong with the estimate request.");

	                	// Clear Vehicle Type Options
                        uberView.getFieldValue(path, false).vehicleTypeOptions(null);
	                }

	                // Set errorcode false
				    $('#' + path.toLowerCase().replace(/\./g, '_') + "_vehicletype").attr('data-errorcode', "");

				    // Call callback function if there is function
				    if (callbackfun != "" && typeof callbackfun === 'function') {
				    	callbackfun();
				    }

                	// Set is loading false
                	uberView.setIsLoading(false);
                },
                error: function(xhr, status, error) {
                	try {
                        // Try to parse the JSON response from the server
                        var errorResponse = JSON.parse(xhr.responseText);
                        alert(errorResponse.message);

                        // Clear Vehicle Type Options
                        uberView.getFieldValue(path, false).vehicleTypeOptions(null);
                    } catch (e) {
                        alert("Something went wrong with the estimate request.");

                        // Clear Vehicle Type Options
                        uberView.getFieldValue(path, false).vehicleTypeOptions(null);
                    }

                    // Set errorcode false
				    $('#' + path.toLowerCase().replace(/\./g, '_') + "_vehicletype").attr('data-errorcode', "");

				    // Call callback function if there is function
				    if (callbackfun != "" && typeof callbackfun === 'function') {
				    	callbackfun();
				    }

                    // Set is loading false
                	uberView.setIsLoading(false);
                }
            });

        	}, 100);
        }

        function createHealthTrip(request_mode = '') {
        	var formData = $("#uber_trip").serialize(); // Serialize the form data

        	let path = "oneway";
        	if (uberView.tripType() == "oneway") {
        		path = "oneway";
	        	if (uberView.getFieldValue(path, false).whenToRide() == "" || uberView.getLocationLat(path + '.startLocation')() == "" || uberView.getLocationLng(path + '.startLocation')() == "" ||  uberView.getLocationLat(path + '.endLocation')() == "" || uberView.getLocationLng(path + '.endLocation')() == "") {
	        		return false;
	        	}
        	} else if (uberView.tripType() == "roundtrip") {
        		path = "roundtrip.first_leg";
	        	if (uberView.getFieldValue(path, false).whenToRide() == "" || uberView.getLocationLat(path + '.startLocation')() == "" || uberView.getLocationLng(path + '.startLocation')() == "" ||  uberView.getLocationLat(path + '.endLocation')() == "" || uberView.getLocationLng(path + '.endLocation')() == "") {
	        		return false;
	        	}

	        	path = "roundtrip.return_leg";
	        	if (uberView.getFieldValue(path, false).whenToRide() == "" || uberView.getLocationLat(path + '.startLocation')() == "" || uberView.getLocationLng(path + '.startLocation')() == "" ||  uberView.getLocationLat(path + '.endLocation')() == "" || uberView.getLocationLng(path + '.endLocation')() == "") {
	        		return false;
	        	}
        	}

        	// Set is loading true
        	uberView.setIsLoading(true);

        	let action_mode = "create_trip";
        	if (request_mode == "update") {
        		action_mode = "update_trip";
        	}

            // Send the AJAX POST request
            $.ajax({
                url: 'uber_estimatetime.php?action=' + action_mode, // The URL where you want to send the request
                type: 'POST',
                data: formData,  // The form data
                success: function (response) {
                	try {
	                	if (response != '') {
	                		let responseJson = JSON.parse(response);
	                		
	                		if (responseJson['message']) {
	                			alert(responseJson['message']);
	                		}

	                		// Close window after trip creation
	                		dlgclose();
	                	}
	                } catch (e) {
	                	alert("Something went wrong with the create trip request.");
	                }

                	// Set is loading false
                	uberView.setIsLoading(false);
                },
                error: function(xhr, status, error) {
                	try {
                        // Try to parse the JSON response from the server
                        var errorResponse = JSON.parse(xhr.responseText);
                        alert(errorResponse.message);
                    } catch (e) {
                        alert("Something went wrong with the create trip request.");
                    }

                    if (errorResponse['code'] && errorResponse['code'] > 0) {
	            		if (uberView.tripType() == "oneway") {
	            			// Set errorcode false
			    			$("#oneway_vehicletype").attr('data-errorcode', errorResponse['code']);
	            		} else if (uberView.tripType() == "roundtrip") {
	            			// Set errorcode false
			    			$("#roundtrip_first_leg_vehicletype").attr('data-errorcode', errorResponse['code']);
			    			$("#roundtrip_return_leg_vehicletype").attr('data-errorcode', errorResponse['code']);
	            		}

	            		// Validate form data again
	            		uberView.validateForm();
            		}

                    // Set is loading false
                	uberView.setIsLoading(false);
                }
            });
        }

        function saveGeocode(formatted_address = "", lat = 0, lng = 0) {
        	var formData = $("#uber_trip").serialize(); // Serialize the form data

        	if (formatted_address == "" || lat == "" || lng == "") {
        		return false;
        	}

            // Send the AJAX POST request
            $.ajax({
                url: 'uber_estimatetime.php?action=geocode&formatted_address=' + formatted_address + "&lat=" + lat + "&lng=" + lng, // The URL where you want to send the request
                type: 'POST',
                success: function (response) {
                	try {
	                	if (response != '') {
	                		//let responseJson = JSON.parse(response);
	                	}
	                } catch (e) {
	                }
                },
                error: function(xhr, status, error) {
                }
            });
        }

        // Function to format the phone number as (XXX) XXX-XXXX
		function formatPhoneNumber(phoneNumber) {
	            // Remove all non-digit characters
	            phoneNumber = phoneNumber.replace(/\D/g, '');

	            // If the phone number starts with '1', remove the leading '1'
			    if (phoneNumber.startsWith('1')) {
			        phoneNumber = phoneNumber.substring(1); // Strip the first digit '1'
			    }

	            // Apply the format (XXX) XXX-XXXX
	            if (phoneNumber.length <= 3) {
	                phoneNumber = phoneNumber;
	            } else if (phoneNumber.length <= 6) {
	                phoneNumber = `(${phoneNumber.substring(0, 3)}) ${phoneNumber.substring(3)}`;
	            } else {
	                phoneNumber = `(${phoneNumber.substring(0, 3)}) ${phoneNumber.substring(3, 6)}-${phoneNumber.substring(6, 10)}`;
	            }

	            if (phoneNumber.length >= 1) {
	            	phoneNumber = "+1 " + phoneNumber;
	            }

	            return phoneNumber;
	        };

        // Function to recursively convert all properties of an object into observables
        function makeObservables(obj) {
            // Loop through each key of the object
            for (var key in obj) {
                if (obj.hasOwnProperty(key)) {
                    var value = obj[key];

                    // If value is an object, recursively make its properties observables
                    if (typeof value === "object" && value !== null && (key != "startLocation" && key != "endLocation")) {
                        makeObservables(value); // Recurse into the object
                    } else {
                        // Make primitive values observables
                        obj[key] = ko.observable(value);
                    }
                }
            }
        }

        function UberViewModel() {
        	this.defaultTodayDate = '<?php echo $todayDate; ?>';
        	this.defaultCurrentTime = '<?php echo $currentTime; ?>';

        	this.isLoading = ko.observable(1);
        	this.isDefaultValueSet = ko.observable(0);

        	// Rider Info
        	this.riderFirstName = ko.observable('<?php echo addslashes($initialTripObject['riderFirstName'] ?? ""); ?>');
        	this.riderLastName = ko.observable('<?php echo addslashes($initialTripObject['riderLastName'] ?? ""); ?>');
        	this.riderPhoneNumber = ko.observable(formatPhoneNumber('<?php echo $initialTripObject['riderPhoneNumber'] ?? ""; ?>'));

        	// Ride plan
	    	this.defaultLocation = ko.observable({lat: 30.3072916, lng: -97.7427565});
	    	this.tripType = ko.observable('<?php echo $initialTripObject['tripType'] ?? ""; ?>');

	    	initialTripObject = { 
	    		"oneway": <?php echo json_encode($initialTripObject['oneway'] ?? "") ?>,
	    		"roundtrip": <?php echo json_encode($initialTripObject['roundtrip'] ?? "") ?>
	    	};

	    	this.tripTypeObject = JSON.parse(JSON.stringify(initialTripObject));

	    	// Create a Knockout observable for the object
	    	makeObservables(this.tripTypeObject);

	    	this.oneway = this.tripTypeObject.oneway;
	    	this.roundtrip = this.tripTypeObject.roundtrip;
	    	let self = this;
			//this.tripTypeObject = ko.observable(tripTypeObject);

			this.setIsLoading = function(status = true) {
				let currentLoadingValues = this.isLoading();
				if (status === true) {
					this.isLoading(currentLoadingValues + 1);
				} else if (status === false) {
					this.isLoading(currentLoadingValues - 1);
				}
			}

			this.getIsLoading = ko.computed(function() {
		        return self.isLoading() === 0 ? false : true;
		    }, self);

			// Function to access a property dynamically by a dot-separated string path
			this.getValueByPath = function(obj, path) {
			    // Split the path string into an array of keys
			    const keys = path.split('.');
			    
			    // Use reduce to traverse the object and access the value
			    let itemValue = keys.reduce((acc, key) => {
			        // Check if the key exists in the current level of the object
			        if (acc && acc.hasOwnProperty(key)) {
			            return acc[key];  // Move to the next nested level
			        } else {
			            return undefined;  // If the key doesn't exist, return undefined
			        }
			    }, obj);

			    
			    return itemValue;
			}

			// Function to recursively reset all observables to their initial values
			this.resetToInitialValues = function(obj = null, initialValues = null) {
				if (obj == null) {
					obj = {
		        		'oneway' : this.oneway,
		        		'roundtrip' : this.roundtrip
		        	}
	        	}

	        	if (initialValues == null) {
	        		initialValues = initialTripObject;
	        	}

			    for (var key in obj) {
			        if (obj.hasOwnProperty(key)) {
			            // Check if the property is an observable
			            if (ko.isObservable(obj[key])) {
			                // Reset observable to its initial value
			                obj[key](initialValues[key]);
			            } else if (typeof obj[key] === "object" && obj[key] !== null) {
			                // If the property is an object, recursively call this function
			                this.resetToInitialValues(obj[key], initialValues[key]);
			            }
			        }
			    }
			}

			this.getFieldValueComputed = function(path = '') {
		        return ko.computed(function() {
		        	let value = null;
		        	let combinedObj = {
		        		'oneway' : this.oneway,
		        		'roundtrip' : this.roundtrip
		        	}

		            value = this.getValueByPath(combinedObj, path)();

		            return value !== null && value !== undefined && value ? value : ''; // Default to empty string 
		        }, this);
		    };

		    this.getFieldValue = function(path = '', execute = true, combinedObj = null) {
		        let value = null;

		        if (combinedObj == null) {
		        	combinedObj = {
		        		'oneway' : this.oneway,
		        		'roundtrip' : this.roundtrip
		        	}
	        	}

	        	if (execute === true) {
	            	value = this.getValueByPath(combinedObj, path)();
	        	} else {
	        		value = this.getValueByPath(combinedObj, path);
	        	}
	            return value !== null && value !== undefined && value ? value : ''; // Default to empty string
		    };

		    this.setFieldValue = function(path = '', value = '', combinedObj = null) {
	        	if (combinedObj == null) {
		        	combinedObj = {
		        		'oneway' : this.oneway,
		        		'roundtrip' : this.roundtrip
		        	}
	        	}

	            this.getValueByPath(combinedObj, path)(value);
		    };

		    this.getLocationLat = function(path = '') {
        		return ko.computed(function() {
		        	let value = self.getFieldValue(path);
        			return value !== null && value !== undefined && value.lat ? value.lat : ''; // Default to empty string if null
		        }, self);
		    };

		    this.getLocationLng = function(path = '') {
		    	return ko.computed(function() {
		        	let value = self.getFieldValue(path);
        			return value !== null && value !== undefined && value.lng ? value.lng : ''; // Default to empty string if null
		        }, self);
		    };

		    this.isOneWayTrip = ko.computed(function(type = '') {
		        return self.tripType() == "oneway" ? true : false;
		    }, self);

		    this.isRoundTrip = ko.computed(function(type = '') {
		        return self.tripType() == "roundtrip" ? true : false;
		    }, self);

		    this.isFutureTrip = function(path = '') {
		        return ko.computed(function() {
		        	return self.getFieldValue(path, false).whenToRide() == "futuretrip" ? true : false;
		        }, self);
		    };

		    this.isFlexible = function(path = '') {
		        return ko.computed(function() {
		        	return self.getFieldValue(path, false).scheduleType() == "flexible" ? true : false;
		        }, self);
		    };

		    this.isScheduleTrip = function(path = '') {
		        return ko.computed(function() {
		        	return self.getFieldValue(path, false).scheduleType() == "schedule" ? true : false;
		        }, self);
		    };

		    this.getVehicleTypeOptions = function(path ='') {
		    	return ko.computed(function() {
		        	let typeOptions = self.getFieldValue(path, false).vehicleTypeOptions();
		        	let optList = [{ "name" : "Please Select", "id" : "" }];

		        	if (typeOptions != '' && Array.isArray(typeOptions)) {
		        		optList = optList.concat(typeOptions);
		        	}

		        	return optList;
		        }, self);
		    };

		    // Error tracking model for each field (store array of error messages)
		    this.errors = {
		        riderFirstName: ko.observableArray([]),
		        riderLastName: ko.observableArray([]),
		        riderPhoneNumber: ko.observableArray([]),
		        tripType: ko.observableArray([]),
		        oneway: {
		        	startLocation: ko.observableArray([]),
			        endLocation: ko.observableArray([]),
			        whenToRide: ko.observableArray([]),
			        scheduleType: ko.observableArray([]),
			        flexibleRideDate: ko.observableArray([]),
			        futureRideDate: ko.observableArray([]),
			        futureRideTime: ko.observableArray([]),
			        vehicleType: ko.observableArray([])
		        },
		        roundtrip: {
		        	first_leg: {
		        		startLocation: ko.observableArray([]),
				        endLocation: ko.observableArray([]),
				        whenToRide: ko.observableArray([]),
				        scheduleType: ko.observableArray([]),
				        flexibleRideDate: ko.observableArray([]),
				        futureRideDate: ko.observableArray([]),
				        futureRideTime: ko.observableArray([]),
				        vehicleType: ko.observableArray([])
		        	},
		        	return_leg: {
		        		startLocation: ko.observableArray([]),
				        endLocation: ko.observableArray([]),
				        whenToRide: ko.observableArray([]),
				        scheduleType: ko.observableArray([]),
				        flexibleRideDate: ko.observableArray([]),
				        futureRideDate: ko.observableArray([]),
				        futureRideTime: ko.observableArray([]),
				        vehicleType: ko.observableArray([])
		        	}
		        }
		    };

		    // Apply Inputmask to the phone number input field
        	// Computed observable for formatted phone number
		    this.formattedPhoneNumber = ko.computed({
		        read: function() {
		            return formatPhoneNumber(self.riderPhoneNumber());
		        },
		        write: function(value) {
		            self.riderPhoneNumber(value);
		        }
		    });

		    this.getCleanedPhoneNumber = ko.computed(function() {
		    	let phoneNumber = this.riderPhoneNumber();
		        return phoneNumber.replace(/[^\d]/g, '');
		    }, self);

		    // Form validation function
		    this.validateForm = function() {
		    	// Clear existing error messages
		        this.errors.riderFirstName([]);
		        this.errors.riderLastName([]);
		        this.errors.riderPhoneNumber([]);
		        this.errors.tripType([]);

		        // Oneway
		        this.errors.oneway.startLocation([]);
		        this.errors.oneway.endLocation([]);
		        this.errors.oneway.whenToRide([]);
		        this.errors.oneway.scheduleType([]);
		        this.errors.oneway.flexibleRideDate([]);
		        this.errors.oneway.futureRideDate([]);
		        this.errors.oneway.futureRideTime([]);
		        this.errors.oneway.vehicleType([]);

		        // Roundtrip first leg
		        this.errors.roundtrip.first_leg.startLocation([]);
		        this.errors.roundtrip.first_leg.endLocation([]);
		        this.errors.roundtrip.first_leg.whenToRide([]);
		        this.errors.roundtrip.first_leg.scheduleType([]);
		        this.errors.roundtrip.first_leg.flexibleRideDate([]);
		        this.errors.roundtrip.first_leg.futureRideDate([]);
		        this.errors.roundtrip.first_leg.futureRideTime([]);
		        this.errors.roundtrip.first_leg.vehicleType([]);

		        // Roundtrip return leg
		        this.errors.roundtrip.return_leg.startLocation([]);
		        this.errors.roundtrip.return_leg.endLocation([]);
		        this.errors.roundtrip.return_leg.whenToRide([]);
		        this.errors.roundtrip.return_leg.scheduleType([]);
		        this.errors.roundtrip.return_leg.flexibleRideDate([]);
		        this.errors.roundtrip.return_leg.futureRideDate([]);
		        this.errors.roundtrip.return_leg.futureRideTime([]);
		        this.errors.roundtrip.return_leg.vehicleType([]);

		        // Validation checks
		        var valid = true;

		        if (!this.riderFirstName()) {
		            this.errors.riderFirstName.push("First name is required.");
		            valid = false;
		        }

		        if (!this.riderLastName()) {
		            this.errors.riderLastName.push("Last name is required.");
		            valid = false;
		        }

		        if (!this.getCleanedPhoneNumber()) {
		            this.errors.riderPhoneNumber.push("Phone number is required.");
		            valid = false;
		        }

		        if (this.getCleanedPhoneNumber().length > 11 || this.getCleanedPhoneNumber().length < 11) {
		            this.errors.riderPhoneNumber.push("Phone number is not valid.");
		            valid = false;
		        }

		        if (!this.tripType()) {
		            this.errors.tripType.push("Trip type is required.");
		            valid = false;
		        }

		        let requiredFieldValidation = {
		        	"oneway": {
		        		"locationStart": [
			        		"oneway.startLocation"
			        	],
			        	"locationEnd": [
			        		"oneway.endLocation"
			        	],
			        	"whenToRide": [
			        		"oneway.whenToRide"
			        	],
			        	"scheduleType": [
			        		"oneway.scheduleType"
			        	],
			        	"vehicleType": [
			        		"oneway.vehicleType"
			        	],
			        	"flexibleRideDate": [
			        		"oneway.flexibleRideDate"
			        	],
			        	"futureRideDate": [
			        		"oneway.futureRideDate"
			        	],
			        	"futureRideTime": [
			        		"oneway.futureRideTime"
			        	],
			        	"fareExpired": [
			        		"oneway.vehicleType"
			        	]
		        	},
		        	"roundtrip": {
		        		"locationStart": [
			        		"roundtrip.first_leg.startLocation",
			        		"roundtrip.return_leg.startLocation"
			        	],
			        	"locationEnd": [
			        		"roundtrip.first_leg.endLocation",
			        		"roundtrip.return_leg.endLocation"
			        	],
			        	"whenToRide": [
			        		"roundtrip.first_leg.whenToRide",
			        		"roundtrip.return_leg.whenToRide"
			        	],
			        	"scheduleType": [
			        		"roundtrip.first_leg.scheduleType",
			        		"roundtrip.return_leg.scheduleType",
			        	],
			        	"vehicleType": [
			        		"roundtrip.first_leg.vehicleType",
			        		"roundtrip.return_leg.vehicleType"
			        	],
			        	"flexibleRideDate": [
			        		"roundtrip.first_leg.flexibleRideDate",
			        		"roundtrip.return_leg.flexibleRideDate"
			        	],
			        	"flexibleRideDateCheck": [
			        		"roundtrip.return_leg.flexibleRideDate"
			        	],
			        	"futureRideDate": [
			        		"roundtrip.first_leg.futureRideDate",
			        		"roundtrip.return_leg.futureRideDate"
			        	],
			        	"futureRideTime": [
			        		"roundtrip.first_leg.futureRideTime",
			        		"roundtrip.return_leg.futureRideTime"
			        	],
			        	"futureRideDateTimeCheck": [
			        		"roundtrip.return_leg.futureRideDate"
			        	],
			        	"fareExpired": [
			        		"roundtrip.first_leg.vehicleType",
			        		"roundtrip.return_leg.vehicleType"
			        	]
		        	}
		        };

		        for (var typekey in requiredFieldValidation) {
		        	if (typekey == this.tripType()) {
				        for (var key in requiredFieldValidation[typekey]) {
			                if (requiredFieldValidation[typekey].hasOwnProperty(key) && Array.isArray(requiredFieldValidation[typekey][key])) {
			                	requiredFieldValidation[typekey][key].forEach((itempath, index) => {
				                	if (key == "locationStart") {
				                		if (!this.getLocationLat(itempath)() || !this.getLocationLng(itempath)()) {

				                			let startLocationErrorList = this.getFieldValue(itempath, true, this.errors);
				                			startLocationErrorList = startLocationErrorList == null ? startLocationErrorList : [];

				                			this.setFieldValue(itempath, startLocationErrorList.concat(["Pick-up address is required."]), this.errors);
								           
								            valid = false;
								        }
				                	} else if (key == "locationEnd") {
				                		if (!this.getLocationLat(itempath)() || !this.getLocationLng(itempath)()) {
				                			let endLocationErrorList = this.getFieldValue(itempath, true, this.errors);
				                			endLocationErrorList = endLocationErrorList == null ? endLocationErrorList : [];

				                			this.setFieldValue(itempath, endLocationErrorList.concat(["Drop-off address is required."]), this.errors);
								           
								            valid = false;
								        }
				                	} else if (key == "whenToRide") {
				                		if (this.getFieldValue(itempath) == "") {
				                			let whenToRideErrorList = this.getFieldValue(itempath, true, this.errors);
				                			whenToRideErrorList = whenToRideErrorList == null ? whenToRideErrorList : [];

				                			this.setFieldValue(itempath, whenToRideErrorList.concat(["When to ride is required."]), this.errors);
								           
								            valid = false;
				                		}
				                	} else if (key == "vehicleType") {
				                		if (this.getFieldValue(itempath) == "") {
				                			let vehicleTypeErrorList = this.getFieldValue(itempath, true, this.errors);
				                			vehicleTypeErrorList = vehicleTypeErrorList == null ? vehicleTypeErrorList : [];

				                			this.setFieldValue(itempath, vehicleTypeErrorList.concat(["Vehicle type is required."]), this.errors);
								           
								            valid = false;
				                		}
				                	} else if (key == "scheduleType") {
				                		let lastDotIndex = itempath.lastIndexOf('.');
				                		let lastresult = itempath.slice(0, lastDotIndex);

				                		if (this.isFutureTrip(lastresult)() === true && this.getFieldValue(itempath) == "") {
				                			let scheduleTypeErrorList = this.getFieldValue(itempath, true, this.errors);
				                			scheduleTypeErrorList = scheduleTypeErrorList == null ? scheduleTypeErrorList : [];

				                			this.setFieldValue(itempath, scheduleTypeErrorList.concat(["Future type is required."]), this.errors);
								           
								            valid = false;
				                		}
				                	} else if (key == "flexibleRideDate") {
				                		let lastDotIndex = itempath.lastIndexOf('.');
				                		let lastresult = itempath.slice(0, lastDotIndex);

				                		if (this.isFlexible(lastresult)() === true && this.isFutureTrip(lastresult)() === true) {
				                			if (this.getFieldValue(itempath) == "") {
					                			let flexibleRideDateErrorList = this.getFieldValue(itempath, true, this.errors);
					                			flexibleRideDateErrorList = flexibleRideDateErrorList == null ? flexibleRideDateErrorList : [];

					                			this.setFieldValue(itempath, flexibleRideDateErrorList.concat(["Flexible ride date is required."]), this.errors);
									           
									            valid = false;
					                		}
				                		}
				                	} else if (key == "futureRideDate") {
				                		let lastDotIndex = itempath.lastIndexOf('.');
				                		let lastresult = itempath.slice(0, lastDotIndex);

				                		if (this.isScheduleTrip(lastresult)() === true && this.isFutureTrip(lastresult)() === true) {
				                			if (this.getFieldValue(itempath) == "") {
					                			let futureRideDateErrorList = this.getFieldValue(itempath, true, this.errors);
					                			futureRideDateErrorList = futureRideDateErrorList == null ? futureRideDateErrorList : [];

					                			this.setFieldValue(itempath, futureRideDateErrorList.concat(["Future ride date is required."]), this.errors);
									           
									            valid = false;
					                		}
				                		}
				                	} else if (key == "futureRideTime") {
				                		let lastDotIndex = itempath.lastIndexOf('.');
				                		let lastresult = itempath.slice(0, lastDotIndex);

				                		if (this.isScheduleTrip(lastresult)() === true && this.isFutureTrip(lastresult)() === true) {
				                			if (this.getFieldValue(itempath) == "") {
					                			let futureRideTimeErrorList = this.getFieldValue(itempath, true, this.errors);
					                			futureRideTimeErrorList = futureRideTimeErrorList == null ? futureRideTimeErrorList : [];

					                			this.setFieldValue(itempath, futureRideTimeErrorList.concat(["Time required."]), this.errors);
									           
									            valid = false;
					                		}
				                		}
				                	} else if (key == "fareExpired") {
				                		let elementId = itempath.toLowerCase().replace(/\./g, '_');
				                		let eleVehicleType = $('#' + elementId).attr('data-errorcode');

				                		if (eleVehicleType && eleVehicleType == "10002") {
				                			let vhErrorList = this.getFieldValue(itempath, true, this.errors);
				                			vhErrorList = vhErrorList == null ? vhErrorList : [];

				                			this.setFieldValue(itempath, vhErrorList.concat(["The fare estimate has expired, (Note: Click on refresh button for request again.)"]), this.errors);
				                			valid = false;
				                		}
				                	} else if (key == "futureRideDateTimeCheck") {
				                		let flScheduleTypeValue = this.getFieldValue("roundtrip.first_leg.scheduleType");
				                		let rlScheduleTypeValue = this.getFieldValue("roundtrip.return_leg.scheduleType");

				                		let flfutureDateValue = this.getFieldValue("roundtrip.first_leg.futureRideDate");
				                		let flfutureTimeValue = this.getFieldValue("roundtrip.first_leg.futureRideTime");

				                		let rlfutureDateValue = this.getFieldValue("roundtrip.return_leg.futureRideDate");
				                		let rlfutureTimeValue = this.getFieldValue("roundtrip.return_leg.futureRideTime");

				                		let flflexibleRideDateValue = this.getFieldValue("roundtrip.first_leg.flexibleRideDate");

				                		if (flScheduleTypeValue == "schedule" && rlScheduleTypeValue == "schedule") {
					                		if (flfutureDateValue != "" && flfutureTimeValue != "" && rlfutureDateValue != "" && rlfutureTimeValue != "") {
					                			let flFutureDateTime = flfutureDateValue + " " + flfutureTimeValue;
					                			let rlFutureDateTime = rlfutureDateValue + " " + rlfutureTimeValue;

					                			const datetime1 = new Date(flFutureDateTime);  // Date object for the combined datetime
	    										const datetime2 = new Date(rlFutureDateTime);  // Date object for the comparison datetime

	    										if (datetime1 > datetime2) {
	    											let vhErrorList = this.getFieldValue(itempath, true, this.errors);
					                				vhErrorList = vhErrorList == null ? vhErrorList : [];

					                				this.setFieldValue(itempath, vhErrorList.concat(["Future date and time should not be less than first leg future date and time.)"]), this.errors);
					                				valid = false;
	    										}
					                		}
				                		} else if (flScheduleTypeValue == "flexible" && rlScheduleTypeValue == "schedule") {
				                			if (flflexibleRideDateValue != "" && rlfutureDateValue != "" && rlfutureTimeValue != "") {
				                				let flFlexibleRideDate = flflexibleRideDateValue + " 00:00";
				                				let rlFutureDateTime = rlfutureDateValue + " " + rlfutureTimeValue;

				                				const datetime1 = new Date(flFlexibleRideDate);  // Date object for the combined datetime
	    										const datetime2 = new Date(rlFutureDateTime);  // Date object for the comparison datetime

	    										if (datetime1 > datetime2) {
	    											let vhErrorList = this.getFieldValue(itempath, true, this.errors);
					                				vhErrorList = vhErrorList == null ? vhErrorList : [];

					                				this.setFieldValue(itempath, vhErrorList.concat(["Schedule datetimer should not be less than first leg flexible date.)"]), this.errors);
					                				valid = false;
	    										}
				                			}
				                		}
				                	} else if (key == "flexibleRideDateCheck") {
				                		let flScheduleTypeValue = this.getFieldValue("roundtrip.first_leg.scheduleType");
				                		let rlScheduleTypeValue = this.getFieldValue("roundtrip.return_leg.scheduleType");

				                		let flflexibleRideDateValue = this.getFieldValue("roundtrip.first_leg.flexibleRideDate");
				                		let rlflexibleRideDateValue = this.getFieldValue("roundtrip.return_leg.flexibleRideDate");

				                		let flfutureDateValue = this.getFieldValue("roundtrip.first_leg.futureRideDate");
				                		let flfutureTimeValue = this.getFieldValue("roundtrip.first_leg.futureRideTime");

				                		if (flScheduleTypeValue == "flexible" && rlScheduleTypeValue == "flexible") {
					                		if (flflexibleRideDateValue != "" && rlflexibleRideDateValue != "") {
					                			let flFlexibleRideDate = flflexibleRideDateValue + " 00:00";
					                			let rlFlexibleRideDate = rlflexibleRideDateValue + " 00:00";

					                			const datetime1 = new Date(flFlexibleRideDate);  // Date object for the combined datetime
	    										const datetime2 = new Date(rlFlexibleRideDate);  // Date object for the comparison datetime

	    										if (datetime1 > datetime2) {
	    											let vhErrorList = this.getFieldValue(itempath, true, this.errors);
					                				vhErrorList = vhErrorList == null ? vhErrorList : [];

					                				this.setFieldValue(itempath, vhErrorList.concat(["Flexible date should not be less than first leg flexible date.)"]), this.errors);
					                				valid = false;
	    										}
					                		}
				                		} else if (flScheduleTypeValue == "schedule" && rlScheduleTypeValue == "flexible") {
				                			if (rlflexibleRideDateValue != "" && flfutureDateValue != "" && flfutureTimeValue != "") {
				                				let flFutureDateTime = flfutureDateValue + " " + flfutureTimeValue;
				                				let rlFlexibleRideDate = rlflexibleRideDateValue + " 00:00";

				                				const datetime1 = new Date(flFutureDateTime);  // Date object for the combined datetime
	    										const datetime2 = new Date(rlFlexibleRideDate);  // Date object for the comparison datetime

	    										if (datetime1 > datetime2) {
	    											let vhErrorList = this.getFieldValue(itempath, true, this.errors);
					                				vhErrorList = vhErrorList == null ? vhErrorList : [];

					                				this.setFieldValue(itempath, vhErrorList.concat(["Flexible date should not be less than first leg schedule datetime.)"]), this.errors);
					                				valid = false;
	    										}
				                			}
				                		}
				                	}
								});
			                }
		            	}
	            	}
            	}

		        return valid; // Prevent actual form submission
        	}
	    }

	    function submitBookTrip(request_mode = '') {
	    	let validateStatus = uberView.validateForm();

	    	if (validateStatus === true) {
	    		createHealthTrip(request_mode);
	    	}
	    }

	    // This invokes the find-patient popup.
		function sel_patient() {
		    let title = '<?php echo xlt('Patient Search'); ?>';
		    dlgopen('<?php echo $GLOBALS['webroot']; ?>/interface/main/calendar/find_patient_popup.php', 'findPatient', 650, 300, '', title);
		}

		function setpatient(pid, lname, fname, dob = '', alert_info = '', p_data = {}) {
			fetchPatientDetails(pid);
		}

		function fetchPatientDetails(pid) {
			
        	// Set is loading true
        	uberView.setIsLoading(true);

            // Send the AJAX POST request
            $.ajax({
                url: 'uber_estimatetime.php?action=fetch_patient&form_pid=' + pid, // The URL where you want to send the request
                type: 'POST',
                success: function (response) {
                	let needSetLoadingStatus = true;

                	try {
	                	if (response != '') {
	                		let responseJson = JSON.parse(response);

	                		if (responseJson.hasOwnProperty('firstname')) {
	                			uberView.riderFirstName(responseJson['firstname']);
	                		}

	                		if (responseJson.hasOwnProperty('lastname')) {
	                			uberView.riderLastName(responseJson['lastname']);
	                		}

	                		if (responseJson.hasOwnProperty('phonenumber')) {
	                			uberView.riderPhoneNumber(responseJson['phonenumber']);
	                		}

	                		if (responseJson.hasOwnProperty('location_name')) {
	                			needSetLoadingStatus = false;

	                			// Set value status
        	    				uberView.isDefaultValueSet(uberView.isDefaultValueSet() + 1);

	                			defaultDataSet['startLocationName'] = responseJson['location_name'] ? responseJson['location_name'] : "";

				        	  	if (responseJson.hasOwnProperty('location') && responseJson['location']['lat'] != "" && responseJson['location']['lng'] != "") {
				        	  		defaultDataSet['startLocation'] = { "lat": responseJson['location']['lat'], "lng": responseJson['location']['lng'] };
				        		}

				        		// Set pid
				        		$('input[name="form_pid"]').val(pid);

				        		// Set value status
        	    				uberView.isDefaultValueSet(uberView.isDefaultValueSet() - 1);
	                		}
	                	}
	                } catch (e) {
	                	alert("Something went wrong with the fetch details request.");
	                }

	                if (needSetLoadingStatus === true) {
	                	// Set is loading false
                		uberView.setIsLoading(false);
                	}
                },
                error: function(xhr, status, error) {
                	try {
                        // Try to parse the JSON response from the server
                        var errorResponse = JSON.parse(xhr.responseText);
                        alert(errorResponse.message);
                    } catch (e) {
                        alert("Something went wrong with the fetch details request.");
                    }

                    // Set is loading false
                	uberView.setIsLoading(false);
                }
            });
		}

		// This is for callback by the find-addressbook popup.
		function setFacility(id, name, address) {
			fetchFacilityDetails(id);
		}

		// This invokes the find-facilities popup.
		function sel_facilities_address() {
			var url = '<?php echo $GLOBALS['webroot']."/interface/main/attachment/find_facilities_popup.php?pid=". $pid; ?>&pagetype=postal_letter';
		  	let title = '<?php echo xlt('Facilities Search'); ?>';
		  	dlgopen(url, 'findFacilities', 1100, '', '', title);
		}

		function fetchFacilityDetails(facility_id) {
			
        	// Set is loading true
        	uberView.setIsLoading(true);

            // Send the AJAX POST request
            $.ajax({
                url: 'uber_estimatetime.php?action=fetch_facility&form_facility_id=' + facility_id, // The URL where you want to send the request
                type: 'POST',
                success: function (response) {
                	let needSetLoadingStatus = true;

                	try {
	                	if (response != '') {
	                		let responseJson = JSON.parse(response);

	                		if (responseJson.hasOwnProperty('location_name')) {
	                			needSetLoadingStatus = false;

	                			// Set value status
        	    				uberView.isDefaultValueSet(uberView.isDefaultValueSet() + 1);

	                			defaultDataSet['endLocationName'] = responseJson['location_name'] ? responseJson['location_name'] : "";

				        	  	if (responseJson.hasOwnProperty('location') && responseJson['location']['lat'] != "" && responseJson['location']['lng'] != "") {
				        	  		defaultDataSet['endLocation'] = { "lat": responseJson['location']['lat'], "lng": responseJson['location']['lng'] };
				        		}

				        		// Set value status
        	    				uberView.isDefaultValueSet(uberView.isDefaultValueSet() - 1);
	                		}
	                	}
	                } catch (e) {
	                	alert("Something went wrong with the fetch details request.");
	                }

	                if (needSetLoadingStatus === true) {
	                	// Set is loading false
                		uberView.setIsLoading(false);
                	}
                },
                error: function(xhr, status, error) {
                	try {
                        // Try to parse the JSON response from the server
                        var errorResponse = JSON.parse(xhr.responseText);
                        alert(errorResponse.message);
                    } catch (e) {
                        alert("Something went wrong with the fetch details request.");
                    }

                    // Set is loading false
                	uberView.setIsLoading(false);
                }
            });
		}

        // Initialize the Autocomplete API once the page is fully loaded
        window.onload = initMap;
        
    </script>

    <script type="text/javascript">
    	$(document).ready(function(){
    		$(".sel2").select2({
	            theme: "bootstrap4",
	            dropdownAutoWidth: true,
	            width: 'resolve',
	            templateResult: function(data) {
			      // Check if data.text contains HTML (it will if it's from the <option> tag)
			      if (data.text) {
			        return $('<span>').html(data.text);  // Convert the text to HTML
			      }
			      return data.text;
			    }
	        });
    	});
    </script>

    <style>
        /* Google map styles */
        #map {
            width: 100%;
		    height: 100vh;
		    position: relative;
		    max-height: 576px;
			/*max-width: 576px;*/
		    border-radius: 10px;
        }

        .pageContainer {
        	display: grid;
    		grid-template-columns: auto 1fr;
    		grid-gap: 15px;
        }

        .userRideDetails {
        	width: 100%;
        	max-width: 350px;
        	min-width: 350px;
        }

        .mapContainer {
        	width: 100%;
        	justify-items: center;
        }

        .page-loader-container {
        	position: fixed;
		    top: 0;
		    left: 0;
		    background-color: rgba(255, 255, 255, 0.5);
		    width: 100%;
		    height: 100%;
		    z-index: 1000;
		    display: grid;
		    justify-content: center;
		    align-content: center;
        }

        .sel2-vehicle-container {
        	display: grid;
    		grid-template-columns: 1fr auto;
        }

        .select2-results__option {
        	border-bottom: 1px solid var(--gray300);
        }

        input[readonly].form-control {
		  pointer-events: none;  /* Disables all mouse interactions */
		}
    </style>
</head>
<body>
	<div class="pageContainer container-fluid">
		<div class="userRideDetails">
		    <form id="uber_trip" method="post">

		    	<input type="hidden" name="trip_request_id" value="<?php echo $trip_request_id ?? "" ?>">
		    	<input type="hidden" name="request_mode" value="<?php echo $request_mode ?? "" ?>">

		    	<div class="form-row mb-3">
		    		<div class="col-6">
		    			<button type="button" class="btn btn-primary w-100" onclick='sel_patient()'><?php echo xlt("Select Patient"); ?></button>
		    		</div>
		    		<div class="col-6">
		    			<button type="button" class="btn btn-primary w-100" onclick='sel_facilities_address()'><?php echo xlt("Select Facility"); ?></button>
		    		</div>
		    	</div>

		    	<div class="card mb-3">
				  	<div class="card-header"><?php echo xlt("Who's riding?"); ?></div>
				  	<div class="card-body px-3 py-3">
				  		<input type="hidden" name="form_pid" value="<?php echo $form_pid; ?>">
				  		<input type="hidden" name="form_eid" value="<?php echo $form_eid; ?>">
				  		<div class="form-row">
						    <div class="form-group col-md-6">
						      <label for="rider_first_name"><?php echo xlt("Rider first name"); ?></label>
						      <input type="text" class="form-control" name="rider_first_name" id="rider_first_name" placeholder="First Name" data-bind="value: riderFirstName, css: { 'is-invalid': errors.riderFirstName().length > 0 }">
						      <!-- Multiple error messages -->
					          <div class="invalid-feedback" data-bind="foreach: errors.riderFirstName">
					            <div data-bind="text: $data"></div>
					          </div>
						    </div>
						    <div class="form-group col-md-6">
						      <label for="rider_last_name"><?php echo xlt("Rider last name"); ?></label>
						      <input type="text" class="form-control" name="rider_last_name" id="rider_last_name" placeholder="Last Name" data-bind="value: riderLastName, css: { 'is-invalid': errors.riderLastName().length > 0 }">
						      <!-- Multiple error messages -->
					          <div class="invalid-feedback" data-bind="foreach: errors.riderLastName">
					            <div data-bind="text: $data"></div>
					          </div>
						    </div>
						</div>
						<div class="form-group">
						    <label for="rider_phone_number"><?php echo xlt("Phone Number"); ?></label>
						    <input type="text" class="form-control" id="rider_phone_number" placeholder="+1 (000) 000-0000" data-bind="value: formattedPhoneNumber, valueUpdate: 'input', css: { 'is-invalid': errors.riderPhoneNumber().length > 0 }">
						    <!-- Multiple error messages -->
					        <div class="invalid-feedback" data-bind="foreach: errors.riderPhoneNumber">
					          <div data-bind="text: $data"></div>
					        </div>
					        <input type="hidden" name="rider_phone_number" data-bind="value: getCleanedPhoneNumber">
						</div>
				  	</div>
				</div>

				<div class="mb-3">
		    		<div class="btn-group w-100" data-bind="css: { 'is-invalid': errors.tripType().length > 0 }" role="group" aria-label="Basic example">
					  <button type="button" data-bind="css: tripType() === 'oneway' ? 'btn-primary' : 'btn-secondary', click: function() { tripType('oneway'); }" class="btn"><?php echo xlt('One-way Trip'); ?></button>
					  <button type="button" data-bind="css: tripType() === 'roundtrip' ? 'btn-primary' : 'btn-secondary', click: function() { tripType('roundtrip'); }" class="btn"><?php echo xlt('Round Trip'); ?></button>
					</div>

					<!-- Multiple error messages -->
			        <div class="invalid-feedback" data-bind="foreach: errors.tripType">
			          <div data-bind="text: $data"></div>
			        </div>

					<input type="hidden" name="trip_type" data-bind="value: tripType()">
		    	</div>

		    	<div data-bind="if: isRoundTrip">

		    		<div class="card mb-3">
					  	<div class="card-header"><?php echo xlt('Plan Ride'); ?></div>
					  	<div class="card-body px-3 py-3">
					  		<div>
					  		<h6><?php echo xlt('First leg'); ?></h6>
					  		<hr class="mt-1">

					  		<div class="form-group mb-3">
							    <label><?php echo xlt('Pick-up address'); ?></label>
							    <div class="input-group" data-bind="css: { 'is-invalid': errors.roundtrip.first_leg.startLocation().length > 0 }">
								  <input type="text" id="roundtrip-firstleg-start-input" class="form-control" name="roundtrip[first_leg][pickup_location_name]" placeholder="<?php echo xlt('Enter Location'); ?>" data-bind="value: roundtrip.first_leg.startLocationName, css: { 'is-invalid': errors.roundtrip.first_leg.startLocation().length > 0 }">
								  <div class="input-group-append">
								    <button type="button" class="btn btn btn-secondary" data-bind="click: clearLocationName.bind($data, 'roundtrip.first_leg', 'start')"><i class="fa fa-times" aria-hidden="true"></i></button>
								  </div>
								</div>

								<!-- Multiple error messages -->
						        <div class="invalid-feedback" data-bind="foreach: errors.roundtrip.first_leg.startLocation">
						          <div data-bind="text: $data"></div>
						        </div>

								<input type="hidden" name="roundtrip[first_leg][pickup_location_lat]" data-bind="value: getLocationLat.bind($data, 'roundtrip.first_leg.startLocation')()">
								<input type="hidden" name="roundtrip[first_leg][pickup_location_lng]" data-bind="value: getLocationLng.bind($data, 'roundtrip.first_leg.startLocation')()">
							</div>

							<div class="form-group mb-3">
							    <label><?php echo xlt('Drop-off address'); ?></label>
							    <div class="input-group" data-bind="css: { 'is-invalid': errors.roundtrip.first_leg.endLocation().length > 0 }">
								  <input type="text" id="roundtrip-firstleg-end-input" class="form-control" name="roundtrip[first_leg][dropoff_location_name]" placeholder="<?php echo xlt('Enter Location'); ?>" data-bind="value: roundtrip.first_leg.endLocationName, css: { 'is-invalid': errors.roundtrip.first_leg.endLocation().length > 0 }">
								  <div class="input-group-append">
								    <button type="button" class="btn btn btn-secondary" data-bind="click: clearLocationName.bind($data, 'roundtrip.first_leg', 'end')"><i class="fa fa-times" aria-hidden="true"></i></button>
								  </div>
								</div>

								<!-- Multiple error messages -->
						        <div class="invalid-feedback" data-bind="foreach: errors.roundtrip.first_leg.endLocation">
						          <div data-bind="text: $data"></div>
						        </div>

								<input type="hidden" name="roundtrip[first_leg][dropoff_location_lat]" data-bind="value: getLocationLat.bind($data, 'roundtrip.first_leg.endLocation')()">
								<input type="hidden" name="roundtrip[first_leg][dropoff_location_lng]" data-bind="value: getLocationLng.bind($data, 'roundtrip.first_leg.endLocation')()">
							</div>

							<div class="mt-4">
								<h6><?php echo xlt('Select when to ride'); ?></h6>
								<hr/>
								<div class="mb-3">
						    		<!-- <div class="btn-group w-100" data-bind="css: { 'is-invalid': errors.roundtrip.first_leg.whenToRide().length > 0 }" role="group">
									  <button type="button" data-bind="css: getFieldValue('roundtrip.first_leg.whenToRide') === 'futuretrip' ? 'btn-primary' : 'btn-secondary', click: function() { updateWhenToTrip('futuretrip', 'roundtrip.first_leg'); }" class="btn"><?php //echo xlt('Future trip'); ?></button>
									</div> -->
									<h6><?php echo xlt('Future trip'); ?></h6>
									<hr class="mt-2">

									<!-- Multiple error messages -->
							        <div class="invalid-feedback" data-bind="foreach: errors.roundtrip.first_leg.whenToRide">
							          <div data-bind="text: $data"></div>
							        </div>

									<input type="hidden" name="roundtrip[first_leg][when_to_ride]" data-bind="value: roundtrip.first_leg.whenToRide">
						    	</div>

						    	<div class="form-row" data-bind="css: { 'is-invalid': errors.roundtrip.first_leg.scheduleType().length > 0 }">
					    			<div class="col-5">
					    				<div class="form-check form-check-inline">
						    				<input class="form-check-input" type="radio" name="roundtrip[first_leg][schedule_type]" id="roundtrip_first_leg_schedule_type" data-bind="attr: { checked : roundtrip.first_leg.scheduleType() == 'flexible' ? 'checked' : null }, click: updateScheduleTypeTrip.bind($data,'roundtrip.first_leg', 'flexible'), value: 'flexible'">
	  										<label class="form-check-label" for="roundtrip_first_leg_schedule_type" style="font-size:14px"><?php echo xlt("Make it flexible"); ?></label>
  										</div>
					    			</div>
					    			<div class="col-7">
					    				<div class="form-check form-check-inline">
						    				<input class="form-check-input" type="radio" name="roundtrip[first_leg][schedule_type]" id="roundtrip_first_leg_schedule_type_datetime" data-bind="attr: { checked : roundtrip.first_leg.scheduleType() == 'schedule' ? 'checked' : null }, click: updateScheduleTypeTrip.bind($data,'roundtrip.first_leg', 'schedule'), value: 'schedule'">
	  										<label class="form-check-label" for="roundtrip_first_leg_schedule_type_datetime" style="font-size:14px"><?php echo xlt("Choose date and time"); ?></label>
  										</div>
					    			</div>
					    		</div>
					    		<!-- Multiple error messages -->
						        <div class="invalid-feedback" data-bind="foreach: errors.roundtrip.first_leg.scheduleType">
						          <div data-bind="text: $data"></div>
						        </div>

						        <div class="mt-2" data-bind="if: isFlexible.bind($data, 'roundtrip.first_leg')()">
					    			<div class="form-group">
									    <label><?php echo xlt("Choose when they'll ride"); ?></label>
									    <div class="form-row">
									    	<div class="col-7">
									    		<input type="text" class="form-control datepicker future_ride_date" name="roundtrip[first_leg][flexible_ride_date]" id="roundtrip_first_leg_flexible_date" placeholder="Date" data-bind="value: roundtrip.first_leg.flexibleRideDate, css: { 'is-invalid': errors.roundtrip.first_leg.flexibleRideDate().length > 0 }">

									    		<!-- Multiple error messages -->
										        <div class="invalid-feedback" data-bind="foreach: errors.roundtrip.first_leg.flexibleRideDate">
										          <div data-bind="text: $data"></div>
										        </div>

									    	</div>
									    </div>
									</div>
					    		</div>

						        <div class="mt-2" data-bind="if: isScheduleTrip.bind($data, 'roundtrip.first_leg')()">
							    	<div data-bind="if: isFutureTrip.bind($data, 'roundtrip.first_leg')()">
							    		<div class="form-group">
										    <label><?php echo xlt("Choose when they'll ride"); ?></label>
										    <div class="form-row">
										    	<div class="col-7">
										    		<input type="text" class="form-control datepicker future_ride_date" name="roundtrip[first_leg][future_ride_date]" id="roundtrip_firstleg_future_ride_date" placeholder="Date" data-bind="value: roundtrip.first_leg.futureRideDate, css: { 'is-invalid': errors.roundtrip.first_leg.futureRideDate().length > 0 }">

										    		<!-- Multiple error messages -->
											        <div class="invalid-feedback" data-bind="foreach: errors.roundtrip.first_leg.futureRideDate">
											          <div data-bind="text: $data"></div>
											        </div>

										    	</div>
										    	<div class="col-5">
										    		<input type="text" class="form-control datepicker future_ride_time" name="roundtrip[first_leg][future_ride_time]" id="roundtrip_firstleg_future_ride_time" placeholder="Date" data-bind="value: roundtrip.first_leg.futureRideTime, css: { 'is-invalid': errors.roundtrip.first_leg.futureRideTime().length > 0 }">

										    		<!-- Multiple error messages -->
											        <div class="invalid-feedback" data-bind="foreach: errors.roundtrip.first_leg.futureRideTime">
											          <div data-bind="text: $data"></div>
											        </div>
										    	</div>
										    </div>
										</div>
							    	</div>
						    	</div>

						    	<div class="form-group">
								    <label><?php echo xlt("Message to driver"); ?></label>
								    <textarea type="text" class="form-control" name="roundtrip[first_leg][message_to_driver]" id="roundtrip_firstleg_message_to_driver" placeholder="Enter message" data-bind="value: roundtrip.first_leg.messageToDriver"></textarea>
								</div>

							</div>
							</div>

							<div>
					  		<h6><?php echo xlt('Return leg'); ?></h6>
					  		<hr class="mt-1">

					  		<div class="form-group mb-3">
							    <label><?php echo xlt('Pick-up address'); ?></label>
							    <div class="input-group" data-bind="css: { 'is-invalid': errors.roundtrip.return_leg.startLocation().length > 0 }">
								  <input type="text" id="roundtrip-returnleg-start-input" class="form-control" name="roundtrip[return_leg][pickup_location_name]" placeholder="<?php echo xlt('Enter Location'); ?>" data-bind="value: roundtrip.return_leg.startLocationName, css: { 'is-invalid': errors.roundtrip.return_leg.startLocation().length > 0 }" readonly>
								  <div class="input-group-append">
								    <button type="button" class="btn btn btn-secondary" data-bind="click: clearLocationName.bind($data, 'roundtrip.return_leg', 'start')" disabled><i class="fa fa-times" aria-hidden="true"></i></button>
								  </div>
								</div>

								<!-- Multiple error messages -->
						        <div class="invalid-feedback" data-bind="foreach: errors.roundtrip.return_leg.startLocation">
						          <div data-bind="text: $data"></div>
						        </div>

								<input type="hidden" name="roundtrip[return_leg][pickup_location_lat]" data-bind="value: getLocationLat.bind($data, 'roundtrip.return_leg.startLocation')()">
								<input type="hidden" name="roundtrip[return_leg][pickup_location_lng]" data-bind="value: getLocationLng.bind($data, 'roundtrip.return_leg.startLocation')()">
							</div>

							<div class="form-group mb-3">
							    <label><?php echo xlt('Drop-off address'); ?></label>
							    <div class="input-group" data-bind="css: { 'is-invalid': errors.roundtrip.return_leg.endLocation().length > 0 }">
								  <input type="text" id="roundtrip-returnleg-end-input" class="form-control" name="roundtrip[return_leg][dropoff_location_name]" placeholder="<?php echo xlt('Enter Location'); ?>" data-bind="value: roundtrip.return_leg.endLocationName, css: { 'is-invalid': errors.roundtrip.return_leg.endLocation().length > 0 }">
								  <div class="input-group-append">
								    <button type="button" class="btn btn btn-secondary" data-bind="click: clearLocationName.bind($data, 'roundtrip.return_leg', 'end')"><i class="fa fa-times" aria-hidden="true"></i></button>
								  </div>
								</div>

								<!-- Multiple error messages -->
						        <div class="invalid-feedback" data-bind="foreach: errors.roundtrip.return_leg.endLocation">
						          <div data-bind="text: $data"></div>
						        </div>

								<input type="hidden" name="roundtrip[return_leg][dropoff_location_lat]" data-bind="value: getLocationLat.bind($data, 'roundtrip.return_leg.endLocation')()">
								<input type="hidden" name="roundtrip[return_leg][dropoff_location_lng]" data-bind="value: getLocationLng.bind($data, 'roundtrip.return_leg.endLocation')()">
							</div>

							<div class="mt-4">
								<h6><?php echo xlt('Select when to ride'); ?></h6>
								<hr/>
								<div class="mb-3">
						    		<!-- <div class="btn-group w-100" data-bind="css: { 'is-invalid': errors.roundtrip.return_leg.whenToRide().length > 0 }" role="group">
									  <button type="button" data-bind="css: getFieldValue('roundtrip.return_leg.whenToRide') === 'futuretrip' ? 'btn-primary' : 'btn-secondary', click: function() { updateWhenToTrip('futuretrip', 'roundtrip.return_leg'); }" class="btn"><?php //echo xlt('Future trip'); ?></button>
									</div> -->
									<h6><?php echo xlt('Future trip'); ?></h6>
									<hr class="mt-2">

									<!-- Multiple error messages -->
							        <div class="invalid-feedback" data-bind="foreach: errors.roundtrip.return_leg.whenToRide">
							          <div data-bind="text: $data"></div>
							        </div>

									<input type="hidden" name="roundtrip[return_leg][when_to_ride]" data-bind="value: roundtrip.return_leg.whenToRide">
						    	</div>

						    	<div class="form-row" data-bind="css: { 'is-invalid': errors.roundtrip.return_leg.scheduleType().length > 0 }">
					    			<div class="col-5">
					    				<div class="form-check form-check-inline">
						    				<input class="form-check-input" type="radio" name="roundtrip[return_leg][schedule_type]" id="roundtrip_return_leg_schedule_type" data-bind="attr: { checked : roundtrip.return_leg.scheduleType() == 'flexible' ? 'checked' : null }, click: updateScheduleTypeTrip.bind($data,'roundtrip.return_leg', 'flexible'), value: 'flexible'">
	  										<label class="form-check-label" for="roundtrip_return_leg_schedule_type" style="font-size:14px"><?php echo xlt("Make it flexible"); ?></label>
  										</div>
					    			</div>
					    			<div class="col-7">
					    				<div class="form-check form-check-inline">
						    				<input class="form-check-input" type="radio" name="roundtrip[return_leg][schedule_type]" id="roundtrip_return_leg_schedule_type_datetime" data-bind="attr: { checked : roundtrip.return_leg.scheduleType() == 'schedule' ? 'checked' : null }, click: updateScheduleTypeTrip.bind($data,'roundtrip.return_leg', 'schedule'), value: 'schedule'">
	  										<label class="form-check-label" for="roundtrip_return_leg_schedule_type_datetime" style="font-size:14px"><?php echo xlt("Choose date and time"); ?></label>
  										</div>
					    			</div>
					    		</div>
					    		<!-- Multiple error messages -->
						        <div class="invalid-feedback" data-bind="foreach: errors.roundtrip.return_leg.scheduleType">
						          <div data-bind="text: $data"></div>
						        </div>

						        <div class="mt-2" data-bind="if: isFlexible.bind($data, 'roundtrip.return_leg')()">
					    			<div class="form-group">
									    <label><?php echo xlt("Choose when they'll ride"); ?></label>
									    <div class="form-row">
									    	<div class="col-7">
									    		<input type="text" class="form-control datepicker future_ride_date" name="roundtrip[return_leg][flexible_ride_date]" id="roundtrip_return_leg_flexible_date" placeholder="Date" data-bind="value: roundtrip.return_leg.flexibleRideDate, css: { 'is-invalid': errors.roundtrip.return_leg.flexibleRideDate().length > 0 }">

									    		<!-- Multiple error messages -->
										        <div class="invalid-feedback" data-bind="foreach: errors.roundtrip.return_leg.flexibleRideDate">
										          <div data-bind="text: $data"></div>
										        </div>

									    	</div>
									    </div>
									</div>
					    		</div>

					    		<div class="mt-2" data-bind="if: isScheduleTrip.bind($data, 'roundtrip.return_leg')()">
							    	<div data-bind="if: isFutureTrip.bind($data, 'roundtrip.return_leg')()">
							    		<div class="form-group">
										    <label><?php echo xlt("Choose when they'll ride"); ?></label>
										    <div class="form-row">
										    	<div class="col-7">
										    		<input type="text" class="form-control datepicker future_ride_date" name="roundtrip[return_leg][future_ride_date]" id="roundtrip_returnleg_future_ride_date" placeholder="Date" data-bind="value: roundtrip.return_leg.futureRideDate, css: { 'is-invalid': errors.roundtrip.return_leg.futureRideDate().length > 0 }">

										    		<!-- Multiple error messages -->
											        <div class="invalid-feedback" data-bind="foreach: errors.roundtrip.return_leg.futureRideDate">
											          <div data-bind="text: $data"></div>
											        </div>

										    	</div>
										    	<div class="col-5">
										    		<input type="text" class="form-control datepicker future_ride_time" name="roundtrip[return_leg][future_ride_time]" id="roundtrip_returnleg_future_ride_time" placeholder="Date" data-bind="value: roundtrip.return_leg.futureRideTime, css: { 'is-invalid': errors.roundtrip.return_leg.futureRideTime().length > 0 }">

										    		<!-- Multiple error messages -->
											        <div class="invalid-feedback" data-bind="foreach: errors.roundtrip.return_leg.futureRideTime">
											          <div data-bind="text: $data"></div>
											        </div>
										    	</div>
										    </div>
										</div>
							    	</div>
						    	</div>

						    	<div class="form-group">
								    <label><?php echo xlt("Message to driver"); ?></label>
								    <textarea type="text" class="form-control" name="roundtrip[return_leg][message_to_driver]" id="roundtrip_returnleg_message_to_driver" placeholder="Enter message" data-bind="value: roundtrip.return_leg.messageToDriver"></textarea>
								</div>

							</div>
							</div>

							<div class="card mb-3">
							  	<div class="card-header"><?php echo xlt('Vehicle type'); ?></div>
							  	<div class="card-body px-3 py-3">
							  		<div class="form-group">
									    <label><?php echo xlt("First leg vehicle type"); ?></label>
									    
									    <div class="input-group" data-bind="css: { 'is-invalid': errors.roundtrip.first_leg.vehicleType().length > 0 }">
									    <select class="form-control sel2" name="roundtrip[first_leg][vehicle_type]" id="roundtrip_first_leg_vehicletype" data-bind="options: getVehicleTypeOptions.bind($data, 'roundtrip.first_leg')(), value: roundtrip.first_leg.vehicleType, optionsText: 'name', optionsValue: 'id', css: { 'is-invalid': errors.roundtrip.first_leg.vehicleType().length > 0 }">
									    </select>
									    <div class="input-group-append">
									    	<button type="button" class="btn btn btn-primary" data-bind="click: getTripsEstimates.bind($data, 'roundtrip.first_leg', function() { uberView.validateForm(); })"><i class="fa fa-refresh" aria-hidden="true"></i></button>
									  	</div>
										</div>

									    <!-- Multiple error messages -->
								        <div class="invalid-feedback" data-bind="foreach: errors.roundtrip.first_leg.vehicleType">
								          <div data-bind="text: $data"></div>
								        </div>
									</div>

							  		<div class="form-group">
									    <label><?php echo xlt("Return leg vehicle type"); ?></label>
									    
									    <div class="input-group" data-bind="css: { 'is-invalid': errors.roundtrip.return_leg.vehicleType().length > 0 }">
									    <select class="form-control sel2" name="roundtrip[return_leg][vehicle_type]" id="roundtrip_return_leg_vehicletype" data-bind="options: getVehicleTypeOptions.bind($data, 'roundtrip.return_leg')(), value: roundtrip.return_leg.vehicleType, optionsText: 'name', optionsValue: 'id', css: { 'is-invalid': errors.roundtrip.return_leg.vehicleType().length > 0 }">
									    </select>
									    <div class="input-group-append">
									    	<button type="button" class="btn btn btn-primary" data-bind="click: getTripsEstimates.bind($data, 'roundtrip.return_leg', function() { uberView.validateForm(); })"><i class="fa fa-refresh" aria-hidden="true"></i></button>
									  	</div>
										</div>

									    <!-- Multiple error messages -->
								        <div class="invalid-feedback" data-bind="foreach: errors.roundtrip.return_leg.vehicleType">
								          <div data-bind="text: $data"></div>
								        </div>
									</div>

							  	</div>
							</div>

					  	</div>
				  	</div>
		    	</div>

		    	<div data-bind="if: isOneWayTrip">
		    	<div class="card mb-3">
				  	<div class="card-header"><?php echo xlt('Plan Ride'); ?></div>
				  	<div class="card-body px-3 py-3">
					    <div class="section1">
						  <div class="form-group mb-3">
						    <label><?php echo xlt('Pick-up address'); ?></label>
						    <div class="input-group" data-bind="css: { 'is-invalid': errors.oneway.startLocation().length > 0 }">
							  <input type="text" id="oneway-start-input" class="form-control" name="oneway[pickup_location_name]" placeholder="<?php echo xlt('Enter Location'); ?>" data-bind="value: oneway.startLocationName, css: { 'is-invalid': errors.oneway.startLocation().length > 0 }">
							  <div class="input-group-append">
							    <button type="button" class="btn btn btn-secondary" data-bind="click: clearLocationName.bind($data, 'oneway', 'start')"><i class="fa fa-times" aria-hidden="true"></i></button>
							  </div>
							</div>

							<!-- Multiple error messages -->
					        <div class="invalid-feedback" data-bind="foreach: errors.oneway.startLocation">
					          <div data-bind="text: $data"></div>
					        </div>

							<input type="hidden" name="oneway[pickup_location_lat]" data-bind="value: getLocationLat.bind($data, 'oneway.startLocation')()">
							<input type="hidden" name="oneway[pickup_location_lng]" data-bind="value: getLocationLng.bind($data, 'oneway.startLocation')()">
						  </div>
						  <div class="form-group mb-3">
						    <label><?php echo xlt('Drop-off address'); ?></label>
						    <div class="input-group" data-bind="css: { 'is-invalid': errors.oneway.endLocation().length > 0 }">
							  <input type="text" id="oneway-end-input" class="form-control" name="oneway[dropoff_location_name]" placeholder="<?php echo xlt('Enter Location'); ?>" data-bind="value: oneway.endLocationName, css: { 'is-invalid': errors.oneway.endLocation().length > 0 }">
							  <div class="input-group-append">
							    <button type="button" class="btn btn btn-secondary" data-bind="click: clearLocationName.bind($data, 'oneway', 'end')"><i class="fa fa-times" aria-hidden="true"></i></button>
							  </div>
							</div>

							<!-- Multiple error messages -->
					        <div class="invalid-feedback" data-bind="foreach: errors.oneway.endLocation">
					          <div data-bind="text: $data"></div>
					        </div>

							<input type="hidden" name="oneway[dropoff_location_lat]" data-bind="value: getLocationLat.bind($data, 'oneway.endLocation')()">
							<input type="hidden" name="oneway[dropoff_location_lng]" data-bind="value: getLocationLng.bind($data, 'oneway.endLocation')()">
						  </div>
						</div>

						<div class="mt-4">
							<h6><?php echo xlt('Select when to ride'); ?></h6>
							<hr/>
							<div class="mb-3">
					    		<div class="btn-group w-100" data-bind="css: { 'is-invalid': errors.oneway.whenToRide().length > 0 }" role="group">
								  <button type="button" data-bind="css: getFieldValue('oneway.whenToRide') === 'pickupnow' ? 'btn-primary' : 'btn-secondary', click: function() { updateWhenToTrip('pickupnow', 'oneway'); }" class="btn"><?php echo xlt('Pick up now'); ?></button>
								  <button type="button" data-bind="css: getFieldValue('oneway.whenToRide') === 'futuretrip' ? 'btn-primary' : 'btn-secondary', click: function() { updateWhenToTrip('futuretrip', 'oneway'); }" class="btn"><?php echo xlt('Future trip'); ?></button>
								</div>

								<!-- Multiple error messages -->
						        <div class="invalid-feedback" data-bind="foreach: errors.oneway.whenToRide">
						          <div data-bind="text: $data"></div>
						        </div>

								<input type="hidden" name="oneway[when_to_ride]" data-bind="value: oneway.whenToRide">
					    	</div>

					    	<div data-bind="if: isFutureTrip.bind($data, 'oneway')()">
					    		<div class="form-row" data-bind="css: { 'is-invalid': errors.oneway.scheduleType().length > 0 }">
					    			<div class="col-5">
					    				<div class="form-check form-check-inline">
						    				<input class="form-check-input" type="radio" name="oneway[schedule_type]" id="oneway_schedule_type_flexible" data-bind="attr: { checked : oneway.scheduleType() == 'flexible' ? 'checked' : null }, click: updateScheduleTypeTrip.bind($data,'oneway', 'flexible'), value: 'flexible'">
	  										<label class="form-check-label" for="oneway_schedule_type" style="font-size:14px"><?php echo xlt("Make it flexible"); ?></label>
  										</div>
					    			</div>
					    			<div class="col-7">
					    				<div class="form-check form-check-inline">
						    				<input class="form-check-input" type="radio" name="oneway[schedule_type]" id="oneway_schedule_type_datetime" data-bind="attr: { checked : oneway.scheduleType() == 'schedule' ? 'checked' : null }, click: updateScheduleTypeTrip.bind($data,'oneway', 'schedule'), value: 'schedule'">
	  										<label class="form-check-label" for="oneway_schedule_type_datetime" style="font-size:14px"><?php echo xlt("Choose date and time"); ?></label>
  										</div>
					    			</div>
					    		</div>
					    		<!-- Multiple error messages -->
						        <div class="invalid-feedback" data-bind="foreach: errors.oneway.scheduleType">
						          <div data-bind="text: $data"></div>
						        </div>

					    		<div class="mt-2" data-bind="if: isFlexible.bind($data, 'oneway')()">
					    			<div class="form-group">
									    <label><?php echo xlt("Choose when they'll ride"); ?></label>
									    <div class="form-row">
									    	<div class="col-7">
									    		<input type="text" class="form-control datepicker future_ride_date" name="oneway[flexible_ride_date]" id="oneway_flexible_date" placeholder="Date" data-bind="value: oneway.flexibleRideDate, css: { 'is-invalid': errors.oneway.flexibleRideDate().length > 0 }">

									    		<!-- Multiple error messages -->
										        <div class="invalid-feedback" data-bind="foreach: errors.oneway.flexibleRideDate">
										          <div data-bind="text: $data"></div>
										        </div>

									    	</div>
									    </div>
									</div>
					    		</div>
					    		<div class="mt-2" data-bind="if: isScheduleTrip.bind($data, 'oneway')()">
						    		<div class="form-group">
									    <label><?php echo xlt("Choose when they'll ride"); ?></label>
									    <div class="form-row">
									    	<div class="col-7">
									    		<input type="text" class="form-control datepicker future_ride_date" name="oneway[future_ride_date]" id="oneway_future_ride_date" placeholder="Date" data-bind="value: oneway.futureRideDate, css: { 'is-invalid': errors.oneway.futureRideDate().length > 0 }">

									    		<!-- Multiple error messages -->
										        <div class="invalid-feedback" data-bind="foreach: errors.oneway.futureRideDate">
										          <div data-bind="text: $data"></div>
										        </div>

									    	</div>
									    	<div class="col-5">
									    		<input type="text" class="form-control datepicker future_ride_time" name="oneway[future_ride_time]" id="oneway_future_ride_time" placeholder="Date" data-bind="value: oneway.futureRideTime, css: { 'is-invalid': errors.oneway.futureRideTime().length > 0 }">

									    		<!-- Multiple error messages -->
										        <div class="invalid-feedback" data-bind="foreach: errors.oneway.futureRideTime">
										          <div data-bind="text: $data"></div>
										        </div>
									    	</div>
									    </div>
									</div>
								</div>
					    	</div>

					    	<div class="form-group">
							    <label><?php echo xlt("Message to driver"); ?></label>
							    <textarea type="text" class="form-control" name="oneway[message_to_driver]" id="oneway_message_to_driver" placeholder="Enter message" data-bind="value: oneway.messageToDriver"></textarea>
							</div>

						</div>
					</div>
				</div>

				<div class="card mb-3">
				  	<div class="card-header"><?php echo xlt('Vehicle type'); ?></div>
				  	<div class="card-body px-3 py-3">
				  		<div class="form-group">
						    <label><?php echo xlt("Vehicle type"); ?></label>
						    
						    <div class="input-group" data-bind="css: { 'is-invalid': errors.oneway.vehicleType().length > 0 }">
						    <select class="form-control sel2" name="oneway[vehicle_type]" id="oneway_vehicletype" data-bind="options: getVehicleTypeOptions.bind($data, 'oneway')(), value: oneway.vehicleType, optionsText: 'name', optionsValue: 'id', css: { 'is-invalid': errors.oneway.vehicleType().length > 0 }">
						    </select>
						    <div class="input-group-append">
						    	<button type="button" class="btn btn btn-primary" data-bind="click: getTripsEstimates.bind($data, 'oneway', function() { uberView.validateForm(); })"><i class="fa fa-refresh" aria-hidden="true"></i></button>
						  	</div>
							</div>

						    <!-- Multiple error messages -->
					        <div class="invalid-feedback" data-bind="foreach: errors.oneway.vehicleType">
					          <div data-bind="text: $data"></div>
					        </div>
						</div>
				  	</div>
				</div>
				</div>

				<div class="form-group">
				    <button type="button" class="btn btn-primary" data-bind="click: submitBookTrip.bind($data, '<?php echo $request_mode ?? ""; ?>')"><?php echo xlt("Set up Trip"); ?></button>
				</div>
			</form>
		</div>
		<div class="mapDetailsContainer">
			<div class="mapContainer">
				<div id="map"></div>
			</div>
		</div>
	</div>

	<div data-bind="if: getIsLoading">
		<div class="page-loader-container">
			<div class="spinner-border" role="status">
			  <span class="sr-only">Loading...</span>
			</div>
		</div>
	</div>
</body>
</html>
