<?php

namespace Vitalhealthcare\OpenEMR\Modules\Generic\Util;

use OpenEMR\Common\Crypto\CryptoGen;

class PropioUtils
{
    public static function getClientId()
    {
        return isset($GLOBALS['propio_clientid']) ? $GLOBALS['propio_clientid'] : "";
    }

    public static function getAccessCode()
    {
        $cryptoGen = new CryptoGen();
        return $cryptoGen->decryptStandard($GLOBALS['propio_access_code']);
    }

    public static function getApiKey()
    {
        $cryptoGen = new CryptoGen();
        return $cryptoGen->decryptStandard($GLOBALS['propio_api_key']);
    }

    public static function isEnable()
    {
        return self::getClientId() != "" && self::getAccessCode() != "" && self::getApiKey() != "" ? true : false;
    }

    public static function getActiveRequest($meeting_id)
    {
        if(empty($meeting_id)) return array();

        return sqlQuery("SELECT * FROM `vh_propio_event` WHERE meeting_id = ? and status in ('Requested') ", array($meeting_id));
    }

    private static function curl($data = null, $api_url, $method = "POST") {
        
        // Force data object to array
        $data = $data ? (array) $data : $data;
        $access_token = self::getApiKey();

        if(!empty($data)) $data['OrganizationId'] = self::getClientId();
        
        // Define header values
        $headers = [
            'Content-Type: application/json',
            'x-api-key: ' . $access_token
        ];
        
        // Set up client connection
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $api_url);
        curl_setopt($ch, CURLOPT_HEADER, 0);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

        if($method == "POST") {
            curl_setopt($ch, CURLOPT_POST, 1);
        }
        
        // Specify the raw post data
        if (isset($data) && $data != null) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        }
        
        // Send data
        $result = curl_exec($ch);
        $errCode = curl_errno($ch);
        $errText = curl_error($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

        curl_close($ch);

        if($httpCode !== 200) {
            $errText1 = "Status: " . $httpCode;
            if(!empty($result)) $errText1 .= " - " . $result;

            throw new \Exception($errText1);
            return false;
        }

        if($errCode){
            throw new \Exception($errText);
            return false;
        }

        return (!empty($result) && self::isJson($result)) ? json_decode($result, true) : $result;
    }

    public static function requestInterpreter($meeting_id = "", $data = array()) {
        try {
            if(empty($data) || empty($meeting_id)) {
                throw new \Exception("Getting Error");
            }

            $propioEventRes = sqlQuery("SELECT * FROM `vh_propio_event` WHERE meeting_id = ? and status in ('Requested') ", array($meeting_id));

            if(!empty($propioEventRes)) {
                throw new \Exception("Request alreday exists for meeting ".$meeting_id);
            }

            $apiUrl = "https://api.propio-ls.com/request/interpreter";
            $res = self::curl($data, $apiUrl, "POST");

            if(!empty($res) && isset($res['id'])) {
                
                $sql = "INSERT INTO `vh_propio_event` ( meeting_id, call_id, statusCallBack, status, request_data) VALUES (?, ?, ?, ?, ?) ";
                    
                sqlInsert($sql, array(
                    $meeting_id,
                    $res['id'],
                    $res['statusCallback'],
                    "Requested",
                    !empty($data) ? json_encode($data) : ""
                ));
                
                return $res;
            } else {
                throw new \Exception("Getting Error");
            }
        } catch(\Exception $e) {
            return $e->getMessage();
        }
    } 

    public static function completeInterpreter($propio_id = "") {
        try {
            if(empty($propio_id)) {
                throw new \Exception("Getting Error");
            }

            $propioEventRes = sqlQuery("SELECT * FROM `vh_propio_event` WHERE id = ? ", array($propio_id));

            if(empty($propioEventRes) || empty($propioEventRes['call_id'])) {
                throw new \Exception("Request not exists for meeting.");
            }

            $apiUrl = "https://api.propio-ls.com/request/interpreter/".$propioEventRes['call_id']."/status/Completed";
            $res = self::curl(array(), $apiUrl, "POST");

            $requestStatus = self::getStatusInterpreter($propioEventRes['call_id']);

            if(!empty($requestStatus)) {
                sqlStatementNoLog("UPDATE `vh_propio_event` SET `status` = ?, `updated_at` = NOW() WHERE id = ?", array($requestStatus, $propioEventRes['id']));
                return $requestStatus;
            } else {
                throw new \Exception("Getting Error");
            }
        } catch(\Exception $e) {
            return $e->getMessage();
        }
    }

    public static function cancelInterpreter($propio_id = "") {
        try {
            if(empty($propio_id)) {
                throw new \Exception("Getting Error");
            }

            $propioEventRes = sqlQuery("SELECT * FROM `vh_propio_event` WHERE id = ? ", array($propio_id));

            if(empty($propioEventRes) || empty($propioEventRes['call_id'])) {
                throw new \Exception("Request not exists for meeting");
            }

            $apiUrl = "https://api.propio-ls.com/request/interpreter/".$propioEventRes['call_id']."/status/Cancelled";
            $res = self::curl(array(), $apiUrl, "POST");

            $requestStatus = self::getStatusInterpreter($propioEventRes['call_id']);

            if(!empty($requestStatus)) {
                sqlStatementNoLog("UPDATE `vh_propio_event` SET `status` = ?, `updated_at` = NOW() WHERE id = ?", array($requestStatus, $propioEventRes['id']));
                return $requestStatus;
            } else {
                throw new \Exception("Getting Error");
            }
        } catch(\Exception $e) {
            return $e->getMessage();
        }
    }

    public static function getStatusInterpreter($propio_id = "") {
        try {
            if(empty($propio_id)) {
                throw new \Exception("Getting Error");
            }

            $apiUrl = "https://api.propio-ls.com/request/interpreter/".$propio_id."/status/";
            $res = self::curl(array(), $apiUrl, "GET");

            return $res;
        } catch(\Exception $e) {
            return $e->getMessage();
        }
    }

    public static function isJson($string) {
       json_decode($string);
       return json_last_error() === JSON_ERROR_NONE;
    }
}
