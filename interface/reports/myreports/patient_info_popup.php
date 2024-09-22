<?php

require_once("../../globals.php");
require_once("$srcdir/patient.inc");

use OpenEMR\Core\Header;

$patient_id = isset($_REQUEST['patient_id']) ? $_REQUEST['patient_id'] : "";
//$result = getPatientData($patient_id, "*, DATE_FORMAT(DOB,'%Y-%m-%d') as DOB_YMD");

?>
<!DOCTYPE html>
<html>
<head>
	<meta charset="utf-8">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<title><?php echo xlt('Patient Picture'); ?></title>

	<link rel="stylesheet" href="<?php echo $css_header; ?>" type="text/css">

	<?php Header::setupHeader(['common', 'jquery']); ?>

	<style type="text/css">
		body {
			background: transparent !important;
		}

		/* CSS class to set a default background image */
        .img-with-fallback {
            display: inline-block;
            width: 240px; /* Adjust width as needed */
            height: 240px; /* Adjust height as needed */
            background-size: cover;
            background-position: center;
            position: relative;
        }

        .img-with-fallback img {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
			/* object-fit: cover;*/
            opacity: 0; /* Hide the image by default */
        }

        .img-with-fallback img.loaded {
            opacity: 1; /* Show the image when loaded */
        }
	</style>
</head>
<body>
	<?php
		$imgUrl = $GLOBALS['webroot'] . '/controller.php?document&retrieve&patient_id=' . $patient_id . '&document_id=-1&as_file=false&original_file=true&disable_exit=false&show_original=true&context=patient_picture';

		//if(empty(file_get_contents(getMainSiteUrl() . $imgUrl))) {
		$staticimgUrl = $GLOBALS['images_static_relative'] . "/patient-picture-default.png";
		//}

		// $date_of_death = is_patient_deceased($pid);
		// $dobString = "";

		// if (empty($date_of_death)) {
        //     $dobString = xl('DOB') . ": " . oeFormatShortDate($result['DOB_YMD']) . " " . xl('Age') . ": " . getPatientAgeDisplay($result['DOB_YMD']);
        // } else {
        //     $dobString = xl('DOB') . ": " . oeFormatShortDate($result['DOB_YMD']) . " " . xl('Age at death') . ": " . oeFormatAge($result['DOB_YMD'], $date_of_death);
        // }
	?>

	<div class="flex-fill">
		<div class="float-left mr-0">
			<div class="patientPicture img-with-fallback" style="background-image: url('<?php echo $staticimgUrl; ?>');">
				<img id="picimg" class="img-thumbnail" style="width: 240px; height: 240px;" src="<?php echo $imgUrl; ?>" onload="this.classList.add('loaded')" />
			</div>
		</div>

		<!--
		<div class="form-group">
			<h3 class="d-inline">
				<a class="ptName" href="#">
	                <span><?php //echo $result['fname'] . " " . $result['lname'] ?></span>
	                <small class="text-muted">(<span><?php //echo $result['pubpid'] ?? "" ?></span>)</small>
	            </a>
			</h3>
			<div class="mt-2">
                <span data-bind="text:patient().str_dob()"><?php //echo $dobString; ?></span>
            </div>
		</div>
		-->
	</div>
</body>
</html>