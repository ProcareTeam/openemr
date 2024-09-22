<?php


require_once("../../globals.php");
require_once("$srcdir/OemrAD/oemrad.globals.php");

use OpenEMR\Core\Header;
use OpenEMR\OemrAd\Reminder;
use OpenEMR\OemrAd\IdempiereWebservice;
use OpenEMR\OemrAd\InmomentWebservice;
use OpenEMR\OemrAd\WordpressWebservice;

$pid = strip_tags($_REQUEST['pid']);
$config_id = $_REQUEST['config_id'];
$api_type = $_REQUEST['api_type'];
$action_mode = isset($_REQUEST['action_mode']) ? $_REQUEST['action_mode'] : "add";

$api_configuration_type_List = IdempiereWebservice::getApiConfigurationTypeList();
$api_configuration_type_List1 = InmomentWebservice::getApiConfigurationTypeList();
$api_configuration_type_List2 = WordpressWebservice::getApiConfigurationTypeList();

$api_configuration_type_List = array_merge($api_configuration_type_List, $api_configuration_type_List1, $api_configuration_type_List2);


$idempiere_webservice_fields_list = array( 
	'idempiere_webservice' => array(
		'user',
		'password',
		'role',
		'organization',
		'client',
		'service_url',
		'warehouse'
	),
	'inmoment' => array(
		'auth_type',
		'api_url'
	),
	'wordpress' => array(
		'api_url',
		'api_token'
	)
);

