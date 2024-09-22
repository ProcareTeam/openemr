<?php

require_once(dirname(__FILE__, 7) . "/interface/globals.php");

use OpenEMR\Common\Acl\AclMain;

function getHtmlString($text) {
	return addslashes(htmlspecialchars($text));
}

$pendingColumnList = array(
	array(
		"name" => "patient_name",
		"title" => "PATIENT",
		"data" => array(
            "defaultValue" => getHtmlString('<i class="defaultValueText">Empty</i>'),
            "width" => "200",
            "orderable" => false,
		)
	),
	array(
		"name" => "template_name",
		"title" => "FORM",
		"data" => array(
            "defaultValue" => getHtmlString('<i class="defaultValueText">Empty</i>'),
            "width" => "250",
            "orderable" => false,
		)
	),
	array(
		"name" => "status",
		"title" => "STATUS",
		"data" => array(
            "defaultValue" => getHtmlString('<i class="defaultValueText">Empty</i>'),
            "width" => "150",
            "orderable" => false,
		)
	),
	array(
		"name" => "created_date",
		"title" => "CREATED",
		"data" => array(
            "defaultValue" => getHtmlString('<i class="defaultValueText">Empty</i>'),
            "width" => "250",
            "orderable" => true,
		)
	),
	array(
		"name" => "actions",
		"title" => "",
		"data" => array(
            "defaultValue" => getHtmlString('<i class="defaultValueText">Empty</i>'),
            "width" => "50",
            "orderable" => false,
		)
	)
);

$receivedColumnList = array(
	array(
		"name" => "patient_name",
		"title" => "PATIENT",
		"data" => array(
            "defaultValue" => getHtmlString('<i class="defaultValueText">Empty</i>'),
            "width" => "200",
            "orderable" => false,
		)
	),
	array(
		"name" => "template_name",
		"title" => "FORM",
		"data" => array(
            "defaultValue" => getHtmlString('<i class="defaultValueText">Empty</i>'),
            "width" => "250",
            "orderable" => false,
		)
	),
	array(
		"name" => "status",
		"title" => "STATUS",
		"data" => array(
            "defaultValue" => getHtmlString('<i class="defaultValueText">Empty</i>'),
            "width" => "150",
            "orderable" => false,
		)
	),
	array(
		"name" => "received_date",
		"title" => "RECEIVED",
		"data" => array(
            "defaultValue" => getHtmlString('<i class="defaultValueText">Empty</i>'),
            "width" => "250",
            "orderable" => true,
		)
	)
);

if(AclMain::aclCheckCore('admin', 'super') || AclMain::aclCheckCore('patients'  , 'pat_rep')) {
	$receivedColumnList[] = array(
		"name" => "actions",
		"title" => "",
		"data" => array(
            "defaultValue" => getHtmlString('<i class="defaultValueText">Empty</i>'),
            "width" => "50",
            "orderable" => false,
		)
	);
}

$reviewedColumnList = array(
	array(
		"name" => "patient_name",
		"title" => "PATIENT",
		"data" => array(
            "defaultValue" => getHtmlString('<i class="defaultValueText">Empty</i>'),
            "width" => "200",
            "orderable" => false,
		)
	),
	array(
		"name" => "template_name",
		"title" => "FORM",
		"data" => array(
            "defaultValue" => getHtmlString('<i class="defaultValueText">Empty</i>'),
            "width" => "250",
            "orderable" => false,
		)
	),
	array(
		"name" => "status",
		"title" => "STATUS",
		"data" => array(
            "defaultValue" => getHtmlString('<i class="defaultValueText">Empty</i>'),
            "width" => "150",
            "orderable" => false,
		)
	),
	array(
		"name" => "reviewed_date",
		"title" => "REVIEWED",
		"data" => array(
            "defaultValue" => getHtmlString('<i class="defaultValueText">Empty</i>'),
            "width" => "250",
            "orderable" => true,
		)
	),
	array(
		"name" => "reviewer",
		"title" => "REVIEWER",
		"data" => array(
            "defaultValue" => getHtmlString('<i class="defaultValueText">Empty</i>'),
            "width" => "250",
            "orderable" => true,
		)
	),
	array(
		"name" => "actions",
		"title" => "",
		"data" => array(
            "defaultValue" => getHtmlString('<i class="defaultValueText">Empty</i>'),
            "width" => "50",
            "orderable" => false,
		)
	)
);

