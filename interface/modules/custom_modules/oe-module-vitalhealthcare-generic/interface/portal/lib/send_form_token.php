<?php

require_once(dirname(__FILE__, 8) . "/interface/globals.php");
require_once("$srcdir/patient.inc");
require_once($GLOBALS['srcdir']."/wmt-v3/wmt.globals.php");

use OpenEMR\Common\Csrf\CsrfUtils;
use OpenEMR\Core\Header;
use Vitalhealthcare\OpenEMR\Modules\Generic\Controller\FormController;

// Option lists
$message_list = new wmt\Options('Email_Messages');
$message_list1 = new wmt\Options('SMS_Messages');

$templateId = isset($_REQUEST['template_id']) ? $_REQUEST['template_id'] : "";
$pid = isset($_REQUEST['pid']) ? $_REQUEST['pid'] : "";
$formDataId = isset($_REQUEST['form_data_id']) ? $_REQUEST['form_data_id'] : "";
$formType = isset($_REQUEST['form_type']) ? $_REQUEST['form_type'] : "";
$pageAction = isset($_REQUEST['page_action']) ? $_REQUEST['page_action'] : "";


if(!empty($formType) && !empty($templateId)) {
	if($formType == FormController::FORM_LABEL) $templateId = "f" . $templateId;
	if($formType == FormController::PACKET_LABEL) $templateId = "p" . $templateId;
}

$formPatientName = "";

$isOnetime = true;

$patientData = array();
if(!empty($pid)) {
	$patientData = getPatientPID(array("pid" => $pid, "given" => "pid, fname, lname, nickname33, CONCAT(CONCAT_WS(', ', IF(LENGTH(lname),lname,NULL), IF(LENGTH(fname),fname,NULL))) as patient_name"));
	$formPatientName = !empty($patientData) && isset($patientData[0]["patient_name"]) ? $patientData[0]["patient_name"] : "";
}

$readOnly = false;
if(!empty($pid) && !empty($formDataId)) $readOnly = true;

