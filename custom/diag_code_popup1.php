<?php
// Copyright (C) 2008 Rod Roark <rod@sunsetsystems.com>
//
// This program is free software; you can redistribute it and/or
// modify it under the terms of the GNU General Public License
// as published by the Free Software Foundation; either version 2
// of the License, or (at your option) any later version.

require_once('../interface/globals.php');
require_once($GLOBALS['srcdir'].'/patient.inc');
require_once($GLOBALS['srcdir'].'/csv_like_join.php');
require_once($GLOBALS['srcdir'].'/formdata.inc.php');
require_once($GLOBALS['srcdir'].'/wmt-v2/wmtstandard.inc');
if(file_exists(INC_DIR . 'diag_favorites.inc')) 
						require_once(INC_DIR . 'diag_favorites.inc');
require_once($GLOBALS['fileroot'] . '/custom/code_types.inc.php');

use OpenEMR\Core\Header;
use OpenEMR\Common\Acl\AclMain;

$page_type = isset($_REQUEST['page_type']) ? $_REQUEST['page_type'] : "";
$selected_codes_json = isset($_REQUEST['selected_codes_json']) && !empty($_REQUEST['selected_codes_json']) ? $_REQUEST['selected_codes_json'] : "{}";

$info_msg = '';
$codetype = '';
if(isset($_REQUEST['codetype'])) $codetype = $_REQUEST['codetype'];
if(isset($_REQUEST['fav'])) {
	if($_REQUEST['fav'] == 'off') $use_diag_favorites = false;
}
$allowed_codes = array('ICD10');
if(!isset($GLOBALS['wmt::show_icd9'])) $GLOBALS['wmt::show_icd9'] = '';
if($GLOBALS['wmt::show_icd9']) $allowed_codes = array('ICD9', 'ICD10');
if($codetype == '' && isset($GLOBALS['wmt::default_diag_type'])) {
	$codetype = $GLOBALS['wmt::default_diag_type'];
}
$diagfield = '';
$descfield = '';
$dtfield = '';
$typefield = '';
$nextfocus = '';
$thischeck = '';
$search_term = '';
$xref = 'false';
$isDoctor = IsDoctor();
if(isset($_GET['thisdiag'])) $diagfield = $_GET['thisdiag'];
if(isset($_GET['thisdesc'])) $descfield = $_GET['thisdesc'];
if(isset($_GET['thisdate'])) $dtfield = $_GET['thisdate'];
if(isset($_GET['nextfocus'])) $nextfocus = $_GET['nextfocus'];
if(isset($_GET['thistype'])) $typefield = $_GET['thistype'];
if(isset($_GET['thischeck'])) $thischeck = $_GET['thischeck'];
if(isset($_REQUEST['search_term'])) $search_term = $_REQUEST['search_term'];
if(isset($_REQUEST['show_xref'])) $xref = $_REQUEST['show_xref'];
if(isset($_REQUEST['bn_search'])) {
	if(strtolower($_REQUEST['bn_search']) != 'search') $xref = true;
} else {
	$xref = false;
}
if(!$GLOBALS['wmt::show_icd9']) $xref = false;
$form_code_type = $codetype;
if(isset($_POST['form_code_type'])) {
	$form_code_type = $_POST['form_code_type'];
	$codetype = $_POST['form_code_type'];
}

$form_action = "diag_code_popup1.php?thisdiag=$diagfield";
if($descfield != '') $form_action .= "&thisdesc=$descfield";
if($dtfield != '') $form_action .= "&thisdate=$dtfield";
if($nextfocus != '') $form_action .= "&nextfocus=$nextfocus";
if($codetype != '') $form_action .= "&codetype=$codetype";
if($typefield != '') $form_action .= "&thistype=$typefield";
if($thischeck!= '') $form_action .= "&thischeck=$thischeck";
$base_action = $form_action;
$list_add_allowed = checkSettingMode('wmt::list_popup_add::diagnosis_category');
if($list_add_allowed) $list_add_allowed = 'true';
if(AclMain::aclCheckCore('admin','super')) $list_add_allowed = 'true';

