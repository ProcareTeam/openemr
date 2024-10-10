<?php

namespace OpenEMR\OemrAd;

@include_once(__DIR__ . "/../interface/globals.php");

class WordpressWebservice {

	const ACCEPTED_CODES = '200, 201, 202';

	/*Constructor*/
	public function __construct() {
	}

	public static function getApiConfigurationTypeList() {
		$api_configuration_type_List = array(
			'' => 'Select',
			'wordpress' => 'Wordpress'
		);

		return $api_configuration_type_List;
	}

	public static function saveApiEventConfiguration($data) {
		$api_configuration_type = $data['api_configuration_type'] ? $data['api_configuration_type'] : "";

		//Write new record
		$api_event_sql = "INSERT INTO `vh_api_event_configurations` ( api_configuration_type ) VALUES (?) ";
			
		$rowId = sqlInsert($api_event_sql, array(
			$api_configuration_type
		));

		if($rowId && !empty($api_configuration_type)) {
			$data['api_config_id'] = $rowId;
			return self::insertExternalAPIConfigurations($data);
		} else {
			return false;
		}

		return true;
	}

	public static function updateApiEventConfiguration($id, $data) {
		if(!empty($data) && !empty($id)) {
			$type_list = self::getApiConfigurationTypeList();
			$api_configuration_type = $data['api_configuration_type'] ? $data['api_configuration_type'] : "";
			$data['api_config_id'] = $id;

			foreach ($type_list as $key => $item) {
				if(!empty($key)) {
					sqlStatement("DELETE FROM `vh_external_api_configurations` WHERE api_config_id = ? ", array($id));
				}
			}


			sqlStatementNoLog("UPDATE `vh_api_event_configurations` SET api_configuration_type = ? WHERE id = ?", array(
				$api_configuration_type,
				$id
			));

			return self::insertExternalAPIConfigurations($data);
		}

		return true;
	}

	public static function insertExternalAPIConfigurations($data) {
		extract($data);

		if($api_config_id) {
			//Write new record
			$api_type_sql = "INSERT INTO `vh_external_api_configurations` ( ";
			$api_type_sql .= "api_config_id, auth_type, api_url, api_token) VALUES (?, ?, ?, ?) ";
				
			$webRowId = sqlInsert($api_type_sql, array(
				$api_config_id,
				'REST',
				$api_url,
				$api_token
			));

			if(!$webRowId) {
				return false;
			}
		}

		return true;
	}

	public static function getApiEventConfiguration($id = '', $type = '') {
		$result_list = array();
		$binds = array();

		$sql = "SELECT aec.`id` AS id, aec.`api_configuration_type` AS api_configuration_type, ";
		$sql .= "aiwc.* ";
		$sql .= "FROM `vh_api_event_configurations` aec ";

		$sql .= "LEFT JOIN `vh_external_api_configurations` as aiwc ON aiwc.api_config_id = aec.id ";

		$whereSql  = [];
		if(!empty($id)) {
			$whereSql[] = "aec.`id` = ?";
			$binds[] = $id;
		}

		if(!empty($type)) {
			$whereSql[] = "aec.`api_configuration_type` = ? ";
			$binds[] = $type;
		}

		if(!empty($whereSql)) {
			$whereSql = " WHERE " . implode(" AND ", $whereSql);
		} else {
			$whereSql = "";
		}

		$sql .= " $whereSql order by aec.`date` ASC";

		$result = sqlStatementNoLog($sql, $binds);

		while ($result_data = sqlFetchArray($result)) {
			$result_list[] = $result_data;
		}
		return $result_list;
	}

	public static function getApiConfigTitle($id = '', $item = array()) {
		$title_text = "";

		if(!empty($id)) {
			$configurationData = self::getApiEventConfiguration($id);
			if(!empty($configurationData) && count($configurationData) > 0) {
				$item = $configurationData[0];
			}
		}

		if(!empty($item)) {
			$type_list = self::getApiConfigurationTypeList();
			$api_configuration_type = $item['api_configuration_type'] ? $item['api_configuration_type'] : "";
			$api_configuration_type_title = ($type_list[$api_configuration_type]) ? $type_list[$api_configuration_type] : "";
			$titleAttrList = array();

			if($api_configuration_type == "wordpress") {
				if(isset($item['api_url']) && !empty($item['api_url'])) {
					$titleAttrList[] = $item['api_url'];
				}

				$title_text = $api_configuration_type_title . ( !empty($titleAttrList) ? " (".implode(", ", $titleAttrList).")" : "" );
			}
		}

		return $title_text;
	}

