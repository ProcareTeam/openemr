<?php

namespace OpenEMR\OemrAd;

use OpenEMR\Common\Crypto\CryptoGen;

/**
 * Apis Class
 */
class Chat {

	// Get Chat Conversations
	public static function getChatConversations($data = array(), $given = "*", $orderby = "id DESC", $limit = "all", $start = "0") 
	{
		$sqlBindArray = array();
	    $where = array();

	    if(is_array($data)) {
		    foreach ($data as $col => $colValue) {
		        $cond = isset($colValue['condition']) ? $colValue['condition'] : " AND ";
		        $cond_op = isset($colValue['operation']) ? $colValue['operation'] : "=";  

		        if(!empty($col) && !empty($colValue['value'])) {
		            $where[] = " " . $cond . " " . $col . " " . $cond_op . "  ? ";
		            array_push($sqlBindArray, $colValue['value']);
		        } else if(!empty($col) && empty($colValue['value'])) {
		            $where[] = " " . $cond . " " . $col . " ";
		        }
		    }
		} else if(!empty($data)) {
			$where[] = " id = ? ";
		    array_push($sqlBindArray, $data);
		}

	    $whereStr = !empty($where) ? " WHERE " . implode("", $where) . " " : "";

	    $sql="SELECT $given FROM vh_chat_conversations " . $whereStr . " ORDER BY $orderby";
	    if ($limit != "all") {
	        $sql .= " limit " . escape_limit($start) . ", " . escape_limit($limit);
	    }

	    $rez = sqlStatement($sql, $sqlBindArray);
	    
	    for ($iter=0; $row=sqlFetchArray($rez); $iter++) {
	        $returnval[$iter]=$row;
	    }

	    return isset($returnval) && is_array($returnval) && count($returnval) == 1 ? $returnval[0] : $returnval;
	}


	public static function getChatForm($data = array(), $given = "*", $orderby = "id DESC", $limit = "all", $start = "0") 
	{
		$sqlBindArray = array();
	    $where = array();

	    if(is_array($data)) {
		    foreach ($data as $col => $colValue) {
		        $cond = isset($colValue['condition']) ? $colValue['condition'] : " AND ";
		        $cond_op = isset($colValue['operation']) ? $colValue['operation'] : "=";  

		        if(!empty($col) && !empty($colValue['value'])) {
		            $where[] = " " . $cond . " " . $col . " " . $cond_op . "  ? ";
		            array_push($sqlBindArray, $colValue['value']);
		        } else if(!empty($col) && empty($colValue['value'])) {
		            $where[] = " " . $cond . " " . $col . " ";
		        }
		    }
		} else if(!empty($data)) {
			$where[] = " id = ? ";
		    array_push($sqlBindArray, $data);
		}

	    $whereStr = !empty($where) ? " WHERE " . implode("", $where) . " " : "";

	    $sql="SELECT $given FROM vh_chat_form " . $whereStr . " ORDER BY $orderby";
	    if ($limit != "all") {
	        $sql .= " limit " . escape_limit($start) . ", " . escape_limit($limit);
	    }

	    $rez = sqlStatement($sql, $sqlBindArray);
	    
	    for ($iter=0; $row=sqlFetchArray($rez); $iter++) {
	        $returnval[$iter]=$row;
	    }

	    return count($returnval) == 1 ? $returnval[0] : $returnval;
	}

	public static function wsSendMessage($data) {
		$host = isset($GLOBALS['websocket_host']) ? $GLOBALS['websocket_host'] : "";  //where is the websocket server
		$port = isset($GLOBALS['websocket_port']) ? $GLOBALS['websocket_port'] : "";

		if(empty($host) && empty($port)) {
			return false;
		}

		$local = "http://" . $host . "/";  //url where this script run
		$data = !empty($data) ? json_encode($data) : '';  //data to be send

		$head = "GET /chat_server.php HTTP/1.1"."\r\n".
		            "Upgrade: WebSocket"."\r\n".
		            "Connection: Upgrade"."\r\n".
		            "Origin: $local"."\r\n".
		            "Host: $host"."\r\n".
		            "Content-Length: ".strlen($data)."\r\n"."\r\n";
		//WebSocket handshake
		$sock = fsockopen($host, $port, $errno, $errstr, 2);
		fwrite($sock, $head ) or die('error:'.$errno.':'.$errstr);
		$headers = fread($sock, 2000);
		fwrite($sock, $data ) or die('error:'.$errno.':'.$errstr);
		$wsdata = fread($sock, 2000);  //receives the data included in the websocket package "\x00DATA\xff"
		fclose($sock);
	}

