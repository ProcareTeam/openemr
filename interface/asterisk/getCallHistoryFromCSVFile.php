<?php
require_once("../globals.php");

if(!isset($GLOBALS['asterisk_manager_cdr_csv_downloaded_path']) || empty($GLOBALS['asterisk_manager_cdr_csv_downloaded_path'])) {
    echo "CDR CSV download file path empty.\n";
    exit();
}

//Path where CSV file is Downloaded
$cdrCsvFile = $GLOBALS['asterisk_manager_cdr_csv_downloaded_path'];
$fileHandle = fopen($cdrCsvFile, 'r');

if ($fileHandle) {
    $cdrrec = [];
    $extension = sqlQuery("SELECT `extension` FROM `user_extension` WHERE `username` = ? ORDER BY id DESC LIMIT 1", array($_SESSION['authUser']));
    $rowCount = 0;
    $currentDate = date("Y-m-d"); 
    while (($cdrRecord = fgetcsv($fileHandle)) !== false) {
        $dt = new DateTime($cdrRecord[9]);
        $callDate = $dt->format("Y-m-d");
        //filter number of calls based on current date
        if($cdrRecord[2] == $extension['extension'] && $callDate === $currentDate && $cdrRecord[14] == "ANSWERED" && $cdrRecord[3] != "internal") {
            $cdrrec[] = $cdrRecord;
            $rowCount++;
        }  
    }
    krsort($cdrrec);
    if($cdrrec) {
        echo "Extension ".$extension['extension']." has answered ".$rowCount." calls today<br><br>";
        ?>
        <div class="row mb-4">
            <div class="col-6">
                <section class="card mb-2">
                    <div class="card-body p-1">
                        <h6 class="card-title mb-0 d-flex p-1 justify-content-between">
                            <a class="text-left font-weight-bolder" href="#" data-toggle="collapse" data-target="#" aria-expanded="true" aria-controls=""><?php echo xl('Answered Numbers') ?></a>
                        </h6>
                        <div id="patient_details" class="card-text collapse show">
                            <div class="clearfix pt-2">
                                <div class="table-responsive">
                                    <table class="table table-sm table-striped">
                                        <thead>
                                            <tr>  
                                                <th scope="col"><?php echo xl('Patient Name / Law Firm Associated') ?></th>
                                                <th scope="col"><?php echo xl('Number') ?></th>
                                                <th scope="col"><?php echo xl('Call Duration') ?></th>
                                                <th scope="col"><?php echo xl('Call Date') ?></th>
                                            </tr>
                                        </thead>
                                    <tbody>
                                    <?php                 
                                        foreach ($cdrrec as $row) {
                                            $getPatientName = sqlStatement("SELECT pid, CONCAT(CONCAT_WS(' ', IF(LENGTH(pd.fname),pd.fname,NULL), IF(LENGTH(pd.lname),pd.lname,NULL)), ' (', pd.pubpid ,')') as patient_name from patient_data pd where TRIM(LEADING '1' FROM replace(replace(replace(replace(replace(replace(pd.phone_cell,' ',''),'(','') ,')',''),'-',''),'/',''),'+','')) LIKE ? or TRIM(LEADING '1' FROM replace(replace(replace(replace(replace(replace(pd.phone_home,' ',''),'(','') ,')',''),'-',''),'/',''),'+','')) LIKE ? order by id desc",array($row[1],$row[1]));
                                            
                                            $getLiabilityPayerId = sqlStatement("select distinct(case when length(organization)>0  then organization else concat(fname,' ',lname) end) as organization FROM users u where TRIM(LEADING '1' FROM replace(replace(replace(replace(replace(replace(phone,' ',''),'(','') ,')',''),'-',''),'/',''),'+','')) LIKE ? or TRIM(LEADING '1' FROM replace(replace(replace(replace(replace(replace(fax,' ',''),'(','') ,')',''),'-',''),'/',''),'+','')) LIKE ? or TRIM(LEADING '1' FROM replace(replace(replace(replace(replace(replace(phonew1,' ',''),'(','') ,')',''),'-',''),'/',''),'+','')) LIKE ? or TRIM(LEADING '1' FROM replace(replace(replace(replace(replace(replace(phonew2,' ',''),'(','') ,')',''),'-',''),'/',''),'+','')) LIKE ? or TRIM(LEADING '1' FROM replace(replace(replace(replace(replace(replace(phonecell,' ',''),'(','') ,')',''),'-',''),'/',''),'+','')) LIKE ?",array($row[1]."", $row[1]."", $row[1]."", $row[1]."", $row[1].""));
                                            $name = "-";

                                            $fieldHtml= array();
                                            if(sqlNumRows($getPatientName) > 0) {
                                                while ($getPatientName1 = sqlFetchArray($getPatientName)) {
                                                    $fieldHtml[] = "<a href='javascript:goParentPid(".$getPatientName1['pid'].")'>".$getPatientName1['patient_name']."</a>";
                                                }
                                            }

                                            if(sqlNumRows($getLiabilityPayerId) > 0) {
                                                while ($getLiabilityPayerId1 = sqlFetchArray($getLiabilityPayerId)) {
                                                    $fieldHtml[] = $getLiabilityPayerId1['organization'];
                                                }
                                            }

                                            if(!empty($fieldHtml)) {
                                                $fieldHtml = implode(", ", $fieldHtml);
                                            } else {
                                                $fieldHtml = "";
                                            } 

                                            echo "<tr>";
                                            echo "<td >".$fieldHtml."</td>"; 
                                            echo "<td><a href=\"#\" onclick= dlgopen('".$GLOBALS['webroot']."/interface/asterisk/makeCallThroughExtension.php?phone_number=".$row[1]."') >".$row[1]."</a></td>"; 
                                            echo "<td>".gmdate("H:i:s", $row[12])." Hours</td>"; 
                                            echo "<td>". $row[9]."</td>"; 
                                            echo "</tr>"; 
                                            }
                                        ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                </section>
            </div>
        </div>
        <?php
    }
    else {
        echo "Extension ".$extension['extension']." has not answered any calls today<br><br>";
    }
    fclose($fileHandle);
} else {
    echo "Failed to open the CDR CSV file.\n";
    exit();
}
?>