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
use OpenEMR\Events\RestApiExtend\RestApiSecurityCheckEvent;
use Vitalhealthcare\OpenEMR\Modules\Generic\GenericGlobalConfig;
use Vitalhealthcare\OpenEMR\Modules\Generic\Api\XiboRestController;
use Vitalhealthcare\OpenEMR\Modules\Generic\Api\GenericRestController;
use Vitalhealthcare\OpenEMR\Modules\Generic\Api\ZoomRestController;
use Vitalhealthcare\OpenEMR\Modules\Generic\Api\PatientFormController;
use Vitalhealthcare\OpenEMR\Modules\Generic\Api\PortalRestController;
use OpenEMR\Events\Generic\AuthEvent;

class ApiController
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

        $this->subscribeToApiEvents($eventDispatcher);
    }

    public function subscribeToApiEvents(EventDispatcher $eventDispatcher)
    {
        //if ($this->getGlobalConfig()->getGlobalSetting(GlobalConfig::CONFIG_ENABLE_FHIR_API)) {
        $eventDispatcher->addListener(RestApiCreateEvent::EVENT_HANDLE, [$this, 'addApi']);
        $eventDispatcher->addListener(RestApiScopeEvent::EVENT_TYPE_GET_SUPPORTED_SCOPES, [$this, 'addApiScope']);
        $eventDispatcher->addListener(RestApiSecurityCheckEvent::EVENT_HANDLE, [$this, 'skipSecurityCheck']);
        $eventDispatcher->addListener(RestApiResourceServiceEvent::EVENT_HANDLE, [$this, 'addMetadataConformance']);
        //}

        // Event
        $eventDispatcher->addListener(AuthEvent::EVENT_EXPIRETIME_NAME, [$this, 'changeAccessTokenExpiretime']);
    }

    public function addApi(RestApiCreateEvent $event)
    {
        $xiboApiController = new XiboRestController();
        $event->addToRouteMap('GET /api/firstapptlist', [$xiboApiController, 'firstapptlist']);

        $genericApiController = new GenericRestController();
        $event->addToRouteMap('GET /api/getfacilityproviderdata', [$genericApiController, 'getFacilityProviderData']);

        $genericApiController = new GenericRestController();
        $event->addToRouteMap('GET /api/getslottime', [$genericApiController, 'getSlotTimeData']);

        $genericApiController = new GenericRestController();
        $event->addToRouteMap('GET /api/getvisithistory', [$genericApiController, 'getVisithistory']);

        $genericApiController = new GenericRestController();
        $event->addToRouteMap('GET /api/getvisithistoryitem', [$genericApiController, 'getVisithistoryItem']);
        $event->addToRouteMap('POST /api/getvisithistoryitem', [$genericApiController, 'getVisithistoryItem']);

        //$genericApiController = new GenericRestController();
        //$event->addToRouteMap('GET /api/getvisithistoryitemasstream', [$genericApiController, 'getVisithistoryItemAsStream']);

        $genericApiController = new GenericRestController();
        $event->addToRouteMap('GET /api/PatientLedger', [$genericApiController, 'getPatientLedger']);

        $zoomApiController = new ZoomRestController();
        $event->addToRouteMap('POST /api/zoom_webhook', [$zoomApiController, 'zoomWebHook']);

        $patientFormApiController = new PatientFormController();    
        $event->addToRouteMap('POST /api/formauthcheck', [$patientFormApiController, 'formAuthCheck']);

        $patientFormApiController = new PatientFormController();    
        $event->addToRouteMap('GET /api/patientform', [$patientFormApiController, 'patientForm']);

        $patientFormApiController = new PatientFormController();    
        $event->addToRouteMap('POST /api/patientform', [$patientFormApiController, 'savePatientForm']);

        $portalRestApiController = new PortalRestController();    
        $event->addToFHIRRouteMap('GET /fhir/AssignedPatients', [$portalRestApiController, 'getAssignedPatients']);

        /**
         * Events must ALWAYS be returned
         */
        return $event;
    }

    /**
     * Adds the webhook api scopes to the oauth2 scope validation events for the standard api.  This allows the webhook
     * to be fired.
     * @param RestApiScopeEvent $event
     * @return RestApiScopeEvent
     */
    public function addApiScope(RestApiScopeEvent $event)
    {
        if ($event->getApiType() == RestApiScopeEvent::API_TYPE_STANDARD) {
            $scopes = $event->getScopes();

            // firstapptlist
            $scopes[] = 'user/firstapptlist.read';
            $scopes[] = 'patient/firstapptlist.read';
            
            // only add system scopes if they are actually enabled
            if (\RestConfig::areSystemScopesEnabled()) {
                $scopes[] = 'system/firstapptlist.read';
            }
            // End

            // getfacilityproviderdata
            $scopes[] = 'user/getfacilityproviderdata.read';
            $scopes[] = 'patient/getfacilityproviderdata.read';
            
            // only add system scopes if they are actually enabled
            if (\RestConfig::areSystemScopesEnabled()) {
                $scopes[] = 'system/getfacilityproviderdata.read';
            }
            // End

            // getslottime
            $scopes[] = 'user/getslottime.read';
            $scopes[] = 'patient/getslottime.read';
            
            // only add system scopes if they are actually enabled
            if (\RestConfig::areSystemScopesEnabled()) {
                $scopes[] = 'system/getslottime.read';
            }
            // End

            // zoom_webhook
            $scopes[] = 'user/zoom_webhook.read';
            $scopes[] = 'patient/zoom_webhook.read';
            $scopes[] = 'user/zoom_webhook.write';
            $scopes[] = 'patient/zoom_webhook.write';
            
            // only add system scopes if they are actually enabled
            if (\RestConfig::areSystemScopesEnabled()) {
                $scopes[] = 'system/zoom_webhook.read';
                $scopes[] = 'system/zoom_webhook.write';
            }
            // End

            // patientform
            $scopes[] = 'user/patientform.read';
            $scopes[] = 'patient/patientform.read';
            $scopes[] = 'user/patientform.write';
            $scopes[] = 'patient/patientform.write';
            
            // only add system scopes if they are actually enabled
            if (\RestConfig::areSystemScopesEnabled()) {
                $scopes[] = 'system/patientform.read';
                $scopes[] = 'system/patientform.write';
            }
            // End

            // formauthcheck
            $scopes[] = 'user/formauthcheck.read';
            $scopes[] = 'patient/formauthcheck.read';
            
            // only add system scopes if they are actually enabled
            if (\RestConfig::areSystemScopesEnabled()) {
                $scopes[] = 'system/formauthcheck.read';
            }
            // End

            // getvisithistory
            $scopes[] = 'user/getvisithistory.read';
            $scopes[] = 'patient/getvisithistory.read';
            
            // only add system scopes if they are actually enabled
            if (\RestConfig::areSystemScopesEnabled()) {
                $scopes[] = 'system/getvisithistory.read';
            }
            // End

            // getvisithistoryitem
            $scopes[] = 'user/getvisithistoryitem.read';
            $scopes[] = 'patient/getvisithistoryitem.read';
            $scopes[] = 'user/getvisithistoryitem.write';
            $scopes[] = 'patient/getvisithistoryitem.write';
            
            // only add system scopes if they are actually enabled
            if (\RestConfig::areSystemScopesEnabled()) {
                $scopes[] = 'system/getvisithistoryitem.read';
                $scopes[] = 'system/getvisithistoryitem.write';
            }
            // End

            // // getvisithistoryitemasstream
            // $scopes[] = 'user/getvisithistoryitemasstream.read';
            // $scopes[] = 'patient/getvisithistoryitemasstream.read';
            
            // // only add system scopes if they are actually enabled
            // if (\RestConfig::areSystemScopesEnabled()) {
            //     $scopes[] = 'system/getvisithistoryitemasstream.read';
            // }
            // // End

            // get_assigned_patients
            $scopes[] = 'user/AssignedPatients.read';
            $scopes[] = 'patient/AssignedPatients.read';
            
            // only add system scopes if they are actually enabled
            if (\RestConfig::areSystemScopesEnabled()) {
                $scopes[] = 'system/AssignedPatients.read';
            }
            // End

            // PatientLedger
            $scopes[] = 'user/PatientLedger.read';
            $scopes[] = 'patient/PatientLedger.read';
            
            // only add system scopes if they are actually enabled
            if (\RestConfig::areSystemScopesEnabled()) {
                $scopes[] = 'system/PatientLedger.read';
            }
            // End

            $event->setScopes($scopes);
        }
        return $event;
    }

    /**
     * Skip the webhook api auth checks to the oauth2 auth validation events for the standard api.  This allows the webhook
     * to be fired.
     * @param RestApiSecurityCheckEvent $event
     * @return RestApiSecurityCheckEvent
     */
    public function skipSecurityCheck(RestApiSecurityCheckEvent $event)
    {   
        if (in_array($event->getResource(), array('zoom_webhook', 'patientform', 'formauthcheck'))) {
            $event->skipSecurityCheck(true);
        }
    }

    public function addMetadataConformance(RestApiResourceServiceEvent $event)
    {
        //$event->setServiceClass(CustomSkeletonFHIRResourceService::class);
        return $event;
    }

    public function changeAccessTokenExpiretime(AuthEvent $event)
    {
        $request = $event->getRequest();
        $event->setExpireIn(!empty($request->getParsedBody()['expire_in']) ? $request->getParsedBody()['expire_in'] : '');
    }
}