<?php

require_once('../globals.php');
require_once($GLOBALS['srcdir'].'/patient.inc');
require_once($GLOBALS['srcdir'].'/options.inc.php');

use OpenEMR\Core\Header;
use OpenEMR\Services\FacilityService;
use OpenEMR\Common\Acl\AclMain;
use OpenEMR\Common\Crypto\CryptoGen;

$form_patient = $_REQUEST['patient_id'];
$type_form = $_REQUEST['form'];

$cryptoGen = new CryptoGen();
$pacsKey = $cryptoGen->decryptStandard($GLOBALS['pacs_header_key_value']);

$form_from_date = date('Y-m-d', strtotime('-2 years'));
if ($_REQUEST['form_from_date']) {
    $form_from_date = DateToYYYYMMDD($_POST['form_from_date']);
}

$form_to_date = (!empty($_POST['form_to_date'])) ? DateToYYYYMMDD($_POST['form_to_date']) : date('Y-m-d');
$studiesDetails = array();

if (isset($_REQUEST['ajax'])) {
	if ($_REQUEST['ajax'] == "generate_token") {

		$pacsUsername = $GLOBALS['pacs_token_api_username'] ?? "";
		$pacsPassword = $cryptoGen->decryptStandard($GLOBALS['pacs_token_api_password']);

		$ch = curl_init();
	  	curl_setopt($ch, CURLOPT_URL, $GLOBALS['pacs_token_api_url']);
	  	curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
	  	curl_setopt($ch, CURLOPT_USERPWD, "$pacsUsername:$pacsPassword");
	  	$token_responce = curl_exec($ch);
	  	$token_result = json_decode($token_responce, true);

	  	curl_close($ch); // Close the connection

	  	echo !empty($token_result) ? $token_responce : '{}';
	}
	
	exit();
}

//if (isset($_POST['submit'])) {

	$patientData = sqlQuery(
            "SELECT pubpid, pid FROM patient_data WHERE pid = ?",
            array($form_patient )
    );

	if (!empty($patientData) && !empty($patientData['pubpid'] ?? '')) {
		$data = array(
			"pid" => $patientData['pid'],
			"fromDateTime" => date("Ymd", strtotime($form_from_date)),
			"toDateTime" => date("Ymd", strtotime($form_to_date)),
			"modality" => ""
		);

		$request_headers = [
			'Content-Type: application/json',
		    $GLOBALS['pacs_header_key'] . ': ' . $pacsKey
		];

	    $ch = curl_init();
	    curl_setopt($ch, CURLOPT_URL, $GLOBALS['pacs_api_url']);
	    curl_setopt($ch, CURLOPT_POST, 1);
	    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
	    curl_setopt($ch, CURLOPT_HTTPHEADER, $request_headers);
	    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));

	    $error_msg = curl_error($ch);
	    $studiesDetailsData = curl_exec($ch);

	    curl_close($ch);

	    $studiesDetailsData = !empty($studiesDetailsData) ? json_decode($studiesDetailsData, true) : array();
	    $studiesDetails = $studiesDetailsData['studiesDetails'] ?? array();
	}
//}

?>
<html>
<head>

    <title><?php echo xlt('Radiology'); ?></title>
    <link rel="stylesheet" href='<?php echo $css_header ?>' type='text/css'>
    <?php Header::setupHeader(['opener', 'jquery', 'jquery-ui-base', 'datetime-picker', 'datatables', 'datatables-colreorder', 'datatables-bs']); ?>

    <script language="JavaScript">
        $(document).ready(function() {
            var win = top.printLogSetup ? top : opener.top;
            win.printLogSetup(document.getElementById('printbutton'));

            $('.datepicker').datetimepicker({
                <?php $datetimepicker_timepicker = false; ?>
                <?php $datetimepicker_showseconds = false; ?>
                <?php $datetimepicker_formatInput = true; ?>
                <?php require($GLOBALS['srcdir'] . '/js/xl/jquery-datetimepicker-2-5-4.js.php'); ?>
                <?php // can add any additional javascript settings to datetimepicker here; need to prepend first setting with a comma ?>
            });
        });

        async function handleViewStudy(study_id) {
        	var target = '<?php echo $GLOBALS['pacs_image_viewer_url']; ?>';

        	if(study_id != "") {
        		target += study_id;
        	}

        	const token_result = await $.ajax({
                    url: './pac_studies.php?ajax=generate_token',
                    type: 'GET',
                    timeout: 30000
                });
        	
        	if (token_result != "") {
        		const token_obj = JSON.parse(token_result);

        		if(token_obj.hasOwnProperty('token')) {
        			target += "?token=" + token_obj['token'];
        		}
        	}

        	<?php if ($GLOBALS['pacs_popup_type'] == "popup") { ?>
        		dlgopen(target, '', 'modal-full', 700, null, "<?php echo xlt('Radiology'); ?>", { allowExternal : true });
        	<?php } else { ?>
        		dialog.popUp(target, null, 'studypopup' + study_id);
        	<?php } ?>
        }
    </script>
