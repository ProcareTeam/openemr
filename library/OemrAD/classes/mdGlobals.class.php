<?php

namespace OpenEMR\OemrAd;

class Globals {
	
	function __construct(){
	}

	/*Global Fields*/
	public static function setupGlobalField(&$GLOBALS_METADATA, &$USER_SPECIFIC_TABS, &$USER_SPECIFIC_GLOBALS) {
		self::ZoomIntegration($GLOBALS_METADATA, $USER_SPECIFIC_TABS, $USER_SPECIFIC_GLOBALS);
		self::ShortenLink($GLOBALS_METADATA, $USER_SPECIFIC_TABS, $USER_SPECIFIC_GLOBALS);
		self::Utility($GLOBALS_METADATA, $USER_SPECIFIC_TABS, $USER_SPECIFIC_GLOBALS);
		self::Smslib($GLOBALS_METADATA, $USER_SPECIFIC_TABS, $USER_SPECIFIC_GLOBALS);
		self::Twiliolib($GLOBALS_METADATA, $USER_SPECIFIC_TABS, $USER_SPECIFIC_GLOBALS);
		self::ApiLib($GLOBALS_METADATA, $USER_SPECIFIC_TABS, $USER_SPECIFIC_GLOBALS);
		self::CaseLib($GLOBALS_METADATA, $USER_SPECIFIC_TABS, $USER_SPECIFIC_GLOBALS);
		self::CoverageCheck($GLOBALS_METADATA, $USER_SPECIFIC_TABS, $USER_SPECIFIC_GLOBALS);
		self::EmailMessage($GLOBALS_METADATA, $USER_SPECIFIC_TABS, $USER_SPECIFIC_GLOBALS);
		self::FaxMessage($GLOBALS_METADATA, $USER_SPECIFIC_TABS, $USER_SPECIFIC_GLOBALS);
		self::PostalLetter($GLOBALS_METADATA, $USER_SPECIFIC_TABS, $USER_SPECIFIC_GLOBALS);
        self::IdempiereConnection($GLOBALS_METADATA, $USER_SPECIFIC_TABS, $USER_SPECIFIC_GLOBALS);
        self::MauticConnection($GLOBALS_METADATA, $USER_SPECIFIC_TABS, $USER_SPECIFIC_GLOBALS);
        self::ProCareMedicalWebhooks($GLOBALS_METADATA, $USER_SPECIFIC_TABS, $USER_SPECIFIC_GLOBALS);
        self::ICSConfig($GLOBALS_METADATA, $USER_SPECIFIC_TABS, $USER_SPECIFIC_GLOBALS);
	}

