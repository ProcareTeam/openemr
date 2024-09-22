<?php

require_once(dirname(__FILE__, 8) . "/interface/globals.php");
require_once("$srcdir/patient.inc");

use OpenEMR\Core\Header;
use Vitalhealthcare\OpenEMR\Modules\Generic\Controller\FormController;

$formDataId = isset($_REQUEST['form_data_id']) ? $_REQUEST['form_data_id'] : "";

$patientForm = new FormController();
$urlData = $patientForm->getFormLinkByForm($formDataId);

?>
<!DOCTYPE html>
<html lang="">
<head>
    <title><?php echo xlt('Contact') ?></title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <?php Header::setupHeader('common', 'opener'); ?>
</head>
<body>
    <div class="container-fluid">
        <div class="row">
            <div class="col-md-12">
                <div class="form-group show-detail">
                	<div class="form-group">
                		<label for="form_patient"><?php echo xlt('Form Link') ?></label>
                		<textarea class="form-control" value="" readonly style="height:100px"><?php echo isset($urlData['form_link']) ? $urlData['form_link'] : ""; ?></textarea>
                	</div>

                	<div class="form-group">
                		<label for="form_patient"><?php echo xlt('Shorten Link') ?></label>
                		<textarea class="form-control" value="" readonly style="height:60px"><?php echo isset($urlData['shorten_url']) ? $urlData['shorten_url'] : ""; ?></textarea>
                	</div>
                </div>
            </div>
        </div> 
    </div>
</body>
</html>
