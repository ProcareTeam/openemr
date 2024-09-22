<?php

require_once(dirname(__FILE__, 7) . "/interface/globals.php");
require_once "$srcdir/patient.inc";
require_once "$srcdir/options.inc.php";

use OpenEMR\Common\Csrf\CsrfUtils;
use OpenEMR\Core\Header;
use Vitalhealthcare\OpenEMR\Modules\Generic\Controller\FormController;

if(isset($_POST['form_action'])) {
	$formAction = isset($_POST['form_action']) ? $_POST['form_action'] : "";
	$formId = isset($_POST['form_id']) ? $_POST['form_id'] : "";

	if($formAction == "delete") {
		sqlStatement("DELETE FROM `vh_form_templates` WHERE `id` = ? ", array($formId));
		header('Location: '.$_SERVER['REQUEST_URI']);
		exit();
	} else if($formAction == "do_active") {
		sqlStatement("UPDATE `vh_form_templates` SET `status` = 1 WHERE `id` = ? ", array($formId));
	} else if($formAction == "do_inactive") {
		sqlStatement("UPDATE `vh_form_templates` SET `status` = 0 WHERE `id` = ? ", array($formId));
	} else if($formAction == "do_clone") {
		$patientForm = new FormController();
		$formTemplate = $patientForm->getFormTemplates($formId);

		if(!empty($formTemplate)) {
			$formTemplate = $formTemplate[0];

			$formName = isset($formTemplate['template_name']) ? $formTemplate['template_name'] . " - Clone " : "";
			$formSchema = isset($formTemplate['template_content']) ? $formTemplate['template_content'] : "";
			$formStatus = "1";
			$formEmailTemplate = isset($formTemplate['email_template']) ? $formTemplate['email_template'] : "";
			$formSMSTemplate = isset($formTemplate['sms_template']) ? $formTemplate['sms_template'] : "";

			sqlInsert("INSERT INTO `vh_form_templates` ( `uid`, `template_name`, `email_template`, `sms_template`, `to_patient`, `status`, `template_content`) VALUES ( ?, ?, ?, ?, ?, ?, ?)", array($_SESSION['authUserID'], $formName, $formEmailTemplate, $formSMSTemplate, '-1', $formStatus, $formSchema));
		}
	} else if($formAction == "do_pdf_download") {
		$patientForm = new FormController();
		$pdfResponce = $patientForm->getFormPDF('', '', $formId);
		
		if(isset($pdfResponce['content'])) {
			echo $pdfResponce['content'];
		}
		exit();
	}
}

$formItems = array();  
$form_result = sqlStatementNoLog("SELECT * from `vh_form_templates` order by id desc", array());
while ($formrow = sqlFetchArray($form_result)) {
    $formItems[] = $formrow;
}