	public static function ZoomIntegration(&$GLOBALS_METADATA, &$USER_SPECIFIC_TABS, &$USER_SPECIFIC_GLOBALS) {
		/*Configure For Calnder*/

		/*$GLOBALS_METADATA['Zoom Integration']['zoom_user_id'] = array(
			xl('UserId Or Email Id'),
            'text',                           // data type
            '',                      // default
            xl('To performance zoom api operations.')
		);

		$GLOBALS_METADATA['Zoom Integration']['zoom_api_key'] = array(
			xl('API Key'),
            'text',                           // data type
            '',                      // default
            xl('To performance zoom api operations.')
		);

		$GLOBALS_METADATA['Zoom Integration']['zoom_api_secret'] = array(
			xl('API Secret'),
            'text',                           // data type
            '',                      // default
            xl('To performance zoom api operations.')
		);*/

        $GLOBALS_METADATA['Zoom Integration']['zoom_account_id'] = array(
            xl('Account ID'),
            'text',                           // data type
            '',                      // default
            xl('To performance zoom api operations.')
        );

        $GLOBALS_METADATA['Zoom Integration']['zoom_client_id'] = array(
            xl('Client ID'),
            'text',                           // data type
            '',                      // default
            xl('To performance zoom api operations.')
        );

        $GLOBALS_METADATA['Zoom Integration']['zoom_client_secret'] = array(
            xl('Client Secret'),
            'encrypted',                           // data type
            '',                      // default
            xl('To performance zoom api operations.')
        );

        $GLOBALS_METADATA['Zoom Integration']['zoom_secret_token'] = array(
            xl('Secret Token'),
            'encrypted',                           // data type
            '',                      // default
            xl('To performance zoom api operations.')
        );

        /*
		$GLOBALS_METADATA['Zoom Integration']['zoom_access_token'] = array(
			xl('API Access Token'),
            'text',                           // data type
            '',                      // default
            xl('To performance zoom api operations.')
		);
        */

		$GLOBALS_METADATA['Zoom Integration']['zoom_appt_category'] = array(
			xl('Appoiment Category For Zoom Meeting'),
            'textarea',                           // data type
            '',                      // default
            xl('Create Zoom meeting for mentioned category')
		);

		$GLOBALS_METADATA['Zoom Integration']['zoom_appt_facility'] = array(
			xl('Appoiment Facility For Zoom Meeting'),
            'textarea',                           // data type
            '',                      // default
            xl('Create Zoom meeting for mentioned facility')
		);

		$GLOBALS_METADATA['Zoom Integration']['zoom_notify_event_id'] = array(
			xl('Event Id'),
            'textarea',                           // data type
            '',                      // default
            xl('Event Id')
		);

		$GLOBALS_METADATA['Zoom Integration']['zoom_notify_config_id'] = array(
			xl('Config Id'),
            'textarea',                           // data type
            '',                      // default
            xl('Config Id')
		);

        $GLOBALS_METADATA['Zoom Integration']['zoom_send_config_id'] = array(
            xl('Send Details Event/Config Id'),
            'textarea',                           // data type
            '',                      // default
            xl('Send Details Event/Config Id')
        );

        $GLOBALS_METADATA['Zoom Integration']['zoom_cohost_email'] = array(
            xl('Co-Host Email List'),
            'text',                           // data type
            '',                      // default
            xl('Co-Host Email List')
        );
	}

	public static function ShortenLink(&$GLOBALS_METADATA, &$USER_SPECIFIC_TABS, &$USER_SPECIFIC_GLOBALS) {

		/*Configure For Calnder*/
		$GLOBALS_METADATA['ShortenLink']['shortenlink_service'] = array(
			xl('ShortLink Service'),
            array(
                'bitly' => xl('Bitly'),
                'tinyurl' => xl('Tinyurl'),
                'shlink' => xl('Shlink'),
                'yourls' => xl('Yourls'),
            ),
            'bitly',
            xl('ShortLink Service')
		);

		$GLOBALS_METADATA['ShortenLink']['shortenlink_access_token'] = array(
			xl('Access Token'),
            'encrypted',                           // data type
            '',                      // default
            xl('Access Token.')
		);

		$GLOBALS_METADATA['ShortenLink']['shlink_domain'] = array(
			xl('Domain (Shlink/Yourls)'),
            'text',                           // data type
            '',                      // default
            xl('Domain (Shlink/Yourls)')
		);

		$GLOBALS_METADATA['ShortenLink']['shortenlink_username'] = array(
			xl('Username (Yourls)'),
            'text',                           // data type
            '',                      // default
            xl('Username (Yourls)')
		);

		$GLOBALS_METADATA['ShortenLink']['shortenlink_password'] = array(
			xl('Password (Yourls)'),
            'encrypted',                           // data type
            '',                      // default
            xl('Password (Yourls)')
		);
	}

