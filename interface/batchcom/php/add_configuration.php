<?php
/**
 * REminder Tool for selecting/communicating with subsets of patients
 */

require_once("../../globals.php");
require_once("$srcdir/OemrAD/oemrad.globals.php");
require_once("$srcdir/wmt-v3/wmt.globals.php");

use OpenEMR\Core\Header;
use OpenEMR\OemrAd\Reminder;
use OpenEMR\OemrAd\IdempiereWebservice;
use OpenEMR\OemrAd\InmomentWebservice;
use OpenEMR\OemrAd\WordpressWebservice;

$pid = strip_tags($_REQUEST['pid']);
$type = strip_tags($_REQUEST['trigger_type']);
$actionType = strip_tags($_REQUEST['action_type']);
$config_id = $_REQUEST['config_id'];
$action_mode = isset($_REQUEST['action_mode']) ? $_REQUEST['action_mode'] : "add";


function generateQtrStr($paramStr = array()) {
	$current_qtrStr = $_SERVER['QUERY_STRING'];
	parse_str($current_qtrStr, $tmp_qtrStr_array);

	$qtrStr_array = array();
	$param_list = array('pid', 'trigger_type', 'selectedItem', 'action_type', 'eId');

	foreach ($tmp_qtrStr_array as $param => $value) {
		if(in_array($param, $param_list)) {
			$qtrStr_array[$param] = $value;
		}
	}

	if(is_array($paramStr)) {
		foreach ($paramStr as $param => $val) {
			$qtrStr_array[$param] = $val;
		}
	}
	return http_build_query($qtrStr_array);
}

function generateFullUrl($url, $paramStr = array()) {
	return $url ."?". generateQtrStr($paramStr);
}

// Option lists
function getEmailTpl() {
	$tpl_list = new wmt\Options('Reminder_Email_Messages');
	$msgtpl = $tpl_list->getOptionsWithTitle('free_text');
	$htmlStr = preg_replace( "/\r|\n/", "", $msgtpl );
	return $htmlStr;
}

function getSmsTpl() {
	$tpl_list = new wmt\Options('Reminder_SMS_Messages');
	$msgtpl = $tpl_list->getOptionsWithTitle('free_text');
	$htmlStr = preg_replace( "/\r|\n/", "", $msgtpl );
	return $htmlStr;
}

function getFaxTpl() {
	$tpl_list = new wmt\Options('Reminder_Fax_Messages');
	$msgtpl = $tpl_list->getOptionsWithTitle('free_text');
	$htmlStr = preg_replace( "/\r|\n/", "", $msgtpl );
	return $htmlStr;
}

function getPostalTpl() {
	$tpl_list = new wmt\Options('Reminder_Postal_Letters');
	$msgtpl = $tpl_list->getOptionsWithTitle('free_text');
	$htmlStr = preg_replace( "/\r|\n/", "", $msgtpl );
	return $htmlStr;
}

function getInternalMsgTpl() {
	$tpl_list = new wmt\Options('Reminder_Internal_Messages');
	$msgtpl = $tpl_list->getOptionsWithTitle('free_text');
	$htmlStr = preg_replace( "/\r|\n/", "", $msgtpl );
	return $htmlStr;
}

function getDefaultTpl() {
	$htmlStr = "<option value=''>Select Template</option>";
	return $htmlStr;
}

$seq_no = isset($_REQUEST['seq_no']) ? $_REQUEST['seq_no'] : "";
$api_config = isset($_REQUEST['api_config']) ? $_REQUEST['api_config'] : "";
$sync_mode = isset($_REQUEST['sync_mode']) ? $_REQUEST['sync_mode'] : "";
$data_query = isset($_REQUEST['data_query']) ? $_REQUEST['data_query'] : "";
$batch_size = isset($_REQUEST['batch_size']) ? $_REQUEST['batch_size'] : "";
$request_template = isset($_REQUEST['request_template']) ? $_REQUEST['request_template'] : "";
$request_timeout = isset($_REQUEST['request_timeout']) ? $_REQUEST['request_timeout'] : "";
$communication_type = isset($_REQUEST['communication_type']) ? $_REQUEST['communication_type'] : "";
$notification_template = isset($_REQUEST['notification_template']) ? $_REQUEST['notification_template'] : "";
$patient_form = isset($_REQUEST['patient_form']) ? $_REQUEST['patient_form'] : "";
$data_set = isset($_REQUEST['data_set']) ? $_REQUEST['data_set'] : "";
$trigger_type = !empty($type) ? $type : "";
$action_type = !empty($actionType) ? $actionType : "";
$time_trigger_data = isset($_REQUEST['time_trigger_data']) ? $_REQUEST['time_trigger_data'] : "";
$event_trigger = isset($_REQUEST['event_trigger']) ? $_REQUEST['event_trigger'] : "";

