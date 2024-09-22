<?php
unset($oe);
$oe = array();
$multi_labels = array('post_api', 'neu_sense', 'orth_cerv', 'orth_lum', 
	'orth_sac', 'orth_hip', 'orth_shou', 'orth_elbow', 'orth_wrist', 'orth_knee',
	'orth_ankle', 'msc_derm', 'msc_neck', 'msc_scm', 'msc_inter', 'msc_fing_ex', 
	'msc_wrist_ex', 'msc_tri', 'msc_cuff', 'msc_delt', 'msc_lat', 'msc_fing_fl', 
	'msc_wrist_fl', 'msc_bi', 'msc_hip', 'msc_pec', 'msc_psoas', 'msc_tfl',
	'msc_glut_med', 'msc_quad', 'msc_glut_max', 'msc_ham', 'msc_tib', 'msc_per', 
	'msc_ext', 'msc_gastroc', 'tnd_pat', 'tnd_ham', 'tnd_ach', 'tnd_bi', 
	'tnd_tri', 'tnd_rad', 'ge_gait', 'neu_sensory_cervical_c3', 'neu_sensory_cervical_c4', 'neu_sensory_cervical_c5', 'neu_sensory_cervical_c6', 'neu_sensory_cervical_c7', 'neu_sensory_cervical_c8', 'neu_sensory_lumbar_l1', 'neu_sensory_lumbar_l2', 'neu_sensory_lumbar_l3', 'neu_sensory_lumbar_l4', 'neu_sensory_lumbar_l5', 'neu_sensory_lumbar_s1', 'palp_cerv', 'palp_thor', 'palp_lum', 'palp_fin', 'palp_hip', 'msc_c5','msc_l1','msc_c6','msc_l2','msc_c7','msc_l4','msc_c8','msc_l5','msc_c8t1','msc_s1s2');

foreach($multi_labels as $lbl) {
	if(isset($_POST['oe_' . $lbl])) {
		$oe[$lbl] = implode('^|', $_POST['oe_' . $lbl]);
		unset($_POST['oe_' . $lbl]);
	} else {
		$oe[$lbl] = '';
	}
}

foreach($_POST as $key => $val) {
	if(is_string($val)) $val = trim($val);
	if($key == 'ortho_exam_id') {
		$oe[$key] = $val;
		unset($_POST[$key]);
	}
	if(substr($key,0,3) != 'oe_') continue;
	$oe[substr($key, 3)] = $val;
	unset($_POST[$key]);
}

if($frmdir == 'ortho_exam') {
	$oe['ortho_exam_id'] = $id;
} else {
	$oe['link_id'] = $encounter;
	$oe['link_form'] = 'encounter';
}

if($oe['ortho_exam_id']) {
 	$binds = array($pid, $_SESSION['authProvider'], $_SESSION['authUser'],
					$_SESSION['userauthorized'], 1);
 	$q1 = '';
 	foreach ($oe as $key => $val){
		if($key == 'ortho_exam_id') continue;
   	$q1 .= "`$key` = ?, ";
		$binds[] = $val;
 	}
	$binds[] = $oe['ortho_exam_id'];
 	sqlInsert('UPDATE `form_ortho_exam` SET `pid` = ?, `groupname` = ?, ' .
		'`user`=?, `authorized` = ?, `activity` = ?, ' . $q1 . '`date` = NOW() ' .
		'WHERE `id`=?', $binds);
} else {
	unset($oe['ortho_exam_id']);
 	$newid = 
		wmtFormSubmit('form_ortho_exam',$oe,'',$_SESSION['userauthorized'],$pid);
	if($frmdir == 'ortho_exam') {
 		addForm($encounter,$ftitle,$newid,$frmdir,$pid,$_SESSION['userauthorized']);
		$id = $newid;
	}
	$oe['ortho_exam_id'] = $newid;
}
?>