?>
<html>
<head>
    <?php Header::setupHeader(['datetime-picker', 'opener', 'jquery', 'jquery-ui', 'datatables', 'datatables-colreorder', 'datatables-bs']); ?>
    <title><?php echo xlt('Form Manager'); ?></title>
    <script>

        function handleCreateForm(form_id = "") {
        	var target = './create_form_popup.php';

        	if(form_id != "") {
        		target += '?form_id='+form_id;
        	}

			dialog.popUp(target, null, 'formpopup'+form_id);
        }

        function handleDeleteForm(form_id) {
        	if(confirm('<?php echo xlt('Do you want to delete form template?'); ?>')) {
        		document.querySelector('#form_id').value = form_id;
        		document.querySelector('#form_action').value = "delete";
        		document.querySelector('#form-manager').submit();
        	}
        }

        function handleAssignForm(form_id) {
        	let url = './lib/patient_groups.php?form_id=' + form_id;
            dlgopen(url, 'pop-assign-groups', 'modal-lg', 850, '', '', {
                allowDrag: true,
                allowResize: true,
                sizeHeight: 'full',
            });
        }

        function handleFormToken(template_id) {
            let btnClose = <?php echo xlj("Cancel"); ?>;
            let title = <?php echo xlj("Send To Contact"); ?>;
            let url = './lib/send_form_token.php?template_id=' + encodeURIComponent(template_id);
            // leave dialog name param empty so send dialogs can cascade.
            dlgopen(url, '', 'modal-md', 400, '', title, { // dialog restores session
                buttons: [
                    {text: btnClose, close: true, style: 'secondary btn-sm'}
                ]
            });
        };

        function doActive(form_id) {
        	var f = document.forms[0];
			f.form_action.value = "do_active";
			f.form_id.value = form_id;

			f.submit();
        }

        function doInActive(form_id) {
        	var f = document.forms[0];
			f.form_action.value = "do_inactive";
        	f.form_id.value = form_id;

        	f.submit();
        }

        function handleCloneForm(form_id) {
        	var f = document.forms[0];
			f.form_action.value = "do_clone";
        	f.form_id.value = form_id;

        	f.submit();
        }

        function handlePreviewForm(template_id) {
        	var target = './render_form_popup.php';

        	if(form_id != "") {
        		target += '?template_id=' + template_id;
        	}

			dialog.popUp(target, null, 'formpopup' + template_id);
        }

        async function handleDownloadFormPDF(e, form_id) {
        	var data = [];
	        data.push({name: "form_action", value: "do_pdf_download"});
	        data.push({name: "form_id", value: form_id });

	        $(e).find('.fa-file-pdf').hide();
	        $(e).find('.spinner-border').show();

	        await $.ajax({
	            url: "form_manager.php",
	            method: "POST",
	            data: $.param(data),
	            success: async function(result) {
	                if(result != "") {
		                const downloadLink = document.createElement("a");
					    const fileName = "form_pdf.pdf";

					    downloadLink.href = result;
					    downloadLink.download = fileName;
					    downloadLink.click();
					}
	            },                      
	        });

	        $(e).find('.fa-file-pdf').show();
	        $(e).find('.spinner-border').hide();
        }

        function closeRefresh() {
        	//location.reload();
        	var f = document.forms[0];
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
		<h2><?php echo xlt('Form Manager'); ?></h2>
		<div class="mt-4">
			<form action="form_manager.php" method="post" id="form-manager">
				<input type="hidden" name="form_action" id="form_action" value="">
				<input type="hidden" name="form_id" id="form_id" value="">
			</form>
			<button type="button" class="btn btn-primary px-4" onclick="handleCreateForm()" ><i class="fa fa-plus" aria-hidden="true"></i> <?php echo xlt('Create Form'); ?></button>
		</div>
		<div class="main-container mt-4">
			<table id="form_manager_table" class="table table-bordered tbordered table-sm">
				<thead class="thead-light">
					<tr>
						<th><?php echo xlt('Form Name'); ?></th>
						<th><?php echo xlt('Form Status'); ?></th>
						<th><?php echo xlt('Last Modified'); ?></th>
						<th><?php echo xlt('Actions'); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php
						if(!empty($formItems)) {
							foreach ($formItems as $formItem) {
								?>
								<tr>
									<td><b><?php echo isset($formItem['template_name']) ? $formItem['template_name'] : "" ?></b></td>
									<td><?php echo isset($formItem['status']) && $formItem['status'] == "1" ? "Active" : "In Active" ?></td>
									<td><?php echo isset($formItem['modified_date']) ? $formItem['modified_date'] : "" ?></td>
									<td class="p-1" style="vertical-align:middle;">
										<button type="button" class="btn btn-secondary btn-sm" onclick="handleCreateForm('<?php echo $formItem['id'] ?>')" title="<?php echo "Edit Form" ?>"><i class="fa fa-pencil" aria-hidden="true"></i></button>
										<button type="button" class="btn btn-secondary btn-sm" onclick="handleDeleteForm('<?php echo $formItem['id'] ?>')" title="<?php echo "Delete Form" ?>" ><i class="fa fa-trash" aria-hidden="true"></i></button>
										<!-- <button type="button" class="btn btn-secondary btn-sm" onclick="handleAssignForm('<?php //echo $formItem['id'] ?>')" title="<?php //echo "Assign Form" ?>"><i class="fa fa-share" aria-hidden="true"></i></button> -->

										<button type="button" class="btn btn-secondary btn-sm" onclick="handleCloneForm('<?php echo $formItem['id'] ?>')" title="<?php echo "Copy Form" ?>"><i class="fa fa-clone" aria-hidden="true"></i></button>

										<button type="button" class="btn btn-secondary btn-sm" onclick="handlePreviewForm('<?php echo $formItem['id'] ?>')" title="<?php echo "Form Preview" ?>"><i class="fa fa-eye" aria-hidden="true"></i></button>

										<button type="button" class="btn btn-secondary btn-sm" onclick="handleDownloadFormPDF(this, '<?php echo $formItem['id'] ?>')" title="<?php echo "Form PDF" ?>">
											<i class="fa fa-file-pdf" aria-hidden="true"></i>
											<div class="spinner-border spinner-border-sm" style="display:none;"><span class="visually-hidden"></span></div>
										</button>

										<!-- <button type="button" class="btn btn-secondary btn-sm" onclick="handleFormToken('<?php //echo $formItem['id'] ?>')" title="<?php //echo "Form Token Generator" ?>"><i class="fa fa-paper-plane" aria-hidden="true"></i></button> -->

										<?php if(isset($formItem['status']) && $formItem['status'] === "0") { ?>
										<button type="button" class="btn btn-success btn-sm" onclick="doActive('<?php echo $formItem['id'] ?>')" style="font-size: 11px;"><?php echo "Activate" ?></button>
										<?php } else { ?>
										<button type="button" class="btn btn-danger btn-sm" onclick="doInActive('<?php echo $formItem['id'] ?>')" style="font-size: 11px;"><?php echo "In Activate" ?></button>
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