	public static function Utility(&$GLOBALS_METADATA, &$USER_SPECIFIC_TABS, &$USER_SPECIFIC_GLOBALS) {

		/*Configure For Calnder*/
		$GLOBALS_METADATA['Calendar']['disable_calendar_availability_popup'] = array(
			xl('Don’t check availability'),
			'bool',                           // data type
			'0',                               // default
			xl('Don’t check availability')
		);

		$GLOBALS_METADATA['Notifications']['alert_log_recipient'] = array(
			xl('Alert log recipient'),
            'text',                           // data type
            '',                      // default
            xl('Alert log recipient')
		);

        $GLOBALS_METADATA['Notifications']['abook_hubspot_sync'] = array(
            xl('Addressbook Hubspot Sync'),
            'bool',                           // data type
            '0',                      // default
            xl('Addressbook Hubspot Sync')
        );

		$GLOBALS_METADATA['Notifications']['hubspot_listener_sync_config'] = array(
			xl('Hubspot Listener Sync Config'),
            'textarea',                           // data type
            '',                      // default
            xl('To sync hubspot data.')
		);

		// $GLOBALS_METADATA['Calendar']['disable_availability_popup'] = array(
		// 	xl('Don’t check Provider availability'),
		// 	'bool',                           // data type
		// 	'0',                               // default
		// 	xl('Don’t check Provider availability')
		// );

        // $GLOBALS_METADATA['Calendar']['disable_appointment_availability_popup'] = array(
        //     xl('Allow overlapping of appointments'),
        //     'bool',                           // data type
        //     '0',                               // default
        //     xl('Don’t check Provider availability')
        // );
	}

	public static function Smslib(&$GLOBALS_METADATA, &$USER_SPECIFIC_TABS, &$USER_SPECIFIC_GLOBALS) {
		/*Configure For Calnder*/
		$GLOBALS_METADATA['SMS Service']['SMS_SERVICE_TYPE'] = array(
			xl('SMS Service'),
            array(
                'nexmo' => xl('Nexmo'),
                'twilio' => xl('Twilio'),
            ),
            'nexmo',
            xl('SMS Service')
		);

		$GLOBALS_METADATA['SMS Service']['EXTRA_SMS_TEXT'] = array(
			xl('Extra SMS Text'),
            'text',                           // data type
            '',                      // default
            xl('Extra SMS Text')
		);

		$GLOBALS_METADATA['SMS Service']['EXTRA_SMS_TEXT_INTERVAL'] = array(
			xl('SMS Text Day Interval Day'),
            'text',                           // data type
            '30',                      // default
            xl('SMS Text Day Interval Day')
		);

		$GLOBALS_METADATA['Notifications']['APPT_CONFIRM_CONFIG_ID'] = array(
			xl('Special Appointment Confirmation Config Id'),
            'text',                           // data type
            '',                      // default
            xl('Special Appointment Confirmation Config Id')
		);
	}

	public static function Twiliolib(&$GLOBALS_METADATA, &$USER_SPECIFIC_TABS, &$USER_SPECIFIC_GLOBALS) {
		/*Configure For Calnder*/
		$GLOBALS_METADATA['Twilio Integration']['SMS_TWILIO_ACCOUNT_SID'] = array(
			xl('Twilio Account SID'),
            'text',                           // data type
            '',                      // default
            xl('To performance Twilio api operations.')
		);

		$GLOBALS_METADATA['Twilio Integration']['SMS_TWILIO_AUTH_TOKEN'] = array(
			xl('Twilio Auth Token Token'),
            'encrypted',                           // data type
            '',                      // default
            xl('To performance Twilio api operations.')
		);

		$GLOBALS_METADATA['Twilio Integration']['SMS_TWILIO_DEFAULT_FROM'] = array(
			xl('Twilio From Number'),
            'text',                           // data type
            '',                      // default
            xl('To performance Twilio api operations.')
		);

		$GLOBALS_METADATA['Twilio Integration']['SMS_TWILIO_SITE_URL'] = array(
			xl('Site URL'),
            'text',                           // data type
            '',                      // default
            xl('Site URL')
		);
	}

	public static function ApiLib(&$GLOBALS_METADATA, &$USER_SPECIFIC_TABS, &$USER_SPECIFIC_GLOBALS) {
		$GLOBALS_METADATA['Apis']['oemr_api_token'] = array(
			xl('Api Key'),
            'encrypted',                           // data type
            '',                      // default
            xl('Api key for auth')
		);

		$GLOBALS_METADATA['Apis']['oemr_xibo_appt_cat'] = array(
			xl('Xibo Appt Categories'),
            'text',                           // data type
            '',                      // default
            xl('Xibo Appt Categories')
		);
	}