	public static function deleteApiEventConfiguration($id = '') {
		if(!empty($id)) {
			$sql = "DELETE FROM `vh_api_event_configurations` WHERE `id` = '$id' ";
			$deleteEventResponce = sqlStatement($sql);
		
			sqlStatement("DELETE FROM `vh_external_api_configurations` WHERE api_config_id = ? ", array($id));

			return true;
		}

		return false;
	}

	public static function handleInSyncPrepare($userId = '', $opt = "") {
		//if($GLOBALS['abook_user_sync'] != 1) {
			//return;
		//}

		if(!empty($opt) && !empty($userId)) {

			$uRow = sqlQuery("SELECT CASE WHEN u.username = '' THEN CONCAT(LEFT(u.fname, 1), u.lname, u.id) ELSE u.username END as username1, vapc.portal_access, u.* FROM users u JOIN `vh_attorney_portal_config` as vapc on vapc.abook_id = u.id WHERE id = ?", array($userId));
			$portal_access = isset($uRow['portal_access']) ? $uRow['portal_access'] : '';

			if (!empty($portal_access) && $portal_access == "1") {

				if($opt === "INSERT") {
					$hpData = array(
						'event_id' => 'wp_user_sync',
						'config_id' => 'wp_user_create_update',
						'event_type' => '2',
						'mode' => 'INSERT_UPDATE',
						'tablename' => 'users',
						'uniqueid' => $userId,
						'user_type' => 'Cron',
						'sent' => 0,
						'trigger_time' => date('Y-m-d H:i:s', strtotime("+1 minutes")),
						'time_delay' => 0
					);

					self::createlogData($hpData);
				} else if($opt === "UPDATE") {
					$hpData = array(
						'event_id' => 'wp_user_sync',
						'config_id' => 'wp_user_create_update',
						'event_type' => '2',
						'mode' => 'INSERT_UPDATE',
						'tablename' => 'users',
						'uniqueid' => $userId,
						'user_type' => 'Cron',
						'sent' => 0,
						'trigger_time' => date('Y-m-d H:i:s', strtotime("+1 minutes")),
						'time_delay' => 0
					);

					self::createlogData($hpData);
				} else if($opt === "DELETE") {
					//if($uabookType === 'Attorney') {
						$hpData = array(
							'event_id' => 'wp_user_sync',
							'config_id' => 'wp_user_delete',
							'event_type' => '2',
							'mode' => 'DELETE',
							'tablename' => 'users',
							'uniqueid' => isset($uRow['email']) ? $uRow['email'] : "",
							'user_type' => 'Cron',
							'sent' => 0,
							'trigger_time' => date('Y-m-d H:i:s', strtotime("+1 minutes")),
							'time_delay' => 0
						);

						self::createlogData($hpData);
					//}
				}
			}
		}
	}

	public static function createlogData($data = array()) {
		extract($data);

		if(!empty($data)) {
			$binds = array();

			foreach ($data as $di => $dItem) {
				$binds[] = $dItem !== '' ? $dItem : '';
			}

			$nId = sqlInsert("INSERT INTO vh_wordpress_sync_log ( event_id, config_id, event_type, mode, tablename, uniqueid, user_type, sent, trigger_time, time_delay) VALUES ( ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)", $binds);
		}

		return $nId;
	}