$timeData = isset($time_trigger_data) ? json_decode($time_trigger_data, true) : array();
$timeTitle = isset($timeData['title']) ? $timeData['title'] : "";

// If we are saving, then save.
if (isset($_POST['formaction']) && $_POST['formaction'] == 'save') {
	$savedata = array();
	$savedata['id'] = isset($_POST['config_id']) ? $_POST['config_id'] : "";
	$savedata['seq'] = isset($_POST['seq_no']) ? $_POST['seq_no'] : "";
	$savedata['api_config'] = isset($_POST['api_config']) ? $_POST['api_config'] : "";
	$savedata['sync_mode'] = isset($_POST['sync_mode']) ? $_POST['sync_mode'] : "";
	$savedata['data_query'] = isset($_POST['data_query']) ? $_POST['data_query'] : "";
	$savedata['batch_size'] = isset($_POST['batch_size']) ? $_POST['batch_size'] : "";
	$savedata['request_timeout'] = isset($_POST['request_timeout']) ? $_POST['request_timeout'] : "";
	$savedata['request_template'] = isset($_POST['request_template']) ? base64_encode($_POST['request_template']) : "";
	$savedata['communication_type'] = isset($_POST['communication_type']) ? $_POST['communication_type'] : "";
	$savedata['notification_template'] = isset($_POST['notification_template']) ? $_POST['notification_template'] : "";
	$savedata['patient_form'] = isset($_POST['patient_form']) ? $_POST['patient_form'] : "";
	$savedata['data_set'] = isset($_POST['data_set']) ? $_POST['data_set'] : "";
	$savedata['trigger_type'] = isset($_POST['trigger_type']) ? $_POST['trigger_type'] : "";
	$savedata['action_type'] = isset($_POST['action_type']) ? $_POST['action_type'] : "";
	$savedata['time_trigger_data'] = isset($_POST['time_trigger_data']) ? $_POST['time_trigger_data'] : "";
	$savedata['event_trigger'] = isset($_POST['event_trigger']) ? $_POST['event_trigger'] : "";
	$savedata['time_delay'] = isset($_POST['time_delay']) ? $_POST['time_delay'] : "0";
	$savedata['test_mode'] = isset($_POST['test_mode']) ? $_POST['test_mode'] : 0;
	$savedata['to_user'] = isset($_POST['to_user']) ? $_POST['to_user'] : "";
	$savedata['pre_processing_data_set'] = isset($_POST['pre_processing_data_set']) ? $_POST['pre_processing_data_set'] : "";

	$isConfigurationExist = Reminder::isConfigurationExist($savedata['id']);
	$isDataEmpty = Reminder::isDataEmpty($savedata);

	$actionResponce = false;
	
	if($isDataEmpty === true) {
		?>
		<script type="text/javascript">
			setTimeout(function() {alert('Please enter value for required fields.') }, 500);
		</script>
		<?php
	}
	
	if($action_mode == "add") {
		if($isConfigurationExist === false && $isDataEmpty === false) {
			//Insert Data
			$actionResponce = Reminder::saveNotificationConfiguration($savedata);
		}

		if($isConfigurationExist !== false){
			?>
			<script type="text/javascript">
				setTimeout(function() {alert('Configuration already exists with same event name.') }, 500);
			</script>
			<?php
		}

	} else if($action_mode == "update") {
		if($isDataEmpty === false) {
			
			$cid = $savedata['id'];
			unset($savedata['id']);
			
			//Insert Data
			$actionResponce = Reminder::updateNotificationConfiguration($cid, $savedata);
		}
	}

	if($actionResponce === true) {
		$redirectUrl = generateFullUrl($GLOBALS['webroot']."/interface/batchcom/php/time_configuration_list.php");
		?>
		<script type="text/javascript">
			window.location='<?php echo $redirectUrl; ?>';
		</script>
		<?php
	}

} else if(!empty($config_id) && !isset($_POST['formaction'])) {
	//Fetch configuration
	$configurationData = Reminder::getNotificationConfiguration($config_id);

	if(!empty($configurationData) && count($configurationData) > 0) {
		$configData = $configurationData[0];
		
		$config_id = isset($configData['id']) ? $configData['id'] : "";
		$seq_no = isset($configData['seq']) ? $configData['seq'] : "";
		$api_config = isset($configData['api_config']) ? $configData['api_config'] : "";
		$sync_mode = isset($configData['sync_mode']) ? $configData['sync_mode'] : "";
		$data_query = isset($configData['data_query']) ? $configData['data_query'] : "";
		$batch_size = isset($configData['batch_size']) ? $configData['batch_size'] : "";
		$request_template = isset($configData['request_template']) ? base64_decode($configData['request_template']) : "";
		$request_timeout = isset($configData['request_timeout']) ? $configData['request_timeout'] : "";
		$communication_type = isset($configData['communication_type']) ? $configData['communication_type'] : "";
		$notification_template = isset($configData['notification_template']) ? $configData['notification_template'] : "";
		$patient_form = isset($configData['patient_form']) ? $configData['patient_form'] : "";
		$data_set = isset($configData['data_set']) ? $configData['data_set'] : "";
		$trigger_type = isset($configData['trigger_type']) ? $configData['trigger_type'] : "";
		$action_type = isset($configData['action_type']) ? $configData['action_type'] : "";
		$time_trigger_data = isset($configData['time_trigger_data']) ? $configData['time_trigger_data'] : "";
		$event_trigger = isset($configData['event_trigger']) ? $configData['event_trigger'] : "";
	
		$timeData = isset($time_trigger_data) ? json_decode($time_trigger_data, true) : array();
		$timeTitle = isset($timeData['title']) ? $timeData['title'] : "";
		$time_delay = isset($configData['time_delay']) ? $configData['time_delay'] : "";
		$test_mode = isset($configData['test_mode']) ? $configData['test_mode'] : "0";
		$to_user = isset($configData['to_user']) ? $configData['to_user'] : "";
		$pre_processing_data_set = isset($configData['pre_processing_data_set']) ? $configData['pre_processing_data_set'] : "";
	}
}

