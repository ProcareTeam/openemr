<?php

require_once(dirname(__FILE__, 7) . "/interface/globals.php");
require_once "$srcdir/patient.inc";
require_once "$srcdir/options.inc.php";
require_once("$srcdir/patient.inc");

use OpenEMR\Common\Csrf\CsrfUtils;
use OpenEMR\Core\Header;
use Vitalhealthcare\OpenEMR\Modules\Generic\Controller\FormController;

$formPid = isset($_REQUEST['pid']) ? $_REQUEST['pid'] : "";
$formDataId = isset($_REQUEST['form_data_id']) ? $_REQUEST['form_data_id'] : "";
$formType = isset($_REQUEST['form_type']) ? $_REQUEST['form_type'] : FormController::FORM_LABEL;
$ajax_action = isset($_REQUEST['ajax_action']) ? trim(strip_tags($_REQUEST['ajax_action'])) : "";


$patientForm = new FormController(); 

if(!empty($ajax_action)) {

    if($ajax_action == "generate_pdf") {
    	$formAssocResult = $patientForm->getFormAssocItems($formDataId);

    	foreach ($formAssocResult as $formassocrow) {
	    	if(isset($formassocrow['id'])) {

		    	$formDetails = $patientForm->getUserFormData($formassocrow['id'], $formPid);
				$formDetails = count($formDetails) > 0 ? $formDetails[0] : array();
				$formLogDataDetails = isset($formDetails['form_data']) ? $formDetails['form_data'] : array();

				$formTemplateDetails = isset($formDetails['template']) ? $formDetails['template'] : array();


				$docLogItems = $patientForm->getFormDocumentsLog($formassocrow['id']);
				$regeneratePDF = false;

				if(empty($docLog)) {
					$regeneratePDF = true;
				} else if(isset($formLogDataDetails['received_date']) && !empty($formLogDataDetails['received_date']) && !empty($docLog)) {
					$dlItem = reset($docLog);

					if(isset($dlItem['created_date']) && !empty($dlItem['created_date'])) {
						if(strtotime($dlItem['created_date']) < strtotime($formLogDataDetails['received_date'])) {
							$regeneratePDF = true;
						}
					}
				}

				$isEnableToReview = isset($formDetails['status']) && in_array($formDetails['status'], array(FormController::SUBMIT_LABEL)) ? true : false;

				if($isEnableToReview === true && $regeneratePDF === true && !empty($formTemplateDetails)) {
					$formDocData =  $patientForm->getFormDocumentsLog($formassocrow['id']);
				    $tName = isset($formTemplateDetails['template_name']) ? $formTemplateDetails['template_name'] : "";

				    if(!empty($tName)) {
				    	$patientResult = getPatientData( $formDetails['form_data']['pid'], "pubpid");

						$patientForm->saveFormPDF($formassocrow['id'], $formPid, $tName, array("submitted_on" => $formDetails['form_data']['received_date'], "pubpid" => $patientResult['pubpid'])); 
					}
				}
			}
		}

		echo json_encode(array("status" => "Success"));
    }

    exit();
}


if(isset($_POST['form_mode']) && $_POST['form_mode'] == "review") {

	$formAssocResult = $patientForm->getFormAssocItems($formDataId);

	foreach ($formAssocResult as $formassocrow) {
    	if(isset($formassocrow['id'])) {

    		// Update Status
    		$patientForm->updateOnsiteForms(array(
				"status" => FormController::REVIEW_LABEL,
				"reviewed_date" => "CURRENT_TIMESTAMP",
				"reviewer" => $_SESSION['authUserID']
			), $formassocrow['id']);
    	}
    }

	?>
	<html>
	<head>
		<?php Header::setupHeader(['opener', 'jquery']); ?>
	</head>
	<body>
		<?php echo "<i>Saving....</i>"; ?>
		<script type="text/javascript">
			function selformpopup() {
				if (opener.closed || ! opener.closeRefresh) {
				   	alert('The destination form was closed; I cannot act on your selection.');
				} else {
				   	opener.closeRefresh();
				  	window.close();
				  	return false;
				}
			}

			(function () {
		  		'use strict'
				selformpopup();
			})();
		</script>
	</body>
	</html>
	<?php
	exit();
}

