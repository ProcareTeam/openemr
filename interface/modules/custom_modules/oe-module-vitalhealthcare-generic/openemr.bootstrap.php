<?php

/**
 * Bootstrap custom module for the Comlink Telehealth module.
 *
 * @package openemr
 * @link      http://www.open-emr.org
 * @author    Hardik Khatri
 */

namespace Vitalhealthcare\OpenEMR\Modules\Generic;

use OpenEMR\Core\ModulesClassLoader;

/**
 * @global OpenEMR\Core\ModulesClassLoader $classLoader
 */
$classLoader->registerNamespaceIfNotExists('Vitalhealthcare\\OpenEMR\\Modules\\Generic\\', __DIR__ . DIRECTORY_SEPARATOR . 'src');

/**
 * @global EventDispatcher $eventDispatcher Injected by the OpenEMR module loader;
 */

$bootstrap = new Bootstrap($eventDispatcher, $GLOBALS['kernel']);
$bootstrap->subscribeToEvents();
