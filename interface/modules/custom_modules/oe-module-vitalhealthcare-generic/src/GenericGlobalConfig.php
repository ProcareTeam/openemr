<?php

/**
 * Contains all of the TeleHealth global settings and configuration
 *
 * @package openemr
 * @link      http://www.open-emr.org
 * @author    Stephen Nielson <snielson@discoverandchange.com>
 * @copyright Copyright (c) 2022 Comlink Inc <https://comlinkinc.com/>
 * @license   https://github.com/openemr/openemr/blob/master/LICENSE GNU General Public License 3
 */

namespace Vitalhealthcare\OpenEMR\Modules\Generic;

use OpenEMR\Common\Crypto\CryptoGen;
use OpenEMR\Services\Globals\GlobalSetting;

class GenericGlobalConfig
{
    const GENERIC_APP_NAME = 'generic_app_name';

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
    public function isConfigured()
    {
        return true;
    }

    public function getInstitutionName()
    {
        return $this->getGlobalSetting(self::GENERIC_APP_NAME);
    }

    public function getXiboGlobalSettingSectionConfiguration()
    {
        $settings = [
            'oemr_xibo_appt_cat' => [
                'title' => 'Xibo Appt Categories'
                ,'description' => 'Xibo Appt Categories'
                ,'type' => GlobalSetting::DATA_TYPE_TEXT
                ,'default' => ''
            ]
        ];
        return $settings;
    }

    public function getPropioGlobalSettingSectionConfiguration()
    {
        $settings = [
            'propio_clientid' => [
                'title' => 'ClientId'
                ,'description' => 'ClientId'
                ,'type' => GlobalSetting::DATA_TYPE_TEXT
                ,'default' => ''
            ],
            'propio_access_code' => [
                'title' => 'Access Code'
                ,'description' => 'Access Code'
                ,'type' => GlobalSetting::DATA_TYPE_ENCRYPTED
                ,'default' => ''
            ],
            'propio_api_key' => [
                'title' => 'Api Key'
                ,'description' => 'Api Key'
                ,'type' => GlobalSetting::DATA_TYPE_ENCRYPTED
                ,'default' => ''
            ]
        ];
        return $settings;
    }

    public function getFormManagerGlobalSettingSectionConfiguration()
    {
        $settings = [
            'fm_form_portal_url' => [
                'title' => 'Form Portal URL'
                ,'description' => 'Form Portal URL'
                ,'type' => GlobalSetting::DATA_TYPE_TEXT
                ,'default' => 'http://127.0.0.1'
            ],
            'fm_default_document_category' => [
                'title' => 'Default Document Upload Category'
                ,'description' => 'Default Document Upload Category'
                ,'type' => GlobalSetting::DATA_TYPE_TEXT
                ,'default' => '3'
            ],
            'fm_form_token_expiretime' => [
                'title' => 'Form Token Expiretime'
                ,'description' => 'Form Token Expiretime'
                ,'type' => GlobalSetting::DATA_TYPE_TEXT
                ,'default' => 'P2D'
            ],
            'fm_form_translate_lang' => [
                'title' => 'Form Translation Language'
                ,'description' => 'Form Translation Language (Ex en,es)'
                ,'type' => GlobalSetting::DATA_TYPE_TEXT
                ,'default' => ''
            ]
        ];
        return $settings;
    }

    public function getPacsManagerGlobalSettingSectionConfiguration()
    {
        $settings = [
            'pacs_api_url' => [
                'title' => 'PACS API URL'
                ,'description' => 'PACS API URL'
                ,'type' => GlobalSetting::DATA_TYPE_TEXT
                ,'default' => 'http://127.0.0.1'
            ],
            'pacs_header_key' => [
                'title' => 'PACS Header Key'
                ,'description' => 'PACS Header Key'
                ,'type' => GlobalSetting::DATA_TYPE_TEXT
                ,'default' => ''
            ],
            'pacs_header_key_value' => [
                'title' => 'PACS Header Key Value'
                ,'description' => 'PACS Header Key Value'
                ,'type' => GlobalSetting::DATA_TYPE_ENCRYPTED
                ,'default' => ''
            ],
            'pacs_image_viewer_url' => [
                'title' => 'PACS Image Viewer URL'
                ,'description' => 'PACS Image Viewer URL'
                ,'type' => GlobalSetting::DATA_TYPE_TEXT
                ,'default' => 'http://127.0.0.1'
            ],
            'pacs_token_api_url' => [
                'title' => 'PACS Token Api URL'
                ,'description' => 'PACS Token Api URL'
                ,'type' => GlobalSetting::DATA_TYPE_TEXT
                ,'default' => ''
            ],
            'pacs_token_api_username' => [
                'title' => 'PACS Token Api Username'
                ,'description' => 'PACS Token Api Username'
                ,'type' => GlobalSetting::DATA_TYPE_TEXT
                ,'default' => ''
            ],
            'pacs_token_api_password' => [
                'title' => 'PACS Token Api Password'
                ,'description' => 'PACS Token Api Password'
                ,'type' => GlobalSetting::DATA_TYPE_ENCRYPTED
                ,'default' => ''
            ],
            'pacs_popup_type' => [
                'title' => 'PACS Popup Type'
                ,'description' => 'PACS Popup Type'
                ,'type' => array(
                    "window" => "External Popup",
                    "popup" => "Inner Popup"
                )
                ,'default' => 'window'
            ]
        ];
        return $settings;
    }

