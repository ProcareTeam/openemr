<?php
function ext_exam2_report( $pid, $encounter, $cols, $id, $create=false) {
	global $PDF_OUTPUT;

	// Pdf output
	$PDF_OUTPUT = isset($PDF_OUTPUT) ? $PDF_OUTPUT : 0;

  include_once('../../globals.php');
	$frmdir = 'ext_exam2';
	$frmn = 'form_'.$frmdir;
  include($GLOBALS['srcdir'].'/wmt-v2/report_setup.inc.php');
  include_once($GLOBALS['srcdir'].'/wmt-v2/ee1form.inc');
  
	if(!$create) include($GLOBALS['srcdir'].'/wmt-v2/report_header.inc.php');
?>

<?php if(!$create && $PDF_OUTPUT === 0) echo '<body>'; ?>

<?php
	include($GLOBALS['srcdir'].'/wmt-v2/report_body.inc.php');
	if(!$create) include($GLOBALS['srcdir'].'/wmt-v2/report_signatures.inc.php');
?>
 
<?php if(!$create && $PDF_OUTPUT === 0) echo '</body></html>'; ?>

<?php } ?>
