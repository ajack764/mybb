<?php
/**
 * MyBB 1.0
 * Copyright � 2005 MyBulletinBoard Group, All Rights Reserved
 *
 * Website: http://www.mybboard.com
 * License: http://www.mybboard.com/eula.html
 *
 * $Id$
 */

require "./global.php";

// Load language packs for this section
global $lang;
$lang->load("users");

addacpnav($lang->nav_users, "users.php");
switch($mybb->input['action'])
{
	case "add":
		addacpnav($lang->nav_add_user);
		break;
	case "edit":
		addacpnav($lang->nav_edit_user);
		break;
	case "delete":
		addacpnav($lang->nav_delete_user);
		break;
	case "merge":
	case "do_merge":
		addacpnav($lang->nav_merge_accounts);
		break;
	case "email":
		addacpnav($lang->nav_email);
		break;
	case "find":
		addacpnav($lang->nav_find);
		break;
	case "banned":
		addacpnav($lang->nav_banned);
		break;
	case "manageban":
		if($uid)
		{
			addacpnav($lang->nav_edit_ban);
		}
		else
		{
			addacpnav($lang->nav_add_ban);
		}
		break;
}


function date2timestamp($date)
{
	$d = explode("-", $date);
	$nowdate = date("H-j-n-Y");
	$n = explode("-", $nowdate);
	if($n[0] >= 12)
	{
		$n[1] += 1;
	}
	$n[1] += $d[0];
	$n[2] += $d[1];
	$n[3] += $d[2];
	return mktime(0, 0, 0, $n[2], $n[1], $n[3]);
}

function getbanremaining($lifted)
{
	global $lang;
	$remain = $lifted-time();
	$years = intval($remain/31536000);
	$months = intval($remain/2592000);
	$weeks = intval($remain/604800);
	$days = intval($remain/86400);
	$hours = intval($remain/3600);
	if($years > 1)
	{
		$r = "$years $lang->years";
	}
	elseif($years == 1)
	{
		$r = "1 $lang->year";
	}
	elseif($months > 1)
	{
		$r = "$months $lang->months";
	}
	elseif($months == 1)
	{
		$r = "1 $lang->month";
	}
	elseif($weeks > 1)
	{
		$r = "<span class=\"highlight3\">$weeks $lang->weeks</span>";
	}
	elseif($weeks == 1)
	{
		$r = "<span class=\"highlight2\">1 $lang->week</span>";
	}
	elseif($days > 1)
	{
		$r = "<span class=\"highlight2\">$days $lang->days</span>";
	}
	elseif($days == 1)
	{
		$r = "<span class=\"highlight1\">1 $lang->day</span>";
	}
	elseif($days < 1)
	{
		$r = "<span class=\"highlight1\">$hours $lang->hours</span>";
	}
	return $r;
}

function checkbanned()
{
	global $db;
	$time = time();
	$query = $db->query("SELECT * FROM ".TABLE_PREFIX."banned WHERE lifted<='$time' AND lifted!='perm'");
	while($banned = $db->fetch_array($query))
	{
		$db->query("UPDATE ".TABLE_PREFIX."users SET usergroup='$banned[oldgroup]' WHERE uid='$banned[uid]'");
		$db->query("DELETE FROM ".TABLE_PREFIX."banned WHERE uid='$banned[uid]'");
	}
}

$bantimes["1-0-0"] = "1 $lang->day";
$bantimes["2-0-0"] = "2 $lang->days";
$bantimes["3-0-0"] = "3 $lang->days";
$bantimes["4-0-0"] = "4 $lang->days";
$bantimes["5-0-0"] = "5 $lang->days";
$bantimes["6-0-0"] = "6 $lang->days";
$bantimes["7-0-0"] = "1 $lang->week";
$bantimes["14-0-0"] = "2 $lang->weeks";
$bantimes["21-0-0"] = "3 $lang->weeks";
$bantimes["0-1-0"] = "1 $lang->month";
$bantimes["0-2-0"] = "2 $lang->months";
$bantimes["0-3-0"] = "3 $lang->months";
$bantimes["0-4-0"] = "4 $lang->months";
$bantimes["0-5-0"] = "5 $lang->months";
$bantimes["0-6-0"] = "6 $lang->months";
$bantimes["0-0-1"] = "1 $lang->year";
$bantimes["0-0-2"] = "2 $lang->years";

checkadminpermissions("caneditusers");
logadmin();

