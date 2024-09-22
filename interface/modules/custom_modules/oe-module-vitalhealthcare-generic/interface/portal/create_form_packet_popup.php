<?php

require_once(dirname(__FILE__, 7) . "/interface/globals.php");
require_once "$srcdir/patient.inc";
require_once "$srcdir/options.inc.php";
require_once($GLOBALS['srcdir']."/wmt-v3/wmt.globals.php");

use OpenEMR\Common\Csrf\CsrfUtils;
use OpenEMR\Core\Header; 
use Vitalhealthcare\OpenEMR\Modules\Generic\Controller\FormController;

$packetId = isset($_REQUEST['packet_id']) ? $_REQUEST['packet_id'] : "";

// Option lists
$message_list = new wmt\Options('Email_Messages');
$message_list1 = new wmt\Options('SMS_Messages');

$patientForm = new FormController();
$FormTemplates = $patientForm->getFormTemplates("", 1);

if(isset($_POST['formsubmit'])) {
	$packetName = isset($_POST['packetName']) ? $_POST['packetName'] : "";
	$packetEmailTemplate = isset($_POST['packetEmailTemplate']) ? $_POST['packetEmailTemplate'] : "";
	$packetSMSTemplate = isset($_POST['packetSMSTemplate']) ? $_POST['packetSMSTemplate'] : "";
	$packetStatus = isset($_POST['packetStatus']) ? $_POST['packetStatus'] : "";
	$packetExpireTime = isset($_POST['packetExpireTime']) ? $_POST['packetExpireTime'] : "";
	$assignedForms = isset($_POST['assignedForms']) && !empty($_POST['assignedForms']) ? json_decode($_POST['assignedForms'], true) : array();

	if(!empty($packetId)) {
		sqlStatement("UPDATE `vh_form_packets` SET `name` = ?, `email_template` = ?, `sms_template` = ?, `status` = ?, `expire_time` = ?, `modified_date` = NOW() WHERE `id` = ? ", array($packetName, $packetEmailTemplate, $packetSMSTemplate, $packetStatus, $packetExpireTime, $packetId));
	} else {
		$packetId = sqlInsert("INSERT INTO `vh_form_packets` ( `uid`, `name`, `email_template`, `sms_template`, `status`, `expire_time`) VALUES ( ?, ?, ?, ?, ?, ?)", array($_SESSION['authUserID'], $packetName, $packetEmailTemplate, $packetSMSTemplate, $packetStatus, $packetExpireTime));
	}

	if(!empty($packetId)) {
		sqlStatement("DELETE FROM `vh_packet_link` WHERE `packet_id` = ? ", array($packetId));
	}

	if(!empty($assignedForms)) {
		foreach ($assignedForms as $fItems) {
			sqlInsert("INSERT INTO `vh_packet_link` ( `packet_id`, `form_id`) VALUES ( ?, ?)", array($packetId, $fItems['form_id']));
		}
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

if(!empty($packetId)) {
	$packetData = $patientForm->getpacketTemplates($packetId);
	$packetData = count($packetData) > 0 ? $packetData[0] : array();
	$assignedForms = isset($packetData['form_items']) ? $packetData['form_items'] : array();
	$packetName = isset($packetData['template_name']) ? $packetData['template_name'] : "";
	$packetStatus = isset($packetData['status']) ? $packetData['status'] : "";
	$packetEmailTemplate = isset($packetData['email_template']) ? $packetData['email_template'] : "";
	$packetSMSTemplate = isset($packetData['sms_template']) ? $packetData['sms_template'] : "";
}

?>
<html>
<head>
	<?php Header::setupHeader(['datetime-picker', 'opener', 'jquery', 'jquery-ui', 'datatables', 'datatables-colreorder', 'datatables-bs', 'sortablejs']); ?>

    <title><?php echo xlt('Create From Packet'); ?></title>

    <style type="text/css">
    	
    </style>

    <style>
	  body {
	    overflow:hidden;
	  }
	  .list-group-item {
	    cursor: move;
	  }
	  strong {
	    font-weight: 600;
	  }
	  .col-height {
	    max-height: 95vh;
	    overflow-y:auto;
	  }
	  #assigned_patient {
	  	min-height: 150px;
	  }
	</style>

    <script type="text/javascript">
    	document.addEventListener('DOMContentLoaded', function () {
	        // init drag and drop
	        let patientRepository = document.getElementById('searchResults');
	        Sortable.create(patientRepository, {
	            group: {
	                name: 'patientGroup',
	                pull: 'clone',
	            },
	            multiDrag: true,
	            selectedClass: 'active',
	            fallbackTolerance: 3,
	            sort: false,
	            swapThreshold: 0.25,
	            animation: 150,
	            revertClone: true,
	            removeCloneOnHide: true,
	            onAdd: function (evt) {
	                if (evt.items.length > 0) {
	                    for (let i = 0; i < evt.items.length; i++) {
	                        let el = evt.items[i];
	                        el.parentNode.removeChild(el);
	                    }
	                } else {
	                    let el = evt.item;
	                    el.parentNode.removeChild(el);
	                }
	            }
	        });
	        let assignEl = "assigned_patient";
	        let dropAssign = document.getElementById(assignEl);
	        Sortable.create(dropAssign, {
	            group: {
	                name: 'patientGroup',
	                delay: 1000,
	            },
	            multiDrag: true,
	            selectedClass: 'active',
	            fallbackTolerance: 3,
	            animation: 150,
	            sort: true,
	            swapThreshold: 0.25,
	            removeCloneOnHide: false,
	            onAdd: function (evt) { // make group unique
	                let toList = evt.to.children;
	                let dedup = {};
	                let list = [...toList];
	                list.forEach(function (toEl) {
	                    if (dedup[toEl.getAttribute('data-formid')]) {
	                        toEl.remove();
	                    } else {
	                        dedup[toEl.getAttribute('data-formid')] = true;
	                    }
	                });
	            }
	        });

	        $('#searchparm').focus();

	        $('#btnCancel').click(function () {
			  	window.close();
			  	return false;
	        });
	    });

	    function submitGroups() {
	        let target = document.getElementById('edit-groups');
	        let assignTarget = target.querySelectorAll('ul');
	        let patientArray = [];
	        let listArray = [];
	        let listData = {};
	        assignTarget.forEach((ulItem, index) => {
	            let lists = ulItem.querySelectorAll('li');
	            lists.forEach((item, index) => {
	                console.log({index, item})
	                listData = {
	                    'form_id': item.dataset.formid
	                }
	                patientArray.push(listData);
	                listData = {};
	            });
	        });
	        const data = new FormData();
	        $('#assignedForms').val(JSON.stringify(patientArray));

	        if(patientArray.length <= 0) {
	        	alert("Please assign forms");
	        	return false;
	        }

	        $('#btnFormSubmit').click();
	    }
    </script>

    <script>
		// Example starter JavaScript for disabling form submissions if there are invalid fields
		(function() {
		  'use strict';
		  window.addEventListener('load', function() {
		    // Fetch all the forms we want to apply custom Bootstrap validation styles to
		    var forms = document.getElementsByClassName('needs-validation');
		    // Loop over them and prevent submission
		    var validation = Array.prototype.filter.call(forms, function(form) {
		      form.addEventListener('submit', function(event) {
		        if (form.checkValidity() === false) {
		          event.preventDefault();
		          event.stopPropagation();
		        }
		        form.classList.add('was-validated');
		      }, false);
		    });
		  }, false);
		})();
	</script>
</head>
<body>
	<div id="page-container">
		<div class='container-fluid'>
		<div class="card mt-3">
			<div class="card-header"><?php echo xlt('Packet Details'); ?></div>
			<div class="card-body">
				<form action="<?php echo "create_form_packet_popup.php" ?>" class="needs-validation mb-0" novalidate name="edit_form_packet" id="edit_form_packet" method="post">
				<?php if(!empty($packetId)) { ?>
				<input type="hidden" name="packet_id" value="<?php echo $packetId; ?>">
				<?php } ?>

				<div class="row">
					<div class="col-md-12 p-2 pb-1">
		        		<label class="form-label"><?php echo xlt('Packet Name'); ?></label>
					    <input type="text" class="form-control" id="packetName" name="packetName" value="<?php echo $packetName; ?>" required>
					    <div class="invalid-feedback">
					        <?php echo xlt('Please enter form packet name.'); ?>
					    </div>
					</div>
				</div>

				<div class="row">
					<div class="col-md-6 p-2 pb-1">
						<label class="form-label"><?php echo xlt('Email Template'); ?></label>
						<select id="form_email_template" name="packetEmailTemplate" class='form-control'>
							<option value=""><?php echo xlt('Select Please'); ?></option>
							<?php $message_list->showOptions($packetEmailTemplate); ?>
						</select>
					</div>

					<div class="col-md-6 p-2 pb-1">
						<label class="form-label"><?php echo xlt('SMS Template'); ?></label>
						<select id="form_sms_template" name="packetSMSTemplate" class='form-control'>
							<option value=""><?php echo xlt('Select Please'); ?></option>
							<?php $message_list1->showOptions($packetSMSTemplate); ?>
						</select>
					</div>
				</div>

				<div class="row">
					<div class="col-md-6 p-2 pb-1">
					    <label for="status" class="form-label"><?php echo xlt('Status'); ?></label>
					    <select class="form-select form-control" id="packetStatus" name="packetStatus">
					      <option value="1" <?php echo $packetStatus == "1" ? "selected" : "" ?>>Active</option>
						  <option value="0" <?php echo $packetStatus == "0" ? "selected" : "" ?>>InActive</option>
						</select>
						<div class="invalid-feedback">
					        <?php echo xlt('Please choose status.'); ?>
					    </div>
					</div>
					<div class="col-md-6 p-2 pb-1">
						<label class="form-label"><?php echo xlt('Expire Time'); ?></label>
						<input type="text" class="form-control" id="packetExpireTime" name="packetExpireTime" value="<?php echo isset($packetData['expire_time']) ? $packetData['expire_time'] : "P2D" ?>" required>
					    <div class="invalid-feedback">
					        <?php echo xlt('Please enter expire time.'); ?>
					    </div>
					</div>
				</div>
				<div class='row'>
	            	<div class='col-6 p-2 pb-1'>
						<label><?php echo xlt('Forms'); ?></label>
						<div class="bg-light col-height" style="min-height: 150px;">
							<ul id='searchResults' class='list-group mx-0'>
								<?php foreach ($FormTemplates as $fkey => $fItem) { ?>
								<li class='list-group-item px-1 py-1 mb-1' data-formid='<?php echo $fItem["id"] ?>'>
									<strong><?php echo $fItem["template_name"] ?></strong>
								</li>
								<?php } ?>
							</ul>
						</div>
					</div>
					<div class='col-6 p-2 pb-1'>
						<label><?php echo xlt('Assigned Forms'); ?></label>
						<div id="edit-groups" class="bg-light col-height" style="min-height: 150px;">
							<ul id='assigned_patient' class='list-group mx-0 px-1 show' data-group='assigned_patient'>
								<?php foreach ($assignedForms as $afkey => $afItem) { ?>
								<li class='list-group-item px-1 py-1 mb-1' data-formid='<?php echo $afItem["id"] ?>'>
									<strong><?php echo $afItem["template_name"] ?></strong>
								</li>
								<?php } ?>
							</ul>
							<textarea name="assignedForms" id="assignedForms" style="display:none;"><?php echo $assignedForms ?></textarea>
						</div>
					</div>
				</div>
				<div class="row">
					<div class='col-12 p-2'>
	                <div class='btn-group ml-0'>
	                    <button type='button' class='btn btn-primary' onclick='return submitGroups();'><?php echo xlt('Save'); ?></button>
	                    <!-- <button type='button' class='btn btn-secondary' id="btnCancel"><?php //echo xlt('Quit'); ?></button> -->
	                </div>
	                <div class="col-12" style="display:none;">
					    <button class="btn btn-primary" id="btnFormSubmit" type="submit" value="submit" name="formsubmit" ><?php echo xlt('Submit form'); ?></button>
					</div>
	            	</div>
	            </div>
	        	</form>
			</div>
		</div>
		</div>
	</div>
</body>
</html>