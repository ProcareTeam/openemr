<?php 

require_once("../../globals.php");
require_once("$srcdir/options.inc.php");
require_once("$srcdir/patient.inc");
require_once("$srcdir/wmt-v2/wmtstandard.inc");
require_once("$srcdir/wmt-v2/wmt.msg.inc");
require_once("$srcdir/OemrAD/oemrad.globals.php");

use OpenEMR\Core\Header;
use OpenEMR\OemrAd\Caselib;

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
$columnList = array(
	array(
		"name" => "dt_control",
		"title" => "dt_control",
		"data" => array(
            "className" => 'dt-control-all dt-control',
            "orderable" => false,
            "data" => '',
            "defaultContent" => '',
            "width" => "25"
		) 
	),
	array(
		"name" => "case_id",
		"title" => "Case Number",
		"data" => array(
			"width" => "120"
		)
	),
	array(
		"name" => "case_manager",
		"title" => "Case Manager",
		"data" => array(
			"width" => "120"
		)
	),
	array(
		"name" => "patient_name",
		"title" => "Patient Name",
		"data" => array(
            "defaultValue" => getHtmlString('<i class="defaultValueText">Empty</i>'),
		)
	),
	array(
		"name" => "primary_payer",
		"title" => "Primary Payer",
		"data" => array(
            "defaultValue" => getHtmlString('<i class="defaultValueText">Empty</i>'),
            "width" => "150"
		)
	),
	array(
		"name" => "owner",
		"title" => "Owner",
		"data" => array(
            "defaultValue" => getHtmlString('<i class="defaultValueText">Empty</i>'),
            "width" => "150"
		)
	),
	array(
		"name" => "status",
		"title" => "Status",
		"data" => array(
            "defaultValue" => getHtmlString('<i class="defaultValueText">Empty</i>'),
            "width" => "150"
		)
	),
	array(
		"name" => "created_date",
		"title" => "Created Date",
		"data" => array(
            "defaultValue" => getHtmlString('<i class="defaultValueText">Empty</i>'),
            "width" => "150"
		)
	),
	array(
		"name" => "action_item",
		"title" => "Action Item",
		"data" => array(
            "defaultValue" => getHtmlString('<i class="defaultValueText">Empty</i>'),
            "width" => "0",
            "visible" => false,
            "orderable" => false
		)
	)
);

$statusList = array(
	'pending' => 'Pending',
	'done' => 'Done'
);

function getHtmlString($text) {
	return addslashes(htmlspecialchars($text));
}


//Filter Query Data
function generateFilterQuery($filterData = array()) {
	$filterQryList = array();
	$filterQry = "";

	if(!empty($filterData)) {
		if(isset($filterData['status']) && !empty($filterData['status'])) {
			$filterQryList[] = "vaid.status = '". $filterData['status'] ."'";
		}

		if(isset($filterData['owner']) && !empty($filterData['owner'])) {
			$filterQryList[] = "vaid.owner = '". $filterData['owner'] ."'";
		}

		if(isset($filterData['created_date_from']) && !empty($filterData['created_date_from']) && isset($filterData['created_date_to']) && !empty($filterData['created_date_to'])) {
			$filterData['created_date_from'] = date('Y/m/d', strtotime($filterData['created_date_from']));
			$filterData['created_date_to'] = date('Y/m/d', strtotime($filterData['created_date_to']));
			
			$filterQryList[] = "(vaid.created_datetime IS NOT null and vaid.created_datetime != '' and date(vaid.created_datetime) between '".$filterData['created_date_from']."' and '".$filterData['created_date_to']."')";
		}

		if(isset($filterData['case_manager']) && !empty($filterData['case_manager'])) {
			$filterQryList[] = "vpcmd.field_value = '" . $filterData['case_manager'] . "'";
		}

		if(!empty($filterQryList)) {
			$filterQry = implode(" and ", $filterQryList);
		}
	}

	return $filterQry;
}

