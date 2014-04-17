<?php 
/*
 * @author: Tanveer Noman <tanveer.noman@gmail.com>
 * @return: strint
 * @description: This function will return hostname or IP address from D7 database
 */


/*
 * This function can works only in Drupal 7
 * The session_id() will do the trick. It can return current user's IP address or hostname by using session-id
 * whether user is a guest or registered member.
*/
function _getHostName(){
	$row = db_query("SELECT hostname FROM sessions WHERE sid = '".session_id()."'");
	$data = db_fetch_object($row );
	return $data->hostname;
}