	public static function websocketSendMessage($payload) {
        $host = isset($GLOBALS['websocket_host']) ? $GLOBALS['websocket_host'] : "";  //where is the websocket server
		$port = isset($GLOBALS['websocket_port']) ? $GLOBALS['websocket_port'] : "";
		$httpType = isset($GLOBALS['websocket_address_type']) && $GLOBALS['websocket_address_type'] == "wss" ? "https" : "http";

		$cURLConnection = curl_init();
        curl_setopt($cURLConnection, CURLOPT_URL,  $httpType."://".$host.":".$port."/send_message?pathName=" . self::getSiteBaseURL(true));
        
        $payload = json_encode($payload);

        curl_setopt( $cURLConnection, CURLOPT_POSTFIELDS, $payload );
        curl_setopt( $cURLConnection, CURLOPT_HTTPHEADER, array('Content-Type:application/json'));
        curl_setopt( $cURLConnection, CURLOPT_RETURNTRANSFER, true);

        $responce = curl_exec($cURLConnection);

        if (curl_errno($cURLConnection)) {
            $errorMsg = curl_error($cURLConnection);

            if(!empty($errorMsg)) {
                return array(
                    'error' => true,
                    'messages' => $errorMsg 
                );
            }
        }

        curl_close($cURLConnection);

        return !empty($responce) ? json_decode($responce, true) : array('error' => true, 'messages' => 'Something went wrong');
	}

	public static function getRecentChat($startTime = '', $endTime = '') {
		$rez = sqlStatement("SELECT vcf1.conversation_id from (SELECT * from vh_chat_form vcf where vcf.creation_time BETWEEN '" . $startTime . "' AND '" . $endTime . "' order by vcf.creation_time desc) as vcf1 group by vcf1.conversation_id", array());

		$msgItems = array();

		for ($iter=0; $row=sqlFetchArray($rez); $iter++) {
		 	if(isset($row['conversation_id'])) {
		 		$msgData = self::getChatForm(array(
		 			'conversation_id' => array('value' => $row['conversation_id'], 'condition' => '')
		 		), "*", "creation_time desc", "1", "0");

		 		$conversationData = self::getChatConversations(array(
		 			'conversation_id' => array('value' => $row['conversation_id'], 'condition' => '')
		 		), "*", "id desc", "1", "0");

		 		$msgData['pid'] = $conversationData['pid'];
		 		$msgData['convid'] = $conversationData['id'];
		 		$msgData['name'] = $conversationData['first_name'] . " " . $conversationData['last_name'];
		 		$msgData['conv_uid'] = $conversationData['uid'];

		 		if(empty($msgData)) {
		 			continue;
		 		}

		 		$msgItems[] = $msgData;
	    	}
	    }

	    return $msgItems;
	}

	public static function saveMessageLog($data = array(), $conversation_id) {
		$isDataExits = sqlQuery("SELECT ml.id from message_log ml where msg_convid = ? order by id desc limit 1", array($conversation_id));

		if(isset($isDataExits['id']) && !empty($isDataExits['id'])) {
			$msgData = array(
				'1',
				isset($data['direction']) ? $data['direction'] : "",
				isset($data['message']) ? $data['message'] : "",
				$isDataExits['id']
			);

			sqlStatementNoLog("UPDATE `message_log` SET `activity`= ?, `direction`= ?, `msg_time`= NOW(), `message` = ? WHERE `id` = ?", $msgData);
		} else {
			$chatConv = self::getChatConversations(array(
				"conversation_id" => array("value" => $conversation_id, "condition" => "")
			),"*");

			$msgData = array(
				'1',
				isset($chatConv['pid']) ? $chatConv['pid'] : 0,
				'',
				isset($data['direction']) ? $data['direction'] : "",
				isset($_SESSION['authUserID']) ? $_SESSION['authUserID'] : "",
				isset($data['msg_to']) ? $data['msg_to'] : "",
				isset($data['msg_from']) ? $data['msg_from'] : "",
				isset($data['msg_convid']) ? $data['msg_convid'] : "",
				isset($data['msg_status']) ? $data['msg_status'] : "",
				isset($data['message']) ? $data['message'] : ""
			);

			sqlInsert("INSERT INTO `message_log` SET `activity`= ?, `type`='CHAT', `pid`=?, `event`=?, `direction`=?, `userid`=?, `msg_to`=?, `msg_from`=?, `msg_convid`=?, `msg_time`= NOW(), `msg_status`=?, `message`=?", $msgData);
		}
	}

