<?php

$isPrint = isset($_REQUEST['action']) && $_REQUEST['action'] == "print" ? true : false;
$checkValidation = isset($_REQUEST['action']) && $_REQUEST['action'] == "validation" ? true : false;

if($isPrint === true || $checkValidation === true) {
	$_SESSION['site'] = 'default';
	$backpic = "";
	$ignoreAuth=1;
}

require_once(dirname(__FILE__, 7) . "/interface/globals.php");
require_once "$srcdir/patient.inc";
require_once "$srcdir/options.inc.php";

use OpenEMR\Common\Csrf\CsrfUtils;
use OpenEMR\Core\Header; 
use Vitalhealthcare\OpenEMR\Modules\Generic\Controller\FormController;

$patientForm = new FormController();

$formId = isset($_REQUEST['form_id']) ? $_REQUEST['form_id'] : "";
$formDataId = isset($_REQUEST['form_data_id']) ? $_REQUEST['form_data_id'] : "";
$pid = isset($_REQUEST['pid']) ? $_REQUEST['pid'] : "";

$json = file_get_contents('php://input');
$jsondata = json_decode($json, true);

if(isset($jsondata['form_data_id']) && !empty($jsondata['form_data_id'])) {
	$formDataId = $jsondata['form_data_id'];
}

if(isset($jsondata['form_id']) && !empty($jsondata['form_id'])) {
	$formId = $jsondata['form_id'];
}

if(isset($jsondata['pid']) && !empty($jsondata['pid'])) {
	$pid = $jsondata['pid'];
}

if(empty($formDataId) && empty($formId)) {
	echo "Print Error";
	exit();
}

if(!empty($formDataId)) {
	$fullFormData = $patientForm->getFullFormData($pid, $formDataId, true);
} else if(!empty($formId)) {
	$fullFormData = $patientForm->getFormPreview($formId);

	if(empty($fullFormData)) {
		echo xlt('Error');
		exit();
	}
}

$formSchema = isset($fullFormData['schema']) && !empty($fullFormData['schema']) ? json_encode($fullFormData['schema']) : "{}";
$formData = isset($fullFormData['data']) && !empty($fullFormData['data']) ? json_encode($fullFormData['data']) : "{}";
$formDetails = isset($fullFormData['form_details']) && !empty($fullFormData['form_details']) ? $fullFormData['form_details'] : array();
$formStatus = isset($formDetails['status']) ? $formDetails['status'] : "";

if(isset($jsondata['form_data']) && !empty($jsondata['form_data'])) {
	$formData = json_encode($jsondata['form_data']);
}

?>
<html>
<head>
    <title><?php echo xlt('Print Form'); ?></title>

    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.0.2/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.0.2/css/font-awesome.css">
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.0.2/dist/js/bootstrap.min.js"></script>

    <link rel="stylesheet" href="<?php echo $GLOBALS['fm_form_portal_url'] ?>/assets/formio.full.min.css">
    <script src='<?php echo $GLOBALS['fm_form_portal_url'] ?>/assets/formio.full.min.js'></script>

    <script type='text/javascript'>
      	window.addEventListener("load", (event) => {
      	  var formOption = { readOnly: true, renderMode: "html", display: "pdf" };
      	   	
      	  <?php if($checkValidation === true) { ?>
      	  	var formOption = { }
      	  <?php } ?>
	      Formio.createForm(document.getElementById('builder'), JSON.parse('<?php echo addslashes($formSchema); ?>'), formOption).then(function(form){
	      	form.submission =  JSON.parse('<?php echo addslashes($formData); ?>');
	      	
	      	form.on('change', function() {
	      		<?php if($checkValidation === true) { ?>
			      	form.emit('checkValidity', form.submission.data);
			      	let formErrorList = [];
			      	form.errors.forEach(function (item, index) {
			      		if(item.hasOwnProperty('message')) {
			      			formErrorList.push(item['message']);
			      		}
					});

			      	var element = document.createElement("input");
		  			element.type = "hidden";
		  			element.id = "errMsg";
		  			element.value = JSON.stringify(formErrorList);

			      	document.querySelector('.page-container').appendChild(element);
	      		<?php } ?>	
	      	});
	      	
	      });
	  	});
    </script>

    <style type="text/css">
    	.print.page {
    		margin-left: 35px;
    		margin-right: 35px;
    	}

    	.print #builder .card  {
    		page-break-inside: avoid;
		}

		.print #builder .formio-form > .formio-component-panel > .card {
			margin-bottom: 30px !important;
			border-radius: 0px !important;
		}

		.print #builder .formio-form > .formio-component-tabs > .card {
		    margin-bottom: 30px !important;
		    border-radius: 0px !important;
		} 

		.print #builder .formio-form > .formio-component-panel > .card .formio-component-tabs > div > .card {
		    margin-bottom: 20px !important;
		    border-radius: 0px !important;
		}

		div.formio-component-signature > div > img{
			max-height: unset !important;
		}

		@media print {
			* {
			    color-adjust: exact !important;
			    -webkit-print-color-adjust: exact !important;
			    print-color-adjust: exact !important;
			}
		}
    </style>
</head>
<body>
	<div class="page-container <?php echo $isPrint === true ? "print page" : "container mt-4"; ?>">
		<div style="height:0px;">
		</div>
		<div class="p-0">
			<div class="px-6 mb-3 mt-0 text-center">
				<h3 class="text-4xl text-body"><?php echo isset($formDetails['form_title']) ? $formDetails['form_title'] : ""; ?></h3>
			</div>
			<div id="builder" class="container-fluid p-0"></div>
		</div>
	</div>
</body>
</html>
