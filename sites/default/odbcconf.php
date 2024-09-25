<?php

use OpenEMR\Common\Crypto\CryptoGen;

$psql_host = isset($GLOBALS['idempiere_host']) ? $GLOBALS['idempiere_host'] : "";
$psql_port = isset($GLOBALS['idempiere_port']) ? $GLOBALS['idempiere_port'] : "";
$psql_db = isset($GLOBALS['idempiere_db']) ? $GLOBALS['idempiere_db'] : "";
$psql_user = isset($GLOBALS['idempiere_db_user']) ? $GLOBALS['idempiere_db_user'] : "";
$psql_password = ($cryptoGen->decryptStandard($GLOBALS['idempiere_db_password']) != '') ? $cryptoGen->decryptStandard($GLOBALS['idempiere_db_password']) : "";
$ad_client_id = isset($GLOBALS['idempiere_client_id']) ? $GLOBALS['idempiere_client_id'] : "";


if(!empty($psql_host) && !empty($psql_port) && !empty($psql_db) && !empty($psql_user) && !empty($psql_password) && !empty($ad_client_id)) {
	$idempiere_connection = pg_connect("host=$psql_host port=$psql_port dbname=$psql_db user=$psql_user password=$psql_password");
} else {
	$idempiere_connection = false;
}