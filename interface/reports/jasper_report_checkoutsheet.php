<?php
// ini_set('display_errors', 1);
// ini_set('display_startup_errors', 1);
// error_reporting(E_ALL);
/**
 * Payment processing report.
 *  Supports void and credit with Sphere payment processing.
 *
 * @package   OpenEMR
 * @link      https://www.open-emr.org
 * @author    Brady Miller <brady.g.miller@gmail.com>
 * @copyright Copyright (c) 2021 Brady Miller <brady.g.miller@gmail.com>
 * @license   https://github.com/openemr/openemr/blob/master/LICENSE GNU General Public License 3
 */

require_once("../globals.php");

use OpenEMR\Common\Acl\AclMain;
use OpenEMR\Common\Csrf\CsrfUtils;
use OpenEMR\Common\Twig\TwigContainer;
use OpenEMR\Core\Header;



$postCalCat = array();
$sql = "SELECT * FROM openemr_postcalendar_categories WHERE pc_cattype = 'Calendar' and pc_active = 1";
$rez = sqlStatement($sql, $sqlBindArray);
for ($iter = 0; $row = sqlFetchArray($rez); $iter++) {
    $postCalCat[$iter] = $row;
}

if (isset($_GET['ajax'])) {
    $sqlBindArray = array();
    $sql = "Select coalesce(case_number,id) as case_number,case_description,id from form_cases where pid=" . $_GET['pid'];

    $rez = sqlStatement($sql, $sqlBindArray);
    for ($iter = 0; $row = sqlFetchArray($rez); $iter++) {
        $returnval[$iter] = $row;
    }
    die(json_encode($returnval));
}
if (isset($_GET['genreport'])) {
    $pid = $_GET['pid'];
    $catid = explode("_",$_GET['catid']);
    $prdid = explode("_",$_GET['prdid']);
    $frdt = $_GET['frdt'];

    $frdt = explode(" - ", $_GET['frdt']); //02/15/2023 - 02/18/2023

    $date1 = explode("/", $frdt[0]);
    $date1 = $date1[2] . "-" . $date1[0] . "-" . $date1[1];

    $date2 = explode("/", $frdt[1]);
    $date2 = $date2[2] . "-" . $date2[0] . "-" . $date2[1];


    $sqlBindArray = array();


    require_once("../../sites/default/odbcconf.php");


    $sql = "SELECT ope.pc_case,pd.pubpid FROM openemr_postcalendar_events ope,patient_data pd WHERE ope.pc_pid=pd.pid ";
    if (!empty($prdid)  && isset($prdid[0]) && $prdid[0]!="")
        $sql .= "and ope.pc_aid in (".implode(",",$prdid).") ";
    if (!empty($catid)  && isset($catid[0]) && $catid[0]!="")
        $sql .= "and ope.pc_catid in (".implode(",",$catid).") ";
    if ($pid!="")
        $sql .= "and ope.pc_pid =$pid ";
    $sql .= " and ope.pc_eventDate between '$date1' and '$date2' and pc_pid>0 order by pc_catid,pc_aid";

    require_once("../../sites/default/odbcconf.php");

    $caseIds = [];
    $rez = sqlStatement($sql, $sqlBindArray);
    for ($iter = 0; $row = sqlFetchArray($rez); $iter++) {
        $caseID = $caseIds[$iter] = $row['pc_case'];
        $pubId = $row['pubpid'];

        $sql = "delete from vh_form_case_balance where caseid=" . $caseID;
        sqlStatement($sql, $sqlBindArray);
        $psql = "select c_Bpartner_id,value from c_Bpartner where value like '$pubId'";

        $patientId = "";
        $rs = pg_query($idempiere_connection, $psql) or die("Cannot execute query: $sql\n");
        while ($row = pg_fetch_row($rs)) {
            $patientId = $row[0]; //.' '.$row[1]; // $row[1] $row[2]\n";
        }


        $psql = "select x_mwcase_id,pc_oemrcaseno from x_mwcase where pc_oemrcaseno like '" . $caseID . "'";
        $IDcaseId = 0;
        $rs = pg_query($idempiere_connection, $psql) or die("Cannot execute query: $sql\n");
        while ($row = pg_fetch_row($rs)) {
            $IDcaseId = $row[0];
        }

        $balance = 0;
	$patientresp = 0;
	$psql = "select vh_caseopenbalance($patientId,$IDcaseId)"; //"select x_mwcase_id,pc_oemrcaseno from x_mwcase where pc_oemrcaseno like '".$caseID."'";//where pc_oemrcaseno like 'casenumber'";


	if($patientId!='' && $IDcaseId!='')
	{
        	$rs = pg_query($idempiere_connection, $psql) or die("Cannot execute query: $psql\n");
        	while ($row = pg_fetch_row($rs)) {
            	$balance = $row[0]; //.' '.$row[1]."<br>"; // $row[1] $row[2]\n";
        	}
        //  echo "<br>Fetched balance $balance";
        $psql = "select vh_patientresponsibility($patientId,$IDcaseId)";
        $rs = pg_query($idempiere_connection, $psql) or die("Cannot execute query: $psql\n");
        while ($row = pg_fetch_row($rs)) {
            $patientresp = $row[0];
	}
	}
        $sql = "insert into vh_form_case_balance(caseid,balance,patientresp) values ( '$caseID', '$balance', '$patientresp')";
        sqlStatement($sql);
    }

    pg_close($idempiere_connection);

    $pid = $_GET['pid'];
    $catid = $_GET['catid'];
    $prdid = $_GET['prdid'];
    $frdt = $_GET['frdt'];

    //echo $caseId;
    $loadUrl = $GLOBALS['webroot'] . "/genreport.php?pid=" . $pid . "&catid=" . $catid . "&prdid=" . $prdid . "&frdt=" . $frdt."&fname=checkoutsheet";

    die("<script>window.location='$loadUrl';</script>");
    // 
}

