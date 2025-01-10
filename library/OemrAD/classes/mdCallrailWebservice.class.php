<?php

namespace OpenEMR\OemrAd;

@include_once(__DIR__ . "/../interface/globals.php");
@include_once("$srcdir/patient.inc");

use OpenEMR\Common\Crypto\CryptoGen;

class CallrailWebservice {

	const ACCEPTED_CODES = '200, 201, 202';

	public function __construct() {
	}

	public static function fetchNewIncomingMessages() {
		$responceData = array(
			'status' => 'true',
			'synced_count' => 0,
			'failed_count' => 0,
			'conversation_count' => 0
		);

		try {

		$params = array(
			'page' => '1'
		);

		$lastrecord = sqlQuery("SELECT * from `message_log` ml WHERE `type` = 'SMS' and `source` = 'callrail' order by `msg_time` desc limit 1;", array());

		if (!empty($lastrecord) && !empty($lastrecord['msg_time'])) {
			$date = new \DateTime($lastrecord['msg_time']);
			// Format the date in the desired 'YYYY-MM-DDTHH:MM' format
			$params['start_date'] = $date->format('Y-m-d\TH:i');
		}

		$conversationsItems = self::getAllConversations($params);
		$allmsgList = array();

		foreach ($conversationsItems as $conversationsItem) {
			if (isset($conversationsItem['id'])) {

				$msgList = self::getConversation($params, $conversationsItem['id']);

				foreach ($msgList as $msgItem) {
					if (isset($msgItem['id']) && !empty($msgItem['id'])) {
						$timestamp = $msgItem['created_at'] ?? "";
						$idxtimestamp = "";

						if (!empty($timestamp) && strtotime($timestamp) !== false) {
							//$timestamp = date('Y-m-d H:i:s', strtotime($timestamp)); // subtract local offset from utc
							$timestampdate = \DateTime::createFromFormat('Y-m-d\TH:i:s.uP', $timestamp);

							// Convert it to the desired format
							$timestamp = $timestampdate->format('Y-m-d H:i:s');
							$idxtimestamp = $timestampdate->getTimestamp();

						}
						$timestamp = (strtotime($timestamp) === false)? date('Y-m-d H:i:s') : $timestamp;

						if (!empty($idxtimestamp)) {
							$msgItem['timestamp'] = $timestamp;
							$allmsgList[$idxtimestamp] = $msgItem;
						}
					}
				}

				// if (!empty($insertStatus)) {
				// 	$responceData['conversation_count']++;
				// }
			}
		}

		foreach ($allmsgList as $msgItem) {
			// Clean numbers
			$msgId = $msgItem['conversation_id'] . "_" . $msgItem['id'];
			$timestamp = $msgItem['created_at'] ?? "";
			$message = $msgItem['content'] ?? "";
			$direction = "";

			if ($msgItem["direction"] == "incoming") {
				$direction = "in";
			} else if ($msgItem["direction"] == "outgoing") {
				$direction = "out";
			}

			if (!empty($timestamp) && strtotime($timestamp) !== false) {
				//$timestamp = date('Y-m-d H:i:s', strtotime($timestamp)); // subtract local offset from utc
				$timestampdate = \DateTime::createFromFormat('Y-m-d\TH:i:s.uP', $timestamp);

				// Convert it to the desired format
				$timestamp = $timestampdate->format('Y-m-d H:i:s');

			}
			$timestamp = (strtotime($timestamp) === false)? date('Y-m-d H:i:s') : $timestamp;

			if ($direction == "in") {
				$toNumber = $msgItem["current_tracking_number"] ?? "";
				$fromNumber = $msgItem["customer_phone_number"] ?? "";
			} else {
				$toNumber = $msgItem["customer_phone_number"] ?? "";
				$fromNumber = $msgItem["current_tracking_number"] ?? "";
			}

			$toNumber = preg_replace("/[^0-9]/", "", $toNumber);
			$fromNumber = preg_replace("/[^0-9]/", "", $fromNumber);

			$pids = array();
			if ($direction == "in" && !empty($fromNumber)) {
				$pParam = array();
    			$pParam["phone_cell"] = array("value" => $fromNumber, "condition" => "");
    			$pat_data = @getPatientByCondition($pParam, "pid");
    			if (!empty($pat_data) && is_array($pat_data) && array_key_exists('pid', $pat_data)) {
					$pids[] = $pat_data['pid'];
				} else if(is_array($pat_data) && count($pat_data) > 0) {
					foreach ($pat_data as $pItem) {
						$pids[] = $pItem['pid'];
					}
				}
				
			}
			// Unique patient found
			$pid = ( is_array($pids) && count($pids) == 1) ? $pids[0] : '';
			$insertStatus = self::insertMsg("SMS_RECEIVED", $toNumber, $fromNumber, $pid, $msgId, $timestamp, "SMS_RECEIVED", $message, $direction, true);

			if (!empty($insertStatus)) {
				$responceData['synced_count']++;
			} else {
				$responceData['failed_count']++;
			}
		}

		} catch (Exception $e) {
			return array(
        		'status' => 'false',
				'error' => 'Caught exception: ',  $e->getMessage(), "\n"
        	);
		}

		return $responceData;
	}