	public static function getConfiguration($id = '', $type = '', $actionType = '', $eventId = '') {
		$result_list = array();
		$binds = array();

		$sql = "SELECT nc.*, ";
		$sql .= "aec.`api_configuration_type` AS api_configuration_type, ";
		$sql .= "aiwc.api_config_id, aiwc.`api_url` AS api_url, aiwc.`api_token` AS api_token ";
		$sql .= "FROM `notification_configurations` nc ";
		$sql .= "LEFT JOIN `vh_api_event_configurations` aec ON aec.`id` = nc.`api_config` ";
		$sql .= "LEFT JOIN `vh_external_api_configurations` as aiwc ON aiwc.api_config_id = aec.id AND aec.api_configuration_type = 'wordpress' ";

		if(!empty($id)) {
			$sql .= "WHERE nc.`id` = ? ";
			$binds[] = $id;
		}

		if(!empty($type)) {
			$sql .= "WHERE nc.`trigger_type` = ? ";
			$binds[] = $type;
		}

		if(!empty($actionType)) {
			$sql .= "AND nc.`action_type` = ? ";
			$binds[] = $actionType;
		}

		$sql .= "order by nc.`date` ASC";

		$aConfigs = array();
		if(!empty($eventId)) {
			$tConfigs = self::getActionConfigurationByParam(array(
				'id' => $eventId,
				'config_id' => $id,
				'action_type' => 'wordpress',
			));

			if(isset($tConfigs)) {
				foreach ($tConfigs as $tek => $teItem) {
					if(isset($teItem['config_data'])) {
						foreach ($teItem['config_data'] as $tck => $tcItem) {
							if(isset($tcItem['id']) && isset($teItem['active']) && $teItem['active'] == 0) {
								$aConfigs[] = $tcItem['id'];
							}
						}
					}
				}
			}
		}

		$result = sqlStatementNoLog($sql, $binds);

		while ($result_data = sqlFetchArray($result)) {
			$cId = isset($result_data['id']) ? $result_data['id'] : '';
			
			$result_data['active'] = 0;

			if(!empty($eventId)) {
				$result_data['active'] = in_array($cId, $aConfigs) ? 0 : 1;
			}

			$result_list[] = $result_data;
		}
		return $result_list;
	}

	public static function getActionConfigurationByParam($param = array()) {
		$result_list = array();
		$binds = array();

		$sql = "SELECT ac.`id` as event_id, ac.`seq` as as_seq, ac.`seq`, ac.`trigger_type` as as_trigger_type, ac.`action_type`, ac.`configuration_id`, ac.`date` as ac_date, active ";
		$sql .= "FROM `actionevent_configurations` ac ";
		//$sql .= "LEFT JOIN `notification_configurations` nc ON nc.`id` = ac.`configuration_id` ";

		$sqlWhere = array();
		foreach ($param as $pk => $pkValue) {
			if($pk == "id") {
				if(!empty($pkValue) && !is_array($pkValue)) {
					$sqlWhere[] = "ac.`id` = ?";
					$binds[] = $pkValue;
				}

				if(!empty($pkValue) && is_array($pkValue)) {
					$ids = "'".implode("','", $pkValue)."'";
					$sqlWhere[] = "ac.`id` IN (".$ids.")";
				}
			}

			if($pk == "type") {
				if(!empty($pkValue)) {
					$sqlWhere[] = "ac.`trigger_type` = ?";
					$binds[] = $pkValue;
				}
			}

			if($pk == "action_type") {
				if(!empty($pkValue)) {
					$sqlWhere[] = "ac.`action_type` = ?";
					$binds[] = $pkValue;
				}
			}
		}

		if(!empty($sqlWhere)) {
			$sql .= "WHERE " . implode(" AND ", $sqlWhere) . " ";
		}

		$configid_param = "";

		if(isset($param['id']) && isset($param['config_id']) && !empty($param['id']) && !empty($param['config_id'])) {
			$configid_param = $param['config_id'];
		}

		$sql .= "order by ac.`date` ASC";

		$result = sqlStatementNoLog($sql, $binds);

		while ($result_data = sqlFetchArray($result)) {
			$config_ids = (self::isJson($result_data['configuration_id']) == true) ? json_decode($result_data['configuration_id']) : array($result_data['configuration_id']);
			$notify_config_data = array();

			foreach ($config_ids as $key => $id) {

				//Skip configuration
				if(isset($configid_param) && !empty($configid_param) && $configid_param != $id) {
					continue;
				}

				if(!empty($id)) {
					//Get Notification Configuration
					$notify_config = self::getNotificationConfiguration($id);
					if(!empty($notify_config)) {
						$notify_config_data[] = $notify_config[0];
					}
				}
			}

			//Addd Config to list
			$result_data['config_data'] = $notify_config_data;
			$result_list[] = $result_data;
		}

		return $result_list;
	}