</head>
<body class="body_top">
	<span class='title' id='title'><?php echo xlt('Radiology'); ?></span>

	<form method='post' action='pac_studies.php' id='theform' onsubmit='return top.restoreSession()'>
		<input type='hidden' name='patient_id' value='<?php echo attr($form_patient); ?>' />

		<div id="report_parameters" class="alert alert-secondary">
		<table>
	 		<tr>
	 			<td width='70%'>
					<table class='text' style="width:auto;">
			        	<tr>
			        		<td class='control-label' width="30"><?php echo xlt('From'); ?>:&nbsp;&nbsp;&nbsp;&nbsp;</td>
			        		<td width="180">
			        			<input type='text' class='datepicker form-control' name='form_from_date' id="form_from_date" size='10' value='<?php echo attr(oeFormatShortDate($form_from_date)); ?>'>
			        		</td>
			        		<td class='control-label' class='control-label' width="30"><?php echo xlt('To'); ?>:</td>
						    <td width="180">
						    	<input type='text' class='datepicker form-control' name='form_to_date' id="form_to_date" size='10' value='<?php echo attr(oeFormatShortDate($form_to_date)); ?>'>
						    </td>
			        	</tr>
			    	</table>
		    	</td>
		    	<td align='left' valign='middle' height="100%">
		    		<table style='border-left:1px solid; width:100%; height:100%' >
				        <tr>
				            <td>
				                <div class="text-center">
				                	<button type="submit" name="submit" value="1" class='btn btn-secondary btn-save'><?php echo xlt('Submit'); ?></button>
				                </div>
				            </td>
				        </tr>
				    </table>
		    	</td>
		    </tr>
		</table>
    	</div>
	</form>

	<div id="report_results">
		<table class='table'>
		    <thead class='thead-light'>
		    	<tr>
		    		<th width="280"><?php echo xlt('Patient Name'); ?></th>
			    	<th width="200"><?php echo xlt('Study Date'); ?></th>
			    	<th width="120"><?php echo xlt('Modality'); ?></th>
			    	<th width="250"><?php echo xlt('Ref Doctor'); ?></th>
			    	<th><?php echo xlt('Study Desc'); ?></th>
			    	<th width="450"><?php echo xlt('Body Part'); ?></th>
			    	<th></th>
		    	</tr>
		    </thead>
		    <tbody>
		    	<?php if (!empty($studiesDetails)) { 
		    		foreach ($studiesDetails as $stdItem) {
		    			$studyTime = $stdItem['studyTime'] ?? "";
		    			if (preg_match('/\./', $studyTime)) {
		    				$studyTime = strstr($studyTime, '.', true);
		    			}
		    			$studyDateTime = strtotime($stdItem['studyDate'] . " " . $studyTime);
		    			
		    			?>
		    			<tr>
		    				<td><?php echo ($stdItem['patientName'] ?? "") . " (" . strtoupper($stdItem['patientId'] ?? "") . ") " ?></td>
		    				<td><?php echo oeTimestampFormatDateTime($studyDateTime); ?></td>
		    				<td><?php echo $stdItem['modality'] ?? "" ?></td>
		    				<td><?php echo $stdItem['refDr'] ?? "" ?></td>
		    				<td><?php echo $stdItem['studyDesc'] ?? "<i>None</i>" ?></td>
		    				<td><?php echo !empty($stdItem['bodyPart']) ? implode(", ", $stdItem['bodyPart']) : "<i>None</i>" ?></td>
		    				<td>
		    					<button type="button" class="btn btn-secondary btn-sm" onclick="handleViewStudy('<?php echo $stdItem['studyLink'] ?>')" title="<?php echo "View Study" ?>"><i class="fa fa-eye" aria-hidden="true"></i></button>
		    				</td>
		    			</tr>
		    			<?php
		    		}
		    	?>
		    	<?php } else { ?>
		    		<tr>
		    			<td colspan="5">
		    				<center><?php echo xlt('No Record Found'); ?></center>
		    			</td>
		    		</tr>
		    	<?php } ?>
		    </tbody>
		</table>
	</div>
</body>
</html>