	public static function insertMsg($event, $toNumber, $fromNumber, $pid, $msgId, $timestamp, $msg_status, $message, $direction='in', $active=true, $raw_data='') {
		// Create log entry
		$binds = array();
		$binds[] = $event;
		$binds[] = ($active)? '1' : '0';
		$binds[] = $direction;
		$binds[] = 'Pro-Care Bi-Directional';
		$binds[] = $_SESSION['authUserID'] ?? 0;
		$binds[] = (!empty($pid))? $pid : 0;
		$binds[] = null;
		$binds[] = $toNumber;
		$binds[] = $fromNumber;
		$binds[] = $msgId; // message id of current message
		$binds[] = (empty($timestamp)) ? date('Y-m-d H:i:s') : $timestamp;
		$binds[] = $msg_status;
		$binds[] = $message;
		$binds[] = $raw_data;

		// Store log record
		$sql = "INSERT INTO `message_log` SET ";
		$sql .= "type='SMS', source = 'callrail', event=?, activity=?, direction=?, gateway=?, userid=?, pid=?, eid=?, msg_to=?, msg_from=?, msg_newid=?, msg_time=?, msg_status=?, message=?, `raw_data`=?";

		return sqlInsert($sql, $binds);
	}

	public static function getConversation($filters =array(), $conversation_id = '') {
		$cryptoGen = new CryptoGen();
		$cr_account_id = isset($GLOBALS['cr_account_id']) ? $GLOBALS['cr_account_id'] : "";
		$cr_token = $cryptoGen->decryptStandard($GLOBALS['cr_token']);

		if(empty($cr_account_id) || empty($cr_token)) {
			return false;
		}

		if (!isset($filters['per_page'])) {
			$filters['per_page'] = "100";
		}

		if (!isset($filters['page'])) {
			$filters['page'] = "1";
		}

		$queryString = http_build_query($filters);
		$messagesItemList = array();

		$messagesItems = self::callRequest(
			array(
				'api_url' => 'https://api.callrail.com/v3/a/'. $cr_account_id .'/text-messages/'. $conversation_id .'.json?' . $queryString,
				'method' => 'GET'
			),
			$cr_token
		);

		if (!empty($messagesItems)) {
			if (isset($messagesItems['messages']) && is_array($messagesItems['messages']) && !empty($messagesItems['messages'])) {

				$start_date_val = isset($filters['start_date']) && !empty($filters['start_date']) ? new \DateTime($filters['start_date']) : '';

				if (!empty($start_date_val)) {
					foreach ($messagesItems['messages'] as $msgItem) {
						if (isset($msgItem['id'])) {
							$created_date_val = isset($msgItem['created_at']) && !empty($msgItem['created_at']) ? new \DateTime($msgItem['created_at']) : '';
							
							if ($created_date_val > $start_date_val) {
								$tmpmsgitem = $msgItem;
								$tmpmsgitem['conversation_id'] = $messagesItems['id'] ?? '';
								$tmpmsgitem['customer_phone_number'] = $messagesItems['customer_phone_number'] ?? '';
								$tmpmsgitem['current_tracking_number'] = $messagesItems['current_tracking_number'] ?? '';
								$tmpmsgitem['state'] = $messagesItems['state'] ?? '';

								// Add into items
								$messagesItemList[] = $tmpmsgitem;
							}
						}
					}
				} else {
					// Add into items
					foreach ($messagesItems['messages'] as $msgItem) {
						if (isset($msgItem['id'])) {
							$tmpmsgitem = $msgItem;
							$tmpmsgitem['conversation_id'] = $messagesItems['id'] ?? '';
							$tmpmsgitem['customer_phone_number'] = $messagesItems['customer_phone_number'] ?? '';
							$tmpmsgitem['current_tracking_number'] = $messagesItems['current_tracking_number'] ?? '';
							$tmpmsgitem['state'] = $messagesItems['state'] ?? '';

							// Add into items
							$messagesItemList[] = $tmpmsgitem;
						}
					}
				}
			}


			if ($messagesItems['page'] < $messagesItems['total_pages']) {
				$tmpfilter = $filters;
				$tmpfilter['page'] = $currentPage + 1;
				$submessagesItemList = self::getAllConversations($tmpfilter);

				if (!empty($messagesItemList)) {
					$messagesItemList = array_merge($messagesItemList, $submessagesItemList);
				}
			}
		}

		return $messagesItemList;
	}