	public static function CaseLib(&$GLOBALS_METADATA, &$USER_SPECIFIC_TABS, &$USER_SPECIFIC_GLOBALS) {
		$GLOBALS_METADATA['Notifications']['ct_notification_categories'] = array(
			xl('Care Team Notification Categories'),
            'textarea',                           // data type
            '',                      // default
            xl('To send care team notification')
		);
	}

	public static function EmailMessage(&$GLOBALS_METADATA, &$USER_SPECIFIC_TABS, &$USER_SPECIFIC_GLOBALS) {
		$GLOBALS_METADATA['Notifications']['IMAP_SERVER_URL'] = array(
			xl('IMAP Server URL'),
            'text',                           // data type
            '',                      // default
            xl('IMAP Server URL used for get incoming emails')
		);
		
		$GLOBALS_METADATA['Notifications']['IMAP_USER'] = array(
            xl('IMAP User for Authentication'),
            'text',                           // data type
            '',                               // default
            xl('Must be empty if IMAP authentication is not used.')
        );

        $GLOBALS_METADATA['Notifications']['IMAP_PASS'] = array(
            xl('IMAP Password for Authentication'),
            'encrypted',                           // data type
            '',                               // default
            xl('Must be empty if IMAP authentication is not used.')
        );

        $GLOBALS_METADATA['Notifications']['IMAP_ON_MESSAGE_BOARD_PAGE_SYNC'] = array(
						xl('Sync Emails On Message Board Page'),
            array(
                'true' => 'True',
                'false' => 'False'
            ),                          // data type
            'false',                      // default
            xl('Sync email on message board page')
				);

        $GLOBALS_METADATA['Notifications']['IMAP_ON_PAGE_SYNC'] = array(
						xl('IMAP On Page Sync Run'),
            array(
                'true' => 'True',
                'false' => 'False'
            ),                          // data type
            'true',                      // default
            xl('Used to run sync email on page')
				);

				$GLOBALS_METADATA['Notifications']['IMAP_DELETE_AFTER_SYNC'] = array(
						xl('IMAP Delete Email After Sync'),
            array(
                'true' => 'Yes',
                'false' => 'No'
            ),                          // data type
            'false',                      // default
            xl('Used for delete email after sync.')
				);

				$GLOBALS_METADATA['Notifications']['SYNC_EXIST_USER_EMAIL'] = array(
            xl('Sync Emails For NonExisting Email Addresses'),
            array(
                'true' => 'True',
                'false' => 'False'
            ),                          // data type
            'false',                                  // default
            xl('Sync Emails For NonExisting Email Addresses')
        );

        $GLOBALS_METADATA['Notifications']['EMAIL_SEND_FROM'] = array(
           xl('SMTP send from (Email)'),
            'text',                           // data type
            'PATIENT SUPPORT',                // default
            xl('SMTP send from (Email)')
        );

        $GLOBALS_METADATA['Notifications']['EMAIL_MAX_ATTACHMENT_SIZE'] = array(
           xl('Email Max Attachment Size in MB'),
            'text',                           // data type
            '10',                               // default
            xl('Email Max Attachment Size in MB')
        );

        $GLOBALS_METADATA['Notifications']['EMAIL_FROM_NAME'] = array(
           xl('Email From name'),
            'text',                           // data type
            '',                               // default
            xl('Email From name')
        );

        $GLOBALS_METADATA['PDF']['pdf_header_margin'] = array(
           xl('Header margin (mm)'),
            'text',                           // data type
            '0',                               // default
            xl('Header margin (mm)')
        );

        $GLOBALS_METADATA['PDF']['pdf_footer_margin'] = array(
           xl('Footer margin (mm)'),
            'text',                           // data type
            '0',                               // default
            xl('Header margin (mm)')
        );
	}

