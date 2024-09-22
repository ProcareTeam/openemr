<?php

require_once("../../../globals.php");
require_once("$srcdir/patient.inc");

use OpenEMR\Core\Header;

if(!isset($_REQUEST['pid'])) $_REQUEST['pid'] = '';
$pid = strip_tags($_REQUEST['pid']);

if(!isset($_REQUEST['message'])) $_REQUEST['message'] = '';
$message = strip_tags($_REQUEST['message']);

if(!empty($pid)) {
	//Load Patient data
	$result = getPatientData($pid, "*, DATE_FORMAT(DOB,'%Y-%m-%d') as DOB_YMD");
}

?>
<html>
<head>
<link rel="stylesheet" href="<?php echo $css_header;?>" type="text/css">

<?php Header::setupHeader(['common', 'jquery', 'jquery-ui']); ?>

<script type="text/javascript">
    function goToAlertEdit() {
        top.navigateTab(top.webroot_url + '/interface/patient_file/summary/demographics_full.php?atab=Misc',"pat", function () {
                top.activateTabByName("pat",true);
                dlgclose();
        });
    }
</script>

</head>

<body class="body_top">

<table cellspacing='0' cellpadding='0' border='0' style="width: 100%;">
    <tr>
        <td><span class="title"><?php echo xlt("Alert") ?></span></td>
        <td width="10"><a href="javascript:void(0);" onclick="goToAlertEdit()"><i class="fa fa-pencil-alt fa-sm">&nbsp;</i></a></td>
    </tr>
</table>
<br>
    <?php if(isset($result['alert_info']) && !empty(trim($result['alert_info']))) { ?>
        <?php echo trim($result['alert_info']); ?>
    <?php } else if(isset($message) && !empty(trim($message))) { ?>
    	<?php echo trim($message); ?>
    <?php } ?>
</body>
</html>
