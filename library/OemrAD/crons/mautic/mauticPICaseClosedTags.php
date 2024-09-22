<?php

if(!isset($_SERVER['SERVER_NAME']) || empty($_SERVER['SERVER_NAME'])) {
    $_SERVER['REQUEST_URI']=$_SERVER['PHP_SELF'];
    $_SERVER['SERVER_NAME']='localhost';
    $_SERVER['HTTP_HOST'] = 'localhost';
    $_GET['site'] = 'default';
}
$backpic = "";
$ignoreAuth=1;

require_once(dirname( __FILE__, 3 ) . "/interface/globals.php");

use OpenEMR\Common\Crypto\CryptoGen;
use OpenEMR\Core\Header;

$mautic_host = $GLOBALS['mautic_host'];
$mautic_user = $GLOBALS['mautic_username'];
$mautic_pass = $cryptoGen->decryptStandard($GLOBALS['mautic_password']);

if($mautic_host == NULL || $mautic_user == NULL || $mautic_pass == NULL) {
    echo "Please enter mautic credential";
    exit();
}

$sql = sqlStatement("SELECT 'DFT' as message_type, pd.fname as firstname, pd.lname as lastname, pd.email_direct as email, pd.pubpid as pid, vaiwc.uniqueid as caseId, 'PI Case Closed' as tags ,vaiwc.id from vh_closed_attorney_cases_mautc AS vaiwc left join patient_data pd on pd.pid = vaiwc.pid  where vaiwc.event_id = 'event_closed_Attorney_case' and vaiwc.sent = 100");
if(sqlNumRows($sql) > 0) {
    while ($row = sqlFetchArray($sql)) {
        $row += ['phone' => ''];
        $body_params_json = json_encode($row);
        error_log('BodyParams: ' . $body_params_json);
        try {   
            // Initialize cURL session
            $apiUrl = $mautic_host . "/api/contacts/". $row['pid'] ."/pid/create";
            $ch = curl_init($apiUrl);
            curl_setopt($ch, CURLOPT_POST, 1);
            curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($row));
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

            // Set basic authentication header
            curl_setopt($ch, CURLOPT_HTTPHEADER, array(
                'Authorization: Basic ' . base64_encode($mautic_user . ':' . $mautic_pass),
                'Content-Type: application/x-www-form-urlencoded'
            ));
            $response = curl_exec($ch);

            // Check for cURL errors
            if (curl_errno($ch)) {
                echo 'Curl error: ' . curl_error($ch);
                exit();
            } else {
                $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

                // Handle authentication errors
                if ($httpCode === 401) {
                    echo 'Authentication failed: Unauthorized.';
                    exit();
                } elseif ($httpCode === 403) {
                    echo 'Authentication failed: Forbidden.';
                    exit();
                } else {
                    if (strpos($response, '"isPublished":true') !== false) {
                        if( sqlStatement("UPDATE vh_closed_attorney_cases_mautc SET sent = 1 where id = ".$row['id'])) {
                            $body_params_json = json_encode($row);
                            echo('BodyParams: ' . $body_params_json."<br>");
                        }
                    }
                }
            }
            
            // Close cURL session
            curl_close($ch);
        } catch (Exception $e) {
            echo 'Error: ' . $e->getMessage();
        }
    }
}else {
    echo "No records found";
}

?>