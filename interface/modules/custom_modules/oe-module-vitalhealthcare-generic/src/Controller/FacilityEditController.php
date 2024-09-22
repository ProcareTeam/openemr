<?php

namespace Vitalhealthcare\OpenEMR\Modules\Generic\Controller;

use Symfony\Component\EventDispatcher\EventDispatcher;
use Twig\Environment;
use OpenEMR\Events\Core\ScriptFilterEvent;
use OpenEMR\Events\Core\StyleFilterEvent;
use OpenEMR\Common\Utils\CacheUtils;
use OpenEMR\Common\Logging\SystemLogger;
use OpenEMR\Events\RestApiExtend\RestApiCreateEvent;
use OpenEMR\Events\RestApiExtend\RestApiResourceServiceEvent;
use OpenEMR\Events\RestApiExtend\RestApiScopeEvent;
use Vitalhealthcare\OpenEMR\Modules\Generic\GenericGlobalConfig;
use OpenEMR\Events\Generic\Facility\FacilityEditRenderEvent;
use OpenEMR\Events\Facility\FacilityCreatedEvent;
use OpenEMR\Events\Facility\FacilityUpdatedEvent;
use Symfony\Contracts\EventDispatcher\Event;

class FacilityEditController
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

        $this->subscribeToFacilityEvents($eventDispatcher);
    }

    public function subscribeToFacilityEvents(EventDispatcher $eventDispatcher)
    {
        // Event
        $eventDispatcher->addListener(FacilityEditRenderEvent::EVENT_FACILITY_BASIC_EDIT_RENDER_BEFORE, [$this, 'addFacilityEditField']);
        $eventDispatcher->addListener(FacilityCreatedEvent::EVENT_HANDLE, [$this, 'createFacility']);
        $eventDispatcher->addListener(FacilityUpdatedEvent::EVENT_HANDLE, [$this, 'updateFacility']);
    }

    // User Field Render
    public function addFacilityEditField(Event $event): void
    {
        
        // Retrieve the parameters from the event
        $params = $event->getArguments();
        $fData = array();
        if (!empty($params['facility']->getFacilityId())) {
            $fData = sqlQuery("SELECT * FROM `facility` WHERE `id` = ?",array($params['facility']->getFacilityId()));
        }

        ?>

        <div class="form-row">
            <div class="col-sm-12 col-md-7">
                <div class="border p-2 bg-light d-flex">
                    <div class="pr-1">
                        <div class="row">
                            <div class="col-12 mt-2">
                                <label class="col-form-label col-form-label-sm"><b><?php echo xlt('Wordpress Integration'); ?></b></label>
                                <hr class="m-1 mb-2">
                                <div class="form-row custom-control custom-switch my-2">
                                    <div class="col">
                                        <input type="checkbox" class="custom-control-input" name="allowed_to_booked_online" id="allowed_to_booked_online" value="1" <?php echo $fData['allowed_to_booked_online'] == "1" ? 'checked="checked"' : '' ?>>
                                        <label for="allowed_to_booked_online" class="custom-control-label"><?php echo xlt('Allowed to be booked online'); ?></label>
                                    </div>
                                </div>
                            </div>
                            <div class="col-6">
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <?php
    }

    // Event - Create User
    public function createFacility(FacilityCreatedEvent $event)
    {
        $facilityData = $event->getFacilityData();
        $facilityId = $facilityData['id'];
        
        $this->saveFacilityData($facilityData, $facilityId);
    }

    // Event - Update User
    public function updateFacility(FacilityUpdatedEvent $event)
    {
        $facilityData = $event->getNewFacilityData();
        $facilityId = $facilityData['id'];

        $this->saveFacilityData($facilityData, $facilityId);
    }

    // Save user data
    public function saveFacilityData($data, $id) {
        $allowed_to_booked = isset($_POST['allowed_to_booked_online']) ? $_POST['allowed_to_booked_online'] : 0;

        if(!empty($id)) {
           sqlStatement("UPDATE facility SET allowed_to_booked_online=? WHERE id= ? ", array($allowed_to_booked, $id)); 
        }
    }
}