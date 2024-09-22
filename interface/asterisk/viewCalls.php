<?php 

require_once("../globals.php"); 
require_once("$srcdir/options.inc.php");
require_once("$srcdir/patient.inc");
require_once("$srcdir/wmt-v2/wmtstandard.inc");
require_once("$srcdir/wmt-v2/wmt.msg.inc");
require_once("$srcdir/OemrAD/oemrad.globals.php");

use OpenEMR\OemrAd\Caselib;
use OpenEMR\Core\Header;

?>
<html>
<head>
	<title><?php echo htmlspecialchars( xl('Calls on PBX Extension'), ENT_NOQUOTES); ?></title>
	
	<?php Header::setupHeader(['opener', 'dialog', 'jquery']); ?>

	<script type="text/javascript" src="js/prototype-1.4.0.js"></script>
	<script type='text/javascript'><?php include($GLOBALS['srcdir'].'/wmt-v2/report_tools.inc.js'); ?></script>
	
	<link rel="stylesheet" href="<?php echo $css_header; ?>" type="text/css">

    <script language='javascript'>
		async function handleGoToOrder(id, pid, pubpid = '', pname = '', dobstr = '') {
			//const pData = await handleSetPatient(pid);
			//if(pData !== false && pData['data']) {
				handleSetPatientData(pid, function() {
					top.activateTabByName("RTop",true);
				});
			//}

			top.navigateTab(top.webroot_url + "/interface/forms/rto1/new.php?pop=db&id="+id+"&pid="+pid, "RTop", function () {
				top.activateTabByName("RTop",true);
			});
		}

		function handleSetPatientData(pid, callbackfun = null) {
			//parent.left_nav.setPatient(pname, pid, pubpid, '',dobstr);
			//top.RTop.location = top.webroot_url + "/interface/patient_file/summary/demographics.php?set_pid=" + pid;
			top.navigateTab(top.webroot_url + "/interface/patient_file/summary/demographics.php?set_pid=" + pid,"pat", function () {
					if (callbackfun instanceof Function) {
						callbackfun();
					} else {
						top.activateTabByName("pat",true);
					}
			});
		}

		function oldEvt(eventid) {
			dlgopen('<?php echo $GLOBALS['webroot']; ?>/interface/main/calendar/add_edit_event.php?eid=' + eventid, 'blank', 775, 500);
		}

		async function handlegotoCase(case_id, pid, section = '') {
			//const pData = await handleSetPatient(pid);

			top.left_nav.closetab = false;
			//if(pData !== false && pData['data']) {
				handleSetPatientData(pid, function() {
					top.activateTabByName("case",true);
				});
			//}

			let sectionurl = section != "" ? '&sectionto='+section : '';

			top.navigateTab(top.webroot_url + '/interface/forms/cases/view.php?id='+case_id+'&pid='+pid+'&list_mode=list&list_popup=&popup=no&caller=patient' + sectionurl,"case", function () {
				top.activateTabByName("case",true);
			});
		}

		var currentNum = "";
		var initFetch = false;

		function getdata(){
 		    var url = 'checkcall.php';
		    var target = 'content_refresh1';
		    var myAjax = new Ajax.PeriodicalUpdater(target, url, {
		    	asynchronous:true, 
		    	onSuccess:function(transport){
		    		let response_json = JSON.parse(transport.responseText);
		    		let cNum = "";

		    		if(response_json.hasOwnProperty("error") && response_json['error'] === true) {
		    			jQuery('#content_refresh').html(response_json['message']);
		    		}

		    		if(response_json.hasOwnProperty("call_status") && response_json['call_status'] === true) {

		    			cNum = response_json['num'];
		    		} else if(response_json.hasOwnProperty("call_status") && response_json['call_status'] === false) {
		    			cNum = "";


		    			//jQuery('#content_refresh').html('Idle - No current call information');
		    		}

		    		if((currentNum != cNum && cNum == "") || initFetch === false) {
		    			// Fetch Call History
		    			fetchCallHistory('#content_refresh');
		    		}
		    		
		    		if(currentNum != cNum) {
		    			if(cNum != "") {
		    				fetchsection('#content_refresh', 'patient_details', response_json['data']);
		    				top.activateTabByName("pbxdata",true);
		    			}

		    			currentNum = cNum;
		    		}

		    		// Set Status True
		    		initFetch = true;
		    	},
		    	frequency:5
		    });
		}

		function fetchsection(section = '', action = '', data = []) {
			fetch("ajax_call.php", {
			    method: 'post',
			    headers: {
			        'Accept': 'application/json',
			        'Content-Type': 'application/json'
			    },
			    body: JSON.stringify ({
					data: data,
					action: action,
				})
			}).then((response) => {
			    return response.text();
			}).then((res) => {
				if(section != "") {
					jQuery(section).html(res);
				}
			}).catch((error) => {
			    console.log(error)
			});
		}

		function fetchCallHistory(section = '') {
			fetch("getCallHistoryFromCSVFile.php")
			.then((response) => {
			    return response.text();
			})
			.then((res) => {
				if(section != "") {
					jQuery(section).html(res);
				}
			}).catch((error) => {
			    console.log(error)
			});
		}

		window.document.addEventListener('scrollHeightChange', handleEvent, false);
		function handleEvent(e) {
			if(e.detail.page == "case_manager_report") {
				document.querySelector('#'+e.detail.elementId+' iframe').style.height = e.detail.scrollHeight + 80;
				document.querySelector('#'+e.detail.elementId+' .iframe-loader').style.display = "none";
			}
		}

		// Onload Event
		jQuery(document).ready(function() {
			getdata();
		});

		parent.document.addEventListener("extension-update", function(event) {
			var currentNum = "";
			var initFetch = false;
			getdata();
		});

		jQuery(document).on('click', '#contents .list_of_payers_table tbody td.details-control', function () {
            toggleRowDetails($(this));
        });

		function toggleRowDetails(el, mode = 0) {
            let tr = jQuery(el).closest('tr');
            let childkey = tr.data('key');
            let child_tr = jQuery('.row-details-'+childkey);

            if(!tr.hasClass("details") || mode == 1) {
                tr.addClass('details');
                child_tr.addClass('show');
            } else if(tr.hasClass("details") || mode == 2) {
                tr.removeClass('details');
                child_tr.removeClass('show');
            }
            
        }
	</script>

	<style type="text/css">
		.card {
		    box-shadow: 1px 1px 1px hsl(0 0% 0% / .2);
		    border-radius: 0;
		}
		section {
		    background: var(--white);
		    margin-top: 0.25em;
		    padding: 0.25em;
		}
		.rowdetail {
            display: none;
        }
        .rowdetail.show {
            display: table-row !important;
        }
	</style>
</head>
<body class="container-fluid mt-3 bg-light">
	<div id="main">
        <div id="contents" >
			<div id='content_refresh'></div>
		</div>
	</div>
</body>
</html>
