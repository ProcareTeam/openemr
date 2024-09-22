<?php

require_once(dirname(__FILE__, 8) . "/interface/globals.php");
require_once("$srcdir/patient.inc");

use OpenEMR\Core\Header;
use OpenEMR\Services\DocumentTemplates\DocumentTemplateService;
use Vitalhealthcare\OpenEMR\Modules\Generic\Controller\FormController;

$templateService = new DocumentTemplateService();
$patientForm = new FormController();

$_POST['mode'] = $_POST['mode'] ?? null;
$formId = isset($_REQUEST['form_id']) ? $_REQUEST['form_id'] : "";

if ($_POST['mode'] === 'save_assign' && !empty($formId)) {
    $assignedPatients = json_decode(($_POST['assign_list'] ?? ''), true, 512, JSON_THROW_ON_ERROR);

    $aList = [];
    foreach ($assignedPatients as $pItem) {
    	if(isset($pItem['type']) && $pItem['type'] == "group") {
    		$aList[] = "grp:" . $pItem['id'];
    	} else {
    		$aList[] = $pItem['id'];
    	}
    }


    $aList = implode("|", $aList);

    if(!empty($formId)) {
    	$rtn = sqlStatement("UPDATE `vh_form_templates` SET `to_patient` = ? WHERE `id` = ? ", array($aList, $formId));
    	if ($rtn) {
	        echo xlt('Successfully saved.');
	    } else {
	        echo xlt('Error! Save failed. Check your Assigned patient lists.');
	    }
	}
    exit;
}