if (!empty($_POST)) {
    if (!CsrfUtils::verifyCsrfToken($_POST["csrf_token_form"])) {
        CsrfUtils::csrfNotVerified();
    }
}

if (!AclMain::aclCheckCore('acct', 'rep_a')) {
    //echo (new TwigContainer(null, $GLOBALS['kernel']))->getTwig()->render('core/unauthorized.html.twig', ['pageTitle' => xl("Chiro COS")]);
    die("Unauthorized access.");
    exit;
}

// If from date is empty, default to 1 week ago.
$from_date = (!empty($_POST['form_from_date'])) ? DateTimeToYYYYMMDDHHMMSS($_POST['form_from_date']) : date('Y-m-d H:i:s', strtotime('-1 week'));
$to_date = (!empty($_POST['form_to_date'])) ? DateTimeToYYYYMMDDHHMMSS($_POST['form_to_date']) : date('Y-m-d H:i:s');

$patient = $_POST['form_patient'] ?? null;
$service = $_POST['form_service'] ?? null;
$ticket = $_POST['form_ticket'] ?? null;
$transId = $_POST['form_trans_id'] ?? null;
$actionName = $_POST['form_action_name'] ?? null;

?>

<html>

<head>
    <title><?php echo xlt('Chiro COS'); ?></title>

    <?php Header::setupHeader(["datetime-picker", "report-helper"]); ?>

    <script>
        $(function() {
            $('#form_date').daterangepicker({});
        });

        function refreshme() {
            document.forms[0].submit();
        }

        function setpatient(pid, lname, fname, dob) {
            document.forms[0].elements['form_patient'].value = lname + ", " + fname;
            fetchData2(pid);
            document.getElementById('pid').value = pid;
        }

        function fetchData2(pid) {
            const xhttp = new XMLHttpRequest();
            xhttp.onload = function() {
                var json_d = JSON.parse(this.responseText);
                console.log(json_d);

                var options = "<option value=''>Select</option>";
                for (var i = 0; i < json_d.length; i++) {
                    options += "<option value='" + json_d[i].id + "'>" + json_d[i].case_number + "-" + json_d[i].case_description + "</option>";
                }
                document.getElementById('case_dropdown').innerHTML = options;
            }
            xhttp.open("GET", "?ajax=1&pid=" + pid + "&csrf_token_form=");
            xhttp.send();
        }

        function sel_patient() {
            jQuery("#form_patient").val();
            document.getElementById('pid').value ='';
            dlgopen('../main/calendar/find_patient_popup.php?pflag=0', '_blank', 500, 400);
        }

        function set_allday() {
            var f = document.forms[0];
            var color1 = 'var(--gray)';
            var color2 = 'var(--gray)';
            var disabled2 = true;
            if (document.getElementById('rballday1').checked) {
                color1 = '';
            }
            if (document.getElementById('rballday2').checked) {
                color2 = '';
                disabled2 = false;
            }
            document.getElementById('tdallday1').style.color = color1;
            document.getElementById('tdallday2').style.color = color2;
            //document.getElementById('tdallday3').style.color = color2;
            document.getElementById('tdallday4').style.color = color2;
            document.getElementById('tdallday5').style.color = color2;
            f.form_hour.disabled = disabled2;
            f.form_minute.disabled = disabled2;
            <?php if ($GLOBALS['time_display_format'] == 1) { ?>
                f.form_ampm.disabled = disabled2;
            <?php } ?>
            f.form_duration.disabled = disabled2;
        }

        function dateChanged() {

        }
    </script>

    <style>
        /* specifically include & exclude from printing */
        @media print {
            #report_parameters {
                visibility: hidden;
                display: none;
            }

            #report_parameters_daterange {
                visibility: visible;
                display: inline;
            }

            #report_results table {
                margin-top: 0px;
            }
        }

        input#form_date {
            width: 20rem;
        }

        .alignclass {
            vertical-align: text-top;
        }

        /* specifically exclude some from the screen */
        @media screen {
            #report_parameters_daterange {
                visibility: hidden;
                display: none;
            }
        }
    </style>
</head>

