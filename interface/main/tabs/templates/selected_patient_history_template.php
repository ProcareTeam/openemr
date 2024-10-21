<?php

if (isset($_REQUEST['ajax']) && $_REQUEST['ajax'] == "1") {

	require_once(__DIR__ . '/../../../globals.php');

	$res12 = sqlStatement("SELECT * from vh_recentpatients_history vrh WHERE user_id = ? ", array($_SESSION['authUserID']));

	if(sqlNumRows($res12) > 0) {
		while ($result4 = sqlFetchArray($res12)) {
			$patient_list = !empty($result4['patient_list']) ? json_decode($result4['patient_list'], true) : array();

			$patien_data_list =  sqlStatement("SELECT CONCAT(CONCAT_WS(' ', IF(LENGTH(pd.fname),pd.fname,NULL), IF(LENGTH(pd.lname),pd.lname,NULL)), ' (', pd.pubpid ,')') as patient_name, pid from patient_data pd where pid IN (" . implode(",", $patient_list) . ") ORDER BY FIELD(pid, " . implode(",", $patient_list) . ") ");

			while ($result5 = sqlFetchArray($patien_data_list)) {
		?>
    		<!-- <li class="menuLabel">
            	<div class="d-flex">
            		<div><b><a href="#!" onclick="gotopatientdashboard('<?php //echo $result5['pid'] ?>');"><?php //echo $result5['patient_name'] ?? "" ?></a></b></div>
            	</div>
            </li> -->
            <a class="dropdown-item" href="#!" onclick="gotopatientdashboard('<?php echo $result5['pid'] ?>');" ><?php echo $result5['patient_name'] ?? "" ?></a>
		<?php
			}
		}
	} else {
		?>
		<div class="px-3"><?php echo xlt('Not Found'); ?></div>
		<?php
	}

	exit();
}

?>
<style type="text/css">
	#selectedpatientinfo:hover > .patientfunctions {
		display: block;
		width: 98%;
	}

	#selectedpatientinfo:hover > .patientfunctions li {
		background-color: #fff !important;
	}

	#selectedpatientinfodropdown {
		width: 350px !important;
	}

	.selectedpatientinfoicon {
		font-size: 16px;
    	padding: 0px 5px;
	}

	#selectedpatientinfo {
		background-color: transparent !important;
		border: 0px !important;
		padding: 3px 6px !important;
		width: auto !important;
		height: auto !important;
	}

	/* Remove outline on focus */
    #selectedpatientinfo.dropdown-toggle:focus,  #selectedpatientinfo.dropdown-toggle.dropdown-toggle:active {
        outline: none !important;
        box-shadow: none !important;
    }

    /* Hide the arrow */
    #selectedpatientinfo.dropdown-toggle::after {
        display: none;
    }

</style>

<span id="selectedpatientInfoData">
	<div class="dropdown">
	  <button class="btn btn-secondary dropdown-toggle" type="button" id="selectedpatientinfo" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
	    <span class="selectedpatientinfoicon">
            <i class="fa fa-users" aria-hidden="true"></i>
        </span>
	  </button>
	  <div id="selectedpatientinfodropdown" class="dropdown-menu dropdown-menu-right" aria-labelledby="dropdownMenuButton">
	  	<div class="px-3"><?php echo xla('Loading...'); ?></div>
	  </div>
	</div>
</span>

<script type="text/javascript">
	function fetchSelectedPatientHistory() {
		request = new FormData;
		request.append("ajax", "1");
        
        fetch(webroot_url + "/interface/main/tabs/templates/selected_patient_history_template.php", {
            method: 'POST',
            credentials: 'same-origin',
            body: request
        }).then(function(response) {
        		// When the page is loaded convert it to text
        		return response.text()
    	}).then((htmldata) => {
            document.getElementById('selectedpatientinfodropdown').innerHTML = htmldata;
        }).catch(function(error) {
            console.log('HTML Background Service start Request failed: ', error);
        });
	}

	function gotopatientdashboard(pid) {
		closeDropdown();
		goParentPid(pid);
	}

	function goParentPid(pid) {
		<?php if($GLOBALS['new_tabs_layout'] == 1) { ?>
	  	top.restoreSession();
	  	top.RTop.location = "<?php echo $GLOBALS['rootdir']; ?>/patient_file/summary/demographics.php?set_pid=" + pid;
		<?php } else { ?>

		if( (window.opener) && (window.opener.setPatient) ) {
			window.opener.loadFrame('RTop', 'RTop', 'patient_file/summary/demographics.php?set_pid=' + pid);
		} else if( (parent.left_nav) && (parent.left_nav.loadFrame) ) {
			parent.left_nav.loadFrame('RTop', 'RTop', 'patient_file/summary/demographics.php?set_pid=' + pid);
		} else {
			var newWin = window.open('<?php echo $GLOBALS['rootdir']; ?>/main/main_screen.php?patientID=' + pid);
		}
		<?php } ?>
	}

	$(function() {
		fetchSelectedPatientHistory();
	});

	function closeDropdown() {
        $('#selectedpatientinfodropdown').removeClass('show');
        $('#selectedpatientinfo').attr('aria-expanded', 'false');
    }
</script>