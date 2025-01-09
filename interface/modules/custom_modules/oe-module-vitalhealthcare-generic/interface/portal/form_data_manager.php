<?php

require_once(dirname(__FILE__, 7) . "/interface/globals.php");
require_once "$srcdir/patient.inc";
require_once "$srcdir/options.inc.php";
require_once('./form_data_manager_columns.php');

use OpenEMR\Common\Csrf\CsrfUtils;
use OpenEMR\Core\Header;
use Vitalhealthcare\OpenEMR\Modules\Generic\Controller\FormController;
use OpenEMR\Common\Acl\AclMain;

$default_mode = isset($_REQUEST['default_mode']) && !empty($_REQUEST['default_mode']) ? true : false;
$form_pid = isset($_REQUEST['form_pid']) && !empty($_REQUEST['form_pid']) ? $_REQUEST['form_pid'] : "";
$form_mode = isset($_REQUEST['form_mode']) ? $_REQUEST['form_mode'] : FormController::PENDING_LABEL;

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

if($form_mode == "pending") {
	$columnList = $pendingColumnList;
} else if($form_mode == "received") {
	$columnList = $receivedColumnList;
} else if($form_mode == "reviewed") {
	$columnList = $reviewedColumnList;
}

//Filter Query Data
function generateFilterQuery($filterData = array()) {
	$filterQryList = array();
	$filterQry = "";

	if(!empty($filterData)) {
		if(isset($filterData['form_mode'])) {
			if($filterData['form_mode'] == "pending") {
				$filterQryList[] = "vof.status in ('" . FormController::PENDING_LABEL . "', '" . FormController::SAVE_LABEL . "') AND FROM_UNIXTIME(oa.expires) >= NOW() ";
			} else if($filterData['form_mode'] == "received") {
				$filterQryList[] = "vof.status in ('" . FormController::SUBMIT_LABEL . "')";
			} else if($filterData['form_mode'] == "reviewed") {
				//$filterQryList[] = "vof.status in ('" . FormController::REVIEW_LABEL . "','" . FormController::REJECT_LABEL . "') ";
			}
		}


		if((isset($filterData['form_from_date']) && !empty($filterData['form_from_date'])) && (isset($filterData['form_to_date']) && !empty($filterData['form_to_date']))) {
			if($filterData['form_mode'] == "pending") {
				$filterQryList[] = "DATE(vof.created_date) BETWEEN '" . date('Y-m-d', strtotime($filterData['form_from_date'])) . "' AND '" . date('Y-m-d', strtotime($filterData['form_to_date'])) . "'";
			} else if($filterData['form_mode'] == "received") {
				$filterQryList[] = "DATE(vof.received_date) BETWEEN '" . date('Y-m-d', strtotime($filterData['form_from_date'])) . "' AND '" . date('Y-m-d', strtotime($filterData['form_to_date'])) . "'";
			} else if($filterData['form_mode'] == "reviewed") {
				//$filterQryList[] = "DATE(vof.reviewed_date) BETWEEN '" . date('Y-m-d', strtotime($filterData['form_from_date'])) . "' AND '" . date('Y-m-d', strtotime($filterData['form_to_date'])) . "'";
			}
		}

		if($filterData['form_mode'] == "reviewed") {
			if((isset($filterData['form_from_date']) && !empty($filterData['form_from_date'])) && (isset($filterData['form_to_date']) && !empty($filterData['form_to_date']))) {
				$filterQryList[] = " (( vof.status in ('" . FormController::REVIEW_LABEL . "','" . FormController::REJECT_LABEL . "') AND DATE(vof.reviewed_date) BETWEEN '" . date('Y-m-d', strtotime($filterData['form_from_date'])) . "' AND '" . date('Y-m-d', strtotime($filterData['form_to_date'])) . "' ) OR ( DATE(vof.created_date) BETWEEN '" . date('Y-m-d', strtotime($filterData['form_from_date'])) . "' AND '" . date('Y-m-d', strtotime($filterData['form_to_date'])) . "' AND vof.status in ('" . FormController::PENDING_LABEL . "', '" . FormController::SAVE_LABEL . "') AND FROM_UNIXTIME(oa.expires) < NOW())) ";
			}
		}

		if(isset($filterData['form_pid']) && !empty($filterData['form_pid'])) {
			$filterQryList[] = "vof.pid = " . $filterData['form_pid'];
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

	$sql = "SELECT $select_qry FROM vh_onsite_forms vof JOIN  vh_form_data_log vfdl ON vof.ref_id  = vfdl.id JOIN vh_onetimetoken_form_log vofl ON vofl.ref_id = vof.ref_id JOIN onetime_auth oa ON vofl.onetime_token_id = oa.id ";

	if($isSearch === false) {
	}

	$sql .= " WHERE vof.status != '' and vof.deleted = 0 ";

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

//Prepare Data Table Data
function prepareDataTableData($row_item = array(), $columns = array(), $rowDataSet = array()) {
	global $form_mode;

	$rowData = array();
	$apptTypeList = array();

	$patientForm = new FormController();

	if($row_item['form_type'] == FormController::FORM_LABEL) {
		$formTemplate = $patientForm->getFormTemplates($row_item['form_id']);

		$row_item['template_name'] = $formTemplate[0]['template_name'];
		$row_item['template_status'] = $formTemplate[0]['status'];
	} else if($row_item['form_type'] == FormController::PACKET_LABEL) {
		$formTemplate = $patientForm->getPacketTemplates($row_item['form_id'], '', false);

		$row_item['template_name'] = $formTemplate[0]['name'];
		$row_item['template_status'] = $formTemplate[0]['status'];
	}

	foreach ($columns as $clk => $cItem) {
		if(isset($cItem['name'])) {
			if($cItem['name'] == "patient_name") {
				$fieldHtml = "<a href=\"#!\" class='linktext $req_class' onclick=\"goParentPid('".$row_item['pid']."');\">". $row_item[$cItem['name']] . "</a>";
				$rowData[$cItem['name']] = (isset($row_item[$cItem['name']]) && !empty($row_item[$cItem['name']])) ? getHtmlString($fieldHtml) : "-";
				continue;
			} else if($cItem['name'] == "template_name") {
				$formTypeStr = isset($row_item['form_type']) ? " (" . strtoupper($row_item['form_type']) . ") " : "";
				if($row_item['form_type'] == FormController::PACKET_LABEL) {
					$formTypeStr .= " - " . $row_item['id'];

					$fieldHtml = "<a href=\"#!\" class='linktext $req_class' onclick=\"renderForm('".$row_item['id']."', '". $row_item['pid'] ."', 'packet');\">". $row_item[$cItem['name']] . $formTypeStr . "</a>";
				} else if($row_item['form_type'] == FormController::FORM_LABEL) {
					$fieldHtml = "<a href=\"#!\" class='linktext $req_class' onclick=\"renderForm('".$row_item['item_id']."', '". $row_item['pid'] ."', 'form');\">". $row_item[$cItem['name']] . $formTypeStr . "</a>";
				}

				$rowData[$cItem['name']] = (isset($row_item[$cItem['name']]) && !empty($row_item[$cItem['name']])) ? getHtmlString($fieldHtml) : "-";
				continue;
			} else if($cItem['name'] == "status") {
				if(in_array($row_item['status'], array(FormController::PENDING_LABEL, FormController::SAVE_LABEL)) && $row_item['isExpire'] === "1") {
					$fieldHtml = strtoupper(FormController::EXPIRE_LABEL);
				} else {
					$fieldHtml = strtoupper($row_item[$cItem['name']]);
				}

				if(FormController::REJECT_LABEL == $row_item['status']) {
					$fieldHtml = "<a href=\"#!\" ><span data-toggle='tooltip1' class='tooltip_text' title='" . $row_item['denial_reason'] . "'>" . getHtmlString($fieldHtml) . "</span></a>";
				}

				//if(in_array($row_item['status'], array(FormController::PENDING_LABEL, FormController::SAVE_LABEL))) {
					
					$reminderLogItems = $patientForm->getReminderlog($row_item['id']);
					$reminderLogHtml = "";
					foreach ($reminderLogItems as $logItem) {
						$reminderLogHtml .= "<tr><td>" . $logItem['type'] . "</td><td>" . $logItem['status'] . "</td><td>" . $logItem['datetime'] . "</td></tr>";
					}

					if(!empty($reminderLogHtml)) { 
						$reminderLogHtml = "<table class='table-sm table-bordered text'><thead><tr><th>Type</th><th>Status</th><th>Date</th></tr></thead><tbody>" . $reminderLogHtml . "</tbody></table>";
					} else {
						$reminderLogHtml = "";
					}

					$createdBy = "";
					if(isset($row_item['form_created_by']) && !empty($row_item['form_created_by'])) {
						if(is_numeric($row_item['form_created_by'])) {
							if($row_item['form_created_by'] > 0) {
								$createdBy = "USER";
								$userData = sqlQuery("select u.*, CONCAT(CONCAT_WS(' ', IF(LENGTH(u.fname),u.fname,NULL), IF(LENGTH(u.lname),u.lname,NULL))) as u_name FROM users u where u.id = ? ", array($row_item['form_created_by']));

								if(!empty($userData) && !empty($userData['u_name'])) {
									$createdBy = $userData['u_name'];
								}
							}
						} else {
							$createdBy = $row_item['form_created_by'];
						}
					}
					
					if(!empty($createdBy)) {
						$reminderLogHtml = "<div class='mb-2'><span class='text'>Created By - " . $createdBy . "</span></div>" . $reminderLogHtml;
					}

					$fieldHtml .= " - <span data-toggle='tooltip2' class='tooltip_text' title=''><i class='fa fa-info-circle' aria-hidden='true'></i><div class='hidden_content'style='display:none;'>" . $reminderLogHtml . "</div></span>";
				//}

				$rowData[$cItem['name']] = (isset($row_item[$cItem['name']]) && !empty($row_item[$cItem['name']])) ? getHtmlString($fieldHtml) : "-";
				continue;
			} else if($cItem['name'] == "reviewer") {
				$fieldHtml = $row_item[$cItem['name']];
				$rowData[$cItem['name']] = (isset($row_item[$cItem['name']]) && !empty($row_item[$cItem['name']])) ? getHtmlString($fieldHtml) : "-";
				continue;
			} else if($cItem['name'] == "actions") {
				$fieldHtml = '';

				if(in_array($row_item['status'], array(FormController::PENDING_LABEL, FormController::SAVE_LABEL))) {
					$fieldHtml = '<div class="btn-group btn-actions">';
					$fieldHtml .= '<button class="btn btn-secondary btn-sm dropdown-toggle" type="button" id="actionBtn'.$row_item['item_id'].'" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">Actions</button>';
	  				$fieldHtml .= '<div class="dropdown-menu" aria-labelledby="actionBtn'.$row_item['item_id'].'">';
	    			$fieldHtml .= '<a class="dropdown-item" href="javascript:void(0);" onclick="openCopyLink(\''.$row_item['pid'].'\',\''.$row_item['form_id'].'\',\''.$row_item['id'].'\',\''.$row_item['form_type'].'\')">Copy Link</a>';

	    			if($row_item['template_status'] == "1") {
	    				$fieldHtml .= '<a class="dropdown-item" href="javascript:void(0);" onclick="sendFormToken(\''.$row_item['pid'].'\',\''.$row_item['form_id'].'\',\''.$row_item['id'].'\',\''.$row_item['form_type'].'\', false)">Re-Send Reminder</a>';
	    			}

	    			if(AclMain::aclCheckCore('admin', 'super')) {
	    				$fieldHtml .= '<a class="dropdown-item" href="javascript:void(0);" onclick="deleteForm(\''.$row_item['id'].'\')">Delete</a>';
	    			}

	    			$fieldHtml .= '</div>';
	  				$fieldHtml .= '</div>';
  				} else if(in_array($row_item['status'], array(FormController::SUBMIT_LABEL))) {
  					$fieldHtml = '<div class="btn-group btn-actions">';
					$fieldHtml .= '<button class="btn btn-secondary btn-sm dropdown-toggle" type="button" id="actionBtn'.$row_item['item_id'].'" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">Actions</button>';
	  				$fieldHtml .= '<div class="dropdown-menu" aria-labelledby="actionBtn'.$row_item['item_id'].'">';
	    			
	    			$fieldHtml .= '<a class="dropdown-item" href="javascript:void(0);" onclick="formDeleteStatus(\''.$row_item['item_id'].'\')">Delete</a>';

	    			if($row_item['template_status'] == "1") {
	    				$fieldHtml .= '<a class="dropdown-item" href="javascript:void(0);" onclick="sendFormToken(\''.$row_item['pid'].'\',\''.$row_item['form_id'].'\',\''.$row_item['id'].'\',\''.$row_item['form_type'].'\', false, \'resend_after_submit\')">Re-Send Reminder</a>';
	    			}

	    			$fieldHtml .= '<a class="dropdown-item" href="javascript:void(0);" onclick="reviewSummary(\''.$row_item['pid'].'\',\''.$row_item['id'].'\')">Review</a>';

	    			$fieldHtml .= '<a class="dropdown-item" href="javascript:void(0);" onclick="rejectForm(\''.$row_item['id'].'\')">Reject Form</a>';

	    			$fieldHtml .= '</div>';
	  				$fieldHtml .= '</div>';
  				} else if(in_array($row_item['status'], array(FormController::REVIEW_LABEL))) {
  					$fieldHtml = '<div class="btn-group btn-actions">';
					$fieldHtml .= '<button class="btn btn-secondary btn-sm dropdown-toggle" type="button" id="actionBtn'.$row_item['item_id'].'" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">Actions</button>';
	  				$fieldHtml .= '<div class="dropdown-menu" aria-labelledby="actionBtn'.$row_item['item_id'].'">';
	    			
	    			$fieldHtml .= '<a class="dropdown-item" href="javascript:void(0);" onclick="reviewSummary(\''.$row_item['pid'].'\',\''.$row_item['id'].'\')">Review</a>';

	    			$fieldHtml .= '</div>';
	  				$fieldHtml .= '</div>';
  				}

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
		"select" => "DISTINCT vof.ref_id as id, vof.created_date, (SELECT vof1.id from vh_onsite_forms vof1 where vof1.ref_id = vof.ref_id order by case when vof1.status = 'rejected' then 5 when vof1.status = 'reviewed' then 4 when vof1.status = 'submited' then 3 when vof1.status = 'saved' then 2 when vof1.status = 'pending' then 1 end desc limit 1) as item_id",
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
		if(isset($row_item['item_id'])) {
			$itemsIdList[] = $row_item['item_id'];
		}
	}

	$itemsIdList = implode(",", $itemsIdList);
	if(!empty($itemsIdList)) {
		$sqlDataQuery = "SELECT vfdl.id, vfdl.`type` as form_type, vfdl.form_id, vfdl.`created_by` as form_created_by, vof.id as item_id, vof.pid, vof.created_date, vof.reviewed_date, vof.received_date, pd.pubpid, CONCAT(CONCAT_WS(' ', IF(LENGTH(pd.fname),pd.fname,NULL), IF(LENGTH(pd.lname),pd.lname,NULL)), ' (', pd.pubpid ,')') as patient_name, vofl.onetime_token, vof.status, CONCAT(LEFT(us.`fname`,1), '. ',us.`lname`) AS 'reviewer', IF(FROM_UNIXTIME(oa.expires) < NOW(), 1, 0) as isExpire, vof.denial_reason from vh_form_data_log vfdl join vh_onsite_forms vof on vof.ref_id  = vfdl.id join patient_data pd on pd.pid = vof.pid join vh_onetimetoken_form_log vofl on vofl.ref_id = vof.ref_id join onetime_auth oa on vofl.onetime_token_id = oa.id left join `users` us ON vof.`reviewer` = us.`id` WHERE vof.id IN (" . $itemsIdList . ")";

		if(!empty($columnName)) {
			$sqlDataQuery .= " ORDER BY $columnName $columnSortOrder";
		}

		$dataResult = sqlStatement($sqlDataQuery);
		while ($data_row_item = sqlFetchArray($dataResult)) {
			$dataSet[] = prepareDataTableData($data_row_item, $columns);
		}
	}

	return array(
		"data" => $dataSet
	);
}

if(!empty($page_action)) {
	$patientForm = new FormController();

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
	} else if($page_action == "action_delete") {
		
		try {
			$form_data_id = isset($_REQUEST['form_data_id']) ? $_REQUEST['form_data_id'] : "";
			
			if(empty($form_data_id)) {
				throw new \Exception("Empty form data id");
			}

			$patientForm->deleteOnsiteForms($form_data_id);

		} catch (\Throwable $e) {
            echo json_encode(array("status" => false, "message" => $e->getMessage()));
        } 	

		echo json_encode(array("status" => true, "message" => "Success"));
		exit();
	} else if($page_action == "action_delete_submited") {
		try {
			$form_data_id = isset($_REQUEST['form_data_id']) ? $_REQUEST['form_data_id'] : "";
			
			if(empty($form_data_id)) {
				throw new \Exception("Empty form data id");
			}

			$patientForm->setDeleteStatusOnsiteForms($form_data_id);

		} catch (\Throwable $e) {
            echo json_encode(array("status" => false, "message" => $e->getMessage()));
        } 	

		echo json_encode(array("status" => true, "message" => "Success"));
		exit();
	} else if($page_action == "action_rejected") {

		try {
			$form_data_id = isset($_REQUEST['form_data_id']) ? $_REQUEST['form_data_id'] : "";
			
			if(empty($form_data_id)) {
				throw new \Exception("Empty form data id");
			}

			$patientForm->updateOnsiteForms(array(
				"status" => FormController::REJECT_LABEL,
				"reviewed_date" => "CURRENT_TIMESTAMP",
				"reviewer" => $_SESSION['authUserID']
			), $form_data_id);

		} catch (\Throwable $e) {
            echo json_encode(array("status" => false, "message" => $e->getMessage()));
        }

		echo json_encode(array("status" => true, "message" => "Success"));
		exit();
	}
}

?>
<html>
<head>
    <title><?php echo xlt('Form Dashboard'); ?></title>

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
		    // @VH - nickNameVal changes.
		    var nickNameVal = (p_data.hasOwnProperty('nickname33') && p_data['nickname33'] != "" && p_data['nickname33'] != null) ? ' "'+p_data['nickname33']+'" ' : '';

		    var f = document.forms[0];
		    // @VH - Added nickname value to patient name.
		    f.form_patient.value = lname + ', ' + fname  + nickNameVal;
		    f.form_pid.value = pid;
		}

		function dlReviewSummary(pid, form_data_id) {
			reviewSummary(pid, form_data_id);
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
		});
	</script>
</head>
<body> 
	<div class="container mt-3">
		<h2><?php echo xlt('Form Dashboard'); ?></h2>
		<div class="main-container mt-4">
			<div class="datatable_filter">
				<form id="page_report_filter">
					<div class="filter-container">
						<div class="btn-group btn-group-toggle" data-toggle="buttons">
						  <label class="btn btn-primary ">
						  	<?php if($default_mode === true) { ?>
						  	<input type="hidden" name="default_mode" value="<?php echo $default_mode; ?>">
						  	<?php } ?>
						    <input type="radio" name="form_mode" class="form_status" value="pending" autocomplete="off" <?php echo $form_mode == "pending" ? "checked" : ""; ?>> <?php echo xlt('Pending Items'); ?>
						  </label>
						  <label class="btn btn-primary">
						    <input type="radio" name="form_mode" class="form_status" value="received" autocomplete="off" <?php echo $form_mode == "received" ? "checked" : ""; ?>> <?php echo xlt('Received Items'); ?>
						  </label>
						  <label class="btn btn-primary">
						    <input type="radio" name="form_mode" class="form_status" value="reviewed" autocomplete="off" <?php echo $form_mode == "reviewed" ? "checked" : ""; ?>> <?php echo xlt('Archived Items'); ?>
						  </label>
						</div>
						<div></div>
						<div>
							<button type="button" class="btn btn-primary" onclick="sendFormToken()">
								<i class="fa fa-plus" aria-hidden="true"></i> <?php echo xlt('Send Form'); ?>
							</button>
						</div>
					</div>

					<div class="form-row mt-4 align-items-center">
						<input type='hidden' name='form_pid' value='<?php echo $form_pid; ?>' />
					    <?php if($default_mode === false) { ?>
					    <div class="col-4">
					      	<div class="form-group">
					      		<label><?php echo xlt('Patient'); ?></label>
					      		<input type="text" class="form-control" name="form_patient" placeholder="<?php echo xlt('Patient'); ?>" onclick='sel_patient()'>
					      	</div>
					    </div>
					    <div class="col-3">
					      	<div class="form-group">
					      		<label><?php echo xlt('From Date'); ?></label>
					      		<input type="text" class="form-control date_field" name="form_from_date" placeholder="<?php echo xlt('Date From'); ?>" value="<?php echo date('m/d/Y', strtotime("-1 month")); ?>">
					      	</div>
					    </div>

					    <div class="col-3">
					      	<div class="form-group">
					      		<label><?php echo xlt('To Date'); ?></label>
					      		<input type="text" class="form-control date_field" name="form_to_date" placeholder="<?php echo xlt('Date To'); ?>" value="<?php echo date('m/d/Y'); ?>">
					      	</div>
					    </div>

					    <div class="col-auto" style="margin-top: 30px;">
					    	<div class="form-group">
					      		<button type="button" class="btn btn-primary" id="filter_submit" onclick="submitTable()" ><i class="fa fa-search" aria-hidden="true"></i> <?php echo xlt('Search'); ?></button>
					  		</div>
					    </div>
						<?php } ?>
					</div>
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

		function initTooltip() {
			$('[data-toggle="tooltip1"]').tooltip();

			$('[data-toggle="tooltip2"]').tooltip({
	        	classes: {
	                "ui-tooltip": "ui-corner-all uiTooltipContainer",
	                "ui-tooltip-content" : "ui-tooltip-content uiTooltipContent"
	            },
	            content: function(){
	              var element = $( this );
	              return element.find('.hidden_content').html();
	            },
	        	html: true,
	        	track: true
	        });
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
				        "order": [[ 3, "desc" ]],
				        "iDisplayLength" : 100,
				        "deferLoading" : 0,
				        "info": false, // Disable showing information
			        	"pagingType" : "simple",
    					"paging": true, // Enable pagination 
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

				$(id).on('draw.dt', function () {
		            //Init Tooltip
		            initTooltip();
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
		    		if(ii == "form_from_date" && indexed_array["form_to_date"] == "") {
		    			alert("Please select to date.");
		    			return false;
		    		}else if(ii == "form_to_date" && indexed_array["form_from_date"] == "") {
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
				'form_data_manager.php', 
				{ action: 'fetch_data' },
				'<?php echo json_encode($columnList); ?>'
			);

			dataTable.draw();
			window.dataTable = dataTable;

			$('.form_status').change(function() {
				var f = document.forms[0];
				f.submit();
			});
		});

		// async function actionHandleCall(data, doUpdate = false, doUpdateAll = false) {
		// 	var res = await $.ajax({
		// 	    url: 'form_data_manager.php',
		// 	    type: 'POST',
		// 	    data: { 'columnList' : JSON.parse('<?php echo json_encode($columnList); ?>'), ...data, 'doUpdate' : doUpdate }
		// 	});

		// 	//Parse JSON Data.
		// 	if(res != undefined) {
		// 		res = JSON.parse(res);
		// 	}

		// 	return res;
		// }

		function closeRefresh() {
        	location.reload();
        }

        function submitTable() {
        	window.dataTable.draw();
        }

        function setreject(form_data_id) {
    		window.dataTable.draw();
    	}
	</script>	
</body>
</html>