<?php

function sendReminderEmail($reminder, $USERS_DATABASE, $HOST) {

	if (! $USERS_DATABASE) $USERS_DATABASE = USERS_DATABASE;
	if ($HOST === NULL) $HOST = HOST;

	$recipients = array();
	if (@$reminder['rem_email']) {
		array_push($recipients, array(
			"email" => $reminder['rem_email'],
			"e"		=> $reminder['rem_email'],
			"u"		=> null));
	}
	else if (@$reminder['rem_usr_id']) {
		$res = mysql_query('select ugr_FirstName,ugr_LastName,ugr_eMail from '.$USERS_DATABASE.'.sysUGrps usr where Id = '.$reminder['rem_usr_id']);
		$row = mysql_fetch_assoc($res);
		if ($row) {
			array_push($recipients, array(
				"email" => $row['ugr_FirstName'].' '.$row['ugr_LastName'].' <'.$row['ugr_eMail'].'>',
				"e"		=> null,
				"u"		=> $reminder['rem_usr_id']));
		}
	}
	else if (@$reminder['rem_cgr_id']) {
		$res = @$reminder['rem_id'] 
				? mysql_query('select ugr_FirstName,ugr_LastName,ugr_eMail,Id
							   from coll_group_links left join '.$USERS_DATABASE.'.sysUGrps usr on cgl_usr_id=Id
							   left join reminders_blacklist on rbl_user_id=Id and rbl_rem_id = '.$reminder['rem_id'].'
							   where cgl_cgr_id = '.$reminder['rem_cgr_id'].' and isnull(rbl_id)')
				: mysql_query('select ugr_FirstName,ugr_LastName,ugr_eMail,Id
							   from coll_group_links left join '.$USERS_DATABASE.'.sysUGrps usr on cgl_usr_id=Id
							   where cgl_cgr_id = '.$reminder['rem_cgr_id']);
			
		while ($row = mysql_fetch_assoc($res)) {
			array_push($recipients, array(
				"email" => $row['ugr_FirstName'].' '.$row['ugr_LastName'].' <'.$row['ugr_eMail'].'>',
				"e"		=> null,
				"u"		=> $row['Id']));
		}
	}
	else if (@$reminder['rem_wg_id']) {
		$res = @$reminder['rem_id']
				? mysql_query('select ugr_FirstName,ugr_LastName,ugr_eMail,Id
							   from '.$USERS_DATABASE.'.sysUsrGrpLinks left join '.$USERS_DATABASE.'.sysUGrps usr on ugl_UserID=Id
							   left join reminders_blacklist on rbl_user_id=Id and rbl_rem_id = '.$reminder['rem_id'].'
							   where ugl_GroupID = '.$reminder['rem_wg_id'].' and isnull(rbl_id)')
				: mysql_query('select ugr_FirstName,ugr_LastName,ugr_eMail,Id
							   from '.$USERS_DATABASE.'.sysUsrGrpLinks left join '.$USERS_DATABASE.'.sysUGrps usr on ugl_UserID=Id
							   where ugl_GroupID = '.$reminder['rem_wg_id']);
		while ($row = mysql_fetch_assoc($res))
			array_push($recipients, array(
				"email" => $row['ugr_FirstName'].' '.$row['ugr_LastName'].' <'.$row['ugr_eMail'].'>',
				"e"		=> null,
				"u"		=> $row['Id']));
	}

	$email_headers = 'From: Heurist reminder service <no-reply@'.$HOST.'>';

	$res = mysql_query('select ugr_FirstName,ugr_LastName,ugr_eMail from '.$USERS_DATABASE.'.sysUGrps usr where Id = '.$reminder['rem_owner_id']);
	$owner = mysql_fetch_assoc($res);
	if ($owner) {
		if (@$reminder['rem_email']  || (@$reminder['rem_user_id']  &&  @$reminder['rem_usr_id'] != @$reminder['rem_owner_id']))
			$email_headers .= "\r\nCc: ".$owner['ugr_FirstName'].' '.$owner['ugr_LastName'].' <'.$owner['ugr_eMail'].'>';
		$email_headers .= "\r\nReply-To: ".$owner['ugr_FirstName'].' '.$owner['ugr_LastName'].' <'.$owner['ugr_eMail'].'>';
	}

	$res = mysql_query('select rec_title, rec_wg_id, rec_visibility, grp.ugr_Name from records left join '.$USERS_DATABASE.'.sysUGrps grp on grp.ugr_ID=rec_wg_id and grp.ugr_Type != "User" where rec_id = '.$reminder['rem_rec_id']);
	$bib = mysql_fetch_assoc($res);

	$email_subject = '[Heurist] "'.$bib['rec_title'].'"';

	if (@$reminder['rem_usr_id'] != @$reminder['rem_owner_id'])
		$email_subject .= ' from ' . $owner['ugr_FirstName'].' '.$owner['ugr_LastName'];

	foreach($recipients as $recipient) {
		$email_text = 'Reminder From: ' . ($reminder['rem_usr_id'] == $reminder['rem_owner_id'] ? 'you'
										   : $owner['ugr_FirstName'].' '.$owner['ugr_LastName'].' <'.$owner['ugr_eMail'].'>') . "\n\n"
					. 'For: "'.$bib['rec_title'].'"' . "\n\n"
					. 'URL: http://'.$HOST.'/heurist/?w=all&q=ids:'.$reminder['rem_rec_id'] . "\n\n";
					
		if ($bib['rec_wg_id'] && $bib['rec_visibility'] == 'Hidden') {
			$email_text .= "Note: Resource belongs to workgroup ".$bib['ugr_Name'] . "\n"
         				.	"You must be logged in to Heurist and a member of this workgroup to view it". "\n\n";
		}
		
		$email_text .= 'Message: '.$reminder['rem_message'] . "\n\n";
		
		if (@$reminder['rem_id']  &&  $reminder['rem_freq'] != "once") {
			$email_text .= "-------------------------------------------\n\n"
						.  "You will receive this reminder " . $reminder['rem_freq'] . "\n"
						.  "Click below if you do not wish to receive this reminder again:\n\n"
						.  "http://".$HOST."/heurist/php/delete-reminder.php?r=".$reminder['rem_id']
						.  ($recipient['u'] ? "&u=".$recipient['u'] : "&e=".$recipient['e']) . "&h=".$reminder['rem_nonce'] . "\n\n";
		} else {
			$email_text .= "-------------------------------------------\n\n"
						.  "You will not receive this reminder again.\n\n";
		}
		$email_text .= "-------------------------------------------\n"
					.  "This email was generated by Heurist:\n"
					.  "http://".$HOST."\n";

		mail($recipient['email'], $email_subject, $email_text, $email_headers);
	}

	return true;
}

?>
