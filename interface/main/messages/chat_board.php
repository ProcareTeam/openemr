<?php

if(isset($_REQUEST['ig'])) {
    $_SESSION['site'] = 'default';
    $backpic = "";
    $ignoreAuth=1;
}

require_once("../../globals.php");
require_once("$srcdir/OemrAD/classes/mdChat.class.php");
require_once("$srcdir/user.inc");
require_once($GLOBALS['srcdir']."/wmt-v3/wmt.globals.php");

use OpenEMR\Core\Header;
use OpenEMR\Common\Auth\AuthUtils;
use OpenEMR\OemrAd\Chat;
use OpenEMR\Common\Crypto\CryptoGen;

$form_convid = isset($_REQUEST['convid']) ? trim(strip_tags($_REQUEST['convid'])) : "";
$form_convid1 = isset($_REQUEST['convid1']) ? trim(strip_tags($_REQUEST['convid1'])) : "";
$ajax_action = isset($_REQUEST['ajax_action']) ? trim(strip_tags($_REQUEST['ajax_action'])) : "";

function fetchNewMessage($convid = 0) {
    $currentDate = date("Y-m-d");
    $filterStartDate = new DateTime('now');
    $filterStartDate->sub(new DateInterval('P' . $GLOBALS['webhook_datefilter'] . 'D'));
    $newDateString = $filterStartDate->format('Y-m-d'); // Change the format as needed

    $msgItems = Chat::getRecentChat($newDateString." 00:00:00", $currentDate." 23:59:59");

    $mList = array();
    foreach ($msgItems as $mKey => $msgData) {

        $isOnline = Chat::isUserOnline($msgData['conv_uid']);

        $msgData['isOnline'] = $isOnline;

        if(isset($msgData) && $msgData['direction'] == "in" && $msgData['status'] == "0" && $msgData['isOnline'] === true) {
            $mList['n'][strtotime($msgData['creation_time'])] = $msgData;
        } else if(isset($msgData) && $msgData['isOnline'] === true) {
            $mList['o'][strtotime($msgData['creation_time'])] = $msgData;
        } else {
            $mList['r'][strtotime($msgData['creation_time'])] = $msgData;
        }
    }

    ksort($mList, SORT_STRING);

    ob_start();

    echo "<ul>";
    foreach ($mList as $mlk => $msgData1) {
        arsort($msgData1);

        foreach ($msgData1 as $msgData) {

        try {
            $pat_data = wmt\Patient::getPidPatient($msgData['pid']);
        } catch (\Exception $e) {
            $pat_data = new stdClass();
        }

        $pat_name = isset($pat_data->format_name) ? $pat_data->format_name : "";
        if(empty($pat_name)) {
            $pat_name = $msgData['name'];
        }

        if(!empty($pat_name)) {
            $pwords = explode(" ", $pat_name);
            $pacronym = "";

            foreach ($pwords as $pw) {
              $pacronym .= mb_substr($pw, 0, 1);
            }
        } else {
            $pacronym = "UK";
        }

        $givenDateTime = $msgData['creation_time']; // Format: YYYY-MM-DD HH:MM:SS
        $timestamp = strtotime($givenDateTime);
        $currentTimestamp = time();

        // Calculate the difference in seconds
        $difference = $currentTimestamp - $timestamp;

        if ($difference < 604800) {
            if (date("Y-m-d", $timestamp) == date("Y-m-d", $currentTimestamp)) {
                $originalDatetime = date("Y-m-d H:i:s", $timestamp);
                $datetime1 = new DateTime($originalDatetime);
                $datetime = $datetime1->format('H:i');
            } else {
                // If the date is within the previous week but not today, display the day of the week
                $datetime = date("l", $timestamp);
            }
        } else {
            // If the date is outside of the previous week, display the full date and time
            $datetime = date("d/m/Y", $timestamp);
        }

        $itemClass = '';
        
        if(isset($msgData) && $msgData['direction'] == "in" && $msgData['status'] == "0") {
            $eleClass = 'sb-fade-in';
        } else {
            $eleClass = 'sb-fade-out';
            $itemClass = 'sb-conversation-recent';
        }

        $isActiveClass = "";

        if(!empty($convid) && $convid == $msgData['convid']) {
            $isActiveClass = "sb-active";
        }

        ?>
        <li class="sb-conversation-item <?php echo $isActiveClass; ?> <?php echo $itemClass; ?>" data-convid="<?php echo $msgData['convid'] ?>" data-conversation-id="<?php echo $msgData['conversation_id'] ?>" data-conversation-status="<?php echo $msgData['status_code'] ?>" onclick="openConversation(this, '<?php echo $msgData['convid'] ?>')">
            <span class="sb-label-new <?php echo $eleClass; ?>">New</span>
            <div class="sb-profile">
                <img loading="lazy" src="../../../library/OemrAD/assets/img/user.svg">
                <!-- <div class="sb-profile-container" style="background-color: <?php //echo Chat::getRandomColor() ?>;"><span class="sb-profile-text" style="color: <?php //echo Chat::getRandomColor() ?>;"><?php //echo $pacronym; ?></span></div> -->
                <span class="sb-name <?php echo $msgData['isOnline'] === true ? "sb-online" : ""; ?>"><?php echo $pat_name; ?></span>
                <span class="sb-time"><span data-today=""><?php echo $datetime; ?></span></span>
            </div>
            <p><?php echo $msgData['message'] ?></p>
        </li>
        <?php

        }

        $mList[$mlk] = $msgData1;
    }

    echo "</ul>";
    $resData = ob_get_clean();

    return json_encode(array('items' => $mList, 'content' => $resData));
}