	public static function getDataForProcess($ids = array(), $event_type = false, $limit = 5000, $offset = 0) {
		$sql = "SELECT * FROM `vh_wordpress_sync_log` WHERE ";

		if(!empty($ids)) {
			$sql .= " `id` IN (".implode(",", $ids).") AND ";
		}

		if($event_type !== false) {
			$sql .= " event_type = '".$event_type."' AND ";
		}

		$sql .= " sent = 0 ORDER BY seq LIMIT ".$offset.", ".$limit."";

		$resultItems = array();
		$result = sqlStatementNoLog($sql);
		while ($result_data = sqlFetchArray($result)) {
			$resultItems[] = $result_data;
		}

		return $resultItems;
	}

	public static function preProcessingQuery($ids = array(), $event_type = false) {
		$dataItems = self::getDataForProcess($ids, $event_type);
		$event_ids = array();
		$configList = array();

		foreach ($dataItems as $key => $item) {
			if(!empty($item['id'])) {
				$event_ids[] = $item['id'];
			}

			if(isset($item['config_id']) && !empty($item['config_id'])) {
				if(!isset($configList[$item['config_id']])) {
					$configs = self::getConfiguration($item['config_id'], '', '', $item['event_id']);
					if(isset($configs) && count($configs) > 0) {
						foreach ($configs as $configKey => $config) {
							$configList[$config['id']] = $config;
						}
					}
				}

				if(isset($configList[$item['config_id']])) {
					$config = $configList[$item['config_id']];
				}

				$replacevars = array(
				  '$nid' => isset($item['id']) ? $item['id'] : "",
				  '$event_id' => isset($item['event_id']) ? $item['event_id'] : "",
				  '$tablename' => isset($item['tablename']) ? $item['tablename'] : "",
				  '$uniqueid' => isset($item['uniqueid']) ? $item['uniqueid'] : ""
				);

				if(isset($config)) {
					if($item['config_id'] == $config['id']) {
						$dataItems[$key]['config_item'] = $config;
						
						if($config['active'] === 0) {
							if(!empty(trim($config['pre_processing_data_set']))) {
								$pre_processing = trim($config['pre_processing_data_set']);
								$final_pre_processing = strtr($pre_processing, $replacevars);

								//Execute Query
								sqlStatementNoLog($final_pre_processing);
							}
						}
					}
				}
			}
		}

		//return $event_ids;
		return $dataItems;
	}

	public static function savePreparedData($data) {
		//Extract value to variable
		extract($data);

		//Write new record
		$sql = "INSERT INTO `vh_wordpress_sync_log` ( ";
		$sql .= "event_id, config_id, event_type, tablename, uniqueid, uid, user_type, sent, sent_time, trigger_time, time_delay, created_time ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?) ";
			
		sqlInsert($sql, array(
			$event_id,
			$config_id,
			$event_type,
			$tablename,
			$uniqueid,
			$uid,
			$user_type,
			$sent,
			$sent_time,
			$trigger_time,
			$time_delay,
			date('Y-m-d H:i:s')
		));

		return true;
	}

	public static function isNotifyDataExists($event_id, $tablename, $uniqueid, $send_status, $config_id) {
		$row = sqlQuery("SELECT * FROM `vh_wordpress_sync_log` WHERE event_id = ? AND config_id = ? AND tablename = ? AND uniqueid = ? AND sent = ? ", array($event_id, $config_id, $tablename, $uniqueid, $send_status));
		
		if(isset($row) && !empty($row)) {
			return $row;
		}

		return false;
	}

