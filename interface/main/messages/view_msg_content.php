
<?php

require_once("../../globals.php");
include_once("$srcdir/OemrAD/oemrad.globals.php");

$msg_id = isset($_GET['msg_id']) ? $_GET['msg_id'] : '';
//$msg_version = isset($_GET['msg_version']) ? $_GET['msg_version'] : 'TEXT/HTML';
$msg_version = isset($_GET['msg_version']) ? $_GET['msg_version'] : 'TEXT/PLAIN';

use OpenEMR\Core\Header;
use OpenEMR\OemrAd\MessagesLib;
use OpenEMR\OemrAd\Attachment;

$msgData = MessagesLib::fetchMsgById($msg_id);

$msgRawData = isset($msgData['raw_data']) ? $msgData['raw_data'] : "";
$mailObj = MessagesLib::isJson($msgRawData) ? json_decode($msgRawData) : array();
$mailObj = isset($mailObj->mail) ? $mailObj->mail : array();

?>
<!DOCTYPE html>
<html>
<head>
	<meta charset="utf-8">
	<title>Message</title>

	<?php Header::setupHeader(['opener', 'dialog', 'jquery', 'jquery-ui', 'jquery-ui-base', 'fontawesome', 'main-theme', 'oemr_ad']); ?>
</head>
<body>
	<?php
		$btnStr = "";
		$mContents = array();
		if(isset($mailObj->content) && is_array($mailObj->content)) {
			foreach ($mailObj->content as $mk => $mItem) {
				if($mItem->type == "content") {
					$mContents[$mItem->mime] = isset($mItem->data) ? $mItem->data : '';
					$btnClass = ($msg_version == $mItem->mime) ? 'btn-primary' : 'btn-secondary';
					$btnStr .= "<button type='submit' class='btn ".$btnClass."' name='msg_version' value='".$mItem->mime."'>".$mItem->mime."</button>";
				}
			}
		}

		if(!empty($btnStr)) {
			?>
			<form name='theform' id='theform' action='view_msg_content.php'>
				<input type="hidden" name="msg_id" value="<?php echo $msg_id ?>">
				<div class="btn-group">
					<?php echo $btnStr; ?>
				</div>
			</form>
			<?php
		}

		if(isset($mContents[$msg_version])) {
			$formatedMessage = MessagesLib::displayIframeMsg($mContents[$msg_version], 'text', $msg_version);
			$formatedMessage = MessagesLib::replaceMessageContent($formatedMessage, $msg_id);

			?>
			<div class="mt-3">
				<iframe scrolling="no" id="msgContent" data-id="<?php echo $msg_id; ?>" class="contentiFrame" srcdoc="<?php echo htmlentities($formatedMessage) ?>"></iframe>
				<script>$(document).ready(function(){$("#msgContent").iframereadmoretext({'fullcontent' : true});});</script>
				<?php echo MessagesLib::displayAttachment($msgData['type'], $msgData['id'], $msgData); ?>
			</div>
			<?php
		} else {
			?>
			<div class="mt-3"><h2>No message content</h2></div>
			<?php
		}
	?>
</body>
</html>


