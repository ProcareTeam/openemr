<?php

require_once(dirname(__FILE__, 7) . "/interface/globals.php");
require_once "$srcdir/patient.inc";
require_once "$srcdir/options.inc.php";

use OpenEMR\Common\Csrf\CsrfUtils;
use OpenEMR\Core\Header; 
use Vitalhealthcare\OpenEMR\Modules\Generic\Controller\FormController;

$patientForm = new FormController();

$formMode = isset($_REQUEST['form_mode']) ? $_REQUEST['form_mode'] : "";
$templateId = isset($_REQUEST['template_id']) ? $_REQUEST['template_id'] : "";
$formDataId = isset($_REQUEST['form_data_id']) ? $_REQUEST['form_data_id'] : "";
$pid = isset($_REQUEST['pid']) ? $_REQUEST['pid'] : "";
$needToDisableReview = isset($_REQUEST['disable_review']) && $_REQUEST['disable_review'] == "1" ? true : false;
$formDataItemId = "";

if(isset($_POST['form_mode']) && $_POST['form_mode'] == "review") {

	$patientForm->updateOnsiteForms(array(
		"status" => FormController::REVIEW_LABEL,
		"reviewed_date" => "CURRENT_TIMESTAMP"
	), $formDataId);

	?>
	<html>
	<head></head>
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

try {

	if(empty($formMode) && empty($formDataId) && empty($templateId)) {
		echo xlt('Error');
		exit();
	}

	if(!empty($formDataId)) {
		$fullFormData = $patientForm->getFullFormData($pid, $formDataId, true);
		$extraFormDetails = sqlQuery("SELECT vof.`ref_id`, vfdl.`type` from vh_form_data_log vfdl join vh_onsite_forms vof on vof.ref_id = vfdl.id where vof.id = ? order by vof.id ASC", array($formDataId));

		if(!empty($extraFormDetails)) {
			$formDataItemId = $extraFormDetails['ref_id'];
		}

	} else if(!empty($templateId)) {
		$fullFormData = $patientForm->getFormPreview($templateId);

		if(empty($fullFormData)) {
			echo xlt('Error');
			exit();
		}
	}

} catch (\Throwable $e) {
    echo xlt('Error');
	exit();
}

$formSchema = isset($fullFormData['schema']) && !empty($fullFormData['schema']) ? json_encode($fullFormData['schema']) : "{}";
$formData = isset($fullFormData['data']) && !empty($fullFormData['data']) ? json_encode($fullFormData['data']) : "{}";
$formDetails = isset($fullFormData['form_details']) && !empty($fullFormData['form_details']) ? $fullFormData['form_details'] : array();
$formStatus = isset($formDetails['status']) ? $formDetails['status'] : "";

?>
<html>
<head>
    <title><?php echo xlt('Create From'); ?></title>

    <script type="text/javascript" src="<?php echo $GLOBALS['webroot']; ?>/public/assets/jquery/dist/jquery.min.js"></script>

    <script type="text/javascript" src="<?php echo $GLOBALS['webroot']; ?>/library/js/utility.js"></script>


    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.0.2/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.0.2/css/font-awesome.css">
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.0.2/dist/js/bootstrap.min.js"></script>

    <link rel="stylesheet" href="<?php echo $GLOBALS['fm_form_portal_url'] ?>/assets/formio.full.min.css">
    <link rel="stylesheet" href="<?php echo $GLOBALS['fm_form_portal_url'] ?>/assets/styles.css">
    <script src='<?php echo $GLOBALS['fm_form_portal_url'] ?>/assets/formio.full.min.js'></script>

    <script type="text/javascript" src="../../public/assets/js/dialog.js"></script>

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

		body {
			background: #f3f5f7;
		}
    </style>

    <script type='text/javascript'>
      	window.addEventListener("load", (event) => {
      	  var formInstance =  null;

      	  var builderOption = { readOnly: true };

      	  <?php if(in_array($formStatus, array(FormController::SUBMIT_LABEL))) { ?>
      	  var builderOption = { readOnly: true, fileDownloadClickEvent:  true, isFileDownloadEnable: false };
      	  <?php } else if(in_array($formStatus, array(FormController::PREVIEW_LABEL))) { ?>
      	  var builderOption = { readOnly: false };	
      	  <?php } ?>

      	  <?php if($formMode === "fill_req_field") { ?>
      	  	builderOption['reqFieldMode'] = true;
      	  <?php } ?>

	      Formio.createForm(document.getElementById('builder'), JSON.parse('<?php echo addslashes($formSchema); ?>'), builderOption).then(function(form){

	      	<?php if($formMode === "fill_req_field") { ?>
	      		form.submission = opener.getFormData('<?php echo $templateId; ?>');
	      	<?php } else { ?>	
	      		form.submission = JSON.parse('<?php echo addslashes($formData); ?>');
	      	<?php } ?>

	        window.formInstance = form;

	        form.on("fileDownloadClick", function(fileItem) {
	        	if(fileItem.hasOwnProperty('document')) {
	        		const documentId = fileItem.document.hasOwnProperty('id') ? fileItem.document.id : "";

	        		if(documentId != "") {
		        		dlgopen('<?php echo $GLOBALS['webroot']; ?>/controller.php?document&view&patient_id=<?php echo $pid; ?>&doc_id=' + documentId, 'docpop', 'modal-full', '700', false, 'Document', {
			                sizeHeight: 'full',
			                onClosed: 'onClosed'
			            });
	        		}
	        	}
	        });
	      });
	  	});

	  	function reqFieldFormSubmit() {
	  		const formData = formInstance.submission.hasOwnProperty('data') ? { data : formInstance.submission.data } : {};

            // Check Form Validation
      		formInstance.emit('submitButton');
      		let isFormValid = formInstance.checkValidity(formInstance.submission.data);

      		if(isFormValid === true) {
      			window.close();
            	opener.set_ReqFormField('<?php echo $templateId ?>', formData);
      		}
	  	}

	  	function onClosed() {
    		window.location.reload();
    	}

    	function setreject(form_data_id) {
    		opener.closeRefresh();
			window.close();
    	}

    	async function rejectForm(form_data_id) {
    		let btnClose = <?php echo xlj("Cancel"); ?>;
            let title = <?php echo xlj("Reject Form"); ?>;
            let url = './lib/reject_popup.php?';

            if(form_data_id != "") url += "form_data_id=" + form_data_id;

            // leave dialog name param empty so send dialogs can cascade.
            dlgopen(url, '', 'modal-md', 450, '', title);
    	}

    </script>
</head>
<body>
	<div id="page-container">
		<div class="main-content">
			<form action="<?php echo "render_form_popup.php" ?>" name="render_form_popup" id="render_form_popup" method="post">
				<input type="hidden" name="form_data_id" value="<?php echo $formDataId; ?>">
				<input type="hidden" name="form_mode" value="">
			</form>
			<div class="pt-8 pb-2">
				<div class="container main-container max-w-screen-xl">
					<div class="px-6 mb-8 text-center">
						<h3 class="text-4xl text-body"><?php echo isset($formDetails['form_title']) ? $formDetails['form_title'] : ""; ?></h3>
					</div>
					<div id="builder" class="container-fluid"></div>
				</div>
			</div>
		</div>
		<div class="footer-container container-fluid bg-white border-top">
			<div class="pt-2 pb-2 d-flex flex-row-reverse">
				<?php if($formMode === "fill_req_field") { ?>
					<button class="btn btn-primary px-4 me-3" id="btnReqSubmit" type="button" onclick="reqFieldFormSubmit()"><?php echo xlt('Submit'); ?></button>
					<button class="btn btn-secondary px-4 me-3" id="btnCancel" type="button"><?php echo xlt('Close'); ?></button>
				<?php } else if($needToDisableReview === true) { ?>
					<button class="btn btn-secondary px-4 me-3" id="btnCancel" type="button"><?php echo xlt('Close'); ?></button>
				<?php } else if(in_array($formStatus, array(FormController::SAVE_LABEL, FormController::PENDING_LABEL, FormController::REVIEW_LABEL))) { ?>
					<button class="btn btn-secondary px-4 me-3" id="btnCancel" type="button"><?php echo xlt('Close'); ?></button>
				<?php } else if(in_array($formStatus, array(FormController::SUBMIT_LABEL))) { ?>
					<button class="btn btn-primary px-4 me-4" id="btnSubmit" type="button" onclick="reviewSummary()" ><?php echo xlt('Review'); ?></button>
					<button class="btn btn-primary px-4 me-3" id="btnSubmit" type="button" onclick="rejectForm('<?php echo $formDataItemId; ?>')" ><?php echo xlt('Reject'); ?></button>
					<button class="btn btn-secondary px-4 me-3" id="btnCancel" type="button"><?php echo xlt('Cancel'); ?></button>
				<?php } else if(in_array($formStatus, array(FormController::PREVIEW_LABEL))) { ?>
					<button class="btn btn-secondary px-4 me-3" id="btnCancel" type="button"><?php echo xlt('Close'); ?></button>
				<?php } ?>
			</div>
		</div>
	</div>

	<script type="text/javascript">
		function reviewSummary() {
            // dlgopen('review_summary.php?pid=<?php echo $pid; ?>&form_data_id=<?php echo $formDataItemId; ?>', '', 'modal-full', '700', false, 'Summary', {
            //     sizeHeight: 'full',
            //     onClosed: ''
            // });
            window.close();
            opener.dlReviewSummary('<?php echo $pid ?>', '<?php echo $formDataItemId; ?>');
		}

		function makeReview() {
		  	var f = document.forms[0];
		  	f.form_mode.value = "review";
		  	f.submit();
		}

		function closeRefresh() {
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

		  document.querySelector('#btnCancel').addEventListener('click', function (event) {
		  	window.close();
		  });
		  
		})()
	</script>
</body>
</html>