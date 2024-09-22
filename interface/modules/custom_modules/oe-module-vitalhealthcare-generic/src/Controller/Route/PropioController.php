<?php

namespace Vitalhealthcare\OpenEMR\Modules\Generic\Controller\Route;

require_once($GLOBALS['srcdir'] . '/options.inc.php');

use Twig\Environment;
use Vitalhealthcare\OpenEMR\Modules\Generic\Util\PropioUtils;

class PropioController
{
    /**
     * @var Environment The twig environment
     */
    private $twig;

    public function __construct(string $assetPath = null, Environment $twig = null)
    {
        $this->assetPath = $assetPath;
        $this->twig = $twig;

        if(empty($this->twig)) {
            if (empty($kernel)) {
                $kernel = new \OpenEMR\Core\Kernel();
            }

            $templatePath = \dirname(__DIR__, 3) . DIRECTORY_SEPARATOR . "templates" . DIRECTORY_SEPARATOR;

            $twig = new \OpenEMR\Common\Twig\TwigContainer($templatePath, $kernel);
            $this->twig = $twig->getTwig();
        }
    }

    public function renderPropioLayout($queryVars)
    {
        $assetPath = $this->assetPath;
        $authUser = isset($queryVars['authUser']) ? $queryVars['authUser'] : "";
        $conferenceId = isset($queryVars['conferenceId']) ? $queryVars['conferenceId'] : "";
        $joinLink = isset($queryVars['joinLink']) ? $queryVars['joinLink'] : "";
        $appt_id = isset($queryVars['appt_id']) ? $queryVars['appt_id'] : "";
        $modulePath = dirname(dirname($assetPath)) . "/"; // make sure to end with a path

        if(!empty($appt_id)) {
            $meetingDetails = sqlQuery("SELECT * FROM zoom_appointment_events where pc_eid = ?", array($appt_id));
            $conferenceId = isset($meetingDetails['m_id']) ? $meetingDetails['m_id'] : "";
            $joinLink = isset($meetingDetails['join_url']) ? $meetingDetails['join_url'] : "";
        }

        ob_start();
        generate_form_field(array('data_type' => 1, 'field_id' => 'language_id', 'list_id' => 'Propio_Interpreting_Languages', 'empty_title' => 'Please Select'), '');
        $language_id_str = ob_get_clean(); 
        
        echo $this->twig->render("propio/propio-popup.twig", ['assetPath' => $assetPath, 'authUser' => $authUser, 'language_id_list' => $language_id_str, 'conferenceId' => $conferenceId, 'joinLink' => $joinLink, 'apptId' => $appt_id]);
    }

    public function renderPropioElement($meetingId, $apptId)
    {
        if(PropioUtils::isEnable() === true) {
            $propioEventRes = PropioUtils::getActiveRequest($meetingId);

            echo $this->twig->render("propio/propio-element.twig", [
                'meetingId' => $meetingId,
                'apptId' => $apptId,
                'propioId' => isset($propioEventRes['id']) ? $propioEventRes['id'] : "",
                'isRequestExist' => !empty($propioEventRes) && is_array($propioEventRes) ? true : false
            ]);
        }
    }

    public function ajaxPropio($queryVars)
    {
        try {
            $languageId = isset($queryVars['languageId']) ? $queryVars['languageId'] : "";
            $conferenceId = isset($queryVars['conferenceId']) ? $queryVars['conferenceId'] : "";
            $joinLink = isset($queryVars['joinLink']) ? $queryVars['joinLink'] : "";
            $meeting_id = isset($queryVars['meetingId']) ? $queryVars['meetingId'] : "";
            $propio_id = isset($queryVars['propioId']) ? $queryVars['propioId'] : "";
            $appt_id = isset($queryVars['apptId']) ? $queryVars['apptId'] : "";
            
            $mode = isset($queryVars['mode']) ? $queryVars['mode'] : "";
            
            if($mode == "request") {
                if(!empty($languageId) && !empty($joinLink) && !empty($conferenceId)) {
                    $requestRes = PropioUtils::requestInterpreter($conferenceId, array(
                        'languageId' => $languageId,
                        'ConferenceId' => $conferenceId,
                        'JoinLink' => $joinLink
                    ));

                    if(is_array($requestRes) && !empty($requestRes)) {
                        http_response_code(200);
                        echo json_encode($requestRes);
                        exit();
                    } else {
                        throw new \Exception($requestRes);
                    }

                } else {
                    throw new \Exception("Getting Error");
                }
            } else if($mode == "cancel") {
                if(!empty($propio_id)) {
                    $requestRes = PropioUtils::cancelInterpreter($propio_id);

                    if(is_array($requestRes) && !empty($requestRes)) {
                        http_response_code(200);
                        echo json_encode($requestRes);
                        exit();
                    } else {
                        throw new \Exception($requestRes);
                    }
                } else {
                    throw new \Exception("Getting Error");
                }
            } else if($mode == "complete") {
                if(!empty($propio_id)) {
                    $requestRes = PropioUtils::completeInterpreter($propio_id);

                    if(is_array($requestRes) && !empty($requestRes)) {
                        http_response_code(200);
                        echo json_encode($requestRes);
                        exit();
                    } else {
                        throw new \Exception($requestRes);
                    }
                } else {
                    throw new \Exception("Getting Error");
                }
            } else if($mode == "fetch") {
                http_response_code(200);
                $this->renderPropioElement($meeting_id, $appt_id);
                exit();
            } else {
                throw new \Exception("Not valid operation");
            }
        } catch(\Exception $e) {
            http_response_code(504);
            echo $e->getMessage();
            exit();
        }
    }
}
