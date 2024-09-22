<?php

require_once(dirname(__FILE__, 8) . "/interface/globals.php");
require_once("$srcdir/patient.inc");

use OpenEMR\Core\Header;
use Vitalhealthcare\OpenEMR\Modules\Generic\Controller\FormController;

$formId = isset($_REQUEST['form_id']) ? $_REQUEST['form_id'] : "";
$formPid = isset($_REQUEST['form_pid']) ? $_REQUEST['form_pid'] : "";
$formDataId = isset($_REQUEST['form_data_items']) ? $_REQUEST['form_data_items'] : "";
$formType = "form";

$p1 = new FormController();
$ftv = $p1->getFormIdType($formId);

if(isset($ftv['formId']) && $ftv['formType']) {
    $formId = $ftv['formId'];
    $formType = $ftv['formType'];
}

if(empty($formId) || empty($formDataId) || $formDataId != "new") {
    echo xlt('Empty data');
    exit();
}

$patientForm = new FormController();
$reqBeforeSendingData = $patientForm->checkRequiredFieldBeforeSend($formId, $formDataId, $formType);

if(empty($reqBeforeSendingData) || $reqBeforeSendingData === false) {
    echo xlt('No Required field data available.');
    exit();
}

$reqBeforeSendingData = $reqBeforeSendingData['req_form'];

?>
<!DOCTYPE html>
<html lang="">
<head>
    <title><?php echo xlt('Required to be filled before sending') ?></title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <?php Header::setupHeader(['dialog', 'opener', 'main_theme', 'main-theme', 'jquery']);  ?>

    <style type="text/css">
        .formContainer {
            min-height: 140px;
        }

        .statusIcon .valid {
            color: #04AA6D;
        }

        .statusIcon .invalid {
            color: red;
        }
    </style>

    <script type="text/javascript">
        var formsData = {};

        $(document).ready(function() {
            // Check Validation
            checkReqField();
        });

        function checkReqField() {
            let formFieldStatus = false
            let formIdEle = document.querySelectorAll('li[data-formid]');

            formIdEle.forEach((formIdItem) => {
                let formidAttr = formIdItem.getAttribute('data-formid');
                
                if(formsData.hasOwnProperty(formidAttr)) {
                    formIdItem.querySelector('span.statusIcon').innerHTML = '<i class="valid fa fa-check-circle" aria-hidden="true"></i>';
                    formFieldStatus = true;
                } else {
                    formIdItem.querySelector('span.statusIcon').innerHTML = '<i class="invalid fa fa-times-circle" aria-hidden="true"></i>';
                    formFieldStatus = false;
                }
            });

            return formFieldStatus;
        }

        function getFormData(formId) {
            return formsData.hasOwnProperty(formId) ? formsData[formId] : {};
        }

        function set_ReqFormField(formId, formData = {}) {
            if(formId != "") {
                formsData[formId] = formData;

                // Check Validation
                checkReqField();
            }
        }
    </script>
</head>
<body>
    <div class="container-fluid">
        <div class="row">
            <div class="col-12">
                <ul class="formContainer list-group mt-2">
                <?php
                    foreach ($reqBeforeSendingData as $formItem) {
                        if(isset($formItem['form'])) {
                            ?>
                              <li class="list-group-item d-flex justify-content-between align-items-center" data-formid="<?php echo $formItem['form']['id'] ?>">
                                <a href="#!" class='linktext' onclick="fillForm('<?php echo $formItem['form']['id'] ?>', 'new', '<?php echo $formPid; ?>');"><?php echo $formItem['form']['template_name']; ?></a>
                                <span class="statusIcon"></span>
                            </li>
                            <?php
                        }
                    }
                ?>
                </ul>

                <button class="btn btn-primary px-4 me-3" id="btnSubmit" type="button" onclick="submitBtn()" ><?php echo xlt('Submit'); ?></button>
            </div>
        </div> 
    </div>
    
    <script type="text/javascript">
        function submitBtn() {
            // Check Validation
            let formFieldStatus = checkReqField();

            if(formFieldStatus === true) {
                opener.setsendformdata(formsData);
                dlgclose();
            }
        }
    </script>
</body>
</html>
