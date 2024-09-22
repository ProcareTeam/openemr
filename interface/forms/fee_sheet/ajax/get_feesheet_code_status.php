<?php

include_once("../../../globals.php");

$encounter = isset($_REQUEST['encounter']) ? $_REQUEST['encounter'] : array();
$pid = isset($_REQUEST['pid']) ? $_REQUEST['pid'] : '';

function getBillingInfo($encounter) {
	$results = array();

	$result = sqlStatement("SELECT bl.* FROM `billing` AS bl WHERE bl.encounter = ? AND bl.activity = 1 ",array($encounter));
	while ($row = sqlFetchArray($result)) {
		$results[] = $row;
	}

	return $results;
}

function isCodeExists($type, $code, $items = array()) {
	$status = false;
	foreach ($items as $key => $item) {
		if(isset($item['code_type']) && $item['code_type'] == $type && $item['code'] == $code) {
			$status = true;
		}
	}

	return $status;
}

function decodeCode($codeStr) {
	$codes = array();

	if(!empty($codeStr)) {
		$codeItems = explode(":", $codeStr);

		foreach ($codeItems as $key => $codeItem) {
			if(!empty($codeItem)) {
				$codeValues = explode("|", $codeItem);
				if(!empty($codeValues)) {
					$codes[] = array( 'code_type' => $codeValues[0], 'code' => $codeValues[1]);
				}
			}
		}
	}

	return $codes;
}

function validateCPTCode($encounter, $pid) {
	$billingData = getBillingInfo($encounter);
	$codeItems = array();
	$validationStatus = true;

	foreach ($billingData as $key => $item) {
		if(isset($item['code_type']) && !empty($item['code_type'])) {
			$codeItems[] = array( 'code_type' => $item['code_type'], 'code' => $item['code']);
		}
	}

	foreach ($billingData as $key => $bItem) {
		if(isset($bItem['code_type']) && (substr($bItem['code_type'], 0, 3 ) == "CPT" || substr($bItem['code_type'], 0, 5 ) == "HCPCS")) {
			if(isset($bItem['justify']) && empty($bItem['justify'])) {
				$validationStatus = false;
			}

			if(isset($bItem['justify']) && !empty($bItem['justify'])) {
				$codeValues = decodeCode($bItem['justify']);
				$icdStatus = false;

				foreach ($codeValues as $key => $jCode) {
					if(isset($jCode['code_type']) && substr($jCode['code_type'], 0, 3 ) == "ICD") {
						$codeValueStatus = isCodeExists($jCode['code_type'], $jCode['code'], $codeItems);
						if($codeValueStatus === true) {
							$icdStatus = true;
						}
					}
				}
				$validationStatus = $icdStatus;
			}
		}
	}

	return $validationStatus;
}

// OEMR - Get ICD10BilingCodes
function getICD10BilingCodes($codes = array()) {
    $results = array();
    if(empty($codes) || !is_array($codes)) return $results;
    $result = sqlStatement("SELECT icd10_dx_order_code.formatted_dx_code as code, icd10_dx_order_code.long_desc as code_text, icd10_dx_order_code.short_desc as code_text_short, codes.id, codes.code_type, codes.active, 'ICD10' as code_type_name FROM icd10_dx_order_code LEFT OUTER JOIN `codes` ON icd10_dx_order_code.formatted_dx_code = codes.code AND codes.code_type = (select ct.ct_id from code_types ct where ct.ct_key = 'ICD10' limit 1) WHERE icd10_dx_order_code.formatted_dx_code in ('". implode("','", $codes) ."') and icd10_dx_order_code.active='1' AND icd10_dx_order_code.valid_for_coding = '1' AND (codes.active = 1 || codes.active IS NULL) ORDER BY icd10_dx_order_code.formatted_dx_code+0,icd10_dx_order_code.formatted_dx_code",array());

    while ($row = sqlFetchArray($result)) {
        if(isset($row['code']) && !empty($row['code'])) {
            $results[] = $row['code'];
        }
    }

    return $results;
}

function checkValidICDCode($encounter, $pid) {
	$billingData = getBillingInfo($encounter);
	$codeList = array();

	foreach ($billingData as $key => $item) {
		if(isset($item['code_type']) && $item['code_type'] == "ICD10") {
			$codeList[] = $item['code'];
		}
	}

	$validCodeList = array();
    if(!empty($codeList)) $validCodeList = getICD10BilingCodes($codeList);

    return array_values(array_diff($codeList,$validCodeList));
}

// @VH: Check Valid Code Before Esign [2023011610] 
$status = validateCPTCode($encounter, $pid);
$invalidcodeStatus = checkValidICDCode($encounter, $pid);

echo json_encode(array(
	'feesheet_code_status' => $status,
	'invalid_code' => $invalidcodeStatus
));