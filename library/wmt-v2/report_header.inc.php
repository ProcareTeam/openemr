<?php if(isset($PDF_OUTPUT) && $PDF_OUTPUT === 1) {

	$stylesheet1 = file_get_contents($GLOBALS['fileroot'] .'/library/wmt-v2/wmtreport.css');
	echo '<style type="text/css">' . $stylesheet1 . '</style>';

	$stylesheet2 = file_get_contents($GLOBALS['fileroot'] .'/library/wmt-v2/wmtreport.bkk.css');
	echo '<style type="text/css">' . $stylesheet2 . '</style>';

} else { ?>
<html>
<head>
<link rel="stylesheet" href="<?php echo $GLOBALS['css_header']; ?>" type="text/css">
<link rel="stylesheet" href="<?php echo $GLOBALS['webroot']; ?>/library/wmt-v2/wmtreport.css" type="text/css">
<link rel="stylesheet" href="<?php echo $GLOBALS['webroot']; ?>/library/wmt-v2/wmtreport.bkk.css" type="text/css">
</head>
<?php } ?>