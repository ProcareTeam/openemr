<?php

use OpenEMR\Core\Header;

require_once("../../globals.php");
require_once("{$GLOBALS['srcdir']}/options.inc.php");
require_once("{$GLOBALS['srcdir']}/calendar.inc");
require_once("{$GLOBALS['srcdir']}/pnotes.inc");
require_once("{$GLOBALS['srcdir']}/forms.inc");
require_once("{$GLOBALS['srcdir']}/translation.inc.php");
require_once("{$GLOBALS['srcdir']}/formatting.inc.php");
include_once("{$GLOBALS['srcdir']}/wmt-v2/wmtstandard.inc");
include_once("{$GLOBALS['srcdir']}/wmt-v2/rto.inc");
include_once("{$GLOBALS['srcdir']}/wmt-v2/wmtpatient.class.php");
include_once("{$GLOBALS['srcdir']}/wmt-v2/rto.class.php");
include_once("{$GLOBALS['srcdir']}/wmt-v2/wmt.msg.inc");

use OpenEMR\Common\Acl\AclMain;

$rto_id = isset($_REQUEST['rto_id']) ? $_REQUEST['rto_id'] : '';

?>
<html>
<head>
	<title><?php echo htmlspecialchars( xl('Logs'), ENT_NOQUOTES); ?></title>
	<link rel="stylesheet" href='<?php echo $css_header ?>' type='text/css'>
		<?php Header::setupHeader(['opener', 'dialog', 'jquery']); ?>
	</script>
	<style type="text/css">
		.alert_log_table {
			width: 100%;
		}
		.alert_log_table_container table tr td,
		.alert_log_table_container table tr th {
			padding: 5px;
		}

		.alert_log_table_container table tr:nth-child(even) {
			background: #EEEEEE !important;
		}

		.content {
			word-break: break-word;
		}

		.alert_log_table_container{
			padding: 10px;
		}

		.alert_log_table2 {
			margin-top: 20px;
		}
	</style>
</head>
<body>
<div class="alert_log_table_container">
	<table class="alert_log_table text">
		<tr class="showborder_head">
			<th width="180" align="center"><?php echo xlt("Created Date"); ?></th>
			<th><?php echo xlt("Notes"); ?></th>
		</tr>

		<?php
			$res = sqlStatement("SELECT vporh.*, u.fname, u.lname from vh_portal_order_request_history vporh left join users u on u.email = vporh.`user` WHERE vporh.order_id = ? order by vporh.created_date", array($rto_id));
			while ($row = sqlFetchArray($res)) {
				$commentPrefix = "";

				if (!empty($row['user'] ?? "") && !empty($row['fname'] ?? "") && !empty($row['lname'] ?? "")) {
					$commentPrefix .= $row['fname'] . " " . $row['lname'] . " (" . $row['user'] . ")";
				}

				if (!empty($row['created_date'] ?? "")) {
					$commentPrefix .= " - " . $row['created_date'];
				}

				if (!empty($row['source'] ?? "")) {
					$commentPrefix .= " - " . $row['source'];
				}

				?>
				<tr>
					<td><?php echo $row['created_date'] ?? ""; ?></td>
					<td><?php echo $commentPrefix . (!empty($row['comment'] ?? "") ? " - " . $row['comment'] : ""); ?></td>
				</tr>
				<?php
			}
		?>
	</table>
</div>
</body>
</html>