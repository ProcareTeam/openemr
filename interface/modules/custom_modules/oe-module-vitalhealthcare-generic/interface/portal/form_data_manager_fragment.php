<?php

require_once(dirname(__FILE__, 7) . "/interface/globals.php");
require_once('./form_data_manager_columns.php');

use OpenEMR\Common\Csrf\CsrfUtils;
use OpenEMR\Core\Header;
use Vitalhealthcare\OpenEMR\Modules\Generic\Controller\FormController;
use OpenEMR\Common\Acl\AclMain;

// Check authorization
if (!AclMain::aclCheckCore('patients', 'notes', '', array ('write', 'addonly'))) {
	die (htmlspecialchars(xl('You are not authorized to access this information'), ENT_NOQUOTES));
}

?>

<style type="text/css">
	table.table.msg-table td.no-padding {
		padding: 0px !important;
	}

	.row-details-table.table tr:first-child td{
		border-top: 1px solid #fff!important;
	}

	.forms-tab-container {
		display: grid;
    	grid-template-rows: 1fr auto;
	}
</style>

<div class="clearfix pt-2">
	<ul class="tabNav">
		<li class="current"><a id="header_tab_pending" href="#"><?php echo htmlspecialchars(xl('Pending Items'),ENT_NOQUOTES); ?></a></li>
		<li><a id="header_tab_received" href="#"><?php echo htmlspecialchars(xl('Received Items'),ENT_NOQUOTES); ?></a></li>
		<li><a id="header_tab_archived" href="#"><?php echo htmlspecialchars(xl('Archived Items'),ENT_NOQUOTES); ?></a></li>
		
	</ul>

	<div class='tabContainer'>
		<!-- PENDING ITEMS -->
		<div id='pending' class="tab current mb-0 px-0">
			<div class="forms-tab-container">
				<table id="pending_table" class="text table table-sm msg-table tableRowHighLight" style="width:100%">
					<thead>
						<tr>
							<?php
						      	foreach ($pendingColumnList as $clk => $cItem) {
						      		if($cItem["name"] == "dt_control") {
						      		?> <th><div class="dt-control text"></div></th> <?php
						      		} else {
						      		?> <th><?php echo $cItem["title"] ?></th> <?php
						      		}
						      	}
						     ?>
						</tr>
					</thead>
				</table>
			</div>
		</div>

		<!-- RECEIVED ITEMS -->
		<div id='received' class="tab mb-0 px-0">
			<div class="forms-tab-container">
				<table id="received_table" class="text table table-sm msg-table tableRowHighLight" style="width:100%">
					<thead>
						<tr>
							<?php
						      	foreach ($receivedColumnList as $clk => $cItem) {
						      		if($cItem["name"] == "dt_control") {
						      		?> <th><div class="dt-control text"></div></th> <?php
						      		} else {
						      		?> <th><?php echo $cItem["title"] ?></th> <?php
						      		}
						      	}
						     ?>
						</tr>
					</thead>
				</table>
			</div>
		</div>

		<!-- ARCHIVED ITEMS -->
		<div id='reviewed' class="tab mb-0 px-0">
			<div class="forms-tab-container">
				<table id="reviewed_table" class="text table table-sm msg-table tableRowHighLight" style="width:100%">
					<thead>
						<tr>
							<?php
						      	foreach ($reviewedColumnList as $clk => $cItem) {
						      		if($cItem["name"] == "dt_control") {
						      		?> <th><div class="dt-control text"></div></th> <?php
						      		} else {
						      		?> <th><?php echo $cItem["title"] ?></th> <?php
						      		}
						      	}
						     ?>
						</tr>
					</thead>
				</table>
			</div>
		</div>

		<div>
			<button type="button" class="btn btn-sm btn-primary" onclick="sendFormToken('<?php echo $pid; ?>')">
				<i class="fa fa-plus" aria-hidden="true"></i> <?php echo xlt('Send Form'); ?>
			</button>
		</div>
	</div>
</div>