if($mybb->input['action'] == "do_add")
{
	if(username_exists($mybb->input['username']))
	{
		cpmessage($lang->error_name_exists);
	}
	if(!$mybb->input['username'] || !$mybb->input['password'] || !$mybb->input['email'])
	{
		cpmessage($lang->missing_fields);
	}
	if($mybb->input['website'] == "http://" || $mybb->input['website'] == "none")
	{
		$mybb->input['website'] = "";
	}
	$md5password = md5($mybb->input['password']);

	//
	// Generate salt, salted password, and login key
	//
	$salt = generate_salt();
	$md5password = salt_password($md5password, $salt);
	$loginkey = generate_loginkey();

	// Determine the usergroup stuff
	if(is_array($mybb->input['additionalgroups']))
	{
		foreach($mybb->input['additionalgroups'] as $gid)
		{
			if($gid == $usergroup)
			{
				unset($mybb->input['additionalgroups'][$gid]);
			}
		}
		$additionalgroups = implode(",", $mybb->input['additionalgroups']);
	}
	else
	{
		$additionalgroups = "";
	}
	$birthday = explode("-", $mybb->input['birthday']);
	if($birthday[0] < 10 && $birthday[0] != "")
	{
		$nbirthday[0] = "0".$birthday[0];
	}
	if($birthday[1] < 10 && $birthday[1] != "")
	{
		$nbirthday[1] = "0".$birthday[1];
	}
	if($nbirthday[0] && $nbirthday[1])
	{
		$mybb->input['birthday'] = $nbirthday[0]."-".$nbirthday[1]."-".$nbirthday[2];
	}

	$timenow = time();
	$user = array(
		"uid" => "NULL",
		"username" => addslashes($mybb->input['username']),
		"password" => $md5password,
		"salt" => $salt,
		"loginkey" => $loginkey,
		"email" => addslashes($mybb->input['email']),
		"usergroup" => intval($mybb->input['usergroup']),
		"usertitle" => addslashes($mybb->input['usertitle']),
		"regdate" => time(),
		"lastactive" => time(),
		"lastvisit" => time(),
		"avatar" => addslashes($mybb->input['avatar']),
		"website" => addslashes($mybb->input['website']),
		"icq" => addslashes($mybb->input['icq']),
		"aim" => addslashes($mybb->input['aim']),
		"yahoo" => addslashes($mybb->input['yahoo']),
		"msn" => addslashes($mybb->input['msn']),
		"birthday" => addslashes($mybb->input['birthday']),
		"allownotices" => $mybb->input['allownotices'],
		"hideemail" => $mybb->input['hideemail'],
		"emailnotify" => $mybb->input['emailnotify'],
		"invisible" => $mybb->input['invisible'],
		"style" => $mybb->input['style'],
		"timezone" => $mybb->input['timezoneoffset'],
		"receivepms" => $mybb->input['receivepms'],
		"pmpopup" => $mybb->input['pmpopup'],
		"pmnotify" => $mybb->input['pmnotify'],
		"signature" => $mybb->input['signature']
		);
	$db->insert_query(TABLE_PREFIX."users", $user);
	$uid = $db->insert_id();

	// Custom profile fields
	$querypart1 = "";
	$querypart2 = "";
	$comma = "";
	$query = $db->query("SELECT * FROM ".TABLE_PREFIX."profilefields WHERE editable='yes' ORDER BY disporder");
	while($profilefield = $db->fetch_array($query))
	{
		$profilefield['type'] = htmlspecialchars_uni($profilefield['type']);
		$thing = explode("\n", $profilefield[type], "2");
		$type = trim($thing[0]);
		$field = "fid$profilefield[fid]";
		$options = "";
		if($type == "multiselect" || $type == "checkbox")
		{
			if(is_array($mybb->input[$field]))
			{
				while(list($key, $val) = each($mybb->input[$field]))
				{
					if($options)
					{
						$options .= "\n";
					}
					$options .= "$val";
				}
			}
		}
		else
		{
			$options = $mybb->input[$field];
		}
		$options = addslashes($options);
		$userfields[$field] = $options;
	}
	$userfields['ufid'] = $uid;
	$db->insert_query(TABLE_PREFIX."userfields", $userfields);
	if($mybb->input['usergroup'] == 5)
	{
		$activationcode = random_str();
		$now = time();
		$activationarray = array(
			"aid" => "NULL",
			"uid" => $uid,
			"dateline" => time(),
			"code" => $activationcode,
			"type" => "r"
		);
		$db->query(TABLE_PREFIX."awaitingactivation", $activationarray);
		$emailsubject = sprintf($lang->emailsubject_activateaccount, $settings['bbname']);
		$emailmessage = sprintf($lang->email_activeateaccount, $username, $settings['bbname'], $settings['bburl'], $uid, $activationcode);
		mymail($email, $emailsubject, $emailmessage);
	}
	$cache->updatestats();
	cpredirect("users.php", $lang->user_added);
}
if($mybb->input['action'] == "do_edit")
{
	$query = $db->query("SELECT * FROM ".TABLE_PREFIX."users WHERE uid='".intval($mybb->input['uid'])."'");
	$user = $db->fetch_array($query);

	$query = $db->query("SELECT username FROM ".TABLE_PREFIX."users WHERE username='".addslashes($mybb->input['username'])."' AND username!='".addslashes($user['username'])."'");
	if($db->fetch_array($query))
	{
		cpmessage($lang->error_name_exists);
	}
	if(!$mybb->input['email'])
	{
		cpmessage($lang->missing_fields);
	}
	if($mybb->input['website'] == "http://" || $mybb->input['website'] == "none")
	{
		$mybb->input['website'] = "";
	}

	if($mybb->input['password'] != "")
	{
		update_password($user['uid'], md5($mybb->input['password']), $user['salt']);
	}
	// Custom profile fields
	$querypart1 = "";
	$querypart2 = "";
	$comma = "";
	$query = $db->query("SELECT * FROM ".TABLE_PREFIX."profilefields WHERE editable='yes' ORDER BY disporder");
	while($profilefield = $db->fetch_array($query))
	{
		$profilefield['type'] = htmlspecialchars_uni($profilefield['type']);
		$thing = explode("\n", $profilefield[type], "2");
		$type = trim($thing[0]);
		$field = "fid$profilefield[fid]";
		$options = "";
		if($type == "multiselect" || $type == "checkbox")
		{
			if(is_array($mybb->input[$field]))
			{
				while(list($key, $val) = each($mybb->input[$field]))
				{
					if($options)
					{
						$options .= "\n";
					}
					$options .= "$val";
				}
			}
		}
		else
		{
			$options = $mybb->input[$field];
		}
		$options = addslashes($options);
		$userfields[$field] = $options;
	}
	$userfields['ufid'] = $uid;
	$db->query("DELETE FROM ".TABLE_PREFIX."userfields WHERE ufid='".intval($mybb->input['uid'])."'");
	$db->insert_query(TABLE_PREFIX."userfields", $userfields);

	// Determine the usergroup stuff
	if(is_array($mybb->input['additionalgroups']))
	{
		foreach($mybb->input['additionalgroups'] as $gid)
		{
			if($gid == $usergroup)
			{
				unset($mybb->input['additionalgroups'][$gid]);
			}
		}
		$additionalgroups = implode(",", $mybb->input['additionalgroups']);
	}
	else
	{
		$additionalgroups = "";
	}

	$birthday = explode("-", $mybb->input['birthday']);
	if($birthday[0] < 10 && $birthday[0] != "")
	{
		$nbirthday[0] = "0".$birthday[0];
	}
	if($birthday[1] < 10 && $birthday[1] != "")
	{
		$nbirthday[1] = "0".$birthday[1];
	}
	if($nbirthday[0] && $nbirthday[1])
	{
		$mybb->input['birthday'] = $nbirthday[0]."-".$nbirthday[1]."-".$nbirthday[2];
	}

	$user = array(
		"username" => addslashes($mybb->input['username']),
		"email" => addslashes($mybb->input['email']),
		"usergroup" => intval($mybb->input['usergroup']),
		"additionalgroups" => $additionalgroups,
		"usertitle" => addslashes($mybb->input['usertitle']),
		"avatar" => addslashes($mybb->input['avatar']),
		"website" => addslashes($mybb->input['website']),
		"icq" => addslashes($mybb->input['icq']),
		"aim" => addslashes($mybb->input['aim']),
		"yahoo" => addslashes($mybb->input['yahoo']),
		"msn" => addslashes($mybb->input['msn']),
		"birthday" => addslashes($mybb->input['birthday']),
		"allownotices" => $mybb->input['allownotices'],
		"hideemail" => $mybb->input['hideemail'],
		"emailnotify" => $mybb->input['emailnotify'],
		"invisible" => $mybb->input['invisible'],
		"style" => $mybb->input['stylesel'],
		"timezone" => $mybb->input['timezoneoffset'],
		"receivepms" => $mybb->input['receivepms'],
		"pmpopup" => $mybb->input['pmpopup'],
		"pmnotify" => $mybb->input['pmnotify'],
		"signature" => $mybb->input['signature'],
		"postnum" => $mybb->input['postnum'],
		);

	$db->update_query(TABLE_PREFIX."users", $user, "uid='".intval($mybb->input['uid'])."'");

	if($mybb->input['username'] != $user['username'])
	{
		$db->query("UPDATE ".TABLE_PREFIX."forums SET lastposter='".addslashes($mybb->input['username'])."' WHERE lastposter='".addslashes($user[username])."'");
		$db->query("UPDATE ".TABLE_PREFIX."threads SET lastposter='".addslashes($mybb->input['username'])."' WHERE lastposter='".addslashes($user[username])."'");
	}

	cpredirect("users.php", $lang->profile_updated);
}
if($mybb->input['action'] == "do_delete")
{
	if($mybb->input['deletesubmit'])
	{	
		$db->query("UPDATE ".TABLE_PREFIX."posts SET uid='0' WHERE uid='".intval($mybb->input['uid'])."'");
		$db->query("DELETE FROM ".TABLE_PREFIX."users WHERE uid='".intval($mybb->input['uid'])."'");
		$db->query("DELETE FROM ".TABLE_PREFIX."userfields WHERE ufid='".intval($mybb->input['uid'])."'");
		$db->query("DELETE FROM ".TABLE_PREFIX."privatemessages WHERE uid='".intval($mybb->input['uid'])."'");
		$db->query("DELETE FROM ".TABLE_PREFIX."events WHERE author='".intval($mybb->input['uid'])."'");
		$db->query("DELETE FROM ".TABLE_PREFIX."moderators WHERE uid='".intval($mybb->input['uid'])."'");
		$db->query("DELETE FROM ".TABLE_PREFIX."forumsubscriptions WHERE uid='".intval($mybb->input['uid'])."'");
		$db->query("DELETE FROM ".TABLE_PREFIX."favorites WHERE uid='".intval($mybb->input['uid'])."'");
		$db->query("DELETE FROM ".TABLE_PREFIX."sessions WHERE uid='".intval($mybb->input['uid'])."'");

		// Update forum stats
		$cache->updatestats();

		cpredirect("users.php", $lang->user_deleted);
	}
	else
	{
		header("Location: users.php?123");
	}
}
if($mybb->input['action'] == "do_email")
{
	$conditions = "1=1";

	$search = $mybb->input['search'];

	if($search['username'])
	{
		$conditions .= " AND username LIKE '%".addslashes($search[username])."%'";
	}
	if(is_array($search['usergroups']))
	{
		$groups = implode(",", $search['usergroups']);
		$conditions .= " AND usergroup IN ($groups)";
	}

	if($search['email'])
	{
		$conditions .= " AND email LIKE '%".addslashes($search[email])."%'";
	}
	if($search['website'])
	{
		$conditions .= " AND website LIKE '%".addslashes($search[website])."%'";
	}
	if($search['icq'])
	{
		$conditions .= " AND icq LIKE '%".addslashes($search[icq])."%'";
	}
	if($search['aim'])
	{
		$conditions .= " AND aim LIKE '%".addslashes($search[aim])."%'";
	}
	if($search['yahoo'])
	{
		$conditions .= " AND yahoo LIKE '%".addslashes($search[yahoo])."%'";
	}
	if($search['msn'])
	{
		$conditions .= " AND msn LIKE '%".addslashes($search[msn])."%'";
	}
	if($search['signature'])
	{
		$conditions .= " AND signature LIKE '%".addslashes($search[signature])."%'";
	}
	if($search['usertitle'])
	{
		$conditions .= " AND usertitle LIKE '%".addslashes($search[usertitle])."%'";
	}
	if($search['postsgreater'])
	{
		$conditions .= " AND postnum>".intval($search[postsgreater]);
	}
	if($search['postsless'])
	{
		$conditions .= " AND postnum<".intval($search[postsless]);
	}
	if($search['overridenotice'] != "yes")
	{
		$conditions .= " AND allownotices!='no'";
	}

	$searchop = $mybb->input['searchop'];
	if(!$searchop['perpage'])
	{
		$searchop['perpage'] = "500";
	}
	if(!$searchop['page'])
	{
		$searchop['page'] = "0";
		$searchop['start'] = "0";
	}
	else
	{
		$searchop['start'] = ($searchop['page']-1) * $searchop['perpage'];
	}
	$searchop['page']++;

	$query = $db->query("SELECT COUNT(*) AS results FROM ".TABLE_PREFIX."users WHERE $conditions ORDER BY uid LIMIT $searchop[start], $searchop[perpage]");
	$num = $db->fetch_array($query);
	if(!$num[results])
	{
		cpmessage($lang->error_no_users);
	}
	else
	{
		cpheader();
		starttable();
		tableheader($lang->mass_mail);
		$lang->results_matching = sprintf($lang->results_matching, $num['results']);
		tablesubheader($lang->results_matching);
		$bgcolor = getaltbg();
		echo "<tr>\n<td class=\"$bgcolor\" valign=\"top\">\n";
//		$query = $db->query("SELECT * FROM ".TABLE_PREFIX."users WHERE $conditions ORDER BY uid LIMIT $searchop[start], $searchop[perpage]");	
	@set_time_limit(0);
		$query = $db->query("SELECT * FROM ".TABLE_PREFIX."users WHERE $conditions ORDER BY uid");		
		while($user = $db->fetch_array($query))
		{
			$sendmessage = $searchop['message'];
			$sendmessage = str_replace("{uid}", $user['uid'], $sendmessage);
			$sendmessage = str_replace("{username}", $user['username'], $sendmessage);
			$sendmessage = str_replace("{email}", $user['email'], $sendmessage);
			$sendmessage = str_replace("{bbname}", $settings['bbname'], $sendmessage);
			$sendmessage = str_replace("{bburl}", $settings['bburl'], $sendmessage);

			if($searchop['type'] == "html" && $user['email'] != "")
			{
				echo sprintf($lang->email_sent, $user['username']);
			}
			elseif($searchop['type'] == "pm")
			{
				$now = time();
				$db->query("INSERT INTO ".TABLE_PREFIX."privatemessages(pmid,uid,toid,fromid,folder,subject,message,dateline,status,receipt) VALUES(NULL,'$user[uid]','$user[uid]','$mybbadmin[uid]','1','$searchop[subject]','$sendmessage','$now','0','no');");
				echo sprintf($lang->pm_sent, $user['username']);
			}
			elseif($user['email'] != "")
			{
				mymail($user['email'], $searchop['subject'], $sendmessage, $searchop[from]);
				echo sprintf($lang->email_sent, $user['username']);
			}
			else
			{
				echo sprintf($lang->not_sent, $user['username']);
			}
			echo "<br />";
		}
		echo "<br>".$lang->done;
		echo "</td>\n</tr>\n";
		endtable();
		startform("users.php", "", "do_email");	
		if(is_array($search))
		{
			while(list($key, $val) = each($search))
			{
				$hiddens .= "<input type=\"hidden\" name=\"search[$key]\" value=\"$val\">";
			}
		}
		while(list($key, $val) = each($searchop))
		{
			$hiddens .= "<input type=\"hidden\" name=\"searchop[$key]\" value=\"$val\">";
		}
		echo "$hiddens";
		if($num[results] > $searchop[perpage])
		{
			endform($lang->next_page, "");
		}

		cpfooter();
	}
}
if($mybb->input['action'] == "do_do_merge")
{
	if(!$mybb->input['deletesubmit'])
	{
		cpredirect("users.php?action=merge", "You chose not to merge the two users. You will now be taken back to the merge page.");
		exit;
	}
	$query = $db->query("SELECT * FROM ".TABLE_PREFIX."users WHERE username='".addslashes($mybb->input['source'])."'");
	$sourceuser = $db->fetch_array($query);
	if(!$sourceuser[uid])
	{
		cperror($lang->error_invalid_source);
	}

	$query = $db->query("SELECT * FROM ".TABLE_PREFIX."users WHERE username='".addslashes($mybb->input['destination'])."'");
	$destuser = $db->fetch_array($query);
	if(!$destuser[uid])
	{
		cperror($lang->error_invalid_destination);
	}
	$db->query("UPDATE ".TABLE_PREFIX."adminlog SET uid='".$destuser['uid']."' WHERE uid='".$sourceuser['uid']."'");
	$db->query("UPDATE ".TABLE_PREFIX."announcements SET uid='".$destuser['uid']."' WHERE uid='".$sourceuser['uid']."'");
	$db->query("UPDATE ".TABLE_PREFIX."events SET author='".$destuser['uid']."' WHERE author='".$sourceuser['uid']."'");
	$db->query("UPDATE ".TABLE_PREFIX."favorites SET uid='".$destuser['uid']."' WHERE uid='".$sourceuser['uid']."'");
	$db->query("UPDATE ".TABLE_PREFIX."forums SET lastposter='".$destuser['username']."' WHERE lastposter='".$sourceuser['username']."'");
	$db->query("UPDATE ".TABLE_PREFIX."forumsubscriptions SET uid='".$destuser['uid']."' WHERE uid='".$sourceuser['uid']."'");
	$db->query("UPDATE ".TABLE_PREFIX."moderatorlog SET uid='".$destuser['uid']."' WHERE uid='".$sourceuser['uid']."'");
	$db->query("UPDATE ".TABLE_PREFIX."moderators SET uid='".$destuser['uid']."' WHERE uid='".$sourceuser['uid']."'");
	$db->query("UPDATE ".TABLE_PREFIX."pollvotes SET uid='".$destuser['uid']."' WHERE uid='".$sourceuser['uid']."'");
	$db->query("UPDATE ".TABLE_PREFIX."posts SET uid='".$destuser['uid']."', username='".$destuser['username']."' WHERE uid='".$sourceuser['uid']."'");
	$db->query("UPDATE ".TABLE_PREFIX."posts SET edituid='".$destuser['uid']."' WHERE edituid='".$sourceuser['uid']."'");
	$db->query("UPDATE ".TABLE_PREFIX."privatemessages SET uid='".$destuser['uid']."' WHERE uid='".$sourceuser['uid']."'");
	$db->query("UPDATE ".TABLE_PREFIX."privatemessages SET toid='".$destuser['uid']."' WHERE toid='".$sourceuser['uid']."'");
	$db->query("UPDATE ".TABLE_PREFIX."privatemessages SET fromid='".$destuser['uid']."' WHERE fromid='".$sourceuser['uid']."'");
	$db->query("UPDATE ".TABLE_PREFIX."reputation SET uid='".$destuser['uid']."' WHERE uid='".$sourceuser['uid']."'");
	$db->query("UPDATE ".TABLE_PREFIX."threadratings SET uid='".$destuser['uid']."' WHERE uid='".$sourceuser['uid']."'");
	$db->query("UPDATE ".TABLE_PREFIX."threads SET uid='".$destuser['uid']."', username='".$destuser['username']."' WHERE uid='".$sourceuser['uid']."'");
	$db->query("UPDATE ".TABLE_PREFIX."threads SET lastposter='".$destuser['username']."', username='".$destuser['username']."' WHERE lastposter='".$sourceuser['username']."'");
	$db->query("DELETE FROM ".TABLE_PREFIX."users WHERE uid='".$sourceuser['uid']."'");
	$db->query("DELETE FROM ".TABLE_PREFIX."banned WHERE uid='".$sourceuser['uid']."'");
	$query = $db->query("SELECT COUNT(*) AS postnum FROM ".TABLE_PREFIX."posts WHERE uid='".$destuser['uid']."'");
	$num = $db->fetch_array($query);
	$db->query("UPDATE ".TABLE_PREFIX."users SET postnum='".$num['postnum']."' WHERE uid='".$destuser['uid']."'");
	$lang->users_merged = sprintf($lang->users_merged, $sourceuser['username'], $sourceuser['username'], $destuser['username']);
	cpmessage($lang->users_merged);
}
if($mybb->input['action'] == "do_merge")
{
	$query = $db->query("SELECT * FROM ".TABLE_PREFIX."users WHERE username='".addslashes($mybb->input['source'])."'");
	$sourceuser = $db->fetch_array($query);
	if(!$sourceuser[uid])
	{
		cperror($lang->error_invalid_source);
	}

	$query = $db->query("SELECT * FROM ".TABLE_PREFIX."users WHERE username='".addslashes($mybb->input['destination'])."'");
	$destuser = $db->fetch_array($query);
	if(!$destuser[uid])
	{
		cperror($lang->error_invalid_destination);
	}
	$lang->confirm_merge = sprintf($lang->confirm_merge, $sourceuser['username'], $destuser['username'], $sourceuser['username']);
	cpheader();
	startform("users.php", "", "do_do_merge");
	makehiddencode("source", $mybb->input['source']);
	makehiddencode("destination", $mybb->input['destination']);
	starttable();
	tableheader($lang->merge_accounts, "", 1);
	$yes = makebuttoncode("deletesubmit", $lang->yes);
	$no = makebuttoncode("no", $lang->no);
	makelabelcode("<center>$lang->confirm_merge<br><br>$yes$no</center>", "");
	endtable();
	endform();
	cpfooter();
}

