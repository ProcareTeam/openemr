<?php
use OpenEMR\Common\Crypto\CryptoGen;
require_once("../globals.php"); 

// OEMR - Asterisk Change
?>
<script type="text/javascript" src="<?php echo $GLOBALS['webroot']; ?>/library/dialog.js"></script>
<?php
// End

$number = !empty($_GET['phone_number']) ? $_GET['phone_number'] : '';
$errno = "";
$errstr = "";
$timeout = "30";
$am_host = $GLOBALS['asterisk_manager_host'];
$am_user = $GLOBALS['asterisk_manager_user'];
$am_pass = $cryptoGen->decryptStandard($GLOBALS['asterisk_manager_password']);

$strContext = "from-internal";
$strWaitTime = $GLOBALS['asterisk_manager_call_timeout'];
$exten = sqlQuery("SELECT `extension` FROM `user_extension` WHERE `username` = ? ORDER BY id DESC LIMIT 1", array($_SESSION['authUser']));
$extension = $exten['extension'];
$strChannel = "$extension";
$strPriority = "1";

$strCallerId = "Web Call $number";
$pos=strpos ($number,"local");

$socket = fsockopen($am_host,"5038", $errno, $errstr, $timeout);

if (!$socket) {
    echo "Error connecting to Asterisk Manager Interface: $errstr ($errno)";
    
    // OEMR - Asterisk Change
    echo "<html>\n<body>\n<script>\nwindow.setTimeout(\"dlgclose()\", 5000);\n</script>\n</body>\n</html>\n";
    // End

    exit(1);
}
sendCommand("Action: Login\r\nUsername: $am_user\r\nSecret: $am_pass\r\n\r\n");
$response = readManagerResponse();
if (strpos($response, 'Authentication accepted') === false) {
    echo "Error logging in to Asterisk Manager Interface.";
    
    // OEMR - Asterisk Change
    echo "<html>\n<body>\n<script>\nwindow.setTimeout(\"dlgclose()\", 5000);\n</script>\n</body>\n</html>\n";
    // End

    exit(1);
}
sendCommand("Action: Originate\r\nChannel: SIP/$extension\r\nTimeout: $strWaitTime\r\nExten: $number\r\nContext: $strContext\r\nPriority: 1\r\nCallerID: $strCallerId\r\nAsync: yes\r\n\r\n");
$response = readManagerResponse();
if (strpos($response, 'Success') === false) {
    echo "Error making the call: $response";

    // OEMR - Asterisk Change
    echo "<html>\n<body>\n<script>\nwindow.setTimeout(\"dlgclose()\", 5000);\n</script>\n</body>\n</html>\n";
    // End
}
else {
    echo "Transferring ".$number." number to Extension";

    // OEMR - Asterisk Change
    echo "<html>\n<body>\n<script>\nwindow.setTimeout(\"dlgclose()\", 5000);\n</script>\n</body>\n</html>\n";
    // End
}

sendCommand("Action: Logoff\r\n\r\n");

fclose($socket);

function sendCommand($command)
{
    global $socket;
    fwrite($socket, $command);
}

// Function to read the response from the Asterisk Manager Interface
function readManagerResponse()
{
    global $socket;
    $response = '';
    while (!feof($socket)) {
        $response .= fgets($socket);
        if (strpos($response, "\r\n\r\n") !== false) {
            break;
        }
    }
    return $response;
}
?>