//Generate Query
function generateQuery($data = array(), $isSearch = false) {
	$select_qry = isset($data['select']) ? $data['select'] : "*";
	$where_qry = isset($data['where']) ? $data['where'] : "";
	$order_qry = isset($data['order']) ? $data['order'] : "fc.id"; 
	$order_type_qry = isset($data['order_type']) ? $data['order_type'] : "desc";

	if($order_qry == "patient_name") {
		$order_qry = "CONCAT(CONCAT_WS(' ', IF(LENGTH(pd.fname),pd.fname,NULL), IF(LENGTH(pd.lname),pd.lname,NULL)), ' (', pd.pubpid ,')')";
	}

	$limit_qry = isset($data['limit']) ? $data['limit'] : ""; 
	$offset_qry = isset($data['offset']) ? $data['offset'] : "asc";

	$sql = "SELECT $select_qry from vh_action_items_details vaid left join form_cases fc on fc.id = vaid.case_id left join patient_data pd on pd.pid = fc.pid left join users u on u.username = vaid.owner and length(vaid.owner)>0  ";

	$pi_case_join = " left join vh_pi_case_management_details vpcmd on vpcmd.case_id = fc.id and vpcmd.field_name = 'case_manager' and vpcmd.field_index = 0 left join users u1 on u1.id = vpcmd.field_value";

	$ins_case_join = " left join insurance_data id on id.id = fc.ins_data_id1 left join insurance_companies ic on ic.id = id.provider";

	$sql .= $pi_case_join . $ins_case_join;

	if(!empty($where_qry)) {
		$sql .= " WHERE $where_qry";
	}

	if(!empty($order_qry)) {
		$sql .= " ORDER BY $new_order_query $order_qry $order_type_qry";
	}

	if($limit_qry != '' && $offset_qry != '') {
		$sql .= " LIMIT $limit_qry , $offset_qry";
	}

	return $sql;
}

