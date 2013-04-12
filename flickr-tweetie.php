<?php
/**
* Flickr/Tweetie Bridge
* Copyright (c) 2009 Chris Morrell
*
* This program is free software: you can redistribute it and/or modify
* it under the terms of the GNU General Public License as published by
* the Free Software Foundation, either version 3 of the License, or
* (at your option) any later version.
*
* This program is distributed in the hope that it will be useful,
* but WITHOUT ANY WARRANTY; without even the implied warranty of
* MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
* GNU General Public License for more details.
*
* You should have received a copy of the GNU General Public License
* along with this program.  If not, see <http://www.gnu.org/licenses/>.
*
* @category  Galahad
* @package   Galahad
* @copyright Copyright (c) 2009 Chris Morrell <http://cmorrell.com>
* @license   GPL <http://www.gnu.org/licenses/>
* @version   0.2
*/

// -----------------------------------------------------------------------------
//   INSTRUCTIONS
// -----------------------------------------------------------------------------
/*
     First you need an API Key.  Go to:
     ----------------------------------

     http://www.flickr.com/services/api/keys/apply/

     And apply for a key (non-commercial should be fine).  Once you have an API
     Key and Secret, past them into the appropriate spots in the API Settings
     section and continue.     
 
     Once you have an API Key:
     -------------------------
 
     Upload this file to a server running PHP5, and navigate to the file's URL.
     You will be given instructions to authorize this application and then add
     a line of code to this file (in the API Settings section).

     Once you have done that, reload the page (BUT NOT BEFORE!) and you will be
     given one more line of code to add to the API Settings section.  Once that
     is done you're good to go.  Simply add this file's URL to Tweetie and give
     it a test--your file should upload and it should give you a nice, short
     http://flic.kr/ URL.

     Optional:
     ---------

     Settings:
       - FLICKR_TAGS:      These are the tags that are added for all files uploaded
       - FLICKR_TITLE:     All uploaded files are give this same title
       - FLICKR_HIDDEN:    Set this to 1 if you don't want Tweetie uploads to show
                           up in public searches.
       - USE_TWEETIE_MSG:  Set this to TRUE if you want to override your FLICKR_TITLE
                           with your twitter message text (available in tweetie 2.1).
       - TAG_WITH_HANDLE:  Set this to TRUE if you want to tag all twitter uploads
                           with the handle of the account that uploaded the file.  For
                           example, an image uploaded by me would be tagged @inxilpro.
*/
 
// -----------------------------------------------------------------------------
//   Application Settings
// -----------------------------------------------------------------------------
define('FLICKR_TAGS',		'twitter tweetie');
define('FLICKR_TITLE',		'Tweetie Upload');
define('FLICKR_HIDDEN',		0);

define('USE_TWEETIE_MSG',	true);
define('TAG_WITH_HANDLE',	true);

// -----------------------------------------------------------------------------
//   API Settings
// -----------------------------------------------------------------------------
define('API_KEY', '');
define('API_SECRET', '');

// -----------------------------------------------------------------------------
//   Change Log
// -----------------------------------------------------------------------------
/*
     0.2 - Added additional settings
     0.1 - Initial Release
*/

/**
* Galahad Flickr/Tweetie Bridge
*
* Provides a bridge between Tweetie 2 and Flickr for image uploads and
* URL shortening.  Will return a http://flic.kr/ URL if possible.
*
* Please note: This is a very early release of this file and may not
* work on all server configurations.  Error checking/compatibility testing
* will come next.
*
* @category   Galahad
* @package    Galahad
* @copyright  Copyright (c) 2009 Chris Morrell <http://cmorrell.com>
* @license    GPL <http://www.gnu.org/licenses/>
*/
class GalahadFlickrTweetieBridge
{
    public function route()
    {
	if (!defined('API_TOKEN') && !defined('API_FROB')) {
	    echo $this->_generateFrob();
	} else if (!defined('API_TOKEN')) {
	    echo $this->_generateToken();
	} else if (isset($_FILES['media'])) {
	    echo $this->_forwardPost();
	} else {
	    echo 'OK, you\'re all set!';
	    echo '<form enctype="multipart/form-data" action="" method="post">
		  <input type="file" name="media" /><input type="submit" value="Test" />
		  </form>';
	}
    }
    