    public function getPortalGlobalSettingSectionConfiguration()
    {
        $settings = [
            'pc_watermark_text' => [
                'title' => 'Watermark Text'
                ,'description' => 'Watermark Text'
                ,'type' => GlobalSetting::DATA_TYPE_TEXT
                ,'default' => ''
            ]
        ];
        return $settings;
    }

    public function getCallrailGlobalSettingSectionConfiguration()
    {
        $settings = [
            'cr_account_id' => [
                'title' => 'Client ID'
                ,'description' => 'Client ID'
                ,'type' => GlobalSetting::DATA_TYPE_TEXT
                ,'default' => ''
            ],
            'cr_token' => [
                'title' => 'Token'
                ,'description' => 'Token'
                ,'type' => GlobalSetting::DATA_TYPE_ENCRYPTED
                ,'default' => ''
            ]
        ];
        return $settings;
    }

    public function getInmomentGlobalSettingSectionConfiguration()
    {
        $settings = [
            'inm_client_id' => [
                'title' => 'Client Id'
                ,'description' => 'Client Id'
                ,'type' => GlobalSetting::DATA_TYPE_TEXT
                ,'default' => ''
            ],
            'inm_client_secret' => [
                'title' => 'Client Secret'
                ,'description' => 'Client Secret'
                ,'type' => GlobalSetting::DATA_TYPE_ENCRYPTED
                ,'default' => ''
            ],
            'inm_username' => [
                'title' => 'Username'
                ,'description' => 'Username'
                ,'type' => GlobalSetting::DATA_TYPE_TEXT
                ,'default' => ''
            ],
            'inm_password' => [
                'title' => 'Password'
                ,'description' => 'Password'
                ,'type' => GlobalSetting::DATA_TYPE_ENCRYPTED
                ,'default' => ''
            ],
            'inm_orgid' => [
                'title' => 'OrgID'
                ,'description' => 'OrgID'
                ,'type' => GlobalSetting::DATA_TYPE_TEXT
                ,'default' => ''
            ],
        ];
        return $settings;
    }

    public function getUberHealthGlobalSettingSectionConfiguration()
    {
        $settings = [
            'ub_client_id' => [
                'title' => 'Uber Health Client Id'
                ,'description' => 'Uber Health Client Id'
                ,'type' => GlobalSetting::DATA_TYPE_TEXT
                ,'default' => ''
            ],
            'ub_client_secret' => [
                'title' => 'Uber Health Client Secret'
                ,'description' => 'Uber Health Client Secret'
                ,'type' => GlobalSetting::DATA_TYPE_ENCRYPTED
                ,'default' => ''
            ]
        ];
        return $settings;
    }

    public function getCaseManagerOrdersGlobalSettingSectionConfiguration()
    {
        $settings = [
            'cmo_order_status' => [
                'title' => 'Order Status'
                ,'description' => 'Order Status'
                ,'type' => GlobalSetting::DATA_TYPE_TEXT
                ,'default' => ''
            ],
            'cmo_approval_order_status' => [
                'title' => 'Approval Order Status'
                ,'description' => 'Approval Order Status'
                ,'type' => GlobalSetting::DATA_TYPE_TEXT
                ,'default' => ''
            ],
            'cmo_denial_approval_status' => [
                'title' => 'Denial Approval Status'
                ,'description' => 'Denial Approval Status'
                ,'type' => GlobalSetting::DATA_TYPE_TEXT
                ,'default' => ''
            ]
        ];
        return $settings;
    }
}