if($mybb->input['action'] == "add")
{
	$query = $db->query("SELECT * FROM ".TABLE_PREFIX."usergroups ORDER BY title ASC");
	while($usergroup = $db->fetch_array($query))
	{
		$additionalgroups[] = "<input type=\"checkbox\" name=\"additionalgroups[]\" value=\"$usergroup[gid]\" /> $usergroup[title]";
	}
	$additionalgroups = implode("<br />", $additionalgroups);

	cpheader();
	starttable();
	startform("users.php", "", "do_add", 0);
	tableheader($lang->add_user);
	tablesubheader($lang->required_info);
	makeinputcode($lang->username, "username", "", 25, "", 25, 0);
	makepasswordcode($lang->password, "password", "", 25, 0);
	makeinputcode($lang->email, "email");
	makeselectcode($lang->primary_usergroup, "usergroup", "usergroups", "gid", "title", 2);
	makelabelcode($lang->secondary_usergroups, "<small>$additionalgroups</small>");

	tablesubheader($lang->optional_info);
	makeinputcode($lang->custom_title, "usertitle");
	makeinputcode($lang->avatar_url, "avatar");
	makeinputcode($lang->website, "website");
	makeinputcode($lang->icq_number, "icq");
	makeinputcode($lang->aim_handle, "aim");
	makeinputcode($lang->yahoo_handle, "yahoo");
	makeinputcode($lang->msn_address, "msn");
	makeinputcode($lang->birthday, "birthday");
	$query = $db->query("SELECT * FROM ".TABLE_PREFIX."profilefields WHERE editable='yes' ORDER BY disporder");
	while($profilefield = $db->fetch_array($query))
	{
		$profilefield[type] = htmlspecialchars_uni(stripslashes($profilefield[type]));
		$thing = explode("\n", $profilefield[type], "2");
		$type = trim($thing[0]);
		$options = $thing[1];
		$field = "fid$profilefield[fid]";
		if($type == "multiselect")
		{
			$expoptions = explode("\n", $options);
			if(is_array($expoptions))
			{
				while(list($key, $val) = each($expoptions))
				{
					$val = trim($val);
					$val = str_replace("\n", "\\n", $val);
					$select .= "<option value\"$val\">$val</option>\n";
				}
				if(!$profilefield[length])
				{
					$profilefield[length] = 3;
				}
				$code = "<select name=\"".$field."[]\" size=\"$profilefield[length]\" multiple=\"multiple\">$select</select>";
			}
		}
		elseif($type == "select")
		{
			$expoptions = explode("\n", $options);
			if(is_array($expoptions))
			{
				while(list($key, $val) = each($expoptions))
				{
					$val = trim($val);
					$val = str_replace("\n", "\\n", $val);
					$select .= "<option value\"$val\">$val</option>";
				}
				if(!$profilefield[length])
				{
					$profilefield[length] = 1;
				}
				$code = "<select name=\"$field\" size=\"$profilefield[length]\">$select</select>";
			}
		}
		elseif($type == "radio")
		{
			$expoptions = explode("\n", $options);
			if(is_array($expoptions))
			{
				while(list($key, $val) = each($expoptions))
				{
					$code .= "<input type=\"radio\" name=\"$field\" value=\"$val\"> $val<br>";
				}
			}
		}
		elseif($type == "checkbox")
		{
			$expoptions = explode("\n", $options);
			if(is_array($expoptions))
			{
				while(list($key, $val) = each($expoptions))
				{
					$code .= "<input type=\"checkbox\" name=\"".$field."[]\" value=\"$val\"> $val<br>";
				}
			}
		}
		elseif($type == "textarea")
		{
			$code = "<textarea name=\"$field\" rows=\"6\" cols=\"50\"></textarea>";
		}
		else
		{
			$value = htmlspecialchars_uni($mybbuser[$field]);
			$code = "<input type=\"text\" name=\"$field\" length=\"$profilefield[length]\" maxlength=\"$profilefield[maxlength]\">";
		}
		makelabelcode("$profilefield[name]", $code);
		$code = "";
		$select = "";
		$val = "";
		$options = "";
		$expoptions = "";
		$useropts = "";
		$seloptions = "";
	}


	tablesubheader($lang->account_prefs);
	makeyesnocode($lang->invisible_mode, "invisible", "no");
	makeyesnocode($lang->admin_emails, "allownotices", "yes");
	makeyesnocode($lang->hide_email, "hideemail", "no");
	makeyesnocode($lang->email_notify, "emailnotify", "yes");
	makeyesnocode($lang->enable_pms, "receivepms", "yes");
	makeyesnocode($lang->pm_popup, "pmpopup", "yes");
	makeyesnocode($lang->pm_notify, "pmnotify", "yes");
	makeinputcode($lang->time_offset, "timezoneoffset");
	makeselectcode($lang->style, "style", "themes", "tid", "name", -1, $lang->use_default, "", "tid>1");
	maketextareacode($lang->signature, "signature", "", 6, 50);
	endtable();
	endform($lang->add_user, $lang->reset_button);
	cpfooter();
}
if($mybb->input['action'] == "edit")
{
	$uid = intval($mybb->input['uid']);
	$query = $db->query("SELECT * FROM ".TABLE_PREFIX."users WHERE uid='$uid'");
	$user = $db->fetch_array($query);

	$additionalgroups = explode(",", $user['additionalgroups']);
	if($additionalgroups)
	{
		foreach($additionalgroups as $gid)
		{
			if($gid != $user['usergroup'])
			{
				$secondarygroups[$gid] = 1;
			}
		}
	}
	unset($additionalgroups);
	$query = $db->query("SELECT * FROM ".TABLE_PREFIX."usergroups ORDER BY title ASC");
	while($usergroup = $db->fetch_array($query))
	{
		$checked = "";
		if($secondarygroups[$usergroup['gid']])
		{
			$checked = "checked=\"checked\"";
		}
		if($user['usergroup'] != $usergroup['gid'])
		{
			$additionalgroups[] = "<input type=\"checkbox\" name=\"additionalgroups[]\" value=\"$usergroup[gid]\" $checked /> $usergroup[title]";
		}
	}
	$additionalgroups = implode("<br />", $additionalgroups);


	$lang->modify_user = sprintf($lang->modify_user, $user['username']);

	cpheader();
	starttable();
	makelabelcode("<ul>\n<li><a href=\"users.php?action=delete&uid=$uid\">$lang->delete_account</a></li>\n<li><a href=\"users.php?action=misc&uid=$uid\">$lang->view_user_stats</a></li>\n</ul>");
	endtable();

	starttable();
	startform("users.php", "", "do_edit", 0);	
	makehiddencode("uid", $uid);
	tableheader($lang->modify_user);
	tablesubheader($lang->required_info);
	makeinputcode($lang->username, "username", $user['username'], 25, "", 25, 0);
	makepasswordcode($lang->new_password, "password", "", 25, 0);
	makeinputcode($lang->email, "email", $user['email']);
	makeselectcode($lang->primary_usergroup, "usergroup", "usergroups", "gid", "title", $user['usergroup']);
	makelabelcode($lang->secondary_usergroups, "<small>$additionalgroups</small>");
	makeinputcode($lang->post_count, "postnum", $user['postnum'], 4);

	tablesubheader($lang->optional_info);
	makeinputcode($lang->custom_title, "usertitle", $user['usertitle']);
	makeinputcode($lang->avatar_url, "avatar", $user['avatar']);
	makeinputcode($lang->website, "website", $user['website']);
	makeinputcode($lang->icq_number, "icq", $user['icq']);
	makeinputcode($lang->aim_handle, "aim", $user['aim']);
	makeinputcode($lang->yahoo_handle, "yahoo", $user['yahoo']);
	makeinputcode($lang->msn_address, "msn", $user['msn']);
	makeinputcode($lang->birthday, "birthday", $user['birthday']);

	$query = $db->query("SELECT * FROM ".TABLE_PREFIX."userfields WHERE ufid='$uid'");
	$userfields = $db->fetch_array($query);

	$query = $db->query("SELECT * FROM ".TABLE_PREFIX."profilefields ORDER BY disporder");
	while($profilefield = $db->fetch_array($query))
	{
		$profilefield['type'] = htmlspecialchars_uni(stripslashes($profilefield['type']));
		$thing = explode("\n", $profilefield['type'], "2");
		$type = trim($thing[0]);
		$options = $thing[1];
		$field = "fid$profilefield[fid]";
		if($type == "multiselect")
		{
			$useropts = explode("\n", $userfields[$field]);
			while(list($key, $val) = each($useropts))
			{
				$seloptions[$val] = $val;
			}
			$expoptions = explode("\n", $options);
			if(is_array($expoptions))
			{
				while(list($key, $val) = each($expoptions))
				{
					$val = trim($val);
					$val = str_replace("\n", "\\n", $val);
					if($val == $seloptions[$val])
					{
						$sel = "selected";
					}
					else
					{
						$sel = "";
					}
					$select .= "<option value=\"$val\" $sel>$val</option>\n";
				}
				if(!$profilefield[length])
				{
					$profilefield[length] = 3;
				}
				$code = "<select name=\"".$field."[]\" size=\"$profilefield[length]\" multiple=\"multiple\">$select</select>";
			}
		}
		elseif($type == "select") {
			$expoptions = explode("\n", $options);
			if(is_array($expoptions))
			{
				while(list($key, $val) = each($expoptions))
				{
					$val = trim($val);
					$val = str_replace("\n", "\\n", $val);
					if($val == $userfields[$field])
					{
						$sel = "selected";
					}
					else
					{
						$sel = "";
					}
					$select .= "<option value=\"$val\" $sel>$val</option>";
				}
				if(!$profilefield[length])
				{
					$profilefield[length] = 1;
				}
				$code = "<select name=\"$field\" size=\"$profilefield[length]\">$select</select>";
			}
		}
		elseif($type == "radio")
		{
			$expoptions = explode("\n", $options);
			if(is_array($expoptions))
			{
				while(list($key, $val) = each($expoptions))
				{
					if($val == $userfields[$field])
					{
						$checked = "checked";
					}
					else
					{
						$checked = "";
					}
					$code .= "<input type=\"radio\" name=\"$field\" value=\"$val\" $checked> $val<br>";
				}
			}
		}
		elseif($type == "checkbox")
		{
			$useropts = explode("\n", $userfields[$field]);
			while(list($key, $val) = each($useropts))
			{
				$seloptions[$val] = $val;
			}
			$expoptions = explode("\n", $options);
			if(is_array($expoptions))
			{
				while(list($key, $val) = each($expoptions))
				{
					if($val == $seloptions[$val])
					{
						$checked = "checked";
					}
					else
					{
						$checked = "";
					}
					$code .= "<input type=\"checkbox\" name=\"".$field."[]\" value=\"$val\" $checked> $val<br>";
				}
			}
		}
		elseif($type == "textarea")
		{
			$value = htmlspecialchars_uni($userfields[$field]);
			$code = "<textarea name=\"$field\" rows=\"6\" cols=\"50\">$value</textarea>";
		}
		else
		{
			$value = htmlspecialchars_uni($userfields[$field]);
			$code = "<input type=\"text\" name=\"$field\" length=\"$profilefield[length]\" maxlength=\"$profilefield[maxlength]\" value=\"$value\">";
		}
		makelabelcode($profilefield[name], $code);
		$code = "";
		$select = "";
		$val = "";
		$options = "";
		$expoptions = "";
		$useropts = "";
		$seloptions = "";
	}


	tablesubheader($lang->account_prefs);
	makeyesnocode($lang->invisible_mode, "invisible", $user[invisible]);
	makeyesnocode($lang->admin_emails, "allownotices", $user[allownotices]);
	makeyesnocode($lang->hide_email, "hideemail", $user[hideemail]);
	makeyesnocode($lang->email_notify, "emailnotify", $user[emailnotify]);
	makeyesnocode($lang->enable_pms, "receivepms", $user[receivepms]);
	makeyesnocode($lang->pm_popup, "pmpopup", $user[pmpopup]);
	makeyesnocode($lang->pm_notify, "pmnotify", $user['pmnotify']);
	makeinputcode($lang->time_offset, "timezoneoffset", $user[timezone]);
	makeselectcode($lang->style, "stylesel", "themes", "tid", "name", $user[style], $lang->use_default, "", "tid>1");
	maketextareacode($lang->signature, "signature", $user[signature], 6, 50);
	if(!$user['regip']) { $user['regip'] = " "; }
	makelabelcode($lang->reg_ip, $user[regip]);

	endtable();
	endform($lang->update_user, $lang->reset_button);
}
if($mybb->input['action'] == "delete")
{
	$uid = intval($mybb->input['uid']);
	$query = $db->query("SELECT * FROM ".TABLE_PREFIX."users WHERE uid='$uid'");
	$user = $db->fetch_array($query);
	$lang->delete_user = sprintf($lang->delete_user, $user['username']);
	$lang->confirm_delete_user = sprintf($lang->confirm_delete_user, $user['username']);
	cpheader();
	startform("users.php", "", "do_delete");
	makehiddencode("uid", $uid);
	starttable();
	tableheader($lang->delete_user, "", 1);
	$yes = makebuttoncode("deletesubmit", $lang->yes);
	$no = makebuttoncode("no", $lang->no);
	makelabelcode("<center>$lang->confirm_delete_user<br><br>$yes$no</center>", "");
	endtable();
	endform();
	cpfooter();
}