function generateQtrStr($paramStr = array()) {
	$current_qtrStr = $_SERVER['QUERY_STRING'];
	parse_str($current_qtrStr, $tmp_qtrStr_array);

	$qtrStr_array = array();
	$param_list = array('pid', 'selectedItem', 'api_type');

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

function isValueEmpty($value) {
	return (empty($value) && $value != '0') ? true : false;
}

function isDataEmpty($data) {
	global $idempiere_webservice_fields_list;

	$isEmpty = false;

	// if(isValueEmpty($data['config_id'] ? $data['config_id'] : "") === true) {
	// 	$isEmpty = true;
	// }

	if(isValueEmpty($data['api_configuration_type'] ? $data['api_configuration_type'] : "") === true) {
		$isEmpty = true;
	} else {
		if($data['api_configuration_type'] === $data['api_configuration_type']) {
			if(isset($idempiere_webservice_fields_list[$data['api_configuration_type']])) {
				foreach($idempiere_webservice_fields_list[$data['api_configuration_type']] as $i => $field_item) {
					if(isValueEmpty(isset($data[$field_item]) ? $data[$field_item] : "") === true) {
						$isEmpty = true;
					}
				}
			}
		} 
	}
	return $isEmpty;
}

function extractValue($data, $list = array()) {
	$fieldValues = array();

	foreach ($list as $key => $field) {
		$fieldValues[$field] = isset($data[$field]) ? $data[$field] : "";
	}

	return $fieldValues;
}

$api_configuration_type = isset($_REQUEST['api_configuration_type']) ? $_REQUEST['api_configuration_type'] : "";
$configValData = array(
	"api_configuration_type" => ""
);

if(!empty($api_configuration_type)) {
	$configValData = extractValue($_REQUEST, $idempiere_webservice_fields_list[$api_configuration_type] ? $idempiere_webservice_fields_list[$api_configuration_type] : array());
}

// If we are saving, then save.
if (isset($_POST['formaction']) && $_POST['formaction'] == 'save') {
	$isConfigurationExist = (!empty($config_id)) ? true : false;
	$isDataEmpty = isDataEmpty($_POST);
	$actionResponce = false;

	if($isDataEmpty === true) {
		?>
		<script type="text/javascript">
			setTimeout(function() {alert('Please enter value for required fields.') }, 500);
		</script>
		<?php
	}

	$savedata = $_POST;
	if ($api_type == "inmoment") {

		if($action_mode == "add") {
			if($isConfigurationExist === false && $isDataEmpty === false) {
				$actionResponce = InmomentWebservice::saveApiEventConfiguration($savedata);
			}
		} else if($action_mode == "update") {
			if($isDataEmpty === false) {
				$actionResponce = InmomentWebservice::updateApiEventConfiguration($config_id, $savedata);
			}
		}
	} else if ($api_type == "wordpress") {
		if($action_mode == "add") {
			if($isConfigurationExist === false && $isDataEmpty === false) {
				$actionResponce = WordpressWebservice::saveApiEventConfiguration($savedata);
			}
		} else if($action_mode == "update") {
			if($isDataEmpty === false) {
				$actionResponce = WordpressWebservice::updateApiEventConfiguration($config_id, $savedata);
			}
		}
	} else {
		if($action_mode == "add") {
			if($isConfigurationExist === false && $isDataEmpty === false) {
				$actionResponce = IdempiereWebservice::saveApiEventConfiguration($savedata);
			}
		} else if($action_mode == "update") {
			if($isDataEmpty === false) {
				$actionResponce = IdempiereWebservice::updateApiEventConfiguration($config_id, $savedata);
			}
		}
	}

	if($actionResponce === true) {
		$redirectUrl = generateFullUrl($GLOBALS['webroot']."/interface/batchcom/php/api_configuration_list.php");
		?>
		<script type="text/javascript">
			window.location='<?php echo $redirectUrl; ?>';
		</script>
		<?php
	}

} else if(!empty($config_id)) {

	if ($api_type == "wordpress") {
		//Fetch configuration
		$configurationData = WordpressWebservice::getApiEventConfiguration($config_id, $api_configuration_type);
	} else if ($api_type == "inmoment") {
		//Fetch configuration
		$configurationData = InmomentWebservice::getApiEventConfiguration($config_id, $api_configuration_type);
	} else {
		//Fetch configuration
		$configurationData = IdempiereWebservice::getApiEventConfiguration($config_id, $api_configuration_type);
	}

	if(!empty($configurationData) && count($configurationData) > 0) {
		$configData = $configurationData[0];

		foreach ($configData as $config_key => $config_item) {
			if($_POST['formaction'] == 'refresh') {
				if($config_key != "api_configuration_type" && (!isset($configValData[$config_key]) || empty($configValData[$config_key]))) {
					$configValData[$config_key] = $config_item;
				}
			} else {
				$configValData[$config_key] = $config_item;
			}
		}
	}
}

if(isset($configValData)) {
	extract($configValData);
}

?>
<html>
<head>
	<title><?php echo htmlspecialchars( xl('Add Api Configuration'), ENT_NOQUOTES); ?></title>
	<link rel="stylesheet" href='<?php echo $css_header ?>' type='text/css'>
	<?php Header::setupHeader(['opener', 'dialog', 'jquery', 'jquery-ui', 'jquery-ui-base', 'fontawesome', 'main-theme', 'datetime-picker']); ?>

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
    </style>
</head>
<body>
	<div class="addformContainer">
		<form method='post' name='addform' id='addform' action="<?php echo generateFullUrl('add_api_configuration.php'); ?>">
			<input type="hidden" name="action_mode" class="optin form-control" value="<?php echo htmlspecialchars($action_mode, ENT_QUOTES); ?>" />
			<input type="hidden" name="config_id" class="optin form-control" value="<?php echo htmlspecialchars($config_id, ENT_QUOTES); ?>" />

			<input type="hidden" name="api_type" class="optin form-control" value="<?php echo htmlspecialchars($api_type, ENT_QUOTES); ?>" />

			<table class="addform_table">
				<tr>
					<td class="titleTd" width="180"><span class="control-label"><b><?php echo xlt('Api Configuration Type'); ?>:&nbsp;</b></span></td>
					<td>
						<select id="api_configuration_type" name="api_configuration_type" class=" api_configuration_type form-control">
							<?php
							foreach ($api_configuration_type_List as $key => $desc) {
					            ?>
					            <option value="<?php echo $key ?>" <?php echo ($key == $api_configuration_type) ? "selected" : ""; ?> ><?php echo htmlspecialchars($desc) ?></option>
					            <?php
					        }
							?>
						</select>
					</td>
				</tr>
				<?php if($api_configuration_type == "hubspot_sync") { ?>
				<tr>
					<td class="titleTd" width="220"><span class="control-label"><b><?php echo xlt('Token'); ?>:&nbsp;</b></span></td>
					<td>
						<input type="text" name="token" value="<?php echo htmlspecialchars($token, ENT_QUOTES); ?>" class="form-control" />
					</td>
				</tr>
				<?php } ?>
				<?php if($api_configuration_type == "idempiere_webservice") { ?>
				<tr>
					<td class="titleTd" width="220"><span class="control-label"><b><?php echo xlt('User'); ?>:&nbsp;</b></span></td>
					<td>
						<input type="text" name="user" value="<?php echo htmlspecialchars($user, ENT_QUOTES); ?>" class="form-control" />
					</td>
				</tr>
				<tr>
					<td class="titleTd" width="220"><span class="control-label"><b><?php echo xlt('Password'); ?>:&nbsp;</b></span></td>
					<td>
						<input type="password" name="password" value="<?php echo htmlspecialchars($password, ENT_QUOTES); ?>" class="form-control" />
					</td>
				</tr>
				<tr>
					<td class="titleTd" width="220"><span class="control-label"><b><?php echo xlt('role'); ?>:&nbsp;</b></span></td>
					<td>
						<input type="text" name="role" value="<?php echo htmlspecialchars($role, ENT_QUOTES); ?>" class="form-control" />
					</td>
				</tr>
				<tr>
					<td class="titleTd" width="220"><span class="control-label"><b><?php echo xlt('Organization'); ?>:&nbsp;</b></span></td>
					<td>
						<input type="text" name="organization" value="<?php echo htmlspecialchars($organization, ENT_QUOTES); ?>" class="form-control" />
					</td>
				</tr>
				<tr>
					<td class="titleTd" width="220"><span class="control-label"><b><?php echo xlt('Client'); ?>:&nbsp;</b></span></td>
					<td>
						<input type="text" name="client" value="<?php echo htmlspecialchars($client, ENT_QUOTES); ?>" class="form-control" />
					</td>
				</tr>
				<tr>
					<td class="titleTd" width="220"><span class="control-label"><b><?php echo xlt('Service Url'); ?>:&nbsp;</b></span></td>
					<td>
						<input type="text" name="service_url" value="<?php echo htmlspecialchars($service_url, ENT_QUOTES); ?>" class="form-control" />
					</td>
				</tr>
				<tr>
					<td class="titleTd" width="220"><span class="control-label"><b><?php echo xlt('Warehouse'); ?>:&nbsp;</b></span></td>
					<td>
						<input type="text" name="warehouse" value="<?php echo htmlspecialchars($warehouse, ENT_QUOTES); ?>" class="form-control" />
					</td>
				</tr>
				<?php } ?>

				<?php if($api_configuration_type == "wordpress") { ?>

				<tr>
					<td class="titleTd" width="220"><span class="control-label"><b><?php echo xlt('API Url'); ?>:&nbsp;</b></span></td>
					<td>
						<input type="text" name="api_url" value="<?php echo htmlspecialchars($api_url, ENT_QUOTES); ?>" class="form-control" />
					</td>
				</tr>

				<tr>
					<td class="titleTd" width="220"><span class="control-label"><b><?php echo xlt('API Token'); ?>:&nbsp;</b></span></td>
					<td>
						<input type="text" name="api_token" value="<?php echo htmlspecialchars($api_token, ENT_QUOTES); ?>" class="form-control" />
					</td>
				</tr>

				<?php } else if($api_configuration_type == "inmoment") { ?>
				<tr>
					<td class="titleTd" width="220"><span class="control-label"><b><?php echo xlt('Auth Type'); ?>:&nbsp;</b></span></td>
					<td>
						<select id="auth_type" name="auth_type" class=" form-control">
							<option value="oauth" <?php echo $auth_type == "oauth" ? "selected" : ""; ?>><?php echo xlt('OAuth'); ?></option>
						</select>
					</td>
				</tr>

				<tr>
					<td class="titleTd" width="220"><span class="control-label"><b><?php echo xlt('Username'); ?>:</b></span></td>
					<td>
						<input type="text" name="username" value="<?php echo htmlspecialchars($username, ENT_QUOTES); ?>" class="form-control" />
					</td>
				</tr>
				<tr>
					<td class="titleTd" width="220"><span class="control-label"><b><?php echo xlt('Password'); ?>:</b></span></td>
					<td>
						<input type="password" name="password" value="<?php echo htmlspecialchars($password, ENT_QUOTES); ?>" class="form-control" />
					</td>
				</tr>
				<tr>
					<td class="titleTd" width="220"><span class="control-label"><b><?php echo xlt('Client Id'); ?>:</b></span></td>
					<td>
						<input type="text" name="client_id" value="<?php echo htmlspecialchars($client_id, ENT_QUOTES); ?>" class="form-control" />
					</td>
				</tr>
				<tr>
					<td class="titleTd" width="220"><span class="control-label"><b><?php echo xlt('Client Secret'); ?>:</b></span></td>
					<td>
						<input type="text" name="client_secret" value="<?php echo htmlspecialchars($client_secret, ENT_QUOTES); ?>" class="form-control" />
					</td>
				</tr>
				<tr>
					<td class="titleTd" width="220"><span class="control-label"><b><?php echo xlt('API Url'); ?>:&nbsp;</b></span></td>
					<td>
						<input type="text" name="api_url" value="<?php echo htmlspecialchars($api_url, ENT_QUOTES); ?>" class="form-control" />
					</td>
				</tr>
				<?php } ?>
				<tr>
					<td></td>
					<td>
						<div class="btnContainer">
							<button type="submit" name="formaction" class="btn btn-primary" value="save">Save</button>
							<button type="submit" id="refreshbtn" class="btn btn-primary" name="formaction" value="refresh" style="display:none;">Save</button>
					
							<?php
								$cancelUrl = generateFullUrl($GLOBALS['webroot']."/interface/batchcom/php/api_configuration_list.php");
							?>
							<button type="button" class="btn btn-primary" onclick="window.location='<?php echo $cancelUrl; ?>';">Cancel</button>
						</div>
					</td>
				</tr>
			</table>
		</form>
	</div>
	<script type="text/javascript">
		$(document).ready(function(){
			$('#api_configuration_type').change(function(){
				//$('form[name="addform"]').submit();
				$('#refreshbtn').click();
			});
		});
	</script>
</body>
</html>

<?php