<?php

// since we are working inside the portal we have to use the portal session verification logic here...
require_once "../../../../globals.php";

use Vitalhealthcare\OpenEMR\Modules\Generic\Bootstrap;

$kernel = $GLOBALS['kernel'];
$bootstrap = new Bootstrap($kernel->getEventDispatcher(), $kernel);
$roomController = $bootstrap->getRouteController(false);

$action = $_REQUEST['action'] ?? '';
$queryVars = $_REQUEST ?? [];
$queryVars['pid'] = $_SESSION['pid'] ?? null;
$queryVars['authUser'] = $_SESSION['authUser'] ?? null;
$roomController->dispatch($action, $queryVars);
exit;