if($mybb->input['action'] == "showreferrers")
{
	cpheader();
	$uid = intval($mybb->input['uid']);
	if($uid)
	{
		$query = $db->query("SELECT * FROM ".TABLE_PREFIX."users WHERE uid='$uid'");
		$user = $db->fetch_array($query);
		$lang->members_referred_by = sprintf($lang->members_referred_by, $user['username']);

		starttable();
		tableheader($lang->members_referred_by, "", 6);
		echo "<tr>\n";
		echo "<td class=\"subheader\">$lang->username</td>\n";
		echo "<td class=\"subheader\">$lang->posts</td>\n";
		echo "<td class=\"subheader\">$lang->email</td>\n";
		echo "<td class=\"subheader\">$lang->reg_date</td>\n";
		echo "<td class=\"subheader\">$lang->last_visit</td>\n";
		echo "</tr>\n";

		$query = $db->query("SELECT * FROM ".TABLE_PREFIX."users WHERE referrer='$uid' ORDER BY regdate DESC");
		while($refuser = $db->fetch_array($query))
		{
			$bgcolor = getaltbg();
			$regdate = gmdate("d-m-Y", $refuser['regdate']);
			$lvdate = gmdate("d-m-Y", $refuser['lastvisit']);
			echo "<tr>\n";
			echo "<td class=\"$bgcolor\">$refuser[username]</td>\n";
			echo "<td class=\"$bgcolor\">$refuser[postnum]</td>\n";
			echo "<td class=\"$bgcolor\">$refuser[email]</td>\n";
			echo "<td class=\"$bgcolor\">$regdate</td>\n";
			echo "<td class=\"$bgcolor\">$lvdate</td>\n";
			echo "</tr>\n";
		}
		endtable();
	}
}
if($mybb->input['action'] == "findips")
{
	cpheader();
	$uid = intval($mybb->input['uid']);
	$query = $db->query("SELECT uid,username,regip FROM ".TABLE_PREFIX."users WHERE uid='$uid'");
	$user = $db->fetch_array($query);
	if (!$user['uid'])
	{
		cperror($lang->error_no_users);
	}
	starttable();
	$lang->ip_addresses_user = sprintf($lang->ip_addresses_user, $user['username']);
	tableheader($lang->ip_addresses_user, "");
	tablesubheader($lang->reg_ip, "");
	if(!empty($user['regip']))
	{
		echo "<tr>\n<td class=\"$bgcolor\" width=\"40%\">$user[regip]</td>\n";
		echo "<td class=\"$bgcolor\" align=\"right\" width=\"60%\"><input type=\"button\" value=\"$lang->find_users_reg_with_ip\" onclick=\"hopto('users.php?action=find&search[regip]=$user[regip]');\" class=\"submitbutton\">  <input type=\"button\" value=\"$lang->find_users_posted_with_ip\" onclick=\"hopto('users.php?action=find&search[postip]=$user[regip]');\" class=\"submitbutton\">";
		echo "</td>\n</tr>\n";
	}
	else
	{
		makelabelcode($lang->error_no_ips, "", 2);
	}
	tablesubheader($lang->post_ip);
	$query = $db->query("SELECT DISTINCT ipaddress FROM ".TABLE_PREFIX."posts WHERE uid='$uid'");
	if($db->num_rows($query) > 0)
	{
		while($row = $db->fetch_array($query))
		{
			if(!empty($row['ipaddress']))
			{
				$bgcolor = getaltbg();
				echo "<tr>\n<td class=\"$bgcolor\" valign=\"top\" width=\"40%\">$row[ipaddress]</td>\n";
				echo "<td class=\"$bgcolor\" align=\"right\" width=\"60%\"><input type=\"button\" value=\"$lang->find_users_reg_with_ip\" onclick=\"hopto('users.php?action=find&search[regip]=$row[ipaddress]');\" class=\"submitbutton\">  <input type=\"button\" value=\"$lang->find_users_posted_with_ip\" onclick=\"hopto('users.php?action=find&search[postip]=$row[ipaddress]');\" class=\"submitbutton\">";
				echo "</td>\n</tr>\n";
			}
		}
	}
	else
	{
		makelabelcode($lang->error_no_ips, "", 2);
	}
	endtable();
}
if($mybb->input['action'] == "misc")
{
	cpheader();
	$uid = intval($mybb->input['uid']);
	starttable();
	makelabelcode("<ul>\n<li><a href=\"users.php?action=showreferrers&uid=$uid\">$lang->show_referred_members</a></li>\n<li><a href=\"users.php?action=pmstats&uid=$uid\">$lang->pm_stats</a></li>\n<li><a href=\"users.php?action=stats&uid=$uid\">$lang->general_stats</a></li>\n<li><a href=\"users.php?action=findips&uid=$uid\">$lang->ip_addresses</a></li>\n</ul>");
	endtable();
	cpfooter();
}
if($mybb->input['action'] == "merge")
{
	cpheader();
	startform("users.php", "", "do_merge");
	starttable();
	tableheader($lang->merge_user_accounts);
	tablesubheader($lang->instructions);
	makelabelcode($lang->merge_instructions, "", 2);
	tablesubheader($lang->user_accounts);
	makeinputcode($lang->source_account, "source");
	makeinputcode($lang->dest_account, "destination");
	endtable();
	endform($lang->merge_user_accounts);
	cpfooter();
}
if($mybb->input['action'] == "stats")
{
	$uid = intval($mybb->input['uid']);
	$query = $db->query("SELECT * FROM ".TABLE_PREFIX."users WHERE uid='$uid'");
	$user = $db->fetch_array($query);
	$lang->general_user_stats = sprintf($lang->general_user_stats, $user['username']);

	$daysreg = (time() - $user[regdate]) / (24*3600);
	$ppd = $user[postnum] / $daysreg;
	$ppd = round($ppd, 2);
	if(!$ppd || $ppd > $user[postnum])
	{
		$ppd = $user[postnum];
	}
	$query = $db->query("SELECT COUNT(pid) FROM ".TABLE_PREFIX."posts");
	$posts = $db->result($query, 0);
	if($posts == 0)
	{
		$percent = "0%";
	}
	else
	{
		$percent = $user[postnum]*100/$posts;
		$percent = round($percent, 2)."%";
	}

	$query = $db->query("SELECT COUNT(*) FROM ".TABLE_PREFIX."users WHERE referrer='$user[uid]'");
	$referrals = $db->result($query, 0);

	$memregdate = mydate($settings[dateformat], $user[regdate]);
	$memlocaldate = gmdate($settings[dateformat], time() + ($user[timezone] * 3600));
	$memlocaltime = gmdate($settings[timeformat], time() + ($user[timezone] * 3600));
	$memlastvisitdate = mydate($settings[dateformat], $user[lastvisit]);
	$memlastvisittime = mydate($settings[timeformat], $user[lastvisit]);
	
	if($user['birthday'])
	{
		$membday = explode("-", $user['birthday']);
		if($membday[2])
		{
			$bdayformat = fixmktime($settings['dateformat'], $membday[2]);
			$membday = mktime(0, 0, 0, $membday[1], $membday[0], $membday[2]);
			$membdayage = "(" . floor((time() - $membday) / 31557600) . " ".$lang->years_old .")";
			$membday = gmdate($bdayformat, $membday);
		}
		else
		{
			$membday = mktime(0, 0, 0, $membday[1], $membday[0], 0);
			$membday = gmdate("F j", $membday);
			$membdayage = "";
		}
	}
	else
	{
		$membday = $lang->not_specified;
		$membdayage = $lang->not_specified;
	}
	cpheader();
	starttable();
	tableheader($lang->general_user_stats);
	makelabelcode($lang->reg_date, $memregdate);
	makelabelcode($lang->total_posts, $user[postnum]);
	makelabelcode($lang->posts_per_day, $ppd);
	makelabelcode($lang->percent_tot_posts, $percent);
	makelabelcode($lang->last_visit, "$memlastvisitdate $memlastvisittime");
	makelabelcode($lang->local_time, "$memlocaldate $memlocaltime");
	makelabelcode($lang->age, $membdayage);
	endtable();
	cpfooter();
}