if(!empty($ajax_action)) {
    if($ajax_action == "fetch_chat") {
        $chatConversationDetails = Chat::getChatConversations($form_convid, "*");
        
        if(!empty($chatConversationDetails)) {
            $chat_conversation_id = isset($chatConversationDetails['conversation_id']) && !empty($chatConversationDetails['conversation_id']) ? $chatConversationDetails['conversation_id'] : "";

            if(!empty($chat_conversation_id)) {
                $messages = Chat::getChatForm(array(
                    "conversation_id" => array("value" => $chat_conversation_id, "condition" => "")
                ), "*", "creation_time asc");

                if(count(array_filter(array_keys($messages), 'is_string'))) {
                    $messages = array($messages);
                }

                $convByDate = array();
                foreach ($messages as $event) {
                    $dateTime = new DateTime($event['creation_time']);
                    $date = $dateTime->format('Y-m-d');
                    if (!isset($convByDate[$date])) {
                        $convByDate[$date] = array();
                    }
                    $convByDate[$date][] = $event;
                }

                foreach ($convByDate as $date => $eventsOnDate) {
                    $timestamp = strtotime($date);
                    // Get the current timestamp
                    $currentTimestamp = time();
                    // Calculate the difference in seconds
                    $difference = $currentTimestamp - $timestamp;
                    if ($difference < 604800) {
                        // If the given date is within the previous week (604800 seconds in a week) and not in the future
                        if (date("Y-m-d", $timestamp) == date("Y-m-d", $currentTimestamp)) {
                            // If the date is today, display "Today" followed by the time
                            $datetime = "Today";
                        } else {
                            // If the date is within the previous week but not today, display the day of the week
                            $datetime = date("l", $timestamp);
                        }
                    } else {
                        // If the date is outside of the previous week, display the full date and time
                        $datetime = date("d/m/Y", $timestamp);
                    }

                    ?>
                    <div class="sb-label-date">
                        <span><?php echo $datetime; ?></span>
                    </div>
                    <?php
                    foreach ($eventsOnDate as $msg) {
                         // Message to patient
                         if($msg['direction'] == 'out') { // inbound chat?>
                            <div class="sb-right" >
                                <div class="sb-cnt" >
                                    <div class="sb-message"><?php echo text($msg['message']); ?></div>
                                    <div class="sb-time"><span>
                                    <?php
                                        $datetime1 = new DateTime($msg['creation_time']);
                                        echo $datetime1->format('H:i'); 
                                    ?></span></i></div>
                                </div>
                            </div>
                            <?php 
                        }  
                        // Message from patient
                        else { // outbound chat?>
                            <div>
                                <div class="sb-cnt" >
                                    <div class="sb-message"><?php echo $msg['message']; ?></div>
                                    <div class="sb-time"><span><?php 
                                        $datetime1 = new DateTime($msg['creation_time']);
                                        echo $datetime1->format('H:i'); 
                                    ?></span></i></div>
                                </div>
                            </div>
                        <?php 
                        }
                    }
                }
            }
        }
        
    } else if($ajax_action == "send_message") {
        $form_conversation_id = isset($_REQUEST['conversation_id']) ? trim(strip_tags($_REQUEST['conversation_id'])) : "";

        $data = Chat::support_board_api([
            'function' => 'send-message', 
            'user_id' => $GLOBALS['webhook_userid'], 
            'conversation_id' => $form_conversation_id, 
            'message' => $_REQUEST['message']
            ]);

        if(isset($data['response'])) {
            $msg_id = isset($data['response']['id']) ? $data['response']['id'] : "";
            
            if(!empty($msg_id)) {
                $msgLogId = sqlInsert("INSERT INTO `vh_chat_form` SET msg_id=?, conversation_id=?, uid=?, direction=?, status_code=?, status=?, message=?", array($msg_id, $form_conversation_id, $_SESSION['authUserID'], 'out', 0, 1, $_REQUEST['message']));

                sqlStatementNoLog("UPDATE `vh_chat_form` SET status = 1 WHERE conversation_id = ? AND status = ? ", array($form_conversation_id, 0));

                Chat::saveMessageLog(array(
                    'direction' => 'out',
                    'msg_convid' => $form_conversation_id, 
                    'message' => $_REQUEST['message']
                ), $form_conversation_id);

                return json_encode(array('msg_id' => $msgLogId));
            }
        }
    } else if($ajax_action == "fetch_new_chat") {
        echo fetchNewMessage($form_convid);
    } else if($ajax_action == "fetch_conversation_chat") {
        echo getChatDetails($form_convid);
    } else if($ajax_action == "update_users_last_activity") {
        echo Chat::updateUsersLastActivity();
    } else if($ajax_action == "get_online_users") {
        echo json_encode(Chat::isUserOnline());
    }

    exit();
}