function getAliasDetails($form_code_type, $dx_code) {
	$search_table = 'icd9_dx_code';
	if($form_code_type == 'ICD10') $search_table = 'icd10_dx_order_code';

	$codeDetails = sqlQuery("SELECT doc.* from ". $search_table ." doc WHERE doc.`formatted_dx_code` = ? and doc.active = 1 ", array($dx_code));

	$aliasDetails = sqlQuery("SELECT vdca.* from vh_dx_code_alias vdca join ". $search_table ." doc on doc.dx_id = vdca.dx_id where vdca.`type` = '" . $form_code_type . "' and doc.`formatted_dx_code` = '" . $dx_code . "' and vdca.uid = '". $_SESSION['authUserID'] ."' and doc.active = 1 ");

	if(!empty($aliasDetails)) {
		$codeDetails['alias'] = isset($aliasDetails['alias']) ? $aliasDetails['alias'] : "";
		$codeDetails['alias_id'] = isset($aliasDetails['id']) ? $aliasDetails['id'] : "";
	}

	return $codeDetails;
}

?>
<html>
<head>
<title><?php xl('Code Finder','e'); ?></title>

<link rel="stylesheet" href='<?php echo $css_header ?>' type='text/css'>
<?php Header::setupHeader(['jquery', 'jquery-ui']); ?>

<style>
td { font-size:10pt; }

div.notification {
  font-size: 0.9em;
  font-weight: bold;
	text-align: center;
	padding: 6px 22px 6px 22px;
	position: fixed;
	border: solid 1px black;
	border-radius: 10px;
	width: 180px;
	z-index: 3000;
	cursor: progress;
	box-shadow: 8px 8px 5px #888888;
}

.fieldsContainer {
	display: grid;
    grid-template-columns: 1fr auto;
}

.fieldsContainer input[type="button"],
.fieldsContainer input[type="submit"] {
	margin: 3px;
}
.select_item_list_table {
	text-align: left;
	max-width: 500px;
	margin-top: 10px;
}
.select_item_list_table > .sTitle {
	padding-left: 25px;
}
.select_item_list_table > ul {
	margin-top: 5px;
}
</style>

<script type="text/javascript">

function selcode(codetype, code, selector, codedesc) {
  if (opener.closed || ! opener.set_diag) {
   alert('The destination form was closed; I cannot act on your selection.');
  } else {
   opener.set_diag(codetype, code, selector, codedesc, '<?php echo $diagfield; ?>', '<?php echo $descfield; ?>', '<?php echo $dtfield; ?>', '<?php echo $nextfocus; ?>', '<?php echo $typefield; ?>','<?php echo $thischeck; ?>');
  window.close();
  return false;
 }
}

function hideDiv()
{
	var target = '';
	if(arguments.length > 0) target = arguments[0];
	if(target != '') {
		var div = document.getElementById(target);
		if(div != null) div.style.display = 'none';
	}
	return true;
}

function delayedHideDiv()
{
	var target = 'save-notification';
	var pause = 1500;
	if(arguments.length > 0) target = arguments[0];
	if(arguments.length > 1) pause = arguments[1];
	window.setTimeout("hideDiv('"+target+"')", pause);
	return true;
}

