<?php

if (isset($_REQUEST['ajax']) && $_REQUEST['ajax'] == "1") {

	require_once(__DIR__ . '/../../../globals.php');

	//verify csrf
	if (!\OpenEMR\Common\Csrf\CsrfUtils::verifyCsrfToken($_REQUEST["csrf_token_form"])) {
	    echo json_encode(array("error" => xl('Authentication Error') ));
	    \OpenEMR\Common\Csrf\CsrfUtils::csrfNotVerified(false);
	}

	$res12 = sqlStatement("SELECT * from vh_appt_info vai");

	if(sqlNumRows($res12) > 0) {
		while ($result4 = sqlFetchArray($res12)) { 
		?>
    		<li class="menuLabel">
            	<div class="d-flex">
            		<div><b><?php echo $result4['name'] ?? "" ?></b></div>
					<div class="ml-auto"><?php echo round($result4['percent'], 2) ?? 0 ?>%</div>
            	</div>
            </li>
		<?php 
		}
	} else {
		?>
		<!-- <li class="menuLabel">
        	<div class="d-flex">
        		<div><?php //echo xlt('Not Found'); ?></div>
				<div class="ml-auto"></div>
        	</div>
        </li> -->
		<?php
	}

	exit();
} else if (isset($_REQUEST['ajax']) && $_REQUEST['ajax'] == "2") {
	require_once(__DIR__ . '/../../../globals.php');

	//verify csrf
	if (!\OpenEMR\Common\Csrf\CsrfUtils::verifyCsrfToken($_REQUEST["csrf_token_form"])) {
	    echo json_encode(array("error" => xl('Authentication Error') ));
	    \OpenEMR\Common\Csrf\CsrfUtils::csrfNotVerified(false);
	}

	$cat_name = "New Chiropractic Patient Exam";
	$current_week = sqlQuery("select count(ope.pc_eid) as total from openemr_postcalendar_events ope join openemr_postcalendar_categories opc on ope.pc_catid = opc.pc_catid where week(ope.pc_eventdate)=week(now()) and year(ope.pc_eventdate)=year(now()) and ope.pc_apptstatus not in ('x','%','?') and opc.pc_catid = 30;");
	$current_week_last_year = sqlQuery("select count(ope.pc_eid) as total from openemr_postcalendar_events ope join openemr_postcalendar_categories opc on ope.pc_catid = opc.pc_catid where week(ope.pc_eventdate)=week(now()) and year(ope.pc_eventdate)=year(now())-1 and ope.pc_apptstatus not in ('x','%','?') and opc.pc_catid = 30;");
	$previous_week = sqlQuery("select count(ope.pc_eid) as total from openemr_postcalendar_events ope join openemr_postcalendar_categories opc on ope.pc_catid = opc.pc_catid where week(ope.pc_eventdate)=week(now())-1 and year(ope.pc_eventdate)=year(now()) and ope.pc_apptstatus not in ('x','%','?') and opc.pc_catid = 30;");
	$previous_week_last_year = sqlQuery("select count(ope.pc_eid) as total from openemr_postcalendar_events ope join openemr_postcalendar_categories opc on ope.pc_catid = opc.pc_catid where week(ope.pc_eventdate)=week(now())-1 and year(ope.pc_eventdate)=year(now())-1 and ope.pc_apptstatus not in ('x','%','?') and opc.pc_catid = 30;");

	?>
	<li class="menuLabel">
    	<div class="d-flex">
    		<div><b><?php echo xlt('New Chiro Current Week'); ?></b></div>
			<div class="ml-auto"><?php echo !empty($current_week) && isset($current_week['total']) ? $current_week['total'] : 0 ?></div>
    	</div>
    </li>
    <li class="menuLabel">
    	<div class="d-flex">
    		<div><b><?php echo xlt('New Chiro Current Week Last Year'); ?></b></div>
			<div class="ml-auto"><?php echo !empty($current_week_last_year) && isset($current_week_last_year['total']) ? $current_week_last_year['total'] : 0 ?></div>
    	</div>
    </li>
    <li class="menuLabel">
    	<div class="d-flex">
    		<div><b><?php echo xlt('New Chiro Previous Week'); ?></b></div>
			<div class="ml-auto"><?php echo !empty($previous_week) && isset($previous_week['total']) ? $previous_week['total'] : 0 ?></div>
    	</div>
    </li>
    <li class="menuLabel">
    	<div class="d-flex">
    		<div><b><?php echo xlt('New Chiro Previous Week Last Year'); ?></b></div>
			<div class="ml-auto"><?php echo !empty($previous_week_last_year) && isset($previous_week_last_year['total']) ? $previous_week_last_year['total'] : 0 ?></div>
    	</div>
    </li>
	<?php

	exit();
}

?>
<style type="text/css">
	#apptinfo:hover > .apptfunctions {
		display: block;
		width: 98%;
	}

	#apptinfo:hover > .apptfunctions li {
		background-color: #fff !important;
	}

	#apptinfodropdown {
		width: 350px !important;
	}

	.apptinfoicon {
		font-size: 20px;
    	padding: 0px 5px;
	}

</style>

<span id="apptInfoData">
	<div id="apptinfo" class="appMenu ml-3">
		<div class='menuLabel dropdown' id="apptinfo" title="<?php echo xla('Current user') ?>" role="button" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
	        <span class="apptinfoicon">
	            <i class="fa fa-info-circle" aria-hidden="true"></i>
	        </span>
	        <ul id="apptinfodropdown" class="apptfunctions menuEntries dropdown-menu dropdown-menu-right menu-shadow-ovr rounded-0 border-0">
	        	<ul id="stats1">
	        	</ul>
	        	<ul id="stats2">
	        	</ul>
	        </ul>
	    </div>
	</div>
</span>

<script type="text/javascript">

	function goApptInfoRepeaterServices() {
		request = new FormData;
        request.append("ajax", "1");
        request.append("csrf_token_form", csrf_token_js);
        fetch(webroot_url + "/interface/main/tabs/templates/appt_data_info_template.php", {
            method: 'POST',
            credentials: 'same-origin',
            body: request
        }).then(function(response) {
        		// When the page is loaded convert it to text
        		return response.text()
    	}).then((htmldata) => {
            document.querySelector('#apptinfodropdown #stats1').innerHTML = htmldata;
        }).catch(function(error) {
            console.log('HTML Background Service start Request failed: ', error);
        });

		var repeater = setTimeout("goApptInfoRepeaterServices()", 10000);	
	}

	function fetchStats2() {
		request = new FormData;
        request.append("ajax", "2");
        request.append("csrf_token_form", csrf_token_js);
        fetch(webroot_url + "/interface/main/tabs/templates/appt_data_info_template.php", {
            method: 'POST',
            credentials: 'same-origin',
            body: request
        }).then(function(response) {
        		// When the page is loaded convert it to text
        		return response.text()
    	}).then((htmldata) => {
            document.querySelector('#apptinfodropdown #stats2').innerHTML = htmldata;
        }).catch(function(error) {
            console.log('HTML Background Service start Request failed: ', error);
        });
	}

	$(function() {
        goApptInfoRepeaterServices();
        fetchStats2();
    });
</script>