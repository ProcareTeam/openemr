<?php

namespace OpenEMR\OemrAd;


class EmailVerificationLib {
	
	/*Constructor*/
	public function __construct() {	
	}

	/*
	Author: Hardik Khatri
	Description: Get verification results
	*/
	public static function getEmailVerificationResults($field_value) {
		if(!empty($field_value)) {
			$sql = sqlStatement("SELECT * FROM email_verifications WHERE field_value = ? ", array($field_value));

			$records = sqlFetchArray($sql);
			if($records) {
				return $records;
			}
		}
		return false;
	}

	/*
	Author: Hardik Khatri
	Description: Update or Save email verificatio [V100050]
	*/
	public static function updateEmailVerification($data) {
		$hvFieldName = "hidden_verification_status";

		foreach ($data as $dKey => $dItem) {
			if (substr( $dKey, -strlen($hvFieldName) ) === $hvFieldName) {
				$form_field = str_replace("_" . $hvFieldName,"",$dKey);
				$hidden_verification_status = isset($data[$dKey]) ? $data[$dKey] : 0;

				if(isset($data[$form_field])) {
					$field_name = str_replace("form_","",$form_field);
					$form_field_value = isset($data[$form_field]) ? $data[$form_field] : "";

					if($field_name && $form_field_value != "") {
						$isRecordExists = self::getEmailVerificationResults($form_field_value);

						if($isRecordExists) {
							sqlQuery("UPDATE email_verifications SET verification_status = ? WHERE field_value = ?", array($hidden_verification_status, $form_field_value));
						} else if ($hidden_verification_status !== "0") {
							$query = "INSERT INTO email_verifications ( field_value, verification_status) VALUES ( ?,? )";
					        sqlStatement($query, array($form_field_value, $hidden_verification_status));
						}
					}
				}
			}
		}
	}

	/*
	Author: Hardik Khatri
	Description: Get email verification content
	*/

	public static function getEmailVerificationData($field_value = '') {
		$vStatusFlag = 0;

		if(!empty($field_value)) {
			$records = self::getEmailVerificationResults($field_value);

			if($records != false && is_array($records)) {
				$vfield_value = attr($records['field_value']);
				$vStatus = attr($records['verification_status']);

				if($vStatus == "1") {
					$vStatusFlag = 1;
				}
			}
		}

		return $vStatusFlag;
	}

	/*
	Author: Hardik Khatri
	Description: Javascript functions for "new_comprehensive.php" 
	*/
	public static function getScript() {
		return <<<EOF
			<script type="text/javascript">
			</script>
EOF;
	}
}