?>
<!DOCTYPE html>
<html lang="">
<head>
    <title><?php echo xlt('Contact') ?></title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <?php Header::setupHeader(['dialog', 'opener', 'main_theme', 'main-theme', 'jquery']); 
    echo "<script>var pid=" . js_escape($pid) . ";var isFax=" . js_escape($isFax) . ";var isOnetime=" . js_escape($isOnetime) . ";var isEmail=" . js_escape($isEmail) . ";var isSms=" . js_escape($isSMS) . ";var isForward=" . js_escape($isForward) . ";var recipient=" . js_escape($recipient_phone) . ";</script>";
    ?>
  
    <style>
      .panel-body {
        word-wrap: break-word;
        overflow: hidden;
      }
      select[readonly], input[readonly] {
      	pointer-events: none;
      }
    </style>

    <script type="text/javascript">
    	var formList = [];

    	$(document).ready(async function() {
    		<?php if(!empty($pid)) { ?>
    		await setPatientTemplate('<?php echo $templateId; ?>');
    		await setPatientFormData('<?php echo $formDataId; ?>');
    		<?php } ?>

    		$('#form_template').change(function() {
    			setPatientFormData();

    			var f = document.forms[0];

    			sFormItems = formList.hasOwnProperty(f.form_id.value) ? formList[f.form_id.value] : {};
            	if(sFormItems.hasOwnProperty('email_template')) {
            		f.formEmailTemplate.value = sFormItems['email_template'];
            	}

            	if(sFormItems.hasOwnProperty('sms_template')) {
            		f.formSMSTemplate.value = sFormItems['sms_template'];
            	}
    		});
    	});

    	// This invokes the find-patient popup.
		function sel_patient(fom_id) {
		    let title = '<?php echo xlt('Patient Search'); ?>';
		    dlgopen('<?php echo $GLOBALS['webroot']; ?>/interface/main/calendar/find_patient_popup.php', 'findPatient', 650, 300, '', title);
		}

		function setpatient(pid, lname, fname, dob = '', alert_info = '', p_data = {}) {
		    // @VH - nickNameVal changes.
		    var nickNameVal = (p_data.hasOwnProperty('nickname33') && p_data['nickname33'] != "" && p_data['nickname33'] != null) ? ' \"'+p_data['nickname33']+'\" ' : '';

		    var f = document.forms[0];
		    // @VH - Added nickname value to patient name.
		    f.form_patient.value = lname + ', ' + fname  + nickNameVal;
		    f.form_pid.value = pid;

		    setPatientTemplate();
		}

		// This invokes the req field form.
		function sel_fillform(fom_id, form_data_id = 0, form_pid = 0) {
		    let title = '<?php echo xlt('Required to be filled before sending'); ?>';
		    dlgopen('<?php echo $GLOBALS['webroot']; ?>/interface/modules/custom_modules/oe-module-vitalhealthcare-generic/interface/portal/lib/req_form_list.php?form_id=' + fom_id + '&form_data_items=' + form_data_id + '&form_pid=' + form_pid, 'reqFormList', 650, 300, '', title);
		}

		function setsendformdata(formData) {
			$('#form_field_data').val(JSON.stringify(formData));
			$('#sendTokenBtn').click();
		}

		$(function () {
			function selFormTokenSend() {
				//opener.setsendtoken();
			}
		});

		async function handleSendToken(el) {
			var f = document.forms[0];
			f.form_action.value = "send_token";

			if($('#is_already_exist').val() == "1" && $('#form_data_items').val() == "new") {
				if(!confirm("Similar form/packet has been sent to patient. Do you want to send again?")) {
					return false;
				}
			}

			if(f.form_patient.value == "") {
				alert("Please select patient");
				return false;
			}

			if(f.form_data_items.value == "") {
				alert("Please select form");
				return false;
			}

			if(f.notification_method.value == "") {
				alert("Please select method");
				return false;
			}

			if(f.formEmailTemplate.value == "" && ["email", "both"].includes(f.notification_method.value)) {
				alert("Please select email template");
				return false;
			}

			if(f.formSMSTemplate.value == "" && ["sms", "both"].includes(f.notification_method.value)) {
				alert("Please select sms template");
				return false;
			}

			el.disabled = true;
			el.querySelector('.spinner-border').style.display = "inline-block";

			const templateData = await $.ajax({
                type: "POST",
                url: "./ajax_form.php",
                data: $("#contact-form").serialize(),
                success: function (data) {
                	const resJson = JSON.parse(data);

                	if(resJson['status'] === true) {
                		const dataJson = resJson['data'];
                		const oneTimeJson = dataJson['oneTime'];

                		let msgList = [];
                		if(dataJson.hasOwnProperty('success') && dataJson['success'].length > 0) {
                			msgList = msgList.concat(dataJson['success']);
                		}

                		if(dataJson.hasOwnProperty('errors') && dataJson['errors'].length > 0) {
                			msgList = msgList.concat(dataJson['errors']);
                		}

                		let alertMsg = "";
                		if(msgList.length > 0) {
                			alertMsg += msgList.join("\n");
                		}

                		if(oneTimeJson.hasOwnProperty('encoded_link')) {
                			if(alertMsg != "") {
                				alertMsg += "\n\n";
                			}

                			alertMsg += "Link: " + oneTimeJson['encoded_link'];
                		}

                		alert(alertMsg);

                		// close dialog as we have success.
                        //dlgclose();
                        selsendformtoken();
                	}

                	if(resJson['status'] === false) {
                		if(resJson.hasOwnProperty('errors')) {
                			alert(resJson['errors']);

                			if(resJson.hasOwnProperty('reqBeforeSendingFieldStatus') && resJson['reqBeforeSendingFieldStatus'] === true) {
                				sel_fillform($('#form_template').val(), $('#form_data_items').val(), $('input[name="form_pid"]').val());
                			}
                		}
                	}
                    
                }
            });

            $('#form_field_data').val('');

            el.querySelector('.spinner-border').style.display = "none";
            el.disabled = false;
		}

		async function setPatientTemplate(dValue = "") {
			var f = document.forms[0];

			if(f.form_pid.value == "") {
				return false;
			}

			f.form_action.value = "patient_template";
			f.form_template.value = "";

			const templateData = await $.ajax({
                type: "POST",
                url: "./ajax_form.php",
                data: $("#contact-form").serialize(),
                success: async function (data) {
                	let dataJson = JSON.parse(data);
                    $("#form_template").html(dataJson['content']);

                    formList = dataJson.hasOwnProperty('list') ? dataJson['list'] : [];

                    if(dValue != "") {
                    	f.form_template.value = dValue;

                    	sFormItems = formList.hasOwnProperty(dValue) ? formList[dValue] : {};
                    	if(sFormItems.hasOwnProperty('email_template')) {
                    		f.formEmailTemplate.value = sFormItems['email_template'];
                    	}

                    	if(sFormItems.hasOwnProperty('sms_template')) {
                    		f.formSMSTemplate.value = sFormItems['sms_template'];
                    	}
                    }

                    return data;
                }
            });
		}

		async function setPatientFormData(dValue = "") {
			var f = document.forms[0];

			if(f.form_pid.value == "" || f.form_id.value == "") {
				return false;
			}

			f.form_action.value = "patient_form_data";
			f.form_data_items.value = "";

			const templateData = await $.ajax({
                type: "POST",
                url: "./ajax_form.php",
                data: $("#contact-form").serialize(),
                success: function (data) {
                	let dataJson = JSON.parse(data);
                    $("#form_data_items").html(dataJson['content']);

                    if(dataJson.hasOwnProperty('status') && dataJson['status'].length > 0) {
                    	$("#is_already_exist").val("1");
                    } else {
                    	$("#is_already_exist").val("0");
                    }

                    if(dValue != "") {
                    	f.form_data_items.value = dValue;
                    }

                    return data;
                }
            });
		}

		function selsendformtoken() {
          if (opener.closed || ! opener.setsendformtoken)
           dlgclose();
          else
           opener.setsendformtoken();
           dlgclose();
          return false;
         }
    </script>
