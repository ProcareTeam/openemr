<?php

/**
 * This bootstrap file connects the module to the OpenEMR system hooking to the API, api scopes, and event notifications
 *
 * @package openemr
 * @link      http://www.open-emr.org
 * @author    Hardik Khatri
 */

namespace Vitalhealthcare\OpenEMR\Modules\Generic;

use Vitalhealthcare\OpenEMR\Modules\Generic\Controller\ApiController;
use Vitalhealthcare\OpenEMR\Modules\Generic\Controller\UserEditController;
use Vitalhealthcare\OpenEMR\Modules\Generic\Controller\FacilityEditController;
use Vitalhealthcare\OpenEMR\Modules\Generic\Controller\RouteController;
use Vitalhealthcare\OpenEMR\Modules\Generic\Controller\MenuController;
use Vitalhealthcare\OpenEMR\Modules\Generic\Controller\SubscriberController;
use Vitalhealthcare\OpenEMR\Modules\Generic\Controller\DocumentController;
use OpenEMR\Common\Logging\SystemLogger;
use OpenEMR\Common\Twig\TwigContainer;
use OpenEMR\Common\Utils\CacheUtils;
use OpenEMR\Core\Kernel;
use OpenEMR\Events\Appointments\AppointmentSetEvent;
use OpenEMR\Events\Core\TwigEnvironmentEvent;
use OpenEMR\Events\Globals\GlobalsInitializedEvent;
use OpenEMR\Events\Main\Tabs\RenderEvent;
use OpenEMR\Services\Globals\GlobalSetting;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Twig\Environment;
use Twig\Loader\FilesystemLoader;

class Bootstrap
{
    const OPENEMR_GLOBALS_LOCATION = "../../../../globals.php";
    const MODULE_INSTALLATION_PATH = "/interface/modules/custom_modules/";
    const MODULE_NAME = "";
    const MODULE_MENU_NAME = "Generic";

    /**
     * @var EventDispatcherInterface The object responsible for sending and subscribing to events through the OpenEMR system
     */
    private $eventDispatcher;

    private $moduleDirectoryName;

    /**
     * The OpenEMR Twig Environment
     * @var Environment
     */
    private $twig;

    /**
     * @var GlobalConfig
     */
    private $globalsConfig;

    /**
     * @var SystemLogger
     */
    private $logger;

    /**
     * @var ApiDispatchController
     */
    private $apiDispatchController;

    /**
     * @var UserEditDispatchController
     */
    private $userEditDispatchController;

    /**
     * @var FacilityEditController
     */
    private $facilityEditDispatchController;

    /**
     * @var AppointmentSetDispatchController
     */
    private $appointmentSetDispatchController;

    /**
     * @var MenuDispatchController
     */
    private $menuDispatchController;

    /**
     * @var SubscriberController
     */
    private $subscriberController;

    /**
     * @var DocumentController
     */
    private $documentController;

    public function __construct(EventDispatcher $dispatcher, ?Kernel $kernel = null)
    {
        global $GLOBALS;

        if (empty($kernel)) {
            $kernel = new Kernel();
        }
        $this->eventDispatcher = $dispatcher;
        $twig = new TwigContainer($this->getTemplatePath(), $kernel);
        $twigEnv = $twig->getTwig();
        $this->twig = $twigEnv;

        $this->globalsConfig = new GenericGlobalConfig($GLOBALS);
        $this->moduleDirectoryName = basename(dirname(__DIR__));
        $this->logger = new SystemLogger();
    }

    public function getTemplatePath()
    {
        return \dirname(__DIR__) . DIRECTORY_SEPARATOR . "templates" . DIRECTORY_SEPARATOR;
    }

    public function getURLPath()
    {
        return $GLOBALS['webroot'] . self::MODULE_INSTALLATION_PATH . $this->moduleDirectoryName . "/public/";
    }

    public function getPatientURLPath()
    {
        return $GLOBALS['webroot'] . self::MODULE_INSTALLATION_PATH . $this->moduleDirectoryName . "/patient/";
    }

    /**
     * @return \Twig\Environment
     */
    public function getTwig()
    {
        return $this->twig;
    }

