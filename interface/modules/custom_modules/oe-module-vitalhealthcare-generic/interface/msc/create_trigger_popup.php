<?php

require_once(dirname(__FILE__, 7) . "/interface/globals.php");
require_once "$srcdir/patient.inc";
require_once "$srcdir/options.inc.php";

use OpenEMR\Common\Csrf\CsrfUtils;
use OpenEMR\Core\Header; 

$triggerId = isset($_REQUEST['trigger_id']) ? $_REQUEST['trigger_id'] : "";
$triggerName = isset($_POST['trigger_name']) ? $_POST['trigger_name'] : "";
$triggerQuery = isset($_POST['trigger_query']) ? $_POST['trigger_query'] : "";

function getTriggerItems($triggerId = "", $status = "") {
    $strWhere = "";
    $binds = array();

    if(!empty($triggerId)) {
        $strWhere .= " and vdt.id = ? ";
        $binds[] = $triggerId;
    }

    if(!empty($status)) {
        $strWhere .= " and vdt.status = ? ";
        $binds[] = $status;
    }

    $pResult = sqlStatementNoLog("SELECT vdt.* from vh_db_triggers vdt where vdt.id != '' " . $strWhere . " order by vdt.id desc", $binds);

    $tReturn = [];
    while ($row = sqlFetchArray($pResult)) {
        $tReturn[] = $row;
    }

    return $tReturn;
}

if(isset($_POST['formsubmit'])) {
	try {
		if(empty($triggerName) || empty($triggerQuery)) {
			throw new \Exception("Trigger empty issue");
		}

		if(!empty($triggerId)) {
			sqlStatement("UPDATE `vh_db_triggers` SET `trigger_name` = ?, `trigger_query` = ? WHERE `id` = ? ", array($triggerName, $triggerQuery, $triggerId));
		} else {
			$triggerId = sqlInsert("INSERT INTO `vh_db_triggers` ( `trigger_name`, `trigger_query`) VALUES ( ?, ? )", array($triggerName, $triggerQuery));
		}

		// SQL query to check if the trigger exists
		$dData = sqlQuery("SELECT * FROM information_schema.TRIGGERS WHERE TRIGGER_SCHEMA = '" . $GLOBALS['dbase'] . "' AND TRIGGER_NAME = '$triggerName'");

		if(!empty($dData)) {
			// Delete sql trigger
			sqlQueryNoLog("DROP TRIGGER " . $GLOBALS['dbase'] . "." . $triggerName . ";", false, true);
		}

		// Execute sql query trigger
		sqlQueryNoLog($triggerQuery, false, true);

	} catch (\Throwable $e) {
       	$message = $e->getMessage();
    }

    if(empty($message)) {
    	?>
		<html>
		<head><?php Header::setupHeader(['opener']); ?></head>
		<body>
			<?php echo "<i>Saving....</i>"; ?>
			<script type="text/javascript">
				function seltriggerpopup() {
					if (opener.closed || ! opener.closeRefresh) {
					   	alert('The destination form was closed; I cannot act on your selection.');
					} else {
					   	opener.closeRefresh();
					  	dlgclose();
					}
				}

				(function () {
			  		'use strict'
					seltriggerpopup();
				})();
			</script>
		</body>
		</html>
		<?php
		exit();
	}
	
}


if(!empty($triggerId)) {
	$tData = getTriggerItems($triggerId);

	if(!empty($tData) && count($tData)) {
		$tData = $tData[0];

		$triggerName = isset($tData['trigger_name']) ? $tData['trigger_name'] : "";
		$triggerQuery = isset($tData['trigger_query']) ? $tData['trigger_query'] : "";
	}
}

?>
<html>
<head>
	<?php Header::setupHeader(['datetime-picker', 'opener', 'jquery', 'jquery-ui', 'datatables', 'datatables-colreorder', 'datatables-bs', 'sortablejs']); ?>

    <title><?php echo xlt('Trigger'); ?></title>

    <script>
		// Example starter JavaScript for disabling form submissions if there are invalid fields
		(function() {
		  'use strict';
		  window.addEventListener('load', function() {
		    // Fetch all the forms we want to apply custom Bootstrap validation styles to
		    var forms = document.getElementsByClassName('needs-validation');
		    // Loop over them and prevent submission
		    var validation = Array.prototype.filter.call(forms, function(form) {
		      form.addEventListener('submit', function(event) {
		        if (form.checkValidity() === false) {
		          event.preventDefault();
		          event.stopPropagation();
		        }
		        form.classList.add('was-validated');
		      }, false);
		    });
		  }, false);
		})();

		<?php if(!empty($message)) { ?>
			alert('<?php echo $message ?>');
		<?php } ?>
	</script>
</head>
<body>
	<div class='container-fluid'>
		<form action="<?php echo "create_trigger_popup.php" ?>" class="needs-validation mb-0" novalidate name="edit_trigger" id="edit_trigger" method="post">

			<input type="hidden" name="trigger_id" value="<?php echo $triggerId; ?>">

			<div class="row">
				<div class="col-md-12 p-2 pb-1">
	        		<label class="form-label"><?php echo xlt('Trigger Name'); ?></label>
				    <input type="text" class="form-control" id="triggerName" name="trigger_name" value="<?php echo $triggerName; ?>" required <?php echo !empty($triggerId) ? "readonly" : ""; ?>>
				    <div class="invalid-feedback">
				        <?php echo xlt('Please enter trigger name.'); ?>
				    </div>
				</div>

				<div class="col-md-12 p-2 pb-1">
	        		<label class="form-label"><?php echo xlt('Trigger Query'); ?></label>
				    <textarea class="form-control" id="triggerQuery" name="trigger_query" rows="6" required><?php echo $triggerQuery; ?></textarea>
				    <div class="invalid-feedback">
				        <?php echo xlt('Please enter trigger query code.'); ?>
				    </div>
				</div>

				<div class='col-12 p-2'>
	                <div class='btn-group ml-0'>
	                    <button type='submit' class='btn btn-primary' type="submit" value="submit" name="formsubmit" ><?php echo xlt('Save'); ?></button>
	                </div>
	            </div>
			</div>
		</form>
	</div>
</body>
</html>