<?php if($use_diag_favorites) {
include_once(AJAX_DIR . 'init_ajax.inc.js'); 
?>

function ajaxAddDiagFavorite(grp) {
	var div = document.getElementById('save-notification');
	var type = document.forms[0].elements['tmp_type'].value;
	var code = document.forms[0].elements['tmp_code'].value;
	var btn = document.forms[0].elements['tmp_btn'].value;
	if(div != null) div.style.display = 'block';
	var output = 'error';
	$.ajax({
		type: "POST",
		url: "<?php echo AJAX_DIR_JS; ?>/diag_favorites.ajax.php",
		datatype: "html",
		data: {
			type: type,
			code: code, 
			group: grp
		},
		success: function(result) {
			if(result['error']) {
				output = '';
				alert('There was a problem saving the favorite\n'+result['error']);
			} else {
				var div = document.getElementById(btn);
				if(div != null) div.style.display = 'none';
				output = result;
			}
		},
		async: false
	});
	return output;
}

function set_item(grp,title) {
	document.forms[0].elements['tmp_grp'].value = grp;
	ajaxAddDiagFavorite(grp); 
	delayedHideDiv();
}

function popGroupSelection(type, code, btn) {
	document.forms[0].elements['tmp_type'].value = type;
	document.forms[0].elements['tmp_code'].value = code;
	document.forms[0].elements['tmp_btn'].value = btn;

	var linkref = '<?php echo $GLOBALS['webroot']; ?>/custom/add_list_entry_popup.php?thisList=Diagnosis_Categories&choose=true&add=<?php echo $list_add_allowed; ?>&prompt=a Category&lbl_type=Category';
	wmtOpen(linkref, '_blank', 400, 350);
}

<?php } ?>

</script>
</head>

<div id="save-notification" class="notification wmtColorMenu" style="left: 45%; top: 40%; z-index: 850; display: none; ">Saved to Favorites....</div>
<body class="body_top" onLoad='document.forms[0].elements["search_term"].focus();'>
<form method="post" name="theform" action="<?php echo $base_action?>">
<input type="hidden" tabindex="-1" name="tmp_grp" id="tmp_grp" value="">
<input type="hidden" tabindex="-1" name="page_type" id="page_type" value="<?php echo $page_type; ?>">
<input type="hidden" tabindex="-1" name="tmp_type" id="tmp_type" value="">
<input type="hidden" tabindex="-1" name="tmp_code" id="tmp_code" value="">
<input type="hidden" tabindex="-1" name="tmp_btn" id="tmp_btn" value="">
<?php if(isset($_REQUEST['fav'])) { ?>
<input type="hidden" tabindex="-1" name="fav" id="fav" value="<?php echo $_REQUEST['fav']; ?>">
<?php } ?>
<center>

<table border='0' cellpadding='5' cellspacing='0'>
 <tr> <td height="1">&nbsp;</td> </tr>
 <tr bgcolor='#ddddff'>
  <td><div class="fieldsContainer"><div><b>

<?php
if (isset($allowed_codes)) {
	if (count($allowed_codes) === 1) {
  echo "<input type='text' class='form-control' name='form_code_type' value='$codetype' size='5' readonly>\n";
	} else {
?>
   <select name='form_code_type' class='form-control'>
<?php
		foreach ($allowed_codes as $code) {
			$value = htmlspecialchars($code, ENT_QUOTES);
			// echo "Code:  ($code)  And Type [$form_code_type]<br>\n";
			$selected_attr = ($form_code_type == $code) ? " selected='selected'" : '';
			$text = htmlspecialchars($code, ENT_NOQUOTES);
?>
   	<option value='<?php echo $value ?>'<?php echo $selected_attr?>><?php echo $text ?></option>
<?php
		}
?>
   </select>
<?php
	}
} else {
  echo "   <select name='form_code_type' class='form-control'>\n";
  foreach ($code_types as $key => $value) {
    echo "    <option value='$key'";
    if ($codetype == $key || $form_code_type == $key) echo " selected";
    echo ">$key</option>\n";
  }
  echo "    <option value='PROD'";
  if ($codetype == 'PROD' || $form_code_type == 'PROD') echo " selected";
  echo ">Product</option>\n";
  echo "   </select>&nbsp;&nbsp;\n";
}
?>

 <?php xl('Search for:','e'); ?>
   <input type='text' class='form-control' name='search_term' id='search_term' size='12' value='<?php echo $search_term; ?>'
    title='<?php xl('Any part of the desired code or its description','e'); ?>' />
   &nbsp;
   <input type='submit' class='btn btn-primary mr-1' name='bn_search' value='<?php xl('Search','e'); ?>' />
   &nbsp;&nbsp;&nbsp;
   <input type='button' class='btn btn-primary' value='<?php xl('Erase','e'); ?>' onclick="selcode('', '', '', '', '', '', '', '', '<?php echo $typefield; ?>','<?php echo $thischeck; ?>')" />
   	</b>
	</div>
	<div>
		<input type='button' class='btn btn-primary' name='bn_select' value='<?php xl('Submit','e'); ?>' onClick="handleSelectItem()" />
	</div>
	</div>
  </td>
 </tr>