    public function subscribeToEvents()
    {
        $this->addGlobalSettings();
        // we only show the Addon settings if all of the Addon configuration has been configured.
        if ($this->globalsConfig->isConfigured()) {
            // note we need to subscribe at the admin controller as it must precede the registration controller
            // we need our Addon settings setup for a user before we hit the registration controller
            // as there is an implicit data dependency here.
            // TODO: would it be better to abstract this into a separate controller that controls the flow of events
            // instead of relying on the admin being called before the registration?
            $this->getApiController()->subscribeToEvents($this->eventDispatcher);
            $this->getUserEditDispatchController()->subscribeToEvents($this->eventDispatcher);
            $this->getFacilityEditDispatchController()->subscribeToEvents($this->eventDispatcher);
            $this->getMenuController()->subscribeToEvents($this->eventDispatcher);
            $this->getSubscriberController()->subscribeToEvents($this->eventDispatcher);
            $this->getDocumentController()->subscribeToEvents($this->eventDispatcher);
        }
    }

    public function getApiController() {
        if (empty($this->apiDispatchController)) {
            $this->apiDispatchController = new ApiController(
                $this->globalsConfig,
                $this->getTwig(),
                $this->logger,
                $this->getAssetPath(),
                $this->getCurrentLoggedInUser()
            );
        }
        return $this->apiDispatchController;
    }

    public function getUserEditDispatchController() {
        if (empty($this->userEditDispatchController)) {
            $this->userEditDispatchController = new UserEditController(
                $this->globalsConfig,
                $this->getTwig(),
                $this->logger,
                $this->getAssetPath(),
                $this->getCurrentLoggedInUser()
            );
        }
        return $this->userEditDispatchController;
    }

    public function getFacilityEditDispatchController() {
        if (empty($this->facilityEditDispatchController)) {
            $this->facilityEditDispatchController = new FacilityEditController(
                $this->globalsConfig,
                $this->getTwig(),
                $this->logger,
                $this->getAssetPath(),
                $this->getCurrentLoggedInUser()
            );
        }
        return $this->facilityEditDispatchController;
    }

    public function getMenuController() {
        if (empty($this->menuDispatchController)) {
            $this->menuDispatchController = new MenuController(
                $this->globalsConfig,
                $this->getTwig(),
                $this->logger,
                $this->getAssetPath(),
                $this->getPatientURLPath(),
                $this->getCurrentLoggedInUser()
            );
        }
        return $this->menuDispatchController;
    }

    public function getSubscriberController() {
        if (empty($this->subscriberController)) {
            $this->subscriberController = new SubscriberController(
                $this->globalsConfig,
                $this->getTwig(),
                $this->logger,
                $this->getAssetPath(),
                $this->getPatientURLPath(),
                $this->getCurrentLoggedInUser()
            );
        }
        return $this->subscriberController;
    }

    public function getDocumentController() {
        if (empty($this->documentController)) {
            $this->documentController = new DocumentController(
                $this->globalsConfig,
                $this->getTwig(),
                $this->logger,
                $this->getAssetPath(),
                $this->getPatientURLPath(),
                $this->getCurrentLoggedInUser()
            );
        }
        return $this->documentController;
    }

    public function getCurrentLoggedInUser()
    {
        return $_SESSION['authUserID'] ?? null;
    }

    private function getPublicPath()
    {
        return $GLOBALS['webroot'] . self::MODULE_INSTALLATION_PATH . ($this->moduleDirectoryName ?? '') . DIRECTORY_SEPARATOR . 'public' . DIRECTORY_SEPARATOR;
    }

    private function getAssetPath()
    {
        return $this->getPublicPath() . 'assets' . DIRECTORY_SEPARATOR;
    }

    public function getMainController($isPatient): MainController
    {
        return new MainController(
            $this->getTwig(),
            new SystemLogger(),
            $this->getAssetPath(),
            $isPatient
        );
    }

    public function getRouteController($isPatient): RouteController
    {
        return new RouteController(
            $this->getTwig(),
            new SystemLogger(),
            $this->getAssetPath(),
            $isPatient
        );
    }

    public function addGlobalSettings()
    {
        $this->eventDispatcher->addListener(GlobalsInitializedEvent::EVENT_HANDLE, [$this, 'addGlobalModuleSettings']);
    }

