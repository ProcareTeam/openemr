<?php

require_once("../../globals.php");
require_once($GLOBALS['srcdir']."/wmt-v3/wmt.globals.php");
//Included EXT_Message File
include_once("$srcdir/OemrAD/oemrad.globals.php");

use OpenEMR\Common\Csrf\CsrfUtils;
use OpenEMR\Core\Header;
use OpenEMR\OemrAd\Attachment;

$orderId = isset($_REQUEST['orderId']) ? $_REQUEST['orderId'] : "";
$pid = isset($_REQUEST['pid']) ? $_REQUEST['pid'] : "";

$ajax_mode = isset($_REQUEST['ajax_mode']) ? $_REQUEST['ajax_mode'] : "";

if(isset($ajax_mode) && !empty($ajax_mode)) {

    if($ajax_mode == "order_pdf") {
        $pdfContent = Attachment::getOrderPDF(array(array("order_id" => $orderId)), $pid);
        $pdf_name = "order.pdf";

        header('Content-Type: application/pdf');
        header('Content-Length: '.strlen( $pdfContent ));
        header('Content-disposition: inline; filename="' . $pdf_name . '"');
        header('Cache-Control: public, must-revalidate, max-age=0');
        header('Pragma: public');
        echo $pdfContent;
        exit();
    }
}

$jsonAttachment = array(
	"orders" => array(
		array("order_id" => $orderId)
	)
);
$jsonAttachment = urlencode(json_encode($jsonAttachment));

?>
<!DOCTYPE html>
<html lang="">
<head>
    <title><?php echo xlt('Send External Reminder') ?></title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <?php Header::setupHeader(['dialog', 'opener', 'main_theme', 'main-theme', 'jquery', 'oemr_ad']); ?>

    <script type="text/javascript" src="<?php echo $GLOBALS['webroot']; ?>/interface/main/messages/js/messages.js"></script>
  
    <script type="text/javascript">
    	function handleSendExternalReminder(ele) {
    		const communicationType = document.getElementById('form_communication_type').value;

    		if(communicationType == "") {
    			alert("Please select communication")
    			return false;
    		}

    		if (communicationType == 'EMAIL') {
    			openEmailPopup('?pid=<?php echo $pid ?>&df_attachment=<?php echo $jsonAttachment; ?>');
    		} else if (communicationType == 'FAX') {
    			openFaxPopup('?pid=<?php echo $pid ?>&df_attachment=<?php echo $jsonAttachment; ?>');
    		} else if (communicationType == 'P_LETTER') {
    			openPostalLetterPopup('?pid=<?php echo $pid ?>&df_attachment=<?php echo $jsonAttachment; ?>');
    		} else if (communicationType == 'PDF') {

                $(ele).attr("disabled", "disabled");

                $.ajax({
                    type: "POST",
                    url: "send_external_reminder.php?ajax_mode=order_pdf",
                    data: {
                        orderId : '<?php echo $orderId; ?>',
                        pid : '<?php echo $pid; ?>',
                    },
                    xhrFields: {
                        responseType: 'blob'
                    },
                    success: function (data) {

                        if(data != "") {
                            var a = document.createElement('a');
                            var url = window.URL.createObjectURL(data);
                            a.href = url;
                            a.download = 'order.pdf';
                            document.body.append(a);
                            a.click();
                            a.remove();
                            window.URL.revokeObjectURL(url);
                        }

                        $(ele).removeAttr("disabled", "disabled");
                    }
                });
            }
    	}

    	function doRefresh(data) {
    		dlgclose();
    	}
    </script>
</head>
<body>
	<div class="container-fluid">
		<div class="row">
            <div class="col-md-12">
                <div class="form-group show-detail">
                	<div class="form-group">
                		<label for="form_communication_type"><?php echo xlt('Communication') ?></label>
                		<select class="form-control" name="form_communication_type" id="form_communication_type">
                			<option value=""><?php echo xlt('Please select') ?></option>
                			<option value="EMAIL"><?php echo xlt('Email') ?></option>
                			<option value="FAX"><?php echo xlt('Fax') ?></option>
                			<option value="P_LETTER"><?php echo xlt('Postal Method') ?></option>
                            <option value="PDF"><?php echo xlt('Print / PDF') ?></option>
                		</select>
                	</div>
                </div>
            </div>
        </div>
        <button type="button" class="btn btn-success float-left" onclick="handleSendExternalReminder(this)" >
            <?php echo xlt('Send'); ?>	
        </button>
	</div>
</body>
</html>