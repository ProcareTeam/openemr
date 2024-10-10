<?php

/**
 * The address book entry editor.
 * Available from Administration->Addr Book in the concurrent layout.
 *
 * @package   OpenEMR
 * @link      http://www.open-emr.org
 * @author    Rod Roark <rod@sunsetsystems.com>
 * @author    tony@mi-squared.com
 * @author    Jerry Padgett <sjpadgett@gmail.com>
 * @author    Brady Miller <brady.g.miller@gmail.com>
 * @copyright Copyright (c) 2006-2010, 2016 Rod Roark <rod@sunsetsystems.com>
 * @copyright Copyright (c) 2018 Brady Miller <brady.g.miller@gmail.com>
 * @license   https://github.com/openemr/openemr/blob/master/LICENSE GNU General Public License 3
 */

require_once(dirname(__FILE__, 7) . "/interface/globals.php");
require_once("$srcdir/options.inc.php");

use OpenEMR\Common\Acl\AclMain;
use OpenEMR\Common\Csrf\CsrfUtils;
use OpenEMR\Common\Twig\TwigContainer;
use OpenEMR\Core\Header;

/* OEMRAD - Changes */
$popup = empty($_GET['popup']) ? 0 : 1;
$use_as_select = empty($_GET['select']) ? 0 : 1;
/* End */

// @VH - Added acl rule for search addresses
if (!AclMain::aclCheckCore('admin', 'practice') && !AclMain::aclCheckCore('lists', 'addresses') && !AclMain::aclCheckCore('admin', 'addresses')) {
    echo (new TwigContainer(null, $GLOBALS['kernel']))->getTwig()->render('core/unauthorized.html.twig', ['pageTitle' => xl("Address Book")]);
    exit;
}

if (!empty($_POST)) {
    if (!CsrfUtils::verifyCsrfToken($_POST["csrf_token_form"])) {
        CsrfUtils::csrfNotVerified();
    }
}

$popup = empty($_GET['popup']) ? 0 : 1;
$rtn_selection = 0;
if ((!empty($_GET['popup']) && $_GET['popup'] == 2) || (!empty($_POST['popup']) && $_POST['popup'] == 2)) {
    $rtn_selection = 2;
}

$form_fname = trim($_POST['form_fname'] ?? '');
$form_lname = trim($_POST['form_lname'] ?? '');
$form_specialty = trim($_POST['form_specialty'] ?? '');
$form_organization = trim($_POST['form_organization'] ?? '');
$form_npi = trim($_POST['form_npi'] ?? '');
$form_abook_type = trim($_REQUEST['form_abook_type'] ?? '');
$form_external = !empty($_POST['form_external']) ? 1 : 0;
$form_inactive = !empty($_POST['form_inactive']) ? 1 : 0;

$sqlBindArray = array();
$query = "SELECT u.*, lo.option_id AS ab_name, lo.option_value as ab_option FROM users AS u " .
  "LEFT JOIN list_options AS lo ON " .
  "list_id = 'abook_type' AND option_id = u.abook_type AND activity = 1 " .
  "WHERE u.email != '' AND u.phonecell != '' AND ( u.authorized = 1 OR u.username = '' ) ";
if ($form_organization) {
    $query .= "AND u.organization LIKE ? ";
    array_push($sqlBindArray, $form_organization . "%");
}

if ($form_lname) {
    $query .= "AND u.lname LIKE ? ";
    array_push($sqlBindArray, $form_lname . "%");
}

if ($form_fname) {
    $query .= "AND u.fname LIKE ? ";
    array_push($sqlBindArray, $form_fname . "%");
}

if ($form_specialty) {
    $query .= "AND u.specialty LIKE ? ";
    array_push($sqlBindArray, "%" . $form_specialty . "%");
}

if ($form_npi) {
    $query .= "AND u.npi LIKE ? ";
    array_push($sqlBindArray, "%" . $form_npi . "%");
}

//if ($form_abook_type) {
    $query .= "AND u.abook_type LIKE ? ";
    array_push($sqlBindArray, "Attorney");
//}

