<?php

require_once(dirname(__FILE__, 7) . "/interface/globals.php");
require_once "$srcdir/patient.inc";
require_once "$srcdir/options.inc.php";
require_once('./trips_manager_columns.php');
//Included EXT_Message File
include_once("$srcdir/OemrAD/oemrad.globals.php");

use OpenEMR\Common\Csrf\CsrfUtils;
use OpenEMR\Core\Header;
use Vitalhealthcare\OpenEMR\Modules\Generic\Controller\UberController;
use OpenEMR\Common\Acl\AclMain;
use OpenEMR\OemrAd\MessagesLib;


$default_mode = isset($_REQUEST['default_mode']) && !empty($_REQUEST['default_mode']) ? true : false;
$form_pid = isset($_REQUEST['form_pid']) && !empty($_REQUEST['form_pid']) ? $_REQUEST['form_pid'] : "";
$trip_status = isset($_REQUEST['trip_status']) ? $_REQUEST['trip_status'] : UberController::TODAY_IN_PROCESSING_LABEL;
$trip_request_id = isset($_REQUEST['trip_request_id']) ? $_REQUEST['trip_request_id'] : "";
$appt_eid = isset($_REQUEST['eid']) ? $_REQUEST['eid'] : "";
$view_mode = isset($_REQUEST['view_mode']) && $_REQUEST['view_mode'] == "1" ? true : false;

$page_action = isset($_REQUEST['action']) ? $_REQUEST['action'] : '';
$draw = $_POST['draw'];
$row = $_POST['start'];
$rowperpage = $_POST['length']; // Rows display per page
$columnIndex = $_POST['order'][0]['column']; // Column index
$columnName = $_POST['columns'][$columnIndex]['data']; // Column name
$columnSortOrder = $_POST['order'][0]['dir']; // asc or desc
$searchValue = $_POST['search']['value']; // Search value

$filterVal = isset($_POST['filterVal']) ? $_POST['filterVal'] : array(); // Filter value
$colList = isset($_POST['columnList']) ? $_POST['columnList'] : array(); // Column List value

$searchArray = array();

$columnList = $upcomingColumnList;

//Filter Query Data
function generateFilterQuery($filterData = array()) {
	$filterQryList = array();
	$filterQry = "";

	if(!empty($filterData)) {

		if(isset($filterData['trip_request_id'])) {
			$explodeReq = explode(",", $filterData['trip_request_id']);
			if (!empty($explodeReq)) {
				$filterQryList[] = "vuht.request_id in ('" . implode("','", $explodeReq) . "')";
			}
		}

		if(!empty($filterData['eid'] ?? "")) {
			$filterQryList[] = "vuht.eid = " . $filterData['eid'] . " ";
		}

		if(isset($filterData['trip_status'])) {
			if($filterData['trip_status'] == UberController::TODAY_UPCOMING_LABEL) {
				$filterQryList[] = "DATE(vuht.trip_schedule_date) = DATE(NOW()) AND TIME(vuht.trip_schedule_time) > TIME(NOW()) ";
			} else if($filterData['trip_status'] == UberController::TODAY_COMPLETED_LABEL) {
				$filterQryList[] = "DATE(vuht.trip_schedule_date) = DATE(NOW()) AND TIME(vuht.trip_schedule_time) <= TIME(NOW()) AND vuht.trip_status != 'in_progress' ";
			} else if($filterData['trip_status'] == UberController::TODAY_IN_PROCESSING_LABEL) {
				$filterQryList[] = "DATE(vuht.trip_schedule_date) = DATE(NOW()) AND vuht.trip_status = 'in_progress' ";
			} else if($filterData['trip_status'] == UberController::FUTURE_ACTIVITY_LABEL) {
				$filterQryList[] = "DATE(vuht.trip_schedule_date) > DATE(NOW()) ";
			} else if($filterData['trip_status'] == UberController::PAST_ACTIVITY_LABEL) {
				$filterQryList[] = "DATE(vuht.trip_schedule_date) < DATE(NOW()) ";
			}
		}

		if((isset($filterData['trip_from_date']) && !empty($filterData['trip_from_date'])) && (isset($filterData['trip_to_date']) && !empty($filterData['trip_to_date']))) {
			$filterQryList[] = "DATE(vuht.trip_schedule_date) BETWEEN '" . date('Y-m-d', strtotime($filterData['trip_from_date'])) . "' AND '" . date('Y-m-d', strtotime($filterData['trip_to_date'])) . "'";
		}

		if(isset($filterData['form_pid']) && !empty($filterData['form_pid'])) {
			$filterQryList[] = "vuht.pid = " . $filterData['form_pid'];
		}
	}

	if(!empty($filterQryList)) {
		$filterQry = implode(" and ", $filterQryList);
	}

	return $filterQry;
}

//Generate Query
function generateSqlQuery($data = array(), $isSearch = false) {
	$select_qry = isset($data['select']) ? $data['select'] : "*";
	$where_qry = isset($data['where']) ? $data['where'] : "";
	$order_qry = isset($data['order']) ? $data['order'] : "vof.id"; 
	$order_type_qry = isset($data['order_type']) ? $data['order_type'] : "desc";

	$limit_qry = isset($data['limit']) && $data['limit'] > 0 ? $data['limit'] : "0"; 
	$offset_qry = isset($data['offset']) && $data['offset'] != "-1" ? $data['offset'] : "5";

	$sql = "SELECT $select_qry FROM vh_uber_health_trips vuht left join `users` u on u.id = vuht.user_id ";

	if($isSearch === false) {
	}

	$sql .= " WHERE vuht.trip_status != '' ";

	if(!empty($where_qry)) {
		$sql .= " AND " . $where_qry;
	}

	if(!empty($order_qry)) {
		$sql .= " ORDER BY $order_qry $order_type_qry";
	}

	if($limit_qry != '' && $offset_qry != '') {
		$sql .= " LIMIT $limit_qry , $offset_qry";
	}

	return $sql;
}

