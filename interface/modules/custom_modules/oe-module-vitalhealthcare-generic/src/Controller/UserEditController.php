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
use OpenEMR\Events\User\UserEditRenderEvent;
use OpenEMR\Events\User\UserCreatedEvent;
use OpenEMR\Events\User\UserUpdatedEvent;

class UserEditController
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
        $eventDispatcher->addListener(UserEditRenderEvent::EVENT_USER_EDIT_RENDER_AFTER, [$this, 'addUserEditField']);
        $eventDispatcher->addListener(UserCreatedEvent::EVENT_HANDLE, [$this, 'createUser']);
        $eventDispatcher->addListener(UserUpdatedEvent::EVENT_HANDLE, [$this, 'updateUser']);
    }

    // User Field Render
    public function addUserEditField(UserEditRenderEvent $event)
    {
        $uData = sqlQuery("SELECT * FROM `users` WHERE `id` = ?",array($event->getUserId()));
        $user_services = isset($uData['user_services']) ? explode("|", $uData['user_services']) : array();

        ?>
        <!-- <table style="width: 100%;">
            <tbody>
            <tr>
                <td width="120">
                    <span class=text><?php //echo xlt('Services'); ?>: </span>
                </td>
                <td width="210">
                    <select name="user_services[]" id="user_services" class="form-control" multiple="multiple" style="width: 150px;"> -->
                        <?php
                            // $query = "select * from list_options where list_id='User_Services'";
                            // $fres = sqlStatement($query);
                            // while ($frow = sqlFetchArray($fres)) {
                            //     $sSelected = in_array($frow['option_id'], $user_services) ? 'selected="selected"' : '';
                                ?>
                                <!-- <option value="<?php //echo $frow['option_id'] ?>" <?php //echo $sSelected; ?> ><?php //echo $frow['title'] ?></option> -->
                                <?php
                            //}

                        ?>
                    <!-- </select>
                </td>
                <td width="150"><span class=text><?php //echo xlt('Specialization'); ?>: </span></td>
                <td><input type="text" name="specialization" id="specialization" class="form-control" value="<?php //echo $uData['specialization']; ?>" style="width: 150px;" /></td>
            </tr>

             <tr>
                <td width="120">
                    <span class=text><?php //echo xlt('Calendar Interval'); ?>: </span>
                </td>
                <td width="210">
                    <input type="text" name="calendar_interval" id="calendar_interval" class="form-control" value="<?php //echo $uData['calendar_interval']; ?>" style="width: 150px;" />
                </td>
                <td width="150"></td>
                <td></td>
            </tr> -->

            <tr>
                <td colspan="4">
                    <div class="mb-4 mt-4">
                        <span class="text"><b><?php echo xlt('Wordpress Integration'); ?></b></span>
                        <hr class="m-1 mb-2" />
                        <span class=text><?php echo xlt('Allowed to be booked online'); ?>: </span><input type="checkbox" name="allowed_to_booked_online" value='1' <?php echo $uData['allowed_to_booked_online'] == "1" ? 'checked="checked"' : '' ?> />
                    </div>
                </td>
            </tr>
            </tbody>
        </table>
        <?php
    }

    // Event - Create User
    public function createUser(UserCreatedEvent $event)
    {
        $userData = $event->getUserData();
        $uData = sqlQuery("SELECT id FROM `users` WHERE `username` = ? AND `username` != '' AND `username` IS NOT NULL",array(isset($userData['username']) ? $userData['username'] : ''));
        $userId = !empty($uData) && isset($uData['id']) ? $uData['id'] : '';
        $this->saveUserData($userData, $userId);
    }

    // Event - Update User
    public function updateUser(UserUpdatedEvent $event)
    {
        $userData = $event->getNewUserData();
        $userId = $userData['id'];
        $this->saveUserData($userData, $userId);
    }

    // Save user data
    public function saveUserData($data, $id) {
        $allowed_to_booked = isset($data['allowed_to_booked_online']) ? $data['allowed_to_booked_online'] : 0;
        //$user_services_arr = isset($data['user_services']) ? $data['user_services'] : array();
        //$user_services = is_array($user_services_arr) ? implode("|", $user_services_arr) : "";
        //$specialization = isset($data['specialization']) ? $data['specialization'] : "";
        //$calendar_interval = isset($data['calendar_interval']) ? $data['calendar_interval'] : "";

        if(!empty($id)) {
           sqlStatement("UPDATE users SET allowed_to_booked_online=? WHERE id= ? ", array($allowed_to_booked, $id)); 
        }
    }
}