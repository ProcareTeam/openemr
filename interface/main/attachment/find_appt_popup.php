<?php

include_once("../../globals.php");
include_once("$srcdir/OemrAD/oemrad.globals.php");

use OpenEMR\Core\Header;
use OpenEMR\OemrAd\FaxMessage;
use OpenEMR\OemrAd\PostalLetter;

if(!isset($_REQUEST['pid'])) $_REQUEST['pid'] = '';
if(!isset($_REQUEST['pagetype'])) $_REQUEST['pagetype'] = '';
if(!isset($_REQUEST['case_id'])) $_REQUEST['case_id'] = '';

$pid = strip_tags($_REQUEST['pid']);
$case_id = strip_tags($_REQUEST['case_id']);
$pagetype = $_REQUEST['pagetype'];
$urlQryStr = "";
$urlQryStr .= !empty($pagetype) ? '&pagetype='.$pagetype : '';
$urlQryStr .= !empty($case_id) ? '&case_id='.$case_id : '';


if(isset($_REQUEST['ajax'])) {
	$aColumns = explode(',', $_REQUEST['sColumns']);

	// Paging parameters.  -1 means not applicable.
	//
	$iDisplayStart  = isset($_REQUEST['iDisplayStart' ]) ? 0 + $_REQUEST['iDisplayStart' ] : -1;
	$iDisplayLength = isset($_REQUEST['iDisplayLength']) ? 0 + $_REQUEST['iDisplayLength'] : -1;
	$limit = '';
	if ($iDisplayStart >= 0 && $iDisplayLength >= 0) {
	    $limit = "LIMIT " . escape_limit($iDisplayStart) . ", " . escape_limit($iDisplayLength);
    }

    // Column sorting parameters.
	//
	$orderby = '';
	if (isset($_REQUEST['iSortCol_0'])) {
	    for ($i = 0; $i < intval($_REQUEST['iSortingCols']); ++$i) {
	        $iSortCol = intval($_REQUEST["iSortCol_$i"]);
	        if ($_REQUEST["bSortable_$iSortCol"] == "true") {
	            $sSortDir = escape_sort_order($_REQUEST["sSortDir_$i"]); // ASC or DESC
	      		// We are to sort on column # $iSortCol in direction $sSortDir.
	            $orderby .= $orderby ? ', ' : 'ORDER BY ';
	      		//
	            $orderby .= "`" . escape_sql_column_name($aColumns[$iSortCol], array('openemr_postcalendar_events')) . "` $sSortDir";
	        }
	    }
    }
    
    // Global filtering.
	//
	$tmp_where = "";
	$where = "";
	if (isset($_GET['sSearch']) && $_GET['sSearch'] !== "") {
	    $sSearch = add_escape_custom(trim($_GET['sSearch']));
	    foreach ($aColumns as $colname) {
	        $where .= $where ? "OR " : $tmp_where. " (";

	        if ($colname == "provider") {
	        	$where .= " u.fname LIKE '$sSearch%' OR u.mname LIKE '$sSearch%' OR u.lname LIKE '$sSearch%' ";
	        } else if ($colname == "pc_eventDate") {
	        	$where .= " ope.pc_eventDate LIKE '$sSearch%' ";
	        } else if ($colname == "pc_startTime") {
	        	$where .= " ope.pc_startTime LIKE '$sSearch%' ";
	        } else if ($colname == "pc_catname") {
	        	$where .= " opc.pc_catname LIKE '$sSearch%' ";
	        } else if ($colname == "pc_apptstatus_name") {
	        	$where .= " lo.title LIKE '$sSearch%' ";
	        }
	    }

	    if ($where) {
	        $where .= ")";
	    }
    }

    if ($where != "") {
    	$where = " WHERE ope.pc_pid = " . $_REQUEST['pid'] . " AND ope.pc_case = " . $_REQUEST['case_id'] . " AND " . $where; 
    } else {
    	$where = " WHERE ope.pc_pid = " . $_REQUEST['pid'] . " AND ope.pc_case = " . $_REQUEST['case_id']  . "";
    }
    
    // Column-specific filtering.
	//
	for ($i = 0; $i < count($aColumns); ++$i) {
	    $colname = $aColumns[$i];
	    if (isset($_GET["bSearchable_$i"]) && $_GET["bSearchable_$i"] == "true" && $_GET["sSearch_$i"] != '') {
	        $where .= $where ? ' AND' : $tmp_where;
	        $sSearch = add_escape_custom($_GET["sSearch_$i"]);
	        $where .= " `" . escape_sql_column_name($colname, array('openemr_postcalendar_events')) . "` LIKE '$sSearch%'";
	    }
    }
    
    // Get total number of rows in the table.
	//
	$iTotalsqlQtr = "SELECT COUNT(ope.pc_eid) AS count FROM `openemr_postcalendar_events` AS ope left join users u on u.id = ope.pc_aid left join openemr_postcalendar_categories opc on opc.pc_catid = ope.pc_catid left join list_options lo on lo.list_id = 'apptstat' and lo.option_id = ope.pc_apptstatus ";
    $row = sqlQuery($iTotalsqlQtr);
    $iTotal = $row['count'];

    // Get total number of rows in the table after filtering.
	//
	$iFilteredTotalsqlQtr = "SELECT COUNT(ope.pc_eid) AS count FROM `openemr_postcalendar_events` AS ope left join users u on u.id = ope.pc_aid left join openemr_postcalendar_categories opc on opc.pc_catid = ope.pc_catid left join list_options lo on lo.list_id = 'apptstat' and lo.option_id = ope.pc_apptstatus";
    $row = sqlQuery($iFilteredTotalsqlQtr . $where);
    $iFilteredTotal = $row['count'];
    
    $out = array(
        "sEcho"                => intval($_GET['sEcho']),
        "iTotalRecords"        => $iTotal,
        "iTotalDisplayRecords" => $iFilteredTotal,
        "aaData"               => array()
    );

    $sellist = "ope.pc_pid, ope.pc_eid, ope.pc_eventDate, ope.pc_startTime, u.fname as provider_fname, u.mname as provider_mname, u.lname as provider_lname, opc.pc_catname as pc_catname, lo.title as pc_apptstatus_name";
	$query = "SELECT $sellist FROM `openemr_postcalendar_events` AS ope left join users u on u.id = ope.pc_aid left join openemr_postcalendar_categories opc on opc.pc_catid = ope.pc_catid left join list_options lo on lo.list_id = 'apptstat' and lo.option_id = ope.pc_apptstatus $where $orderby $limit";
    $res = sqlStatement($query);
    
    while ($row = sqlFetchArray($res)) {
		$apptTypeStr = $row['pc_catname'];
		$apptStatusStr = $row['pc_apptstatus_name'];
		$arow = array('DT_RowId' => $row['pc_eid'].'~'.$apptTypeStr.'~'.$apptStatusStr);

	    //$arow[] = isset($row["pc_eid"]) ? oeFormatShortDate($row["pc_eid"]) : '';
	    $arow[] = $row['provider_lname'] . ', ' . $row['provider_fname'] . ' ' . $row['provider_mname'];
	    $arow[] = isset($row["pc_eventDate"]) ? oeFormatShortDate($row["pc_eventDate"]) : '';
	    $arow[] = isset($row["pc_startTime"]) ? oeFormatTime($row["pc_startTime"]) : '';
	    $arow[] = isset($row["pc_catname"]) ? $row["pc_catname"] : '';
	    $arow[] = isset($row["pc_apptstatus_name"]) ? $row["pc_apptstatus_name"] : '';

	    $out['aaData'][] = $arow;
	}

	echo json_encode($out, 15);
} else {
?>
<html>
<head>
	<title><?php echo htmlspecialchars( xl('Appt Finder'), ENT_NOQUOTES); ?></title>
	<link rel="stylesheet" href='<?php echo $css_header ?>' type='text/css'>
	<?php Header::setupHeader(['opener', 'dialog', 'jquery', 'jquery-ui', 'datatables', 'datatables-colreorder', 'datatables-bs', 'fontawesome', 'oemr_ad']); ?>

	<link rel="stylesheet" href="//gyrocode.github.io/jquery-datatables-checkboxes/1.2.12/css/dataTables.checkboxes.css">
	<script type="text/javascript" src="//gyrocode.github.io/jquery-datatables-checkboxes/1.2.12/js/dataTables.checkboxes.min.js"></script>

    <style type="text/css">
		.disclaimersContainer {
			font-size: 14px;
			padding: 15px;
		}
	</style>
    <style type="text/css">
    	.apptDataTable {
    		width: 100%!important;
    	}
    </style>
    <script language="JavaScript">

	 function selappt(id, type, status) {
		if (opener.closed || ! opener.setAppt)
		alert("<?php echo htmlspecialchars( xl('The destination form was closed; I cannot act on your selection.'), ENT_QUOTES); ?>");
		else
		opener.setAppt(id, type, status);
		window.close();
		return false;
	 }

	</script>
</head>
<body>
	<div class="table-responsive table-container">
		<table id='apptDataTable' class='table table-sm' style="width:100%">
		  <thead class="thead-dark">
		    <tr>
		      <th>Provider</th>
			  <th>Date</th>
			  <th>Time</th>
	          <th>Type</th>
			  <th>Status</th>
		    </tr>
		  </thead>
		</table>
	</div>
	<script type="text/javascript">
		$(document).ready(function(){
		   $('#apptDataTable').DataTable({
		      'processing': true,
		      'serverSide': true,
		      'pageLength': 8,
		      'bLengthChange': false,
		      'sAjaxSource': '<?php echo $GLOBALS['webroot']."/interface/main/attachment/find_appt_popup.php?pid=". $pid; ?>&ajax=1<?php echo $urlQryStr; ?>',
		      'columns': [
		         { sName: 'provider' },
		         { sName: 'pc_eventDate' },
		         { sName: 'pc_startTime' },
		         { sName: 'pc_catname' },
		         { sName: 'pc_apptstatus_name' }
		      ],
		      'order': [[ 1, "desc" ]],
		      <?php // Bring in the translations ?>
    			<?php $translationsDatatablesOverride = array('search'=>(xla('Search all columns') . ':')) ; ?>
    		 <?php require($GLOBALS['srcdir'] . '/js/xl/datatables-net.js.php'); ?>
		   });

		    $("#apptDataTable").on('click', 'tbody > tr', function() { SelectAppt(this); });

		    var SelectAppt = function (eObj) {
			    objID = eObj.id;
			    var parts = objID.split("~");
			    return selappt(parts[0], parts[1], parts[2]);
			}

		});
	</script>
</body>
</html>
<?php
}