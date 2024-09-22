<?php

/**
 * FacilityEditRenderEvent class is fired from the interface/usergroup/facility_admin.php and interface/usergroup/facility_admin_add.php
 * pages inside OpenEMR and allows event listeners to render content before or after the form fields for the user.  Content
 * will be contained inside a div.
 *
 * @package   OpenEMR
 * @link      http://www.open-emr.org
 *
 * @author    Hardik Khatri
 */

namespace OpenEMR\Events\Generic\Facility;

use OpenEMR\Events\Core\TemplatePageEvent;

class FacilityEditRenderEvent extends TemplatePageEvent
{
    const EVENT_FACILITY_BASIC_EDIT_RENDER_BEFORE = 'faclity.basic.edit.render.before';


    const EVENT_FACILITY_BASIC_EDIT_RENDER_AFTER = 'faclity.basic.edit.render.after';

    private $faclityId;

    /**
     * FacilityEditRenderEvent constructor.
     * @param string $pageName
     * @param int|null $faclityId The userid that is being edited, null if this is a brand new user
     * @param array $context
     */
    public function __construct(string $pageName, ?int $faclityId = null, $context = array())
    {
        parent::__construct($pageName, $context);
        $this->setFacilityId($faclityId);
    }

    /**
     * @return int|null
     */
    public function getFacilityId()
    {
        return $this->faclityId;
    }

    /**
     * @param int|null $faclityId
      * @return FacilityEditRenderEvent
     */
    public function setFacilityId($faclityId)
    {
        $this->faclityId = $faclityId;
        return $this;
    }
}
