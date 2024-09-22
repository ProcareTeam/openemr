<?php

require_once(dirname(__FILE__, 7) . "/interface/globals.php");
require_once "$srcdir/patient.inc";
require_once "$srcdir/options.inc.php";

use OpenEMR\Common\Csrf\CsrfUtils;
use OpenEMR\Core\Header;
use Vitalhealthcare\OpenEMR\Modules\Generic\Controller\FormController;

$patientForm = new FormController();

if(isset($_POST['packet_action'])) {
	$packetAction = isset($_POST['packet_action']) ? $_POST['packet_action'] : "";
	$packetId = isset($_POST['packet_id']) ? $_POST['packet_id'] : "";

	if($packetAction == "delete") {
		sqlStatement("DELETE FROM `vh_form_packets` WHERE `id` = ? ", array($packetId));
		sqlStatement("DELETE FROM `vh_packet_link` WHERE `packet_id` = ? ", array($packetId));
		header('Location: '.$_SERVER['REQUEST_URI']);
		exit();
	} else if($packetAction == "do_active") {
		sqlStatement("UPDATE `vh_form_packets` SET `status` = 1 WHERE `id` = ? ", array($packetId));
	} else if($packetAction == "do_inactive") {
		sqlStatement("UPDATE `vh_form_packets` SET `status` = 0 WHERE `id` = ? ", array($packetId));
	} else if($packetAction == "do_clone") {
		
		$patientForm = new FormController();
		$packetTemplate = $patientForm->getPacketTemplates($packetId);

		if(!empty($packetTemplate)) {
			$packetTemplate = $packetTemplate[0];

			$packetName = isset($packetTemplate['template_name']) ? $packetTemplate['template_name'] . " - Clone " : "";
			$packetEmailTemplate = isset($packetTemplate['email_template']) ? $packetTemplate['email_template'] : "";
			$packetSMSTemplate = isset($packetTemplate['sms_template']) ? $packetTemplate['sms_template'] : "";

			$packetStatus = "1";
			$packetExpireTime = isset($packetTemplate['expire_time']) ? $packetTemplate['expire_time'] : "";

			$assignedForms = isset($packetTemplate['form_items']) ? $packetTemplate['form_items'] : array();

			if(!empty($assignedForms)) {
				$npacketId = sqlInsert("INSERT INTO `vh_form_packets` ( `uid`, `name`, `email_template`, `sms_template`, `status`, `expire_time`) VALUES ( ?, ?, ?, ?, ?, ?)", array($_SESSION['authUserID'], $packetName, $packetEmailTemplate, $packetSMSTemplate, $packetStatus, $packetExpireTime));

				foreach ($assignedForms as $fItems) {
					sqlInsert("INSERT INTO `vh_packet_link` ( `packet_id`, `form_id`) VALUES ( ?, ?)", array($npacketId, $fItems['id']));
				}
			}
		}
	}
}

$packetItems = array();  
$packetItems = $patientForm->getPacketTemplates();


?>
<html>
<head>
    <?php Header::setupHeader(['datetime-picker', 'opener', 'jquery', 'jquery-ui', 'datatables', 'datatables-colreorder', 'datatables-bs']); ?>
    <title><?php echo xlt('Form Packet Manager'); ?></title>
    <script>
    	function handleCreatePacket(packet_id = "") {
        	var target = './create_form_packet_popup.php';

        	if(packet_id != "") {
        		target += '?packet_id='+packet_id;
        	}

			dialog.popUp(target, null, 'formpacketpopup'+packet_id);
        }

        function handleDeletePacket(form_id) {
        	if(confirm('<?php echo xlt('Do you want to delete packet?'); ?>')) {
        		document.querySelector('#packet_id').value = form_id;
        		document.querySelector('#packet_action').value = "delete";
        		document.querySelector('#packet-manager').submit();
        	}
        }

        function handleClonePacket(packet_id) {
        	var f = document.forms[0];
			f.packet_action.value = "do_clone";
        	f.packet_id.value = packet_id;

        	f.submit();
        }

        function closeRefresh() {
        	//location.reload(false);
        	var f = document.forms[0];
        	f.submit();
        }

        function doActive(packet_id) {
        	var f = document.forms[0];
			f.packet_action.value = "do_active";
			f.packet_id.value = packet_id;

			f.submit();
        }

        function doInActive(packet_id) {
        	var f = document.forms[0];
			f.packet_action.value = "do_inactive";
        	f.packet_id.value = packet_id;

        	f.submit();
        }
    </script>

    <style type="text/css">
    	#form_manager_table {
    		width: 100% !important;
    		border-collapse: collapse !important;
    	}

    	table.table-bordered.tbordered tbody tr > td,
    	table.table-bordered.tbordered thead tr > th {
    		border-width: 0px !important;
    		border-bottom-width: 1px !important;
			padding: 0.8rem !important;
    	}
    </style>