<?php if($GLOBALS['wmt::show_icd9']) { ?>
 <tr bgcolor="#ddddff">
	<td><input name="show_xref" id="show_xref" type="checkbox" value="true" <?php echo ($xref == 'true') ? 'checked ' : ''; ?> onchange="UpdateSubmitAction(this);" /><label for="show_xref">Show cross-referenced codes</label></td>
 </tr>
<?php } ?>
</table>
<div class="select_item_list_table">
	<span class="sTitle" style="display: none;"><b>Selected Items</b></span>
	<ul>
	</ul>
</div>
<?php if (isset($_REQUEST['bn_search'])) { ?>
<table border='0'>
 <tr>
	<td>&nbsp;</td>
	<?php
		if($page_type == 'ext') {
    		echo '<td></td>';
    	}
	?>
	<td style="width: 12x;">&nbsp;</td>
  <td><b><?php xl ('Code','e'); ?></b></td>
	<td style="width: 10px;">&nbsp;</td>
  <td colspan="2"><b><?php xl ('Description','e'); ?></b></td>
 </tr>
<?php
	// $res = code_set_search($form_code_type,$search_term);
	$search = strtoupper($search_term);

	$aliasCodeType = $form_code_type == 'ICD10' ? 'ICD10' : 'ICD9';
	$aliasres = sqlStatement("SELECT vdca.* from vh_dx_code_alias vdca where vdca.`type` = '" . $aliasCodeType . "' and vdca.`alias` like '%".add_escape_custom($search)."%' and vdca.uid = '" . $_SESSION['authUserID'] . "'");

	$dx_id_list = array();
	$dxWhereStr = "";
	$dxJoin = "";
	while ($aliasrow = sqlFetchArray($aliasres)) {
		if(isset($aliasrow['dx_id']) && !empty($aliasrow['dx_id'])) {
			$dx_id_list[] = $aliasrow['dx_id'];
		}
	}

	if(!empty($dx_id_list)) {
		$dxWhereStr = " OR (doc.dx_id in ('" . implode("','", $dx_id_list) . "')) ";
	}


	$words = explode(' ', $search);
	$code = str_replace('.', '', $search);
	$search_table = 'icd9_dx_code';
	if($form_code_type == 'ICD10') $search_table = 'icd10_dx_order_code';
	$query = "SELECT doc.*, vdca.alias, vdca.id as alias_id FROM $search_table doc left join vh_dx_code_alias vdca on vdca.uid = '" . $_SESSION['authUserID'] . "' and vdca.`type` = '". $aliasCodeType ."' and vdca.dx_id = doc.dx_id WHERE active = 1 AND ";
	if($form_code_type == 'ICD10') $query .= "valid_for_coding = 1 AND ";
	$query .= "((formatted_dx_code LIKE '%".add_escape_custom($search)."%' "; 
	if (!is_numeric($search)) {
		$short = $long = '';
		foreach($words as $word) {
			if($short) $short .= ' AND ';
			$short .= "short_desc LIKE '%".add_escape_custom($word)."%' ";
			if($long) $long.= ' AND ';
			$long.= "long_desc LIKE '%".add_escape_custom($word)."%' ";
		}
		$query .= "OR ($short) OR ($long)";
	}
	$query .= ") ". $dxWhereStr ." ) ORDER BY doc.dx_code;";
	$res = sqlStatement($query);

  while ($row = sqlFetchArray($res)) {
    $itercode = htmlspecialchars($row['formatted_dx_code'],ENT_QUOTES,'UTF-8',FALSE);
		$raw_code = $row['dx_code'];
    $itertext = htmlspecialchars(ucfirst(strtolower(trim($row['long_desc']))),ENT_QUOTES,'UTF-8',FALSE);
		$title = $itertext;
		if(strlen($itertext) > 90) $itertext = substr($itertext,0,90).'...';

		$rowalias = !empty($row['alias']) ? " - <a href='javascript:void(0);' title='".$row['alias']."' onclick='return Addeditalias(\"".$row['dx_id']."\", \"".$form_code_type."\", \"".$row['alias_id']."\")'>[ ". htmlspecialchars($row['alias'],ENT_QUOTES,'UTF-8',false) . " ]</a>" : "";

		if($use_diag_favorites && $isDoctor && empty($rowalias)) {
			$rowalias = " - <a href='javascript:void(0);' title='Add Alias' onclick='return Addeditalias(\"".$row['dx_id']."\", \"".$form_code_type."\")'>[ ADD ALIAS ]</a>";
		}
    
	//if($page_type == "ext") {
    //	$anchor = "<a href='javascript:void(0);' title='$title'>";
	//} else {
		$anchor = "<a href='' title='$title' onclick='return ".
				"selcode(\"$form_code_type\", \"$itercode\", \"\", \"$title\")'>";
	//}
    echo " <tr>";
		echo '	<td>';
		if($use_diag_favorites && $isDoctor) {
			$sql = "SELECT id FROM wmt_diag_fav WHERE code_type=? AND code=? ".
				"AND list_user=? ORDER BY seq DESC LIMIT 1";
			$binds = array($form_code_type, $row['formatted_dx_code'], 
				$_SESSION['authUser']); 
			$dup = sqlQuery($sql,$binds);
			if(!isset($dup['id'])) $dup['id'] = 0;
			if(!$dup['id']) echo "<div style='padding: 0px 8px 0px 0px; margin: 2px;' id='btn_$raw_code'><a href='javascript:;' class='css_button_small' onclick=\"popGroupSelection('$form_code_type','".$row['formatted_dx_code']."','btn_$raw_code');\"><span>Add&nbsp;Favorite</span></a></div>";
		} else echo '&nbsp;';
		echo '</td>';
		if($page_type == 'ext') {
    		echo '<td>';
    		echo '<input type="checkbox" class="itemSelect" id="check_'.$itercode.'" name="check_'.$itercode.'" value="1" data-codetype="'.$form_code_type.'" data-itercode="'.$itercode.'" data-title="'.$title.'" >';
    		echo '</td>';
    	}
    echo "  <td colspan='2'>$anchor$itercode</a></td>\n";
		echo "	<td>&nbsp;</td>\n";
    echo "  <td colspan='2'>$anchor$itertext</a>$rowalias</td>\n";
    echo " </tr>";
		if($xref == 'true') {
			$xquery = "SELECT xref.dx_icd10_target, dx.dx_code, dx.short_desc, ".
				"dx.long_desc, dx.formatted_dx_code FROM icd10_gem_dx_9_10 AS xref ".
				"LEFT JOIN icd10_dx_order_code AS dx ON xref.dx_icd10_target = ".
				"dx.dx_code WHERE xref.dx_icd9_source = ? AND xref.active > 0 ".
				"AND dx.active > 0 AND dx.valid_for_coding = 1 ORDER BY dx_code ASC";
			$alt_code_type = 'ICD10';
			if($form_code_type == 'ICD10') {
				$xquery = "SELECT xref.dx_icd9_target, dx.dx_code, dx.short_desc, ".
					"dx.long_desc, dx.formatted_dx_code FROM icd10_gem_dx_10_9 AS xref ".
					"LEFT JOIN icd9_dx_code AS dx ON xref.dx_icd9_target = ".
					"dx.dx_code WHERE xref.dx_icd10_source = ? AND xref.active > 0 ".
					"AND dx.active > 0 ORDER BY dx_code ASC";
				$alt_code_type = 'ICD9';
			}
			$xtable = sqlStatement($xquery, array($raw_code));
			while($xrow = sqlFetchArray($xtable)) {
				if($xrow['formatted_dx_code'] == '') continue;
    		$xcode = htmlspecialchars($xrow['formatted_dx_code'],ENT_QUOTES,'UTF-8',false);
    		$xtext = htmlspecialchars(ucfirst(strtolower(trim($xrow['long_desc']))),ENT_QUOTES,'UTF-8',false);
				$title = $xtext;
				if(strlen($xtext) > 80) $xtext = substr($xtext,0,80).'...';
    			//if($page_type == "ext") {
    			//	$anchor = "<a href='javascript:void(0);' title='$title'>";
				//} else {
					$anchor = "<a href='' title='$title'  onclick='return ".
						"selcode(\"$alt_code_type\", \"$xcode\", \"\", \"$title\")'>";
				//}

				$xaliasDetails = getAliasDetails($form_code_type, $xcode);
				$xalias = !empty($xaliasDetails['alias']) ? " - <a href='javascript:void(0);' title='".$row['alias']."' onclick='return Addeditalias(\"".$xaliasDetails['dx_id']."\", \"".$form_code_type."\", \"".$xaliasDetails['alias_id']."\")'>[ ". htmlspecialchars($xaliasDetails['alias'],ENT_QUOTES,'UTF-8',false) . " ]</a>" : "";

				if($use_diag_favorites && $isDoctor && empty($xalias)) {
					$xalias = " - <a href='javascript:void(0);' title='Add Alias' onclick='return Addeditalias(\"".$xaliasDetails['dx_id']."\", \"".$form_code_type."\")'>[ ADD ALIAS ]</a>";
				}
					
				echo "<tr>\n";
				echo "<td>&nbsp;</td>\n";
				echo "<td style='text-align: right; width: 12px;'>&#8226;</td>\n";
				echo "<td style='text-align: right;'>$anchor$xcode</a></td>\n";
				echo "<td>&nbsp;</td>\n";
				echo "<td colspan='2' style='padding-left: 12px;'>$anchor$xtext</a>$xalias</td>\n";
				echo "</tr>\n";
			}
		}
  }
?>
</table>

<?php } else if($use_diag_favorites && (strtoupper(substr($codetype,0,3)) == 'ICD')) { ?>
<table border='0'>
 <tr>
	<td colspan="5" style="text-align: center;"><i>Currently displaying favorites</i></td>
 </tr>
<?php
	$fav = getAllDiagFavorites($form_code_type);
	$last_cat = '~|~|';
	if(count($fav) > 0) {
?>
 <tr>
  <td></td>
  <td colspan="2"><b><?php xl ('Code','e'); ?></b></td>
	<td>&nbsp;</td>
  <td><b><?php xl ('Description','e'); ?></b></td>
 </tr>
<?php
		foreach($fav as $xrow) {
			$favaliasDetails = getAliasDetails($form_code_type, $xrow['code']);
			$favalias = !empty($favaliasDetails['alias']) ? " - <a href='javascript:void(0);' title='".$row['alias']."' onclick='return Addeditalias(\"".$favaliasDetails['dx_id']."\", \"".$form_code_type."\", \"".$favaliasDetails['alias_id']."\")'>[ ". htmlspecialchars($favaliasDetails['alias'],ENT_QUOTES,'UTF-8',false) . " ]</a>" : "";

			$favremove = "";
			if($use_diag_favorites && $isDoctor) {
				$favremove = " - <a href='javascript:void(0);' title='Favorite remove' onclick='return RemoveFavorite(\"".$xrow['id']."\")' style='color:red;'><b><i class=\"fa fa-times\" aria-hidden=\"true\"></i></b></a>";
				
				if(empty($favalias)) {
					$favalias = " - <a href='javascript:void(0);' title='Add Alias' onclick='return Addeditalias(\"".$favaliasDetails['dx_id']."\", \"".$form_code_type."\")'>[ ADD ALIAS ]</a>";
				}
			}

			if($xrow['code'] == '') continue;
			if($xrow['grp'] != $last_cat) {
				$cat = ListLook($xrow['grp'], 'Diagnosis_Categories');
				$last_cat = $xrow['grp'];
				if($last_cat == '') $cat = 'No Category Assigned';
				echo "<tr><td colspan='5'><b><i>$cat</i></b></td></tr>\n";
 			}
 			$xcode = htmlspecialchars($xrow['code'],ENT_QUOTES);
 			$xtext = htmlspecialchars(ucfirst(trim($xrow['title'])),ENT_QUOTES);
			$title = $xtext;
			if(strlen($xtext) > 80) $xtext = substr($xtext,0,80).'...';
 			$anchor = "<a href='' title='$title'  onclick='return ".
					"selcode(\"$form_code_type\", \"$xcode\", \"\", \"$title\")'>";
			echo "<tr>\n";
			if($page_type == 'ext') {
    		echo '<td>';
	    		echo '<input type="checkbox" class="itemSelect" id="check_'.$xrow['code'].'" name="check_'.$xrow['code'].'" value="1" data-codetype="'.$form_code_type.'" data-itercode="'.$xrow['code'].'" data-title="'.$title.'" >';
	    		echo '</td>';
	    	}
			echo "<td style='text-align: right;'>&#9733;</td>\n";
			echo "<td style='text-align: right;'>$anchor$xcode</a></td>\n";
			echo "<td>&nbsp;</td>\n";
			echo "<td colspan='2'>$anchor$xtext</a>$favremove$favalias</td>\n";
			echo "</tr>\n";
		}
	} else {
		echo "<tr><td>&nbsp;</td></tr>\n";
		echo "<tr><td colspan='5' style='text-align: center;'><b><i>No favorites are currently defined -<br>They can be defined in the Diag Favorites heading under Misc</i></b></td></tr>\n";
	}
?>
</table>
<?php } ?>

