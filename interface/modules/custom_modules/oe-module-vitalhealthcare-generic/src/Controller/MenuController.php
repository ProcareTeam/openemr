<?php

namespace Vitalhealthcare\OpenEMR\Modules\Generic\Controller;

use Symfony\Component\EventDispatcher\EventDispatcher;
use Twig\Environment;
use OpenEMR\Events\Core\ScriptFilterEvent;
use OpenEMR\Events\Core\StyleFilterEvent;
use OpenEMR\Common\Utils\CacheUtils;
use OpenEMR\Common\Logging\SystemLogger;
use Vitalhealthcare\OpenEMR\Modules\Generic\GenericGlobalConfig;
use OpenEMR\Menu\MenuEvent;
use OpenEMR\Events\Main\Tabs\RenderEvent;
use Vitalhealthcare\OpenEMR\Modules\Generic\Classes\Chat;

class MenuController
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
        $this->subscribeToUserEvents($eventDispatcher);
    }

    public function subscribeToUserEvents(EventDispatcher $eventDispatcher)
    {
        // Event
        $eventDispatcher->addListener(MenuEvent::MENU_UPDATE, [$this, 'setMenu']);
        $eventDispatcher->addListener(ScriptFilterEvent::EVENT_NAME, [$this, 'addMainJavascript']);
        $eventDispatcher->addListener(RenderEvent::EVENT_BODY_RENDER_PRE, [$this, 'preBodyRender']);
        
    }

    // Event - Set Menu
    public function setMenu(MenuEvent $event)
    {  
        // Get menu list
        $menuList = $event->getMenu();

        $waiting_room_menu = (object) array(
            "label" => "Telemed",
            "menu_id" => "pfb01",
            "target" => "flb1",
            "url" => "/interface/modules/custom_modules/oe-module-vitalhealthcare-generic/interface/patient_tracker/telemedicine_waiting_room.php?skip_timeout_reset=1",
            "children" => array(),
            "requirement" => 0,
            "acl_req" => array("patients", "appt"),
            "global_req_strict" => array("!disable_pat_trkr", "!disable_calendar"),
        );

        foreach ($menuList as $key1 => $c1) {
            if($c1->label == "Reports") {
                foreach($c1->children as $key2 => $c2) {
                    if($c2->label == "Clients") {
                        $menuList[$key1]->children[$key2]->children[] =(object) array(
                            "label" => "Pre-Patient",
                            "menu_id" => "rep1",
                            "target" => "rep",
                            "url" => "/interface/reports/myreports/pre_patient.php",
                            "children" => array(),
                            "requirement" => 0,
                            "acl_req" => array("patients", "med")
                        );

                        $menuList[$key1]->children[$key2]->children[] =(object) array(
                            "label" => "HubSpot Sync",
                            "menu_id" => "rep2",
                            "target" => "rep",
                            "url" => "/interface/reports/myreports/hubspot_submission_manager.php",
                            "children" => array(),
                            "requirement" => 0,
                            "acl_req" => array("patients", "med")
                        );
                    }
                }
            } else if($c1->label == "Miscellaneous") {
                array_splice($menuList[$key1]->children, 2, 0, array((object) array(
                            "label" => "Patient form",
                            "menu_id" => "dpor2",
                            "target" => "por2",
                            "children"=> array(
                                (object) array(
                                    "label" => "Form Manager",
                                    "menu_id" => "dpor3",
                                    "target" => "por2",
                                    "url" => "/interface/modules/custom_modules/oe-module-vitalhealthcare-generic/interface/portal/form_manager.php",
                                    "requirement" => 0,
                                    "acl_req" => array("patients", "pat_rep")
                                ),
                                (object) array(
                                    "label" => "Form Packet Manager",
                                    "menu_id" => "dpor4",
                                    "target" => "por2",
                                    "url" => "/interface/modules/custom_modules/oe-module-vitalhealthcare-generic/interface/portal/form_packet_manager.php",
                                    "requirement" => 0,
                                    "acl_req" => array("patients", "pat_rep")
                                ),
                                (object) array(
                                    "label" => "Patient Forms",
                                    "menu_id" => "dpor5",
                                    "target" => "por2",
                                    "url" => "/interface/modules/custom_modules/oe-module-vitalhealthcare-generic/interface/portal/form_data_manager.php",
                                    "requirement" => 0,
                                    "acl_req" => array("patients", "pat_rep")
                                )
                            ),
                            "requirement" => 0,
                            "acl_req" => array("patients", "pat_rep")
                        )));

                array_splice($menuList[$key1]->children, 11, 0, array((object) array(
                            "label" => "MySQL Trigger Manager",
                            "menu_id" => "adm1",
                            "target" => "msc",
                            "url" => "/interface/modules/custom_modules/oe-module-vitalhealthcare-generic/interface/msc/trigger_manager.php",
                            "children"=> array(),
                            "requirement" => 0,
                            "acl_req" => array(
                                array("admin", "actionevents"),
                                array("admin", "practice"),
                            )
                        )));

                array_splice($menuList[$key1]->children, 12, 0, array((object) array(
                            "label" => "Case Mgmt Portal",
                            "menu_id" => "tc1",
                            "target" => "msc",
                            "url" => "/interface/modules/custom_modules/oe-module-vitalhealthcare-generic/interface/usergroup/portal_addrbook_list.php",
                            "children"=> array(),
                            "requirement" => 0,
                            "acl_req" => array("patients", "appt")
                        )));

                array_splice($menuList[$key1]->children, 13, 0, array((object) array(
                            "label" => "Uber Dashboard",
                            "menu_id" => "ub1",
                            "target" => "msc",
                            "url" => "/interface/modules/custom_modules/oe-module-vitalhealthcare-generic/interface/uber/trips_manager.php",
                            "children"=> array(),
                            "requirement" => 0,
                            "acl_req" => array("patients", "appt")
                        )));
            }
        }

        array_splice($menuList, 3, 0, array($waiting_room_menu));

        // Set menu list
        $event->setMenu($menuList);
    }

    public function addMainJavascript(ScriptFilterEvent $event)
    {
        $pageName = $event->getPageName();

        //$checkNotification = sqlQuery("SELECT `notification` FROM `user_notification` WHERE `user_id` = ? ORDER BY id DESC LIMIT 1", array($_SESSION['authUserID']));

        //if($checkNotification['notification'] == 'CHAT') {
            if ($pageName == "main.php") {
                $scripts = $event->getScripts();
                $scripts[] = $this->getAssetPath() . "js/chat_notification.js";
                $event->setScripts($scripts);
            }

            if ($pageName == "demographics.php" || $pageName == "form_data_manager.php" || $pageName == "review_summary.php" || $pageName == "req_form_list.php") {
                $scripts = $event->getScripts();
                $scripts[] = $this->getAssetPath() . "js/form_data_manager.js.php";
                $event->setScripts($scripts);
            }
        //}
    }

    public function preBodyRender(RenderEvent $event) {
        $checkNotification = sqlQuery("SELECT `notification`, `user_status` FROM `user_notification` WHERE `user_id` = ? ORDER BY id DESC LIMIT 1", array($_SESSION['authUserID']));

        $vh_chat_notification = $checkNotification['notification'] == 'CHAT' ? 'true' : 'false';
        $vh_user_status_update = $checkNotification['user_status'] == 'online' ? 'true' : 'false';

        ?>
        <script type="text/javascript">
            var vh_websocket_host = '<?php echo $GLOBALS['websocket_host']; ?>';
            var vh_websocket_port = '<?php echo $GLOBALS['websocket_port']; ?>';
            var vh_websocket_address_type = '<?php echo $GLOBALS['websocket_address_type']; ?>';
            var vh_websocket_siteurl = '<?php echo Chat::getSiteBaseURL(true); ?>';
            var vh_chat_notification = String('<?php echo $vh_chat_notification; ?>') == "true" ? true : false;
            var vh_user_status_update = String('<?php echo $vh_user_status_update; ?>') == "true" ? true : false;
        </script>
        <?php
    }

    private function getAssetPath()
    {
        return $this->assetPath;
    }
}