$communication_type_List = array(
	'' => 'Select',
	'email' => 'Email',
	'sms' => 'SMS',
	'fax' => 'Fax',
	'postalmethod' => 'Postal Method',
	'internalmessage' => 'Internal Message'
);

$int_msg_communication_type_List = array(
	'' => 'Select',
	'email' => 'Email'
);

$trigger_type_List = array(
	'' => 'Select',
	'time' => 'Time',
	'event' => 'Trigger'
);

$test_mode_List = array(
	'1' => 'True',
	'0' => 'False'
);

?>
<html>
<head>
	<title><?php echo htmlspecialchars( xl('Select Configuration'), ENT_NOQUOTES); ?></title>
	<link rel="stylesheet" href='<?php echo $css_header ?>' type='text/css'>
	<?php Header::setupHeader(['opener', 'dialog', 'jquery', 'jquery-ui', 'jquery-ui-base', 'fontawesome', 'main-theme', 'datetime-picker']); ?>

    <script language="JavaScript">

	 function selTime(id, data) {
		if (opener.closed || ! opener.setTime)
		alert("<?php echo htmlspecialchars( xl('The destination form was closed; I cannot act on your selection.'), ENT_QUOTES); ?>");
		else
		opener.setTime(id, data);
		window.close();
		return false;
	 }

    </script>

    <style type="text/css">
    	.addform_table {
    		width: 100%;
    		max-width: 500px;
    		border-collapse: separate;
    		border-spacing: 0 1em;
    	}
    	.addformContainer {
    		padding: 15px;
    		margin-top: 0px;
    	}
    	.titleTd {
    		vertical-align: middle;
    	}
    	.btnContainer {
    		margin-top: 10px;
    	}
		input[type="text"] {
			border: 1px solid #ccc;
		}
		.control-label:after {
		  content:"*";
		  color:red;
		}
		.apiConfigFieldContainer {
			display: grid;
			grid-template-columns: 1fr auto;
			grid-column-gap: 10px;
		}

		.apiConfigFieldContainer button {
    		margin: 3px;
		}
    </style>
