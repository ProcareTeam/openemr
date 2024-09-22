<?php

/**
 * Smarty plugin
 * @package Smarty
 * @subpackage plugins
 * dispatchPatientDocumentEvent() version for smarty templates
 *
 * Copyright (C) 2019 Brady Miller <brady.g.miller@gmail.com>
 *
 * This program is free software; you can redistribute it and/or
 * modify it under the terms of the GNU General Public License
 * as published by the Free Software Foundation; either version 2
 * of the License, or (at your option) any later version.
 */

use OpenEMR\Events\Generic\PatientDocument\PatientDocumentEvent;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\EventDispatcher\GenericEvent;

/**
 * Smarty {dispatchVhPatientDocumentEvent} function plugin
 *
 * Type:     function<br />
 * Name:     dispatchVhPatientDocumentEvent<br />
 * Purpose:  Event listener for PatientDocumentEvent<br />
 *
 * Examples:
 *
 * {dispatchVhPatientDocumentEvent event="javascript_vh"}
 * {dispatchVhPatientDocumentEvent event="actions_render_imageeditor_anchor"}
 * {dispatchVhPatientDocumentEvent event="actions_render_case_field"}
 *
 * @param array
 * @param Smarty
 */


function smarty_function_dispatchVhPatientDocumentEvent($params, &$smarty)
{
    if (empty($params['event'])) {
        trigger_error("dispatchVhPatientDocumentEvent: missing 'event' parameter", E_USER_WARNING);
        return;
    } else {
        $event = $params['event'];
    }

    $eventDispatcher = $GLOBALS['kernel']->getEventDispatcher();
    if ($event == "javascript_vh") {
        $eventDispatcher->dispatch(new GenericEvent(), PatientDocumentEvent::JAVASCRIPT_VH);
    } elseif ($event == "actions_render_imageeditor_anchor") {

        if (empty($params['mimetype'])) {
            trigger_error("dispatchVhPatientDocumentEvent: missing 'mimetype' parameter", E_USER_WARNING);
            return;
        }

        $mimetype = $params['mimetype'];

        if (in_array($mimetype, array("image/png", "image/jpeg", "image/png"))) {
            $eventDispatcher->dispatch(new GenericEvent(), PatientDocumentEvent::ACTIONS_RENDER_IMAGEEDITOR_ANCHOR);
        }

    } elseif ($event == "actions_render_case_field") {
        $case_id = $params['case_id'] ?? "";
        $case_desc = $params['case_desc'] ?? "";
        $eventDispatcher->dispatch(new GenericEvent(null, array('case_id' => $case_id, 'case_desc' => $case_desc)), PatientDocumentEvent::ACTIONS_RENDER_CASE_FIELD);
    } else {
        trigger_error("dispatchVhPatientDocumentEvent: invalid 'event' parameter", E_USER_WARNING);
        return;
    }
}