</head>
<body>
	<div class="container mt-3">
		<h2><?php echo xlt('Form Packet Manager'); ?></h2>
		<div class="mt-4">
			<form action="form_packet_manager.php" method="post" id="packet-manager">
				<input type="hidden" name="packet_action" id="packet_action" value="">
				<input type="hidden" name="packet_id" id="packet_id" value="">
			</form>
			<button type="button" class="btn btn-primary px-4" onclick="handleCreatePacket()" ><i class="fa fa-plus" aria-hidden="true"></i> <?php echo xlt('Create Form Packet'); ?></button>
		</div>
		<div class="main-container mt-4">
			<table id="packet_manager_table" class="table table-bordered tbordered table-sm">
				<thead class="thead-light">
					<tr>
						<th><?php echo xlt('Packet Name'); ?></th>
						<th><?php echo xlt('Packet Status'); ?></th>
						<th><?php echo xlt('Last Modified'); ?></th>
						<th><?php echo xlt('Actions'); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php
						if(!empty($packetItems)) {
							foreach ($packetItems as $packetItem) {
								?>
								<tr>
									<td><b><?php echo isset($packetItem['template_name']) ? $packetItem['template_name'] : "" ?></b></td>
									<td><?php echo isset($packetItem['status']) && $packetItem['status'] == "1" ? "Active" : "In Active" ?></td>
									<td><?php echo isset($packetItem['modified_date']) ? $packetItem['modified_date'] : "" ?></td>
									<td class="p-1" style="vertical-align:middle;">
										<button type="button" class="btn btn-secondary btn-sm" onclick="handleCreatePacket('<?php echo $packetItem['id'] ?>')" title="<?php echo "Edit Packet" ?>"><i class="fa fa-pencil" aria-hidden="true"></i></button>
										<button type="button" class="btn btn-secondary btn-sm" onclick="handleDeletePacket('<?php echo $packetItem['id'] ?>')" title="<?php echo "Delete Packet" ?>" ><i class="fa fa-trash" aria-hidden="true"></i></button>
										<!-- <button type="button" class="btn btn-secondary btn-sm" onclick="handleAssignForm('<?php //echo $formItem['id'] ?>')" title="<?php //echo "Assign Form" ?>"><i class="fa fa-share" aria-hidden="true"></i></button> -->

										<button type="button" class="btn btn-secondary btn-sm" onclick="handleClonePacket('<?php echo $packetItem['id'] ?>')" title="<?php echo "Copy Packet" ?>"><i class="fa fa-clone" aria-hidden="true"></i></button>

										<!-- <button type="button" class="btn btn-secondary btn-sm" onclick="handlePreviewForm('<?php //echo $formItem['id'] ?>')" title="<?php //echo "Form Preview" ?>"><i class="fa fa-eye" aria-hidden="true"></i></button> -->

										<!-- <button type="button" class="btn btn-secondary btn-sm" onclick="handleDownloadFormPDF(this, '<?php //echo $formItem['id'] ?>')" title="<?php //echo "Form PDF" ?>">
											<i class="fa fa-file-pdf" aria-hidden="true"></i>
											<div class="spinner-border spinner-border-sm" style="display:none;"><span class="visually-hidden"></span></div>
										</button> -->

										<!-- <button type="button" class="btn btn-secondary btn-sm" onclick="handleFormToken('<?php //echo $formItem['id'] ?>')" title="<?php //echo "Form Token Generator" ?>"><i class="fa fa-paper-plane" aria-hidden="true"></i></button> -->

										<?php if(isset($packetItem['status']) && $packetItem['status'] === "0") { ?>
										<button type="button" class="btn btn-success btn-sm" onclick="doActive('<?php echo $packetItem['id'] ?>')" style="font-size: 11px;"><?php echo "Activate" ?></button>
										<?php } else { ?>
										<button type="button" class="btn btn-danger btn-sm" onclick="doInActive('<?php echo $packetItem['id'] ?>')" style="font-size: 11px;"><?php echo "In Activate" ?></button>
										<?php } ?>
									</td>
								</tr>
								<?php
							}
						} else {
							?>
							<tr>
								<td colspan="3"><?php echo xlt('Not Found'); ?></td>
							</tr>
							<?php
						}
					?>
				</tbody>
			</table>
		</div>
	</div>
</body>
</html>