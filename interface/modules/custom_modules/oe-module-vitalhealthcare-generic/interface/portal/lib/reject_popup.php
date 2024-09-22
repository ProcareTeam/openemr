<?php

require_once(dirname(__FILE__, 8) . "/interface/globals.php");
require_once("$srcdir/patient.inc");

use OpenEMR\Core\Header;
use Vitalhealthcare\OpenEMR\Modules\Generic\Controller\FormController;

$formDataId = isset($_REQUEST['form_data_id']) ? $_REQUEST['form_data_id'] : "";
$page_action = isset($_REQUEST['action']) ? $_REQUEST['action'] : '';

$patientForm = new FormController();

if(!empty($page_action)) {
    if($page_action == "action_rejected") {

        try {
            
            if(empty($formDataId)) {
                throw new \Exception("Empty form data id");
            }

            $formAssocResult = $patientForm->getFormAssocItems($formDataId);

            foreach ($formAssocResult as $formassocrow) {
                if(isset($formassocrow['id'])) {

                    // Update Status
                    $patientForm->updateOnsiteForms(array(
                        "status" => FormController::REJECT_LABEL,
                        "reviewed_date" => "CURRENT_TIMESTAMP",
                        "reviewer" => $_SESSION['authUserID'],
                        "denial_reason" => isset($_REQUEST['reject_note']) ? $_REQUEST['reject_note'] : ""
                    ), $formassocrow['id']);
                }
            }

        } catch (\Throwable $e) {
            echo json_encode(array("status" => false, "message" => $e->getMessage()));
        }

        echo json_encode(array("status" => true, "message" => "Success"));
        exit();
    }
}

?>
<!DOCTYPE html>
<html lang="">
<head>
    <title><?php echo xlt('Contact') ?></title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <?php Header::setupHeader(['dialog', 'opener', 'main_theme', 'main-theme', 'jquery']); ?>

    <script type="text/javascript">
        async function rejectForm(form_data_id) {
            if(confirm("Do you want to reject form?")) {
             var f = document.forms[0];

             if(f.reject_note.value == "") {
                alert("Please enter note value");
                return false;
             }

             const formResponce = await actionHandleCall({
                 'action' : 'action_rejected',
                 'form_data_id' : form_data_id,
                 'reject_note' : f.reject_note.value
             }, true);

             if(formResponce.hasOwnProperty('status') && formResponce['status'] === true) {
                alert(formResponce['message']);
                
                selreject(form_data_id);
             } else {
                alert(formResponce['message']);
             }
            }
        }

        async function actionHandleCall(data, doUpdate = false, doUpdateAll = false) {
            var res = await $.ajax({
                url: 'reject_popup.php',
                type: 'POST',
                data: { ...data }
            });

            //Parse JSON Data.
            if(res != undefined) {
                res = JSON.parse(res);
            }

            return res;
        }

        function selreject(form_data_id) {
          if (opener.closed || ! opener.setreject)
           alert("<?php echo xlt('The destination form was closed; I cannot act on your selection.'); ?>");
          else
           opener.setreject(form_data_id);
           dlgclose();
          return false;
         }
    </script>
</head>
<body>
    <div class="container-fluid">
        <form class="form" id="reject-form" method="post" action="reject_popup.php" role="form">
            <input type="hidden" id="form_data_id" name="form_data_id" value='<?php echo $formDataId; ?>'>
            <div class="row">
                <div class="col-md-12">
                    <div class="form-group show-detail">
                    	<div class="form-group">
                    		<label for="reject_note"><?php echo xlt('Note') ?></label>
                    		<textarea class="form-control" id="reject_note" name="reject_note" value="" style="height:220px"></textarea>
                    	</div>

                        <div class="mt-2">
                            <button type="button" name="submit_btn" value="submit" class="btn btn-primary" onclick="rejectForm('<?php echo $formDataId; ?>')"><?php echo xlt('Reject') ?></button>
                            <button type="button" class="btn btn-secondary" onclick="dlgclose();"><?php echo xlt('Cancel') ?></button>
                        </div>
                    </div>
                </div>
            </div>
        </form>
    </div>
</body>
</html>