if ($form_external) {
    $query .= "AND u.username = '' ";
}

if ($form_inactive) {
    $query .= "AND u.active = 0 ";
} else {
    $query .= "AND u.active = 1 ";
}

if ($form_lname) {
    $query .= "ORDER BY u.lname, u.fname, u.mname";
} elseif ($form_organization) {
    $query .= "ORDER BY u.organization";
} else {
    $query .= "ORDER BY u.organization, u.lname, u.fname";
}

$query .= " LIMIT 500";
$res = sqlStatement($query, $sqlBindArray);

/* OEMRAD - Changes */
$action = 'portal_addrbook_list.php';
$addl = '';
if($popup) $addl .= 'popup=' . strip_tags($_GET['popup']) . '&';
if($use_as_select) $addl .= 'select=' . strip_tags($_GET['select']) . '&';
if($addl) $action .= '?' . $addl;
/* End */
?>

<!DOCTYPE html>
<html>

<head>

<!-- OEMRAD - Change -->
<?php Header::setupHeader(['common', 'opener']); ?>

<title><?php echo xlt('Case Mgmt Portal'); ?></title>

<!-- style tag moved into proper CSS file -->

</head>

<body class="body_top">

<div class="container-fluid">
    <div class="nav navbar-fixed-top body_title">
        <div class="col-md-12">
            <h3><?php echo xlt('Case Management Portal'); ?></h3>
        <!-- OEMRAD - From action changed -->
        <form class='navbar-form' method='post' action='<?php echo $action; ?>' onsubmit='return top.restoreSession()'>
            <input type="hidden" name="csrf_token_form" value="<?php echo attr(CsrfUtils::collectCsrfToken()); ?>" />
            <input type="hidden" name="popup" value="<?php echo attr($rtn_selection); ?>" />

                <div class="form-group">
                <div class="row">
                    <div class="col-sm-2">
                    <label for="form_organization"><?php echo xlt('Organization') ?>:</label>
                    <input type='text' class="form-control inputtext" name='form_organization' size='10' value='<?php echo attr($form_organization); ?>'  title='<?php echo xla("All or part of the organization") ?>'/>&nbsp;
                    </div>
                    <div class="col-sm-2">
                    <label for="form_fname"><?php echo xlt('First Name') ?>:</label>
                    <input type='text' class="form-control inputtext" name='form_fname' size='10' value='<?php echo attr($form_fname); ?>'  title='<?php echo xla("All or part of the first name") ?>'/>&nbsp;
                    </div>
                    <div class="col-sm-2">
                    <label for="form_lname"><?php echo xlt('Last Name') ?>:</label>
                    <input type='text' class="form-control inputtext" name='form_lname' size='10' value='<?php echo attr($form_lname); ?>'  title='<?php echo xla("All or part of the last name") ?>'/>&nbsp;
                    </div>
                    <div class="col-sm-2">
                    <label for="form_specialty"><?php echo xlt('Specialty') ?>:</label>
                    <input type='text' class="form-control inputtext" name='form_specialty' size='10' value='<?php echo attr($form_specialty); ?>' title='<?php echo xla("Any part of the desired specialty") ?>'/>&nbsp;
                    </div>
                    <div class="col-sm-2">
                    <label for="form_npi"><?php echo xlt('Specialty') ?>:</label>
                    <input type='text' class="form-control inputtext" name='form_npi' size='10' value='<?php echo attr($form_npi); ?>' title='<?php echo xla("Any part of the desired NPI") ?>'/>&nbsp;
                    </div>
                    <div class="col-sm-2">
                    <?php
                    //echo '<label>' . xlt('Type') . ": " . '</label>';
                    // Generates a select list named form_abook_type:
                    //echo generate_select_list("form_abook_type", "abook_type", $form_abook_type, '', 'All');
                    ?>
                    </div>
                    </div>
                    <input type='checkbox' id="formExternal" name='form_external' value='1'<?php echo ($form_external) ? ' checked ' : ''; ?> title='<?php echo xla("Omit internal users?") ?>' />
                    <label for="formExternal"><?php echo xlt('External Only') ?></label>

                    <!-- OEMR - Inactive only filter -->
                    <input type='checkbox' id="formInactive" name='form_inactive' value='1'<?php echo ($form_inactive) ? ' checked ' : ''; ?> title='<?php echo xla("Omit active users?") ?>' />
                    <label for="formInactive"><?php echo xlt('Show Inactive') ?></label>
                    <!-- End -->

                    <input type='submit' title='<?php echo xla("Use % alone in a field to just sort on that column") ?>' class='btn btn-primary btn-search' name='form_search' value='<?php echo xla("Search") ?>'/>
                    </div>
        </form>
    </div>
    </div>

