<?php

require_once('../interface/globals.php');
require_once($GLOBALS['srcdir'].'/sql.inc.php');
require_once($GLOBALS['srcdir'].'/wmt-v2/list_tools.inc');

use OpenEMR\Core\Header;

$dx_id = isset($_REQUEST['dx_id']) ? strip_tags($_REQUEST['dx_id']) : "";
$alias_id = isset($_REQUEST['alias_id']) ? strip_tags($_REQUEST['alias_id']) : "";
$code_type = isset($_REQUEST['code_type']) ? strip_tags($_REQUEST['code_type']) : "";
$alias_name = isset($_REQUEST['alias_name']) ? strip_tags($_REQUEST['alias_name']) : "";

$form_action = "add_alias_entry_popup.php";

if(isset($_POST['mode'])) {
	
	if($_POST['mode'] == "submit") {
		if(!empty($alias_id)) {
			sqlQueryNoLog("UPDATE `vh_dx_code_alias` vdca SET vdca.alias = ? WHERE vdca.id  = ? ", array($alias_name, $alias_id));
		} else {
			sqlInsert("INSERT INTO `vh_dx_code_alias` (`uid`, `type`, `dx_id`, `alias`) VALUES(?, ?, ?, ?)", array($_SESSION['authUserID'], $code_type, $dx_id, $alias_name));
		}

		?>
		<html>
		<head></head>
		<body>
			<?php echo "<i>Saving....</i>"; ?>
			<script type="text/javascript">
				function selformpopup() {
					if (opener.closed || ! opener.closeRefresh) {
					   	alert('The destination form was closed; I cannot act on your selection.');
					} else {
					   	opener.closeRefresh();
					  	window.close();
					  	return false;
					}
				}

				(function () {
			  		'use strict'
					selformpopup();
				})();
			</script>
		</body>
		</html>
		<?php
		exit();
	} else if($_POST['mode'] == "delete") {
		if(!empty($alias_id)) {
			sqlQueryNoLog("DELETE FROM `vh_dx_code_alias` WHERE id  = ? ", array($alias_id));
		}

		?>
		<html>
		<head></head>
		<body>
			<?php echo "<i>Saving....</i>"; ?>
			<script type="text/javascript">
				function selformpopup() {
					if (opener.closed || ! opener.closeRefresh) {
					   	alert('The destination form was closed; I cannot act on your selection.');
					} else {
					   	opener.closeRefresh();
					  	window.close();
					  	return false;
					}
				}

				(function () {
			  		'use strict'
					selformpopup();
				})();
			</script>
		</body>
		</html>
		<?php
		exit();
	}
}

$aliasDetails = array();
if(!empty($alias_id)) {
	$aliasDetails = sqlQuery("SELECT vdca.* from vh_dx_code_alias vdca where vdca.id  = ?", array($alias_id));
}

?>
<html>
<head>
<title>&nbsp;</title>

<?php Header::setupHeader(['opener', 'jquery', 'jquery-ui']); ?>
<link rel="stylesheet" href='<?php echo $css_header ?>' type='text/css'>


<script>
	function submit_form(mode = '') {
		if(mode == "delete") {
			if(!confirm("Do you want delete?")) {
				return false;
			}
		}

		document.forms[0].mode.value = mode;
  		document.forms[0].submit();
	}
</script>

</head>

<body class="body_top mt-4">
	<form method='post' name='addform' action="<?php echo $form_action; ?>">
		<input type="hidden" name="dx_id" value="<?php echo $dx_id; ?>">
		<input type="hidden" name="alias_id" value="<?php echo $alias_id; ?>">
		<input type="hidden" name="code_type" value="<?php echo $code_type; ?>">
		<input type="hidden" name="mode" value="">
		<div class="form-group">
		    <label for="alias_name"><?php xl('Alias','e'); ?></label>
		    <input type="text" class="form-control" id="alias_name" name="alias_name" placeholder="Enter Alias" value="<?php echo isset($aliasDetails['alias']) ? $aliasDetails['alias'] : ""; ?>">
		</div>
		<button type="button" name="save_alias" class="btn btn-primary" onclick="submit_form('submit')"><?php xl('Save','e'); ?></button>
		<?php if(!empty($dx_id)) { ?>
		<button type="button" class="btn btn-danger" onclick="submit_form('delete')"><?php xl('Delete','e'); ?></button>
		<?php } ?>
	</form>
</body>
</html>