	public static function FaxMessage(&$GLOBALS_METADATA, &$USER_SPECIFIC_TABS, &$USER_SPECIFIC_GLOBALS) {
		$GLOBALS_METADATA['Fax']['FAX_USER'] = array(
            xl('Fax User for Authentication'),
            'text',                           // data type
            '',                               // default
            xl('Must be empty if Fax authentication is not used.')
        );

        $GLOBALS_METADATA['Fax']['FAX_PASS'] = array(
            xl('Fax Password for Authentication'),
            'encrypted',                           // data type
            '',                               // default
            xl('Must be empty if Fax authentication is not used.')
        );

        $GLOBALS_METADATA['Fax']['FAX_SRC'] = array(
            xl('vfax DID to send fax from'),
            'text',                           // data type
            '',                               // default
            xl('The vfax DID to send fax from')
        );

        $GLOBALS_METADATA['Fax']['FAX_CHECK_STATUS_AFTER'] = array(
            xl('Check Fax Status After MIN'),
            'text',                           // data type
            '',                               // default
            xl('Check Fax Status After Min')
        );

        $GLOBALS_METADATA['Fax']['FAX_INITIAL_COST'] = array(
            xl('Initial Cost'),
            'text',                           // data type
            '',                               // default
            xl('Initial Cost')
        );

        $GLOBALS_METADATA['Fax']['FAX_ADDITIONAL_COST'] = array(
            xl('Additional Page Cost'),
            'text',                           // data type
            '',                               // default
            xl('Additional Page Cost')
        );

        $GLOBALS_METADATA['Fax']['FAX_LIMIT_COST'] = array(
            xl('Limit Cost'),
            'text',                           // data type
            '',                               // default
            xl('Limit Cost')
        );
	}

	public static function PostalLetter(&$GLOBALS_METADATA, &$USER_SPECIFIC_TABS, &$USER_SPECIFIC_GLOBALS) {
        $GLOBALS_METADATA['Postal Letter']['POSTAL_LETTER_SECRETKEY'] = array(
            xl('Postal Letter Secret Key'),
            'encrypted',                           // data type
            '',                               // default
            xl('Must be empty if Postal Letter is not used.')
        );

        $GLOBALS_METADATA['Postal Letter']['POSTAL_LETTER_WORKMODE'] = array(
            xl('Postal Letter WorkMode'),
            array(
                'Default' => xl('Default'),
                'Production' => xl('Production'),
                'Development' => xl('Development'),
            ),
            'Default',                               // default
            xl('Postal Letter WorkMode')
        );


        $GLOBALS_METADATA['Postal Letter']['POSTAL_LETTER_SEL_REPlY_ADDRESS'] = array(
            xl('Select Reply Address'),
            'text',                           // data type
            '',                               // default
            xl('Select Reply Address')
        );

        $GLOBALS_METADATA['Postal Letter']['POSTAL_LETTER_REPlY_ADDRESS'] = array(
            xl('Reply Address'),
            'textarea',                           // data type
            '',                               // default
            xl('Reply Address')
        );

        $GLOBALS_METADATA['Postal Letter']['POSTAL_LETTER_REPlY_ADDRESS_JSON'] = array(
            xl('REPlY_ADDRESS_JSON'),
            'textarea',                           // data type
            '""',                               // default
            xl('REPlY_ADDRESS_JSON')
        );

        $GLOBALS_METADATA['Postal Letter']['POSTAL_LETTER_INITIAL_COST'] = array(
            xl('Initial Cost'),
            'text',                           // data type
            '',                               // default
            xl('Initial Cost')
        );

        $GLOBALS_METADATA['Postal Letter']['POSTAL_LETTER_ADDITIONAL_COST'] = array(
            xl('Additional Page Cost'),
            'text',                           // data type
            '',                               // default
            xl('Additional Page Cost')
        );

        $GLOBALS_METADATA['Postal Letter']['POSTAL_LETTER_LIMIT_COST'] = array(
            xl('Limit Cost'),
            'text',                           // data type
            '',                               // default
            xl('Limit Cost')
        );

		// Configure For Email Verification API
        $GLOBALS_METADATA['Email Verification']['email_verification_api'] = array(
            xl('Email Verification API'),
            'encrypted',                           // data type
            '',                               // default
            xl('Email Verification API')
        );
	}

