<?php

/**
 * OnsiteDocumentEvent class is fired from the interface/usergroup/facility_admin.php and interface/usergroup/facility_admin_add.php
 * pages inside OpenEMR and allows event listeners to render content before or after the form fields for the user.  Content
 * will be contained inside a div.
 *
 * @package   OpenEMR
 * @link      http://www.open-emr.org
 *
 * @author    Hardik Khatri
 */

namespace OpenEMR\Events\Generic\PatientPortal;

use OpenEMR\Events\Core\TemplatePageEvent;

class OnsiteDocumentEvent extends TemplatePageEvent
{
    const EVENT_VERIFY_SESSION_AFTER = 'onsitedocument.verify.session.after';

    const EVENT_ONSITEDOCUMENT_PATIENT_INDEX_RENDER_BEFORE = 'onsitedocument.patientindex.render.before';

    const EVENT_ONSITEDOCUMENT_CREATE_BEFORE = 'onsitedocument.basic.create.before';

    const EVENT_ONSITEDOCUMENT_UPDATE_BEFORE = 'onsitedocument.basic.update.before';

    const EVENT_ONSITEDOCUMENT_DELETE_BEFORE = 'onsitedocument.basic.delete.before';

    const EVENT_ONSITEDOCUMENT_CREATE_AFTER = 'onsitedocument.basic.create.after';

    const EVENT_ONSITEDOCUMENT_UPDATE_AFTER = 'onsitedocument.basic.update.after';

    const EVENT_ONSITEDOCUMENT_DELETE_AFTER = 'onsitedocument.basic.delete.after';

    const EVENT_ONSITEDOCUMENT_LISTVIEW_RENDER_BEFORE = 'onsitedocument.listview.render.before';

    const EVENT_PATIENTDOCUMENT_RENDER_FIELD_BEFORE = 'onsitedocument.patientdocument.field.render.before';

    private $mode;
    private $obj;
    private $param;
    private $pid;
    private $fullDocument;
    private $route_map;
    private $template_path;

    /**
     * OnsiteDocumentEvent constructor.
     * @param string $pageName
     * @param int|null $mode
     * @param array $context
     */
    public function __construct(string $pageName, $context = array())
    {
        parent::__construct($pageName, $context);

        $mode = isset($context['mode']) ? $context['mode'] : 1;
        $obj = isset($context['obj']) ? $context['obj'] : null;
        $param = isset($context['param']) ? $context['param'] : array();
        $pid = isset($context['pid']) ? $context['pid'] : null;
        $route_map = isset($context['ROUTE_MAP']) ? $context['ROUTE_MAP'] : array();

        $this->setMode($mode);
        $this->setObj($obj);
        $this->setParam($param);
        $this->setPid($pid);
        $this->fullDocument = false;
        $this->route_map = $route_map;
        $this->template_path = '';
    }

    public function getMode()
    {
        return $this->mode;
    }

    public function setMode($mode)
    {
        $this->mode = $mode;
        return $this;
    }

    public function getObj()
    {
        return $this->obj;
    }

    public function setObj(&$obj)
    {
        $this->obj = $obj;
        return $this;
    }

    public function getParam()
    {
        return $this->param;
    }

    public function setParam($param)
    {
        $this->param = $param;
        return $this;
    }

    public function getPid()
    {
        return $this->pid;
    }

    public function setPid($pid)
    {
        $this->pid = $pid;
        return $this;
    }

    public function getFullDocument()
    {
        return $this->fullDocument;
    }

    public function setFullDocument($fullDocument)
    {
        $this->fullDocument = $fullDocument;
        return $this;
    }

    public function getRouteMap()
    {
        return $this->route_map;
    }

    public function setRouteMap($route_map = array())
    {
        $this->route_map = $route_map;
        return $this;
    }

    public function getTemplatePath()
    {
        return $this->template_path;
    }

    public function setTemplatePath($template_path = '')
    {
        $this->template_path = $template_path;
        return $this;
    }
}
