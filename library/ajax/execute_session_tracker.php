<?php

use OpenEMR\Common\Csrf\CsrfUtils;
use OpenEMR\Common\Session\SessionTracker;

//ajax param should be set by calling ajax scripts
$isAjaxCall = isset($_POST['ajax']);

//if false ajax and this is a called from command line, this is a cron job and set up accordingly
if (!$isAjaxCall && (php_sapi_name() === 'cli')) {
    $ignoreAuth = 1;
    //process optional arguments when called from cron
    $_GET['site'] = $argv[1] ?? 'default';
    if (isset($argv[2]) && $argv[2] != 'all') {
        $_GET['background_service'] = $argv[2];
    }

    if (isset($argv[3]) && $argv[3] == '1') {
        $_GET['background_force'] = 1;
    }

    //an additional require file can be specified for each service in the background_services table
    // Since from command line, set $sessionAllowWrite since need to set site_id session and no benefit to set to false
    $sessionAllowWrite = true;
    require_once(__DIR__ . "/../../interface/globals.php");
} else {
    //an additional require file can be specified for each service in the background_services table
    require_once(__DIR__ . "/../../interface/globals.php");

    // not calling from cron job so ensure passes csrf check
    if (!CsrfUtils::verifyCsrfToken($_POST["csrf_token_form"])) {
        CsrfUtils::csrfNotVerified();
    }
}

if (!SessionTracker::isSessionExpired()) {
    SessionTracker::updateSessionExpiration();
}

exit();