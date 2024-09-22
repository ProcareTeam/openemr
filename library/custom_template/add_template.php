<?php

// +-----------------------------------------------------------------------------+
// Copyright (C) 2011 Z&H Consultancy Services Private Limited <sam@zhservices.com>
//
//
// This program is free software; you can redistribute it and/or
// modify it under the terms of the GNU General Public License
// as published by the Free Software Foundation; either version 2
// of the License, or (at your option) any later version.
//
//
// This program is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
//
// A copy of the GNU General Public License is included along with this program:
// openemr/interface/login/GnuGPL.html
// For more information write to the Free Software
// Foundation, Inc., 59 Temple Place - Suite 330, Boston, MA  02111-1307, USA.
//
// Author:   Eldho Chacko <eldho@zhservices.com>
//           Jacob T Paul <jacob@zhservices.com>
//
// +------------------------------------------------------------------------------+



require_once("../../interface/globals.php");

use OpenEMR\Common\Acl\AclMain;
use OpenEMR\Core\Header;

$list_id = $_REQUEST['list_id'];

// @VH: Code Section
if (trim($_POST['categoryname']) != '' && !empty($_POST['multi_context']) && $_POST['action'] == 'add') {
    $templateid = $_POST['categoryname'];
    $arr = $_POST['multi_context'];

    for ($i = 0; $i <= count($arr) - 1; $i++) {
        $sql = sqlStatement("SELECT * FROM customlists AS cl LEFT OUTER JOIN template_users AS tu ON cl.cl_list_slno=tu.tu_template_id
                        WHERE cl_list_item_long=? AND cl_list_type=3 AND cl_deleted=0 AND cl_list_id=? AND tu.tu_user_id=?", array($templateid, $arr[$i], $_SESSION['authUserID']));
        $cnt = sqlNumRows($sql);
        if ($cnt == 0) {
            $newid = sqlInsert("INSERT INTO customlists (cl_list_id,cl_list_type,cl_list_item_long,cl_creator) VALUES (?,?,?,?)", array($arr[$i], 3, $templateid, $_SESSION['authUserID']));
            sqlStatement("INSERT INTO template_users (tu_user_id,tu_template_id) VALUES (?,?)", array($_SESSION['authUserID'], $newid));
        }
    }
} elseif ($_POST['action'] == 'delete' && $_POST['item'] != '') {
    $templateid = isset($_POST['item']) ? $_POST['item'] : "";
    if(!empty($templateid)) {
        sqlStatement("UPDATE customlists SET cl_deleted=1 WHERE cl_list_type=3 AND cl_list_slno=?", array($templateid));
        sqlStatement("DELETE FROM template_users WHERE tu_template_id=?", array($templateid));
        $res = sqlStatement("SELECT * FROM customlists AS cl WHERE cl_list_id=?", array($templateid));
        while ($row = sqlFetchArray($res)) {
            sqlStatement("UPDATE customlists SET cl_deleted=1 WHERE cl_list_slno=?", array($row['cl_list_slno']));
            sqlStatement("DELETE FROM template_users WHERE tu_template_id=?", array($row['cl_list_slno']));
        }
    }
} elseif ($_POST['action'] == 'update' && trim($_POST['updatecategoryname']) != '' && !empty($_POST['multi_context'])) {
    $new_context_id = is_array($_POST['multi_context']) && !empty($_POST['multi_context']) ? $_POST['multi_context'][0] : "";
    $item_id = isset($_POST['item']) ? $_POST['item'] : "";
    $new_category_name = isset($_POST['updatecategoryname']) ? $_POST['updatecategoryname'] : "";

    if(!empty($new_context_id) && !empty($item_id)) {
        $sql = sqlStatement("SELECT * FROM customlists WHERE cl_list_type=3 AND cl_deleted=0 AND cl_list_slno = ?", array($item_id));
        $cnt = sqlNumRows($sql);
        if ($cnt == 1) {
            sqlStatement("UPDATE customlists SET cl_list_id=?, cl_list_item_long=? WHERE cl_list_slno=?", array($new_context_id, $new_category_name, $item_id));
        }
    }
}
// END

?>
<html>
    <head>
        <?php Header::setupHeader('opener'); ?>

        <!-- @VH: Styles -->
        <style>
            .bottom {
                border-bottom: 1px solid var(--black);
            }
            .top {
                border-top: 1px solid var(--black);
            }
            .left {
                border-left: 1px solid var(--black);
            }
            .right {
                border-right:1px solid var(--black);
            }
            .class1 {
                background-color: #add9e9;
            }
            .class2 {
                background-color: #b1c0a5;
            }
            #multi_context {
                width: 100%;
                margin-bottom: 15px;
            }
        </style>
        <!-- END -->

        <script>
        function add_template(){
            top.restoreSession();
            mainform=window.opener.opener.document;
            if(document.getElementById('template_name').value){
                len = document.getElementById('multi_context').options.length;
                sel_len=0;
                val="";
                for(i=0;i<len;i++){
                   if(document.getElementById('multi_context').options[i].selected==true){
                    sel_len++;
                    val+=document.getElementById('multi_context').options[i].value+"|";
                   }
                }
                if(sel_len>0){
                $.ajax({
                type: "POST",
                url: "ajax_code.php",
                dataType: "html",
                data: {
                     templateid: document.getElementById('template_name').value,
                     list_id: <?php echo js_escape($list_id);?>,
                     multi: val,
                     source: "add_template"
                },
                async: false,
                success: function(thedata){
                        if(thedata=="Fail"){
                            alert(document.getElementById('template_name').value+" <?php echo addslashes(xl('already exists'));?>");
                            return false;
                        }
                        else{
                            mainform.getElementById('templateDD').innerHTML = thedata;
                            alert("<?php echo addslashes(xl('Successfully added category'));?> "+document.getElementById('template_name').value);
                            //window.opener.opener.location.reload();
                            dlgclose();
                        }
                            },
                error:function(){

                }
                });
                }
                else{
                    alert("<?php echo addslashes(xl('You should select at least one context'));?>");
                }
            }
            else{
                alert("<?php echo addslashes(xl('Category name is empty'));?>");
                return false;
            }
        }

        // @VH: Script section
        $(function () {
        $('#categoryadd').hide();
        $('#categoryupdate').hide();
        $('#addcategorybtn').click(function() {
           $('#categoryadd').show(600);
           $('#categorylist').hide(600);
           $('#addcategorybtn').hide(600);
           return false;
         });
        $('#cancel').click(function() {
           $('#categoryadd').hide(600);
           $('#categorylist').show(600);
           $('#addcategorybtn').show(600);
           document.getElementById('multi_context').addAttribute("multiple");
           return false;
         });
        $('#ucancel').click(function() {
           $('#categoryupdate').hide(600);
           $('#categorylist').show(600);
           $('#addcategorybtn').show(600);
           document.getElementById('multi_context').addAttribute("multiple");
           return false;
         });
        });

        function checkSubmit(){
            top.restoreSession();
            if(document.getElementById('categoryname').value){
                document.getElementById('action').value='add';
                document.designation_managment.submit();
            }
            else{
                alert("<?php echo addslashes(xl('Category name can\'t be empty'));?>");
            }
        }
        function deleteme(id){
            top.restoreSession();
            msg = '';
            msg = "<?php echo addslashes(xl('Do you want to delete this?'));?>";
            if(confirm(msg)){
            document.getElementById('action').value='delete';
            document.getElementById('item').value=id;
            document.designation_managment.submit();
            }
        }
        function editme(id,val,contextid=''){
            top.restoreSession();
            $('#categoryupdate').show(600);
            $('#categorylist').hide(600);
            $('#addcategorybtn').hide(600);
            document.getElementById('item').value=id;
            document.getElementById('updatecategoryname').value=val;
            document.getElementById('multi_context').removeAttribute("multiple");
            document.getElementById('multi_context').value = contextid;
        }
        function checkUpdate(){
            top.restoreSession();
            if(document.getElementById('updatecategoryname').value){
            document.getElementById('action').value='update';
            document.designation_managment.submit();
            }
            else{
               alert("<?php echo addslashes(xl('Category name can\'t be empty'));?>");
            }
        }
        // END
        </script>
    </head>
    <body class="body_top">
        <!-- @VH: layout change -->
        <form name="designation_managment" action="" method="post">
            <table cellpadding='2' cellspacing='0' border="0" align="center">
                <tr height="30">
                  <td class='title_bar' colspan="4" align="center"><u><?php echo htmlspecialchars(xl('Add Category'), ENT_QUOTES);?></u></td>
                  <td class='title_bar' align="center"><a href="#" id="addcategorybtn" class="btn btn-primary"><span><?php echo htmlspecialchars(xl('Add'), ENT_QUOTES);?></span></a></td>
                </tr>

                <tr class="text">
                    <td>
                        <select multiple name="multi_context[]" id="multi_context" size="5" style="width:100%">
                            <?php
                            $res = sqlStatement("SELECT * FROM customlists WHERE cl_list_type=2 AND cl_deleted=0");
                            while ($row = sqlFetchArray($res)) {
                                echo "<option value='" . htmlspecialchars($row['cl_list_id'], ENT_QUOTES) . "'>" . htmlspecialchars($row['cl_list_item_long'], ENT_QUOTES) . "</option>";
                            }
                            ?>
                        </select>
                    </td>
                </tr>

                <!-- @VH: Categorylist -->
                <tr id="categorylist">
                    <td colspan="5">
                        <table>
                            <tr>
                             <td align="center" class="title_bar_top top right bottom left">#</td>
                             <td align="center" class="title_bar_top top right bottom"><?php echo htmlspecialchars(xl('Category'), ENT_QUOTES);?></td>
                             <td align="center" class="title_bar_top top right bottom"><?php echo htmlspecialchars(xl('Edit'), ENT_QUOTES);?></td>
                             <td align="center" class="title_bar_top top right bottom"><?php echo htmlspecialchars(xl('Delete'), ENT_QUOTES);?></td>
                            </tr>
                            <?php
                            $res = sqlStatement("SELECT c.*, c1.cl_list_item_long as context_text, c1.cl_list_slno as context_id FROM customlists c left join customlists c1 on c1.cl_list_slno = c.cl_list_id AND c1.cl_list_type = 2 WHERE c.cl_list_type=3 AND c.cl_deleted=0");
                            $i = 0;
                            while ($row = sqlFetchArray($res)) {
                                $i++;
                                $class = ($class == 'class1') ? 'class2' : 'class1';
                                $titleText = isset($row['context_text']) && !empty($row['context_text']) ? $row['context_text'] . "->" : "";
                                $titleText .= $row['cl_list_item_long'];
                                ?>
                                <tr class="text <?php echo $class;?>">
                                    <td class="right bottom left"><?php echo htmlspecialchars($i, ENT_QUOTES);?></td>
                                    <td class="right bottom"><?php echo htmlspecialchars(xl($titleText), ENT_QUOTES);?></td>
                                    <td class="right bottom">
                                        <?php if (AclMain::aclCheckCore('nationnotes', 'nn_configure')) { ?>
                                        <a href="#" onclick='editme("<?php echo htmlspecialchars($row['cl_list_slno'], ENT_QUOTES);?>","<?php echo htmlspecialchars($row['cl_list_item_long'], ENT_QUOTES);?>","<?php echo htmlspecialchars($row['context_id'], ENT_QUOTES);?>")'><img src='<?php echo $GLOBALS['images_static_relative']; ?>/b_edit.png' border=0></a>
                                        <?php } ?>
                                    </td>
                                    <td class="right bottom">
                                        <?php if (AclMain::aclCheckCore('nationnotes', 'nn_configure')) { ?>
                                        <a href="#" onclick="deleteme(<?php echo htmlspecialchars($row['cl_list_slno'], ENT_QUOTES);?>)"><img src='<?php echo $GLOBALS['images_static_relative']; ?>/deleteBtn.png' border=0></a>
                                        <?php } ?>
                                    </td>
                                </tr>
                                <?php
                            }
                            ?>
                        </table>
                    </td>
                </tr>
                <tr id="categoryadd">
                    <td colspan="3"><input type="text" name="categoryname" id="categoryname"></td>
                    <td colspan="1"><a href="#" onclick="checkSubmit()" class="btn btn-primary"><span><?php echo htmlspecialchars(xl('Save'), ENT_QUOTES);?><a href="#" id="cancel" class="btn btn-secondary"><span><?php echo htmlspecialchars(xl('Cancel'), ENT_QUOTES);?></span></a></span></a></td>
                </tr>
                <tr id="categoryupdate">
                    <td colspan="3"><input type="text" name="updatecategoryname" id="updatecategoryname"></td>
                    <td colspan="1"><a href="#" onclick="checkUpdate()" class="btn btn-primary"><span><?php echo htmlspecialchars(xl('Update'), ENT_QUOTES);?><a href="#" id="ucancel" class="btn btn-secondary"><span><?php echo htmlspecialchars(xl('Cancel'), ENT_QUOTES);?></span></a></span></a></td>
                </tr>
                <input type="hidden" name="action" id="action">
                <input type="hidden" name="item" id="item">
                <input type="hidden" name="stat" id="stat">
                <!-- END -->
            </table>
        </form>
    </body>
</html>
