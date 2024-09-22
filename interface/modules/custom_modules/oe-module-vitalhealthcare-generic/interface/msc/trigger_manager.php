<?php

require_once(dirname(__FILE__, 7) . "/interface/globals.php");
require_once "$srcdir/patient.inc";
require_once "$srcdir/options.inc.php";

use OpenEMR\Common\Csrf\CsrfUtils;
use OpenEMR\Core\Header;

if(isset($_POST['form_action'])) {
	$formAction = isset($_POST['form_action']) ? $_POST['form_action'] : "";
	$triggerId = isset($_POST['trigger_id']) ? $_POST['trigger_id'] : "";

	if($formAction == "delete") {
		// Fetch trigger data
		$dData = sqlQuery("SELECT * from `vh_db_triggers` WHERE id = ?", array($triggerId));

		if(!empty($dData)) {

			// Delete db trigger
			sqlStatement("DELETE FROM `vh_db_triggers` WHERE `id` = ? ", array($triggerId));

			// Delete sql trigger
			sqlQueryNoLog("DROP TRIGGER " . $GLOBALS['dbase'] . "." . $dData['trigger_name'] . ";", false, true);
		}

		header('Location: '.$_SERVER['REQUEST_URI']);
		exit();
	}
}

$triggerItems = array();  
$triggerResult = sqlStatementNoLog("SELECT * from `vh_db_triggers` order by id desc", array());
while ($trow = sqlFetchArray($triggerResult)) {

	$dData = sqlQuery("SELECT * FROM information_schema.TRIGGERS WHERE TRIGGER_SCHEMA = '" . $GLOBALS['dbase'] . "' AND TRIGGER_NAME = '" . $trow["trigger_name"] . "'");

	if(!empty($dData)) {
		$trow['trigger_data'] = "CREATE TRIGGER `" . $dData["EVENT_OBJECT_SCHEMA"] . "`.`" . $dData["TRIGGER_NAME"] . "` " . $dData["ACTION_TIMING"] . " " . $dData["EVENT_MANIPULATION"] . " ON " . $dData["EVENT_OBJECT_TABLE"];
	}

    $triggerItems[] = $trow;
}

?>
<html>
<head>
    <?php Header::setupHeader(['datetime-picker', 'opener', 'jquery', 'jquery-ui', 'datatables', 'datatables-colreorder', 'datatables-bs']); ?>
    <title><?php echo xlt('Trigger Manager'); ?></title>

    <script>
        function handleCreateTrigger(trigger_id = "") {
        	var target = './create_trigger_popup.php';

        	if(trigger_id != "") {
        		target += '?trigger_id=' + trigger_id;
        	}

	  		let title = '<?php echo xlt('Trigger'); ?>';
	  		dlgopen(target, 'triggerpopup', 1000, 400, '', title);
        }

        function handleDeleteTrigger(trigger_id = "") {
        	if(confirm('<?php echo xlt('Do you want to delete trigger?'); ?>')) {
        		document.querySelector('#trigger_id').value = trigger_id;
        		document.querySelector('#form_action').value = "delete";
        		document.querySelector('#trigger-manager').submit();
        	}
        }

        function closeRefresh() {
        	location.reload();
        }
    </script>
</head>
<body>
	<div class="container mt-3">
		<h2><?php echo xlt('Trigger Manager'); ?></h2>
		<div class="mt-4">
			<form action="trigger_manager.php" method="post" id="trigger-manager">
				<input type="hidden" name="form_action" id="form_action" value="">
				<input type="hidden" name="trigger_id" id="trigger_id" value="">
			</form>
			<button type="button" class="btn btn-primary px-4" onclick="handleCreateTrigger()" ><i class="fa fa-plus" aria-hidden="true"></i> <?php echo xlt('Create Trigger'); ?></button>
		</div>
		<div class="main-container mt-4">
			<table id="trigger_manager_table" class="table table-bordered tbordered table-sm">
				<thead class="thead-light">
					<tr>
						<th><?php echo xlt('Trigger Name'); ?></th>
						<th><?php echo xlt('Details'); ?></th>
						<th width="180"><?php echo xlt('Created'); ?></th>
						<th width="200"><?php echo xlt('Actions'); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php
						if(!empty($triggerItems)) {
							foreach ($triggerItems as $triggerItem) { 
							?>
							<tr>
								<td><b><?php echo isset($triggerItem['trigger_name']) ? $triggerItem['trigger_name'] : "" ?></b></td>
								<td><?php echo isset($triggerItem['trigger_data']) ? $triggerItem['trigger_data'] : "" ?></td>
								<td><?php echo isset($triggerItem['created']) ? $triggerItem['created'] : "" ?></td>
								<td class="p-1" style="vertical-align:middle;">
									<button type="button" class="btn btn-secondary btn-sm" onclick="handleCreateTrigger('<?php echo $triggerItem['id'] ?>')" title="<?php echo "Edit Trigger" ?>"><i class="fa fa-pencil" aria-hidden="true"></i></button>
									<button type="button" class="btn btn-secondary btn-sm" onclick="handleDeleteTrigger('<?php echo $triggerItem['id'] ?>')" title="<?php echo "Delete Trigger" ?>" ><i class="fa fa-trash" aria-hidden="true"></i></button>
								</td>
							</tr>
							<?php 
							} 
						} else {
							?>
							<tr>
								<td colspan="4"><center><?php echo xlt('Not Found'); ?></center></td>
							</tr>
							<?php
						} 
					?>
				</tbody>
			</table>
		</div>
	</div>
</body>
</html>