	public static function linkPatient($pid, $conversation_id) {
		sqlStatementNoLog("UPDATE `vh_chat_conversations` SET `pid`= ? WHERE `conversation_id` = ?", array($pid, $conversation_id));
	}

	public static function support_board_api($query) {
	    $cryptoGen = new CryptoGen();
	    $webhook_token = $cryptoGen->decryptStandard($GLOBALS['webhook_token']);

	    $ch = curl_init($GLOBALS['webhook_url']);
	    $parameters = [
	        CURLOPT_RETURNTRANSFER => true,
	        CURLOPT_SSL_VERIFYPEER => false,
	        CURLOPT_USERAGENT => 'Support Board',
	        CURLOPT_POST => true,
	        CURLOPT_CONNECTTIMEOUT => 5,
	        CURLOPT_POSTFIELDS => http_build_query(array_merge(['token' => $webhook_token], $query))
	    ];
	    curl_setopt_array($ch, $parameters); 
	    $response = curl_exec($ch);
	    curl_close($ch);

	    return json_decode($response, true);
	}

	public static function isUserOnline($user_id = "") {
		$getOnlineUsers = self::support_board_api([
	        'function' => 'get-online-users',
	    ]);


		if(!empty($user_id)) {
		    $isOnline = false;
		    if(!empty($getOnlineUsers) && isset($getOnlineUsers['response'])) {
		    	foreach ($getOnlineUsers['response'] as $onlineUser) {
		    		if(isset($onlineUser['id']) && $onlineUser['id'] === $user_id) {
		    			$isOnline = true;
		    			break;
		    		}
		    	}
		    }

		    return $isOnline;
		} else {
			return !empty($getOnlineUsers) && isset($getOnlineUsers['response']) ? $getOnlineUsers['response'] : array();
		}
	}

	public static function getUserDetails($user_id) {
		$getUserData = self::support_board_api([
	        'function' => 'get-user',
	        'user_id' => $user_id,
	        'extra' => true
	    ]);

	    $getUserData = !empty($getUserData) && isset($getUserData['response']) ? $getUserData['response'] : array();

		if(!empty($getUserData)) {
		    
		    foreach ($getUserData['details'] as $fieldKey => $fieldValue) {
		    	$getUserData[$fieldValue['slug']] = $fieldValue['value'];
		    }

		    if(isset($getUserData['ip'])) {
			    $apiUrl = "http://ip-api.com/json/{$getUserData['ip']}";
				$response = file_get_contents($apiUrl);

				// Check if the request was successful
				if ($response !== false) {
					// Decode the JSON response
					$data = json_decode($response);

					if ($data->status == 'success') {
					    $getUserData['location'] = $data->city.", ".$data->country;
					} else {
					    $getUserData['location'] ="Unknown";
					}
				}
			}
		}

	    return $getUserData;
	}

	public static function updateUsersLastActivity() {
		if(isset($GLOBALS['webhook_userid'])) {
			$usla = self::support_board_api([
		        'function' => 'update-users-last-activity',
		        'user_id' => $GLOBALS['webhook_userid'],
		        'return_user_id' => '-1',
		        'check_slack' => false
		    ]);

		    return $usla;
		}

		return false;
	}

	public static function getSiteBaseURL($needBaseEncode = false) {
		// output: /myproject/index.php
	    $currentPath = $_SERVER['PHP_SELF']; 

	    // output: Array ( [dirname] => /myproject [basename] => index.php [extension] => php [filename] => index ) 
	    $pathInfo = pathinfo($currentPath); 

	    // output: localhost
	    $hostName = $_SERVER['HTTP_HOST']; 

	    // output: http://
	    //$protocol = strtolower(substr($_SERVER["SERVER_PROTOCOL"],0,5))=='https'?'https':'http';
	    $protocol = empty($_SERVER['HTTPS']) ? 'http' : 'https';

	    // return: http://localhost/myproject/
	    $bUrl = $protocol.'://'.$hostName.$GLOBALS['webroot'];
	
	    if($needBaseEncode === true) {
	    	return urlencode(base64_encode($bUrl));
	    }

	    return $bUrl;
	}

	public static function getRandomColor() {
    	$rand = array('0', '1', '2', '3', '4', '5', '6', '7', '8', '9', 'a', 'b', 'c', 'd', 'e', 'f');
	    $color = '#'.$rand[rand(0,15)].$rand[rand(0,15)].$rand[rand(0,15)].$rand[rand(0,15)].$rand[rand(0,15)].$rand[rand(0,15)];
	    return $color;
	}
}