    public function addGlobalModuleSettings(GlobalsInitializedEvent $event)
    {
        global $GLOBALS;

        $service = $event->getGlobalsService();
        
        $xibo_section = xlt("Xibo");
        $service->createSection($xibo_section, 'Portal');
        $xibo_settings = $this->globalsConfig->getXiboGlobalSettingSectionConfiguration();

        foreach ($xibo_settings as $key => $config) {
            $value = $GLOBALS[$key] ?? $config['default'];
            $service->appendToSection(
                $xibo_section,
                $key,
                new GlobalSetting(
                    xlt($config['title']),
                    $config['type'],
                    $value,
                    xlt($config['description']),
                    true
                )
            );
        }

        $xibo_section = xlt("Propio");
        $service->createSection($xibo_section, 'Propio');
        $xibo_settings = $this->globalsConfig->getPropioGlobalSettingSectionConfiguration();

        foreach ($xibo_settings as $key => $config) {
            $value = $GLOBALS[$key] ?? $config['default'];
            $service->appendToSection(
                $xibo_section,
                $key,
                new GlobalSetting(
                    xlt($config['title']),
                    $config['type'],
                    $value,
                    xlt($config['description']),
                    true
                )
            );
        }


        $form_manager_section = xlt("Form Manager");
        $service->createSection($form_manager_section, 'Form Manager');
        $form_manager_settings = $this->globalsConfig->getFormManagerGlobalSettingSectionConfiguration();

        foreach ($form_manager_settings as $key => $config) {
            $value = $GLOBALS[$key] ?? $config['default'];
            $service->appendToSection(
                $form_manager_section,
                $key,
                new GlobalSetting(
                    xlt($config['title']),
                    $config['type'],
                    $value,
                    xlt($config['description']),
                    true
                )
            );
        }

        $pacs_manager_section = xlt("Radiology Manager");
        $service->createSection($pacs_manager_section, 'Radiology Manager');
        $pacs_manager_settings = $this->globalsConfig->getPacsManagerGlobalSettingSectionConfiguration();

        foreach ($pacs_manager_settings as $key => $config) {
            $value = $GLOBALS[$key] ?? $config['default'];
            $service->appendToSection(
                $pacs_manager_section,
                $key,
                new GlobalSetting(
                    xlt($config['title']),
                    $config['type'],
                    $value,
                    xlt($config['description']),
                    true
                )
            );
        }

        $pc_manager_section = xlt("Portal Configuration");
        $service->createSection($pc_manager_section, 'Portal Configuration');
        $pc_manager_settings = $this->globalsConfig->getPortalGlobalSettingSectionConfiguration();

        foreach ($pc_manager_settings as $key => $config) {
            $value = $GLOBALS[$key] ?? $config['default'];
            $service->appendToSection(
                $pc_manager_section,
                $key,
                new GlobalSetting(
                    xlt($config['title']),
                    $config['type'],
                    $value,
                    xlt($config['description']),
                    true
                )
            );
        }

        $cr_manager_section = xlt("Callrail Configuration");
        $service->createSection($cr_manager_section, 'Callrail Configuration');
        $cr_manager_settings = $this->globalsConfig->getCallrailGlobalSettingSectionConfiguration();
        foreach ($cr_manager_settings as $key => $config) {
            $value = $GLOBALS[$key] ?? $config['default'];
            $service->appendToSection(
                $cr_manager_section,
                $key,
                new GlobalSetting(
                    xlt($config['title']),
                    $config['type'],
                    $value,
                    xlt($config['description']),
                    true
                )
            );
        }

        $inm_manager_section = xlt("Inmoment Configuration");
        $service->createSection($inm_manager_section, 'Inmoment Configuration');
        $inm_manager_settings = $this->globalsConfig->getInmomentGlobalSettingSectionConfiguration();
        foreach ($inm_manager_settings as $key => $config) {
            $value = $GLOBALS[$key] ?? $config['default'];
            $service->appendToSection(
                $inm_manager_section,
                $key,
                new GlobalSetting(
                    xlt($config['title']),
                    $config['type'],
                    $value,
                    xlt($config['description']),
                    true
                )
            );
        }
    }
}