function getChatDetails($form_convid) {
    $chatConversationDetails = array();
    $isOnline = false;

    if(!empty($form_convid)) {
        $chatConversationDetails = Chat::getChatConversations($form_convid, "*");
    }

    $form_conversation_id = isset($chatConversationDetails['conversation_id']) ? $chatConversationDetails['conversation_id'] : "";

    if($form_conversation_id) {
        sqlStatementNoLog("UPDATE `vh_chat_form` SET status = 1 WHERE conversation_id = ? AND status = ? ", array($form_conversation_id, 0));
    }

    if(!empty($chatConversationDetails)) {
        if(isset($chatConversationDetails['pid']) && !empty($chatConversationDetails['pid'])) {
            try{
                $pat_data = wmt\Patient::getPidPatient($chatConversationDetails['pid']);
            } catch (\Exception $e) {
                $pat_data = new stdClass();
            }
            
            $pat_name = isset($pat_data->format_name) && !empty($pat_data->format_name) ? "<a href=\"javascript:void(0);\" onclick=\"handleSetPatientData('".$pat_data->pid."')\">" . $pat_data->format_name . "</a>" : "";
        }
    }

    if(empty($pat_name) && !empty($chatConversationDetails)) {
        $pat_name = $chatConversationDetails['first_name'] . " " . $chatConversationDetails['last_name'];
    }

    if(!empty($chatConversationDetails)) {
        $isOnline = Chat::isUserOnline($chatConversationDetails['uid']);
    }

    ob_start();
    ?>
    <div class="sb-conversation" id="sb-conversation">
        <div class="sb-top">
            <p style="margin-bottom: 0px"><?php echo !empty($pat_name) ? $pat_name : xl('Unkown'); ?><p>

            <?php if($isOnline === true) { ?>
            <div class="sb-labels">
                <span class="sb-status-online"><?php echo xl('Online'); ?></span>
            </div>
            <?php } ?>
        </div>
        <div id="chat_list" class="sb-list"> 
            <div class="sb-loading-container">
                <div class="spinner-border text-primary" role="status">
                  <span class="sr-only">Loading...</span>
                </div>
            </div>             
        </div>
        <div class="sb-outer-container">
        <div id="sending-label" class="sending-label" style="display:none">
            <span><i><?php echo xl('Sending...'); ?></i></span>
        </div>
        <?php if($isOnline === true) { ?>
        <div class="sb-editor">
            <div class="sb-textarea">
                <textarea placeholder="Write a message..." style="height: 25px;" id="message"></textarea>
                <button type="button" id="send-btn" class="send-btn" onclick="ajaxTransmit(<?php echo isset($chatConversationDetails['conversation_id']) ? $chatConversationDetails['conversation_id'] : ""; ?>)" ><i class="fa fa-paper-plane" aria-hidden="true"></i></button>
            </div>
        </div>
        <?php } ?>
        <input type="hidden" id="form_conversation_id" value="<?php echo $chatConversationDetails['conversation_id']; ?>">
        </div>
    </div>
    <div class="sb-user-details">
        <div class="sb-top"><?php echo xl('Details'); ?></div>
        <div class="sb-scroll-area">
            <div class="sb-profile">
                <span class="sb-name"><?php echo !empty($name) ? text($name) : ""; ?></span>
            </div>
            <div class="sb-profile-list sb-profile-list-conversation">
                <ul>
                    <li>
                        <span><?php echo xl('CONVERSATION ID'); ?>:</span>
                        <label id="conv_id"><?php echo isset($chatConversationDetails['conversation_id']) ? text($chatConversationDetails['conversation_id']) : "-"; ?></label>
                    </li>
                    <li>
                        <span><?php echo xl('USER ID'); ?>:</span>
                        <label id="user_id"><?php echo isset($chatConversationDetails['uid']) ? text($chatConversationDetails['uid']) : "-"; ?></label>
                    </li>
                    <li>
                        <span><?php echo xl('NAME'); ?>:</span>
                        <label id="first_name"><?php echo isset($chatConversationDetails['first_name']) ? text($chatConversationDetails['first_name']) : "-"; ?></label>
                    </li>
                    <!-- <li>
                        <span><?php //echo xl('LAST NAME'); ?>:</span>
                        <label id="last_name"><?php //echo isset($chatConversationDetails['last_name']) ? text($chatConversationDetails['last_name']) : "-"; ?></label>
                    </li> -->
                    <li>
                        <span><?php echo xl('EMAIL'); ?>:</span>
                        <label><?php echo isset($chatConversationDetails['email']) ? text($chatConversationDetails['email']) : "-"; ?></label>
                    </li>
                    <li>
                        <span><?php echo xl('PHONE'); ?>:</span>
                        <label><?php echo isset($chatConversationDetails['phone_number']) ? text($chatConversationDetails['phone_number']) : "-"; ?></label>
                    </li>
                    <li>
                        <span><?php echo xl('IP'); ?>:</span>
                        <label><?php echo isset($chatConversationDetails['ip']) ? text($chatConversationDetails['ip']) : "-"; ?></label>
                    </li>
                    <li>
                        <span><?php echo xl('LOCATION'); ?>:</span>
                        <label><?php echo isset($chatConversationDetails['location']) ? text($chatConversationDetails['location']) : "-"; ?></label>
                    </li>
                    <li>
                        <span><?php echo xl('CREATION TIME'); ?>:</span>
                        <label><?php echo isset($chatConversationDetails['created_date']) ? text($chatConversationDetails['created_date']) : "-"; ?></label>
                    </li>
                </ul>
            </div>
        </div>
    </div>

    <script type="text/javascript">
        $(document).ready(async function() {
            const messageInput = document.getElementById('message');

            if(messageInput != null) {
                messageInput.addEventListener('keydown', function (event) {
                    const form_conversation_id = document.getElementById('form_conversation_id').value;

                    if (form_conversation_id != "" && event.key === 'Enter' && !event.ctrlKey) {
                        ajaxTransmit(form_conversation_id);
                    }
                });
            }


            <?php if(!empty($form_convid)) { ?>
                await fetchChat('<?php echo $form_convid; ?>');
                const element = document.getElementById('chat_list');
                element.scrollTop = element.scrollHeight;
            <?php } ?>

            await fetchNewChat();
        });
    </script>
    <?php

    $resData = ob_get_clean();

    return $resData;
}

