<?php

// @VH: Show encounter list for global/section copy selection. [V10006]

require_once("../../../globals.php");
include_once($GLOBALS['srcdir'].'/api.inc');
include_once($GLOBALS['srcdir'].'/patient.inc');
include_once($GLOBALS['srcdir'].'/forms.inc');

use OpenEMR\Core\Header;

$dateFormat = DateFormatRead("jquery-datetimepicker");

$pid = isset($_REQUEST['pid']) ? $_REQUEST['pid'] : "";
$section_id = isset($_REQUEST['section_id']) ? $_REQUEST['section_id'] : "";
$formname = isset($_REQUEST['formname']) ? $_REQUEST['formname'] : "";
$encounter = isset($_REQUEST['encounter']) ? $_REQUEST['encounter'] : "";

function fetch_appt_signatures_data_byId($eid) {
    if(!empty($eid)) {
        $eSql = "SELECT FE.encounter, E.id, E.tid, E.table, E.uid, U.fname, U.lname, E.datetime, E.is_lock, E.amendment, E.hash, E.signature_hash 
                FROM form_encounter FE 
                LEFT JOIN esign_signatures E ON (case when E.`table` ='form_encounter' then FE.encounter = E.tid else  FE.id = E.tid END)
                LEFT JOIN users U ON E.uid = U.id 
                WHERE FE.encounter = ? 
                ORDER BY E.datetime ASC";
        $result = sqlQuery($eSql, array($eid));
        return $result;
    }
    return false;
}

$result4 = sqlStatement("SELECT fe.encounter,fe.date,openemr_postcalendar_categories.pc_catname, us.fname, us.mname, us.lname FROM form_encounter AS fe left join openemr_postcalendar_categories on fe.pc_catid=openemr_postcalendar_categories.pc_catid  left join users AS us on fe.provider_id = us.id  WHERE fe.pid = ? AND fe.encounter != ? order by fe.date desc", array($pid, $encounter));

$enounterList = array();
while ($rowresult4 = sqlFetchArray($result4)) {
	$encounter = isset($rowresult4['encounter']) ? $rowresult4['encounter'] : '';
	$id = '';

	if(!empty($encounter)) {
		$sql= "SELECT * FROM forms WHERE deleted=0 AND pid=? AND encounter=? AND formdir=?";
		$parms = array($pid, $encounter, $formname);
		$frow = sqlQuery($sql, $parms);
		if($frow['form_id']) {
			$id= $frow['form_id'];
		}
	}

	if(!empty($id)) {
		$rowresult4['form_id'] = $id;
		$enounterList[] = $rowresult4;
	}
}

?>

<html>
<head>
	<title><?php echo htmlspecialchars( xl('Select Encounter'), ENT_NOQUOTES); ?></title>
	<link rel="stylesheet" href='<?php echo $css_header ?>' type='text/css'>
	<?php Header::setupHeader(['opener', 'dialog', 'jquery', 'jquery-ui', 'jquery-ui-base', 'fontawesome', 'main-theme']); ?>
	</script>
	<style type="text/css">
		.encounterContainer {
			padding-top: 20px;
    		font-size: 16px;
		}

		.encounterContainer ul li {
			line-height: 25px;
		}
	</style>
</head>
<body>
	<div class="encounterContainer">
		<ul>
			<?php 
				foreach ($enounterList as $i => $item) {
					$edate = isset($item['date']) ? date($dateFormat, strtotime($item['date'])) : '';
					$cCat = isset($item['pc_catname']) ? $item['pc_catname'] : '';
					$pName = trim($item['fname'].' '.$item['mname'].' '.$item['lname']);
					if(!empty($pName)) {
						$pName = ' - '.$pName;
					}

					$signed = $item['signed'] === true ? 'Signed' : 'Unsigned';
					if(!empty($signed)) {
						$signed = ' - <i>'.$signed.'</i>';
					}

					$titleLink = trim($edate .' '. $cCat.$pName.$signed);

					$encounter_id = isset($item['encounter']) ? $item['encounter'] : '';
					$form_id = isset($item['form_id']) ? $item['form_id'] : '';

					?>
					<li>
						<a href="javascript: void(0)" onClick="selectEncounter('<?php echo $section_id; ?>', '<?php echo $encounter_id; ?>', '<?php echo $form_id; ?>', '<?php echo $pid; ?>')"><?php echo $titleLink; ?></a>
					</li>
					<?php
				}
			?>
		</ul>
	</div>
	<script type="text/javascript">
		function selectEncounter(section_id, encounter_id, form_id, pid) {
			return selEncounter(section_id, encounter_id, form_id, pid);
		}

		function selEncounter(section_id, encounter_id, form_id, pid) {
			if (opener.closed || ! opener.setEncounter)
			alert("<?php echo htmlspecialchars( xl('The destination form was closed; I cannot act on your selection.'), ENT_QUOTES); ?>");
			else
			opener.setEncounter(section_id, encounter_id, form_id, pid);
			window.close();
			return false;
		 }
	</script>
</body>
</html>