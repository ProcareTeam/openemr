<?php 

$_SERVER['REQUEST_URI'] = $_SERVER['PHP_SELF'];
$_SERVER['SERVER_NAME'] = 'localhost';
$_GET['site'] = 'default';
$ignoreAuth = 1;

require_once(dirname( __FILE__, 2 ) . "/interface/globals.php");
require_once("$srcdir/OemrAD/classes/mdChat.class.php");
require_once("$srcdir/patient.inc");

use OpenEMR\OemrAd\Chat;

// Get the webhook data sent by Support Board
$webhookData = file_get_contents("php://input");

//writeLog($webhookData);

$dataArray = json_decode($webhookData, true);

if(empty($dataArray)) {
    echo "Failed to decode JSON response.";
    exit();
}

$functionName = isset($dataArray['function']) ? $dataArray['function'] : "";
$conversationData = isset($dataArray['data']) ? $dataArray['data'] : array();
$senderUrl = isset($dataArray['sender-url']) ? $dataArray['sender-url'] : "";

if(empty($conversationData)) {
    echo "Wrong data";
    exit();
}

$userType = is_array($conversationData) && count($conversationData) > 0 && isset($conversationData[0]['details']['user_type']) ? $conversationData[0]['details']['user_type'] : "";

if($functionName == 'registration') {
    $conversationUserData = isset($conversationData['user']) ? $conversationData['user'] : array();
    
    $conversation_id = isset($conversationData['conversation_id']) ? $conversationData['conversation_id'] : "";
    $first_name = isset($conversationUserData['first_name']) ? $conversationUserData['first_name'][0] : "";
    $last_name = isset($conversationUserData['first_name']) ? $conversationUserData['last_name'][0] : "";
    $email = isset($conversationUserData['email']) ? $conversationUserData['email'][0] : "";
    $userid = isset($conversationUserData['id']) ? $conversationUserData['id'][0] : 0;
    $phone = isset($conversationData['extra']) && isset($conversationData['extra']['phone']) ? $conversationData['extra']['phone'][0] : "";

    if(empty($conversation_id) || $conversation_id == "false") {
        exit();
    }

    $phone = !empty($phone) ? preg_replace("/[^0-9]/", "", $phone) : "";
    $pid = 0;

    $col = $GLOBALS['wmt::use_email_direct'] ? 'email_direct' : 'email';
    $formattedfromaddr = str_replace( array( '\''), '', $email);

    $pParam = array();
    $pParam[$col] = array("value" => $formattedfromaddr, "condition" => "");
    $pParam["phone_cell"] = array("value" => $phone, "condition" => "OR");

    $pData = getPatientByCondition($pParam, "pid");
    if(isset($pData['pid']) && !empty($pData['pid'])) {
        $pid = $pData['pid'];
    }

    $userData = Chat::getUserDetails($userid);
    $ip = isset($userData['ip']) ? $userData['ip'] : "";
    $location = isset($userData['location']) ? $userData['location'] : "";

    if(!empty($conversation_id) && (!empty($email) || !empty($phone))) {
        $msgLogId1 = sqlInsert("INSERT INTO `vh_chat_conversations` SET first_name=?, last_name=?, phone_number=?, email=?, pid=?, uid=?, conversation_id=?, ip=?, location=?", array($first_name, $last_name, $phone, $email, $pid, $userid, $conversation_id, $ip, $location));
    }


} else if($functionName == 'new-message' && $userType!='bot') {
    $conversationData = isset($conversationData[0]) ? $conversationData[0] : array();
    $conversationDetailsData = isset($conversationData['details']) ? $conversationData['details'] : array();

    $msg_id = isset($conversationDetailsData['id']) ? $conversationDetailsData['id'] : "";
    $conversation_id = isset($conversationDetailsData['conversation_id']) ? $conversationDetailsData['conversation_id'] : "";
    $creation_time = isset($conversationDetailsData['creation_time']) ? $conversationDetailsData['creation_time'] : "";
    $message = isset($conversationDetailsData['message']) ? $conversationDetailsData['message'] : "";
    $statusCode = isset($conversationDetailsData['status_code']) ? $conversationDetailsData['status_code'] : "";

    if(empty($conversation_id)) {
        exit();
    }
                
    if($conversationDetailsData['user_type'] == 'user') {
        $direction='in';
    } else {
        $direction='out';
    }

    if($conversationDetailsData['user_type'] == 'admin') {
        Chat::websocketSendMessage(array("action" => "agent-new-message", "webhook" => $dataArray, 'mesg_id' => 0, 'conversation_id' => $conversation_id));
    }

    $chatConversationDetails = Chat::getChatConversations(array(
        'conversation_id' => array('value' => $conversation_id, 'condition' => '')
    ), "count(id) as count");
    
    if(!empty($chatConversationDetails) && $chatConversationDetails['count'] == "1") {
        if($direction == "in") {
            $isMessageExist = Chat::getChatForm(array(
                "msg_id" => array("value" =>  $msg_id, "condition" => "")
            ), "count(id) as count");

            if(isset($isMessageExist) && isset($isMessageExist['count']) && $isMessageExist['count'] > 0) {
                exit();
            }

            $msgLogId = sqlInsert("INSERT INTO `vh_chat_form` SET msg_id=?, conversation_id=?, uid=?, direction=?, status_code=?, status=?, message=?, sender_url=?, msg_time=?", array($msg_id, $conversation_id, 0, $direction, $statusCode, 0, $message, $senderUrl, $creation_time));

            Chat::saveMessageLog(array(
                'direction' => $direction,
                'msg_convid' => $conversation_id,
                'message' => $message
            ), $conversation_id);

            if($direction == "in") {
                Chat::websocketSendMessage(array("action" => $functionName, "webhook" => $dataArray, 'mesg_id' => $msgLogId, 'conversation_id' => $conversation_id));
            }
        } else {
            sqlStatementNoLog("UPDATE `vh_chat_form` SET status_code=?, status = 1, msg_time=?, sender_url=?  WHERE conversation_id = ? AND msg_id = ? ", array($status_code, $creation_time, $senderUrl, $form_conversation_id, $msg_id));
        }
    }
} else if($functionName == 'message-sent') {
    $msg_id = isset($conversationData['message_id']) ? $conversationData['message_id'] : "";
    $conversation_id = isset($conversationData['conversation_id']) ? $conversationData['conversation_id'] : "";

    //Chat::websocketSendMessage(array("action" => $functionName, "webhook" => $dataArray, 'mesg_id' => 0, 'conversation_id' => $conversation_id));
} else if($functionName == 'agent-rating') {
    $conversation_id = isset($conversationData['conversation_id']) ? $conversationData['conversation_id'] : "";
    $conversation_rating = isset($conversationData['rating']) ? $conversationData['rating'] : "";
    $conversation_status = isset($conversationData['chatStatus']) ? $conversationData['chatStatus'] : "";

    if(!empty($conversation_id) && $conversation_rating != "") {
        sqlStatementNoLog("UPDATE `vh_chat_conversations` SET conversation_rating=?, chat_status = ? WHERE conversation_id = ? ", array($conversation_rating, $conversation_status, $conversation_id));
    }
}  else if($functionName == 'close-chat') {
    $conversation_id = isset($conversationData['conversation_id']) ? $conversationData['conversation_id'] : "";
    $conversation_status = isset($conversationData['chatStatus']) ? $conversationData['chatStatus'] : "";

    if(!empty($conversation_id) && ($conversation_status != "" || $conversation_status === 0)) {
        sqlStatementNoLog("UPDATE `vh_chat_conversations` SET chat_status = ? WHERE conversation_id = ? ", array($conversation_status, $conversation_id));
    }
}

function writeLog($log = "", $fileInfo = true, $appendDate = true) {
    $filePath = $GLOBALS['fileroot'] . '/library/OemrAD/crons/webhook_request.txt';

    $appendStr = "";
    if($appendDate === true) {
        $appendStr .= "[".date("Y-m-d H:i:s")."] ";
    }

    if(!empty($appendStr)) {
        $log = $appendStr . $log;
    }

    if(!empty($log)) {
        file_put_contents($filePath, $log.PHP_EOL, FILE_APPEND);
    }
}

?>