<?php
//script to add, update and delete events in cases
session_start();
require('../auth/session_check.php');
require('../../../db.php');
require('../users/user_data.php');
require('../utilities/format_text.php');


function generate_recipients($dbh,$thread_id) //Get list of recipients for a reply
{

	$q = $dbh->prepare("SELECT * FROM `cm_messages` WHERE `id` = $thread_id");

	$q->execute();

	$parties = $q->fetch(PDO::FETCH_ASSOC);

	$data = array('tos' => $parties['to'], 'ccs' => $parties['ccs'], 'from' => $parties['from']);

	return $data;

}

function get_subject($dbh,$msg_id) //Get subject for inclusion in notification email
{
	$q = $dbh->prepare("SELECT id, subject FROM cm_messages WHERE id = ?");

	$q->bindParam(1, $msg_id);

	$q->execute();

	$data = $q->fetch(PDO::FETCH_ASSOC);

	return $data['subject'];

}


//Get variables

$action = $_POST['action'];

$user = $_SESSION['login'];

if (isset($_POST['id']))
	{$id = $_POST['id'];}

if (isset($_POST['thread_id']))
	{$thread_id = $_POST['thread_id'];}

if (isset($_POST['reply_text']))
	{$reply_text = $_POST['reply_text'];}


switch ($action) {

	case 'send':


	break;

	case 'reply':

		$q = $dbh->prepare("INSERT INTO  `cm_messages` (`id` ,`thread_id` ,`to` ,`from` ,`ccs` ,`subject` ,`body` ,`assoc_case` ,`time_sent` ,`read` ,`archive` ,`starred`)
			VALUES (NULL ,  :thread_id,  :to,  :sender, :ccs,  '',:reply_text,  '', CURRENT_TIMESTAMP ,  '',  '',  '');");

		$tos = generate_recipients($dbh,$thread_id);

		$to = $tos['from'] . ',' . $tos['tos'];

		$cc = $tos['ccs'];

		$data = array('thread_id' => $thread_id, 'to' => $to,'ccs' => $cc, 'sender' => $user,'reply_text' => $reply_text);

		$q->execute($data);

		$error = $q->errorInfo();

		//Remove any usernames from read and archive fields; this will show that there is a new reply
		//to the message in the inbox
		if (!$error[1])
			{
				$q = $dbh->prepare("UPDATE cm_messages SET `archive` = '',`read` = '' WHERE `id` = '$thread_id'");

				//next send an email notifying user of the new reply
				$recipients_to = explode(',', $to);
				if (!empty($cc))
				{
					$recipients_cc = explode(',', $cc);
					$email_to = array_merge($recipients_to,$recipients_cc);
				}
				else
				{
					$email_to = $recipients_to;
				}

				$msg_subject = get_subject($dbh,$thread_id);
				$preview = snippet(20,$reply_text);

				foreach ($email_to as $r) {
					$email = user_email($dbh,$r);
					$subject = "ClincCases: Reply to '" . $msg_subject . "'";
					$body = "$user has replied to '" . $msg_subject ."':\n\n'$preview'\n\n" . CC_EMAIL_FOOTER;
					mail($email,$subject,$body,CC_EMAIL_HEADERS);
					//TODO test on mail server
				}
			}


	break;

	case 'forward':

	break;

	case 'star_on':  //add start to message

		$q = $dbh->prepare("UPDATE cm_messages SET starred = CONCAT(starred,:user) WHERE id = :id");

		$user_string = $user . ",";

		$data = array('user' => $user_string,'id' => $id);

		$q->execute($data);

		$error = $q->errorInfo();

		break;

	case 'star_off':  //remove star from message

		$q = $dbh->prepare("UPDATE cm_messages SET starred = REPLACE(starred,:user,'') WHERE id = :id");

		$user_string = $user . ",";

		$data = array('user' => $user_string,'id' => $id);

		$q->execute($data);

		$error = $q->errorInfo();


		break;

	case 'mark_read':

		$q = $dbh->prepare("UPDATE cm_messages SET `read` = CONCAT(`read`,:user) WHERE id = :id");

		$user_string = $user . ",";

		$data = array('user' => $user_string,'id' => $id);

		$q->execute($data);

		$error = $q->errorInfo();

		break;

	case 'mark_unread':

		$q = $dbh->prepare("UPDATE cm_messages SET read = REPLACE(read,:user,'') WHERE id = :id");

		$user_string = $user . ",";

		$data = array('user' => $user_string,'id' => $id);

		$q->execute($data);

		$error = $q->errorInfo();

		break;

	case 'archive':

		$q = $dbh->prepare("UPDATE cm_messages SET archive = CONCAT(archive,:user) WHERE id = :id");

		$user_string = $user . ",";

		$data = array('user' => $user_string,'id' => $id);

		$q->execute($data);

		$error = $q->errorInfo();

		break;

	case 'unarchive':

		$q = $dbh->prepare("UPDATE cm_messages SET archive = REPLACE(archive,:user,'') WHERE id = :id");

		$user_string = $user . ",";

		$data = array('user' => $user_string,'id' => $id);

		$q->execute($data);

		$error = $q->errorInfo();

		break;
};

if($error[1])

		{
			$return = array('message' => 'Sorry, there was an error. Please try again.','error' => true);
			echo json_encode($return);
		}

		else
		{

			switch($action){

			case "send":
			$return = array('message'=>'Message sent.');
			echo json_encode($return);
			break;

			case "reply":
			$return = array('message'=>'Reply sent.');
			echo json_encode($return);
			break;

			case "forward":
			$return = array('message'=>'Message forwarded.');
			echo json_encode($return);
			break;

			case "archive":
			$return = array('message'=>'Message archived.');
			echo json_encode($return);
			break;

			case "unarchive":
			$return = array('message'=>'Message returned to Inbox.');
			echo json_encode($return);
			break;

			case "star_on":
			$return = array('message'=>'OK');
			echo json_encode($return);
			break;

			case "star_off":
			$return = array('message'=>'OK');
			echo json_encode($return);
			break;

			case "mark_read":
			$return = array('message'=>'OK');
			echo json_encode($return);
			break;

			case "mark_unread":
			$return = array('message'=>'Message marked unread.');
			echo json_encode($return);
			break;

			}

		}