function formateTripDateTime($dateTimeStr = '') {
	$formattedDate = "";

	if (!empty($dateTimeStr)) {
		// Convert the timestamp to seconds by dividing by 1000
		$expiration_timestamp_s = $dateTimeStr / 1000;

		// Create a DateTime object from the timestamp
		$dateTime = new DateTime();
		$dateTime->setTimestamp($expiration_timestamp_s);

		// Format the time with the correct timezone offset
		$offset = $dateTime->getOffset() / 3600; // Convert seconds to hours
		$gmtOffset = "GMT" . ($offset >= 0 ? "+" : "") . $offset;

		// Get the day of the month with the suffix (e.g., "31st")
		$day = $dateTime->format('j');
		$daySuffix = 'th';

		if ($day == 1 || $day == 21 || $day == 31) {
		    $daySuffix = 'st';
		} elseif ($day == 2 || $day == 22) {
		    $daySuffix = 'nd';
		} elseif ($day == 3 || $day == 23) {
		    $daySuffix = 'rd';
		}

		// Format the final string with desired output
		$formattedDate = $dateTime->format('F') . " " . $day . $daySuffix . " - " . $dateTime->format('H:i') . " " . $gmtOffset;
	}

	return $formattedDate;
}

//Prepare Data Table Data
function prepareDataTableData($row_item = array(), $columns = array(), $rowDataSet = array()) {
	global $trip_status;

	foreach ($columns as $clk => $cItem) {
		if(isset($cItem['name'])) {
			if($cItem['name'] == "uber_ride_details") {
				$fieldHtml = "";

				$tripResponce = json_decode($row_item['trip_response'] ?? "", true);
				$guestDetails = $tripResponce['guest'] ?? array();

				if (empty($guestDetails)) {
					$guestDetails = array(
						"first_name" => $row_item['rider_first_name'] ?? "",
						"last_name" => $row_item['rider_last_name'] ?? "",
						"phone_number" => $row_item['rider_phone_number'] ?? ""
					);
				}

				$vehicleDetails = $tripResponce['vehicle'] ?? array();
				$driverDetails = $tripResponce['driver'] ?? array();
				$deferredRideOptions = $tripResponce['deferred_ride_options'] ?? array();
				$schedulingOptions = $tripResponce['scheduling'] ?? array();
				$productOptions = $tripResponce['product'] ?? array();
				$fareOption = $tripResponce['fare'] ?? array();

				$rider_full_name = ($guestDetails['first_name'] ?? "") . " " . ($guestDetails['last_name'] ?? "");
				$rider_phone_number = !empty($guestDetails['phone_number']) ? MessagesLib::getPhoneNumbers($guestDetails['phone_number']) : "";

				$currency_code = $tripResponce['currency_code'] ?? "";
				if (isset($fareOption['currency_code'])) {
					$currency_code = $fareOption['currency_code'];
				}

				$tripTypeText = "Pick-up now trip";
				if (!empty($deferredRideOptions)) {
					$tripTypeText = "Flexible trip";
				} else if (!empty($schedulingOptions)) {
					$tripTypeText = "Scheduled trip";
				}

				if (empty($tripResponce)) {
					$tripTypeText = "<span class='text-danger'>Trips details not available.</span>";
				}

				$tripStatusText = $tripResponce["status"] ?? "";
				$tripStatusTextClass = "";
				if (!empty($tripResponce["status"])) {
		    		if (isset(UberController::STATUS_DESCRIPTION[$tripResponce["status"]])) {
		    			$tripStatusText = ucfirst($tripResponce["status"]) . " - " . UberController::STATUS_DESCRIPTION[$tripResponce["status"]];
		    		}

		    		if (in_array($tripResponce["status"], array("completed"))) {
		    			$tripStatusTextClass = "text-success";
		    		} else if (in_array($tripResponce["status"], array("driver_canceled", "rider_canceled", "failed", "expired", "coordinator_canceled", "guest_rider_canceled"))) {
		    			$tripStatusTextClass = "text-danger";
		    		}
	    		}

				ob_start();
				?>
				<div>
					<div class="dsection1 mx-3 my-3">
						<div class="row">
						    <div class="col-6">
						        <div class="name_text">
						        	<?php if (isset($row_item['pid']) && !empty($row_item['pid'])) { ?>
						        	<a href="#!" class="linktext" onclick="goParentPid('<?php echo $row_item['pid']; ?>');"><?php echo $rider_full_name ?? ""; ?></a>
						        	<?php } else { ?>
						        	<span><?php echo $rider_full_name ?? ""; ?></span>
						        	<?php } ?>
						        </div>
						        <div class="phone_text text-secondary"><span><?php echo !empty($rider_phone_number) ? "+1 " . $rider_phone_number['pat_phone'] : " - " ?></span></div>
						    </div>
						    <div class="col-6">

						    	<?php 
						    	if (!empty($deferredRideOptions)) {
						    		$formattedDate = " - ";
						    		if (!empty($deferredRideOptions['expiration_time_m_s'])) {
						    			$formattedDate = "Expires " . formateTripDateTime($deferredRideOptions['expiration_time_m_s']);
									}
						    	?>
						    	<div class="flexible_section">
						    		<div class="text-secondary icon-text-container mb-1">
						    			<i class="fa fa-calendar icon-text" aria-hidden="true"></i>
						    			<span><?php echo isset($deferredRideOptions['pickup_day']) ? strftime("%A, %d %B %Y", strtotime($deferredRideOptions['pickup_day'])) : " - ";?></span>
						    		</div>
						    		<div class="text-secondary icon-text-container mb-1">
						    			<i class="fa fa-clock icon-text" aria-hidden="true"></i>
						    			<span><?php echo $formattedDate; ?></span>
						    		</div>
						    	</div>
						    	<?php } else if (!empty($schedulingOptions)) { ?>
						    	<div class="scheduling_section">
						    		<div class="text-secondary icon-text-container mb-1">
						    			<i class="fa fa-calendar icon-text" aria-hidden="true"></i>
						    			<span><?php echo isset($schedulingOptions['pickup_time']) ? strftime("%A, %d %B %Y", $schedulingOptions['pickup_time'] / 1000) : " - ";?></span>
						    		</div>
						    	</div>
						    	<?php } else if (!empty($tripResponce['request_time'])) { ?>
						    	<div class="requesttime_section">
						    		<div class="text-secondary icon-text-container mb-1">
						    			<i class="fa fa-calendar icon-text" aria-hidden="true"></i>
						    			<span><?php echo isset($tripResponce['request_time']) ? strftime("%A, %d %B %Y", $tripResponce['request_time'] / 1000) : " - ";?></span>
						    		</div>
						    	</div>
						    	<?php } else if (!empty($row_item['trip_schedule_date'])) { ?>
						    	<div class="requesttime_section">
						    		<div class="text-secondary icon-text-container mb-1">
						    			<i class="fa fa-calendar icon-text" aria-hidden="true"></i>
						    			<span><?php echo isset($row_item['trip_schedule_date']) ? strftime("%A, %d %B %Y", strtotime($row_item['trip_schedule_date'])) : " - ";?></span>
						    		</div>
						    	</div>
						    	<?php } ?>

						    	<?php 
						    	if (!empty($schedulingOptions['pickup_time'])) {
						    		$formattedDate = " - ";
						    		if (!empty($schedulingOptions['pickup_time'])) {
						    			$formattedDate = "Pick-up " . formateTripDateTime($schedulingOptions['pickup_time']);
									}
						    	?>
						    	<div class="text-secondary icon-text-container mb-1">
					    			<i class="fa fa-clock icon-text" aria-hidden="true"></i>
					    			<span><?php echo $formattedDate; ?></span>
					    		</div>
						    	<?php } ?>

						    	<?php 
						    	if (empty($tripResponce) && !empty($row_item['created_date'])) {
						    		$createdAtDate = " - ";
						    		if (!empty($row_item['created_date'])) {
						    			$createdAtDate = "Created at " . formateTripDateTime($row_item['created_date']);
									}
						    	?>
						    	<div class="text-secondary icon-text-container mb-1">
					    			<i class="fa fa-clock icon-text" aria-hidden="true"></i>
					    			<span><?php echo $createdAtDate; ?></span>
					    		</div>
						    	<?php } ?>

						    	<!-- Vehicle details -->
						    	<?php if (!empty($vehicleDetails)) { ?>
						    	<div class="vehicle_details">
						    		<div class="text-secondary icon-text-container mb-1">
						    			<i class="fa fa-car icon-text" aria-hidden="true"></i>
						    			<span><?php echo $vehicleDetails['license_plate'] ?? " - " ?></span>
						    		</div>
						    		<div class="text-secondary icon-text-container mb-1">
						    			<i class="fa fa-stop icon-text" aria-hidden="true"></i>
						    			<span><?php echo $vehicleDetails['model'] ?? " - " ?></span>
						    		</div>
						    	</div>
						    	<?php } ?>

						    	<!-- Trip type details -->
						    	<?php if (isset($tripResponce["total_trip_legs"]) && $tripResponce["total_trip_legs"] == "1") { ?> 
							    	<div class="trip_type text-secondary icon-text-container mb-1">
							    		<i class="fa fa-long-arrow-left icon-text" aria-hidden="true"></i>
							    		<span><?php echo xlt('Oneway Trip'); ?></span>
							    	</div>
						    	<?php } else if (isset($tripResponce["total_trip_legs"]) && $tripResponce["total_trip_legs"] >= "2") { ?>
						    		<div class="trip_type text-secondary icon-text-container mb-1">
							    		<i class="fa fa-retweet icon-text" aria-hidden="true"></i>
							    		<span><?php echo xlt('Round Trip'); ?></span>
							    	</div>
						    	<?php } ?>
						    </div>
						</div>
						<div class="row mt-3">
						    <div class="col-6 d-flex align-items-end">
						    	<div class="<?php echo $tripStatusTextClass; ?>">
						    	<h6 class="mb-0"><?php echo $tripTypeText; ?></h6>
						    	<?php if (!empty($tripStatusText)) { ?>
						    		<span class="text-sm font-italic" style="font-size:13px;"><?php echo $tripStatusText; ?></span>
						    	<?php } ?>
						    	</div>
						    </div>
						    <div class="col-3 d-flex align-items-end" style="gap: 6px;">
						    	<?php if (!empty($tripResponce)) { ?>
						    	<!-- <button class="btn btn-sm btn-secondary" onclick="updateUberPopupWindow('<?php //echo $row_item['request_id'] ?? ""; ?>')"><?php //echo xlt('Edit'); ?></button> -->
						    	<?php } ?>
						    	<?php if (($tripResponce['status']) == "scheduled") { ?>
						    	<button class="btn btn-sm btn-secondary" onclick="updateUberPopupWindow('<?php echo $row_item['request_id'] ?? ""; ?>')"><?php echo xlt('Edit'); ?></button>
						    	<button class="btn btn-sm btn-secondary" onclick="cancelTrip('<?php echo $row_item['request_id'] ?? ""; ?>')"><?php echo xlt('Cancel'); ?></button>
						    	<?php } ?>
						    	<button class="btn btn-sm btn-secondary" style="height:31px;" onclick="fetchTripDetails('<?php echo $row_item['request_id'] ?? ""; ?>')"><i class="fa fa-refresh" aria-hidden="true"></i></button>
						    </div>
						    <div class="col-3 d-flex align-items-end justify-content-end">
						    	<?php if (!empty($tripResponce)) { ?>
						    	<button class="btn btn-sm btn-secondary details-toggle-button float-right" style="height:31px;"><i class="fa fa-arrow-down" aria-hidden="true"></i></button>
						    	<?php } ?>
						    </div>
						</div>
					</div>

					<?php if (!empty($tripResponce)) { ?>
					<div class="full-details-section hide-section">
						<hr class="mt-3"/>
						<div class="mx-3 my-3">
							<div class="row">
								<div class="col-5">
									<div class="location-container m-3">
										<?php
										$tripPointData = array();
										if (isset($tripResponce["total_trip_legs"]) && $tripResponce["total_trip_legs"] > 1) {
											if ($tripResponce["trip_leg_number"] == "0") {
												$tripPointData[] = array(
													"point" => "start",
													"address" => $tripResponce["pickup"] ?? array()
												);
												$tripPointData[] = array(
													"point" => "middle",
													"address" => $tripResponce["destination"] ?? array()
												);
												$tripPointData[] = array(
													"point" => "end",
													"linked_trip" => $tripResponce["linked_trip_details"] ?? array()
												);
											} else if ($tripResponce["trip_leg_number"] == "1") {
												$tripPointData[] = array(
													"point" => "start",
													"linked_trip" => $tripResponce["linked_trip_details"] ?? array()
												);
												$tripPointData[] = array(
													"point" => "middle",
													"address" => $tripResponce["pickup"] ?? array()
												);
												$tripPointData[] = array(
													"point" => "end",
													"address" => $tripResponce["destination"] ?? array()
												);
											}
										} else {
											$tripPointData[] = array(
												"point" => "start",
												"address" => $tripResponce["pickup"] ?? array()
											);
											$tripPointData[] = array(
												"point" => "end",
												"address" => $tripResponce["destination"] ?? array()
											);
										}
										?>

									  	<div class="point-container <?php echo count($tripPointData) > 2 ? "moreitem" : ""; ?>">	
									  		<?php
									  		foreach ($tripPointData as $tripPointItem) {
									  			if (isset($tripPointItem['point'])) {
									  				if (isset($tripPointItem['address'])) {
									  					$classprefix = "circle";
									  					if ($tripPointItem['point'] == "end" || $tripPointItem['point'] == "middle") {
									  						$classprefix = "square";
									  					}

									  					?>
									  					<div class="<?php echo $tripPointItem['point']; ?> point-item">
												  			<div class="<?php echo $classprefix; ?>-container">
														  		<div class="inner-<?php echo $classprefix; ?>"></div>
															</div>
															<div>
																<h6 class="mb-0"><?php echo $tripPointItem['address']['title'] ?? ""; ?></h6>
																<span class="text-secondary"><?php echo $tripPointItem['address']['subtitle'] ?? ""; ?></span>
															</div>
												  		</div>
									  					<?php
									  				} else if (isset($tripPointItem['linked_trip'])) {
									  					$tripLinkTitle = xlt('View return trip');
									  					if ($tripResponce["trip_leg_number"] == "1") {
									  						$tripLinkTitle = xlt('View first trip');
									  					}

									  					$classprefix = "circle";
									  					if ($tripPointItem['point'] == "end" || $tripPointItem['point'] == "middle") {
									  						$classprefix = "square";
									  					}

									  					?>
									  					<div class="<?php echo $tripPointItem['point']; ?>  point-item">
												  			<div class="<?php echo $classprefix; ?>-container">
														  		<div class="inner-<?php echo $classprefix; ?>"></div>
															</div>
															<div>
																<a href="javascript:void(0);" class="trip_view_link" onclick="linkedTripDetails('<?php echo $tripPointItem['linked_trip']['request_id'] ?? ""; ?>')"><h6><?php echo $tripLinkTitle; ?></h6></a>
															</div>
												  		</div>
									  					<?php
									  				}
									  			}
									  		}
									  		?>
									  	</div>
									  	<div class="line-item">
								  			<div class="line bg-secondary"></div>
								  		</div>
									</div>
								</div>
								<div class="col-7">
								</div>
							</div>

							<div class="row mt-2">
								<div class="col-6">
									<?php if (!empty($productOptions)) { ?>
									<div class="product_type_section">
										<h5 class="product_text mb-0"><?php echo $productOptions['display_name'] ?? ""; ?></h5>
									</div>
									<?php } ?>

									<?php if (!empty($tripResponce['client_fare'] ?? "")) { ?>
									<div class="estimated_price_section">
										<h4 class="estimated_price_text mb-1"><b><?php echo $currency_code ." ". $tripResponce['client_fare']; ?></b></h4>
										<span class="text-secondary"><?php echo xlt('Estimated price'); ?></span>
									</div>
									<?php } ?>

									<?php if ($tripResponce['call_enabled'] === true) { ?>
									<div class="ac_section mt-4 mb-4">
										<i class="fa fa-check-circle" aria-hidden="true"></i>
										<span class="ac_text"><?php echo xlt('Automated calling enabled'); ?></span>
									</div>
									<?php } ?>
									<div class="note_section mt-4 mb-4">
										<span class="note_text"><b><?php echo xlt('Note to driver'); ?></b></span>
										<p class="note_value text-secondary"><?php echo !empty($tripResponce['note_for_driver'] ?? "") ? $tripResponce['note_for_driver'] : " - " ?></p>
									</div>

									<?php if (!empty($tripResponce['expense_code'] ?? "")) { ?>
									<div class="expense_details_section mt-4 mb-4">
										<span class="expense_details_text"><b><?php echo xlt('Expense details'); ?></b></span>
										<p class="expense_details_value text-secondary"><?php echo $tripResponce['expense_code']; ?></p>
									</div>
									<?php } ?>
								</div>
								<div class="col-6">
									<?php if (!empty($vehicleDetails)) { ?>
									<div class="vehicle_details_section mb-4">
										<?php if (!empty($driverDetails)) { ?>
										<span class="text-sm" style="font-weight:500;"><?php echo $driverDetails['name'] ?? ""; ?></span>
										<br/>
										<?php } ?>
										<span class="text-sm" style="font-weight:500;"><?php echo $vehicleDetails['model'] ?? ""; ?></span>
										<br/>
										<span class="text-sm text-secondary"><?php echo $vehicleDetails['license_plate'] ?? ""; ?></span>
									</div>
									<?php } ?>
									<div class="trip_details_section mb-4">
										<span class="trip_details_text"><b><?php echo xlt('Trip ID'); ?></b></span>
										<p class="trip_details_value text-secondary mb-2"><?php echo $tripResponce['request_id'] ?? ""; ?></p>

										
										<?php if (!empty($tripResponce['requester_name'] ?? "")) { ?>
										<span class="text-secondary">Requested by: <?php echo !empty($row_item['requester_name']) ? $row_item['requester_name'] : $tripResponce['requester_name']; ?></span>
										<br/>
										<?php } ?>

										<?php if (!empty($tripResponce['expense_memo'] ?? "")) { ?>
										<span class="text-secondary">Expense memo: <?php echo !empty($row_item['expense_memo']) ? $row_item['expense_memo'] : ""; ?></span>
										<br/>
										<?php } ?>

										<?php
										if (!empty($schedulingOptions['pickup_time'])) {
								    		if (!empty($schedulingOptions['pickup_time'])) {
								    			?>
								    			<span class="text-secondary">Scheduled time: <?php echo formateTripDateTime($schedulingOptions['pickup_time']); ?> </span>
								    			<?php
											}
										}

										if (!empty($tripResponce['rider_tracking_u_r_l'] ?? "")) {
											?>
											</br>
											<span class="text-secondary">Rider tracking url: <a href="https://trip.uber.com/J6ngWazJg9w" target="_blank" onClick="window.open(this.href, '_blank', 'width=800,height=600,resizable=yes,scrollbars=yes'); return false;"><?php echo $tripResponce['rider_tracking_u_r_l'] ?? ""; ?></a> </span>
											<?php
										}
										?>
									</div>
								</div>
							</div>
						</div>
					</div>
					<?php } ?>
				</div>
				<?php
				$fieldHtml = ob_get_clean();

				$rowData[$cItem['name']] = getHtmlString($fieldHtml);
				continue;
			}
			
			$rowData[$cItem['name']] = (isset($row_item[$cItem['name']]) && !empty($row_item[$cItem['name']])) ? getHtmlString($row_item[$cItem['name']]) : "";
		}
	}

	return $rowData;
}

