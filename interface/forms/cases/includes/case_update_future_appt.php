<?php

if(empty($id)) {
	$t_data = sqlQuery("SELECT sum(total_count.count) as t_count from (SELECT count(ope.pc_eid) as count from openemr_postcalendar_events ope where cast(concat(ope.pc_eventDate, ' ', ope.pc_startTime) as datetime) >= CURDATE() and ope.pc_pid = ? UNION ALL SELECT count(fe.id) as count  from form_encounter fe where fe.`date` >= CURDATE() and fe.pid = ?) as total_count", array($pid, $pid));
	$t_count = isset($t_data['t_count']) ? $t_data['t_count'] : 0;

	if($t_count > 0) {
		?>
		<input name="form_ufa" id="form_ufa" type="hidden" class="" value="0" />
		<script type="text/javascript">
			// Validation Function
	   		window.formScriptValidations.push(() => caselibObj.validate_FutureAppt('<?php echo $pid; ?>'));
		</script>
		<?php
	}
}
