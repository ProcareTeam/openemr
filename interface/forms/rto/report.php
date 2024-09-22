<?php

require_once(dirname(__file__) . "/../../globals.php");

function rto_report( $pid, $encounter, $cols, $id, $create=false) {
  global $doNotPrintField, $PDF_OUTPUT;

  // Pdf output
	$PDF_OUTPUT = isset($PDF_OUTPUT) ? $PDF_OUTPUT : 0;
  
  $isReport = ((isset($doNotPrintField) && $doNotPrintField === true)) ? true : false;
	$frmdir = 'rto';
	$frmn = 'form_'.$frmdir;
  	include($GLOBALS['srcdir'].'/wmt-v2/report_setup.inc.php');
  	include_once($GLOBALS['srcdir'].'/wmt-v2/ee1form.inc');

	if(!$create) include($GLOBALS['srcdir'].'/wmt-v2/report_header.inc.php');
?>

<?php if(!$create && $PDF_OUTPUT === 0) echo '<body>'; ?>

<?php
	include($GLOBALS['srcdir'].'/wmt-v2/report_body.inc.php');
	//if(!$create) include($GLOBALS['srcdir'].'/wmt-v2/report_signatures.inc.php');
?>
<?php if(!$create && $PDF_OUTPUT === 0) echo '</body></html>'; ?>

<?php } ?>
