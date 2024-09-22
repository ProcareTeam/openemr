<?php
error_reporting(E_ALL & ~E_NOTICE & ~E_WARNING & ~E_STRICT & ~E_DEPRECATED);
ini_set('error_reporting', E_ALL & ~E_NOTICE & ~E_WARNING & ~E_STRICT & ~E_DEPRECATED);
ini_set('display_errors',1);

$ignoreAuth = true; // signon not required!!
$_GET['site'] = 'default';

require_once(dirname( __FILE__, 3 ) . "/interface/globals.php");
require_once("$srcdir/OemrAD/oemrad.globals.php");

use OpenEMR\OemrAd\Reminder;
use OpenEMR\OemrAd\HubspotSync;

function isCommandLineInterface(){
    return (php_sapi_name() === 'cli');
}

$jsonConfigData1 = json_decode($GLOBALS['hubspot_listener_sync_config'], true);

// Token value
$token = isset($jsonConfigData1['token']) ? $jsonConfigData1['token'] : '';

$totalForms = 0;
$totalSubmission = 0;

function getFormItems($params = '') {
    global $token, $totalForms;

    $limit = 100;

    $formApiUrl = "https://api.hubapi.com/marketing/v3/forms?limit=" . $limit;

    if (!empty($params)) {
        $formApiUrl .= "&" . $params;
    }

    $formResponce = HubspotSync::callRequest(
        '', 
        array(
            "url" => $formApiUrl,
            "method" => "GET",
        ),
        array(
            'bearer_token' => $token
        )
    );

    if (!empty($formResponce) && isset($formResponce['results']) && !empty($formResponce['results'])) {

        foreach ($formResponce['results'] as $formItem) {
            $formData = array(
                'id' => $formItem['id'],
                'name' => $formItem['name'],
                'createdAt' => $formItem['createdAt'],
                'updatedAt' => $formItem['updatedAt']
            );

            $fData = sqlQuery("SELECT * FROM `vh_hubspot_forms` WHERE form_id = ? ", array($formItem['id']));

            if (!empty($fData)) {
                // Update form data
                sqlQuery("UPDATE `vh_hubspot_forms` SET name = ?, created_at = ?, updated_at = ? WHERE form_id = ?", array($formItem['name'], $formItem['createdAt'], $formItem['updatedAt'], $formItem['id'] )); 
            } else {
                // Insert form data
                sqlInsert("INSERT INTO `vh_hubspot_forms` (form_id, name, created_at, updated_at) VALUES (?, ?, ?, ?) ", array($formItem['id'], $formItem['name'], $formItem['createdAt'], $formItem['updatedAt']));
            }

            // Delete form submission
            sqlQuery("DELETE FROM `vh_hubspot_form_submissions` WHERE form_id = ?", array($formItem['id']));

            // Get form submission items
            getFormSubmission($formItem['id']);

            $totalForms++;
        }  

        if (isset($formResponce['paging']) && isset($formResponce['paging']['next']) && isset($formResponce['paging']['next']['after']) && !empty($formResponce['paging']['next']['after'])) {
            getFormItems('after=' . $formResponce['paging']['next']['after']);
        }
    }
}

function getFormSubmission($formId = '', $params = '') {
    global $token, $totalSubmission;

    if (empty($formId)) {
        return false;
    }

    $limit = 50;

    $formSubmissionApiUrl = "https://api.hubapi.com/form-integrations/v1/submissions/forms/" . $formId . "?limit=" . $limit;

    if (!empty($params)) {
        $formSubmissionApiUrl .= "&" . $params;
    }

    $formSubmissionResponce = HubspotSync::callRequest(
        '', 
        array(
            "url" => $formSubmissionApiUrl,
            "method" => "GET",
        ),
        array(
            'bearer_token' => $token
        )
    );

    if (!empty($formSubmissionResponce) && isset($formSubmissionResponce['results']) && !empty($formSubmissionResponce['results'])) {
        
        foreach ($formSubmissionResponce['results'] as $formSubmissionItem) {
            $submittedAt = $formSubmissionItem['submittedAt'];
            $fsobject = json_encode($formSubmissionItem);

            // Insert form data
            sqlInsert("INSERT INTO `vh_hubspot_form_submissions` (form_id, submitted_at, object) VALUES (?, ?, ?) ", array($formId, $submittedAt, $fsobject));

            $totalSubmission++;
        }

        if (isset($formSubmissionResponce['paging']) && isset($formSubmissionResponce['paging']['next']) && isset($formSubmissionResponce['paging']['next']['after']) && !empty($formSubmissionResponce['paging']['next']['after'])) {
            // Get form submission
            getFormSubmission($formId, 'after=' . $formSubmissionResponce['paging']['next']['after']);
        }
    }
}

getFormItems();

echo "Total Forms: " . $totalForms . ", Total Submissions: " . $totalSubmission;