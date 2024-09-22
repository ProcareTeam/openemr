<?php

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

$encounterId = isset($_REQUEST['encounter']) ? $_REQUEST['encounter'] : "";
$base = isset($_REQUEST['base']) ? $_REQUEST['base'] : "";
$wrap = isset($_REQUEST['wrap']) ? $_REQUEST['wrap'] : "";
$formID = isset($_REQUEST['formID']) ? $_REQUEST['formID'] : "";
$mode = isset($_REQUEST['mode']) ? $_REQUEST['mode'] : "";

$pid = isset($_SESSION['pid']) ? $_SESSION['pid'] : "";

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
</head>
<body>

<div class="container">
	<textarea name="selected_codes_json" id="selected_codes_json" style="display: none;"><?php echo $selected_codes_json; ?></textarea>

	<table border='0'>
	<?php
		$diag=GetProblemsWithDiags($pid,"current",$encounterId);
		if(count($diag) > 0) {
	?>
	 <tr>
	  <td><?php echo '<input type="checkbox" class="itemSelectAll" >'; ?></td>
	  <td colspan="2"><b><?php xl ('Code','e'); ?></b></td>
		<td>&nbsp;</td>
	  <td><b><?php xl ('Description','e'); ?></b></td>
	 </tr>
	<?php
			$prev_diagnosis = array();
			foreach($diag as $xrow) {
				if($xrow['enddate']) continue;

				if(in_array($xrow['diagnosis'], $prev_diagnosis)) {
					//continue;
				}

	 			$xrow['code'] = $xrow['diagnosis'];
	 			$prev_diagnosis[] = $xrow['diagnosis'];
	 			$xcode = htmlspecialchars($xrow['code'],ENT_QUOTES);
	 			$xtext = htmlspecialchars(ucfirst(trim($xrow['title'])),ENT_QUOTES);
				$title = $xtext;
	 			$anchor = "<a href='' title='$title'  onclick='return ".
						"selcode(\"$form_code_type\", \"$xcode\", \"\", \"$title\")'>";
				echo "<tr>\n";
	    		echo '<td>';
		    		echo '<input type="checkbox" class="itemSelect" id="check_'.$xrow['id'].'" name="check_'.$xrow['id'].'" value="1" data-codeid="'.$xrow['id'].'" data-codetype="'.$form_code_type.'" data-itercode="'.$xrow['id'].'" data-title="'.$title.'" >';
		    		echo '</td>';
				echo "<td style='text-align: left;'>&#9733;</td>\n";
				echo "<td style='text-align: left;'>$anchor$xcode</a></td>\n";
				echo "<td>&nbsp;</td>\n";
				echo "<td colspan='2'>$anchor$xtext</a></td>\n";
				echo "</tr>\n";
			}
		} else {
			echo "<tr><td>&nbsp;</td></tr>\n";
			echo "<tr><td colspan='5' style='text-align: center;'><b><i>Not found</i></b></td></tr>\n";
		}
	?>
	</table>
	<div class="mb-4 mt-4">
		<input type='button' class='btn btn-primary' name='bn_select' value='<?php xl('Submit','e'); ?>' onClick="handleSelectItem()" />
	</div>
</div>

</body>
<script src="<?php echo INC_DIR_JS; ?>wmtpopup.js" type="text/javascript"></script>	

<script type="text/javascript">

$(document).ready(function(){

	$('.itemSelect').change(function(){
		var selectedData = $('#selected_codes_json').val();

		var selectedItems = {};
		if(selectedData != "") {
			selectedItems = jQuery.parseJSON(selectedData);
		}

		var codetype = $(this).data('codetype');
		var code_id = $(this).data('codeid');
		var itercode = $(this).data('itercode');
		var title = $(this).data('title');
		var eleId = $(this).attr('id');

		if($(this).prop('checked')==true) {
			selectedItems["c_"+code_id] = {'code_id' : code_id, 'codetype' : codetype , 'itercode' : itercode, 'title' : title};
		} else if($(this).prop('checked')==false){
			delete selectedItems["c_"+code_id];
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

	$('.itemSelectAll').change(function(){
		if($(this).prop('checked')==true) {
			$( ".itemSelect" ).each(function( index ) {
				$(this).prop('checked', true);
				$(this).trigger('change');
			});
		} else if($(this).prop('checked')==false) {
			$( ".itemSelect" ).each(function( index ) {
				$(this).prop('checked', false);
				$(this).trigger('change');
			});
		}
	});

	generateList(true);
});

function generateList(init = false) {
	var selectedData = $('#selected_codes_json').val();
	var output = [];

	var selectedItems = {};
	if(selectedData != "") {
		selectedItems = jQuery.parseJSON(selectedData);
	}

	//$('.itemSelect').prop('checked', false);
	if(init === true) {
		$(".itemSelectAll").prop('checked', true);
		$(".itemSelectAll").trigger('change');
	}


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

function handleSelectItem() {
	var selectedData = $('#selected_codes_json').val();

	var selectedItems = {};
	if(selectedData != "") {
		selectedItems = jQuery.parseJSON(selectedData);
	}
	
	selcodes(selectedItems);
}

function selcodes(selectedItem) {
  if (opener.closed || ! opener.set_selected_current_diag) {
   	alert('The destination form was closed; I cannot act on your selection.');
  } else {
   	opener.set_selected_current_diag(selectedItem, '<?php echo $base; ?>', '<?php echo $wrap; ?>', '<?php echo $formID; ?>','<?php echo $mode; ?>');
  	window.close();
  return false;
 }
}

</script>


</body>
</html>