	public static function CoverageCheck(&$GLOBALS_METADATA, &$USER_SPECIFIC_TABS, &$USER_SPECIFIC_GLOBALS) {
		// Configure For Postal Letter
        $GLOBALS_METADATA['Availity']['availity_client_id'] = array(
            xl('Availity Client Id'),
            'text',                           // data type
            '',                               // default
            xl('Availity Client Id')
        );

		$GLOBALS_METADATA['Availity']['availity_client_secret'] = array(
            xl('Availity Client Secret'),
            'encrypted',                           // data type
            '',                               // default
            xl('Availity Client Secret')
        );

        $GLOBALS_METADATA['Availity']['default_provider'] = array(
            xl('Default provider for Coverage Eligibility Check'),
            'text',                           // data type
            '',                               // default
            xl('Default provider for Coverage Eligibility Check')
        );

        $GLOBALS_METADATA['Availity']['blank_provider'] = array(
            xl('Blank provider for Coverage Eligibility Check'),
            'text',                           // data type
            '',                               // default
            xl('Blank provider for Coverage Eligibility Check')
        );

        /*
        $GLOBALS_METADATA['Availity']['rq_taxidforinsurance'] = array(
            xl('Insurances which require provider federal tax id.'),
            'text',                           // data type
            '',                               // default
            xl('Insurances which require provider federal tax id.')
        );*/

        $GLOBALS_METADATA['Availity']['av_default_providerType'] = array(
            xl('Default ProviderType'),
            array(
                'AT' => xl('Professional'),
                'H' => xl('Institutional')
            ),
            'AT',                               // default
            xl('Default ProviderType')
        );

        $GLOBALS_METADATA['Availity']['av_default_placeOfService'] = array(
            xl('Default PlaceOfService'),
            array(
                '02' => xl('Telehealth'),
                '11' => xl('Office'),
                '12' => xl('Home'),
                '21' => xl('Inpatient Hospital'),
                '22' => xl('On Campus-Outpatient Hospital'),
                '23' => xl('Emergency Room - Hospital'),
                '99' => xl('Other Place of Service')
            ),
            '11',                               // default
            xl('Default PlaceOfService')
        );
	}

