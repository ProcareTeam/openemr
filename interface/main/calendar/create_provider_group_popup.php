<?php

/**
 * interface/main/calendar/find_group_popup.php
 *
 * @package   OpenEMR
 * @link      http://www.open-emr.org
 * @author    Rod Roark <rod@sunsetsystems.com>
 * @author    Shachar Zilbershlag <shaharzi@matrix.co.il>
 * @author    Amiel Elboim <amielel@matrix.co.il>
 * @author    Brady Miller <brady.g.miller@gmail.com>
 * @copyright Copyright (c) 2005-2007 Rod Roark <rod@sunsetsystems.com>
 * @copyright Copyright (c) 2016 Shachar Zilbershlag <shaharzi@matrix.co.il>
 * @copyright Copyright (c) 2016 Amiel Elboim <amielel@matrix.co.il>
 * @copyright Copyright (c) 2018 Brady Miller <brady.g.miller@gmail.com>
 * @license   https://github.com/openemr/openemr/blob/master/LICENSE GNU General Public License 3
 */

require_once('../../globals.php');

use OpenEMR\Common\Acl\AclMain;
use OpenEMR\Common\Csrf\CsrfUtils;
use OpenEMR\Core\Header;

$Saved = 0;
/*if (!empty($_POST)) {
    if (!CsrfUtils::verifyCsrfToken($_POST["csrf_token_form"])) {
        CsrfUtils::csrfNotVerified();
    }
}*/
$msg = "";
$group_title = "";
$group_providers = [];
$edit_group_id = 0;
$is_super_user = AclMain::aclCheckCore('admin', 'super');
$chk_visible_to_all = 0;
if(isset($_POST['edit-group']))
{
    $edit_provider = sqlQuery("select * from user_provider_groups where id = " . $_POST['edit-group']);
    $group_title = $edit_provider['title'];
    $group_providers = explode(",", $edit_provider['provider_ids']);
    $edit_group_id = $edit_provider['id'];
    $chk_visible_to_all = isset($edit_provider['visible_to_all']) && !empty($edit_provider['visible_to_all']) ? $edit_provider['visible_to_all'] : 0;
}
if(isset($_POST["delete-group"]))
{
    
    sqlStatement("DELETE from user_provider_groups  WHERE id =" . $_POST['delete-group'] );
    $msg = "Group Deleted Successfully";
    $Saved = 1;
}
if(isset($_POST["update_group"]))
{
    $visible_to_all = isset($_POST['chk_visible_to_all']) ? 1 : 0;
    $selected_providers = implode(', ',$_POST["providers_selected"]);
    sqlStatement("UPDATE user_provider_groups SET title ='" . $_POST['provider_group_name'] . "', user_id=" . $_SESSION['authUserID'] . ", provider_ids= '" . $selected_providers . "', visible_to_all=" . $visible_to_all . " WHERE id =" . $_POST['update_group_id'] );
    $msg = "Group Updated Successfully";
    $Saved = 1;
}
if(isset($_POST["save_group"]))
 {
    $visible_to_all = isset($_POST['chk_visible_to_all']) ? 1 : 0;
    
    $selected_providers = implode(', ',$_POST["providers_selected"]);
    sqlStatement("INSERT INTO user_provider_groups SET title ='" . $_POST['provider_group_name'] . "', user_id=" . $_SESSION['authUserID'] . ", provider_ids= '" . $selected_providers . "', visible_to_all=" . $visible_to_all . "");
    $msg = "Group Created Successfully";
    $Saved = 1;
 }
 
 $provider_groups_sql = sqlStatement("select * from user_provider_groups where user_id =". $_SESSION['authUserID'] ."");
 $provider_groups = [];
 for ($iter = 0; $row = sqlFetchArray($provider_groups_sql); $iter++) {
     $provider_groups[$iter] = $row;
 }

?>

<html>
<head>
    <title><?php echo xlt('Group Finder'); ?></title>
    <?php Header::setupHeader('opener'); ?>

    <style>
        form {
            padding: 0px;
            margin: 0px;
        }

        a {
            color: #007bff !important;
            cursor: pointer;
            text-decoration: none;
            background-color: transparent;
        }
        #searchCriteria {
            text-align: center;
            width: 100%;
            font-size: 0.8em;
            background-color: #ddddff;
            font-weight: bold;
            padding: 3px;
        }

        #searchResultsHeader {
            width: 100%;
            background-color: lightgrey;
        }

        #searchResultsHeader table {
            width: 96%; /* not 100% because the 'searchResults' table has a scrollbar */
            border-collapse: collapse;
        }

        #searchResultsHeader th {
            font-size: 0.7em;
        }

        #searchResults {
            width: 96%;
            height: 80%;
            overflow: auto;
        }

        #results_table{
            text-align: center;
        }

        /* search results column widths */
        .srName {
            width: 30%;
        }

        .srGID {
            width: 21%;
        }

        .srType {
            width: 17%;
        }

        .srStartDate {
            width: 17%;
        }

        #searchResults table {
            width: 100%;
            border-collapse: collapse;
            background-color: white;
        }

        #searchResults tr {
            cursor: hand;
            cursor: pointer;
        }

        #searchResults td {
            font-size: 0.7em;
            border-bottom: 1px solid #eee;
        }

        .oneResult {
        }

        .billing {
            color: red;
            font-weight: bold;
        }

        /* for search results or 'searching' notification */
        #searchstatus {
            font-size: 0.8em;
            font-weight: bold;
            padding: 1px 1px 10px 1px;
            font-style: italic;
            color: black;
            text-align: center;
        }

        .noResults {
            background-color: #ccc;
        }

        .tooManyResults {
            background-color: #fc0;
        }

        .howManyResults {
            background-color: #9f6;
        }

        #searchspinner {
            display: inline;
            visibility: hidden;
        }

        /* highlight for the mouse-over */
        .highlight {
            background-color: #336699;
            color: white;
        }
    </style>

    <script>

        function selgid(gid, name, end_date) {
            if (opener.closed || !opener.setgroup)
                alert(<?php echo xlj('The destination form was closed; I cannot act on your selection.'); ?>);
            else
                opener.setgroup(gid, name, end_date);
            dlgclose();
            return false;
        }

    </script>