</head>
<body>
	<div class="addformContainer">
	<form method='post' name='addform' id='addform' action="<?php echo generateFullUrl('add_configuration.php'); ?>">
		<table class="addform_table">
			<tr>
				<td class="" width="180" valign="top"><span class="control-label"><b><?php echo xlt('Event Name'); ?>:&nbsp;</b></span></td>
				<td>
					<input type="hidden" name="action_mode" class="optin form-control" value="<?php echo htmlspecialchars($action_mode, ENT_QUOTES); ?>" />
					<input type="hidden" name="action_type" class="optin form-control" value="<?php echo htmlspecialchars($action_type, ENT_QUOTES); ?>" />
					<?php if(!empty($trigger_type)) { ?>
					<input type="hidden" name="trigger_type" value="<?php echo htmlspecialchars($trigger_type, ENT_QUOTES); ?>" />
					<?php } ?>
					<input type="text" name="config_id" class="optin form-control" value="<?php echo htmlspecialchars($config_id, ENT_QUOTES); ?>" <?php echo $action_mode == "update" ? "readonly" : "" ?> />
				</td>
			</tr>
			<?php if($action_type == "idempiere_webservice" || $action_type === "inmoment" || $action_type === "wordpress") { ?>
			<tr>
				<td class="" width="180" valign="top"><span class="control-label"><b><?php echo xlt('Seq'); ?>:&nbsp;</b></span></td>
				<td>
					<input type="text" name="seq_no" class="optin form-control" value="<?php echo htmlspecialchars($seq_no, ENT_QUOTES); ?>"/>
				</td>
			</tr>
			<?php } ?>
			<?php if($action_type === "idempiere_webservice" || $action_type === "hubspot_sync") { 
				$title_text = IdempiereWebservice::getApiConfigTitle($api_config);
			?>
				<tr>
					<td class="" width="180" valign="top"><span class="control-label"><b><?php echo xlt('API Config'); ?>:&nbsp;</b></span></td>
					<td>
						<div class="apiConfigFieldContainer">
							<div>
								<input type="hidden" name="api_config" value="<?php echo htmlspecialchars($api_config, ENT_QUOTES); ?>">
								<input type="text" class="form-control" name="api_config_text" value="<?php echo htmlspecialchars($title_text, ENT_QUOTES); ?>" disabled="disabled">
							</div>
							<div>
								<button type="button" class="btn btn-primary" onClick="apiConfiguration('<?php echo $action_type; ?>')">Edit</button>
							</div>
						</div>	
					</td>
				</tr>
			<?php } ?>

			<?php if($action_type === "inmoment") { 
				$title_text = InmomentWebservice::getApiConfigTitle($api_config);
			?>
				<tr>
					<td class="" width="180" valign="top"><span class="control-label"><b><?php echo xlt('API Config'); ?>:&nbsp;</b></span></td>
					<td>
						<div class="apiConfigFieldContainer">
							<div>
								<input type="hidden" name="api_config" value="<?php echo htmlspecialchars($api_config, ENT_QUOTES); ?>">
								<input type="text" class="form-control" name="api_config_text" value="<?php echo htmlspecialchars($title_text, ENT_QUOTES); ?>" disabled="disabled">
							</div>
							<div>
								<button type="button" class="btn btn-primary" onClick="apiConfiguration('<?php echo $action_type; ?>')">Edit</button>
							</div>
						</div>	
					</td>
				</tr>
			<?php } ?>

			<?php if($action_type === "wordpress") { 
				$title_text = WordpressWebservice::getApiConfigTitle($api_config);
			?>
				<tr>
					<td class="" width="180" valign="top"><span class="control-label"><b><?php echo xlt('API Config'); ?>:&nbsp;</b></span></td>
					<td>
						<div class="apiConfigFieldContainer">
							<div>
								<input type="hidden" name="api_config" value="<?php echo htmlspecialchars($api_config, ENT_QUOTES); ?>">
								<input type="text" class="form-control" name="api_config_text" value="<?php echo htmlspecialchars($title_text, ENT_QUOTES); ?>" disabled="disabled">
							</div>
							<div>
								<button type="button" class="btn btn-primary" onClick="apiConfiguration('<?php echo $action_type; ?>')">Edit</button>
							</div>
						</div>	
					</td>
				</tr>
			<?php } ?>

			<?php if($action_type !== "idempiere_webservice" && $action_type !== "hubspot_sync" && $action_type !== "inmoment" && $action_type !== "wordpress") { ?>
			<tr>
				<td class="titleTd" width="180"><span class="control-label"><b><?php echo xlt('Communication Type'); ?>:&nbsp;</b></span></td>
				<td>
					<?php if(!empty($actionType) && $actionType == "internal_messaging") { ?>
						<select id="communication_type" name="communication_type" class=" communication_type form-control">
							<?php
							foreach ($int_msg_communication_type_List as $key => $desc) {
					            ?>
					            <option value="<?php echo $key ?>" <?php echo ($key == $communication_type) ? "selected" : ""; ?> ><?php echo htmlspecialchars($desc) ?></option>
					            <?php
					        }
							?>
						</select>
					<?php } else { ?>
						<select id="communication_type" name="communication_type" class=" communication_type form-control">
							<?php
							foreach ($communication_type_List as $key => $desc) {
					            ?>
					            <option value="<?php echo $key ?>" <?php echo ($key == $communication_type) ? "selected" : ""; ?> ><?php echo htmlspecialchars($desc) ?></option>
					            <?php
					        }
							?>
						</select>
					<?php } ?>
				</td>
			</tr>
			<?php } ?>
			<?php if($action_type !== "idempiere_webservice" && $action_type !== "hubspot_sync" && $action_type !== "inmoment" && $action_type !== "wordpress") { ?>
			<tr>
				<td class="titleTd" width="180"><span class="control-label"><b><?php echo xlt('Template'); ?>:&nbsp;</b></span></td>
				<td>
					<select name="notification_template" id="notification_template" class="optin notification_template form-control">
						<?php echo getDefaultTpl() ?>
					</select>
					<script type="text/javascript">
						$(document).ready(function(){
							setOptionVal('<?php echo $communication_type; ?>', '<?php echo $notification_template; ?>');
						});
					</script>
				</td>
			</tr>
			<?php } ?>
			<?php if($action_type == "messaging") { ?>
			<tr>
				<td class="titleTd" width="180"><span class="control-label"><b><?php echo xlt('Patient Form'); ?>:&nbsp;</b></span></td>
				<td>
					<select name="patient_form" id="patient_form" class="optin patient_form form-control">
						<option value="">Please Select</option>
					</select>
					<script type="text/javascript">
						$(document).ready(function() {
							setPatientTemplate('<?php echo $patient_form; ?>');
						});
					</script>
				</td>
			</tr>	
			<?php } ?>	
			<?php if(!empty($actionType) && $actionType == "internal_messaging") { ?>
			<tr>
				<td class="titleTd" width="180"><b><?php echo xlt('Emails'); ?>:&nbsp;</b></td>
				<td>
					<input type="text" name="to_user" value="<?php echo htmlspecialchars($to_user, ENT_QUOTES); ?>" class="form-control" />
				</td>
			</tr>
			<?php } ?>
			<?php if(empty($trigger_type) || ($trigger_type == "time" || ($trigger_type == "event" && $actionType == "internal_messaging"))) { ?>
			<tr>
				<td class="" width="180" valign="top"><span class="control-label"><b><?php echo xlt('Data Set'); ?>:&nbsp;</b></span></td>
				<td>
					<textarea name="data_set" class="form-control" style="height: 250px; min-height: 30px; resize: vertical;"><?php echo $data_set; ?></textarea>
				</td>
			</tr>
			<?php } ?>
			<tr>
				<td class="" width="180" valign="top"><b><?php echo xlt('Pre Processing'); ?>:&nbsp;</b></td>
				<td>
					<textarea name="pre_processing_data_set" class="form-control" style="height: 80px; min-height: 30px; resize: vertical;"><?php echo $pre_processing_data_set; ?></textarea>
				</td>
			</tr>
			<?php if($action_type === "hubspot_sync") { ?>
				<tr>
					<td class="" width="180" valign="top"><span><b><?php echo xlt('Sync Mode'); ?>:&nbsp;</b></span></td>
					<td>
						<select name="sync_mode" id="sync_mode" class="optin sync_mode form-control">
							<option value="0" <?php echo ("0" == $sync_mode) ? "selected" : ""; ?>>Out</option>
							<option value="1" <?php echo ("1" == $sync_mode) ? "selected" : ""; ?>>In</option>
						</select>
					</td>
				</tr>
			<?php } ?>
			<?php if(($action_type === "idempiere_webservice" || $action_type === "inmoment" || $action_type === "wordpress") || ($action_type === "messaging" && $communication_type == "internalmessage") || ($action_type === "hubspot_sync")) { ?>
				<tr>
					<td class="" width="180" valign="top"><span><b><?php echo xlt('Data Query'); ?>:&nbsp;</b></span></td>
					<td>
						<textarea name="data_query" class="form-control" style="height: 80px; min-height: 30px; resize: vertical;"><?php echo $data_query; ?></textarea>
					</td>
				</tr>
			<?php } ?>
			<?php if($action_type === "idempiere_webservice") { ?>
				<tr>
					<td class="" width="180" valign="top"><span><b><?php echo xlt('Batch Size'); ?>:&nbsp;</b></span></td>
					<td>
						<input type="text" name="batch_size" class="optin form-control" value="<?php echo htmlspecialchars($batch_size, ENT_QUOTES); ?>"/>
					</td>
				</tr>
			<?php } ?>
			<?php if($action_type === "idempiere_webservice" || ($action_type === "messaging" && $communication_type == "internalmessage") || $action_type === "hubspot_sync" || $action_type === "inmoment" || $action_type === "wordpress") { ?>
				<tr>
					<td class="" width="180" valign="top"><span><b><?php echo xlt('Request Template'); ?>:&nbsp;</b></span></td>
					<td>
						<textarea name="request_template" class="form-control" style="height: 80px; min-height: 30px; resize: vertical;"><?php echo $request_template; ?></textarea>
					</td>
				</tr>
			<?php } ?>
			<?php if($action_type === "idempiere_webservice" || $action_type === "hubspot_sync" || $action_type === "inmoment" || $action_type === "wordpress") { ?>
				<tr>
					<td class="" width="180" valign="top"><span><b><?php echo xlt('Request Timeout'); ?>:&nbsp;</b></span></td>
					<td>
						<input type="text" name="request_timeout" class="optin form-control" value="<?php echo htmlspecialchars($request_timeout, ENT_QUOTES); ?>"/>
					</td>
				</tr>
			<?php } ?>
			<?php if(empty($trigger_type)) { ?>
			<tr>
				<td class="titleTd" width="180"><span class="control-label"><b><?php echo xlt('Trigger Type'); ?>:&nbsp;</b></span></td>
				<td>
					<select name="trigger_type" class="form-control">
						<?php
						foreach ($trigger_type_List as $key => $desc) {
				            ?>
				            <option value="<?php echo $key ?>" <?php echo ($key == $trigger_type) ? "selected" : ""; ?> ><?php echo htmlspecialchars($desc) ?></option>
				            <?php
				        }
						?>
					</select>
				</td>
			</tr>
			<?php } ?>
			<?php if(empty($trigger_type) || $trigger_type == "time") { ?>
			<tr>
				<td class="titleTd" width="180"><span class="control-label"><b><?php echo xlt('Time Trigger'); ?>:&nbsp;</b></span></td>
				<td>
					<input type="text" id="time_trigger" name="time_trigger" value="<?php echo htmlspecialchars($timeTitle, ENT_QUOTES); ?>" class="form-control time_trigger" onClick="select_time('time_trigger')" readonly />
					<textarea id="time_trigger_data" name="time_trigger_data" style="display: none;"><?php echo $time_trigger_data; ?></textarea>
				</td>
			</tr>
			<?php } ?>
			<?php if($action_type !== "hubspot_sync") { ?>
			<?php if(empty($trigger_type) || $trigger_type == "event") { ?>
			<tr>
				<td class="titleTd" width="180"><span class="control-label"><b><?php echo xlt('Trigger'); ?>:&nbsp;</b></span></td>
				<td>
					<input type="text" name="event_trigger" value="<?php echo htmlspecialchars($event_trigger, ENT_QUOTES); ?>" class="form-control" />
				</td>
			</tr>
			<?php } ?>
			<?php } ?>
			<tr>
				<td class="titleTd" width="180"><b><?php echo xlt('Time Delay (Seconds)'); ?>:&nbsp;</b></td>
				<td>
					<input type="text" name="time_delay" value="<?php echo htmlspecialchars($time_delay, ENT_QUOTES); ?>" class="form-control" />
				</td>
			</tr>
			<?php if($action_type === "messaging") { ?>
				<tr>
					<td class="titleTd" width="180"><b><?php echo xlt('Test Mode'); ?>:&nbsp;</b></td>
					<td>
						<select id="test_mode" name="test_mode" class="test_mode form-control">
							<?php
								foreach ($test_mode_List as $key => $desc) {
					         ?>
					            <option value="<?php echo $key ?>" <?php echo ($key == $test_mode) ? "selected" : ""; ?> ><?php echo htmlspecialchars($desc) ?></option>
					        <?php
					        	}
							?>
						</select>
					</td>
				</tr>
			<?php } ?>
			<tr>
				<td></td>
				<td>
					<div class="btnContainer">
						<button type="submit" name="formaction" class="btn btn-primary" value="save">Save</button>
						<button type="submit" name="formaction" class="btn btn-primary" id="refresh_action" value="refresh" style="display: none;"></button>
				
						<?php
							$cancelUrl = generateFullUrl($GLOBALS['webroot']."/interface/batchcom/php/time_configuration_list.php");
						?>
						<button type="button" class="btn btn-primary" onclick="window.location='<?php echo $cancelUrl; ?>';">Cancel</button>
					</div>
				</td>
			</tr>
		</table>
	</form>
	</div>
	<script type="text/javascript">
		// This invokes the select-time popup.
		function select_time(id = '') {
			var timeData = $("#time_trigger_data").text();

			var url = '<?php echo $GLOBALS['webroot']."/interface/batchcom/php/select_time.php?"; ?>&id='+id+'&data_set='+encodeURI(timeData);
		  	let title = '<?php echo xlt('Time'); ?>';
		  	dlgopen(url, 'timeSelection', 900, 400, '', title);
		}

		// This is for callback by the time popup.
		function setTime(id, data) {
			$('#'+id).val(data['title']);
			$('#time_trigger_data').text(JSON.stringify(data));
		}

		$(document).ready(function(){
			$('.communication_type').change(function(){
				var selectedVal = $(this).val();
				var id = 'notification_template';
				setOptionVal(selectedVal);

				//if(selectedVal == "internalmessage") {
					$('#refresh_action').click();
				//}
			});
		});

		//Set Option
		function setOptions(val, id) {
			if(val == "email") {
				$('#'+id).html("<?php echo getEmailTpl(); ?>");
			} else if(val == "sms") {
				$('#'+id).html("<?php echo getSmsTpl(); ?>");
			} else if(val == "fax") {
				$('#'+id).html("<?php echo getFaxTpl(); ?>");
			} else if(val == "postalmethod") {
				$('#'+id).html("<?php echo getPostalTpl(); ?>");
			} else if(val == "internalmessage") {
				$('#'+id).html("<?php echo getInternalMsgTpl(); ?>");
			} else {
				$('#'+id).html("<?php echo getDefaultTpl(); ?>");
			}
		}

		//Init
		function setOptionVal(val, selectVal = '') {
			var id = 'notification_template';
			setOptions(val, id);
			if(selectVal != "") {
				$('#'+id).val(selectVal);
			}
		}

		function apiConfiguration(api_type) {
			var api_config = $('input[name="api_config"]').val();
			var selectedItemJson = [];

			var selectedItemJson = (api_config && api_config != "") ? JSON.stringify([api_config]) : JSON.stringify([]);
			var url = encodeURI('<?php echo $GLOBALS['webroot']."/interface/batchcom/php/api_configuration_list.php?pid=". $pid ."&api_type="; ?>'+api_type+"&selectedItem="+selectedItemJson);
		  	let title = '<?php echo xlt('Api Configuration'); ?>';
		  	dlgopen(url, 'apiConfigurationSelection', 1200, 400, '', title);
		}

		function setApiConfigId(id) {
			if(id && Array.isArray(id) && id.length > 0) {
				var value = (id[0] && id[0]['value']) ? id[0]['value'] : "";
				var title = (id[0] && id[0]['title']) ? id[0]['title'] : "";

				$('input[name="api_config"]').val(value);
				$('input[name="api_config_text"]').val(title);
			} else {
				$('input[name="api_config"]').val("");
				$('input[name="api_config_text"]').val("");
			}
		}

		async function setPatientTemplate(defVal = '') {
			const templateData = await $.ajax({
                type: "POST",
                url: top.webroot_url + "/interface/modules/custom_modules/oe-module-vitalhealthcare-generic/interface/portal/lib/ajax_form.php",
                dataType: 'json',
                data: { 'form_action' : 'patient_template' },
                success: async function (data) {
                    $("#patient_form").html(data['content']);

                    if(defVal != '') {
                    	$("#patient_form").val(defVal);
                    }
                }
            });
		}	
	</script>
</body>
</html>