</head>
<body>
    <div class="container-fluid">
        <form class="form" id="contact-form" method="post" action="send_form_token.php" role="form">
            <input type="hidden" name="csrf_token_form" id="csrf_token_form" value="<?php echo attr(CsrfUtils::collectCsrfToken('contact-form')); ?>" />
            
            <!-- <input type="hidden" id="form_id" name="form_id" value='<?php //echo attr($templateId); ?>'> -->
            <input type="hidden" id="form_action" name="form_action" value=''>
            <input type="hidden" id="is_already_exist" name="is_already_exist" value='0'>
            <input type="hidden" id="page_action" name="page_action" value='<?php echo $pageAction; ?>'>
            <textarea name="form_field_data" id="form_field_data" style="display:none;"></textarea>

            <div class="messages"></div>
            <div class="row">
                <div class="col-md-12">
                    <div class="form-group show-detail">
                    	<div class="form-group">
                    		<label for="form_patient"><?php echo xlt('Patient') ?></label>
                    		<input type="text" class="form-control" name="form_patient" onclick="sel_patient()" value="<?php echo isset($formPatientName) ? $formPatientName : ""; ?>" placeholder="Select Patient" <?php echo $readOnly === true ? "readonly" : "" ?>>
                    		<input type="hidden" class="form-control" name="form_pid" value="<?php echo isset($pid) ? $pid : ""; ?>">
                    	</div>

                    	<div class="form-group">
                    		<label for="form_patient"><?php echo xlt('Form Template') ?></label>
                    		<select class="form-control" name="form_id" id="form_template" <?php echo $readOnly === true ? "readonly" : "" ?>>
                    			<option value=""><?php echo xlt("Please Select"); ?></option>
                    		</select>
                    	</div>

                    	<div class="form-group">
                    		<label for="form_patient"><?php echo xlt('Form') ?></label>
                    		<select class="form-control" name="form_data_items" id="form_data_items" <?php echo $readOnly === true ? "readonly" : "" ?>>
                    			<option value=""><?php echo xlt("Please Select"); ?></option>
                    		</select>
                    	</div>

                    	<div class="form-group">
							<label class="form-label"><?php echo xlt('Email Template'); ?></label>
							<select id="form_email_template" name="formEmailTemplate" class='form-control'>
								<option value=""><?php echo xlt('Select Please'); ?></option>
								<?php $message_list->showOptions($formEmailTemplate); ?>
							</select>
						</div>

						<div class="form-group">
							<label class="form-label"><?php echo xlt('SMS Template'); ?></label>
							<select id="form_sms_template" name="formSMSTemplate" class='form-control'>
								<option value=""><?php echo xlt('Select Please'); ?></option>
								<?php $message_list1->showOptions($formSMSTemplate); ?>
							</select>
						</div>

                        <div class="mt-2">
                            <?php if ($isOnetime ?? 0) { ?>
                                <span class="form-group">
                                	<div class="form-check-inline">
		                                <label><?php echo xlt("Send Using"); ?></label>
		                            </div>
		                            <div class="form-check-inline">
		                                <label class="form-check-label">
		                                    <input type="radio" class="form-check-input" name="notification_method" value="link" /><?php echo xlt("Link") ?>
		                                </label>
		                            </div>
		                            <div class="form-check-inline">
		                                <label class="form-check-label">
		                                    <input type="radio" class="form-check-input" name="notification_method" value="sms" /><?php echo xlt("SMS") ?>
		                                </label>
		                            </div>
		                            <div class="form-check-inline">
		                                <label class="form-check-label">
		                                    <input type="radio" class="form-check-input" name="notification_method" value="email" /><?php echo xlt("Email") ?>
		                                </label>
		                            </div>
		                            <div class="form-check-inline">
		                                <label class="form-check-label">
		                                    <input type="radio" class="form-check-input" name="notification_method" value="both" checked /><?php echo xlt("Both (Email/SMS)") ?>
		                                </label>
		                            </div>
		                        </span>
                            <?php } ?>
                            <button type="button" id="sendTokenBtn" class="btn btn-success float-right" onclick="handleSendToken(this)" >
                            	<?php echo xlt('Send'); ?>
                            	<div class="spinner-border spinner-border-sm" style="display:none;" role="status"></div>	
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </form>
    </div>
</body>
</html>
