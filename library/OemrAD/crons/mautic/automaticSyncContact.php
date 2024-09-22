<?php

if(!isset($_SERVER['SERVER_NAME']) || empty($_SERVER['SERVER_NAME'])) {
    $_SERVER['REQUEST_URI']=$_SERVER['PHP_SELF'];
    $_SERVER['SERVER_NAME']='localhost';
    $_SERVER['HTTP_HOST'] = 'localhost';
    $_GET['site'] = 'default';
}
$backpic = "";
$ignoreAuth=1;

require_once(dirname( __FILE__, 3 ) . "/interface/globals.php");

use OpenEMR\Common\Crypto\CryptoGen;
use OpenEMR\Core\Header;

$mautic_host = $GLOBALS['mautic_host'];
$mautic_user = $GLOBALS['mautic_username'];
$mautic_pass = $cryptoGen->decryptStandard($GLOBALS['mautic_password']);

$filename = "Test.php";
if($mautic_host == NULL || $mautic_user == NULL || $mautic_pass == NULL) {
    echo "Please enter mautic credential";
    exit();
}

$idb_host = $GLOBALS['idempiere_host'];
$idb_user = $GLOBALS['idempiere_db_user'];
$idb_pass = $cryptoGen->decryptStandard($GLOBALS['idempiere_db_password']);
$idb_port = $GLOBALS['idempiere_port'];
$idb = $GLOBALS['idempiere_db'];
$idb_client_id = $GLOBALS['idempiere_client_id'];