if(empty($form_convid) && !empty($form_convid1)) {
        $chatConversationDetails = Chat::getChatConversations(array(
            'conversation_id' => array('value' => $form_convid1, 'condition' => '')
        ), "*");
        $form_convid = $chatConversationDetails['id'];
}

if(empty($form_convid) && empty($form_convid1)) {
    $recentData = fetchNewMessage($form_convid);
    $recentData = json_decode($recentData, true);

    if(isset($recentData['items'])) {
        if(isset($recentData['items']['n'])) {
            $fitem = reset($recentData['items']['n']);
            $form_convid = $fitem['convid'];
        } else if(isset($recentData['items']['r'])) {
            $fitem = reset($recentData['items']['r']);
            $form_convid = $fitem['convid'];
        }
    }
}

?>

<html>
    <head>
        <title><?php echo xl('Chat Board'); ?></title>

        <?php Header::setupHeader(['common', 'jquery', 'oemr_ad']); ?>

        <script type="text/javascript">
            var form_convid = '<?php echo $form_convid; ?>';
            var websocket_host = '<?php echo $GLOBALS['websocket_host']; ?>';
            var websocket_port = '<?php echo $GLOBALS['websocket_port']; ?>';
            var websocket_address_type = '<?php echo $GLOBALS['websocket_address_type']; ?>';
            var websocket_siteurl = '<?php echo Chat::getSiteBaseURL(true); ?>'
        </script>

        <style>
            .sb-admin {
                height: 100%;
                width: 100%;
                position: fixed;
                font-size: 14px;
                line-height: 17px;
                background: #f5f7fa;
                top: 0;
                z-index: 9;
            }
            .sb-admin > main {
                padding: 0;
                background: #fff;
                height: 100%;
                overflow: hidden;
                width: 100%;
                margin: 0;
            }
            .sb-board {
                display: flex;
                justify-content: space-between;
                height: 100%;
            }
            .sb-board > .sb-admin-list {
                max-width: 350px;
                min-width: 350px;
                border-right: 1px solid #d4d4d4;
                position: relative;
            }
            .sb-board .sb-main-chat-container {
                width: 100%;
                min-width: 0;
                position: relative;
                display: flex;
/*                flex-direction: column;*/
            }
            .sb-board .sb-conversation {
                width: 100%;
                min-width: 0;
                position: relative;
                display: flex;
                flex-direction: column;
            }
            .sb-board .sb-user-details {
                min-width: 350px;
                width: 350px;
                border-left: 1px solid #d4d4d4;
                display: flex;
                flex-direction: column;
                height: 100%;
                overflow: hidden;
                position: relative;
            }
            .sb-board div > .sb-top {
                border-bottom: 1px solid #d4d4d4;
                padding: 15px 20px;
                height: 70px;
                min-height: 70px;
                box-sizing: border-box;
                line-height: 42px;
                font-size: 18px;
                font-weight: 600;
            }
            .sb-board .sb-conversation .sb-list {
                height: 100%;
                padding: 10px 0 5px 0;
                overflow-y: scroll;
            }
            .sb-list  div.sb-label-date, .sb-label-date-top {
                text-align: center;
                max-width: 100% !important;
                width: auto;
                float: none !important;
                background: none;
                margin: 0 !important;
            }
            .sb-list div.sb-right {
                float: right;
                margin: 2px 20px 25px 10px;
                background-color: #E6F2FC;
            }
            .sb-board .sb-conversation .sb-list > div {
                max-width: calc(100% - 275px);
            }
            .sb-list .sb-message, .sb-list .sb-message a {
                color: #566069;
                font-size: 13px;
                line-height: 21px;
                letter-spacing: 0.3px;
                outline: none;
            }
            .sb-list .sb-message {
                overflow: hidden;
                text-overflow: ellipsis;
            }
            .sb-list > div.sb-right .sb-message, .sb-list > div.sb-right .sb-message a, .sb-list > div.sb-right .sb-rich-message .sb-text {
                color: #004c7d;
            }
            .sb-list > div.sb-right .sb-time {
                right: 0;
                left: auto;
            }
            .sb-list .sb-time {
                opacity: .9;
                color: #566069;
                font-size: 11px;
                letter-spacing: 0.5px;
                line-height: 16px;
                bottom: -20px;
                left: 0;
                white-space: nowrap;
                position: absolute;
                display: flex;
            }
            .sb-list > div {
                float: left;
                clear: both;
                position: relative;
                margin: 2px 10px 25px 20px;
                box-shadow: none;
                background-color: whitesmoke;
                border-radius: 6px;
                padding: 8px 12px;
                max-width: calc(90% - 110px);
            }
            .sb-editor {
                background: #fbfbfb;
                padding-bottom: 0;
                padding: 15px;
                position: relative;
                margin: 0;
            }
            .sb-board .sb-conversation .sb-editor {
                flex-shrink: 0;
                margin: 1px 15px 15px 15px;
                border: 1px solid rgba(0, 0, 0, 0.2);
                border-radius: 4px;
                box-shadow: 0 2px 8px 0 rgba(0, 0, 0, 0.1);
                transition: box-shadow linear 40ms, border-color linear 0.2s;
                background-color: #fff;
            }
            .sb-board .sb-conversation .sb-editor .sb-textarea {
                border: none;
                padding: 0 !important;
                display: flex;
            }
            .sb-editor textarea {
                margin: 0 !important;
                box-shadow: none !important;
                border-radius: 0 !important;
                font-size: 13px;
                letter-spacing: 0.3px;
                width: 100%;
                height: 25px;
                line-height: 25px;
                min-height: 0 !important;
                padding: 0px !important;
                outline: none !important;
                text-align: left;
                font-weight: 400;
                resize: none !important;
                border: none !important;
                color: #566069 !important;
                background: transparent !important;
                transition: all 0.4s;
                overflow: hidden;
                display: block;
            }
            .sb-admin, .sb-admin input, .sb-admin textarea, .sb-admin select, .sb-title, .daterangepicker, .ct__content {
                font-family: "Support Board Font", "Helvetica Neue", "Apple Color Emoji", Helvetica, Arial, sans-serif;
                color: #24272a;
            }
            .sb-board .sb-user-details .sb-scroll-area {
                height: calc(100% - 70px);
            }
            .sb-board .sb-user-details .sb-profile {
                margin: 20px 15px 0 15px;
                cursor: pointer;
            }
            .sb-profile {
                position: relative;
                color: #24272a;
                line-height: 30px;
                padding-left: 10px;
                text-decoration: none;
                display: flex;
                align-items: center;
            }
            .sb-profile > span {
                font-size: 16px;
                font-weight: 600;
                letter-spacing: 0.3px;
            }
            .sb-board .sb-user-details .sb-profile-list {
                padding: 15px;
            }
            .sb-profile-list > ul > li:first-child span {
                text-transform: uppercase;
            }
            .sb-profile-list > ul > li > span {
                position: relative;
                top: 1px;
            }
            .sb-profile-list > ul > li > span, .sb-panel-details .sb-title {
                font-weight: 500;
                padding-right: 10px;
                font-size: 11px;
                letter-spacing: .3px;
                color: #88969e;
                text-transform: uppercase;
                transition: all 0.4s;
            }
            .sb-profile-list > ul > li label {
                font-weight: 400;
                margin: 0;
            }
            .sb-admin label {
                cursor: text;
            }
            .sb-profile-list > ul > li {
                padding-left: 30px;
            }
            .sb-profile-list > ul > li, .sb-panel-details .sb-list-items > div, .sb-panel-details .sb-list-items > a {
                position: relative;
                font-size: 13px;
                line-height: 27px;
                letter-spacing: 0.3px;
                white-space: nowrap;
                text-overflow: ellipsis;
                overflow: hidden;
            }
            .sb-admin li {
                margin: 0;
            }
            .sb-board .sb-user-details .sb-profile-list ul {
                margin-top: 0;
            }
            .sb-admin ul {
                padding: 0;
                margin: 0;
                list-style: none;
            }
            .sb-board > .sb-admin-list .sb-scroll-area li p {
                font-size: 13px;
                line-height: 20px;
                opacity: 0.8;
                /*height: 40px;*/
                height: auto;
                overflow: hidden;
                letter-spacing: 0.3px;
                margin: 15px 0 0 0;
            }
            .sb-board > .sb-admin-list .sb-scroll-area li[data-conversation-status="2"] .sb-name {
                font-size: 16px;
                font-weight: 600;
            }
            .sb-board > .sb-admin-list .sb-scroll-area li .sb-profile .sb-name {
                height: 30px;
                padding-right: 15px;
                margin-right: 70px;
                font-size: 15px;
                font-weight: 600;
                white-space: nowrap;
                letter-spacing: 0.3px;
                overflow: hidden;
                text-overflow: ellipsis;
            }
            .sb-board > .sb-admin-list .sb-scroll-area li[data-conversation-status="2"] .sb-time {
                font-weight: 500;
            }
            .sb-board > .sb-admin-list .sb-scroll-area li .sb-profile .sb-time {
                position: absolute;
                right: 0;
                font-size: 13px;
                font-weight: 400;
                opacity: 0.8;
            }

            .sb-board > .sb-admin-list .sb-scroll-area {
                height: calc(100% - 70px);
            }
            .sb-admin ul {
                padding: 0;
                margin: 0;
                list-style: none;
            }
            .sb-board > .sb-admin-list .sb-scroll-area li {
                position: relative;
                border-left: 2px solid rgba(255, 255, 255, 0);
                border-bottom: 1px solid #d4d4d4;
                background: rgba(245, 245, 245, 0.75);
                padding: 18px 20px 15px 20px;
                max-height: 100%;
                overflow: hidden;
                opacity: 0.8;
                cursor: pointer;
                transition: all 0.4s;
            }
            .sb-scroll-area, .sb-conversation .sb-list, .sb-list .sb-message pre, .sb-rich-table .sb-content, .sb-admin .sb-top-bar > div:first-child > ul, .sb-area-settings > .sb-tab > .sb-nav, .sb-area-reports > .sb-tab > .sb-nav, .sb-dialog-box pre, .sb-horizontal-scroll {
                overflow: hidden;
                overflow-y: scroll;
                scrollbar-color: #ced6db #ced6db;
                scrollbar-width: thin;
            }
            .sb-board .sb-conversation .sb-list > div {
                max-width: calc(100% - 275px);
            }
            .sb-list > div.sb-label-date, .sb-label-date-top {
                text-align: center;
                max-width: 100% !important;
                width: auto;
                float: none !important;
                background: none;
                margin: 0 !important;
            }
            .sb-list > div.sb-label-date span, .sb-label-date-top span {
                display: inline-block;
                background: #f5f7fa;
                padding: 0 10px;
                border-radius: 4px;
                font-size: 12px;
                line-height: 26px;
                letter-spacing: .3px;
                margin: 15px auto 15px auto;
                font-weight: 400;
                color: #566069;
            }
            .sb-board > .sb-admin-list .sb-scroll-area li > span {
                position: absolute;
                right: 15px;
                height: 25px;
                min-width: 45px;
                line-height: 16px;
                font-size: 13px;
                padding: 5px 10px;
                border-radius: 4px;
                white-space: nowrap;
                text-align: center;
                background: #028be5;
                color: #fff;
                z-index: 9;
                letter-spacing: 0.3px;
                animation: sb-fade-animation .3s;
            }
            .sb-profile {
                position: relative;
                color: #24272a;
                line-height: 30px;
                padding-left: 45px;
                text-decoration: none;
                display: flex;
                align-items: center;
            }
            .sb-profile img {
                position: absolute;
                width: 30px;
                height: 30px;
                border-radius: 50%;
                left: 0;
            }
            .sb-fade-out {
                opacity: 0;
                animation: sb-fade-out .5s;
            }
            .sb-board > .sb-admin-list .sb-scroll-area li[data-conversation-status="2"] .sb-name {
                font-size: 16px;
                font-weight: 600;
            }
            .sb-conversation-item.sb-conversation-recent {
                background-color: #fff !important;
            }
            .send-btn {
                border: 0px;
                width: 40px;
                height: 40px;
                border-radius: 30px;
            }
            .sending-label {
                float: right;
                margin: 1px 15px 0px 15px;
                text-align: right;
            }
            .sb-board .sb-labels {
                padding-left: 10px;
                display: flex;
                align-items: center;
            }
            .sb-board .sb-labels .sb-status-online {
                background: rgba(19, 202, 126, 0.21);
                color: #009341;
            }
            .sb-board .sb-conversation > .sb-top {
                width: auto;
                display: flex;
                align-items: center;
                flex-grow: 0;
                justify-content: flex-start;
            }
            .sb-board .sb-labels span {
                font-size: 14px;
                line-height: 30px;
                padding: 1px 10px 0 10px;
                border-radius: 3px;
                margin: 0 5px;
                display: block;
                font-weight: 600;
                white-space: nowrap;
                cursor: default;
                position: relative;
            }
            .onlineuser .sb-name {
                color: green !important;
            }
            .sb-board > .sb-admin-list .sb-scroll-area li:hover, .sb-board > .sb-admin-list .sb-scroll-area li.sb-active {
                background-color: #f5f7fa !important;
            }
            .sb-outer-container {
                display: grid;
                margin-bottom: 5px;
            }
            #chat_board_form {
                margin: 0px;
            }
            .sb-board > .sb-admin-list .sb-scroll-area li {
                border-left-width: 3px;
            }
            .sb-board > .sb-admin-list .sb-scroll-area li.sb-active {
                border-left-color: #028be5;
                border-left-width: 3px;
                opacity: 1;
            }
            .sb-loading-container {
                height: 100%;
                width: 100%;
                display: grid;
                justify-items: center;
                align-items: center;
                max-width: 100% !important;
                background-color: transparent !important;
            }
            .sb-online {
                position: relative;
            }
            .sb-online:before {
                content: "";
                width: 8px;
                height: 8px;
                position: absolute;
                border-radius: 50%;
                margin-top: -4px;
                top: 50%;
                right: 0;
                background: #13ca7e;
            }
            .sb-profile-container {
                position: absolute;
                width: 35px;
                height: 35px;
                border-radius: 50%;
                left: 0;
                display: grid;
                align-items: center;
                justify-items: center;
            }
            .sb-profile-text {
                font-weight: 600;
                text-align: center;
                font-size: 12px;
                line-height: 12px;
                -webkit-filter: invert(100%);
                filter: invert(100%);
            }
        </style>
    </head>
    <body>
        <div class="sb-main sb-admin">
            <main>
                <form id="chat_board_form" action="chat_board.php" method="post">
                    <input type="hidden" id="form_convid" name="convid" value="<?php echo $form_convid; ?>">
                </form>
                <div class = "conversation-area">
                    <div class="sb-board">
                        <div class="sb-admin-list">
                            <div class="sb-top"><?php echo xl('Inbox'); ?></div>
                            <div id="sb-scroll-area" class="sb-scroll-area">
                                <div class="sb-loading-container">
                                    <div class="spinner-border text-primary" role="status">
                                      <span class="sr-only">Loading...</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div id="sb-main-chat-container" class="sb-main-chat-container">
                        <?php echo getChatDetails($form_convid); ?>
                        </div>
                    </div>
                </div>
            </main>
        </div>
    </body>
