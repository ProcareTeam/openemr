<?php

namespace OEMR\OpenEMR\Modules\Voicenote\Controller;

use Twig\Environment;

class VoicenotePopupController
{
    /**
     * @var Environment The twig environment
     */
    private $twig;

    public function __construct(string $assetPath, Environment $twig)
    {
        $this->assetPath = $assetPath;
        $this->twig = $twig;
    }

    public function renderVoicenotePopup($queryVars)
    {
        $assetPath = $this->assetPath;
        $authUser = isset($queryVars['authUser']) ? $queryVars['authUser'] : "";
        $modulePath = dirname(dirname($assetPath)) . "/"; // make sure to end with a path
        $accountNumber = isset($GLOBALS['vnote_account_number']) ? $GLOBALS['vnote_account_number'] : "";
        $hostName = isset($GLOBALS['vnote_hostname']) ? $GLOBALS['vnote_hostname'] : "";
        echo $this->twig->render("oemr/voicenote-popup.twig", ['assetPath' => $assetPath, 'authUser' => $authUser, 'accountNumber' => $accountNumber]);
    }

    public function renderVoicenoteLayout()
    {
        $assetPath = $this->assetPath;
        $modulePath = dirname(dirname($assetPath)) . "/"; // make sure to end with a path
        echo $this->twig->render("oemr/voicenote-layout.twig", []);
    }
}