$sql = sqlStatement("SELECT
cs.comments,
cs.employer,
emp.upin,
emp.organization as emporganization,
	cs.id as vh_oemr_uniquekey,
	cs.id,
	cs.case_description,
	cs.pid,
	pd.pubpid,
	cs.case_guarantor_pid,
	gpd.pubpid as guarantor_pubpid,
	cs.provider_id,
	(
	SELECT
		hl7doc.hl7_doctor
	FROM
		hl7_doctor_xlat as hl7doc
	WHERE
		hl7doc.oemr_doctor = cs.provider_id limit 1) as hl7_provider_id,
	cs.referring_id,
	coalesce((
	SELECT
		hl7doc.hl7_doctor
	FROM
		hl7_doctor_xlat as hl7doc
	WHERE
		hl7doc.oemr_doctor = cs.referring_id limit 1),(select uu.npi from users uu where uu.id=cs.referring_id limit 1))  as hl7_referring_id,
	cs.referral_source,
	cs.injury_date,
	cs.first_consult_date,
	cs.cash,
	cs.closed,
	cs.accident_state,
	cs.ins_data_id1,
	cs.ins_data_id2,
	cs.ins_data_id3,
	cs.notes,
	(
	SELECT
		hl7ins.hl7_ins_id
	FROM
		hl7_ins_xlat as hl7ins
	WHERE
		hl7ins.oemr_id = insd1.provider limit 1) as hl7_ins_id1,
	(
	SELECT
		hl7ins.hl7_ins_id
	FROM
		hl7_ins_xlat as hl7ins
	WHERE
		hl7ins.oemr_id = insd2.provider limit 1) as hl7_ins_id2,
	(
	SELECT
		hl7ins.hl7_ins_id
	FROM
		hl7_ins_xlat as hl7ins
	WHERE
		hl7ins.oemr_id = insd3.provider limit 1) as hl7_ins_id3,
	insd1.subscriber_relationship as subscriber_relationship1,
	insd2.subscriber_relationship as subscriber_relationship2,
	insd3.subscriber_relationship as subscriber_relationship3,
	insd1.policy_number as policy_number1,
	insd2.policy_number as policy_number2,
	insd3.policy_number as policy_number3,
	insd1.group_number as group_number1,
	insd2.group_number as group_number2,
	insd3.group_number as group_number3,
	inspd1.pubpid as subscriber_pubpid1,
	inspd2.pubpid as subscriber_pubpid2,
	inspd3.pubpid as subscriber_pubpid3,ic1.name as inscomp1,
	ic2.name as inscomp2,ic3.name as inscomp3,
	insd1.subscriber_fname as subscriber_fname1, 
	insd1.subscriber_mname as subscriber_mname1, 
	insd1.subscriber_lname as subscriber_lname1,   
	insd1.subscriber_DOB as subscriber_DOB1, 
	insd1.subscriber_ss as subscriber_ss1, 
	insd1.subscriber_street as subscriber_street1,
	insd1.subscriber_city as subscriber_city1,
	insd1.subscriber_postal_code as subscriber_postal_code1,
	insd1.subscriber_state as subscriber_state1,
	insd2.subscriber_fname as subscriber_fname2, 
	insd2.subscriber_mname as subscriber_mname2, 
	insd2.subscriber_lname as subscriber_lname2,   
	insd2.subscriber_DOB as subscriber_DOB2, 
	insd2.subscriber_ss as subscriber_ss2, 
	insd2.subscriber_street as subscriber_street2,
	insd2.subscriber_city as subscriber_city2,
	insd2.subscriber_postal_code as subscriber_postal_code2,
	insd2.subscriber_state as subscriber_state2,
	insd3.subscriber_fname as subscriber_fname3, 
	insd3.subscriber_mname as subscriber_mname3, 
	insd3.subscriber_lname as subscriber_lname3,   
	insd3.subscriber_DOB as subscriber_DOB3, 
	insd3.subscriber_ss as subscriber_ss3, 
	insd3.subscriber_street as subscriber_street3,
	insd3.subscriber_city as subscriber_city3,
	insd3.subscriber_postal_code as subscriber_postal_code3,
	insd3.subscriber_state as subscriber_state3,insd1.subscriber_sex as subscriber_sex1,
	insd2.subscriber_sex as subscriber_sex2,
	insd3.subscriber_sex as subscriber_sex3,
	insd1.provider as insuranceid1, 
	insd2.provider as insuranceid2, 
	insd3.provider as insuranceid3,
	ic1.name as name_payer1,
	ic1.attn as attn_payer1,
	coalesce(ic1.cms_id,ic1.alt_cms_id) payerID_payer1,
	coalesce((SELECT hl7ins.hl7_ins_id FROM hl7_ins_xlat as hl7ins WHERE hl7ins.oemr_id = ic1.id),ic1.name) as hl7_ins_id_payer1,
	ic1.`ins_type_code` as ins_type_code_payer1, 
	ad1.`line1` as line1_payer1, 
	ad1.`line2` as line2_payer1, 
	ad1.`city` as city_payer1, 
	ad1.`state` as state_payer1, 
	ad1.`zip` as zip_payer1, 
	ad1.`country` as country_payer1,
	CONCAT('', ph1.`area_code`, ph1.`prefix`, ph1.`number`) AS phone_payer1, 
	CONCAT('', fx1.`area_code`, fx1.`prefix`, fx1.`number`) AS fax_payer1,
	ic2.name as name_payer2,
	ic2.attn as attn_payer2,
	coalesce(ic2.cms_id,ic2.alt_cms_id) payerID_payer2,
	coalesce((SELECT hl7ins.hl7_ins_id FROM hl7_ins_xlat as hl7ins WHERE hl7ins.oemr_id = ic2.id),ic2.name) as hl7_ins_id_payer2,
	ic2.`ins_type_code` as ins_type_code_payer2, 
	ad2.`line1` as line1_payer2, 
	ad2.`line2` as line2_payer2, 
	ad2.`city` as city_payer2, 
	ad2.`state` as state_payer2, 
	ad2.`zip` as zip_payer2, 
	ad2.`country` as country_payer2,
	CONCAT('', ph2.`area_code`, ph2.`prefix`, ph2.`number`) AS phone_payer2, 
	CONCAT('', fx2.`area_code`, fx2.`prefix`, fx2.`number`) AS fax_payer2,
	ic3.name as name_payer3,
	ic3.attn as attn_payer3,
	coalesce(ic3.cms_id,ic3.alt_cms_id) payerID_payer3,
	coalesce((SELECT hl7ins.hl7_ins_id FROM hl7_ins_xlat as hl7ins WHERE hl7ins.oemr_id = ic3.id),ic3.name) as hl7_ins_id_payer3,
	ic3.`ins_type_code` as ins_type_code_payer3, 
	ad3.`line1` as line1_payer3, 
	ad3.`line2` as line2_payer3, 
	ad3.`city` as city_payer3, 
	ad3.`state` as state_payer3, 
	ad3.`zip` as zip_payer3, 
	ad3.`country` as country_payer3,
	CONCAT('', ph3.`area_code`, ph3.`prefix`, ph3.`number`) AS phone_payer3, 
	CONCAT('', fx3.`area_code`, fx3.`prefix`, fx3.`number`) AS fax_payer3,
	pd.`pubpid`,
	pd.`fname` as firstname, 
	pd.`lname` as lastname, 
    pd.`email` as email, 
    pd.phone_contact as phone,
    pd.phone_cell as mobile,
    pd.phone_biz,
    pd.street as address1,
	pd.`street`, 
	pd.`city`, 
	pd.`state`, 
	pd.`postal_code` as zipcode, 
	pd.`country_code`, 
	pd.`hipaa_allowsms`, 
	pd.`hipaa_allowemail`, 
	pd.`phone_home`, 
	pd.`phone_cell`,
	pd.`sex` as gender, 
	pd.`DOB` as birthday, 
	emp.`id` as abook_id, 
	emp.`abook_type` as abook_type, 
	emp.`organization` as abook_org_name, 
	emp.`fname` as abook_fname, 
	emp.`mname` as abook_mname, 
	emp.`lname` as abook_lname, 
	emp.`phone` as abook_home_phone, 
	emp.`phonew1` as abook_work_phone, 
	emp.phonecell as abook_phonecell, 
	emp.`fax` as abook_fax, 
	emp.`email` as abook_email, 
	emp.`street` as abook_street, 
	emp.`streetb` as abook_streetb, 
	emp.`city` as abook_city, 
	emp.`state` as abook_state, 
	emp.`zip` as abook_zip, 
	emp.`npi` as abook_npi,
	(case when length(emp.upin)=0 then emp.id else emp.upin end) as abook_value,
	refp.`id` as refp_id, 
	refp.`abook_type` as refp_type, 
	refp.`organization` as refp_org_name, 
	refp.`fname` as refp_fname, 
	refp.`mname` as refp_mname, 
	refp.`lname` as refp_lname, 
	refp.`phone` as refp_home_phone, 
	refp.`phonew1` as refp_work_phone, 
	refp.phonecell as refp_phonecell, 
	refp.`fax` as refp_fax, 
	refp.`email` as refp_email, 
	refp.`street` as refp_street, 
	refp.`streetb` as refp_streetb, 
	refp.`city` as refp_city, 
	refp.`state` as refp_state, 
	refp.`zip` as refp_zip, 
	refp.`npi` as refp_npi,
	(case when length(refp.upin)=0 then refp.id else refp.upin end) as refp_value,insd1.subscriber_sex as subsribersex1,insd2.subscriber_sex  as subsribersex2,insd3.subscriber_sex  as subsribersex3,insd1.claim_number as claim_number1,insd2.claim_number as claim_number2,insd3.claim_number as claim_number3,
	eve.pc_eventDate,f.name 
FROM openemr_postcalendar_events eve,facility f ,
	form_cases AS cs
LEFT OUTER JOIN users emp on 
	cs.employer=emp.id
LEFT OUTER JOIN users refp on 
	cs.referring_id =refp.id
LEFT JOIN patient_data AS pd ON
	pd.pid = cs.pid
LEFT JOIN patient_data AS gpd ON
	gpd.pid = cs.case_guarantor_pid
LEFT JOIN insurance_data AS insd1 ON
	insd1.id = cs.ins_data_id1
LEFT JOIN insurance_data AS insd2 ON
	insd2.id = cs.ins_data_id2
LEFT JOIN insurance_data AS insd3 ON
	insd3.id = cs.ins_data_id3
LEFT OUTER JOIN insurance_companies ic1 on 
	insd1.provider = ic1.id 
LEFT OUTER JOIN insurance_companies ic2 on 
	insd2.provider = ic2.id 
LEFT OUTER JOIN insurance_companies ic3 on 
	insd3.provider = ic3.id 	
LEFT JOIN patient_data AS inspd1 ON
	inspd1.pid = insd1.pid
LEFT JOIN patient_data AS inspd2 ON
	inspd2.pid = insd2.pid
LEFT JOIN patient_data AS inspd3 ON
	inspd3.pid = insd3.pid
LEFT JOIN addresses AS ad1 ON 
	ad1.foreign_id = ic1.id  
LEFT JOIN phone_numbers AS ph1 ON 
	ph1.foreign_id = ic1.id AND ph1.`type` = 2 
LEFT JOIN phone_numbers AS fx1 ON 
	fx1.foreign_id = ic1.id AND fx1.`type` = 5
LEFT JOIN addresses AS ad2 ON 
	ad2.foreign_id = ic2.id  
LEFT JOIN phone_numbers AS ph2 ON 
	ph2.foreign_id = ic2.id AND ph2.`type` = 2 
LEFT JOIN phone_numbers AS fx2 ON 
	fx2.foreign_id = ic2.id AND fx2.`type` = 5
LEFT JOIN addresses AS ad3 ON 
	ad3.foreign_id = ic3.id  
LEFT JOIN phone_numbers AS ph3 ON 
	ph3.foreign_id = ic3.id AND ph3.`type` = 2 
LEFT JOIN phone_numbers AS fx3 ON 
	fx3.foreign_id = ic3.id AND fx3.`type` = 5
WHERE
  eve.pc_case = cs.id
 and eve.pc_facility=f.id
 and eve.pc_eventDate=date_sub(CURDATE(),INTERVAL 1 DAY)");
 $body_params = [];
if(sqlNumRows($sql) > 0) {
    while ($row = sqlFetchArray($sql)) {
        $row += ['message_type' => 'ADT'];
        $body_params += ['message_type' => $row['message_type']];
        $body_params += ['firstname' => $row['firstname']];
        $body_params += ['lastname' => $row['lastname']];
        $body_params += ['email' => $row['email']];
        $body_params += ['phone' => $row['phone']];
        $body_params += ['mobile' => $row['mobile']];
        $body_params += ['officeNo' => $row['phone_biz']];
		$body_params += ['mapped_gender' => $row['gender']];
		$body_params += ['birthday' => $row['birthday']];
        $body_params += ['address1' => $row['address1']];
        $body_params += ['city' => $row['city']];
        $body_params += ['state_label' => $row['state']];
        $body_params += ['zipcode' => $row['zipcode']];
		if($row['country_code'] == 'USA') {
			$body_params += ['country' => 'United States'];
		}
		$body_params += ['pid' => $row['pid']];
		$body_params += ['email_consent' => $row['hipaa_allowsms']];
		$body_params += ['sms_consent' => $row['hipaa_allowemail']];
		$apiUrl = $mautic_host . "/api/contacts/". $body_params['pid'] ."/pid/create";
		$ch = curl_init($apiUrl);
		curl_setopt($ch, CURLOPT_POST, 1);
		curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($body_params));
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

		// Set basic authentication header
		curl_setopt($ch, CURLOPT_HTTPHEADER, array(
			'Authorization: Basic ' . base64_encode($mautic_user . ':' . $mautic_pass),
			'Content-Type: application/x-www-form-urlencoded'
		));
		$response = curl_exec($ch);

		print_r($body_params);

		// Check for cURL errors
		if (curl_errno($ch)) {
			echo 'Curl error: ' . curl_error($ch);
			exit();
		} else {
			$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
			// Handle authentication errors
			if ($httpCode === 401) {
				echo 'Authentication failed: Unauthorized.';
				exit();
			} elseif ($httpCode === 403) {
				echo 'Authentication failed: Forbidden.';
				exit();
			} else {
				$response_array = json_decode($response, true);
				$action = isset($response_array['action']) ? $response_array['action'] : "";
				if(isset($response_array['contact']) && $response_array['contact']['id']) {
					$contact_id = $response_array['contact']['id'];
					$message = "Contact Id: ".$contact_id;
					
					if($action == "UPDATE") {
						$message = "Updated Contact (Id: " . $contact_id . ")";
					} else if($action == "CREATE") {
						$message = "Created Contact (Id: " . $contact_id . ")";
					}
					$iserror = false;

					dbLog($filename, $message, $iserror,$idb_host, $idb, $idb_user, $idb_pass, $idb_client_id);
				} else {
					$message = "Unidentified responce.";
			
					if($action == "UPDATE") {
						$message = "Update: Unidentified responce.";
					} else if($action == "CREATE") {
						$message = "Create: Unidentified responce.";
					}
					$iserror = true;
					dbLog($filename, $message, $iserror,$idb_host, $idb, $idb_user, $idb_pass, $idb_client_id);
				}
			}
		}
    }
}else {
    echo "No records found";
}

//Write log into db
function dbLog($filename, $message, $iserror, $idb_host, $idb, $idb_user, $idb_pass, $idb_client_id) {
	$check_error = json_encode($iserror); 
	$connection = pg_connect("host=$idb_host dbname=$idb user=$idb_user password=$idb_pass");
	if (!$connection) {
		echo "Connection failed.";
	} else {
		$pgsql = "INSERT INTO Mautic_Contact_Log(ad_client_id, ad_org_id, created, createdby, date1, filename, help, isactive, iserror, mautic_contact_log_uu, updated, updatedby) VALUES ($1, 0,now(),100,now(), $2, $3, 'Y', $4,generate_uuid(),now(),100)";
		$result = pg_query_params($connection, $pgsql, array($idb_client_id, $filename, $message, $check_error));
		if ($result) {
			echo "Row inserted successfully!";
		} else {
			echo "Insert failed.";
		}
		pg_close($connection); // Close the connection when done
	}
}

?>