<?php

namespace OEMR\OpenEMR\Modules\Voicenote;

use OpenEMR\Common\Crypto\CryptoGen;
use OpenEMR\Services\Globals\GlobalSetting;

class VoicenoteGlobalConfig
{
    const VOICENOTE_ACCOUNT_NUMBER = 'vnote_account_number';

    /**
     * @var CryptoGen
     */
    private $cryptoGen;

    public function __construct()
    {
        $this->cryptoGen = new CryptoGen();
    }

    /**
     * Returns true if all of the telehealth settings have been configured.  Otherwise it returns false.
     * @return bool
     */
    public function isVoicenoteConfigured()
    {
        $config = $this->getGlobalSettingSectionConfiguration();
        $keys = array_keys($config);
        foreach ($keys as $key) {
            $value = $this->getGlobalSettingSectionConfiguration($key);

            if (empty($value)) {
                return false;
            }
        }
        return true;
    }

    public function getGlobalSettingSectionConfiguration()
    {
        $settings = [
            self::VOICENOTE_ACCOUNT_NUMBER => [
                'title' => 'Account Number'
                ,'description' => 'Voicenote account number'
                ,'type' => GlobalSetting::DATA_TYPE_TEXT
                ,'default' => ''
            ]
        ];
        return $settings;
    }
}