	public static function getAllConversations($filters =array()) {

		$cryptoGen = new CryptoGen();
		$cr_account_id = isset($GLOBALS['cr_account_id']) ? $GLOBALS['cr_account_id'] : "";
		$cr_token = $cryptoGen->decryptStandard($GLOBALS['cr_token']);

		if(empty($cr_account_id) || empty($cr_token)) {
			return false;
		}

		if (!isset($filters['per_page'])) {
			$filters['per_page'] = "250";
		}

		if (!isset($filters['page'])) {
			$filters['page'] = "1";
		}

		$currentPage = $filters['page'] ?? "1";

		$queryString = http_build_query($filters);
		$conversationsItemList = array();

		$conversationsItems = self::callRequest(
			array(
				'api_url' => 'https://api.callrail.com/v3/a/'. $cr_account_id .'/text-messages.json?' . $queryString,
				'method' => 'GET'
			),
			$cr_token
		);

		if (!empty($conversationsItems)) {
			if (isset($conversationsItems['conversations']) && is_array($conversationsItems['conversations']) && !empty($conversationsItems['conversations'])) {
				// Add into items
				$conversationsItemList = $conversationsItems['conversations'];
			}


			if ($conversationsItems['page'] < $conversationsItems['total_pages']) {
				$tmpfilter = $filters;
				$tmpfilter['page'] = $currentPage + 1;
				$subConversationsItemList = self::getAllConversations($tmpfilter);

				if (!empty($subConversationsItemList)) {
					$conversationsItemList = array_merge($conversationsItemList, $subConversationsItemList);
				}
			}
		}

		return $conversationsItemList;
	}

	public static function callRequest($request = array(), $token = "") {

		if(!isset($request["api_url"]) || empty($request["api_url"])) {
			return false;
		}

		if(!isset($request["method"]) || empty($request["method"])) {
			return false;
		}

		$headerData = array('Content-Type: application/json');

		$ch = curl_init();

		curl_setopt($ch, CURLOPT_URL, $request["api_url"]);
		curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $request["method"] );
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);

		if (isset($request["body"]) && !empty($request["body"])) {
			curl_setopt( $ch, CURLOPT_POSTFIELDS, $request["body"] );
		}

		if (isset($request["header"]) && !empty($request["header"])) {
			$headerData = array_merge($headerData, $request["header"]);
		}

		if(isset($token) && !empty($token)) {
			$headerData[] = "Authorization: Bearer " . $token;
		}

		if (!empty($headerData)) {
			curl_setopt($ch, CURLOPT_HTTPHEADER, $headerData);
		}

		// Send data
		$result = curl_exec($ch);
		$errCode = curl_errno($ch);
		$errText = curl_error($ch);
		$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

		// Handle result
		return self::handle($result, $httpCode);
	}

	protected static function handle($result, $httpCode) {
		// Check for non-OK statuses
		$codes = explode(",", static::ACCEPTED_CODES);
		if (!in_array($httpCode, $codes)) {
			if($httpCode == "400") {
				//$xml = simplexml_load_string($result);
				//$json = json_encode($xml);
				return json_decode($result,TRUE);
			} else {
				return json_decode($result, true);
			}
		} else {
			return json_decode($result, true);
		}
	}
}