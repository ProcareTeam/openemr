<?php

// Sanitize escapes
$sanitize_all_escapes = true;

// Stop fake global registration
$fake_register_globals = false;

require_once("../../globals.php");
require_once($GLOBALS['srcdir']."/wmt-v3/wmt.globals.php");

//Included EXT_Message File
include_once("$srcdir/OemrAD/oemrad.globals.php");


use OpenEMR\Core\Header;
use OpenEMR\OemrAd\Smslib;
use OpenEMR\OemrAd\Twiliolib;
use OpenEMR\OemrAd\EmailMessage;
use OpenEMR\OemrAd\MessagesLib;

// Set "sender" phone number
$send_phone = preg_replace('/[^0-9]/', '', Smslib::getDefaultFromNo());

$form_to_phone = trim(strip_tags($_REQUEST['phone']));
$form_message = trim(strip_tags($_REQUEST['message']));

try {

    // Validate sender
    if (empty($send_phone)) {
        throw new \Exception("Missing required sender number!!!");
    }

    if (isset($_POST) && isset($_POST['send_sms'])) {

        $patPhoneData = MessagesLib::getPhoneNumbers($form_to_phone);
        $isValidPhoneNumber = false;
        $phonenumber = "";

        if (!empty($patPhoneData) && isset($patPhoneData['msg_phone']) && strlen($patPhoneData['msg_phone']) == "11" && substr($patPhoneData['msg_phone'], 0, 1) == "1") {
           $isValidPhoneNumber = true;
           $phonenumber = $patPhoneData['msg_phone'];
        }

        if (!$isValidPhoneNumber || empty($phonenumber) ) {
            throw new \Exception("Not valid phone number.");
        }

        if (empty($form_message)) {
            throw new \Exception("Empty message content.");
        }

        $sms = Smslib::getSmsObj($send_phone);
        $sms->pid = 0;

        $result = $sms->smsTransmit($form_to_phone, $form_message, 'text');
        $msgId = $result['msgid'];
        $msgStatus = isset($result['msgStatus']) ? $result['msgStatus'] : 'MESSAGE_SENT';

        if (empty($msgId)) {
            throw new \Exception("Message delivery failure!!");
        }
        
        // Raw data
        $raw_data = json_encode(array('pid' => 0, 'phone' => $form_to_phone));

        // Log the message
        $datetime = strtotime('now');
        $msg_date = date('Y-m-d H:i:s', $datetime);
        $sms->logSMS('SMS_MESSAGE', $form_to_phone, $send_phone, 0, $msgId, $msg_date, $msgStatus, $form_message, 'out', false, $raw_data);

        ?>
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset="utf-8">
            <meta name="viewport" content="width=device-width, initial-scale=1">
            <title></title>
            <?php echo Header::setupHeader(['opener', 'dialog']); ?>
        </head>
        <body>
            <script type="text/javascript">
                opener.doRefresh();
                dlgclose();
            </script>
        </body>
        </html>
        <?php

        exit();
    }

} catch (Exception $e) {
    $errorMsg = $e->getMessage();
}

?><!DOCTYPE html>
<!--[if lt IE 7]> <html class="no-js ie6 oldie" lang="en"> <![endif]-->
<!--[if IE 7]>  <html class="no-js ie7 oldie" lang="en"> <![endif]-->
<!--[if IE 8]>  <html class="no-js ie8 oldie" lang="en"> <![endif]-->
<!--[if gt IE 8]><!--> <html class="no-js" lang="en"> <!--<![endif]-->
<head>
    <meta charset="utf-8" />

    <title>Send SMS</title>
    <meta name="viewport" content="width=device-width,initial-scale=1" />

    <link rel="shortcut icon" href="images/favicon.ico" />

    <?php Header::setupHeader(['opener', 'dialog', 'jquery', 'jquery-ui', 'main-theme', 'fontawesome', 'jquery-ui-base']); ?>

    <script>
        // Wait for the page to load
        window.addEventListener('DOMContentLoaded', (event) => {
            // Get the input element
            const phoneInput = document.getElementById('phonenumber');
            
            // Listen for input events
            phoneInput.addEventListener('input', function(event) {
                // Replace anything that is not a number with an empty string
                event.target.value = event.target.value.replace(/[^0-9+\-\(\)\s]/g, '');
            });
        });
    </script>
</head>
<body class="body_top">
    <div id="pnotes" style="max-width: 800px;">
    <?php if (isset($errorMsg) && !empty($errorMsg)) { ?>
    <div class="alert alert-danger" role="alert"><?php echo $errorMsg; ?></div>
    <?php } ?>
    <form name="new_sms" id="new_sms" action="send_sms.php" method="post">
        <div class="form-row">
            <div class="form-group col-sm-12">
                <label><?php echo xlt('To'); ?></label>
                <input type="text" name="phone" id="phonenumber" maxlength="13" class="form-control" value="<?php echo $form_to_phone; ?>" />
            </div>
        </div>
        <div class="form-group">
            <label><?php echo xlt('Message'); ?></label>
            <textarea id="message" name="message" rows="8" class="form-control"><?php echo $form_message; ?></textarea>
        </div>

        <div class="form-group">
            <button type="submit" name="send_sms" class="btn btn-primary"><?php echo xlt('Send SMS'); ?></button>
        </div>
    </form>
    </div>
</body>
</html>