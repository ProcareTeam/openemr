<?php

namespace Vitalhealthcare\OpenEMR\Modules\Generic\Controller;

use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Contracts\EventDispatcher\Event;
use Twig\Environment;
use OpenEMR\Events\Core\ScriptFilterEvent;
use OpenEMR\Events\Core\StyleFilterEvent;
use OpenEMR\Common\Utils\CacheUtils;
use OpenEMR\Common\Logging\SystemLogger;
use OpenEMR\Events\RestApiExtend\RestApiCreateEvent;
use OpenEMR\Events\RestApiExtend\RestApiResourceServiceEvent;
use OpenEMR\Events\RestApiExtend\RestApiScopeEvent;
use Vitalhealthcare\OpenEMR\Modules\Generic\GenericGlobalConfig;
use OpenEMR\Events\Generic\PatientDocument\PatientDocumentEvent;


class DocumentController
{
    private $logger;
    private $assetPath;
    /**
     * @var The database record if of the currently logged in user
     */
    private $loggedInUserId;

    /**
     * @var Environment Twig container
     */
    private $twig;
    private $config;

    public function __construct(GenericGlobalConfig $config, Environment $twig, SystemLogger $logger, $assetPath, $loggedInUserId)
    {
        $this->twig = $twig;
        $this->logger = $logger;
        $this->assetPath = $assetPath;
        $this->loggedInUserId = $loggedInUserId;
        $this->config = $config;
    }

    public function subscribeToEvents(EventDispatcher $eventDispatcher)
    {
        $eventDispatcher->addListener(PatientDocumentEvent::JAVASCRIPT_VH, [$this, 'render_javascript_vh']);
        $eventDispatcher->addListener(PatientDocumentEvent::ACTIONS_RENDER_IMAGEEDITOR_ANCHOR, [$this, 'render_image_editor_anchor']);
        $eventDispatcher->addListener(PatientDocumentEvent::ACTIONS_RENDER_CASE_FIELD, [$this, 'render_case_field']);
        $eventDispatcher->addListener(PatientDocumentEvent::ACTIONS_DOCUMENTS_UPLOAD_AFTER, [$this, 'document_upload_after']);
        $eventDispatcher->addListener(PatientDocumentEvent::ACTIONS_DOCUMENTS_UPLOAD_END, [$this, 'document_upload_end']);
        $eventDispatcher->addListener(PatientDocumentEvent::ACTIONS_DOCUMENTS_UPLOAD_EDIT_AFTER, [$this, 'document_upload_edit_after']);
    }

    // @VH: Render image editor and case_id related script
    public function render_javascript_vh(Event $event): void
    {
        ?>
        // @VH: For open image editor for edit image document
        function imageeditor(patient_id, document_id) {
            dlgopen(top.webroot_url + '/interface/modules/custom_modules/oe-module-vitalhealthcare-generic/interface/patient_file/document_image_editor.php?patient_id='+patient_id+'&document_id='+document_id, 'imageeditor', 'modal-lg', '800', '', 'Editor', {
                allowDrag: true,
                allowResize: true
            });
            return false;
        }

        // @VH: For tagging it with an case
        function caseUpdate() {
          var f = document.forms['case_tag'];
          if (f.case_id.value == "" || f.case_id.value == "0") {
            alert(<?php echo xlj("Please select case"); ?>);
            return false;
          }
          //top.restoreSession();
          document.forms['case_tag'].submit();
        }

        // @VH: Set selected case
        function setCase(case_id, case_dt, desc) {
          if (case_id == "") {
            alert(<?php echo xlj("Invalid case"); ?>);
            return false;
          }

          var decodedDesc = '';
          if(desc) decodedDesc = window.atob(desc);
          var desc = case_dt + ' - [ ' + decodedDesc + ' ]';

          // Set case id
          document.querySelector('#case_id').value = case_id;
          document.querySelector('#case_desc').innerHTML = desc;
        }

        // @VH: For select case
        function sel_case(pid) {
            if(!pid || pid == "0") {
                alert(<?php echo xlj("Not valid patient"); ?>);
                return false;
            }
          var href = top.webroot_url + "/interface/forms/cases/case_list.php?mode=choose&popup=pop&pid=" + pid;
          dlgopen(href, 'findCase', 'modal-lg', '800', '', <?php echo xlj("Case List"); ?>);
        }
        <?php
    }

    // @VH: Render image editor button
    public function render_image_editor_anchor(Event $event): void
    {
        ?>
        <a class="btn btn-primary" href='' onclick='return imageeditor(patientpid, docid)' title="<?php echo xlt('Image Editor'); ?>"><?php echo xlt('Image Editor'); ?></a>
        <?php
    }

    // @VH: Render case text field
    public function render_case_field(Event $event): void
    {

        // Retrieve the parameters from the event
        $params = $event->getArguments();
        
        ?>
        <div class="form-group">
            <label for="docdate"><?php echo xlt('Case'); ?>:</label>
            <input type="text" class="form-control" id="case_id" name="case_id" value="<?php echo $params['case_id'] ?? ""; ?>" readonly onclick="sel_case(patientpid)">
            <div id="case_desc" style="font-style: italic;font-size: 14px;"><?php echo $params['case_desc'] ?? ""; ?></div>
        </div>
        <?php
    }

    // @VH: When uploading a new document, if there is exactly one case associated with the selected patient, automatically link this document to that case.
    public function document_upload_after(PatientDocumentEvent $event): void
    {   
        $d = $event->get_document();
        if ($d && $d->foreign_id) {
            $result_case = sqlStatement("SELECT fc.id from form_cases fc where fc.pid = ?", array($d->foreign_id));
            if (sqlNumRows($result_case) === 1) {
                while ($row_result_case = sqlFetchArray($result_case)) {
                    // Set case id
                    $d->set_case_id($row_result_case['id'] ?? 0);
                    $d->persist();
                }
            }
        }
    }

    // @VH: Redirect to the document view page after document upload, if the 'redirect_to_document' query parameter is set to true.
    public function document_upload_end(PatientDocumentEvent $event): void
    {
        $d = $event->get_document();
        if ($d && $d->foreign_id) {
            // @VH: Check if 'redirect_to_document' parameter is set and true in the query string. If so, redirect the user to the documents view page.
            $redirectToDocument = isset($_GET['redirect_to_document']) ? true : false;

            if($redirectToDocument === true && isset($d) && !empty($d->id)) {
                header('Location: ' . $GLOBALS['web_root'] . '/controller.php?document&view&patient_id=' . $d->foreign_id . '&doc_id=' . $d->id . '&');
            }
        }
    }

    // @VH: Update the document to include the selected case, if a case has been chosen.
    public function document_upload_edit_after(PatientDocumentEvent $event): void
    {   
        $messages = "";
        $d = $event->get_document();
        if ($d && $d->foreign_id) {
            $current_case_id = $d->get_case_id();
            $case_id = $_POST['case_id'];
            if ( $current_case_id != $case_id ) {
                if (!is_numeric($case_id)) {
                    $case_id = 0;
                }

                // Set case id
                $d->set_case_id($case_id);
                $d->persist();
                $messages .= xl('Document tagged to case successfully.') . "<br />";
            }
        }

        $event->set_messages($messages);
    }
}