	public static function prepareNotificationData($prepareFor = 'both', $eventid_param = '', $configid_param = '') {
		$configs = self::getActionConfigurationByParam(array(
			'id' => $eventid_param,
			'config_id' => $configid_param,
			'action_type' => 'wordpress',
		));

		$totalPreparedItem = 0;
		$preparedItemStatus = array();
		
		if(isset($configs)) {
			foreach ($configs as $key => $action_config) {
				foreach ($action_config['config_data'] as $key => $config_data) {
					$config = array_merge($action_config, $action_config['config_data'][$key]);
					$event_id = $config['event_id'];

					if(isset($config['configuration_id']) && !empty($config['configuration_id']) && $config['active'] == "0" && !empty($config['id'])) {
						if($config['action_type'] == "wordpress") {
							if($config['as_trigger_type'] == "time" && ($prepareFor == 'both' || $prepareFor == 'time')) {

								if(isset($config['data_set']) && !empty($config['data_set'])) {
									$dataSetQtr = trim($config['data_set']);

									$resultItems = array();
									$result = sqlStatementNoLog($dataSetQtr);
									if($result) {
										while ($result_data = sqlFetchArray($result)) {
											$resultItems[] = $result_data;

											$tablename = isset($result_data['tablename']) ? $result_data['tablename'] : "";
											$uniqueid = isset($result_data['uniqueid']) ? $result_data['uniqueid'] : "";
											$qtr_trigger_time = isset($result_data['trigger_time']) ? $result_data['trigger_time'] : "";

											if(!empty($tablename) && !empty($uniqueid)) {
												$uid = isset($_SESSION['authUserID']) ? $_SESSION['authUserID'] : "";
												$user_type = isset($uid) && !empty($uid) ? "User" : "Cron";

												$isExists = self::isNotifyDataExists($event_id, $tablename, $uniqueid, '0', $config['id']);

												if($isExists === false) {
													//Calculate Trigger Time
													$triggerTime = Reminder::calTriggerTime($config);
													$current_unix_time = strtotime('now');

													if(!empty($qtr_trigger_time) && $current_unix_time < strtotime($qtr_trigger_time)) {
														$triggerTime = date('Y-m-d H:i:s', strtotime($qtr_trigger_time));
													}

													$event_datetime = isset($triggerTime) ? $triggerTime : false;

													if(!empty($triggerTime)) {
														$preparedData = array(
															'event_id' => $event_id,
															'config_id' => $config['id'],
															'event_type' => '1',
															'tablename' => $tablename,
															'uniqueid' => $uniqueid,
															'uid' => $uid,
															'user_type' => $user_type,
															'sent' => '0',
															'sent_time' => '',
															'trigger_time' => $triggerTime,
															'time_delay' => $config['time_delay']
														);

														self::savePreparedData($preparedData);
														$totalPreparedItem++;

														$preparedItemStatus = Reminder::prepareItemStatus($config, $preparedItemStatus);
													}
												}
											}
										}
									}
								}

							} else if($config['as_trigger_type'] == "event" && ($prepareFor == 'both' || $prepareFor == 'event')) {
								//Todo List
							}
						}
					}
				}
			}
		}

		return array('total_prepared_item' => $totalPreparedItem, 'prepared_item_status' => $preparedItemStatus);
	}

	public static function externalApiByEvent($type = 0, $eventid_param = '', $configid_param = '') {
		$itemIdList = array();
		$event_type = (isset($type) && $type !== 0) ? $type : false;

		$iIds = Reminder::getSendItemIdByEvent(array(
			'tablename' => 'vh_wordpress_sync_log',
			'event_type' => $event_type,
			'event_id' => $eventid_param,
			'config_id' => $configid_param
		));

		if(!empty($iIds)) {
			return self::externalApi($type, $iIds);
		}

		return array('total_items' => 0, 'total_sent_item' => 0);
	}