</head>

<body class="body_top">
    <?php 
        $providers =getProviderInfo();
    ?>
    <form method="post" action="<?=$_SERVER['PHP_SELF'];?>">
        <?php if($Saved==1):?>
            <div class="alert alert-success">
                <strong><?php echo $msg; ?></strong> .
            </div>
        <?php elseif($Saved==2) :?>
            <div class="alert alert-danger">
                <strong>Danger!</strong> Indicates a dangerous or potentially negative action.
            </div>
        <?php endif;?>
        <div class="form-group">
            <label for="provider_group_name">Group Name</label>
            <input type="input" class="form-control" id="provider_group_name" value="<?php echo $group_title;?>" name="provider_group_name"  placeholder="Enter Provider Group Name" required>
        </div>
        <div class="">
            <label for="providers">Select Providers</label>
            <select multiple size='5' class="form-select " style="margin-left:10px;" name="providers_selected[]" id="providers_selected[]" required>
                <?php foreach($providers as $provider):?>
                    <?php if(in_array($provider['id'], $group_providers)) :?>
                        <option value="<?php echo $provider['id'];?>" selected> <?php echo $provider['lname'] . ' ' . $provider['fname']; ?></option>
                    <?php else: ?>    
                        <option value="<?php echo $provider['id'];?>"> <?php echo $provider['lname'] . ' ' . $provider['fname']; ?></option>
                    <?php endif;?>
                <?php endforeach; ?>
            </select>
        </div>
        <?php if($is_super_user):?>
            <div class="form-group">
                <label for="visible_to_all">Make it available for all</label>
                <input type="checkbox" id="chk_visible_to_all" name="chk_visible_to_all" <?php echo $chk_visible_to_all == 1 ?  "checked" :  ''; ?> />
            </div>
        <?php endif; ?>
        <?php if($edit_group_id > 0): ?>
            <input type="hidden" name="update_group_id" value="<?php echo $edit_group_id; ?> ">
            <input type="submit" class="btn btn-primary" name="update_group" id="update_group" value="submit"></input>
        <?php else: ?>      
            <input type="submit" class="btn btn-primary" name="save_group" id="save_group" value="submit"></input>
        <?php endif; ?>  
    </form>

    <br/><br/>
    <div class="table-responsive">
        <table class="table table-striped table-sm">
            <thead>
                <tr>
                    <th><?php echo xlt('Group Name'); ?></th>
                    <th><?php echo xlt('Providers'); ?></th>
                    <?php if($is_super_user):?>
                        <th><?php echo xlt('Visible To All'); ?></th>
                    <?php endif; ?>    
                    <th><?php echo xlt(''); ?></th>
                    <th><?php echo xlt(''); ?></th>
                    
                </tr>    
            </thead>
            <tbody>
            <?php foreach($provider_groups as $group): ?>
                <tr>
                    <td><?php echo $group['title'];?></td>
                    <td>
                        <?php 
                            $user_group_provider = sqlStatement("select * from users where id in (". $group['provider_ids'] . ")");
                            $provinfo = [];
                            for ($iter = 0; $row = sqlFetchArray($user_group_provider); $iter++) {
                                $provinfo[] = $row['lname'] . ' ' . $row['fname'] ;
                            }
                            $provider_names = implode(", ", $provinfo);
                            echo $provider_names;
                        ?>
                    </td>
                    <?php if($is_super_user):?>
                        <td><input type="checkbox" disabled  <?php echo $group['visible_to_all']== 1 ?  "checked" :  ''; ?>  /></td>
                    <?php endif; ?>
                    <td><small><a id="editGroup" data-id="<?php echo $group['id']; ?>">Edit</a></small></td>
                    <td><small><a id="delete-group" data-id="<?php echo $group['id']; ?>">Delete</a></small></td>
                </tr>                                
            <?php endforeach;?>   
            </tbody>
        </table>
    </div>
 
</body>
<script>
    $(document).on('click', '#editGroup', function(e) {
			var href = window.location.href;
			var group_id = $(this).data('id');
			
			var form = $('<form action="'+href+'" method="post" style="display: none;"></form>');
			form.append($('<input type="hidden" name="edit-group" value="'+group_id+'">'));
			$('body').append(form);
			form.submit();
		
	});
    $(document).on('click', '#delete-group', function(e) {
		if (confirm('Are you sure you want to permanently delete this group?'))
		{
			var href = window.location.href;
			var group_id = $(this).data('id');
			
			var form = $('<form action="'+href+'" method="post" style="display: none;"></form>');
			form.append($('<input type="hidden" name="delete-group" value="'+group_id+'">'));
			$('body').append(form);
			form.submit();
		}
		else
		{
			return false;
		}
	});
</script>    
</html>