//Prepare Data Table Data
function prepareDataTableData($row_item = array(), $columns = array()) {
	global $statusList;
	$rowData = array();

	foreach ($columns as $clk => $cItem) {
		if(isset($cItem['name'])) {
			if($cItem['name'] == "case_id") {
				$fieldHtml = "<a href=\"#!\" onclick=\"handlegotoCase('".$row_item['case_id']."','".$row_item['pid']."');\">". $row_item[$cItem['name']] . "</a>";
				$rowData[$cItem['name']] = (isset($row_item[$cItem['name']]) && !empty($row_item[$cItem['name']])) ? getHtmlString($fieldHtml) : "-";
				continue;
			} else if($cItem['name'] == "case_manager") {
				$fieldHtml = htmlspecialchars($row_item['cm_lname'], ENT_QUOTES);
				if($row_item['cm_fname']) $fieldHtml .=  ', '.htmlspecialchars($row_item['cm_fname'], ENT_QUOTES);

				$rowData[$cItem['name']] = (isset($fieldHtml) && $fieldHtml != "") ? $fieldHtml : "";
				continue;
			} else if($cItem['name'] == "patient_name") {
				$fieldHtml = "<a href=\"#!\" onclick=\"goParentPid('".$row_item['pid']."');\">". $row_item[$cItem['name']] . "</a>";
				$rowData[$cItem['name']] = (isset($row_item[$cItem['name']]) && !empty($row_item[$cItem['name']])) ? getHtmlString($fieldHtml) : "-";
				continue;
			} else if($cItem['name'] == "primary_payer") {
				$fieldHtml = htmlspecialchars($row_item['primary_payer'], ENT_QUOTES);
				$rowData[$cItem['name']] = (isset($fieldHtml) && $fieldHtml != "") ? $fieldHtml : "";
				continue;
			} else if($cItem['name'] == "owner") { 
				$fieldHtml = htmlspecialchars($row_item['owner_lname'], ENT_QUOTES);
				if($row_item['owner_fname']) $fieldHtml .=  ', '.htmlspecialchars($row_item['owner_fname'], ENT_QUOTES);

				$rowData[$cItem['name']] = (isset($fieldHtml) && $fieldHtml != "") ? $fieldHtml : "";
				continue;
			} else if($cItem['name'] == "status") { 
				$fieldHtml = isset($row_item['status']) && isset($statusList[$row_item['status']]) ? $statusList[$row_item['status']] : "";

				$rowData[$cItem['name']] = (isset($fieldHtml) && $fieldHtml != "") ? $fieldHtml : "";
				continue;
			} else if($cItem['name'] == "created_date") { 
				$fieldHtml = isset($row_item['created_datetime']) ? DateToYYYYMMDD($row_item['created_datetime']) : "";

				$rowData[$cItem['name']] = (isset($fieldHtml) && $fieldHtml != "") ? $fieldHtml : "";
				continue;
			} else if($cItem['name'] == "action_item") { 
				$fieldHtml = isset($row_item['action_item']) ? $row_item['action_item'] : "";

				$rowData[$cItem['name']] = (isset($fieldHtml) && $fieldHtml != "") ? $fieldHtml : "";
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

	// Search 
	$searchQuery = "";
	if($searchValue != ''){
	}

	//Filter Value
	$filterQuery .= generateFilterQuery($filterVal);

	if(!empty($filterQuery)) {
		$searchQuery .= " " . $filterQuery;
	}

	//$sql_data_query = generateCaseQuery("COUNT(*) AS allcount");
	$bindArray = array();

	// $records = sqlQuery(generateQuery(array(
	// 	"select" => "COUNT(*) AS allcount",
	// 	"filter_data" => array()
	// ), true));
	// $totalRecords = $records['allcount'];

	// $records = sqlQuery(generateQuery(array(
	// 	"select" => "COUNT(*) AS allcount",
	// 	"where" => $searchQuery,
	// 	"filter_data" => $filterVal
	// ), true));

	// $totalRecordwithFilter  = $records['allcount'];

	$result = sqlStatement(generateQuery(array(
		"select" => "fc.id as case_id, fc.pid, fc.date, CONCAT(CONCAT_WS(' ', IF(LENGTH(pd.fname),pd.fname,NULL), IF(LENGTH(pd.lname),pd.lname,NULL)), ' (', pd.pubpid ,')') as patient_name, u.fname as owner_fname, u.mname as owner_mname, u.lname as owner_lname, vaid.status, vaid.created_datetime, vaid.action_item, vpcmd.field_value as cs_manager, u1.fname as cm_fname, u1.mname as cm_mname, u1.lname as cm_lname, ic.name as primary_payer ",
		"where" => $searchQuery,
		"order" => $columnName,
		"order_type" => $columnSortOrder,
		"limit" => $row,
		"offset" => $rowperpage
	)));

	$dataSet = array();
	while ($row_item = sqlFetchArray($result)) {
		$dataSet[] = prepareDataTableData($row_item, $columns);
	}

	// return array(
	// 	"recordsTotal" => $totalRecords,
	// 	"recordsFiltered" => $totalRecordwithFilter,
	// 	"data" => $dataSet
	// );

	return array(
		"data" => $dataSet
	);
}

if(!empty($page_action)) {
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
}

?>
<html>
<head>
    <title><?php echo xlt('CM Action/Items'); ?></title>
	<link rel="stylesheet" href="<?php echo $css_header; ?>" type="text/css">

	<?php Header::setupHeader(['common', 'jquery', 'jquery-ui', 'jquery-ui-base', 'datetime-picker', 'datatables', 'datatables-colreorder', 'datatables-bs', 'oemr_ad']); ?>

	<style type="text/css">
        /*Row Details*/
        table.row_details_table {
			table-layout: fixed;
			width: 100%;
			font-size: 14px;
			padding: 8px 10px;
		}
		table.row_details_table tr td {
			vertical-align: top;
			border: 0px solid #fff !important;
			padding: 0px;
		}
		table.row_details_table .note_val_container {
			white-space: pre-wrap;
			max-width: 90%;
		}

		.datatable_container {
			margin-bottom: 60px;
		}

		/*Read More*/
		.textcontentbox {
			white-space: normal!important;
		}
		.textcontentbox input {
    		opacity: 0;
		    position: absolute;
		    pointer-events: none;
		}
		.textcontentbox .content {
		    display: -webkit-box;
		    -webkit-line-clamp: 3;
		    -webkit-box-orient: vertical;  
		    overflow: hidden;
		}
		.textcontentbox input:focus ~ label {
		    outline: -webkit-focus-ring-color auto 5px;
		}
		.textcontentbox input:checked + .content {
		    -webkit-line-clamp: unset;
		} 
		.textcontentbox input:checked ~ label.readmore, 
		.textcontentbox input:not(:checked) ~ label.lessmore {
			display: none;
		}
		.textcontentbox input:checked ~ label.lessmore,
		.textcontentbox input:not(:checked) ~ label.readmore {
			display: inline-block;
		}
		.textcontentbox .content:not(.truncated) ~ label{
		    display: none!important;
		}
		.textcontentbox .content {
		    margin: 0;
		}
		.textcontentbox label {
		    color: #2672ec !important;
		    outline: none !important;
		    cursor: pointer;
		}
		.textcontentbox label:focus {
			outline: none !important;
		}
		.textcontentbox .readmore,
		.textcontentbox .lessmore {
		}
	</style>

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
<body class="body_top cm_action_items">
	<div class="page-title">
	    <h2><?php echo xlt('CM Action/Items'); ?></h2>
	</div>

	<div class="dataTables_wrapper datatable_filter mb-4">
		<form id="report_table_filter">
			<div class="form-row">
				<div class="col col-2">
					<div class="form-group">
						<label><?php echo xlt('Owner'); ?></label>
						<div class="form-row">
			    			<div class="col">
			    				<select name="owner" class="form-control">
			    					<?php MsgUserGroupSelect('', true, false, false, array(), true); ?>
			    				</select>
			    			</div>
				    	</div>
					</div>
				</div>

				<div class="col col-2">
					<div class="form-group">
						<label><?php echo xlt('Case Manager'); ?></label>
						<select name="case_manager" class="form-control">
							<option value=""><?php echo xlt('Please Select'); ?></option>
							<?php Caselib::getUsersBy('', '', array('physician_type' => array('chiropractor_physician', 'case_manager_232321')), '', false); ?>
						</select>
					</div>
				</div>

				<div class="col col-2">
					<div class="form-group">
						<label><?php echo xlt('Status'); ?></label>
						<div class="form-row">
			    			<div class="col">
			    				<select name="status" class="form-control">
			    					<option value=""></option>
                                    <option value="pending" selected="selected"><?php echo xl('Pending'); ?></option>
                                    <option value="done"><?php echo xl('Done'); ?></option>
			    				</select>
			    			</div>
				    	</div>
					</div>
				</div>				
				<div class="col col-6">
					<div class="form-group">
						<label><?php echo xlt('Created Date'); ?></label>
						<div class="form-row">
			    			<div class="col">
			    				<input type="text" name="created_date_from" class="date_field form-control" placeholder="From (MM/DD/YY)">
			    			</div>
			    			<div class="col">
			    				<input type="text" name="created_date_to" class="date_field form-control" placeholder="To (MM/DD/YY)">
				    		</div>
				    	</div>
					</div>
				</div>
			</div>
			<div class="form-row">
				<div class="col">
					<button type="submit" id="filter_submit" class="btn btn-secondary"><?php echo xlt('Submit'); ?></button>
					<button type="button" id="printbtn" class="btn btn-secondary ml-1"><i class="fa fa-print"></i> <?php echo xlt('Print'); ?></button>
				</div>
			</div>
		</form>
	</div>

	<div id="case_pi_billing_report_container" class="table-responsive removepagination">
		<table id='report_table' class='text table table-sm datatable_report' style="width: 100%;">
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

<script>
	<?php include($GLOBALS['srcdir'].'/wmt-v2/report_tools.inc.js'); ?>
</script>

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
		var action_item_val = decodeHtmlString(d.action_item);

		return '<div><table class="row_details_table text table table-sm table-borderless mb-0"><tbody>'+
					'<tr>'+
						'<td width="120" height="10">'+
							'<span>Action Item:</span>'+
						'</td>'+
						'<td>'+
							'<div>'+
								'<div class="textcontentbox">'+
									'<input type="checkbox" id="expanded_nt_'+d.case_id+'">'+
									'<div class="content note_val_container">'+(action_item_val != "" ? action_item_val : defaultVal)+'</div>'+
									'<label for="expanded_nt_'+d.case_id+'" class="readmore" role="button">Read More</label>'+
									'<label for="expanded_nt_'+d.case_id+'" class="lessmore" role="button">Read Less</label>'+
									'</div></div>'+
						'</td>'+
					'</tr>'+
			   '</tbody></table></div>';
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

			$(id).on('draw.dt', function () {
	            //Expand Row Details
	            dTable.rows().every( function () {
	            	var tr = $(this.node());
	            	var row = dTable.row( tr );
	            	var childTrClass = tr.hasClass('even') ? 'even' : 'odd';
	            	row.child(format(row.data()), 'no-padding row-details-tr bg-light ').show();
		            tr.addClass('shown').trigger('classChange');
		            //$('.dt-control-all').closest('tr').addClass('shown');
	            });

	            const ps = document.querySelectorAll('.textcontentbox .content');
					ps.forEach(p => {
					  if(Math.ceil(p.scrollHeight) > Math.ceil(p.offsetHeight)) {
					  	p.classList.add("truncated");
					  } else {
					  	p.classList.remove("truncated");
					  }
					});
		    });

	        $(id+' tbody').on('classChange', function() {
			    var isShown = $(id+' tbody tr.shown').length;
			    var tr = $(id+' thead tr th.dt-control').closest('tr');

			    if(isShown > 0) {
			    	tr.addClass('shown');
			    } else {
			    	tr.removeClass('shown');
			    }
			});

	        // Add event listener for opening and closing details
		    $(id+' tbody').on('click', 'td.dt-control', function () {
		        var tr = $(this).closest('tr');
		        var row = dTable.row( tr );
		 
		        if ( row.child.isShown() ) {
		            // This row is already open - close it
		            row.child.hide();
		            tr.removeClass('shown').trigger('classChange');
		        }
		        else {
		            // Open this row
		            var childTrClass = tr.hasClass('even') ? 'even' : 'odd';
	            	row.child(format(row.data()), 'no-padding row-details-tr bg-light ').show();
		            tr.addClass('shown').trigger('classChange');
		        }
		    });

		    $(id+' thead').on('click', 'th.dt-control', function () {
		    	var tr = $(this).closest('tr');

		    	if(tr.hasClass( "shown" )) {
		    		//UnExpand Row Details
		    		dTable.rows().every( function () {
		            	var tr = $(this.node());
		            	var row = dTable.row( tr );
		            	row.child.hide();
		            	tr.removeClass('shown').trigger('classChange');
		            });
		    	} else {
		    		//Expand Row Details
		    		dTable.rows().every( function () {
		            	var tr = $(this.node());
		            	var row = dTable.row( tr );
		            	var childTrClass = tr.hasClass('even') ? 'even' : 'odd';
		            	row.child(format(row.data()), 'no-padding row-details-tr bg-light ').show();
			            tr.addClass('shown').trigger('classChange');
		            });
		    	}
		    });

		    $('<div class="dataTables_paginate"><ul class="pagination"><li class="paginate_button custom-page-item previous disabled" id="page_report_previousbtn"><a href="javascript:void(0);" class="page-link">Previous</a></li><li class="paginate_button custom-page-item next disabled" id="page_report_nextbtn"><a href="javascript:void(0);" class="page-link">Next</a></li></ul></div>').insertAfter('.dataTables_paginate');

			// Handle click event for the custom button
		    $('#page_report_nextbtn').on('click', function() {
		        if (!$(this).hasClass('disabled')) {
			        const currentPageInfo = dTable.page.info();

			        if(currentPageInfo.hasOwnProperty('page')) {
			        	dTable.page(currentPageInfo.page + 1).draw( 'page' );
			    	}
		    	}
		    });

		    // Handle click event for the custom button
		    $('#page_report_previousbtn').on('click', function() {
		        if (!$(this).hasClass('disabled')) {
			        const currentPageInfo = dTable.page.info();

			        if(currentPageInfo.hasOwnProperty('page') && currentPageInfo.page > 0) {
			        	dTable.page(currentPageInfo.page - 1).draw( 'page' );
			    	}
		    	}
		    });

		    $('#printbtn').click(function() {
                // Get the table data in HTML format
                var tableHTML = dTable.table().node().outerHTML;

                // Open a new window for the print view
                var printWindow = window.open('', '_blank', 'width=800,height=600');
                printWindow.document.write('<html><head><title><?php echo xlt('CM Action/Items'); ?></title>');

                // Add custom styles for the print view
                printWindow.document.write(`
                    <style>
                    	@media print {
	                    	@page {
		                        margin-left: <?php echo ($GLOBALS['pdf_left_margin'] * 96) / 25.4; ?>px;
		                        margin-right: <?php echo ($GLOBALS['pdf_right_margin'] * 96) / 25.4; ?>px;
		                        margin-top: <?php echo (($GLOBALS['pdf_top_margin'] * 96) / 25.4) * 1.5; ?>px;
		                        margin-bottom: <?php echo (($GLOBALS['pdf_bottom_margin'] * 96) / 25.4) * 1.5; ?>px;
		                    }

	                    	.no-print {
				                display: none !important;
				            }
        				}

                        body {
                            font-family: Arial, sans-serif;
                            font-size: 12pt;
                            color: #333;
                            background-color: #fff;
                            margin: 10px; /* Remove outer margins */
                        	padding: 0; /* Remove padding */
                        }

                        a {
			                color: #333; /* Remove color and set to black */
			                text-decoration: none; /* Remove underline */
			            }

                        table {
                            width: 100% !important;
                            border-collapse: collapse;
                            font-size: 13px;
                        }

                        table th, table td {
                            padding: 5px;
                            text-align: left;
                            border: 1px solid #000;
                            vertical-align: top !important;
                        }

                        table thead th {
                            background-color: #f0f0f0;
                            font-weight: bold;
                        }

                        table .no-padding {
                        	padding: 0px !important;
                        }

                        td.row-details-tr {
                        	padding: 5px !important;
                        }

                        table.row_details_table, table.row_details_table tr td, table.row_details_table tr th {
                        	border: 0px !important;
                        	padding: 2px !important;
                        }

                        .dt-control-all {
                        	display: none !important;
                        }

                        table.row_details_table input[type="checkbox"] {
                        	display: none !important;
                        }
                    </style>
                `);

                // Add the table content to the print window
                printWindow.document.write('</head><body>');
                printWindow.document.write(tableHTML); // Insert the table HTML here
                printWindow.document.write('</body></html>');

                // Close the document and print the page
                printWindow.document.close();
                printWindow.print();

                // Monitor when the print window is closed
				printWindow.onafterprint = function() {
				    printWindow.close(); // Close the print window after printing
				};

				// Monitor if the print window is closed (indicating print dialog is done or canceled)
				let printWindowCheckInterval = setInterval(function() {
				    if (!printWindow.closed) {
				    	clearInterval(printWindowCheckInterval);
				    	printWindow.close();
				    }
				}, 1000);  // Check every second
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
	    		if(ii == "created_date_from" && indexed_array["created_date_to"] == "") {
	    			alert("Please select to created date.");
	    			return false;
	    		} else if(ii == "created_date_to" && indexed_array["created_date_from"] == "") {
	    			alert("Please select from created date.");
	    			return false;
	    		}

	    		form_val_array[ii] = ni;
	    	}
	    });
		}

		return form_val_array;
	}

	$(function () {
		var dataTableId = "#report_table";
		var dataTableFilterId = "#report_table_filter";

		//$('#filter_submit').prop('disabled', true);
		var dataTable = initDataTable(
			dataTableId, 
			'cm_action_items_report.php', 
			{ action: 'fetch_data' },
			'<?php echo json_encode($columnList); ?>'
		);

		$(dataTableFilterId).submit(function(e){
            e.preventDefault();
            dataTable.draw();
        });

		$(".medium_modal").on('click', function(e) {
	        e.preventDefault();e.stopPropagation();
	        dlgopen('', '', 650, 460, '', '', {
	            buttons: [
	                {text: '<?php echo xla('Close'); ?>', close: true, style: 'default btn-sm'}
	            ],
	            //onClosed: 'refreshme',
	            allowResize: false,
	            allowDrag: true,
	            dialogId: '',
	            type: 'iframe',
	            url: $(this).attr('href')
	        });
	    });
	});
</script>

<script type="text/javascript">
	var curr_scrollYVal = 0;
	var prev_scrollYVal = 0;
	$(document).ready(function() {
		window.addEventListener("scroll", (event) => {
		  	curr_scrollYVal = $(window).scrollTop();
		});

		var observer = new MutationObserver(function(mutationsList, observer) {
		    for (var mutation of mutationsList){
		        if($(mutation.target).is(":visible")){
		        	if(prev_scrollYVal >= 0) {
		        		$(window).scrollTop(prev_scrollYVal);
		        	}
		        } else if(!$(mutation.target).is(":visible")){
		        	prev_scrollYVal = curr_scrollYVal
		        }
		    }
		});

		$('.frameDisplay iframe', parent.document).each(function(i, obj) {
			var cElement = $(obj).contents().find('body.cm_action_items');
			if(cElement.length > 0){
				observer.observe(obj.parentElement, { attributes: true});
			}
		});
	});
</script>

</body>
</html>