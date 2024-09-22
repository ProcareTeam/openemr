<?php

define('JASPER_STARTER_PATH','/vendor/geekcom/phpjasper/bin/jasperstarter/bin/');

require_once('sites/default/sqlconf.php');

$out = [];
$postCalCat = array();

$pid = $_GET['pid'];
//$caseId = $_GET['cids'];
$pid = ($_GET['pid']=="")?null:$_GET['pid'];
$catid = ($_GET['catid']=="" && $_GET['catid']!="_")?null:explode("_",$_GET['catid']);
$prdid = ($_GET['prdid']=="" && $_GET['prdid']!="_")?null:explode("_",$_GET['prdid']);
$frdt = $_GET['frdt'];
$fname=$_GET['fname'];

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);


require __DIR__ . '/vendor/geekcom/phpjasper/src/PHPJasper.php';

use PHPJasper\PHPJasper;

$input = __DIR__ . '/interface/reports/jasperreport/'.$fname.'.jrxml'; // '/vendor/geekcom/phpjasper/examples/hello_world.jrxml';
$jasper = new PHPJasper;
$patientID = $_GET['pid'];
$frdt=explode(" - ",$_GET['frdt']);//02/15/2023 - 02/18/2023

$date1=explode("/",$frdt[0]);
$date1=$date1[2]."-".$date1[0]."-".$date1[1];

$date2=explode("/",$frdt[1]);
$date2=$date2[2]."-".$date2[0]."-".$date2[1];


$jasper->compile($input)->execute();

$input = __DIR__ . '/interface/reports/jasperreport/'.$fname.'.jasper';


$wh=' WHERE cs.id = ope.pc_case ';
if (!empty($prdid)  && isset($prdid[0]) && $prdid[0]!="" && !in_array('0',$prdid))$wh.=" and ope.pc_aid in (".implode(",",$prdid).")";
if (!empty($catid)  && isset($catid[0]) && $catid[0]!="" && !in_array("",$catid))$wh.=" and ope.pc_catid in (".implode(",",$catid).") ";
if($pid!="")$wh.=" and ope.pc_pid =$pid ";
if($date1!="")$wh.="  and ope.pc_eventDate between '$date1' and '$date2' ";
$wh.=" and ope.pc_pid>0 order by ope.pc_aid,ope.pc_startTime";

$imagePath=__DIR__ . '/interface/reports/jasperreport/images';
$output = __DIR__ . '/interface/reports/jasperreport/';
$options = [
    'params' => [
     'whereClause'=>$wh,
     'ImagePath'=>$imagePath
    ],
    'format' => ['pdf'],
    'db_connection' => [
        'driver' => 'mysql', //, ....
        'username' => $login,
        'password' => $pass,
        'host' => $host,
        'database' => $dbase,
        'port' =>  $port
    ]
];

$jasper->process(
    $input,
    $output,
    $options
)->execute();


$baseUrl = $_SERVER['REQUEST_SCHEME'];
$baseUrl .= '://' . $_SERVER['HTTP_HOST'] . "/openemr/interface/reports/jasperreport/$fname.pdf?t=" . time();
$file = $baseUrl;
$file_headers = @get_headers($file);
/*if(!$file_headers || $file_headers[0] == 'HTTP/1.1 404 Not Found') {
    $exists = false;
}
else {
    //$baseUrl = $_SERVER['REQUEST_SCHEME'];
    //$baseUrl .= '://' . $_SERVER['HTTP_HOST'] . "/openemr/interface/reports/jasperreport/report2.pdf?t=" . time();
    $exists = true;
}*/
//die();
//if(!$exists)
die("<script>window.location='" . $baseUrl . "'</script>");