	public static function externalApi($type = 0, $itemIds = array()) {
		$totalItem = 0;
		$totalsentItem = 0;
		$event_type = (isset($type) && $type !== 0) ? $type : false;

		//Run Pre processing query
		$dataItems = self::preProcessingQuery($itemIds, $event_type);
		$preparedDataForSync = array();

		foreach ($dataItems as $key => $item) {
			if(!empty($item['trigger_time'])) {
				$trigger_time = $item['trigger_time'];

				//Unix time
				$current_unix_time = strtotime('now');
				$trigger_unix_time = strtotime($item['trigger_time']);

				if(!isset($item['seq'])) {
					continue;
				}

				$seq_key = "s".$item['seq'];
				$config_key = $item['event_id']."_".$item['config_id'];

				if($item['sent'] == 0 && $trigger_unix_time <= $current_unix_time) {
					$p_data_item = array();

					if(!isset($preparedDataForSync[$seq_key])) {
						$preparedDataForSync[$seq_key] = array();
					}
					
					if(!isset($preparedDataForSync[$seq_key][$config_key])) {
						$preparedDataForSync[$seq_key][$config_key] = array(
							'seq' => $item['seq'],
							'config' => $item['config_item'] ? $item['config_item'] : array(),
							'data_items' => array(),
							'error_data_items' => array()
						);
					} else if(isset($preparedDataForSync[$seq_key][$config_key])) {
						$preparedDataForSync[$seq_key][$config_key]['config'] = $item['config_item'] ? $item['config_item'] : array();
					}


					try {

						//If Config Empty
						if(!isset($item['config_item']) || empty($item['config_item'])) {
							throw new \Exception('Config Not Found.');
						}

						//If Config not active
						if(!isset($item['config_item']['active']) || $item['config_item']['active'] === 1 ) {
							throw new \Exception('Config Not active.');
						}

						//If Api Config Empty
						if(!isset($item['config_item']['api_config']) || empty($item['config_item']['api_config'])) {
							throw new \Exception('Api config empty.');
						}

						//If Api Config Not Found
						if(!isset($item['config_item']['api_config_id']) || empty($item['config_item']['api_config_id'])) {
							throw new \Exception('Api config not found.');
						}

						//Fetch Data For Sync	
						$syncData = self::fetchDataForSync($item);
						
						if($syncData === false) {
							throw new \Exception('Fetch data item error.');
						}

						$syncDataItems = (!empty($syncData) && is_array($syncData) && count($syncData) > 0) ? $syncData : array();

						if(empty($syncDataItems)) {
							throw new \Exception('Fetch data item error.');
						}

						foreach ($syncDataItems as $sdk => $syncDataItem) {
							if(isset($item['seq'])) {
								$p_data_item = array(
									'event' => $item,
									'config' => isset($item['config_item']) ? $item['config_item'] : array(),
									'data_item' => $syncDataItem
								);

								if(isset($preparedDataForSync[$seq_key]) && isset($preparedDataForSync[$seq_key][$config_key])) {
									if(isset($preparedDataForSync[$seq_key][$config_key]['data_items']) && !empty($p_data_item)) {
										$preparedDataForSync[$seq_key][$config_key]['data_items'][] = $p_data_item;
									}
								}
							}
						}

					} catch (\Exception $e) {
						$p_data_item = array(
							'dStatus' => false,
							'dError' => $e->getMessage(),
							'event' => $item,
							'config' => isset($item['config_item']) ? $item['config_item'] : array(),
							'data_item' => array()
						);

						if(isset($preparedDataForSync[$seq_key]) && isset($preparedDataForSync[$seq_key][$config_key])) {
							if(isset($preparedDataForSync[$seq_key][$config_key]['error_data_items']) && !empty($p_data_item)) {
								$preparedDataForSync[$seq_key][$config_key]['error_data_items'][] = $p_data_item;
							}
						}
					}
				}
			}
		}

		//Sort By SEQ
		ksort($preparedDataForSync, SORT_NUMERIC);

		//Process Parepared Data For Sync
		foreach ($preparedDataForSync as $ccik => $configItem) {
		foreach ($configItem as $sk => $sItem) {
			$config = $sItem['config'] ? $sItem['config'] : array();
			$batchSize = isset($config['batch_size']) && $config['batch_size'] != "" ? $config['batch_size'] : 1;
			$bodyRequestTemplate = $config['request_template'] ? base64_decode($config['request_template']) : "";

			$data_items = $sItem['data_items'] ? $sItem['data_items'] : array();
			$error_data_items = $sItem['error_data_items'] ? $sItem['error_data_items'] : array();

			$batchRecordList = array();
			$eventIds = array();

			$chunkDataItems = array();
			if($batchSize == -1) {
				$chunkDataItems = array_chunk($data_items, count($data_items));
			} if($batchSize > 0) {
				$chunkDataItems = array_chunk($data_items, $batchSize);
			}

			foreach ($chunkDataItems as $cbk => $cBatchItem) {
				$batchRecordList = array();
				$eventIds = array();

				foreach ($cBatchItem as $cik => $dItem) {
					if(!empty($dItem) && isset($dItem['event']) && isset($dItem['event']['id'])) {
						$eventId = $dItem['event']['id'];
						$eventIds[] = $eventId;

						$batchRecordList[] = isset($dItem['data_item']) ? $dItem['data_item'] : array();

						if($batchSize == 1) {
							$config = $dItem['config'] ? $dItem['config'] : array();
						}
					}

					$totalItem++;
				}

				if(is_array($batchRecordList) && count($batchRecordList) > 0) {
					if(!empty($config['time_delay']) && $config['time_delay'] != 0) {
						sleep($config['time_delay']);
					}

					$token = isset($config['api_token']) ? $config['api_token'] : '';

					if (!empty($token)) {
						//Prepare Request
						$bodyParam = self::prepareRequest(
							$bodyRequestTemplate,
							array(
								'config' => $config,
								'data_item' => ($batchSize == 1 && isset($dItem['data_item'])) ? $dItem['data_item'] : array()
							),
							$batchRecordList
						);

						if(isset($bodyParam['api_url']) && !empty($bodyParam['api_url'])) {
							if (isset($bodyParam['api_url'])) {
								$bodyParam['api_url'] = $config['api_url'] . $bodyParam['api_url'];
							}
						}

						//Service Call
						$callResponce = self::callRequest($bodyParam);

						$updateData = array(
							'sent' => 2,
							'sent_time' => date('Y-m-d H:i:s'),
							'status' => "Something went wrong.",
							'request_body' => '',
							'request_responce' => ''
						);

						if($callResponce && !empty($callResponce)) {
							$updateData = array(
								'sent' => (isset($callResponce['error'])) ? 2 : 1,
								'sent_time' => date('Y-m-d H:i:s'),
								'status' => isset($callResponce['message']) ? $callResponce['message'] : "success",
								'request_body' => isset($bodyParam) && !empty($bodyParam) ? json_encode($bodyParam) : "",
								'request_responce' => isset($callResponce) && !empty($callResponce) ? json_encode($callResponce) : ""
							);
						}

						//Update Data
						if(isset($eventIds)) {
							self::updatePreparedData(
								$eventIds,
								$updateData
							);
						}

						if(isset($updateData['sent']) && $updateData['sent'] == 1) {
							$totalsentItem += count($eventIds);
						}
					}
				}
			}

			foreach ($error_data_items as $edk => $errorDataItem) {
				if(!empty($errorDataItem) && isset($errorDataItem['event']) && isset($errorDataItem['event']['id'])) {
					$eventId = $errorDataItem['event']['id'];
					$errorMsg = "";

					if(isset($errorDataItem['dStatus']) && isset($errorDataItem['dError']) && $errorDataItem['dStatus'] === false) {
						$errorMsg = $errorDataItem['dError'];
					}

					//Update error data
					self::updatePreparedData(
						array($eventId),
						array(
							'sent' => 2,
							'sent_time' => date('Y-m-d H:i:s'),
							'status' => $errorMsg,
							'request_body' => '',
							'request_responce' => ''
						)
					);
				}

				$totalItem++;
			}

		}
		}

		return array('total_items' => $totalItem, 'total_sent_item' => $totalsentItem);
	}

