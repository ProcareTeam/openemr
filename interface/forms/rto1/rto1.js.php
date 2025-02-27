<script type="text/javascript">
	var order_cnt = "";
	var order_case_type = "";
	function sel_case(pid, cnt = "", type = "") {
		order_cnt = cnt;
		order_case_type = type;
	  	var href = "<?php echo $GLOBALS['webroot'].'/interface/forms/cases/case_list.php?mode=choose&popup=pop&pid='; ?>"+pid+"&payer_info=1";
	  	dlgopen(href, 'findCase', 'modal-lg', '800', '', '<?php echo xlt('Case List'); ?>');
	}

	function setCase(case_id, case_dt, desc) {
		var decodedDescString = atob(desc);

		if(order_case_type == "filter") {
			document.getElementById('tmp_case_id').value = case_id;
		} else {
			if(order_cnt && order_cnt != "") {
				var rto_case = document.getElementById('rto_case_'+order_cnt);
				rto_case.value = case_id;
				rto_case.dispatchEvent(new Event('change'));

				document.getElementById('case_description_title_'+order_cnt).innerHTML = decodedDescString;
			} else {
				document.getElementById('rto_case').value = case_id;
				document.getElementById('case_description_title').innerHTML = decodedDescString;
			}
		}
	}	

	// @VH: [31012025]
	function sel_appt(pid, cnt = "") {
		order_cnt = cnt;
	  	let rto_case = null;

		if (order_cnt == "") {
			rto_case = document.getElementById('rto_case');
		} else if (order_cnt != "") {
			rto_case = document.getElementById('rto_case_'+order_cnt);
		}

		if (!rto_case || rto_case.value == "") {
			alert("Please select case");
			return false;
		}

	  	var href = "<?php echo $GLOBALS['webroot'].'/interface/main/attachment/find_appt_popup.php?pid='; ?>" + pid + "&case_id=" + rto_case.value;
	  	dlgopen(href, 'findAppt', 'modal-lg', '800', '', '<?php echo xlt('Appt List'); ?>');
	}

	// @VH: [31012025]
	function setAppt(appt_id, appt_info = {}) {
		let apptTitle = [];

		console.log(appt_info);
		if (appt_info['appt_date'] != "" && appt_info['appt_time'] != "") {
			apptTitle.push(appt_info['appt_date'] + " " + appt_info['appt_time']);
		}

		if (appt_info['appt_type'] != "") {
			apptTitle.push(appt_info['appt_type']);
		}

		if (appt_info['appt_provider'] != "") {
			apptTitle.push(appt_info['appt_provider']);
		}

		apptTitle = apptTitle.join(" - ");
		if (apptTitle != "") {
			apptTitle = "[" + apptTitle + "]";
		}

		if(order_cnt && order_cnt != "") {
			var rto_appt = document.getElementById('rto_appt_'+order_cnt);
			rto_appt.value = appt_id;
			rto_appt.dispatchEvent(new Event('change'));

			document.getElementById('appt_description_title_'+order_cnt).innerHTML = apptTitle;
		} else {
			document.getElementById('rto_appt').value = appt_id;
			document.getElementById('appt_description_title').innerHTML = apptTitle;
		}
	}

	async function checkCaseValidation(pid, r_action = false, c_id = false) {
		var rto_action = r_action;
		var case_id = c_id;
		
		if(rto_action === false) {
			rto_action = document.getElementById('rto_action').value;
		}

		if(case_id === false) {
			case_id = document.getElementById('rto_case').value;
		}
		
		if(!rto_action || rto_action == "") {
			return true;
		}

		if(!case_id || case_id == 0) {
	        var cCount = await caseCount(pid);
	        if(Number(cCount) > 0) {
	            alert('<?php echo xls("You must choose a case"); ?>');
	            return false;
	        }
	    } else {
	    	var isRecentCaseInActive = await checkRecentInactive(pid, case_id);
	        if(isRecentCaseInActive == true) {
	            var msg1 = '<?php echo xls('Selected case is inactive. Choose "OK" to save the chosen case and change the case state from inactive to active.  Choose "Cancel" to choose another case or create a new case'); ?>?';
	            var confirmRes  = confirm(msg1);
	            if(confirmRes) {
	                var activateCaseDAta = await activateCase(pid, case_id)
	            } else if(!confirmRes) {
	                return false;
	            }
	        }
		}
	} 

	function getFormData($form){
	    var unindexed_array = $form.serializeArray();
	    var indexed_array = {};

	    $.map(unindexed_array, function(n, i){
	        indexed_array[n['name']] = n['value'];
	    });

	    return indexed_array;
	}
</script>