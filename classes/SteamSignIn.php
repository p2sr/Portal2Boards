<?php
/**
*
* @package Steam Community API
* @copyright (c) 2010 ichimonai.com
* @license http://opensource.org/licenses/mit-license.php The MIT License
*
*/

class SteamSignIn
{
	const STEAM_LOGIN = 'https://steamcommunity.com/openid/login/';

    static $loggedInUser;

	/**
	* Get the URL to sign into steam
	*
	* @param string host The server hostname.
	* @param mixed returnTo Callback path for Steam to return to e.g. /login
	* @param bool useAmp Use &amp; in the URL, true; or just &, false. 
	* @return string The string to go in the URL
	*/
	public static function genUrl($host, $returnTo, $useAmp = true)
	{
		$returnTo = 'https://' . $host . $returnTo;

		$params = array(
			'openid.ns'			=> 'http://specs.openid.net/auth/2.0',
			'openid.mode'		=> 'checkid_setup',
			'openid.return_to'	=> $returnTo,
			'openid.realm'		=> 'https://' . $host,
			'openid.identity'	=> 'http://specs.openid.net/auth/2.0/identifier_select',
			'openid.claimed_id'	=> 'http://specs.openid.net/auth/2.0/identifier_select',
		);
		
		$sep = ($useAmp) ? '&amp;' : '&';
		return self::STEAM_LOGIN . '?' . http_build_query($params, '', $sep);
	}

	public static function isLoggedIn($id) {
	    return (isset(SteamSignIn::$loggedInUser)) ? (SteamSignIn::$loggedInUser->profileNumber == $id) : false;
    }

    public static function loggedInUserIsAdmin()
    {
        return (isset(SteamSignIn::$loggedInUser)) ? SteamSignIn::$loggedInUser->isAdmin() : false;
    }

    public static function hasProfilePrivileges($userProfile) {
        return SteamSignIn::isLoggedIn($userProfile) || SteamSignIn::loggedInUserIsAdmin();
    }
	
	/**
	* Validate the incoming data
	*
	* @return string Returns the SteamID64 if successful or empty string on failure
	*/
	public static function validate()
	{
		// Start off with some basic params
		$params = array(
			'openid.assoc_handle'	=> $_GET['openid_assoc_handle'],
			'openid.signed'			=> $_GET['openid_signed'],
			'openid.sig'			=> $_GET['openid_sig'],
			'openid.ns'				=> 'http://specs.openid.net/auth/2.0',
		);

		// Get all the params that were sent back and resend them for validation
		$signed = explode(',', $_GET['openid_signed']);
		foreach($signed as $item)
		{
			$val = $_GET['openid_' . str_replace('.', '_', $item)];
			$params['openid.' . $item] = get_magic_quotes_gpc() ? stripslashes($val) : $val;
		}

		// Finally, add the all important mode.
		$params['openid.mode'] = 'check_authentication';

		// Stored to send a Content-Length header
		$data =  http_build_query($params);

		$context = stream_context_create(array(
			'http' => array(
				'method'  => 'POST',
				'header'  => "Accept-Language: en-US,en\r\n" .
					"Content-Type: application/x-www-form-urlencoded\r\n" .
					"Content-Length: " . strlen($data) . "\r\n",
				'content' => $data,
			),
		));

		$result = file_get_contents(self::STEAM_LOGIN, false, $context);

		// Validate whether it's true and if we have a good ID
		preg_match("#^https://steamcommunity.com/openid/id/([0-9]{17,25})#", $_GET['openid_claimed_id'], $matches);
		$steamID64 = is_numeric($matches[1]) ? $matches[1] : 0;

		// Return our final value
		return preg_match("#is_valid\s*:\s*true#i", $result) == 1 ? $steamID64 : '';
	}

}


?>