    private function _forwardPost()
    {
	$url = 'http://api.flickr.com/services/upload/';
	
	$title = FLICKR_TITLE;
	if (USE_TWEETIE_MSG) {
		$title = $_POST['message'];
	}
	
	$tags = FLICKR_TAGS;
	if (TAG_WITH_HANDLE) {
		$tags .= ' @' . $_POST['username'];
	}
	
	$parameters = array(
	    'api_key' => API_KEY,
	    'auth_token' => API_TOKEN,
	    'tags' => $tags,
	    'title' => $title,
	);
	$parameters = $this->_flickrParams($parameters, false);
	$parameters['photo']  = "@{$_FILES['media']['tmp_name']}";
		
	$ch = curl_init($url);
	curl_setopt($ch, CURLOPT_POST, true);
	curl_setopt($ch, CURLOPT_POSTFIELDS, $parameters);
	curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
	curl_setopt($ch, CURLOPT_HEADER, false);
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
	$return = curl_exec($ch);
	
	unlink($_FILES['media']['tmp_name']);
	
	if (false !== strpos($return, 'stat="ok"')) {
	    preg_match('#<photoid>(.*?)</photoid>#i', $return, $matches);
	    $photoId = $matches[1];
	    
	    $info = $this->_flickrCall('photos.getInfo', array('photo_id' => $photoId));
	    if ('ok' == $info['stat']) {
		$url = $info['photo']['urls']['url'][0]['_content'];
		$url = $this->_getCanonical($url);
		return "<mediaurl>{$url}</mediaurl>";
	    }
	}
	
	return '<?xml version="1.0" encoding="UTF-8"?>
	    <rsp stat="fail">
	    <err code="9999" msg="Error" />
	</rsp>';
    }
    
    private function _generateFrob()
    {
	$frobResult = $this->_flickrCall('auth.getFrob');
	if ('ok' != $frobResult['stat']) {
	    die('API Key or Secret configured incorrectly.  Flickr said: ' . $frobResult['message']);
	}
	$frob = $frobResult['frob']['_content'];
	
	$parameters = array(
	    'api_key' => API_KEY,
	    'perms' => 'write',
	    'frob' => $frob,
	);
	$url = 'http://flickr.com/services/auth/?' . $this->_flickrParams($parameters);
	
	return "Please go to the following URL to authorize this application:\n\n{$url}\n\n" .
	       "Then, add the following line to this PHP file, and refresh this page:\n\ndefine('API_FROB', '{$frob}');";
    }
    
    private function _generateToken()
    {
	$tokenResult = $this->_flickrCall('auth.getToken', array('frob' => API_FROB));
	if ('ok' != $tokenResult['stat']) {
	    throw new Exception("Invalid frob.  Please delete the API_FROB and API_TOKEN definitions and start over.");
	}
	
	$perms = $tokenResult['auth']['perms']['_content'];
	if ('write' != $perms) {
	    throw new Exception('You must provide write permissions to this application');
	}
	
	$token = $tokenResult['auth']['token']['_content'];
	return "OK, almost done.  Please add the following line to this PHP file, and refresh:\n\ndefine('API_TOKEN', '{$token}');";
    }
    
    private function _flickrCall($method, $parameters = array())
    {
	$url  = 'http://api.flickr.com/services/rest/?';
	
	$parameters['format'] = 'php_serial';
	$parameters['api_key'] = API_KEY;
	$parameters['method'] = 'flickr.' . $method;
	
	$url .= $this->_flickrParams($parameters);
	
	// echo "URL: [{$url}]\n";
	$result = file_get_contents($url);
	if (false === $result) {
	    throw new Exception("Unable to fetch contents of {$url}");
	}
	
	$result = unserialize($result);
	return $result;
    }
    
    private function _flickrParams($parameters = array(), $convert = true)
    {
	$signature = API_SECRET;
	$url = '';
	
	ksort($parameters);
	foreach ($parameters as $key => $value) {
	    $url .= urlencode($key) . '=' . urlencode($value) . '&';
	    $signature .= "{$key}{$value}";
	}
	
	$signature = md5($signature);
	
	if ($convert) {
	    $url .= 'api_sig=' . $signature;
	    return $url;
	} else {
	    $parameters['api_sig'] = $signature;
	    return $parameters;
	}
    }
    
    private function _getCanonical($url)
    {
	$contents = file_get_contents($url);
	preg_match('#<link[^>]*rev="canonical"[^>]*href="(.*?)"#i', $contents, $matches);
	
	if (isset($matches[1])) {
	    return $matches[1];
	}
	
	return $url;
    }
}

// Run
$bridge = new GalahadFlickrTweetieBridge();
echo $bridge->route();