if($mybb->input['action'] == "pmstats")
{
	$uid = intval($mybb->input['uid']);
	$query = $db->query("SELECT * FROM ".TABLE_PREFIX."users WHERE uid='$uid'");
	$user = $db->fetch_array($query);
	$lang->pm_stats = sprintf($lang->pm_stats, $user['username']);
	$lang->custom_pm_folders = sprintf($lang->custom_pm_folders, $user['username']);
	
	$query = $db->query("SELECT COUNT(*) AS total FROM ".TABLE_PREFIX."privatemessages WHERE uid='$uid'");
	$pmscount = $db->fetch_array($query);

	$query = $db->query("SELECT COUNT(*) AS newpms FROM ".TABLE_PREFIX."privatemessages WHERE uid='$uid' AND dateline>$user[lastvisit]  AND folder='1'");
	$newpmscount = $db->fetch_array($query);

	$query = $db->query("SELECT COUNT(*) AS unreadpms FROM ".TABLE_PREFIX."privatemessages WHERE uid='$uid' AND status='0' AND folder='1'");
	$unreadpmscount = $db->fetch_array($query);

	cpheader();
	starttable();
	tableheader($lang->pm_stats);
	makelabelcode($lang->total_pms, $pmscount[total]);
	makelabelcode($lang->new_pms, $newpmscount[newpms]);
	makelabelcode($lang->unread_pms, $unreadpmscount[unreadpms]);
	tablesubheader($lang->custom_pm_folders);
	$pmfolders = explode("$%%$", $user[pmfolders]);
	while(list($key, $folder) = each($pmfolders))
	{
		$folderinfo = explode("**", $folder, 2);
		$query = $db->query("SELECT COUNT(*) AS inthisfolder FROM ".TABLE_PREFIX."privatemessages WHERE uid='$uid' AND folder='$folderinfo[0]'");
		$thecount = $db->fetch_array($query);
		makelabelcode("$folderinfo[1]", "<b>$thecount[inthisfolder]</b> ".$lang->messages);
		$thecount = "";
	}
	endtable();
	cpfooter();

}
		