<div style="margin-top: 110px;" class="table-responsive">

<div class="alert alert-danger d-flex align-items-center p-2 mb-4" role="alert">
    <div>
    Only address book entries with the type = Attorney and a valid email address and a valid mobile telephone number can be access in this window <span style="color: red;">*</span>
    </div>
</div>

<table class="table table-sm table-bordered table-striped table-hover">
 <thead>
  <th title='<?php echo xla('Click to view or edit'); ?>'><?php echo xlt('Organization'); ?></th>
  <th><?php echo xlt('Name'); ?></th>
  <th><?php echo xlt('Type'); ?></th>
  <th><?php echo xlt('Specialty'); ?></th>
  <th><?php echo xlt('Email'); ?></th>
  <th><?php echo xlt('Mobile No'); ?></th>
 </thead>
<?php
 $encount = 0;
while ($row = sqlFetchArray($res)) {
    ++$encount;
    $username = $row['username'];
    if (! $row['active']) {
        $username = '--';
    }

    $displayName = $row['fname'] . ' ' . $row['mname'] . ' ' . $row['lname']; // Person Name
    if ($row['suffix'] > '') {
        $displayName .= ", " . $row['suffix'];
    }

    if (AclMain::aclCheckCore('admin', 'practice') || (empty($username) && empty($row['ab_name'])) || AclMain::aclCheckCore('admin', 'addresses')) {
       // Allow edit, since have access or (no item type and not a local user)
        $trTitle = xl('Edit') . ' ' . $displayName;
        echo " <tr class='address_names detail' style='cursor:pointer' " .
        "onclick='doedclick_edit(" . attr_js($row['id']) . ")' title='" . attr($trTitle) . "'>\n";
    } elseif ($use_as_select == 1) {
        /* OEMRAD - Changes */
        $trTitle = xl('Select'). ' ' . $displayName;
        echo " <tr class='address_names detail' style='cursor:pointer' " .
        "onclick='doedclick_edit(" . $row['id'] . ")' title='".attr($trTitle)."'>\n";
        /* End */

    } else {
       // Do not allow edit, since no access and (item is a type or is a local user)
        $trTitle = $displayName . " (" . xl("Not Allowed to Edit") . ")";
        echo " <tr class='address_names detail' title='" . attr($trTitle) . "'>\n";
    }

    echo "  <td>" . text($row['organization']) . "</td>\n";
    echo "  <td>" . text($displayName) . "</td>\n";
    echo "  <td>" . generate_display_field(array('data_type' => '1','list_id' => 'abook_type'), $row['ab_name']) . "</td>\n";
    echo "  <td>" . text($row['specialty']) . "</td>\n";
    echo "  <td>" . text($row['email'])     . "</td>\n";
    echo "  <td>" . text($row['phonecell'])     . "</td>\n";
    echo " </tr>\n";
}
?>
</table>
</div>

<?php if ($popup) { ?>
    <?php Header::setupAssets('topdialog'); ?>
<?php } ?>
<script>

<?php if ($popup) {
    require($GLOBALS['srcdir'] . "/restoreSession.php");
} ?>

// Callback from popups to refresh this display.
function refreshme() {
 // location.reload();
 document.forms[0].submit();
}

/* OEMRAD - Added Function */
function doedclick_edit(userid) {
    top.restoreSession();
    dlgopen('portal_addrbook_edit.php?userid=' + userid, '_blank', 650, (screen.availHeight * 75/100));
}
/* End */


</script>
</div>
</body>
</html>