//Get DataTable Data
function getDataTableData($data = array(), $columns = array(), $filterVal = array()) {
	extract($data);

	//Filter Value
	$filterQuery .= generateFilterQuery($filterVal);

	if(!empty($filterQuery)) {
		$searchQuery .= " " . $filterQuery;
	}

	$bindArray = array();

	// $records = sqlQuery(generateSqlQuery(array(
	// 	"select" => "COUNT(vfdl.id) AS allcount",
	// 	"filter_data" => array()
	// ), true));

	// $totalRecords = $records['allcount'];

	// $records = sqlQuery(generateSqlQuery(array(
	// 	"select" => "COUNT(vfdl.id) AS allcount",
	// 	"where" => $searchQuery,
	// 	"filter_data" => $filterVal
	// ), true));

	// $totalRecordwithFilter  = $records['allcount'];

	$result = sqlStatement(generateSqlQuery(array(
		"select" => "vuht.*, CONCAT(IFNULL(SUBSTR(u.`fname`,1,1),''), '. ', u.`lname`) AS 'requester_name' ",
		"where" => $searchQuery,
		"order" => $columnName,
		"order_type" => $columnSortOrder,
		"limit" => $row,
		"offset" => $rowperpage
	)));

	$dataSet = array();
	$rowItems = array();
	$itemsIdList = array();

	while ($row_item = sqlFetchArray($result)) {
		$dataSet[] = prepareDataTableData($row_item, $columns);
	}

	return array(
		"data" => $dataSet
	);
}