if($mybb->input['action'] == "email")
{
	if(!$noheader)
	{
		cpheader();
	}
	startform("users.php", "", "do_email");
	starttable();
	tableheader($lang->mass_email_users);
	tablesubheader($lang->email_options);
	makeinputcode($lang->per_page, "searchop[perpage]", "500", "10");
	makeinputcode($lang->from, "searchop[from]", $settings['adminemail']);
	makeinputcode($lang->subject, "searchop[subject]");
	maketextareacode($lang->message, "searchop[message]", "", 10, 50);
	$typeoptions = "<input type=\"radio\" name=\"searchop[type]\" value=\"email\" checked=\"checked\" /> $lang->normal_email<br />\n";
//	$typeoptions .= "<input type=\"radio\" name=\"searchop[type]\" value=\"html\" /> $lang->html_email<br />\n";
	$typeoptions .= "<input type=\"radio\" name=\"searchop[type]\" value=\"pm\" /> $lang->send_pm<br />\n";
	makelabelcode($lang->send_method, "$typeoptions");
	tablesubheader($lang->email_users);
	makeinputcode($lang->name_contains, "search[username]");
	$query = $db->query("SELECT * FROM ".TABLE_PREFIX."usergroups ORDER BY title ASC");
	while($usergroup = $db->fetch_array($query))
	{
		$groups[] = "<input type=\"checkbox\" name=\"search[usergroups[]]\" value=\"$usergroup[gid]\" /> $usergroup[title]";
	}
	$groups = implode("<br />", $groups);

	makelabelcode($lang->primary_group, "<small>$groups</small>");
	makeinputcode($lang->and_email, "search[email]");
	makeinputcode($lang->and_website, "search[homepage]");
	makeinputcode($lang->and_icq, "search[icq]");
	makeinputcode($lang->and_aim, "search[aim]");
	makeinputcode($lang->and_yahoo, "search[yahoo]");
	makeinputcode($lang->and_msn, "search[msn]");
	makeinputcode($lang->and_sig, "search[signature]");
	makeinputcode($lang->and_title, "search[usertitle]");
	makeinputcode($lang->posts_more, "search[postsgreater]");
	makeinputcode($lang->posts_less, "search[postsless]");
	makeyesnocode($lang->override_notice, "search[overridenotice]", "no");
	endtable();
	endform($lang->send_mail, $lang->reset_button);
	cpfooter();
}

