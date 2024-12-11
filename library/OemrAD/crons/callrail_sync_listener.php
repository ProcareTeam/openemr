<?php
$_SERVER['REQUEST_URI']=$_SERVER['PHP_SELF'];
$_SERVER['SERVER_NAME']='localhost';
$_SERVER['HTTP_HOST'] = 'localhost';
$_SESSION['site'] = 'default';
$backpic = "";
$ignoreAuth=1;

require_once(dirname( __FILE__, 2 ) . "/interface/globals.php");
require_once($GLOBALS['srcdir'] . "/OemrAD/oemrad.globals.php");

use OpenEMR\OemrAd\CallrailWebservice;

function isCommandLineInterface(){
    return (php_sapi_name() === 'cli');
}
?>
<?php if(isCommandLineInterface() === false) { ?>
<html>
<head>
	<title>Conrjob - Email</title>
</head>
<body>
<?php } ?>
<?php

/*Fetch Messages*/
$responce = CallrailWebservice::fetchNewIncomingMessages();

if(is_array($responce) && isset($responce['status']) && $responce['status'] == "false") {
	echo $responce['error'];
} else if(is_array($responce) && isset($responce['status']) && $responce['status'] == "true") {
	echo "Total Synced count: " . $responce['synced_count'] . ", Total Failed count: " . $responce['failed_count'];
}

?>
<?php if(isCommandLineInterface() === false) { ?>
</body>
</html>
<?php
}