</center>
<textarea name="selected_codes_json" id="selected_codes_json" style="display: none;"><?php echo $selected_codes_json; ?></textarea>
</form>
</body>
<script src="<?php echo INC_DIR_JS; ?>wmtpopup.js" type="text/javascript"></script>
<script type="text/javascript">

$(document).ready(function(){

	$('.itemSelect').click(function(){
		var selectedData = $('#selected_codes_json').val();

		var selectedItems = {};
		if(selectedData != "") {
			selectedItems = jQuery.parseJSON(selectedData);
		}

		var codetype = $(this).data('codetype');
		var itercode = $(this).data('itercode');
		var title = $(this).data('title');
		var eleId = $(this).attr('id');

		if($(this).prop('checked')==true) {
			selectedItems[eleId] = {'codetype' : codetype , 'itercode' : itercode, 'title' : title};
		} else if($(this).prop('checked')==false){
			delete selectedItems[eleId];
		}

		$('#selected_codes_json').val(JSON.stringify(selectedItems));
		generateList();
	});

	$('.select_item_list_table').on("click", ".removeSelectedItem", function (e) {
		var selectedData = $('#selected_codes_json').val();

		var selectedItems = {};
		if(selectedData != "") {
			selectedItems = jQuery.parseJSON(selectedData);
		}

		var fileid = $(this).data('fileid');
		delete selectedItems[fileid];

		$('#selected_codes_json').val(JSON.stringify(selectedItems));
		generateList();
	});

	generateList();
});