if($mybb->input['action'] == "find")
{
	$searchdisp = $mybb->input['searchdisp'];
	$search = $mybb->input['search'];
	$searchop = $mybb->input['searchop'];

	$dispcount = count($searchdisp);
	$yescount = "0";
	if($mybb->input['searchdisp'])
	{
		foreach($mybb->input['searchdisp'] as $disp)
		{
			if($disp == "yes")
			{
				$yescount++;
			}
		}
	}
	if($yescount == "0")
	{
		$searchdisp['username'] = "yes";
		$searchdisp['ops'] = "yes";
		$searchdisp['email'] = "yes";
		$searchdisp['regdate'] = "yes";
		$searchdisp['lastvisit'] = "yes";
		$searchdisp['postnum'] = "yes";
		$dispcount = count($searchdisp);
	}
	$conditions = "1=1";

	if($search['username'])
	{
		$search['username'] = addslashes($search['username']);
		$conditions .= " AND username LIKE '%$search[username]%'";
	}
	if(count($search['additionalgroups']) > 0)
	{
		foreach($search['additionalgroups'] as $group)
		{
			$conditions .= " AND (usergroup='".intval($group)."' OR CONCAT(',',additionalgroups,',') LIKE '%,".intval($group).",%')";
		}
	}
	if($search['email'])
	{
		$search['email'] = addslashes($search['email']);
		$conditions .= " AND email LIKE '%$search[email]%'";
	}
	if($search['website'])
	{
		$search['website'] = addslashes($search['website']);
		$conditions .= " AND website LIKE '%$search[website]%'";
	}
	if($search['icq'])
	{
		$search['icq'] = intval($search['icq']);
		$conditions .= " AND icq LIKE '%$search[icq]%'";
	}
	if($search['aim'])
	{
		$search['aim'] = addslashes($search['aim']);
		$conditions .= " AND aim LIKE '%$search[aim]%'";
	}
	if($search['yahoo'])
	{
		$search['yahoo'] = addslashes($search['yahoo']);
		$conditions .= " AND yahoo LIKE '%$search[yahoo]%'";
	}
	if($search['msn'])
	{
		$search['msn'] = addslashes($search['msn']);
		$conditions .= " AND msn LIKE '%$search[msn]%'";
	}
	if($search['signature'])
	{
		$search['signature'] = addslashes($search['signature']);
		$conditions .= " AND signature LIKE '%$search[signature]%'";
	}
	if($search['usertitle'])
	{
		$search['usertitle'] = addslashes($search['usertitle']);
		$conditions .= " AND usertitle LIKE '%$search[usertitle]%'";
	}
	if($search['postsgreater'])
	{
		$search['postsgreater'] = intval($search['postsgreater']);
		$conditions .= " AND postnum>$search[postsgreater]";
	}
	if($search['postsless'])
	{
		$search['postsless'] = intval($search['postsless']);
		$conditions .= " AND postnum<$search[postsless]";
	}
	if($search['regip'])
	{
		$search['regip'] = addslashes($search['regip']);
		$conditions .= " AND regip LIKE '$search[regip]%'";
	}
	if($search['postip'])
	{
		$search['postip'] = addslashes($search['postip']);
		$query = $db->query("SELECT DISTINCT uid FROM ".TABLE_PREFIX."posts WHERE ipaddress LIKE '$search[postip]%'");
		$uids = ',';
		while($u = $db->fetch_array($query))
		{
			$uids .= $u['uid'] . ',';		
		}
		$conditions .= " AND '$uids' LIKE CONCAT('%,',uid,',%')";
	}
	if($listall)
	{
		$conditions = "1=1";
	}
	if(!$searchop['sortby'])
	{
		$searchop['sortby'] = "username";
	}
	if(!$searchop['perpage'])
	{
		$searchop['perpage'] = "30";
	}
	if(!$searchop['page'])
	{
		$searchop['page'] = "1";
		$searchop['start'] = "0";
	}
	else
	{
		$searchop['start'] = ($searchop['page']-1) * $searchop['perpage'];
	}
	$searchop['page']++;

	$countquery = "SELECT * FROM ".TABLE_PREFIX."users WHERE $conditions";
	$query = $db->query($countquery);
	$numusers = $db->num_rows($query);

	$query = $db->query("$countquery ORDER BY $searchop[sortby] $searchop[order] LIMIT $searchop[start], $searchop[perpage]");
	
	if($numusers == 0)
	{
		cpheader();
		starttable();
		makelabelcode($lang->error_no_users);
		endtable();
		$noheader = 1;
		$mybb->input['action'] = "search";
	}
	else
	{
		$query2 = $db->query("SELECT * FROM ".TABLE_PREFIX."usergroups");
		while($usergroup = $db->fetch_array($query2))
		{
			$usergroups[$usergroup['gid']] = $usergroup;
		}
		$lang->results_found = sprintf($lang->results_found, $numusers);
		cpheader();
		starttable();
		tableheader($lang->search_results);
		makelabelcode($lang->results_found);
		endtable();
		starttable();
		echo "<tr>\n";

		if($searchdisp['username'] == "yes")
		{
			echo "<td class=\"subheader\" align=\"center\">$lang->name_header</td>\n";
		}
		if($searchdisp['usergroup'] == "yes")
		{
			echo "<td class=\"subheader\" align=\"center\">$lang->usergroup</td>\n";
		}
		if($searchdisp['email'] == "yes")
		{
			echo "<td class=\"subheader\" align=\"center\">$lang->email</td>\n";
		}
		if($searchdisp['website'] == "yes")
		{
			echo "<td class=\"subheader\" align=\"center\">$lang->website</td>\n";
		}
		if($searchdisp['icq'] == "yes")
		{
			echo "<td class=\"subheader\" align=\"center\">$lang->icq_number</td>\n";
		}
		if($searchdisp['aim'] == "yes")
		{
			echo "<td class=\"subheader\" align=\"center\">$lang->aim_handle</td>\n";
		}
		if($searchdisp['yahoo'] == "yes")
		{
			echo "<td class=\"subheader\" align=\"center\">$lang->yahoo_handle</td>\n";
		}
		if($searchdisp['msn'] == "yes")
		{
			echo "<td class=\"subheader\" align=\"center\">$lang->msn_address</td>\n";
		}
		if($searchdisp['signature'] == "yes")
		{
			echo "<td class=\"subheader\" align=\"center\">$lang->signature</td>\n";
		}
		if($searchdisp['usertitle'] == "yes")
		{
			echo "<td class=\"subheader\" align=\"center\">$lang->usertitle</td>\n";
		}
		if($searchdisp['regdate'] == "yes")
		{
			echo "<td class=\"subheader\" align=\"center\">$lang->reg_date</td>\n";
		}
		if($searchdisp['lastvisit'] == "yes")
		{
			echo "<td class=\"subheader\" align=\"center\">$lang->last_visit</td>\n";
		}
		if($searchdisp['postnum'] == "yes")
		{
			echo "<td class=\"subheader\" align=\"center\">$lang->posts</td>\n";
		}
		if($searchdisp['birthday'] == "yes")
		{
			echo "<td class=\"subheader\" align=\"center\">$lang->birthday</td>\n";
		}
		if($searchdisp['regip'] == "yes")
		{
			echo "<td class=\"subheader\" align=\"center\">$lang->reg_ip</td>\n";
		}
		if($searchdisp['ops'] == "yes")
		{
			echo "<td class=\"subheader\" align=\"center\">$lang->options</td>\n";
		}
		echo "</tr>\n";

		$options['edit'] = $lang->edit;
		$options['delete'] = $lang->delete;
		$options['manageban'] = $lang->ban_user;
		$options['showreferrers'] = $lang->show_referred;
		$options['misc'] = $lang->misc_options;
		$options['findips'] = $lang->ip_addresses;
		while($user = $db->fetch_array($query))
		{
			foreach($user as $name => $value)
			{
				$user[$name] = htmlspecialchars_uni($value);
			}
			if($user['usergroup'] == 5)
			{
				$options['activate'] = $lang->activate;
			}
			$bgcolor = getaltbg();
			startform("users.php");
			makehiddencode("uid", $user['uid']);
			makehiddencode("auid", $user['uid']);
			echo "<tr>\n";
			if($searchdisp['username'] == "yes")
			{
				echo "<td class=\"$bgcolor\">$user[username]</td>\n";
			}
			if($searchdisp['usergroup'] == "yes")
			{
				echo "<td class=\"$bgcolor\" align=\"center\">";
				if(isset($usergroups[$user['usergroup']]))
				{
					$group = $usergroups[$user['usergroup']];
					echo "<b>".$group['title']."</b>";
				}
				$additional = explode(",", $user['additionalgroups']);
				if($additional)
				{
					foreach($additional as $othergroup)
					{
						if($othergroup != $user['usergroup'])
						{
							$ugroup = $usergroups[$othergroup];
							echo "<br />".$ugroup['title'];
						}
					}
				}
				echo "</td>\n";
			}
			if($searchdisp['email'] == "yes")
			{
				echo "<td class=\"$bgcolor\"><a href=\"mailto:$user[email]\">$user[email]</td>\n";
			}
			if($searchdisp['website'] == "yes")
			{
				echo "<td class=\"$bgcolor\"><a href=\"$user[website]\" target=\"_blank\">$user[website]</a></td>\n";
			}
			if($searchdisp['icq'] == "yes")
			{
				echo "<td class=\"$bgcolor\">$user[icq]</td>\n";
			}
			if($searchdisp['aim'] == "yes")
			{
				echo "<td class=\"$bgcolor\">$user[aim]</td>\n";
			}
			if($searchdisp['yahoo'] == "yes")
			{
				echo "<td class=\"$bgcolor\">$user[yahoo]</td>\n";
			}
			if($searchdisp['msn'] == "yes") {
				echo "<td class=\"$bgcolor\">$user[msn]</td>\n";
			}
			if($searchdisp['signature'] == "yes")
			{
				$user['signature'] = nl2br($user['signature']);
				echo "<td class=\"$bgcolor\">$user[signature]</td>\n";
			}
			if($searchdisp['usertitle'] == "yes")
			{
				echo "<td class=\"$bgcolor\">$user[usertitle]</td>\n";
			}
			if($searchdisp['regdate'] == "yes")
			{
				$date = gmdate("d-m-Y", $user['regdate']);
				echo "<td class=\"$bgcolor\">$date</td>\n";
			}
			if($searchdisp['lastvisit'] == "yes")
			{
				$date = gmdate("d-m-Y", $user['lastvisit']);
				echo "<td class=\"$bgcolor\">$date</td>\n";
			}
			if($searchdisp['postnum'] == "yes")
			{
				echo "<td class=\"$bgcolor\"><a href=\"../search.php?action=finduser&uid=$user[uid]\">$user[postnum]</a></td>\n";
			}
			if($searchdisp['birthday'] == "yes")
			{
				echo "<td class=\"$bgcolor\">$user[birthday]</td>\n";
			}
			if($searchdisp['regip'] == "yes")
			{
				echo "<td class=\"$bgcolor\">$user[regip]</td>\n";
			}
			if($searchdisp['ops'] == "yes")
			{
				echo "<td class=\"$bgcolor\" align=\"right\">".makehopper("action", $options)."</td>\n";
			}
			echo "</tr>\n";
			endform();
		}
		endtable();
		startform("users.php", "", "find");	
		if(is_array($search))
		{
			while(list($key, $val) = each($search))
			{
				if ($key != 'additionalgroups[]')
				{
					$hiddens .= "<input type=\"hidden\" name=\"search[$key]\" value=\"$val\">";
				}
			}
		}
		if(is_array($search['additionalgroups']))
		{
			while(list($key, $val) = each($search))
			{
				$hiddens .= "<input type=\"hidden\" name=\"search[additionalgroups][]\" value=\"$val\">";
			}
		}
		while(list($key, $val) = each($searchop))
		{
			$hiddens .= "<input type=\"hidden\" name=\"searchop[$key]\" value=\"$val\">";
		}
		while(list($key, $val) = each($searchdisp))
		{
			$hiddens .= "<input type=\"hidden\" name=\"searchdisp[$key]\" value=\"$val\">";
		}
		echo "$hiddens";
		if($numusers > $searchop['perpage'])
		{
			endform($lang->next_page, "");
		}
	}
}
if($mybb->input['action'] == "activate")
{
	$query = $db->query("UPDATE ".TABLE_PREFIX."users SET usergroup = '2' WHERE uid='".intval($mybb->input['uid'])."' AND usergroup = '5'");
	cpredirect("users.php", $lang->activated);
}
if($mybb->input['action'] == "do_manageban")
{
	if($mybb->input['uid'])
	{
		$query = $db->query("SELECT * FROM ".TABLE_PREFIX."banned WHERE uid='".intval($mybb->input['uid'])."'");
		$ban = $db->fetch_array($query);

		$query = $db->query("SELECT * FROM ".TABLE_PREFIX."users WHERE uid='".intval($mybb->input['uid'])."'");
		$user = $db->fetch_array($query);

		if(!$ban['uid'])
		{
			cperror($lang->error_not_banned);
		}
		$bancheck = $ban;

	}
	else
	{
		$query = $db->query("SELECT * FROM ".TABLE_PREFIX."users WHERE username='".addslashes($mybb->input['username'])."'");
		$user = $db->fetch_array($query);

		if(!$user['uid'])
		{
			cperror($lang->error_not_found);
		}
		$query = $db->query("SELECT * FROM ".TABLE_PREFIX."banned WHERE uid='$user[uid]'");
		$bancheck = $db->fetch_array($query);
		$uid = $user['uid'];
	}
	if($mybb->input['liftafter'] == "---")
	{ // permanent ban
		$liftdate = "perm";
		$mybb->input['liftafter'] = "perm";
	}
	else
	{
		$liftdate = date2timestamp($mybb->input['liftafter']);
	}
	$lang->ban_updated = sprintf($lang->ban_updated, $user['username']);
	$lang->ban_added = sprintf($lang->ban_added, $user['username']);
	$now = time();
	$groupupdate = array(
		"usergroup" => intval($mybb->input['usergroup'])
		);
	$db->update_query(TABLE_PREFIX."users", $groupupdate, "uid='".$user['uid']."'");
	if($bancheck['uid'])
	{
		$banneduser = array(
			"admin" => $mybbadmin['uid'],
			"dateline" => time(),
			"gid" => $mybb->input['gid'],
			"bantime" => $mybb->input['liftafter'],
			"lifted" => $liftdate,
			"reason" => addslashes($mybb->input['banreason'])
			);

		$db->update_query(TABLE_PREFIX."banned", $banneduser, "uid='".$user['uid']."'");
		cpredirect("users.php?action=banned", $lang->ban_updated);
	}
	else
	{
		$banneduser = array(
			"uid" => $user['uid'],
			"admin" => $mybbadmin['uid'],
			"gid" => $mybb->input['gid'],
			"oldgroup" => $user['usergroup'],
			"dateline" => time(),
			"bantime" => $mybb->input['liftafter'],
			"lifted" => $liftdate,
			"reason" => addslashes($mybb->input['banreason'])
			);
		$db->insert_query(TABLE_PREFIX."banned", $banneduser);
		cpredirect("users.php?action=banned", $lang->ban_added);
	}
}
if($mybb->input['action'] == "liftban")
{
	$query = $db->query("SELECT * FROM ".TABLE_PREFIX."banned WHERE uid='".intval($mybb->input['uid'])."'");
	$ban = $db->fetch_array($query);
	$query = $db->query("SELECT * FROM ".TABLE_PREFIX."users WHERE uid='".intval($mybb->input['uid'])."'");
	$user = $db->fetch_array($query);
	$lang->ban_lifted = sprintf($lang->ban_lifted, $user['username']);
	if(!$ban[uid])
	{
		cperror($lang->error_not_banned);
	}
	$groupupdate = array("usergroup" => $ban['oldgroup']);
	$db->update_query(TABLE_PREFIX."users", $groupupdate, "uid='".intval($mybb->input['uid'])."'");
	$db->query("DELETE FROM ".TABLE_PREFIX."banned WHERE uid='".intval($mybb->input['uid'])."'");
	cpredirect("users.php?action=banned", $lang->ban_lifted);
}
if($mybb->input['action'] == "manageban")
{
	if($mybb->input['uid'] && !$mybb->input['auid'])
	{ // editing a ban
		$query = $db->query("SELECT * FROM ".TABLE_PREFIX."banned WHERE uid='".intval($mybb->input['uid'])."'");
		$ban = $db->fetch_array($query);

		$query = $db->query("SELECT * FROM ".TABLE_PREFIX."users WHERE uid='".intval($mybb->input['uid'])."'");
		$user = $db->fetch_array($query);
		$lang->edit_banning_options = sprintf($lang->edit_banning_options, $user['username']);

		if(!$ban[uid])
		{
			cperror($lang->error_not_banned);
		}
	
		cpheader();
		starttable();
		startform("users.php", "", "do_manageban");
		makehiddencode("uid", $mybb->input['uid']);
		tableheader($lang->edit_banning_options);
	}
	else
	{
		$query = $db->query("SELECT * FROM ".TABLE_PREFIX."users WHERE uid='".intval($mybb->input['auid'])."'");
		$user = $db->fetch_array($query);

		cpheader();
		starttable();
		startform("users.php", "", "do_manageban");
		tableheader($lang->ban_user);
		$ban[bantime] = "1-0-0";
		makeinputcode($lang->username, "username", $user['username']);
	}
	makeinputcode($lang->ban_reason, "banreason", $ban['reason']);
	makeselectcode($lang->move_banned_group, "usergroup", "usergroups", "gid", "title", $user['usergroup'], "", "", "isbannedgroup='yes'");
	reset($bantimes);
	while(list($time, $title) = each($bantimes))
	{
		$liftlist .= "<option value=\"$time\" ";
		if($time == $ban[bantime])
		{
			$liftlist .= "selected";
		}
		$thatime = date("D, jS M Y @ g:ia", date2timestamp($time));
		$liftlist .= ">$title ($thatime)</option>\n";
	}
	if($ban[bantime] == "perm" || $ban[bantime] == "---")
	{
		$permsel = "selected";
	}
	makelabelcode($lang->lift_ban_after, "<select name=\"liftafter\">\n$liftlist\n<option value=\"---\" $permsel>$lang->perm_ban</option>\n</select>\n");
	endtable();
	if($uid)
	{
		endform($lang->update_ban_settings, $lang->reset_button);
	}
	else
	{
		endform($lang->ban_user, $lang->reset_button);
	}
	cpfooter();
}
if($mybb->input['action'] == "banned")
{
	checkbanned();
	$query = $db->query("SELECT b.*, a.username AS adminuser, u.username FROM ".TABLE_PREFIX."banned b LEFT JOIN ".TABLE_PREFIX."users u ON (b.uid=u.uid) LEFT JOIN ".TABLE_PREFIX."users a ON (b.admin=a.uid) ORDER BY lifted ASC");
	$numbans = $db->num_rows($query);
	cpheader();
	$hopto[] = "<input type=\"button\" value=\"$lang->ban_user\" onclick=\"hopto('users.php?action=manageban');\" class=\"hoptobutton\">";
	makehoptolinks($hopto);

	starttable();
	tableheader($lang->banned_users, "", 7);
	echo "<tr>\n";
	echo "<td class=\"subheader\" align=\"center\">$lang->username</td>\n";
	echo "<td class=\"subheader\" align=\"center\">$lang->banned_by</td>\n";
	echo "<td class=\"subheader\" align=\"center\">$lang->banned_on</td>\n";
	echo "<td class=\"subheader\" align=\"center\">$lang->ban_length</td>\n";
	echo "<td class=\"subheader\" align=\"center\">$lang->lifted_on</td>\n";
	echo "<td class=\"subheader\" align=\"center\">$lang->time_remaining</td>\n";
	echo "<td class=\"subheader\" align=\"center\">$lang->options</td>\n";
	echo "</tr>\n";
	if(!$numbans)
	{
		makelabelcode("<center>$lang->error_no_banned</center>", "", 7);
	}
	else
	{
		while($user = $db->fetch_array($query))
		{
			$bgcolor = getaltbg();
			if($user[lifted] == "perm" || $user[lifted] == "" || $user[bantime] == "perm" || $user[bantime] == "---")
			{
				$banlength = $lang->permanent;
				$timeremaining = "-";
				$liftedon = $lang->never;
			}
			else
			{
				$banlength = $bantimes[$user['bantime']];
				$timeremaining = getbanremaining($user['lifted']);
				$liftedon = mydate($settings['dateformat'], $user['lifted']);
			}
			$user['banreason'] = htmlspecialchars_uni($user['banreason']);
			$bannedon = mydate($settings['dateformat'], $user['dateline']);
			echo "<tr title='$user[reason]'>\n";
			echo "<td class=\"$bgcolor\" align=\"center\"><a href=\"users.php?action=edit&uid=$user[uid]\">$user[username]</a></td>\n";
			echo "<td class=\"$bgcolor\" align=\"center\">$user[adminuser]</td>\n";
			echo "<td class=\"$bgcolor\" align=\"center\">$bannedon</td>\n";
			echo "<td class=\"$bgcolor\" align=\"center\">$banlength</td>\n";
			echo "<td class=\"$bgcolor\" align=\"center\">$liftedon</td>\n";
			echo "<td class=\"$bgcolor\" align=\"center\">$timeremaining</td>\n";
			echo "<td class=\"$bgcolor\" align=\"center\">".makelinkcode("edit", "users.php?action=manageban&uid=$user[uid]")." ".makelinkcode("lift", "users.php?action=liftban&uid=$user[uid]")."</td>\n";
		}
	}
	endtable();
	cpfooter();
}