</html>

<script>
    const parentDocument = parent.document;

    // Access an element in the parent document by its ID
    const elementInParent = parentDocument.getElementById('mainFrames_div');
    elementInParent.style.width = '100vw';
            
    function ajaxTransmit(conversation_id) {        
        var data = [];
        data.push({name: "ajax_action", value: "send_message"});
        data.push({name: "message", value: $('#message').val()});
        data.push({name: "conversation_id", value: conversation_id});

        $('#sending-label').show();
        $('#message').val('');

        $.ajax({
            url: "chat_board.php",
            method: "POST",
            data: $.param(data),
            success: async function(result) {
                await fetchChat(form_convid);

                const element = document.getElementById('chat_list');
                element.scrollTop = element.scrollHeight;

                fetchNewChat();
                $('#sending-label').hide();
            },                      
        });
    }

    function openConversation(ele, convid) {
        $('.sb-conversation-item').removeClass('sb-active');
        $(ele).addClass('sb-active');

        $("#chat_list").html('<div class="sb-loading-container"><div class="spinner-border text-primary" role="status"><span class="sr-only">Loading...</span></div></div>');

        window.form_convid = convid;

        fetchNewChat();
        fetchConversationChat(convid);

        if(websocket && websocket != null) {
            websocket.send(JSON.stringify({ "action" : "status-update", "conversation_id" : form_convid }));
        }
    }

    var newChatAjaxRequest = null;
    var conversationChatAjaxRequest = null;
    var chatAjaxRequest = null;

    async function fetchNewChat() {
        var data = [];
        data.push({ name: "ajax_action", value: "fetch_new_chat" });
        data.push({ name: "convid", value: form_convid });

        if(newChatAjaxRequest != null) {
            newChatAjaxRequest.abort();
        }

        newChatAjaxRequest = $.ajax({
            url: "chat_board.php",
            method: "POST",
            data: $.param(data),
            success: function(result) {
                let dataJSON = JSON.parse(result);

                $('#sb-scroll-area').html(dataJSON['content']);
                return result;
            },                      
        });
    }

    async function fetchConversationChat(convid) {
        var data = [];
        data.push({name: "ajax_action", value: "fetch_conversation_chat"});
        data.push({name: "convid", value: convid});

        if(conversationChatAjaxRequest != null) {
            conversationChatAjaxRequest.abort();
        }

        conversationChatAjaxRequest = $.ajax({
            url: "chat_board.php",
            method: "POST",
            data: $.param(data),
            success: function(result) {
                $('#sb-main-chat-container').html(result);
                return result;
            },                      
        });
    }

    async function fetchChat(convid) {
        var data = [];
        data.push({name: "ajax_action", value: "fetch_chat"});
        data.push({name: "convid", value: convid});

        if(chatAjaxRequest != null) {
            chatAjaxRequest.abort();
        }

        chatAjaxRequest = $.ajax({
            url: "chat_board.php",
            method: "POST",
            data: $.param(data),
            success: function(result) {
                $('#chat_list').html(result);
                return result;
            },                      
        });
    }

    function onlineUserStatusWebSocket() {
        let websocket = new WebSocket(`${websocket_address_type}://${websocket_host}:${websocket_port}/online_users_status/${websocket_siteurl}`); 

        // Message received from server
        websocket.onmessage =  function(ev) {
            const ouData = JSON.parse(ev['data']);

            if(ouData.hasOwnProperty('data')) {
                if(ouData['data'].length > 0) {
                    console.log(ouData['data']);
                    //fetchChat(window.form_convid);
                    //fetchConversationChat(window.form_convid);
                }
            }
        }

        return websocket;
    }

    function chatWebSocket() {
        if(websocket_host != "" && websocket_port !="") {
            var websocket = new WebSocket(`${websocket_address_type}://${websocket_host}:${websocket_port}/chat_notification/${websocket_siteurl}`); 

            // Message received from server
            websocket.onmessage = async function(ev) {
                const msgData = JSON.parse(ev['data']);

                if(msgData.hasOwnProperty('data') && msgData['data'].hasOwnProperty('conversation_id') && msgData['data'].hasOwnProperty('action') && ["new-message", "agent-new-message"].includes(msgData['data']['action'])) {

                    const form_conversation_id = document.getElementById('form_conversation_id').value;

                    if(form_conversation_id != "" && msgData['data']['conversation_id'] == form_conversation_id) {
                        await fetchChat(window.form_convid);

                        const element = document.getElementById('chat_list');
                        element.scrollTop = element.scrollHeight;
                    }

                    fetchNewChat();
                }
            }

            return websocket;
        }

        return null;
    }

    var websocket = chatWebSocket();
    //var onlineUserStatusWebsocket = onlineUserStatusWebSocket();
</script>
