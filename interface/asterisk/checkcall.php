<?php
require_once("../globals.php");

use OpenEMR\Common\Crypto\CryptoGen;
use OpenEMR\Core\Header;

try {

$authUser = isset($_REQUEST['authUser']) ? $_REQUEST['authUser'] : $_SESSION['authUser'];

if(empty($authUser)) {
    throw new Exception("Extension user not found");
    exit();
}

$userData = sqlQuery("SELECT `extension` FROM `user_extension` WHERE `username` = ? ORDER BY id DESC LIMIT 1", array($authUser)); 

if(empty($userData)) {
    throw new Exception("You are unavailable to view incoming call details");
    exit();
}


$am_host = $GLOBALS['asterisk_manager_host'];
$am_user = $GLOBALS['asterisk_manager_user'];
$am_pass = $cryptoGen->decryptStandard($GLOBALS['asterisk_manager_password']);

$socket = fsockopen($am_host,"5038", $errno, $errstr, $timeout);

fputs($socket, "Action: Login\r\n");
fputs($socket, "UserName: $am_user\r\n");
fputs($socket, "Secret: $am_pass\r\n\r\n");
fputs($socket, "Action: CoreShowChannels\r\n\r\n");
fputs($socket, "Action: Logoff\r\n\r\n");

$wrets = '';
$callerdetails1 = [];

if (!is_resource($socket)) {
    throw new Exception("Socket Connection Error");
    exit();
}

while (!feof($socket)) {
    $wrets .= fread($socket, 4096);
}
fclose($socket);

$channels = explode("\r\n\r\n", $wrets);

foreach ($channels as $channel) {
// Split each channel's details into an associative array
    $channelData = [];
    $lines = explode("\r\n", $channel);
    foreach ($lines as $line) {
        $parts = explode(':', $line, 2);
        if (count($parts) === 2) {
            $channelData[$parts[0]] = trim($parts[1]);
        }
    }
    if (isset($channelData['Channel'])) {
        if($channelData['ChannelStateDesc'] == 'Up' && $channelData['CallerIDNum'] == $userData['extension']) {
            // @VH - Asterisk Change
            $callerdetails1[] = $channelData;
            //End
        }
    }
}

$callerdetails = array_unique($callerdetails1);


if(isset($callerdetails) && is_array($callerdetails) && count($callerdetails) > 0) {
    // if(isset($userData['availability']) && $userData['availability'] == "Available") {

        // Remove country code
        $num = isset($callerdetails[0]['ConnectedLineNum']) ? $callerdetails[0]['ConnectedLineNum'] : "";

        echo json_encode(array('call_status' => true, 'data' => $callerdetails, 'num' => preg_replace('/^\+?1|\|1|\D/', '', ($num))));
        exit();
    // }
}

echo json_encode(array('call_status' => false));

} catch(Exception $e) {
    echo json_encode(array('error' => true, 'message' => $e->getMessage()));
}
exit();