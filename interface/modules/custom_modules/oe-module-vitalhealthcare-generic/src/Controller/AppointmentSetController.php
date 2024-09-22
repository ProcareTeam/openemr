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
use OpenEMR\Events\Appointments\AppointmentSetEvent;


class AppointmentSetController
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
        $this->subscribeToApptEvents($eventDispatcher);
    }

    public function subscribeToApptEvents(EventDispatcher $eventDispatcher)
    {
        // Event
        $eventDispatcher->addListener(AppointmentSetEvent::EVENT_BOTTOM_RENDER_HANDLE, [$this, 'appointmentBottomRenderHandle']);
    }

    // Appointment Bottom Render Handle
    public function appointmentBottomRenderHandle(AppointmentSetEvent $event)
    {
        ?>
        <div>
            <input type="button" class="btn btn-primary" name="request_propio_interpreter" id="request_propio_interpreter" style="margin-top: 4px;" value="<?php echo xla('Request a Propio Interpreter');?>" />
        </div>
        <?php
    }
}