<?php

namespace Vitalhealthcare\OpenEMR\Modules\Generic\Controller;

use Vitalhealthcare\OpenEMR\Modules\Generic\Bootstrap;
use Vitalhealthcare\OpenEMR\Modules\Generic\Controller\Route\OnSiteDocumentController;
use Vitalhealthcare\OpenEMR\Modules\Generic\Controller\Route\PropioController;
use OpenEMR\Common\Acl\AccessDeniedException;
use OpenEMR\Common\Acl\AclMain;
use OpenEMR\Common\Database\QueryUtils;
use OpenEMR\Common\Logging\SystemLogger;
use OpenEMR\Common\Session\EncounterSessionUtil;
use OpenEMR\Common\Session\PatientSessionUtil;
use OpenEMR\Common\Uuid\UuidRegistry;
use OpenEMR\Services\AddressService;
use OpenEMR\Services\AppointmentService;
use OpenEMR\Services\EncounterService;
use OpenEMR\Services\ListService;
use OpenEMR\Services\PatientService;
use OpenEMR\Services\UserService;
use OpenEMR\Validators\ProcessingResult;
use Psr\Log\LoggerInterface;
use Twig\Environment;
use Exception;
use InvalidArgumentException;
use RuntimeException;

class RouteController
{
    /**
     * @var Environment
     */
    private $twig;

    /**
     * @var LoggerInterface
     */
    private $logger;

    /**
     * @var boolean  Whether we are running as a patient in the portal context
     */
    private $isPatient;

    /**
     * @var string The location where the module assets are stored
     */
    private $assetPath;

    /**
     * @var \OEMR\OpenEMR\Modules\Voicenote\Repository\VoicenoteSessionRepository
     */
    private $sessionRepository;


    public function __construct(Environment $twig, LoggerInterface $logger, $assetPath, $isPatient = false)
    {
        $this->assetPath = $assetPath;
        $this->twig = $twig;
        $this->logger = $logger;
        $this->isPatient = $isPatient;
    }

    public function dispatch($action, $queryVars)
    {
        $this->logger->debug("GenericMainController->dispatch()", ['action' => $action, 'queryVars' => $queryVars, 'isPatient' => $this->isPatient]);

        if ($action == 'get_onsitedocument_settings') {
            return $this->getOnSiteDocumentAction($queryVars);
        } if ($action == 'propio_popup') {
            return $this->getPropioAction($queryVars);
        } if ($action == 'ajax_propio') {
            return $this->getPropioAjaxAction($queryVars);
        } else {
            $this->logger->error(self::class . '->dispatch() invalid action found', ['action' => $action]);
            echo "action not supported";
            return;
        }
    }
    
    // OnSite Document
    public function getOnSiteDocumentAction($queryVars)
    {
        $controller = new OnSiteDocumentController($this->assetPath, $this->twig);
        echo $controller->renderSettings($queryVars);
    }

    // Propio Popup
    public function getPropioAction($queryVars)
    {
        $controller = new PropioController($this->assetPath, $this->twig);
        echo $controller->renderPropioLayout($queryVars);
    }

    // Propio Ajax
    public function getPropioAjaxAction($queryVars)
    {
        $controller = new PropioController($this->assetPath, $this->twig);
        echo $controller->ajaxPropio($queryVars);
    }
}
