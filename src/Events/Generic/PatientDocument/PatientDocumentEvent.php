<?php

/**
 * PatientDocumentEvents
 *
 * @package   OpenEMR
 * @link      http://www.open-emr.org
 * @author    Jerry Padgett <sjpadgett@gmail.com>
 * @copyright Copyright (c) 2019 Jerry Padgett <sjpadgett@gmail.com>
 * @license   https://github.com/openemr/openemr/blob/master/LICENSE GNU General Public License 3
 */

namespace OpenEMR\Events\Generic\PatientDocument;

use Symfony\Contracts\EventDispatcher\Event;

class PatientDocumentEvent extends Event
{
    const ACTIONS_RENDER_IMAGEEDITOR_ANCHOR = 'documents.actions.render.imageeditor.anchor';
    const ACTIONS_RENDER_CASE_FIELD = 'documents.actions.render.case.field';
    const JAVASCRIPT_VH = 'documents.javascript.vh';
    const ACTIONS_DOCUMENTS_UPLOAD_AFTER = 'documents.actions.upload.after';
    const ACTIONS_DOCUMENTS_UPLOAD_END = 'documents.actions.upload.end';
    const ACTIONS_DOCUMENTS_UPLOAD_EDIT_AFTER = 'documents.actions.upload.edit.after';

    private $d;
    private $messages;

    /**
     * PatientDocumentEvent constructor.
     * @param object|null $d
     */
    public function __construct($d = null)
    {
        $this->d = $d;
    }

    public function get_document() {
        return $this->d;
    }

    public function set_messages($messages) {
        $this->messages = $messages;
    }

    public function get_messages() {
        return $this->messages;
    }
}
