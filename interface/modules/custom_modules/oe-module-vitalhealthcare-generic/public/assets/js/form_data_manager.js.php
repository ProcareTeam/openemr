<?php

require_once(dirname(__FILE__, 8) . "/interface/globals.php");

?>

function openCopyLink(pid = '', template_id = '', form_data_id = '', form_type = 'form',) {
    let btnClose = <?php echo xlj("Cancel"); ?>;
    let title = <?php echo xlj("Copy Link"); ?>;
    let url = '<?php echo $GLOBALS['webroot']; ?>/interface/modules/custom_modules/oe-module-vitalhealthcare-generic/interface/portal/lib/copy_popup.php?';

    if(pid != "") url += "pid=" + pid;
    if(template_id != "") url += "&template_id=" + template_id;
    if(form_data_id != "") url += "&form_data_id=" + form_data_id;
    if(form_type != "") url += "&form_type=" + form_type;

    // leave dialog name param empty so send dialogs can cascade.
    dlgopen(url, '', 'modal-md', 450, '', title, { // dialog restores session
        buttons: [
            {text: btnClose, close: true, style: 'secondary btn-sm'}
        ]
    });
}

async function deleteForm(formDataId) {
	if(confirm("Do you want to delete all the forms from this Packet ?")) {
		const formResponce = await actionHandleCall({
			'action' : 'action_delete',
			'form_data_id' : formDataId
		}, true);

		if(formResponce.hasOwnProperty('status') && formResponce['status'] === true) {
			alert(formResponce['message']);
			window.dataTable.draw();
		} else {
			alert(formResponce['message']);
		}
	}
}

async function rejectForm(form_data_id) {
	// if(confirm("Do you want to reject form?")) {
	// 	const formResponce = await actionHandleCall({
	// 		'action' : 'action_rejected',
	// 		'form_data_id' : formDataId
	// 	}, true);

	// 	if(formResponce.hasOwnProperty('status') && formResponce['status'] === true) {
	// 		alert(formResponce['message']);
	// 		window.dataTable.draw();
	// 	} else {
	// 		alert(formResponce['message']);
	// 	}
	// }

	let btnClose = <?php echo xlj("Cancel"); ?>;
    let title = <?php echo xlj("Reject Form"); ?>;
    let url = '<?php echo $GLOBALS['webroot']; ?>/interface/modules/custom_modules/oe-module-vitalhealthcare-generic/interface/portal/lib/reject_popup.php?';

    if(form_data_id != "") url += "form_data_id=" + form_data_id;

    // leave dialog name param empty so send dialogs can cascade.
    dlgopen(url, '', 'modal-md', 450, '', title);
}

async function formDeleteStatus(formDataId) {
	if(confirm("Do you want to delete?")) {
		const formResponce = await actionHandleCall({
			'action' : 'action_delete_submited',
			'form_data_id' : formDataId
		}, true);

		if(formResponce.hasOwnProperty('status') && formResponce['status'] === true) {
			alert(formResponce['message']);
			window.dataTable.draw();
		} else {
			alert(formResponce['message']);
		}
	}
}

function sendFormToken(pid = '', template_id = '', form_data_id = '', form_type = 'form', redraw = true, page_action = "") {
    let btnClose = <?php echo xlj("Cancel"); ?>;
    let title = <?php echo xlj("Send To Contact"); ?>;
    let url = '<?php echo $GLOBALS['webroot']; ?>/interface/modules/custom_modules/oe-module-vitalhealthcare-generic/interface/portal/lib/send_form_token.php?';

    if(pid != "") url += "pid=" + pid;
    if(template_id != "") url += "&template_id=" + template_id;
    if(form_data_id != "") url += "&form_data_id=" + form_data_id;
    if(form_type != "") url += "&form_type=" + form_type;
    if(page_action != "") url += "&page_action=" + page_action;

    // leave dialog name param empty so send dialogs can cascade.
    dlgopen(url, '', 'modal-md', 400, '', title, { // dialog restores session
        buttons: [
            {text: btnClose, close: true, style: 'secondary btn-sm'}
        ]
    });
}

function renderForm(formDataId, pid, type = 'form', disableReview = false) {
	if(type == 'form') {
		var target = '<?php echo $GLOBALS['webroot']; ?>/interface/modules/custom_modules/oe-module-vitalhealthcare-generic/interface/portal/render_form_popup.php';

		if(formDataId != "") {
			target += '?form_data_id=' + formDataId + '&pid=' + pid;
		}

		if(disableReview === true) {
			target += '&disable_review=1';
		}

		dialog.popUp(target, null, 'formpopup'+formDataId);
	} else if(type == 'packet') {
		reviewSummary(pid, formDataId, 'packet');
	}
}

function fillForm(formId, formDataId, pid) {
	var target = '<?php echo $GLOBALS['webroot']; ?>/interface/modules/custom_modules/oe-module-vitalhealthcare-generic/interface/portal/render_form_popup.php';

	if(formDataId != "") {
		target += '?form_data_id1=' + formDataId + '&pid=' + pid + '&form_mode=fill_req_field';
	}

	if(formId != "") {
		target += '&template_id=' + formId;
	}

	dialog.popUp(target, null, 'formpopup'+formDataId);
}

function reviewSummary(pid, form_data_id, form_type = 'form') {
	dlgopen('<?php echo $GLOBALS['webroot']; ?>/interface/modules/custom_modules/oe-module-vitalhealthcare-generic/interface/portal/review_summary.php?pid=' + pid + '&form_data_id=' + form_data_id + '&form_type=' + form_type, '', 'modal-full', '700', false, 'Summary', {
        sizeHeight: 'full',
        onClosed: ''
    });
}

async function actionHandleCall(data, doUpdate = false, doUpdateAll = false) {
	var res = await $.ajax({
	    url: '<?php echo $GLOBALS['webroot']; ?>/interface/modules/custom_modules/oe-module-vitalhealthcare-generic/interface/portal/form_data_manager.php',
	    type: 'POST',
	    data: { ...data, 'doUpdate' : doUpdate }
	});

	//Parse JSON Data.
	if(res != undefined) {
		res = JSON.parse(res);
	}

	return res;
}