	public static function callRequest($request = array(), $token = "") {

		if(!isset($request["api_url"]) || empty($request["api_url"])) {
			return false;
		}

		if(!isset($request["method"]) || empty($request["method"])) {
			return false;
		}

		// Determine the protocol (http or https)
		$protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https://' : 'http://';
		$headerData = array(
			'Origin: ' . $protocol . $_SERVER['HTTP_HOST'],
			'Content-Type: application/json'
		);

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
			//curl_setopt($ch, CURLOPT_HEADER, 0);
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

	public static function fetchDataForSync($item = array()) {
		if(!empty($item)) {
			$dataQuery = (isset($item['config_item']) && isset($item['config_item']['data_query']) && $item['config_item']['data_query'] != "") ? $item['config_item']['data_query'] : "";

			$tablename = isset($item['tablename']) ? $item['tablename'] : "";
			$uniqueid = isset($item['uniqueid']) ? $item['uniqueid'] : "";

			$tmpIdArray = array();
			if(isset($item['uniqueid'])) {
				$tmpIdArray = explode(",",$item['uniqueid']);
			}

			$preparedIdArray = array();
			if(is_array($tmpIdArray)) {
				foreach ($tmpIdArray as $tik => $tId) {
					$pItemK = '@id'.($tik + 1);
					$pItemV = $tId;

					$preparedIdArray[$pItemK] = $pItemV;
				}
			}

			$rpvars = array(
			  '@tablename' => $tablename,
			  '@id' => isset($item['uniqueid']) ? $item['uniqueid'] : "",
			  '@nt_id' => isset($item['id']) ? $item['id'] : ""
			);

			$rpvars = array_merge($rpvars, $preparedIdArray);

			if(isset($dataQuery) && $dataQuery != "") {
				$fetchDataSql = strtr($dataQuery, $rpvars);
			} else {
				$fetchDataSql = "SELECT * FROM `".$tablename."` as tb WHERE id = '".$uniqueid."'";
			}

			$resultItems = array();
			$result = sqlStatement($fetchDataSql);

			
			while ($result_data = sqlFetchArray($result)) {
				$resultItems[] = $result_data;
			}

			return $resultItems;
		}

		return false;
	}

	public static function isJson($string) {
		json_decode($string);
		return (json_last_error() == JSON_ERROR_NONE);
	}

	public static function getNotificationConfiguration($id = '', $type = '', $actionType = '') {
		$result_list = array();
		$binds = array();

		$sql = "SELECT nc.* ";
		$sql .= "FROM `notification_configurations` nc ";

		if(!empty($id)) {
			$sql .= "WHERE nc.`id` = ? ";
			$binds[] = $id;
		}

		if(!empty($type)) {
			$sql .= "WHERE nc.`trigger_type` = ? ";
			$binds[] = $type;
		}

		if(!empty($actionType)) {
			$sql .= "AND nc.`action_type` = ? ";
			$binds[] = $actionType;
		}

		$sql .= "order by nc.`date` ASC";

		$result = sqlStatementNoLog($sql, $binds);

		while ($result_data = sqlFetchArray($result)) {
			$result_list[] = $result_data;
		}
		return $result_list;
	}

	public static function prepareRequest($req = '', $data = array(), $items = array()) {
		$variableList = self::getParamForRequest($data);

		if(!empty($items)) {
			$variableList['items'] = $items;
		}
		$bodyParam = self::generateBodyParam($req, $variableList);

		return $bodyParam;
	}

	public static function getParamForRequest($item) {
		$variableList = array();
		$fieldList = array(
			'config' => array(
				'api_configuration_type',
				'api_url',
				'api_token'
			)
		);

		foreach ($fieldList as $sk => $sItem) {
			if(isset($item[$sk]) && !empty($item[$sk])) {
				foreach ($sItem as $fk => $fItem) {
					if(isset($item[$sk][$fItem])) {
						$variableList[$fItem] = $item[$sk][$fItem];
					}
				}
			}
		}

		if(isset($item['data_item']) && is_array($item['data_item'])) {
			foreach ($item['data_item'] as $ik => $dItem) {
				$variableList[$ik] = $dItem;
			}
		}

		return $variableList;
	}

	public static function generateBodyParam($req, $variableList) {
		extract($variableList);
		
		$bodyRequest = eval("return ".$req . ";");

		return $bodyRequest;
	}

	public static function updatePreparedData($id, $data) {
		if(!empty($data) && !empty($id)) {
			$binds = array();
			
			$ids = $id;
			if(!is_array($id)) {
				$ids = array($id);
			}

			foreach ($ids as $idk => $id_item) {
				$exiting_result = sqlQuery("SELECT * FROM `vh_wordpress_sync_log` WHERE id = ?  ", array($id_item));
		
				if(empty($exiting_result)) {
					$exiting_result = array();
				}
				$setColsList = array();

				foreach ($data as $data_field => $item) {
					$setColsList[] = $data_field." = ?";
					$binds[] = $item;
				}

				$setStr = implode(", ", $setColsList);
				$binds[] = $id_item;

				sqlStatementNoLog("UPDATE `vh_wordpress_sync_log` SET ".$setStr." WHERE id = ? ", $binds);
			}
		}
	}

}