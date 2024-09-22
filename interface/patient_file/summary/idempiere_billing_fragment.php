<?php

use OpenEMR\Core\Header;

require_once("../../globals.php");
require_once($GLOBALS['fileroot'] . "/interface/reports/idempiere_pat_ledger_fun.php");

$pid = isset($_REQUEST['pid']) ? $_REQUEST['pid'] : '';
$mode = isset($_REQUEST['mode']) ? $_REQUEST['mode'] : ''; 

function getIdempierePatientBalance($connection, $pid) {
	$balanceData = array();

	if(empty($pid)) return $balanceData;

    $balances = get_idempiere_patient_balance($connection, $pid);

    $patient_balance_due = isset($balances['patientResponsibility']) ? $balances['patientResponsibility'] : 0;
    $total_balance_due = isset($balances['overallBalance']) ? $balances['overallBalance'] : 0;
    $insurance_balance_due = ($total_balance_due - $patient_balance_due);

    $balanceData = array(
        'patient_balance_due' => $patient_balance_due,
        'insurance_balance_due' => $insurance_balance_due,
        'total_balance_due' => $total_balance_due
    );

    return $balanceData;
}

$balanceData = array();
if($idempiere_connection !== false) $balanceData = getIdempierePatientBalance($idempiere_connection, $pid);

$patient_balance_due = isset($balanceData['patient_balance_due']) ? $balanceData['patient_balance_due'] : 0;
$insurance_balance_due = isset($balanceData['insurance_balance_due']) ? $balanceData['insurance_balance_due'] : 0;
$total_balance_due = isset($balanceData['total_balance_due']) ? $balanceData['total_balance_due'] : 0;

if(isset($mode) && $mode == "patient_balance_due") {
    if($idempiere_connection === false) {
        echo json_encode(array("error" => "Patient Balance Not Available"));
        exit();
    }

    echo json_encode(array("patient_balance_due" => round($patient_balance_due, 2)));
    exit();
}

?>
<div class="row">
	<div class="col-4"><?php echo xlt('Patient Balance Due'); ?></div>
    <div class="col" <?php echo $patient_balance_due > 1 ? 'style="color:red; font-weight:bold;"' : '' ?> ><?php echo $patient_balance_due ?></div>
</div>
<div class="row">
    <div class="col-4"><?php echo xlt('Insurance Balance Due'); ?></div>
    <div class="col"><?php echo $insurance_balance_due ?></div>
</div>
<div class="row">
    <div class="col-4 font-weight-bold"><?php echo xlt('Total Balance Due'); ?></div>
    <div class="col font-weight-bold"><?php echo $total_balance_due ?></div>
</div>
<?php
