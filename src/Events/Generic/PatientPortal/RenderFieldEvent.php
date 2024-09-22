<?php

namespace OpenEMR\Events\Generic\PatientPortal;

use OpenEMR\Events\Core\TemplatePageEvent;

class RenderFieldEvent extends TemplatePageEvent
{
    const EVENT_PATIENTDOCUMENT_RENDER_FIELD_BEFORE = 'patientdocument.renderfield.before';

    /**
     * RenderFieldEvent constructor.
     * @param string $pageName
     * @param array $context
     */
    public function __construct(string $pageName, $context = array())
    {
        parent::__construct($pageName, $context);
    }
}
