<?php

require_once("{$GLOBALS['srcdir']}/options.inc.php");
require_once("{$GLOBALS['srcdir']}/forms.inc");
require_once("{$GLOBALS['srcdir']}/translation.inc.php");
include_once("{$GLOBALS['srcdir']}/wmt-v2/wmtstandard.inc");
include_once("{$GLOBALS['srcdir']}/wmt-v2/rto.inc");
include_once("{$GLOBALS['srcdir']}/wmt-v2/wmtpatient.class.php");
include_once("{$GLOBALS['srcdir']}/wmt-v2/rto.class.php");
include_once("{$GLOBALS['srcdir']}/wmt-v2/wmt.msg.inc");

global $iter, $newordermode, $hidebutton, $form_id;

$newordermode = true;
$hidebutton = false;

$rto_id = '';
if(!empty($form_id)) $rto_id = $form_id;
if(isset($iter['form_id'])) $rto_id = isset($iter['form_id']) ? $iter['form_id'] : "";

$rto = getRTObyId($pid, $rto_id);
$rto = is_array($rto) && count($rto) > 0 ? $rto[0] : array();

if(!empty($rto['rto_case'])) $case_data = sqlQuery("SELECT * FROM form_cases WHERE id = ? ", array($rto['rto_case']));

if(!empty($rto) && !empty($rto_id)) {

$layoutData_n = getLayoutForm($rto['rto_action']);
if(!empty($layoutData_n) && !empty($layoutData_n['grp_form_id'])) $rto['layout_form'] = true;

$rtoData_n = getRtoLayoutFormData($pid, $rto_id);

?>
<style type="text/css">
	.onerow pre {
	  font-family: Arial, Helvetica, sans-serif;
	  margin: 0px;
	}
</style>
<div>
	<table class="w-100" id="partable" style="<?php echo isset($isReport) && $isReport === true ? "" : "width: auto!important;" ?>">
		<tbody>
			<?php if(isset($isReport) && $isReport === true) { ?>
			<tr class="text onerow align-top">
				<td><span class="bold"><?php xl('Order Id','e') ?>:</span></td>
				<td><span><?php echo $rto['id']; ?></span></td>
			</tr>
			<tr class="text onerow align-top">
				<td><span class="bold"><?php xl('Ordered By','e') ?>:</span></td>
				<td><span><?php echo UserNameFromName($rto['rto_ordered_by']); ?></span></td>
			</tr>
			<tr class="text onerow align-top">
				<td><span class="bold"><?php xl('Stat','e') ?>:</span></td>
				<td><span><?php echo $rto['rto_stat'] === "1" ? "[&nbsp;X&nbsp;]" : "[&nbsp;&nbsp;&nbsp;]" ?></span></td>
			</tr>
			<?php } else { ?>
			<tr class="text onerow align-top">
				<td><span class="bold"><?php xl('Order Id','e') ?>:</span></td>
				<td><span><?php echo $rto['id']; ?></span></td>
				<td><span class="bold"><?php xl('Date Created','e') ?>:</span></td>
				<td><span><?php echo oeFormatShortDate($rto['date']); ?></span></td>
			</tr>
			<tr class="text onerow align-top">
				<td><span class="bold"><?php xl('Order Type','e') ?>:</span></td>
				<td><span><?php echo ListLook($rto['rto_action'],'RTO_Action'); ?></span></td>
				<td><span class="bold"><?php xl('Ordered By','e') ?>:</span></td>
				<td><span><?php echo UserNameFromName($rto['rto_ordered_by']); ?></span></td>
			</tr>
			<tr class="text onerow align-top">
				<td><span class="bold"><?php xl('Status','e') ?>:</span></td>
				<td><span><?php echo ListLook($rto['rto_status'],'RTO_Status'); ?></span></td>
				<td><span class="bold"><?php xl('Assigned To','e') ?>:</span></td>
				<td><span><?php echo MsgUserGroupDisplay($rto['rto_resp_user']); ?></span></td>
			</tr>
			<tr class="text onerow align-top">
				<td><span class="bold"><?php xl('Case','e') ?>:</span></td>
				<td style="max-width: 350px;">
					<span><?php echo $rto['rto_case']; ?></span>
					<?php
						if(isset($case_data) && isset($case_data['case_description']) && !empty($case_data['case_description'])) {
							echo "<span> - " .$case_data['case_description'] . "</span>";
						}
					?>
				</td>
				<td><span class="bold"><?php xl('Encounter','e') ?>:</span></td>
				<td>
					<span>
						<?php
						$encrow = sqlQuery("SELECT cal.enc_case as case_id, fe.encounter, date(fe.`date`) as encounter_date, CONCAT(CONCAT_WS(' ', IF(LENGTH(u.fname),u.fname,NULL), IF(LENGTH(u.fname),', ', ''), IF(LENGTH(u.lname),u.lname,NULL))) as provider_name, opc.pc_catname from form_encounter fe left join case_appointment_link cal on cal.encounter = fe.encounter left join users u on u.id = fe.provider_id left join openemr_postcalendar_categories opc on opc.pc_catid = fe.pc_catid where fe.pid = ? and fe.encounter = ? order by fe.id desc", array($pid, $rto['encounter']));

						if(!empty($encrow)) {
							if(isset($encrow['encounter_date'])) $encOptionTitle = $encrow['encounter_date'];
	        				if(isset($encrow['pc_catname'])) $encOptionTitle .= " ".$encrow['pc_catname'];
	        				if(isset($encrow['provider_name'])) $encOptionTitle .= " / ".$encrow['provider_name'];

	        				echo $encOptionTitle;
        				}
						?>
					</span>
				</td>
			</tr>
			<tr class="text onerow align-top">
				<td><span class="bold"><?php xl('Stat','e') ?>:</span></td>
				<td><span><?php echo $rto['rto_stat'] === "1" ? "[&nbsp;X&nbsp;]" : "[&nbsp;&nbsp;&nbsp;]" ?></span></td>
				<td colspan="2"></td>
			</tr>
			<?php } ?>
			<?php if($rto['layout_form'] !== true) { ?>
			<tr class="text onerow">
				<td colspan="4">
					<span class="bold"><?php xl('Notes','e') ?>:</span><br>
					<pre><?php echo htmlspecialchars($rto['rto_notes'], ENT_QUOTES, '', FALSE); ?></pre>
				</td>
			</tr>
			<?php } else { ?>
			<tr class="text onerow">
				<td colspan="4">
					<span class="bold"><?php xl('Summary','e') ?>:</span><br>
					<?php getLBFFormData($rto_id, $pid, $rtoData_n, $layoutData_n); ?>
				</td>
			</tr>
			<?php } ?>
		</tbody>
	</table>
</div>
<?php } ?>