$formSummaryDetails = array();
$assoiatedForms = array();
$docLog = array();
$attachList = array();
$isEnableToReview = false;
$regeneratePDF = false;

/*if($formType == FormController::FORM_LABEL) {
	$formFieldData =  $patientForm->getFormFieldComponent($formPid, $formDataId);
	$formInfoData =  $patientForm->getFullFormData($formPid, $formDataId, true);
	$formDetails = $patientForm->getUserFormData($formDataId, $formPid);
	$formDetails = count($formDetails) > 0 ? $formDetails[0] : array();

	//$formTemplateDetails = isset($formDetails['template']) ? $formDetails['template'] : array();
	$formLogDataDetails = isset($formDetails['form_data']) ? $formDetails['form_data'] : array();
	$formData = isset($formInfoData['data']) && isset($formInfoData['data']['data']) ? $formInfoData['data']['data'] : array();
	$formComponents = isset($formFieldData['components']) ? $formFieldData['components'] : array();

	$isEnableToReview = isset($formDetails['status']) && in_array($formDetails['status'], array(FormController::SUBMIT_LABEL)) ? true : false;

	$formSummaryDetails['status'] = $formDetails['status'];
	$formSummaryDetails['received_date'] = $formLogDataDetails['received_date'];
	$formSummaryDetails['created_date'] = $formLogDataDetails['created_date'];
	$formSummaryDetails['reviewed_date'] = $formLogDataDetails['reviewed_date'];
	$formSummaryDetails['reviewed'] = $formLogDataDetails['reviewed'];

	$docLog = $patientForm->getFormDocumentsLog($formDataId);
	$regeneratePDF = false;

	if(isset($formSummaryDetails['received_date']) && !empty($formSummaryDetails['received_date']) && !empty($docLog)) {
		$dlItem = reset($docLog);

		if(isset($dlItem['created_date']) && !empty($dlItem['created_date'])) {
			if(strtotime($dlItem['created_date']) < strtotime($formSummaryDetails['received_date'])) {
				$regeneratePDF = true;
			}
		}
	}
} else if($formType == FormController::PACKET_LABEL) {*/

	$formresults1 = sqlQuery("SELECT DISTINCT vof.ref_id as id, vof.created_date, vof.reviewed_date, (SELECT vof1.id from vh_onsite_forms vof1 where vof1.ref_id = vof.ref_id order by case when vof1.status = 'rejected' then 5 when vof1.status = 'reviewed' then 4 when vof1.status = 'submited' then 3 when vof1.status = 'saved' then 2 when vof1.status = 'pending' then 1 end desc limit 1) as item_id FROM vh_onsite_forms vof WHERE vof.ref_id =  ? " , array($formDataId));
	$item_id = isset($formresults1['item_id']) && !empty($formresults1['item_id']) ? $formresults1['item_id'] : 0;

	//$formresults = sqlQuery("SELECT vfdl.`type`, vfdl.id, vof.id, vof.status, vof.created_date, vof.reviewed_date, vof.received_date, vof.reviewer from vh_form_data_log vfdl join vh_onsite_forms vof on vof.id = (SELECT vof1.id from vh_onsite_forms vof1 where vof1.ref_id = vfdl.id order by case when vof1.status = 'rejected' then 5 when vof1.status = 'reviewed' then 4 when vof1.status = 'submited' then 3 when vof1.status = 'saved' then 2 when vof1.status = 'pending' then 1 end  desc limit 1) where vof.id = ? " , array($item_id));

	$formresults = sqlQuery("SELECT vfdl.`type`, vfdl.id, vof.id, vof.status, vof.created_date, vof.reviewed_date, vof.received_date, vof.reviewer from vh_form_data_log vfdl join vh_onsite_forms vof on vof.ref_id  = vfdl.id where vof.id = ? " , array($item_id));

	if(!empty($formresults)) {
		$formSummaryDetails['status'] = $formresults['status'];
		$formSummaryDetails['received_date'] = $formresults['received_date'];
		$formSummaryDetails['created_date'] = $formresults['created_date'];
		$formSummaryDetails['reviewed_date'] = $formresults['reviewed_date'];
		$formSummaryDetails['reviewed'] = $formresults['reviewed'];
		$formSummaryDetails['type'] = $formresults['type'];
	}

	$formAssocResult = $patientForm->getFormAssocItems($formDataId);
	$cItems = count($formAssocResult) ? $formAssocResult : 0;

	foreach ($formAssocResult as $formassocrow) {
    	if(isset($formassocrow['id'])) {
    		$formFieldData =  $patientForm->getFormFieldComponent($formPid, $formassocrow['id']);
			$formInfoData =  $patientForm->getFullFormData($formPid, $formassocrow['id'], true);

			$formData = isset($formInfoData['data']) && isset($formInfoData['data']['data']) ? $formInfoData['data']['data'] : array();
			$formComponents = isset($formFieldData['components']) ? $formFieldData['components'] : array();

			$docLogItems = $patientForm->getFormDocumentsLog($formassocrow['id']);

			// Check For Enable Review button
			if(isset($formassocrow['status']) && in_array($formassocrow['status'], array(FormController::SUBMIT_LABEL))) {
				$isEnableToReview = true;
			}

			// Check for generation
			if(empty($docLogItems)) {
				$regeneratePDF = true;
			} else if(isset($formassocrow['received_date']) && !empty($formassocrow['received_date']) && !empty($docLogItems)) {
				$dlItem = reset($docLogItems);

				if(isset($dlItem['created_date']) && !empty($dlItem['created_date'])) {
					if(strtotime($dlItem['created_date']) < strtotime($formassocrow['received_date'])) {
						$regeneratePDF = true;
					}
				}
			}

			if(isset($_REQUEST['ajax_action']) && $_REQUEST['ajax_action'] == "refresh_pdf") {
				$regeneratePDF = false;
			}

			// Assign doc items log
			if(!empty($docLogItems)) {
				$docLog = array_merge($docLog, $docLogItems);
			}

			foreach ($formFieldData as $fdk => $fdItem) {
				if(isset($fdItem['type']) && $fdItem['type'] === "file") {
					$fileData = isset($formData[$fdItem['key']]) ? $formData[$fdItem['key']] : array();

					$fdItem['file_data'] = $fileData;
					$attachList[] = $fdItem;
				}
			}

			if($formassocrow['type'] == "packet") {
				$formTemplate = $patientForm->getFormTemplates($formassocrow['form_id']);
				$formassocrow['template_name'] = $formTemplate[0]['template_name'];
				$assoiatedForms[] = $formassocrow;
			}
    	}
    }