if (!isset($_GET['render_group_assignments'])) {
    $info_msg = '';
    $result = '';
    if (!empty($_REQUEST['searchby']) && !empty($_REQUEST['searchparm'])) {
        $searchby = $_REQUEST['searchby'];
        $searchparm = trim($_REQUEST['searchparm'] ?? '');

        if ($searchby == 'Last') {
            $result = getPatientLnames("$searchparm", 'pid, pubpid, lname, fname, mname, providerID, DOB');
        } elseif ($searchby == 'Phone') {
            $result = getPatientPhone("$searchparm");
        } elseif ($searchby == 'ID') {
            $result = getPatientId("$searchparm");
        } elseif ($searchby == 'DOB') {
            $result = getPatientDOB(DateToYYYYMMDD($searchparm));
        } elseif ($searchby == 'SSN') {
            $result = getPatientSSN("$searchparm");
        } elseif ($searchby == 'Issues') {
            $result = $templateService->fetchPatientListByIssuesSearch("$searchparm");
        }
    } else {
        $result = getPatientLnames("", 'pid, pubpid, lname, fname, mname, providerID, DOB');
    }
    ?>
<!DOCTYPE html>
<html>
<head>
    <?php
    if (empty($GLOBALS['openemr_version'] ?? null)) {
        Header::setupHeader(['opener','datetime-picker', 'sortablejs']);
    } else {
        Header::setupHeader(['opener','datetime-picker']); ?>
        <script src="<?php echo $GLOBALS['web_root']; ?>/portal/public/assets/sortablejs/Sortable.min.js?v=<?php echo $GLOBALS['v_js_includes']; ?>"></script>
    <?php } ?>
</head>
<style>
  body {
    overflow:hidden;
  }
  .list-group-item {
    cursor: move;
  }
  strong {
    font-weight: 600;
  }
  .col-height {
    max-height: 95vh;
    overflow-y:auto;
  }
</style>
<script>
    document.addEventListener('DOMContentLoaded', function () {
        // init drag and drop
        let patientRepository = document.getElementById('searchResults');
        Sortable.create(patientRepository, {
            group: {
                name: 'patientGroup',
                pull: 'clone',
            },
            multiDrag: true,
            selectedClass: 'active',
            fallbackTolerance: 3,
            sort: false,
            swapThreshold: 0.25,
            animation: 150,
            revertClone: true,
            removeCloneOnHide: true,
            onAdd: function (evt) {
                if (evt.items.length > 0) {
                    for (let i = 0; i < evt.items.length; i++) {
                        let el = evt.items[i];
                        el.parentNode.removeChild(el);
                    }
                } else {
                    let el = evt.item;
                    el.parentNode.removeChild(el);
                }
            }
        });
        let assignEl = "assigned_patient";
        let dropAssign = document.getElementById(assignEl);
        Sortable.create(dropAssign, {
            group: {
                name: 'patientGroup',
                delay: 1000,
            },
            multiDrag: true,
            selectedClass: 'active',
            fallbackTolerance: 3,
            animation: 150,
            sort: true,
            swapThreshold: 0.25,
            removeCloneOnHide: false,
            onAdd: function (evt) { // make group unique
                let toList = evt.to.children;
                let dedup = {};
                let list = [...toList];
                list.forEach(function (toEl) {
                    if (dedup[toEl.getAttribute('data-pid')]) {
                        toEl.remove();
                    } else {
                        dedup[toEl.getAttribute('data-pid')] = true;
                    }
                });
            }
        });

        $('#searchparm').focus();
        $('#theform').submit(function () {
            SubmitForm(this);
        });

        $('select[name="searchby"]').on('change', function () {
            if ($(this).val() === 'DOB') {
                $('#searchparm').datetimepicker({
                    <?php $datetimepicker_timepicker = false; ?>
                    <?php $datetimepicker_showseconds = false; ?>
                    <?php $datetimepicker_formatInput = true; ?>
                    <?php require($GLOBALS['srcdir'] . '/js/xl/jquery-datetimepicker-2-5-4.js.php'); ?>
                });
            } else {
                $('#searchparm').datetimepicker("destroy");
            }
        });
    });

    function submitGroups() {
        top.restoreSession();
        document.getElementById('search_spinner').classList.toggle('d-none');
        let target = document.getElementById('edit-groups');
        let assignTarget = target.querySelectorAll('ul');
        let patientArray = [];
        let listArray = [];
        let listData = {};
        assignTarget.forEach((ulItem, index) => {
            let lists = ulItem.querySelectorAll('li');
            lists.forEach((item, index) => {
                console.log({index, item})
                listData = {
                    'usertype': item.dataset.usertype,
                    'id': item.dataset.pid
                }
                patientArray.push(listData);
                listData = {};
            });
        });
        const data = new FormData();
        data.append('assign_list', JSON.stringify(patientArray));
        data.append('mode', 'save_assign');
        data.append('form_id', '<?php echo $formId; ?>');
        fetch('./patient_groups.php', {
            method: 'POST',
            body: data,
        }).then(rtn => rtn.text()).then((rtn) => {
            document.getElementById('search_spinner').classList.toggle('d-none');
            (async (time) => {
                await asyncAlertMsg(rtn, time, 'success', 'lg');
            })(1500).then(rtn => {
                if (typeof opener.document.edit_form !== 'undefined') {
                    opener.document.edit_form.submit();
                }
                dlgclose();
            });
        }).catch((error) => {
            console.error('Error:', error);
        });
    }

    const SubmitForm = function (eObj) {
        $("#submit_button").css("disabled", "true");
        return true;
    }
</script>
<body>
    <div class='container-fluid'>
        <div class='row'>
            <div class='col-6 col-height p-0 pb-1'>
                <nav id='searchCriteria' class='navbar navbar-light bg-light sticky-top'>
                    <form class="form-inline" method='post' name='theform' id='theform' action=''>
                        <div class='form-row'>
                            <select name='searchby' id='searchby' class="form-control form-control-sm ml-1">
                                <option value="Last"><?php echo xlt('Name'); ?></option>
                                <option value='Issues'<?php if (!empty($searchby) && ($searchby === 'Issues')) {
                                    echo ' selected'; } ?>><?php echo xlt('Problems or Code'); ?></option>
                            </select>
                            <div class='input-group'>
                                <input type='text' class="form-control form-control-sm" id='searchparm' name='searchparm' value='<?php echo attr($_REQUEST['searchparm'] ?? ''); ?>' title='<?php echo xla('If name, any part of lastname or lastname,firstname') ?>' placeholder='<?php echo xla('Search criteria.') ?>' />
                                <button class='btn btn-primary btn-sm btn-search' type='submit' id="submit_button" value='<?php echo xla('Search'); ?>'></button>
                            </div>
                        </div>
                    </form>
                    <div class='btn-group'>
                        <button type='button' class='btn btn-secondary btn-cancel btn-sm' onclick='dlgclose();'><?php echo xlt('Quit'); ?></button>
                        <span id='search_spinner' class="d-none"><i class='fa fa-spinner fa-spin fa-2x ml-1'></i></span>
                    </div>
                </nav>
                <div class="">
                    <?php if (!is_countable($result)) : ?>
                        <div id="searchstatus" class="alert alert-danger m-1 p-1 rounded-0"><?php echo xlt('No records found. Please expand your search criteria.'); ?><br />
                        </div>
                    <?php elseif (count($result) >= 1000) : ?>
                        <div id="searchstatus" class="alert alert-danger m-1 p-1 rounded-0"><?php echo xlt('More than 1000 records found. Please narrow your search criteria.'); ?></div>
                    <?php else : ?>
                        <div id="searchstatus" class="alert alert-success m-1 p-1 rounded-0"><?php echo text(count($result)) . ' '; ?><?php echo xlt('records found.'); ?></div>
                    <?php endif; ?>
                    <ul id='searchResults' class='list-group mx-1'>
                        <?php
                        if (isset($result) && is_countable($result)) {
                            array_unshift($result,array("pid" => -1, "title" => "All Patients"));

                            foreach ($result as $pt) {
                            	if($pt["pid"] === -1) {
									echo "<li class='list-group-item px-1 py-1 mb-1' data-pid='-1' data-typeuser='patient'>" .
			                        '<strong>All Patients</strong></strong></li>' . "\n";
									continue;
								}

                                $name = $pt['lname'] . ', ' . $pt['fname'] . ' ' . $pt['mname'];
                                $this_name = attr($name);
                                $pt_pid = attr($pt['pid']);
                                $user_type = 'patient';
                                echo "<li class='list-group-item px-1 py-1 mb-1' data-pid='$pt_pid' data-usertype='$user_type'>" .
                                    '<strong>' . text($name) . '</strong>' . ' ' . xlt('Dob') . ': ' .
                                    '<strong>' . text(oeFormatShortDate($pt['DOB'])) . '</strong>' . ' ' . xlt('ID') . ': ' .
                                    '<strong>' . text($pt['pubpid']) . '</strong>';
                                if (!empty($searchby) && ($searchby === 'Issues')) {
                                    echo ' ' . xlt('Result') . ': ' . text($pt['title']) . ' ' . text($pt['diagnosis']);
                                }
                                    echo '</li>' . "\n";
                            }
                        }
                        ?>
                    </ul>
                </div>
            </div>
            <div class='col-6 col-height p-0 pb-1'>
                <nav id='dispose' class='navbar navbar-light bg-light sticky-top'>
                    <div class='btn-group ml-auto'>
                        <button type='button' class='btn btn-primary btn-save btn-sm' onclick='return submitGroups();'><?php echo xlt('Save'); ?></button>
                        <button type='button' class='btn btn-secondary btn-cancel btn-sm' onclick='dlgclose();'><?php /*echo xlt('Quit'); */?></button>
                        <span id='search_spinner' class="d-none"><i class='fa fa-spinner fa-spin fa-2x ml-1'></i></span>
                    </div>
                </nav>
                <div id="edit-groups" class='control-group mx-1 border-left border-right'>
                	<ul id='assigned_patient' class='list-group mx-1 px-1 show' data-group='assigned_patient'>
                	<?php
                	$pItems = array();
					if(!empty($formId)) {
						$pItems = $patientForm->getToAssignedUserList($formId);
					}

					foreach ($pItems as $pt) {
						if($pt["pid"] === -1) {
							echo "<li class='list-group-item px-1 py-1 mb-1' data-pid='-1' data-typeuser='patient'>" .
	                        '<strong>All Patients</strong></strong></li>' . "\n";
							continue;
						}

                        $name = $pt['lname'] . ', ' . $pt['fname'] . ' ' . $pt['mname'];
                    	$this_name = attr($name);
                    	$pt_pid = attr($pt['pid']);
			            echo "<li class='list-group-item px-1 py-1 mb-1' data-pid='$pt_pid' data-typeuser='patient'>" .
                        '<strong>' . text($name) . '</strong>' . ' ' . xlt('Dob') . ': ' .
                        '<strong>' . text(oeFormatShortDate($pt['DOB'])) . '</strong>' . ' ' . xlt('ID') . ': ' .
                        '<strong>' . text($pt['pubpid']) . '</strong>' . '</li>' . "\n";
					}
					?>

                	</ul>
                    <?php

                    // so list is responsive.
                    echo "<div class='py-1'></div>\n";
                    ?>
                </div>
            </div>
        </div>
    </div>
    <hr />
</body>
</html>
<?php } elseif ($_GET['render_group_assignments'] === 'true') { ?>
<?php }
