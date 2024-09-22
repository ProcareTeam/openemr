<?php 
$local_fields = array();
$cnt = 1;
while($cnt < 11) {
	$local_fields[] = 'cows_q' . $cnt;
	$cnt++;
}
$local_fields[] = 'cows_total';
include(FORM_BRICKS . 'module_setup.inc.php');
include(FORM_BRICKS . 'module_loader.inc.php');

if($draw_display) {
?>
<table width="100%" border="0" cellspacing="0" cellpadding="3">
	<tr>
		<td><span class="clickable" onclick="toggleThroughSelect('<?php echo $field_prefix; ?>cows_q1'); total_cows('<?php echo $field_prefix; ?>');"><b>Resting Pulse Rate (BPM)</b> Measure pulse rate after patient is sitting or lying down for 1 minute</span><input name="opiate_withdraw_id" id="opiate_withdraw_id" type="hidden" value="<?php echo $dt['opiate_withdraw_id']; ?>" /></td>
		<td class="wmtR"><select name="<?php echo $field_prefix; ?>cows_q1" id="<?php echo $field_prefix; ?>cows_q1" class="wmtFullInput" onchange="total_cows('<?php echo $field_prefix; ?>');">
		<?php ListSel($dt[$field_prefix.'cows_q1'],'cows_q1_choices'); ?></select></td>
	</tr>
	<tr>
		<td><span class="clickable" onclick="toggleThroughSelect('<?php echo $field_prefix; ?>cows_q2'); total_cows('<?php echo $field_prefix; ?>');"><b>Sweating</b> Sweating not accounted for by room temperature or patient activity over the last 0.5 hours</span></td>
		<td class="wmtR"><select name="<?php echo $field_prefix; ?>cows_q2" id="<?php echo $field_prefix; ?>cows_q2" class="wmtFullInput" onchange="total_cows('<?php echo $field_prefix; ?>');">
		<?php ListSel($dt[$field_prefix.'cows_q2'],'cows_q2_choices'); ?></select></td>
	</tr>
	<tr>
		<td><span class="clickable" onclick="toggleThroughSelect('<?php echo $field_prefix; ?>cows_q3'); total_cows('<?php echo $field_prefix; ?>');"><b>Restlessness observation during assessment</b></span></td>
		<td class="wmtR"><select name="<?php echo $field_prefix; ?>cows_q3" id="<?php echo $field_prefix; ?>cows_q3" class="wmtFullInput" onchange="total_cows('<?php echo $field_prefix; ?>');">
		<?php ListSel($dt[$field_prefix.'cows_q3'],'cows_q3_choices'); ?></select></td>
	</tr>
	<tr>
		<td><span class="clickable" onclick="toggleThroughSelect('<?php echo $field_prefix; ?>cows_q4'); total_cows('<?php echo $field_prefix; ?>');"><b>Pupil Size</b></span></td>
		<td class="wmtR"><select name="<?php echo $field_prefix; ?>cows_q4" id="<?php echo $field_prefix; ?>cows_q4" class="wmtFullInput" onchange="total_cows('<?php echo $field_prefix; ?>');">
		<?php ListSel($dt[$field_prefix.'cows_q4'],'cows_q4_choices'); ?></select></td>
	</tr>
	<tr>
		<td><span class="clickable" onclick="toggleThroughSelect('<?php echo $field_prefix; ?>cows_q5'); total_cows('<?php echo $field_prefix; ?>');"><b>Bone or joint aches</b> If patient was having pain previously, only the additional component attributed to opiate withdrawal is scored</span></td>
		<td class="wmtR"><select name="<?php echo $field_prefix; ?>cows_q5" id="<?php echo $field_prefix; ?>cows_q5" class="wmtFullInput" onchange="total_cows('<?php echo $field_prefix; ?>');">
		<?php ListSel($dt[$field_prefix.'cows_q5'],'cows_q5_choices'); ?></select></td>
	</tr>
	<tr>
		<td><span class="clickable" onclick="toggleThroughSelect('<?php echo $field_prefix; ?>cows_q6'); total_cows('<?php echo $field_prefix; ?>');"><b>Runny nose or tearing</b> Not accounted for by cold symptoms or allergies</span></td>
		<td class="wmtR"><select name="<?php echo $field_prefix; ?>cows_q6" id="<?php echo $field_prefix; ?>cows_q6" class="wmtFullInput" onchange="total_cows('<?php echo $field_prefix; ?>');">
		<?php ListSel($dt[$field_prefix.'cows_q6'],'cows_q6_choices'); ?></select></td>
	</tr>
	<tr>
		<td><span class="clickable" onclick="toggleThroughSelect('<?php echo $field_prefix; ?>cows_q7'); total_cows('<?php echo $field_prefix; ?>');"><b>GI Upset</b> Over the last 0.5 hours</span></td>
		<td class="wmtR"><select name="<?php echo $field_prefix; ?>cows_q7" id="<?php echo $field_prefix; ?>cows_q7" class="wmtFullInput" onchange="total_cows('<?php echo $field_prefix; ?>');">
		<?php ListSel($dt[$field_prefix.'cows_q7'],'cows_q7_choices'); ?></select></td>
	</tr>
	<tr>
		<td><span class="clickable" onclick="toggleThroughSelect('<?php echo $field_prefix; ?>cows_q8'); total_cows('<?php echo $field_prefix; ?>');"><b>Tremor observation of outstretched hands</b></span></td>
		<td class="wmtR"><select name="<?php echo $field_prefix; ?>cows_q8" id="<?php echo $field_prefix; ?>cows_q8" class="wmtFullInput" onchange="total_cows('<?php echo $field_prefix; ?>');">
		<?php ListSel($dt[$field_prefix.'cows_q8'],'cows_q8_choices'); ?></select></td>
	</tr>
	<tr>
		<td><span class="clickable" onclick="toggleThroughSelect('<?php echo $field_prefix; ?>cows_q9'); total_cows('<?php echo $field_prefix; ?>');"><b>Yawning observation during assessment</b></span></td>
		<td class="wmtR"><select name="<?php echo $field_prefix; ?>cows_q9" id="<?php echo $field_prefix; ?>cows_q9" class="wmtFullInput" onchange="total_cows('<?php echo $field_prefix; ?>');">
		<?php ListSel($dt[$field_prefix.'cows_q9'],'cows_q9_choices'); ?></select></td>
	</tr>
	<tr>
		<td><span class="clickable" onclick="toggleThroughSelect('<?php echo $field_prefix; ?>cows_q10'); total_cows('<?php echo $field_prefix; ?>');"><b>Anxiety or irritability</b></span></td>
		<td class="wmtR"><select name="<?php echo $field_prefix; ?>cows_q10" id="<?php echo $field_prefix; ?>cows_q10" class="wmtFullInput" onchange="total_cows('<?php echo $field_prefix; ?>');">
		<?php ListSel($dt[$field_prefix.'cows_q10'],'cows_q10_choices'); ?></select></td>
	</tr>
	<tr>
		<td><span class="clickable" onclick="toggleThroughSelect('<?php echo $field_prefix; ?>cows_q11'); total_cows('<?php echo $field_prefix; ?>');"><b>Gooseflesh skin</b></span></td>
		<td class="wmtR"><select name="<?php echo $field_prefix; ?>cows_q11" id="<?php echo $field_prefix; ?>cows_q11" class="wmtFullInput" onchange="total_cows('<?php echo $field_prefix; ?>');">
		<?php ListSel($dt[$field_prefix.'cows_q11'],'cows_q11_choices'); ?></select></td>
	</tr>
	<tr>
		<td class="clickable"><b>Questionnaire Score:&nbsp;&nbsp;</b><span id="cows_description" style="float: right; margin-right: 12px;"></span></td>
		<td class="wmtB wmtR"><input name="<?php echo $field_prefix; ?>cows_total" id="<?php echo $field_prefix; ?>cows_total" class="wmtInput wmtR" type="text" value="<?php echo htmlspecialchars($dt[$field_prefix.'cows_total'], ENT_QUOTES, '', FALSE); ?>" readonly="readonly" title="" /></td>
	</tr>
</table>

<script type="text/javascript">
function total_cows()
{
	var pre = '';
	if(arguments.length > 0) pre = arguments[0];
	var tot = new Number;
	tot = document.getElementById(pre+'cows_total').value;
	var new_tot = new Number;
	new_tot = 0;
	var t = new Number;
	
	for (var i=1; i<12; i++) {
		var s = pre + 'cows_q' + i;
		var sel = document.getElementById(s);
		t = 0;
		if(sel.selectedIndex) t = parseInt(sel.options[sel.selectedIndex].value);
		new_tot = (new_tot*1) + t;
	}	

	document.getElementById('cows_description').innerHTML = '';
	document.getElementById('cows_description').style.color = 'black';
	if(new_tot < 5) document.getElementById('cows_description').innerHTML = 'NO ACTIVE WITHDRAWAL';
	if(new_tot >= 5 && new_tot < 13) document.getElementById('cows_description').innerHTML = 'MILD WITHDRAWAL';
	if(new_tot >= 13 && new_tot < 25) document.getElementById('cows_description').innerHTML = 'MODERATE WITHDRAWAL';
	if(new_tot >= 25 && new_tot < 37) document.getElementById('cows_description').innerHTML = 'MODERATELY SEVERE WITHDRAWAL';
	if(new_tot >= 25 && new_tot < 37) document.getElementById('cows_description').style.color = 'red';
	if(new_tot >= 37) document.getElementById('cows_description').innerHTML = 'SEVERE WITHDRAWAL';
	if(new_tot >= 37) document.getElementById('cows_description').style.color = 'red';
	document.getElementById(pre+'cows_total').value= new_tot;
	return true;
}
</script>

<?php
} // END DRAW DISPLAY
?>