if(!empty($page_action)) {
	if($page_action == "fetch_data") {
		$response_data = array();
		$datatableDataSet = getDataTableData(array(
			'searchValue' => $searchValue,
			'columnName' => $columnName,
			'columnSortOrder' => $columnSortOrder,
			'row' => $row,
			'rowperpage' => $rowperpage
		), $colList, $filterVal);

		$response_data = array(
			"draw" => intval($draw),
		  	"recordsTotal" => $datatableDataSet['recordsTotal'],
		  	"recordsFiltered" => $datatableDataSet['recordsFiltered'],
		  	"data" => $datatableDataSet['data']
		);

		echo json_encode($response_data);
		exit();
	} else if ($page_action == "fetch_trip_details") {
		$response = array();

		try {
			$trip_request_id = $_REQUEST['request_id'] ?? "";

			if (empty($trip_request_id)) {
				throw new \Exception("Empty request_id");
			}

			// Create uber controller
			$ubController = new UberController();
			$preparedDataForLog = $ubController->updateTripStatus($trip_request_id);

			$response = array(
				"data" => $preparedDataForLog,
				"message" => "Refreshed trip details. \nTrip Request Id: " . $preparedDataForLog['request_id'] . "\nTrip Status: " . $preparedDataForLog['trip_status']
			);

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
	} else if ($page_action == "cancel_trip") {
		$response = array();

		try {
			$trip_request_id = $_REQUEST['request_id'] ?? "";

			// Create uber controller
			$ubController = new UberController();

			$response = $ubController->handelCancelHealthTrip($trip_request_id);

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
	}
}

?>
<html>
<head>
    <title><?php echo xlt('Uber Dashboard'); ?></title>

    <link rel="stylesheet" href="<?php echo $css_header; ?>" type="text/css">

	<?php Header::setupHeader(['common', 'opener', 'jquery', 'jquery-ui', 'jquery-ui-base', 'datetime-picker', 'datatables', 'datatables-bs']); ?>

    <style type="text/css">
    	#page_report {
    		width: 100% !important;
    		border-collapse: collapse !important;
    	}

    	table.table-bordered.dataTable.table-bordered.tbordered tbody tr > td,
    	table.table-bordered.dataTable.table-bordered.tbordered thead tr > th {
    		border-width: 0px !important;
    		border-bottom-width: 1px !important;
			padding: 0.8rem !important;
    	}

    	table.table-bordered.dataTable.table-bordered.tbordered tbody tr > td {
    		padding: 0px !important;
    	}

    	table.table-bordered.dataTable.table-bordered.tbordered tbody tr > td.dataTables_empty {
    		padding: 10px !important;
    	}

    	.btn-actions {
    		float: right;
    	}

    	.filter-container {
    		display: grid;
    		grid-template-columns: auto auto 1fr;
    		grid-gap: 10px;
    	}

    	.removepagination .pagination .paginate_button.page-item {
		    display: none !important;
		}


    	.custom-page-item.disabled .page-link {
		    color: #6c757d;
		    pointer-events: none;
		    cursor: auto;
		    background-color: #fff;
		    border-color: #dee2e6;
		}

		.arrow_btn {
			border-radius: 66px !important;
		    padding: 7px 9px;
		    float: right;
		}

		.icon-text-container {
			display: grid;
		    grid-template-columns: 15px 1fr;
		    grid-gap: 12px;
		    align-items: baseline;
		}

		.icon-text-container .icon-text {
			font-size: 13px;
    		align-content: center;
		}

		.full-details-section.hide-section {
			display: none;
		}

    	.view_mode .main-container,
    	.view_mode #page_report_container {
    		margin: 0px !important;
    	}
    </style>

    <style type="text/css">
		.location-container {
			display: grid;
		    position: relative;
		}

		.location-container .point-container {
		    height: 180px;
		    display: grid;
		    grid-template-rows: 1fr auto auto;
		    align-items: start;
		    /*justify-items: center;
		    width: 50px;*/
		}

		.location-container .point-container.moreitem {
			grid-template-rows: 1fr 1fr auto auto;
			height: 240px !important;
		}

		.location-container .point-item {
			display: grid;
			grid-template-columns: 25px 1fr;
			align-self: start;
			z-index: 100;
			grid-gap: 20px;
		}

		.location-container .start {
		}

		.location-container .middle {
		}

		.location-container .end {
			height: 100%;
			background-color: #fff;
		}

		.location-container .line-item {
			display: grid;
			width: 25px;
			justify-items: center;
		}

		.location-container .line {
			position: absolute;
		    width: 2px;
		    height: 100%;
		    top: 0;
		}

		.circle-container {
		  width: 20px;
		  height: 20px;
		  border-radius: 50%; /* Makes it round */
		  background-color: black; /* Outer circle color */
		  display: flex;
		  justify-content: center; /* Center the inner circle */
		  align-items: center; /* Center the inner circle */
		  z-index: 100;
		  justify-self: center;
		}

		.inner-circle {
		  width: 30%;
		  height: 30%;
		  border-radius: 100%; /* Makes it round */
		  background-color: white; /* Inner circle color */
		}

		.square-container {
		  width: 20px;
		  height: 20px;
		  border-radius: 0px; /* Makes it round */
		  background-color: black; /* Outer circle color */
		  display: flex;
		  justify-content: center; /* Center the inner circle */
		  align-items: center; /* Center the inner circle */
		  z-index: 100;
		  justify-self: center;
		}

		.inner-square {
		  width: 30%;
		  height: 30%;
		  border-radius: 0px; /* Makes it round */
		  background-color: white; /* Inner circle color */
		}
	</style>

    <script type='text/javascript'>
    	<?php include($GLOBALS['srcdir'].'/wmt-v2/report_tools.inc.js'); ?>
    	
        function setsendtoken() {
        	window.dataTable.draw();
        }

        // This invokes the find-patient popup.
		function sel_patient() {
		    let title = '<?php echo xlt('Patient Search'); ?>';
		    dlgopen('<?php echo $GLOBALS['webroot']; ?>/interface/main/calendar/find_patient_popup.php', 'findPatient', 650, 300, '', title);
		}

		function setpatient(pid, lname, fname, dob = '', alert_info = '', p_data = {}) {
		    // OEMRAD - nickNameVal changes.
		    var nickNameVal = (p_data.hasOwnProperty('nickname33') && p_data['nickname33'] != "" && p_data['nickname33'] != null) ? ' "'+p_data['nickname33']+'" ' : '';

		    var f = document.forms[0];
		    // OEMRAD - Added nickname value to patient name.
		    f.form_patient.value = lname + ', ' + fname  + nickNameVal;
		    f.form_pid.value = pid;
		}

		function linkedTripDetails(trip_request_id = "") {
			dlgopen('<?php echo $GLOBALS['webroot']; ?>/interface/modules/custom_modules/oe-module-vitalhealthcare-generic/interface/uber/trips_manager.php?view_mode=1&trip_request_id=' + trip_request_id, 'linkedTripWindow', 'modal-md', '500', false, 'Trip Details', {
		        onClosed: ''
		    });
		}
    	
    </script>

    <script type="text/javascript">
		$(document).ready(function(){
			$('.date_field').datetimepicker({
				<?php $datetimepicker_timepicker = false; ?>
				<?php $datetimepicker_showseconds = false; ?>
				<?php $datetimepicker_formatInput = true; ?>
				<?php require($GLOBALS['srcdir'] . '/js/xl/jquery-datetimepicker-2-5-4.js.php'); ?>
			});

			$('input[name="form_patient"]').change(function() {
				if ($(this).val() == "") {
					var f = document.forms[0];
					f.form_pid.value = "";
				}
			});
		});
	</script>
