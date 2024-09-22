<?php

require_once("../../../globals.php");

use OpenEMR\Core\Header;

$eid = isset($_REQUEST['eid']) ? $_REQUEST['eid'] : null;
$pid = isset($_REQUEST['pid']) ? $_REQUEST['pid'] : "";
$currentDate = isset($_REQUEST['date']) ? $_REQUEST['date'] : "";
$patientBalanceDue = isset($_REQUEST['patient_balance_due']) ? $_REQUEST['patient_balance_due'] : "";

$html_ui = "";

if(!empty($eid) && !empty($pid) && !empty($currentDate)) {
	$pc_events = sqlStatement("select CONCAT(u.fname,' ',u.lname) as provider,pc_title, pc_startTime from openemr_postcalendar_events pc_events inner join users u on u.id = pc_events.pc_aid where date(pc_eventDate) = '" . $currentDate . "' and pc_pid = '" . $pid . "' and pc_eid !='" . $eid . "'");
    if(!empty($pc_events))
    {
        while ($pc_event = sqlFetchArray($pc_events)) 
        {
            $html_ui .= "<li><span>" . $pc_event["provider"] . " - ". $pc_event["pc_title"] . " - " . date_format(date_create($pc_event["pc_startTime"]), "h:i:s A") . "</span></li>";
        }
    }
}

?>
<!DOCTYPE html>
<html>
<head>
	<meta charset="utf-8">
	<meta name="viewport" content="width=device-width, initial-scale=1">

	<?php Header::setupHeader(['common', 'datetime-picker', 'opener', 'jquery-ui', 'jquery-ui-base', 'oemr_ad']); ?>

	<style type="text/css">
		.futureApptUlList {
			    padding-left: 25px;
		}
	</style>

	<script type="text/javascript">
		async function goToBalancePage(pid) {
			await handleGoToBalance(pid, { "default_submit" : true });

			if (opener && !opener.closed && opener.dlgclose) {
				//dlgclose();
				//opener.dlgclose();
			}
		}
	</script>

</head>
<body>

	<center>
		<p style="color:red;">Patient owes 
			<a href="javascript:void(0);" onclick="goToBalancePage('<?php echo $pid; ?>')">$ <?php echo $patientBalanceDue; ?> </a>. Please collect now. 
		</p>
	</center>

	<?php if(!empty($html_ui)) { ?>
		<div class="p-2">
			<b><?php echo xla('Additional Appointment For Today'); ?></b><br/>
			<ul class="futureApptUlList">
				<?php echo $html_ui; ?>
			</ul>
		</div>
	<?php } ?>
</body>
</html>