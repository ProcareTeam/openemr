<?php

require_once(dirname(__FILE__, 7) . "/interface/globals.php");

use OpenEMR\Common\Acl\AclMain;

function getHtmlString($text) {
	return addslashes(htmlspecialchars($text));
}

$upcomingColumnList = array(
	array(
		"name" => "uber_ride_details",
		"title" => "TRIPS DETAILS",
		"data" => array(
            "defaultValue" => getHtmlString('<i class="defaultValueText">Empty</i>'),
            "width" => "200",
            "orderable" => false,
		)
	),
	array(
		"name" => "trip_schedule_date",
		"title" => "",
		"data" => array(
            "defaultValue" => getHtmlString('<i class="defaultValueText">Empty</i>'),
            "visible" => false,
            "orderable" => false,
            "width" => "0"
		)
	)
);

