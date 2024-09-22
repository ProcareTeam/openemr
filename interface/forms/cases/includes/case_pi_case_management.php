<?php

include_once("../../globals.php");
include_once($GLOBALS['srcdir'].'/wmt-v2/wmtpatient.class.php');
include_once($GLOBALS['srcdir'].'/wmt-v2/wmtcase.class.php');
include_once($GLOBALS['srcdir'].'/wmt-v2/wmt.msg.inc');

//Load PI Case Manager Data
$piCaseData = wmtCase::piCaseManagerFormData($id, $field_prefix);
$dt = array_merge($dt, $piCaseData);

$rehabField2List = array(
    'PT' => 'PT',
    'LD' => 'LD',
    'CD' => 'CD',
    'DD' => 'DD'
);

$cslogsData = array();
if(!empty($id)) {
    $cslogsData = wmtCase::fetchAlertLogsByParam(array(
        'field_id' => 'rehab_field',
        'form_name' => 'form_cases',
        'pid' => $pid,
        'form_id' => $id
    ), 5);
}

$piCaseStatus = wmtCase::isInsLiableForPiCase($pid);
//$trClasss = !$piCaseStatus ? 'trHide' : '';

//Prepare action item data
$ai_items_data = wmtCase::getActionItems($id);
$ai_items_data[] = array();


$rehab_field_1_val = isset($dt['tmp_'.$field_prefix.'rehab_field_1']) ? $dt['tmp_'.$field_prefix.'rehab_field_1'] : array();
$rehab_field_2_val = isset($dt['tmp_'.$field_prefix.'rehab_field_2']) ? $dt['tmp_'.$field_prefix.'rehab_field_2'] : array();
$rfieldCount = (count($rehab_field_1_val) == count($rehab_field_2_val)) ? count($rehab_field_1_val) : 1;
$rfieldCount = ($rfieldCount > 0) ? $rfieldCount : 1;

// Lawyer/Paralegal Contacts
$lp_contact_val = isset($dt['tmp_'.$field_prefix.'lp_contact']) ? $dt['tmp_'.$field_prefix.'lp_contact'] : array();