if ($mybb->input['action'] == "search" || !$mybb->input['action'])
{
	if(!$noheader)
	{
		cpheader();
	}
	else
	{
		echo "<br />";
	}
	$query = $db->query("SELECT * FROM ".TABLE_PREFIX."usergroups ORDER BY title ASC");
	while($usergroup = $db->fetch_array($query))
	{
		$groups[] = "<input type=\"checkbox\" name=\"search[additionalgroups][]\" value=\"$usergroup[gid]\" /> $usergroup[title]";
	}
	$groups = implode("<br />", $groups);

	starttable();
	startform("users.php", "", "find");
	tableheader($lang->user_management);
	tablesubheader($lang->quick_search_listing);
	makelabelcode("<ul>\n<li><a href=\"users.php?action=find\">$lang->list_all</a></li>\n<li><a href=\"users.php?action=find&searchop[sortby]=postnum&searchop[order]=desc\">$lang->list_top_posters</a></li>\n<li><a href=\"users.php?action=find&searchop[sortby]=regdate&searchop[order]=desc\">$lang->list_new_regs</a></li>\n<li><a href=\"users.php?action=find&search[additionalgroups][]=5&searchop[sortby]=regdate&searchop[order]=desc\">$lang->list_awaiting_activation</a></li>\n</ul>", "", 2);
	tablesubheader($lang->search_users_where);
	makeinputcode($lang->name_contains, "search[username]");
	makeinputcode($lang->and_email, "search[email]");
	makelabelcode($lang->is_member_of, $groups);
	makeinputcode($lang->and_website, "search[homepage]");
	makeinputcode($lang->and_icq, "search[icq]");
	makeinputcode($lang->and_aim, "search[aim]");
	makeinputcode($lang->and_yahoo, "search[yahoo]");
	makeinputcode($lang->and_msn, "search[msn]");
	makeinputcode($lang->and_sig, "search[signature]");
	makeinputcode($lang->and_title, "search[usertitle]");
	makeinputcode($lang->posts_more, "search[postsgreater]");
	makeinputcode($lang->posts_less, "search[postsless]");
	makeinputcode($lang->and_reg_ip, "search[regip]");
	makeinputcode($lang->and_post_ip, "search[postip]");

	tablesubheader($lang->sorting_misc_options);
	$bgcolor = getaltbg();
	echo "<tr>\n";
	echo "<td class=\"$bgcolor\" valign=\"top\">$lang->sort_results</td>\n";
	echo "<td class=\"$bgcolor\" valign=\"top\">\n";
	echo "<select name=\"searchop[sortby]\">\n";
	echo "<option value=\"username\">$lang->select_username</option>\n";
	echo "<option value=\"email\">$lang->select_email</option>\n";
	echo "<option value=\"regdate\">$lang->select_reg_date</option>\n";
	echo "<option value=\"lastvisit\">$lang->select_last_visit</option>\n";
	echo "<option value=\"postnum\">$lang->select_posts</option>\n";
	echo "</select>";
	echo "<select name=\"searchop[order]\">\n";
	echo "<option value=\"asc\">$lang->sort_asc</option>\n";
	echo "<option value=\"desc\">$lang->sort_desc</option>\n";
	echo "</td>\n</tr>\n";
	makeinputcode($lang->results_per_page, "searchop[perpage]", "30");

	tablesubheader($lang->display_options);
	makeyesnocode($lang->display_username, "searchdisp[username]", "yes");
	makeyesnocode($lang->display_options_2, "searchdisp[ops]", "yes");
	makeyesnocode($lang->display_group, "searchdisp[usergroup]", "no");
	makeyesnocode($lang->display_email, "searchdisp[email]", "yes");
	makeyesnocode($lang->display_website, "searchdisp[website]", "no");
	makeyesnocode($lang->display_icq, "searchdisp[icq]", "no");
	makeyesnocode($lang->display_aim, "searchdisp[aim]", "no");
	makeyesnocode($lang->display_yahoo, "searchdisp[yahoo]", "no");
	makeyesnocode($lang->display_msn, "searchdisp[msn]", "no");
	makeyesnocode($lang->display_sig, "searchdisp[signature]", "no");
	makeyesnocode($lang->display_title, "searchdisp[usertitle]", "no");
	makeyesnocode($lang->display_reg_date, "searchdisp[regdate]", "yes");
	makeyesnocode($lang->display_last_visit, "searchdisp[lastvisit]", "yes");
	makeyesnocode($lang->display_num_posts, "searchdisp[postnum]", "yes");
	makeyesnocode($lang->display_birthday, "searchdisp[birthday]", "no");
	makeyesnocode($lang->display_regip, "searchdisp[regip]", "no");

	endtable();
	endform($lang->search, $lang->reset_button);
	
	cpfooter();
}
?>