/*}*/


?>
<html>
<head>
    <?php Header::setupHeader(['dialog', 'opener', 'main_theme', 'main-theme', 'jquery']); ?>
    <title><?php echo xlt('Review Summary'); ?></title>

    <script type="text/javascript" src="../../public/assets/js/dialog.js"></script>

    <script type="text/javascript">
    	function onClosed() {
    		window.location.reload();
    	}

    	function editDocument(documentId, pid) {
    		dlgopen('<?php echo $GLOBALS['webroot']; ?>/controller.php?document&view&patient_id=' + pid + '&doc_id=' + documentId, 'docpop', 'modal-lg', '700', '', 'Document', {
			                onClosed: 'onClosed'
			            });
    	}
    </script>

    <style type="text/css">
    	#page-container {
		  display: flex; /* displays flex-items (children) inline */
		  flex-direction: column; /* stacks them vertically */
		  height: 100%; /* needs to take the parents height, alternative: body {display: flex} */
		}

		.main-content {
		  flex: 1; /* takes the remaining height of the "container" div */
		  overflow: auto; /* to scroll just the "main" div */
		}

		.loader-container {
			width: 100%;
		    height: 100%;
		    display: grid;
		    justify-items: center;
		    align-items: center;
		}
    </style>
</head>
<body>

	<?php if($isEnableToReview === true && (empty($docLog) || $regeneratePDF === true)) { ?>
	<div class="loader-container">
		<div class="spinner-border text-primary" role="status">
		  <span class="sr-only">Loading...</span>
		</div>
	</div>
	<?php } else { ?>
	<div id="page-container">
		<div class="main-content">
			<form action="<?php echo "review_summary.php" ?>" name="render_form_popup" id="render_form_popup" method="post">
				<input type="hidden" name="form_data_id" value="<?php echo $formDataId; ?>">
				<input type="hidden" name="form_type" value="<?php echo $formType; ?>">
				<input type="hidden" name="form_mode" value="">
			</form>
			<div class="container-fluid">
				<div class="mb-3">
					<label class="col-form-label" ref="label"><?php echo xlt('Form Details'); ?></label>
					<ul class="list-group list-group-striped">
						<li class="list-group-item">
							<div class="row">
								<div class="col-md-3">
									<strong><?php echo xlt('Created Date'); ?></strong>
								</div>
								<div class="col-md-9">
									<?php echo isset($formSummaryDetails['created_date']) ? $formSummaryDetails['created_date'] : "<i>Empty</i>"; ?>
								</div>
							</div>
						</li>
						<li class="list-group-item">
							<div class="row">
								<div class="col-md-3">
									<strong><?php echo xlt('Submited Date'); ?></strong>
								</div>
								<div class="col-md-9">
									<?php echo isset($formSummaryDetails['received_date']) ? $formSummaryDetails['received_date'] : "<i>Empty</i>"; ?>
								</div>
							</div>
						</li>
						<?php if($formDetails['status'] == FormController::REVIEW_LABEL) { ?>
						<li class="list-group-item">
							<div class="row">
								<div class="col-md-3">
									<strong><?php echo xlt('Reviewed Date'); ?></strong>
								</div>
								<div class="col-md-9">
									<?php echo isset($formSummaryDetails['reviewed_date']) ? $formSummaryDetails['reviewed_date'] : "<i>Empty</i>"; ?>
								</div>
							</div>
						</li>

						<li class="list-group-item">
							<div class="row">
								<div class="col-md-3">
									<strong><?php echo xlt('Reviewed By'); ?></strong>
								</div>
								<div class="col-md-9">
									<?php echo isset($formSummaryDetails['reviewed']) ? $formSummaryDetails['reviewed'] : "<i>Empty</i>"; ?>
								</div>
							</div>
						</li>
						<?php } ?>

						<li class="list-group-item">
							<div class="row">
								<div class="col-md-3">
									<strong><?php echo xlt('Status'); ?></strong>
								</div>
								<div class="col-md-9">
									<?php echo isset($formSummaryDetails['status']) ? strtoupper($formSummaryDetails['status']) : "<i>Empty</i>"; ?>
								</div>
							</div>
						</li>
					</ul>
				</div>

				<?php if(!empty($assoiatedForms)) { ?>
				<div class="mb-2">
					<label class="col-form-label" ref="label"><?php echo xlt('Associated Forms'); ?></label>
					<ul class="list-group list-group-striped">
						<li class="list-group-item list-group-header hidden-xs hidden-sm">
							<div class="row">
								<div class="col-md-8"><strong>Name</strong></div>
						        <div class="col-md-4"><strong>Status</strong></div>
						    </div>
						</li>
						<?php
							foreach ($assoiatedForms as $asscItem) {
								?>
								<li class="list-group-item">
									<div class="row">
										<div class="col-md-8">
											<a href="#!" class='linktext' onclick="renderForm('<?php echo $asscItem['id'] ?>', '<?php echo $asscItem['pid'] ?>', 'form', true);"><?php echo $asscItem["template_name"] ?></a>
										</div>
										<div class="col-md-4">
											<?php echo strtoupper($asscItem["status"]); ?>
										</div>
									</div>
								</li>
								<?php
							}
						?>
					</ul>
				</div>
				<?php } ?>

				<?php
				if(!in_array($formSummaryDetails['status'], array(FormController::SAVE_LABEL, FormController::PENDING_LABEL))) {
				?>
				<div class="mb-2">
					<label class="col-form-label" ref="label"><?php echo xlt('Form PDF'); ?></label>
					<ul class="list-group list-group-striped">
						<li class="list-group-item list-group-header hidden-xs hidden-sm">
							<div class="row">
								<div class="col-md-6"><strong>File Name</strong></div>
						        <div class="col-md-4"><strong>Categories</strong></div>
						        <div class="col-md-1"><strong>Size</strong></div>
						        <div class="col-md-1"></div>
						    </div>
						</li>
						<?php
							foreach ($docLog as $docItem) {
								if(isset($docItem['doc_id']) && !empty($docItem['doc_id'])) {
									$d = $patientForm->getFormDocument($docItem['doc_id']);
									?>
									<li class="list-group-item">
										<div class="row">
											<div class="col-md-6">
												<div class="mr-2 d-inline">
													<?php if($isEnableToReview === true) { ?>
														<input type="checkbox" id="cdoc_<?php echo $docItem['doc_id']; ?>" class="cdoc_item" value="1">
													<?php } ?>
												</div>
												<span><?php echo $d->document->get_name() . " (" . $d->document->get_date() . ")"; ?></span>
								          	</div>
								          	<div class="col-md-4"><?php echo implode(", ", $d->categories); ?></div>
								          	<div class="col-md-1"><?php echo $patientForm->size_as_kb($d->document->get_size()); ?></div>
								          	<div class="col-md-1">
								          		<?php if($isEnableToReview === true) { ?>
								          		<a href="javascript:void(0);" onclick="editDocument('<?php echo $docItem['doc_id'] ?>', '<?php echo $formPid ?>')"><?php echo xlt('Edit'); ?></a>
								          		<?php } ?>
								          	</div>
								        </div>
									</li>
									<?php
								}
							}
						?>
					</ul>
				</div>
				<?php } ?>

				<?php
				if(!in_array($formSummaryDetails['status'], array(FormController::SAVE_LABEL, FormController::PENDING_LABEL))) {
				foreach ($attachList as $fdk => $fdItem) {
					//if(isset($fdItem['type']) && $fdItem['type'] === "file") {
						$fileData = isset($fdItem['file_data']) ? $fdItem['file_data'] : array();
						?>
						<div>
							<label class="col-form-label" ref="label"><?php echo $fdItem['label']; ?></label>
							<ul class="list-group list-group-striped">
								<li class="list-group-item list-group-header hidden-xs hidden-sm">
									<div class="row">
										<div class="col-md-6"><strong>File Name</strong></div>
								        <div class="col-md-4"><strong>Categories</strong></div>
								        <div class="col-md-1"><strong>Size</strong></div>
								        <div class="col-md-1"></div>
								    </div>
								</li>
								<?php
									foreach ($fileData as $fItem) {
										$documentId = isset($fItem['document']) && $fItem['document']['id'] ? $fItem['document']['id'] : "";
										?>
										<li class="list-group-item">
											<div class="row">
												<div class="col-md-6">
													<div class="mr-2 d-inline">
														<?php if($isEnableToReview === true) { ?>
															<input type="checkbox" class="cdoc_item" id="cdoc_<?php echo $documentId; ?>" value="1">
														<?php } ?>
													</div>
													<span><?php echo $fItem['originalName']; ?></span>
									          	</div>
									          	<div class="col-md-4"><?php echo implode(", ", $fItem['categories']); ?></div>
									          	<div class="col-md-1"><?php echo $patientForm->size_as_kb($fItem['size']); ?></div>
									          	<div class="col-md-1">
									          		<?php if($isEnableToReview === true) { ?>
									          		<a href="javascript:void(0);" onclick="editDocument('<?php echo $documentId ?>', '<?php echo $formPid ?>')"><?php echo xlt('Edit'); ?></a>
									          		<?php } ?>
									          	</div>
									        </div>
										</li>
										<?php
									}
								?>
							</ul>
						</div>
						<?php
					//}
				}
				}
				?>
			</div>
		</div>
		<div class="footer-container container-fluid bg-white border-top">
			<div class="pt-2 pb-2 d-flex flex-row-reverse">
				<?php if($isEnableToReview === true) { ?>
				<button class="btn btn-primary px-4 ml-2" id="btnSubmit" type="button" onclick="makeReview()" disabled ><?php echo xlt('Confirm Review'); ?></button>
				<?php } ?>
				<?php if($formSummaryDetails['type'] == 'packet') { ?>
				<button class="btn btn-primary px-4 me-3 ml-2" id="btnSubmit" type="button" onclick="rejectForm('<?php echo $formDataId; ?>')" ><?php echo xlt('Reject'); ?></button>
				<?php } ?>	
				<button class="btn btn-secondary px-4 ml-2" id="btnCancel" type="button"><?php echo xlt('Cancel'); ?></button>
			</div>
		</div>
	</div>
	<?php } ?>

	<script type="text/javascript">
		function makeReview() {
		  	var f = document.forms[0];
		  	f.form_mode.value = "review";
		  	f.submit();
		}

		function setreject(form_data_id) {
    		opener.closeRefresh();
    		dlgclose();
    	}

    	async function rejectForm(form_data_id) {
    		let btnClose = <?php echo xlj("Cancel"); ?>;
            let title = <?php echo xlj("Reject Form"); ?>;
            let url = './lib/reject_popup.php?';

            if(form_data_id != "") url += "form_data_id=" + form_data_id;

            // leave dialog name param empty so send dialogs can cascade.
            dlgopen(url, '', 'modal-md', 450, '', title);
    	}

		function generatePDF(form_data_id, pid, form_type = '') {
	        var data = [];
	        data.push({ name: "ajax_action", value: "generate_pdf" });
	        data.push({ name: "form_type", value: form_type });
	        data.push({ name: "form_data_id", value: form_data_id });
	        data.push({ name: "pid", value: pid });

	        let pdfAjaxRequest = $.ajax({
	            url: "review_summary.php",
	            method: "POST",
	            data: $.param(data),
	            success: function(result) {
	                let dataJSON = JSON.parse(result);
	                location.reload();
	            },                      
	        });
	    }

	    $(document).ready(function() {
	    	<?php if($isEnableToReview === true && (empty($docLog) || $regeneratePDF === true)) { ?>
			generatePDF('<?php echo $formDataId; ?>', '<?php echo $formPid; ?>', '<?php echo $formType; ?>');
		  <?php } ?>
		});

		(function () {
		  'use strict'

		  	// Check All check box marked or not
		  	document.querySelectorAll('.cdoc_item').forEach(function(cItem) {
			    cItem.addEventListener('change', function (event) {
			    	let isChecked = true;
			    	document.querySelectorAll('.cdoc_item').forEach(function(cItem2) {
			    		if(cItem2.checked === false) {
			    			isChecked = false;
			    		}
					});

			    	if(isChecked === true) {
			    		document.querySelector('#btnSubmit').disabled = false;
			    	} else {
			    		document.querySelector('#btnSubmit').disabled = true;
			    	}
					
			    });
			});
		  

		  document.querySelector('#btnCancel').addEventListener('click', function (event) {
		  	dlgclose();
		  });
		  
		})();
	</script>
</body>
</html>