?>
<!-- Pi Case Management Section -->
<div id="pi_case_row" class="form-row mt-4 pi-case-management-container sec_row <?php echo !$piCaseStatus ? 'trHide' : ''; ?>">
    <div class="col-md-12">
        <div class="card">
            <div class="card-header">
              <h6 class="mb-0 d-inline-block"><?php echo xl('PI Case Management'); ?></h6>
            </div>
            <div class="card-body px-2 py-2">
                <!-- Case Manager -->
                <div class="form-row">
                    <div class="col-lg-6">
                            <!-- Case manager -->
                            <div class="form-row">
                                <div class="form-group col-lg-4">
                                  <label for="case_id"><?php echo xl('Case Manager'); ?>:</label>
                                  <!-- hidden input -->
                                  <input type="hidden" name="<?php echo $field_prefix; ?>liability_payer_exists" class="liability_payer_exists" value="<?php echo $piCaseStatus === true ? 1 : 0 ?>">
                                  <input type="hidden" name="tmp_<?php echo $field_prefix; ?>casemanager_hidden_sec" class="hidden_sec_input tmp_casemanager_hidden_sec" value="<?php echo $piCaseStatus ? $piCaseStatus : 0 ?>">
                                  <input type="hidden" id="vh_case_manager" name="<?php echo $field_prefix; ?>vh_case_manager" value="<?php echo $dt[ $field_prefix . 'vh_case_manager']; ?>" />
                                  <select name="tmp_<?php echo $field_prefix; ?>case_manager" class="case_manager form-control makedisable" id="<?php echo $field_prefix; ?>case_manager" onChange="casemanagerChange(this)">
                                    <?php wmtCase::getUsersBy($dt['tmp_' . $field_prefix . 'case_manager'], '', array('physician_type' => array('chiropractor_physician', 'case_manager_232321'))); ?>
                                  </select>
                                </div>
                                <div class="col-lg-8">
                                    <label for="case_id"><?php echo xl('Rehab Plan'); ?>:</label>
                                    <div id="reahab_wrapper" class="d-flex align-items-start m-main-wrapper">
                                        <div class="m-elements-wrapper mr-2">
                                            <?php for ($fi=0; $fi < $rfieldCount; $fi++) { ?>
                                            <!-- Input container -->
                                            <div class="m-element-wrapper mb-2">
                                                <!-- Field container -->
                                                <div class="input-group">
                                                    <select name="tmp_<?php echo $field_prefix; ?>rehab_field_1[]" class="form-control makedisable" data-field-id="rehab_field_1" >
                                                        <option value=""></option>
                                                        <?php
                                                            for ($i=1; $i <= 20 ; $i++) {
                                                                $isSelected = ($i == $rehab_field_1_val[$fi]) ? "selected" : ""; 
                                                                ?>
                                                                    <option value="<?php echo $i ?>" <?php echo $isSelected ?>><?php echo $i ?></option>
                                                                <?php
                                                            }
                                                        ?>
                                                      </select>
                                                      <select name="tmp_<?php echo $field_prefix; ?>rehab_field_2[]" class="form-control makedisable" data-field-id="rehab_field_2">
                                                        <option value=""></option>
                                                        <?php
                                                            foreach ($rehabField2List as $rbk => $rbItem) {
                                                                $isSelected = ($rbk == $rehab_field_2_val[$fi]) ? "selected" : ""; 
                                                                ?>
                                                                    <option value="<?php echo $rbk ?>" <?php echo $isSelected ?>><?php echo $rbItem ?></option>
                                                                <?php
                                                            }
                                                        ?>
                                                      </select>
                                                      <div class="input-group-append">
                                                        <button type="button" class="btn btn-primary m-btn-remove"><i class="fa fa-times" aria-hidden="true"></i></button>
                                                        </div>
                                                </div>
                                                <!-- Remove Button -->
                                                <!-- <button type="button" class="btn btn-primary m-btn-remove"><i class="fa fa-times" aria-hidden="true"></i></button> -->
                                            </div>
                                            <?php } ?>
                                        </div>

                                        <!-- Add more item btn -->
                                        <button type="button" class="btn btn-primary m-btn-add" style="white-space: nowrap;"><i class="fa fa-plus" aria-hidden="true"></i> Add more</button>
                                    </div>

                                </div>
                            </div>
                    </div>
                    <div class="col-lg-6">
                        <div class="alert_log_table_container">
                            <table class="alert_log_table text text table table-sm table-bordered table-striped mb-1">
                                <thead class="thead-dark">
                                    <tr>
                                        <th><?php echo xl('Sr.'); ?></th>
                                        <th><?php echo xl('New Value'); ?></th>
                                        <th><?php echo xl('Old Value'); ?></th>
                                        <th><?php echo xl('Username'); ?></th>
                                        <th><?php echo xl('DateTime'); ?></th>
                                    </tr>
                                </thead>
                                <tbody>
                                <?php
                                    $ci = 1;
                                    foreach ($cslogsData as $key => $item) {
                                        ?>
                                        <tr>
                                            <td><?php echo $ci; ?></td>
                                            <td style="vertical-align: text-top;"><div style="white-space: pre;"><?php echo $item['new_value']; ?></div></td>
                                            <td style="vertical-align: text-top;"><div style="white-space: pre;"><?php echo $item['old_value']; ?></div></td>
                                            <td><?php echo $item['user_name']; ?></td>
                                            <td><?php echo date('d-m-Y h:i:s',strtotime($item['date'])); ?></td>
                                        </tr>
                                        <?php
                                        $ci++;
                                    }
                                ?>
                                </tbody>
                            </table>
                        </div>
                        <?php if(isset($id) && !empty($id)) { ?>
                        <a href="javascript:void(0)" onClick="caselibObj.open_field_log('<?php echo $pid ?>', '<?php echo $id ?>', 'rehab_field', 'form_cases')"><?php echo xl('View logs'); ?></a>
                        <?php } ?>
                    </div>
                </div>

                <!-- Lawyer/Paralegal Contacts -->
                <div class="form-row mt-2">
                    <div class="col-lg-6">
                        <label for="case_id"><?php echo xl('Lawyer/Paralegal Contacts'); ?>:</label>
                            <div id="lpc_ele_container" class="d-flex align-items-start m-main-wrapper">
                                <div class="m-elements-wrapper mr-2 w-100">
                                    <?php foreach ((!empty($lp_contact_val) ? $lp_contact_val : array('')) as $lpk => $lpItem) { ?>
                                    <!-- Input container -->
                                    <div class="m-element-wrapper jumbotron jumbotron-fluid px-2 py-2 mb-2 mb-2">
                                        <!-- Field container -->
                                        <div>
                                        <div class="input-group">
                                          <select name="tmp_<?php echo $field_prefix; ?>lp_contact[]" class="form-control" data-field-id="lp_contact">
                                                <?php wmtCase::referringSelect($lpItem, '', '', array('Attorney'), '', true, true); ?>
                                          </select>
                                          <div class="input-group-append">
                                            <button type="button" class="medium_modal btn btn-primary search_user_btn" href='<?php echo $GLOBALS['webroot']. '/interface/forms/cases/php/find_user_popup.php?abook_type=Attorney'; ?>'><i class="fa fa-search" aria-hidden="true"></i></button>
                                            </div>
                                        </div>
                                        <span class="field-text-info c-font-size-sm ipc_info_container c-text-info"></span>
                                    </div>
                                        <!-- Remove Button -->
                                        <button type="button" class="btn btn-primary m-btn-remove"><i class="fa fa-times" aria-hidden="true"></i></button>
                                    </div>
                                    <?php } ?>
                                </div>

                                <!-- Add more item btn -->
                                <button type="button" class="btn btn-primary m-btn-add" style="white-space: nowrap;"><i class="fa fa-plus" aria-hidden="true"></i> <?php echo xl('Add more'); ?></button>
                            </div>
                    </div>
                    <div class="col-lg-6">
                    </div>
                </div>

                <!-- Email Addresses -->
                <div class="form-row mt-4" style="display: none;">
                    <div class="form-group col-lg-6">
                        <label for="case_id" style="width: 100%;"><?php echo xl('Email Addresses'); ?>: <i style="float:right">**  <?php echo xl('Please use a comma to separate multiple addresses'); ?></i></label>
                        <textarea name="<?php echo $field_prefix; ?>notes" id="<?php echo $field_prefix; ?>notes" class="form-control" rows="3" placeholder="Email Addresses" <?php echo \OpenEMR\Common\Acl\AclMain::aclCheckCore('admin', 'super') === false ? "readonly" : ""; ?>><?php echo attr($dt[$field_prefix . 'notes']); ?></textarea>
                    </div>
                    <div class="col-lg-6">
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<div id="action_item_row" class="form-row mt-4 action-item-management-container sec_row">
    <div class="col-md-12">
        <div class="card">
            <div class="card-header">
              <h6 class="mb-0 d-inline-block"><?php echo xl('Action Items'); ?></h6>
            </div>
            <div class="card-body px-2 py-2">
                <!-- Action Items -->
                <div class="form-row mt-2">
                    <div class="col-lg-8">
                        <div id="ai_ele_container" class="d-flex align-items-start m-main-wrapper">
                            <div class="m-elements-wrapper mr-2 w-100">
                                <?php foreach ($ai_items_data as $ai_index => $aItem) { 
                                    $ai_action_item_id = isset($aItem['id']) ? $aItem['id'] : "";
                                    $ai_action_item_val = isset($aItem['action_item']) ? $aItem['action_item'] : '';
                                    $ai_owner_val = isset($aItem['owner']) ? $aItem['owner'] : '';
                                    $ai_status_val = isset($aItem['status']) ? $aItem['status'] : '';
                                    $ai_status_val = !empty($ai_status_val) ? $ai_status_val : "pending";

                                    $ai_item_status = in_array($ai_status_val, array("pending")) ? true : false;
                                ?>
                                <!-- Input container -->
                                <div id="aitem<?php echo $ai_action_item_id; ?>" class="m-element-wrapper ai_item_container jumbotron jumbotron-fluid px-2 py-2 mb-2 mb-2 <?php echo $ai_item_status === false ? 'h-item hide' : '' ?> <?php echo empty($ai_action_item_id) ? 'add_item' : '' ?>" >
                                    <div>
                                    <!-- Field container -->
                                    <input type="hidden" name="tmp_<?php echo $field_prefix; ?>ai_id[]" value="<?php echo $ai_action_item_id; ?>">
                                    <div class="form-group col-md-12">
                                        <div class="form-row">
                                            <div class="form-group col-md-6">
                                                <label><?php echo xl('Action Item'); ?></label>
                                                <textarea class="form-control" name="tmp_<?php echo $field_prefix; ?>ai_action_item[]" rows="2"><?php echo $ai_action_item_val; ?></textarea>
                                            </div>
                                            <div class="form-group col-md-3">   
                                                <label><?php echo xl('Owner'); ?></label>
                                                <select name="tmp_<?php echo $field_prefix; ?>ai_owner[]" class="form-control">
                                                    <?php MsgUserGroupSelect($ai_owner_val, true, false, false, array(), true); ?>
                                                </select>
                                            </div>
                                            <div class="form-group col-md-3">  
                                                <label><?php echo xl('Status'); ?></label>
                                                <select name="tmp_<?php echo $field_prefix; ?>ai_status[]" class="form-control">
                                                    <option value=""></option>
                                                    <option value="pending" <?php echo $ai_status_val == "pending" ? 'selected="selected"' : ''; ?>><?php echo xl('Pending'); ?></option>
                                                    <option value="done" <?php echo $ai_status_val == "done" ? 'selected="selected"' : ''; ?>><?php echo xl('Done'); ?></option>
                                                </select>
                                            </div>
                                        </div>
                                        <div class="view_log_container">
                                            <?php if(!empty($ai_action_item_id)) { ?>
                                                <a href="javascript:void(0)" onClick="caselibObj.open_field_log('', '<?php echo $ai_action_item_id ?>', '', 'form_cases')" style="margin-right: 12px;"><?php echo xl('View logs'); ?></a>
                                            <?php } ?> 
                                            <?php if(!empty($id)) { ?>
                                            <a href="javascript:void(0)" onClick="caselibObj.send_action_items_reminder('<?php echo $id ?>', '<?php echo !empty($ai_action_item_id) ? $ai_action_item_id : 'new'; ?>', '<?php echo $pid ?>', this)"><?php echo xl('Send Reminder'); ?></a>
                                            <?php } ?> 
                                        </div>
                                    </div>

                                    </div>
                                    <!-- Remove Button -->
                                    <button type="button" class="btn btn-primary m-btn-remove" style="float:right"><i class="fa fa-times" aria-hidden="true"></i></button>
                                </div>
                                
                                <?php } ?>
                            </div>

                            <!-- Add more item btn -->
                            <button type="button" class="btn btn-primary m-btn-add" style="white-space: nowrap;"><i class="fa fa-plus" aria-hidden="true"></i> <?php echo xl('Add more'); ?></button>
                        </div>

                        <div>
                            <button type="button" class="btn btn-primary" id="ai_show_all"><?php echo xl('Show All'); ?></button>
                            <button type="button" class="btn btn-primary" id="ai_show_pending_item" style="display: none;"><?php echo xl('Hide'); ?></button>
                        </div>
                    </div>
                    <div class="col-lg-4">
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php if(isset($_REQUEST['sectionto'])) { ?>
<script type="text/javascript">
    $(function() {
        setTimeout(function() {
            $('#ai_show_all').click();
            $(window).scrollTop($("#<?php echo $_REQUEST['sectionto']; ?>").offset().top - 150 );
        }, 100);
    });
</script>
<?php } ?>

