<?php

require_once(dirname(__FILE__, 7) . "/interface/globals.php");
require_once "$srcdir/patient.inc";
require_once "$srcdir/options.inc.php";
require_once($GLOBALS['srcdir']."/wmt-v3/wmt.globals.php");

use OpenEMR\Common\Csrf\CsrfUtils;
use OpenEMR\Core\Header; 

$formId = isset($_REQUEST['form_id']) ? $_REQUEST['form_id'] : "";

// Option lists
$message_list = new wmt\Options('Email_Messages');
$message_list1 = new wmt\Options('SMS_Messages');

if(isset($_POST['formsubmit'])) {

	$formName = isset($_POST['formName']) ? $_POST['formName'] : "";
	$formSchema = isset($_POST['formSchema']) ? $_POST['formSchema'] : "";
	$formStatus = isset($_POST['formStatus']) ? $_POST['formStatus'] : "";
	$formEmailTemplate = isset($_POST['formEmailTemplate']) ? $_POST['formEmailTemplate'] : "";
	$formSMSTemplate = isset($_POST['formSMSTemplate']) ? $_POST['formSMSTemplate'] : "";
	$formExpireTime = isset($_POST['formExpireTime']) ? $_POST['formExpireTime'] : "";

	if(!empty($formId)) {
		sqlStatement("UPDATE `vh_form_templates` SET `template_name` = ?, `email_template` = ?, `sms_template` = ?, `status` = ?, `expire_time` = ?, `template_content` = ?, `modified_date` = NOW() WHERE `id` = ? ", array($formName, $formEmailTemplate, $formSMSTemplate, $formStatus, $formExpireTime, $formSchema, $formId));
	} else {
		sqlInsert("INSERT INTO `vh_form_templates` ( `uid`, `template_name`, `email_template`, `sms_template`, `to_patient`, `status`, `expire_time`, `template_content`) VALUES ( ?, ?, ?, ?, ?, ?, ?,?)", array($_SESSION['authUserID'], $formName, $formEmailTemplate, $formSMSTemplate, '-1', $formStatus, $formExpireTime, $formSchema));
	}

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

$formSchema = "{}";
if(!empty($formId)) {
	$formData = sqlQuery("SELECT * from vh_form_templates where id = ?", array($formId));
	$formStatus = isset($formData['status']) ? $formData['status'] : "";
	$formSchema = isset($formData['template_content']) && !empty($formData['template_content']) ? $formData['template_content'] : "{}";
	$formEmailTemplate = isset($formData['email_template']) ? $formData['email_template'] : "";
	$formSMSTemplate = isset($formData['sms_template']) ? $formData['sms_template'] : "";
}

?>
<html>
<head>
    <title><?php echo xlt('Create From'); ?></title>

    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.0.2/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.0.2/css/font-awesome.css">
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.0.2/dist/js/bootstrap.min.js"></script>

    <link rel="stylesheet" href="<?php echo $GLOBALS['fm_form_portal_url'] ?>/assets/formio.full.min.css">
    <script src='<?php echo $GLOBALS['fm_form_portal_url'] ?>/assets/formio.full.min.js'></script>

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

		section {
		  height: 100%; /* takes the visible area of the "main" div */
		  overflow: auto; /* recommended */
		  border-bottom: 1px solid;
		  background: lightgreen;
		}

    	.form-details-accordion .accordion-button {
    		background-color: rgba(0,0,0,.03);
		    font-size: 1rem;
		    padding: 0.375rem 0.75rem !important;
		    line-height: 1.8 !important;
		    border: 1px;
    	}
    </style>

    <script type='text/javascript'>
      	window.addEventListener("load", (event) => {
      	  var formInstance =  null;
	      Formio.builder(document.getElementById('builder'), JSON.parse('<?php echo addslashes($formSchema); ?>'), { noDefaultSubmitButton: true }).then(function(form){

	        window.formInstance = form;

	        form.on("change", function(e) {
	        	//document.querySelector('#formSchema').value = JSON.stringify(form.schema);
	          	//console.log(JSON.stringify(form.schema));
	        });
	      });
	  	});
    </script>
</head>
<body>
	<div id="page-container">
		<div class="main-content">
			<div class="header-container container-fluid mt-3">
				<div class="accordion form-details-accordion mb-3" id="form-details-accordion">
				  <div class="accordion-item">
				    <h2 class="accordion-header" id="formDetailsItem">
				      <button class="accordion-button" type="button" data-bs-toggle="collapse" data-bs-target="#collapseOne" aria-expanded="true" aria-controls="collapseOne">
				        <?php echo xlt('Form Details'); ?>
				      </button>
				    </h2>
				    <div id="collapseOne" class="accordion-collapse collapse show" aria-labelledby="formDetailsItem" data-bs-parent="#form-details-accordion">
				      <div class="accordion-body p-3">
				        <form action="<?php echo "create_form_popup.php" ?>" class="row g-3 needs-validation" novalidate name="edit_form" id="edit_form" method="post">
				        	<div class="col-md-6">
				        		<label class="form-label"><?php echo xlt('Form Name'); ?></label>
							    <input type="text" class="form-control" id="formName" name="formName" value="<?php echo isset($formData['template_name']) ? $formData['template_name'] : "" ?>" required>
							    <div class="invalid-feedback">
							        <?php echo xlt('Please enter form name.'); ?>
							    </div>
							</div>
							<div class="col-md-6">
							    <label for="status" class="form-label"><?php echo xlt('Status'); ?></label>
							    <select class="form-select" name="formStatus">
							      <option value="1" <?php echo $formStatus == "1" ? "selected" : "" ?>>Active</option>
								  <option value="0" <?php echo $formStatus == "0" ? "selected" : "" ?>>InActive</option>
								</select>
								<div class="invalid-feedback">
							        <?php echo xlt('Please choose status.'); ?>
							    </div>
							</div>

							<div class="col-md-4">
								<label class="form-label"><?php echo xlt('Email Template'); ?></label>
								<select id="form_email_template" name="formEmailTemplate" class='form-control'>
									<option value=""><?php echo xlt('Select Please'); ?></option>
									<?php $message_list->showOptions($formEmailTemplate); ?>
								</select>
							</div>

							<div class="col-md-4">
								<label class="form-label"><?php echo xlt('SMS Template'); ?></label>
								<select id="form_sms_template" name="formSMSTemplate" class='form-control'>
									<option value=""><?php echo xlt('Select Please'); ?></option>
									<?php $message_list1->showOptions($formSMSTemplate); ?>
								</select>
							</div>
							
							<div class="col-md-4">
								<label class="form-label"><?php echo xlt('Expire Time'); ?></label>
								<input type="text" class="form-control" id="formExpireTime" name="formExpireTime" value="<?php echo isset($formData['expire_time']) ? $formData['expire_time'] : "" ?>" required>
							    <div class="invalid-feedback">
							        <?php echo xlt('Please enter expire time.'); ?>
							    </div>
							</div>

							<textarea name="formSchema" id="formSchema" style="display:none;"><?php echo $formSchema ?></textarea>

							<?php if(!empty($formId)) { ?>
							<input type="hidden" name="form_id" value="<?php echo $formId; ?>">
							<?php } ?>

							<div class="col-12" style="display:none;">
							    <button class="btn btn-primary" id="btnFormSubmit" type="submit" value="submit" name="formsubmit" ><?php echo xlt('Submit form'); ?></button>
							</div>
				        </form>
				      </div>
				    </div>
				  </div>
				</div>
			</div>
			<div id="builder" class="container-fluid"></div>
		</div>
		<div class="footer-container container-fluid bg-white border-top">
			<div class="pt-2 pb-2 d-flex flex-row-reverse">
				<button class="btn btn-primary px-4 me-4" id="btnSubmit" type="button" ><?php echo xlt('Save'); ?></button>
				<button class="btn btn-secondary px-4 me-3" id="btnCancel" type="button"><?php echo xlt('Cancel'); ?></button>
			</div>
		</div>
	</div>

	<script type="text/javascript">
		(function () {
		  'use strict'

		  // Fetch all the forms we want to apply custom Bootstrap validation styles to
		  var forms = document.querySelectorAll('.needs-validation')

		  // Loop over them and prevent submission
		  Array.prototype.slice.call(forms)
		    .forEach(function (form) {
		      form.addEventListener('submit', function (event) {
		        if (!form.checkValidity()) {
		          event.preventDefault()
		          event.stopPropagation()
		        }

		        form.classList.add('was-validated')
		      }, false)
		    })

		  document.querySelector('#btnCancel').addEventListener('click', function (event) {
		  	window.close();
		  });

		  document.querySelector('#btnSubmit').addEventListener('click', function (event) {
		  	document.querySelector('#formSchema').value = JSON.stringify(formInstance.schema);
		  	document.querySelector('#btnFormSubmit').click();
		  });
		})()
	</script>
</body>
</html>