<body class="body_top">

    <!-- Required for the popup date selectors -->
    <div id="overDiv" style="position: absolute; visibility: hidden; z-index: 1000;"></div>
    <div id="report_parameters_daterange"><?php echo text(oeFormatShortDate($from_date)) . " &nbsp; " . xlt('to{{Range}}') . " &nbsp; " . text(oeFormatShortDate($to_date)); ?>
    </div>
    <?php
    $loadUrl = "";
    $loadUrl = "?genreport=1&pid=";

    ?>
    <form method='post' name='theform' id='theform' onsubmit='return top.restoreSession()'>
        <input type="hidden" name="csrf_token_form" value="<?php //echo attr(CsrfUtils::collectCsrfToken()); ?>" />
        <input type="hidden" id="pid" />

        <div id="report_parameters">

            <div class="page-title">
                <h2><?php echo xlt('Chiro COS'); ?></h2>
            </div>

            <div class="form-row">
                <div class="col">
                    <div class="form-group">
                        <label><?php echo xlt('Category'); ?></label>
                        <select class="form-control" id="cat_id" multiple>
                            <option value="" selected>Select</option>
                            <?php foreach ($postCalCat as $row) { ?>
                                <option value='<?= $row['pc_catid'] ?>'><?= $row['pc_catname'] ?></option>
                            <?php } ?>
                        </select>
                    </div>
                </div>

                <div class="col">
                    <div class="form-group">
                        <label><?php echo xlt('Provider'); ?></label>
                        <?php
                            // =======================================
                            // multi providers
                            // =======================================
                            $ures = sqlStatement("SELECT id, username, fname, lname FROM users WHERE " .
                                "authorized != 0 AND active = 1 ORDER BY lname, fname");
                            if ($eid || true) {
                                // get provider from existing event
                                $qprov = sqlStatement("SELECT pc_aid FROM openemr_postcalendar_events "); //WHERE pc_eid = ?", array($eid));
                                $provider = sqlFetchArray($qprov);
                                $defaultProvider = $provider['pc_aid'];
                            }
                            echo "<select class='form-control' name='form_provider' id='provd' multiple>";

                            echo "<option value='0' selected>All</option>";
                            while ($urow = sqlFetchArray($ures)) {
                                echo "    <option value='" . attr($urow['id']) . "'";
                                if ($urow['id'] == $defaultProvider) {
                                    echo " selected";
                                }
                                echo ">" . text($urow['lname']);
                                if ($urow['fname']) {
                                    echo ", " . text($urow['fname']);
                                }
                                echo "</option>\n";
                            }
                            echo "</select>";
                        ?>
                    </div>
                </div>

                <div class="col" style="display:none">
                    <div class="form-group">
                        <label><?php echo xlt('Case'); ?></label>
                        <select name='form_service' id='case_dropdown' class='form-control' style='display:none'>
                            <option value=''><?php echo xlt('All'); ?></option>
                            <option value='sphere' <?php echo ($service == 'sphere') ? 'selected' : '' ?>><?php echo xlt('Sphere'); ?></option>
                        </select>
                    </div>
                </div>
            </div>

            <div class="form-row">
                <div class="col">
                    <div class="form-group">
                        <label><?php echo xlt('Patient'); ?></label>
                        <input type='text' size='20' name='form_patient' class='form-control' style='cursor:pointer;' id='form_patient' value='<?php echo attr($patient); ?>' onclick='sel_patient()' title='<?php echo xla('Click to select patient'); ?>' />
                    </div>
                </div>

                <div class="col">
                    <div class="form-group">
                        <label><?php echo xlt('Date'); ?></label>
                        <input class="col-sm-12 form-control datepicker" type='text' size='10' name='form_date' id='form_date' value='<?php echo attr(oeFormatShortDate($date)) ?>' title='<?php echo xla('event date or starting date'); ?>' />
                    </div>
                </div>
            </div>

            <div class="form-row">
                <div class="col">
                    <a href='#' class='btn btn-secondary btn-save' onclick='$("#reportHolder").attr("src","<?= $loadUrl ?>"+$("#pid").val()+"&catid="+$("#cat_id").val().join("_")+"&prdid="+$("#provd").val().join("_")+"&frdt="+$("#form_date").val()); $("#reportHolder").show()'><?php echo xlt('Submit'); ?></a>
                    <?php if (!empty($_POST['form_refresh'])) { ?>
                    <?php } ?>
                </div>
            </div>

            <div class='text'><?php echo xlt('Please input search criteria above, and click Submit to view results.'); ?>
                <iframe id='reportHolder' src='' width='100%' style='display:none;width:100%;height:100vh'>
                </iframe>
            </div>
            <input type='hidden' name='form_refresh' id='form_refresh' value='' />
    </form>
    <script type="text/javascript" src="https://cdn.jsdelivr.net/momentjs/latest/moment.min.js"></script>
    <script type="text/javascript" src="https://cdn.jsdelivr.net/npm/daterangepicker/daterangepicker.min.js"></script>
    <link rel="stylesheet" type="text/css" href="https://cdn.jsdelivr.net/npm/daterangepicker/daterangepicker.css" />
</body>

</html>
