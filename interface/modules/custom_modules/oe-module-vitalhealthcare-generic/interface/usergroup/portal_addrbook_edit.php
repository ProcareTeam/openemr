<?php

require_once(dirname(__FILE__, 7) . "/interface/globals.php");
require_once("$srcdir/options.inc.php");

use OpenEMR\Common\Acl\AclMain;
use OpenEMR\Common\Csrf\CsrfUtils;
use OpenEMR\Common\Twig\TwigContainer;
use OpenEMR\Core\Header;
use OpenEMR\OemrAd\WordpressWebservice;

$userId = $_REQUEST['userid'] ?? "";

if (empty($userId)) {
	echo xlt('No User Id');
	exit();
}

if (isset($_POST['submit'])) {

	$incPatients = $_REQUEST['include_patients'] ?? array();
	$incPayers = $_REQUEST['include_payers'] ?? array();

	if (!empty($userId)) {
		// Delete existing records 
		sqlQuery("DELETE FROM vh_assign_patients WHERE user_id = ? ", array($userId));

		if (!empty($incPatients)) {
			foreach ($incPatients as $incPatientId) {
				// Insert assigned patient
				sqlQuery("INSERT INTO vh_assign_patients (`user_id`, `type`, `action`, `a_id`) VALUES (?, ?, ?, ?)", array($userId, 'patient', 'i', $incPatientId));
			}
		}

		if (!empty($incPayers)) {
			foreach ($incPayers as $incPayerId) {
				// Insert assigned payer 
				sqlQuery("INSERT INTO vh_assign_patients (`user_id`, `type`, `action`, `a_id`) VALUES (?, ?, ?, ?)", array($userId, 'payer', 'i', $incPayerId));
			}
		}

		if (!isset($_POST['portal_access']) || $_POST['portal_access'] == "0") {
			// OEMR - WordPress user delete
    		WordpressWebservice::handleInSyncPrepare($userId, "DELETE");
		}

		$abookData = sqlQuery("SELECT vapc.* FROM vh_attorney_portal_config vapc where vapc.abook_id = ? order by abook_id desc", array($userId));

		if (empty($abookData)) {
			// Insert abook
			sqlQuery("INSERT INTO vh_attorney_portal_config (`abook_id`, `portal_access`) VALUES (?, ?)", array($userId, isset($_POST['portal_access']) ? 1 : 0));
		} else {
			// Update abook
			sqlQuery("UPDATE `vh_attorney_portal_config` SET portal_access = ? WHERE abook_id = ?", array(isset($_POST['portal_access']) ? 1 : 0, $userId));
		}

		if (isset($_POST['portal_access']) && $_POST['portal_access'] == "1") {
			// OEMR - WordPress user update
    		WordpressWebservice::handleInSyncPrepare($userId, "UPDATE");
		}
	}

}

$userData = sqlQuery("SELECT * FROM users WHERE id = ? order by id desc", array($userId));
$abookData = sqlQuery("SELECT vapc.* FROM vh_attorney_portal_config vapc where vapc.abook_id = ? order by abook_id desc", array($userId));

$displayName = $userData['fname'] . ' ' . $userData['mname'] . ' ' . $userData['lname'];
if ($userData['suffix'] > '') {
    $displayName .= ", " . $userData['suffix'];
}

$insPatientsList = array();
$insPayerList = array();

$assignedPatientsres = sqlStatement("SELECT vap.user_id, vap.`type`, vap.a_id, CONCAT(pd.lname, ', ', pd.fname) as patient_name, ic.name as payer_name from vh_assign_patients vap left join patient_data pd on pd.pid = vap.a_id and vap.`type` = 'patient' left join insurance_companies ic on ic.id  = vap.a_id and vap.`type` = 'payer' WHERE vap.user_id = ?", array($userId));
while ($assignrow = sqlFetchArray($assignedPatientsres)) {

	if ($assignrow['type'] == "patient") {
		$insPatientsList[$assignrow['a_id']] = $assignrow['patient_name'];
	} else if ($assignrow['type'] == "payer") {
		$insPayerList[$assignrow['a_id']] = $assignrow['payer_name'];
	}
}