    public static function IdempiereConnection(&$GLOBALS_METADATA, &$USER_SPECIFIC_TABS, &$USER_SPECIFIC_GLOBALS) {
        // Configure For Postal Letter
        $GLOBALS_METADATA['Idempiere Connection']['idempiere_host'] = array(
            xl('Idempiere Host'),
            'text',                           // data type
            '',                               // default
            xl('Idempiere Host')
        );
        $GLOBALS_METADATA['Idempiere Connection']['idempiere_port'] = array(
            xl('Idempiere Port'),
            'text',                           // data type
            '',                               // default
            xl('Idempiere Port')
        );
        $GLOBALS_METADATA['Idempiere Connection']['idempiere_db'] = array(
            xl('Idempiere Database'),
            'text',                           // data type
            '',                               // default
            xl('Idempiere Database')
        );
        $GLOBALS_METADATA['Idempiere Connection']['idempiere_db_user'] = array(
            xl('Idempiere User'),
            'text',                           // data type
            '',                               // default
            xl('Idempiere User')
        );
        $GLOBALS_METADATA['Idempiere Connection']['idempiere_db_password'] = array(
            xl('Idempiere Password'),
            'encrypted',                           // data type
            '',                               // default
            xl('Idempiere Password')
        );
        $GLOBALS_METADATA['Idempiere Connection']['idempiere_client_id'] = array(
            xl('Idempiere Client Id'),
            'text',                           // data type
            '',                               // default
            xl('Idempiere Client Id')
        );
    }
    public static function MauticConnection(&$GLOBALS_METADATA, &$USER_SPECIFIC_TABS, &$USER_SPECIFIC_GLOBALS) {
        // Configure For Postal Letter
        $GLOBALS_METADATA['Mautic Connection']['mautic_host'] = array(
            xl('Mautic Host'),
            'text',                           // data type
            '',                               // default
            xl('Mautic Host')
        );
        $GLOBALS_METADATA['Mautic Connection']['mautic_username'] = array(
            xl('Mautic User'),
            'text',                           // data type
            '',                               // default
            xl('Mautic User')
        );
        $GLOBALS_METADATA['Mautic Connection']['mautic_password'] = array(
            xl('Mautic Password'),
            'encrypted',                           // data type
            '',                               // default
            xl('Mautic Password')
        );
        $GLOBALS_METADATA['Mautic Connection']['mautic_contact_owner'] = array(
            xl('Mautic Contact Owner'),
            'text',                           // data type
            '',                               // default
            xl('Mautic Contact Owner')
        );
    }
    public static function ProCareMedicalWebhooks(&$GLOBALS_METADATA, &$USER_SPECIFIC_TABS, &$USER_SPECIFIC_GLOBALS) {
        // Configure For Postal Letter
        $GLOBALS_METADATA['Wordpress Chat Integration']['webhook_url'] = array(
            xl('Wordpress Chat Webhook URL'),
            'text',                           // data type
            '',                               // default
            xl('Wordpress Chat Webhook URL')
        );
        $GLOBALS_METADATA['Wordpress Chat Integration']['webhook_token'] = array(
            xl('Webhook Token'),
            'encrypted',                           // data type
            '',                               // default
            xl('Webhook Token')
        );
        $GLOBALS_METADATA['Wordpress Chat Integration']['webhook_userid'] = array(
            xl('SupportBoard User ID'),
            'text',                           // data type
            '',                               // default
            xl('SupportBoard User ID')
        );
        $GLOBALS_METADATA['Wordpress Chat Integration']['webhook_datefilter'] = array(
            xl('Recent Messages Days'),
            'text',                           // data type
            '',                               // default
            xl('Recent Messages Days')
        );

        $GLOBALS_METADATA['Wordpress Chat Integration']['websocket_host'] = array(
            xl('Notification Websocket Host'),
            'text',                           // data type
            '',                               // default
            xl('Notification Websocket Host')
        );

        $GLOBALS_METADATA['Wordpress Chat Integration']['websocket_port'] = array(
            xl('Notification Websocket Port'),
            'text',                           // data type
            '',                               // default
            xl('Notification Websocket Port')
        );

        $GLOBALS_METADATA['Wordpress Chat Integration']['websocket_address_type'] = array(
            xl('Notification Websocket Address Type'),
            array(
                'ws' => xl('ws'),
                'wss' => xl('wss')
            ),
            'ws',                               // default
            xl('Notification Websocket Address Type')
        );
    }
    public static function ICSConfig(&$GLOBALS_METADATA, &$USER_SPECIFIC_TABS, &$USER_SPECIFIC_GLOBALS) {
        $GLOBALS_METADATA['ICS File Configs'] = array(
            'S3_BUCKET_KEY' => array(
                xl('Amazon S3 Bucket Key'),
                'text',                           // data type
                '',
                xl('Amazon S3 Bucket Key')
            ),
            'S3_BUCKET_SECRET_KEY' => array(
                xl('Amazon S3 Bucket Secret Key'),
                'encrypted',                           // data type
                '',                              
                xl('Amazon S3 Bucket Secret Key')
            ),
            'S3_BUCKET_NAME' => array(
                xl('Amazon S3 Bucket Name'),
                'text',                           // data type
                '',                              
                xl('Amazon S3 Bucket Name')
            ),
            'S3_BUCKET_REGION' => array(
                xl('Amazon S3 Bucket Region'),
                'text',                           // data type
                '',                              
                xl('Amazon S3 Bucket Region')
            ),
            'S3_BUCKET_VERSION' => array(
                xl('Amazon S3 Bucket Version'),
                'text',                           // data type
                '',                               // default
                xl('Amazon S3 Bucket Version')
            ),
            'ics_attachment' => array(
                xl('Attach ICS File'),
                'bool',                           // data type
                '0',                              // default = false
                xl('This feature will allow the to attach ics file in mail.')
            ),
            'ICS_Link_Name' => array(
                xl('ICS Link Name'),
                'text',                           // data type
                '',                               // default
                xl('ICS Link Name')
            )
        );
    }
    
}