<style type="text/css">
    .ai_item_container {
        display: grid;
        grid-template-columns: 1fr auto;
        align-items: start;
    }

    .ai_item_container.hide {
        display: none !important;
    }

    .add_item .view_log_container {
/*        display: none;*/
    }

    .ai_item_container .m-btn-remove {
        display: none;
    }

    .add_item.ai_item_container .m-btn-remove {
        display: block !important;
    }
</style>

<script type="text/javascript">
    // Validation Function
    window.formScriptValidations.push(() => caselibObj.validate_CaseForm());
    var clp = null;

    $(document).ready(function(){
        // Init multi elements
        $('#reahab_wrapper').multielement();
        $('#lpc_ele_container').multielement();
        $('#ai_ele_container').multielement();

        //Init check
        caselibObj.piCaseManagerSet('#pi_case_row');

        // Lawyer/Paralegal Contacts Set Info
        $('#lpc_ele_container').on('change', 'select[data-field-id="lp_contact"]', function() {
            caselibObj.setLawyerParalegalContacts(this);
        });

        $('#lpc_ele_container select[data-field-id="lp_contact"]').each(function(i, ele) {
          caselibObj.setLawyerParalegalContacts(ele);
        });
    });

    $(document).on('click', '.medium_modal', function(e) {
        clp = $(this).parent().parent().find('select.form-control');

        e.preventDefault();
        e.stopPropagation();
        dlgopen('', '', 700, 400, '', '', {
            buttons: [
                {text: '<?php echo xla('Close'); ?>', close: true, style: 'default btn-sm'}
            ],
            //onClosed: 'refreshme',
            allowResize: false,
            allowDrag: true,
            dialogId: '',
            type: 'iframe',
            url: $(this).attr('href')
        });
    });

    // This is for callback by the find-user popup.
    function setuser(uid, uname, username, status) {
        if(clp && clp != null) {
            $(clp).val(uid).trigger('change');
        }
    }

    $(document).on('click', '#ai_show_all', function(e) {
        $('.ai_item_container.h-item').removeClass('hide');
        $(this).hide();
        $('#ai_show_pending_item').show();
    });

    $(document).on('click', '#ai_show_pending_item', function(e) {
        $('.ai_item_container.h-item').addClass('hide');
        $(this).hide();
        $('#ai_show_all').show();
    });

    $(document).on('change', '.hidden_sec_input', function(e) {
        if($(this).val() == "0") {
            $('#vh_case_manager').val("");
        } else {
            $('#vh_case_manager').val($("#case_header_case_manager").val());
        }
    });

    function casemanagerChange(ele) {
        $('#vh_case_manager').val($(ele).val());
    }
</script>