</head>
<body>
	<div class="<?php echo $view_mode === false ? "container mt-3" : "container-flex ml-1 mr-1 view_mode"; ?>">
		<?php if ($view_mode === false) { ?>
		<h2><?php echo xlt('Uber Dashboard'); ?></h2>
		<?php } ?>
		<div class="main-container mt-4">
			<div class="datatable_filter">
				<form id="page_report_filter">
					<input type='hidden' name='form_pid' value='<?php echo $form_pid; ?>' />
					<input type="hidden" name="trip_request_id" value="<?php echo $trip_request_id; ?>">
					<input type="hidden" name="eid" value="<?php echo $appt_eid; ?>">
					<?php if ($view_mode === false) { ?>
					<div class="filter-container">
						<div class="btn-group btn-group-toggle" data-toggle="buttons">
						  <label class="btn btn-primary ">
						  	<?php if($default_mode === true) { ?>
						  	<input type="hidden" name="default_mode" value="<?php echo $default_mode; ?>">
						  	<?php } ?>
						    <input type="radio" name="trip_status" class="trip_status" value="<?php echo UberController::TODAY_IN_PROCESSING_LABEL; ?>" autocomplete="off" <?php echo $trip_status == UberController::TODAY_IN_PROCESSING_LABEL ? "checked" : ""; ?>> <?php echo xlt('In Progress'); ?>
						  </label>
						  <label class="btn btn-primary">
						    <input type="radio" name="trip_status" class="trip_status" value="<?php echo UberController::TODAY_UPCOMING_LABEL; ?>" autocomplete="off" <?php echo $trip_status == UberController::TODAY_UPCOMING_LABEL ? "checked" : ""; ?>> <?php echo xlt('Upcoming'); ?>
						  </label>
						  <label class="btn btn-primary">
						    <input type="radio" name="trip_status" class="trip_status" value="<?php echo UberController::TODAY_COMPLETED_LABEL; ?>" autocomplete="off" <?php echo $trip_status == UberController::TODAY_COMPLETED_LABEL ? "checked" : ""; ?>> <?php echo xlt('Completed'); ?>
						  </label>
						</div>

						<div class="btn-group btn-group-toggle" data-toggle="buttons">
						  <label class="btn btn-primary">
						    <input type="radio" name="trip_status" class="trip_status" value="<?php echo UberController::FUTURE_ACTIVITY_LABEL; ?>" autocomplete="off" <?php echo $trip_status == UberController::FUTURE_ACTIVITY_LABEL ? "checked" : ""; ?>> <?php echo xlt('Future Activity'); ?>
						  </label>
						  <label class="btn btn-primary">
						    <input type="radio" name="trip_status" class="trip_status" value="<?php echo UberController::PAST_ACTIVITY_LABEL; ?>" autocomplete="off" <?php echo $trip_status == UberController::PAST_ACTIVITY_LABEL ? "checked" : ""; ?>> <?php echo xlt('Past Activity'); ?>
						  </label>
						</div>

						<div>
							<button type="button" class="btn btn-primary" onclick="uberPopupWindow()"><i class="fa fa-plus" aria-hidden="true"></i> <?php echo xlt('Setup trip'); ?></button>							
						</div>
					</div>

					<div class="form-row mt-4 align-items-center">	
					    <div class="col-4">
					      	<div class="form-group">
					      		<label><?php echo xlt('Patient'); ?></label>
					      		<input type="text" class="form-control" name="form_patient" placeholder="<?php echo xlt('Patient'); ?>" onclick='sel_patient()'>
					      	</div>
					    </div>
					    <?php if($default_mode === false) { ?>
					    <?php if (!in_array($trip_status, array(UberController::TODAY_IN_PROCESSING_LABEL, UberController::TODAY_UPCOMING_LABEL, UberController::TODAY_COMPLETED_LABEL))) { ?>
					    <div class="col-3">
					      	<div class="form-group">
					      		<label><?php echo xlt('From Date'); ?></label>
					      		<input type="text" class="form-control date_field" name="trip_from_date" placeholder="<?php echo xlt('Date From'); ?>" value="<?php echo date('m/d/Y', strtotime("-1 month")); ?>">
					      	</div>
					    </div>

					    <div class="col-3">
					      	<div class="form-group">
					      		<label><?php echo xlt('To Date'); ?></label>
					      		<input type="text" class="form-control date_field" name="trip_to_date" placeholder="<?php echo xlt('Date To'); ?>" value="<?php echo date('m/d/Y', strtotime("+1 month")); ?>">
					      	</div>
					    </div>
					    <?php } ?>
						<?php } ?>
					    <div class="col-auto" style="margin-top: 30px;">
					    	<div class="form-group">
					      		<button type="button" class="btn btn-primary" id="filter_submit" onclick="submitTable()" ><i class="fa fa-search" aria-hidden="true"></i> <?php echo xlt('Search'); ?></button>
					  		</div>
					    </div>
					</div>
					<?php } ?>
				</form>
			</div>

			<div id="page_report_container" class="datatable_container table-responsive mt-4 removepagination">
				<table id='page_report' class='table table-bordered border tbordered datatable_report table-sm'>
				  <thead class="thead-light">
				    <tr>
				      <?php
				      	foreach ($columnList as $clk => $cItem) {
				      		if($cItem["name"] == "dt_control") {
				      		?> <th></th> <?php
				      		} else {
				      		?> <th><?php echo $cItem["title"] ?></th> <?php
				      		}
				      	}
				      ?>
				    </tr>
				  </thead>
				</table>
			</div>
		</div>
	</div>

	<script type="text/javascript">
		function decodeHtmlString(text) {
		    var map = {
		        '&amp;': '&',
		        '&#038;': "&",
		        '&lt;': '<',
		        '&gt;': '>',
		        '&quot;': '"',
		        '&#039;': "'",
		        '&#8217;': "’",
		        '&#8216;': "‘",
		        '&#8211;': "–",
		        '&#8212;': "—",
		        '&#8230;': "…",
		        '&#8221;': '”'
		    };

		    if(text != "" && text != null) {
		    	text = text.replace(/\\(.)/mg, "$1");
		    	text = text.replace(/\&[\w\d\#]{2,5}\;/g, function(m) { return map[m]; });
		    	return text;
			}

			return text;
		};

		function format(d, columnList = []) {
			var defaultVal = '<i class="defaultValueText">Empty</i>';
			return '';
		}

		function initDataTable(id, ajax_url = '', data = {}, columnList = []) {
			var colummsData = JSON.parse(columnList);
			var columns = []; 
			colummsData.forEach((item, index) => {
				if(item["name"]) {
					var item_data = item["data"] ? item["data"] : {};

					if(item["name"] == "dt_control") { 
						columns.push({ 
							"data" : "",
							...item_data
						});
					} else {
						columns.push({ 
							"data" : item["name"],
							...item_data,
							"render" : function(data, type, row ) {
								var defaultVal = item_data['defaultValue'] ? decodeHtmlString(item_data['defaultValue']) : "";
								var colValue = decodeHtmlString(data);

								return (colValue && colValue != "") ? colValue : defaultVal;
							} 
						});
					}
				}
			});

			data["columnList"] = colummsData;

			if(id && id != "" && ajax_url != '' && data) {
				var dTable = $(id).DataTable({
						"processing": true,
				       	"serverSide": true,
				         "ajax":{
				             url: ajax_url, // json datasource
				             data: function(adata) {

				             		for (let key in data) {
				             			adata[key] = data[key];
				             		}

				             		//Append Filter Value
				             		adata['filterVal'] = getFilterValues(id + "_filter");
				             },
				             type: "POST",   // connection method (default: GET)
				             
				        },
				        "drawCallback": function (settings) {
					    	const currentPageInfo = this.api().page.info();
					    	
			                if(currentPageInfo.page > 0) {
			                	$('.paginate_button.custom-page-item.previous').removeClass("disabled");
			                } else {
			                	$('.paginate_button.custom-page-item.previous').addClass("disabled");
			                }

			                if(currentPageInfo.length == this.api().data().count()) {
			                	$('.paginate_button.custom-page-item.next').removeClass("disabled");
			                } else {
			                	$('.paginate_button.custom-page-item.next').addClass("disabled");
			                }
			            },
				        "columns": columns,
				        "columnDefs": [
					        { 
					        	"targets": '_all', 
					        	"render" : function ( data, type, row ) {
					        		return data;
				                },
				                
					        },
					    ],
				        "searching" : false,
				        "order": [[ 1, "desc" ]],
				        "iDisplayLength" : 10,
				        "deferLoading" : 0,
				        "info": false, // Disable showing information
			        	"pagingType" : "simple",
    					"paging": <?php echo $view_mode === false ? "true" : "false" ?>, // Enable pagination 
				});

				$(id).on('draw.dt', function () {
					//Expand Row Details
		            dTable.rows().every( function () {
		            	var tr = $(this.node());
		            	var row = dTable.row( tr );
		            	var childTrClass = tr.hasClass('even') ? 'even' : 'odd';
		            	//row.child(format(row.data()), 'no-padding row-details-tr p-3 mb-2 bg-light ').show();
			            tr.addClass('shown').trigger('classChange');
		            });

		            initDetailsToggle();
				});

				$(id).on( 'processing.dt', function ( e, settings, processing ) {
					if(processing === true) {
						$('#filter_submit').prop('disabled', true);
					} else if(processing === false) {
						$('#filter_submit').prop('disabled', false);
					}
				});

				$('<div class="dataTables_paginate"><ul class="pagination"><li class="paginate_button custom-page-item previous disabled" id="page_report_previousbtn"><a href="javascript:void(0);" class="page-link">Previous</a></li><li class="paginate_button custom-page-item next disabled" id="page_report_nextbtn"><a href="javascript:void(0);" class="page-link">Next</a></li></ul></div>').insertAfter('.dataTables_paginate');

				// Handle click event for the custom button
			    $('#page_report_nextbtn').on('click', function() {
			        const currentPageInfo = dTable.page.info();

			        if(currentPageInfo.hasOwnProperty('page')) {
			        	dTable.page(currentPageInfo.page + 1).draw( 'page' );
			    	}
			    });

			    // Handle click event for the custom button
			    $('#page_report_previousbtn').on('click', function() {
			        const currentPageInfo = dTable.page.info();

			        if(currentPageInfo.hasOwnProperty('page') && currentPageInfo.page > 0) {
			        	dTable.page(currentPageInfo.page - 1).draw( 'page' );
			    	}
			    });

				return dTable;
			}

			return false;
		}

		function getFilterValues(id = '') {
			var form_val_array = {};

			if(id != '') {
				var unindexed_array = $(id).serializeArray();
				var indexed_array = {};
			    $.map(unindexed_array, function(n, i){
			        indexed_array[n['name']] = n['value'];
			    });

			    $.map(indexed_array, function(ni, ii){
			    	if(ni != "") {
			    		form_val_array[ii] = ni;
			    	}
			    });
			}

			$.map(indexed_array, function(ni, ii){
		    	if(ni != "") {
		    		if(ii == "trip_from_date" && indexed_array["trip_to_date"] == "") {
		    			alert("Please select to date.");
		    			return false;
		    		}else if(ii == "trip_to_date" && indexed_array["trip_from_date"] == "") {
		    			alert("Please select from date.");
		    			return false;
		    		}

		    		form_val_array[ii] = ni;
		    	}
		    });

			return form_val_array;
		}

		function validateForm() {
			return true;
		}

		$(function () {
			var dataTableId = "#page_report";
			var dataTableFilterId = "#page_report_filter";

			//$('#filter_submit').prop('disabled', true);
			var dataTable = initDataTable(
				dataTableId, 
				'trips_manager.php', 
				{ action: 'fetch_data' },
				'<?php echo json_encode($columnList); ?>'
			);

			dataTable.draw();
			window.dataTable = dataTable;

			$('.trip_status').change(function() {
				let currentValue = $(this).val();
				//var f = document.forms[0];
				//f.submit();
				window.location.href = 'trips_manager.php?trip_status=' + currentValue
			});
		});

		function initDetailsToggle() {
			// Get all the rows with expandable functionality
        	const toggleButtons = document.querySelectorAll('.details-toggle-button');

        	toggleButtons.forEach((button) => {
            	button.addEventListener('click', () => {
            		const parentContainer = button.parentElement.parentElement.parentElement.parentElement;

            		if (parentContainer) {
            			const fullDetailSection = parentContainer.querySelector('.full-details-section');

            			if (fullDetailSection.classList.contains('hide-section')) {
            				// Remove the 'highlight' class
            				fullDetailSection.classList.remove('hide-section');
            				button.innerHTML = '<i class="fa fa-arrow-up" aria-hidden="true"></i>';
            			} else {
            				// Remove the 'highlight' class
            				fullDetailSection.classList.add('hide-section');
            				button.innerHTML = '<i class="fa fa-arrow-down" aria-hidden="true"></i>';
            			}
            		}
            	});
        	});
		}

		function submitTable() {
        	window.dataTable.draw();
        }

        function fetchTripDetails(request_id = "") {
        	if (request_id == "") {
        		alert("Something went wrong. Not valid request_id");
        		return false;
        	}

        	// Send the AJAX POST request
            $.ajax({
                url: 'trips_manager.php?action=fetch_trip_details&request_id=' + request_id, // The URL where you want to send the request
                type: 'POST',
                success: function (response) {
                	try {
	                	if (response != '') {
	                		let responseJson = JSON.parse(response);
	                		if (responseJson['message']) {
	                			alert(responseJson['message']);
	                		}

	                		// Fetch data again
	                		window.dataTable.draw();
	                	}
	                } catch (e) {
	                	alert("Something went wrong with the create trip request.");
	                }
                },
                error: function(xhr, status, error) {
                	try {
                        // Try to parse the JSON response from the server
                        var errorResponse = JSON.parse(xhr.responseText);
                        alert(errorResponse.message);
                    } catch (e) {
                        alert("Something went wrong with the create trip request.");
                    }
                }
            });

        }

        function cancelTrip(request_id = "") {
        	if (request_id == "") {
        		alert("Something went wrong. Not valid request_id");
        		return false;
        	}

        	// Send the AJAX POST request
            $.ajax({
                url: 'trips_manager.php?action=cancel_trip&request_id=' + request_id, // The URL where you want to send the request
                type: 'POST',
                success: function (response) {
                	try {
	                	if (response != '') {
	                		let responseJson = JSON.parse(response);
	                		if (responseJson['message']) {
	                			alert(responseJson['message']);
	                		}

	                		// Fetch data again
	                		window.dataTable.draw();
	                	}
	                } catch (e) {
	                	alert("Something went wrong with the create trip request.");
	                }
                },
                error: function(xhr, status, error) {
                	try {
                        // Try to parse the JSON response from the server
                        var errorResponse = JSON.parse(xhr.responseText);
                        alert(errorResponse.message);
                    } catch (e) {
                        alert("Something went wrong with the create trip request.");
                    }
                }
            });

        }

        // Uber popup window
	    function uberPopupWindow() {
	        var href = "uber_estimatetime.php";
	        dlgopen(href, 'ubertrippopup', 'modal-lg', '800', '', '<?php echo xlt('Uber'); ?>');
	    }

	    // Update Uber popup window
	    function updateUberPopupWindow(request_id = "") {
	        var href = "uber_estimatetime.php?trip_request_id=" + request_id + "&request_mode=update";
	        dlgopen(href, 'ubertrippopup', 'modal-lg', '800', '', '<?php echo xlt('Edit Uber'); ?>');
	    }
	</script>
</body>
</html>