?>
<!DOCTYPE html>
<html>
<head>
	<!-- OEMRAD - Change -->
	<?php Header::setupHeader(['common', 'opener']); ?>

	<title><?php echo xlt('Address Book Manage'); ?></title>

	<!-- style tag moved into proper CSS file -->

	<script type="text/javascript">
		// This invokes the find-patient popup.
		function sel_patient() {
		  dlgopen('<?php echo $GLOBALS['webroot']; ?>/interface/main/calendar/find_patient_popup.php', '_blank', 750, 550, false, 'Select Patient');
		}

		// This is for callback by the find-patient popup.
		function setpatient(pid, lname, fname, dob) {
		  var f = document.forms[0];

		  if(f.include_patients) {
		  	addOption(f.include_patients, lname + ', ' + fname, pid);
		  }
		}

		function sel_insurance() {
		  dlgopen('<?php echo $GLOBALS['webroot']; ?>/interface/practice/ins_search.php', '_blank', 750, 550, false, 'Select Insurance');
		}

		function set_insurance(ins_id, ins_name) {
			var f = document.forms[0];

			if(f.include_payers) {
			  	addOption(f.include_payers, ins_name, ins_id);
			}
		}

		function addOption(ele, optTitle, optVal) {
			let exists = false;

			// Check if the option already exists
            for (let i = 0; i < ele.options.length; i++) {
                if (ele.options[i].value.toLowerCase() === optVal.toLowerCase()) {
                    exists = true;
                    break;
                }
            }

            // If the option doesn't exist, add it
            if (!exists && optVal) {
            	const newOption = document.createElement('option');
                newOption.value = optVal;
                newOption.text = optTitle;
                newOption.selected = true;
                ele.appendChild(newOption);
            }

            add_tag_item(ele);
		}

		function remove_option(ele, optValue) {
			let selectElement = ele.parentElement.parentElement.parentElement.querySelector('.selector_select');
			let badgeElement = ele.parentElement;

			let tagSelector =  ele.parentElement.parentElement.parentElement.querySelector('.selected_items');

			if(selectElement) {
				const options = selectElement.options;
            
	            for (let i = 0; i < options.length; i++) {
	                if (options[i].value == optValue) {
	                    selectElement.remove(i);
	                    badgeElement.remove();
	                    break; // Option found and removed, exit the loop
	                }
	            }

	            if (options.length === 0) {
	            	tagSelector.innerHTML = '<div class="noitem_text">No items</div>';
	        	}
			}
		}

		function add_tag_item(ele) {
			let tagSelector =  ele.parentElement.querySelector('.selected_items');

            if (!tagSelector) {
            	tagSelector = add_tag_div(ele);
            }

            if (tagSelector) {
	            tagSelector.innerHTML = '';

	            // Check if the option already exists
	            for (let i = 0; i < ele.options.length; i++) {
	            	if (ele.options[i].selected) {
		            	const newItemDivChild = document.createElement('div');
		            	newItemDivChild.classList.add('badge','badge-primary','p-2', 'badge_item');
		            	
		            	newItemDivChild.innerHTML = '<span>' + ele.options[i].text + '</span><button type="button" class="btn bbtn" data-dismiss="modal" onclick="remove_option(this, '+ ele.options[i].value +')"><i class="fas fa-times"></i></button>';
		            	tagSelector.appendChild(newItemDivChild);
	            	}
	            }
        	}

        	if (ele.options.length === 0) {
	            tagSelector.innerHTML = '<div class="noitem_text">No items</div>';
	        }
		}

		function add_tag_div(ele) {
			if (ele) {
            	const newDivChild = document.createElement('div');

            	// Add a class to the new div
            	newDivChild.classList.add('selected_items', 'border');
            	newDivChild.innerHTML = '<div class="noitem_text">No items</div>';

            	ele.parentElement.appendChild(newDivChild);

            	return newDivChild;
            }

            return false;
		}

		function init_mutiple_tag(className = '') {
			const selElements = document.getElementsByClassName(className);
			
			for (let i = 0; i < selElements.length; i++) {
				let tagSelector =  selElements[i].parentElement.querySelector('.selected_items');

	            if (!tagSelector) {
	            	tagSelector = add_tag_div(selElements[i]);

	            	add_tag_item(selElements[i]);
	            }

			}
		}

		$(document).ready(function() {
			init_mutiple_tag('selector_select');
		});
	</script>

	<style type="text/css">
		.selected_items {
			display: flex;
		    gap: 5px;
		    flex-wrap: wrap;
		    padding: 10px;
		    border-radius: 5px;
		}

		.badge_item {
			display: grid;
		    grid-template-columns: 1fr auto;
		    align-items: center;
		    grid-gap: 5px;
		}

		.bbtn {
			padding: 0px;
		    height: auto;
		    color: #fff;
		    font-size: 15px;
		}

		.noitem_text {
			color: rgba(0, 0, 0, 0.65);
		}

		.field_container {
			display: grid;
		    grid-template-columns: 1fr auto;
		    grid-gap: 12px;
		}
	</style>
</head>
<body>
	<div class="container-fluid">
		<form method='post' name='theform' id="theform" action='portal_addrbook_edit.php?userid=<?php echo attr_url($userId) ?>'>

			<div class="form-group">
			    <label><?php echo xlt('Name'); ?></label>
			    <input type="text" class="form-control" value="<?php echo $displayName; ?>" disabled>
			</div>

			<div class="form-group">
				<label><?php echo xlt('Assign Patient'); ?></label>
				<div class="field_container">
					<div>
						<select id="include_patients" name="include_patients[]" class="selector_select" multiple style="display:none;">
							<?php
								foreach ($insPatientsList as $insPatientId => $insPatientName) {
									?>
									<option value="<?php echo $insPatientId ?>" selected><?php echo $insPatientName; ?></option>
									<?php
								}
							?>
			        	</select>
			    	</div>
			    	<div>
			    		<button type="button" class="btn btn-primary" onclick='sel_patient()'><?php echo xlt('Add'); ?></button>
			    	</div>
				</div>
	    	</div>

			<div class="form-group">
				<label><?php echo xlt('Assign Payer'); ?></label>
				<div class="field_container">
					<div>
						<select id="include_payers" name="include_payers[]" class="selector_select" multiple style="display:none;">
							<?php
								foreach ($insPayerList as $insPayerId => $insPayerName) {
									?>
									<option value="<?php echo $insPayerId ?>" selected><?php echo $insPayerName; ?></option>
									<?php
								}
							?>
			        	</select>
			    	</div>
			    	<div>
			    		<button type="button" class="btn btn-primary" onclick='sel_insurance()'><?php echo xlt('Add'); ?></button>
			    	</div>
				</div>
	    	</div>

	    	<div class="form-group">
				<div class="form-check">
				  <input class="form-check-input" type="checkbox" value="1" name="portal_access" <?php echo !empty($abookData) && $abookData['portal_access'] == "1" ? "checked" : "" ?>>
				  <label class="form-check-label" for="flexCheckDefault">
				    <?php echo xlt('Allow portal access'); ?>
				  </label>
				</div>
	    	</div>

	    	<div class="form-group">
	    		<button type="submit" name="submit" value="submit" class="btn btn-primary"><?php echo xlt('Submit'); ?></button>
	    	</div>

		</form>
	</div>
</body>
</html>