<?php
$_SERVER['REQUEST_URI']=$_SERVER['PHP_SELF'];
$_SERVER['SERVER_NAME']='localhost';
$_SERVER['HTTP_HOST'] = 'localhost';
$_SESSION['site'] = 'default';
$backpic = "";
$ignoreAuth=1;
// ini_set('display_errors', 1);
// ini_set('display_startup_errors', 1);
// error_reporting(E_ALL);

@require_once(dirname( __FILE__, 2 ) . "/interface/globals.php");
@require_once("$srcdir/OemrAD/oemrad.globals.php");

use OpenEMR\OemrAd\EmailReader;
use OpenEMR\OemrAd\EmailMessage;

function isCommandLineInterface(){
    return (php_sapi_name() === 'cli');
}

function geMessagesContent($mail) {
	$content = "";
	if(!empty($mail)) {
		if(isset($mail->content) && is_array($mail->content)) {
			$i=0;
			$typeList = array("TEXT/PLAIN", "TEXT/HTML");
			
			while (($i < 2) && empty($content)) {
				foreach ($mail->content as $k => $mailItem) {
					if($mailItem->mime == $typeList[$i] && $mailItem->type == "content") {
						if(isset($mailItem->data) && !empty($mailItem->data)) {
							$content = $mailItem->data;
						}
					}
				}
				$i++;
			}
		}
	}

	//$content = substr($content, 0, -500);
	//print_r($content);
	return $content;
	//return htmlentities($content,ENT_QUOTES,'utf-8');

	//return htmlentities("that means's ddf");
	//return htmlentities($content);
}

?>
<?php if(isCommandLineInterface() === false) { ?>
<html>
<head>
	<title>Conrjob - Email</title>
</head>
<body>
<?php } ?>
<?php
$connection = EmailMessage::getImapConnection();
//$emailData   = imap_search($connection, 'SUBJECT "Volk out 02/13/23-02/23/23; Please Read Re: Kelly Grace Reeder, DOB 1997-09-05 referral (76388)"');
//$emailData   = imap_search($connection, 'SUBJECT "Kelly Grace Reeder, DOB 1997-09-05 referral (76388)"');
$emailData   = imap_search($connection, 'SUBJECT "Re: Kevin Donegan 77184 - Records"');

print_r($emailData);

if (!empty($emailData)) {
	foreach ($emailData as $emailIndex => $emailIdent) {
		/* get information specific to this email */
		$overview = imap_fetch_overview($connection, $emailIdent, 0);
		$udate = isset($overview) ? $overview[0]->udate : '';
		$subject = isset($overview) ? $overview[0]->subject : '';
		$timestamp = date("Y-m-d H:i:s", $udate);
		
		$header = imap_headerinfo($connection, $emailIdent);
		$structure = imap_fetchstructure($connection, $emailIdent);
		
		$mail = EmailReader::_fetchHeader($header, $emailIdent);
		$mail = EmailReader::_fetch($connection, $emailIdent, $mail, $structure);
		//$message = EmailReader::geMessagesContent($mail);
		$toaddr = $header->to[0]->mailbox . "@" . $header->to[0]->host;
		$fromaddr = $header->from[0]->mailbox . "@" . $header->from[0]->host;
		$fromPerson = isset($header->fromaddress) && $header->fromaddress != $fromaddr ? strip_tags($header->fromaddress) : "";
		$email_subject = isset($header->Subject) ? strip_tags($header->Subject) : "";
		$formattedfromaddr = str_replace( array( '\''), '', $fromaddr);
		$pids = EmailMessage::getPatientDataByEmail($formattedfromaddr);
		//$message = EmailMessage::getEmailMessage($connection, $emailIdent, $structure);
		//$message = EmailReader::geMessagesContent($mail);
		$message = geMessagesContent($mail);

		

		// echo "\n 1. ********************************************\n";
		// echo '<pre>';
		// print_r($mail);
		// echo '</pre>';
		// echo "\n********************************************\n";

		// echo "\n 2. ********************************************\n";
		// echo '<pre>';
		// print_r($message);
		// echo '</pre>';
		// echo "\n********************************************\n";

		$obj_section = $structure;
		if ($obj_section->encoding == 3) {
		    $message = imap_base64($message);
		} else if ($obj_section->encoding == 4) {
		    $message = imap_qprint($message);
		} else {
			$message = imap_utf8($message);
		}
		
		echo "\n 3. ********************************************\n";
		echo '<pre>';
		print_r($message);
		echo '</pre>';
		echo "\n********************************************\n";
		
		//$message = imap_fetchbody($connection, $emailIdent, "1");
		//$message = quoted_printable_decode(imap_fetchbody($connection,$emailIdent,1));
		//$message = EmailMessage::getEmailMessage($connection, $emailIdent, $structure);
		//$gf  = create_part_array($structure);
		//print_r($message);
	}
}

/*Fetch Incoming Email*/
//$responce = EmailMessage::fetchNewIncomingEmail();
?>
<?php if(isCommandLineInterface() === false) { ?>
</body>
</html>
<?php
}