function generateList() {
	var selectedData = $('#selected_codes_json').val();
	var output = [];

	var selectedItems = {};
	if(selectedData != "") {
		selectedItems = jQuery.parseJSON(selectedData);
	}

	$('.itemSelect').prop('checked', false);
	$.each(selectedItems, function(index, item) {
		var ele = $('input[name="'+index+'"]');
		if(ele.length > 0) {
			ele.prop('checked', true);
		}

		var removeLink = "<a class=\"removeSelectedItem\" href=\"javascript:void(0)\" data-fileid=\"" + index + "\">Remove</a>";
	    output.push('<li><a href="javascript:void(0)">'+item['itercode']+' - '+item['title']+'</a>&nbsp; &nbsp; - ', removeLink, '</li> ');
	});

	if(output.length > 0) {
		$('.select_item_list_table > .sTitle').show();
	} else {
		$('.select_item_list_table > .sTitle').hide();
	}
	$('.select_item_list_table > ul > li').remove();
	$('.select_item_list_table > ul').append(output.join(""));
}

function addslashes(string) {
    return string.replace(/\\/g, '\\\\').
        replace(/\u0008/g, '\\b').
        replace(/\t/g, '\\t').
        replace(/\n/g, '\\n').
        replace(/\f/g, '\\f').
        replace(/\r/g, '\\r').
        replace(/'/g, '\\\'').
        replace(/"/g, '\\"');
}

function handleSelectItem() {
	//var rows_selected = $('.itemSelect:checkbox:checked');
	//var selectedItem = {};

	// jQuery.each(rows_selected, function(index, rowId){
	// 	var codetype = $(rowId).data('codetype');
	// 	var itercode = $(rowId).data('itercode');
	// 	var title = $(rowId).data('title');
	// 	var eleId = $(rowId).attr('id');

	// 	selectedItem[eleId] = {'codetype' : codetype , 'itercode' : itercode, 'title' : title};
	// });

	var selectedData = $('#selected_codes_json').val();

	var selectedItems = {};
	if(selectedData != "") {
		selectedItems = jQuery.parseJSON(selectedData);
	}

	selcodes(selectedItems);
}

function selcodes(selectedItem) {
  if (opener.closed || ! opener.set_selected_diag) {
   alert('The destination form was closed; I cannot act on your selection.');
  } else {
   opener.set_selected_diag(selectedItem);
  window.close();
  return false;
 }
}

function UpdateSubmitAction(box) {
	document.forms[0].action = '<?php echo $base_action; ?>';
	if(box.checked) document.forms[0].action = '<?php echo $base_action; ?>'+
				'&show_xref=true';
}

function RemoveFavorite(fav_id) {
	if(!confirm("Do you want remove?")) {
		return false;
	}

	$.ajax({
		type: "POST",
		url: "<?php echo AJAX_DIR_JS; ?>/diag_favorites.ajax.php",
		datatype: "html",
		data: {
			ajax_action: "remove_favorite",
			fav_id: fav_id
		},
		success: function(result) {
			if(result['error']) {
				alert('There was a problem deleting the favorite\n'+result['error']);
			} else {
				alert("Removed");
				closeRefresh();
			}
		},
		async: false
	});
}

function Addeditalias(dx_id, code_type, alias_id = '') {
	var linkref = '<?php echo $GLOBALS['webroot']; ?>/custom/add_alias_entry_popup.php?dx_id='+dx_id+'&alias_id='+alias_id+'&code_type='+code_type;

	wmtOpen(linkref, '_blank', 500, 350);
}

function closeRefresh() {
	location.reload();
}
</script>
</html>