<?php
error_reporting ( E_ERROR | E_PARSE );
require __DIR__ . '/vendor/autoload.php';
define ( 'APPLICATION_NAME', 'Google Calendar API PHP Quickstart' );
define ( 'CREDENTIALS_PATH', '~/.credentials/calendar-php-quickstart.json' );
define ( 'CLIENT_SECRET_PATH', dirname(__DIR__).'/.secret/client_secret_gcalendar.json' );
define ( 'SCOPES', implode ( ' ', array (
		Google_Service_Calendar::CALENDAR 
) ) );
//header ( 'Content-type: text/calendar; charset=utf-8' );
//header ( 'Content-Disposition: attachment; filename=runtastic.ics' );
include ("./../.secret/runtastic.pass.php");
include ("./../.secret/id_calendar_sport.php");
include ("class.runtastic.php");

//**$debug = true;//**

/**
 * Returns an authorized API client.
 *
 * @return Google_Client the authorized client object
 *        
 */
function getClient() {
	//echo "1;".CLIENT_SECRET_PATH.'<br>';
	//echo "2;".dirname(__DIR__).'<br>';
	//echo "3;".dirname(__DIR__."/../").'<br>';
	$client = new Google_Client ();
	$client->setApplicationName ( APPLICATION_NAME );
	$client->setScopes ( SCOPES );
	$client->setAuthConfigFile ( CLIENT_SECRET_PATH );
	$client->setAccessType ( 'offline' );
	// Load previously authorized credentials from a file.
	$credentialsPath = expandHomeDirectory ( CREDENTIALS_PATH );
	if (file_exists ( $credentialsPath )) {
		$accessToken = file_get_contents ( $credentialsPath );
	} else {
		// Request authorization from the user.
		$authUrl = $client->createAuthUrl ();
		printf ( "Open the following link in your browser:\n%s\n", $authUrl );
		print 'Enter verification code: ';
		$authCode = trim ( fgets ( STDIN ) );
		// Exchange authorization code for an access token.
		$accessToken = $client->authenticate ( $authCode );
		// Store the credentials to disk.
		if (! file_exists ( dirname ( $credentialsPath ) )) {
			mkdir ( dirname ( $credentialsPath ), 0700, true );
		}
		file_put_contents ( $credentialsPath, $accessToken );
		printf ( "Credentials saved to %s\n", $credentialsPath );
	}
	$client->setAccessToken ( $accessToken );
	// Refresh the token if it's expired.
	if ($client->isAccessTokenExpired ()) {
		$client->refreshToken ( $client->getRefreshToken () );
		file_put_contents ( $credentialsPath, $client->getAccessToken () );
	}
	return $client;
}
/**
 * Expands the home directory alias '~' to the full path.
 *
 * @param string $path
 *        	the path to expand.
 * @return string the expanded path.
 *        
 */
function expandHomeDirectory($path) {
	$homeDirectory = getenv ( 'HOME' );
	if (empty ( $homeDirectory )) {
		$homeDirectory = getenv ( "HOMEDRIVE" ) . getenv ( "HOMEPATH" );
	}
	return str_replace ( '~', realpath ( $homeDirectory ), $path );
}
function dateToCal($timestamp) {
	return date ( 'Ymd\THis', $timestamp );
}
function escapeString($string) {
	return preg_replace ( '/([\,;])/', '\\\$1', $string );
}
function echoruntastic($myRuntasticA, $service, $calendarId) {
	global $content, $count, $countaj ,$debug;
	foreach ( $myRuntasticA as $Activities ) {
		$txt = " distance:" . $Activities->distance . " m \\n" . " pace:" . (round ( $Activities->pace * 100 ) / 100) . " min\\km \\n" . " speed:" . (round ( $Activities->speed * 100 ) / 100) . " km\\h \\n" . " kcal:" . $Activities->kcal . " kcal \\n" . " heartrate_avg:" . $Activities->heartrate_avg . " bpm \\n" . " heartrate_max:" . $Activities->heartrate_max . " bpm \\n" . " elevation_gain:" . $Activities->elevation_gain . " m \\n" . " elevation_loss:" . $Activities->elevation_loss . " m \\n" . " surface:" . $Activities->surface . " \\n" . " weather:" . $Activities->weather . " \\n" . " feeling:" . $Activities->feeling . " \\n" . " URL: <a href=\"https://www.runtastic.com" . $Activities->page_url . "\">Activities page_url</a> \\n";
		if ($debug) {echo  "txt=$txt\n";}
		$datest = date_create ( $Activities->date->year . "-" . $Activities->date->month . "-" . $Activities->date->day . " " . $Activities->date->hour . ":" . $Activities->date->minutes . ":" . $Activities->date->seconds );
		$datend = date_create ( $Activities->date->year . "-" . $Activities->date->month . "-" . $Activities->date->day . " " . $Activities->date->hour . ":" . $Activities->date->minutes . ":" . $Activities->date->seconds );
		date_add ( $datend, new DateInterval ( 'PT' . round ( $Activities->duration / 1000 ) . 'S' ) );
		$content .= "BEGIN:VEVENT" . "\n";
		$content .= "UID:" . escapeString ( $Activities->id ) . "\n";
		$content .= "SUMMARY:" . escapeString ( $Activities->type ) . "\n";
		$content .= "URL;VALUE=URI:https://www.runtastic.com" . $Activities->page_url . "\n";
		$content .= "DESCRIPTION;ENCODING=QUOTED-PRINTABLE:" . escapeString ( $txt ) . "\n";
		$content .= "DTSTAMP:" . dateToCal ( time () ) . "\n";
		$content .= "DTSTART:" . date_format ( $datest, "Ymd\THis" ) . "\n";
		$content .= "DTEND:" . date_format ( $datend, "Ymd\THis" ) . "\n";
		$content .= "ATTACH;FILENAME=map.jpg;FMTTYPE=image/jpeg:" . $Activities->map_url . "\n";
		$content .= "END:VEVENT" . "\n";
		$count ++;
		try {
			$event = new Google_Service_Calendar_Event ( array (
					'summary' => escapeString ( $Activities->type ),
					'description' => str_replace ( "\\n", "\n", escapeString ( $txt ) ),
					'start' => array (
							'dateTime' => date_format ( $datest, "Y-m-d\TH:i:s" ),
							'timeZone' => 'Europe/Paris' 
					),
					'end' => array (
							'dateTime' => date_format ( $datend, "Y-m-d\TH:i:s" ),
							'timeZone' => 'Europe/Paris' 
					),
					'htmlLink' => 'https://www.runtastic.com' . $Activities->page_url,
					'iCalUID' => escapeString ( 'R2G' . $Activities->id ) 
			) );
			$event = $service->events->insert ( $calendarId, $event );
			$countaj ++;
		} catch ( Exception $e ) {
			if ($debug) {echo  $e->getCode () . " --- " . $e->getMessage () ."\n";}
			if ($e->getCode () != 409) {
				echo $e->getCode () . " --- " . $e->getMessage ();
			}
		}
	}
}


$runtastic = new Runtastic ();
$runtastic->setUsername ( $runtastic_key );
$runtastic->setPassword ( $runtastic_secret);
$runtastic->setTimeout ( 20 );
$calendarId = $id_calendar_sport;
$client = getClient ();
$service = new Google_Service_Calendar ( $client );
unlink ( "runtastic.ics" );
$content = "";
$count = 0;
$countaj = 0;
if ($runtastic->login ()) {
	if ($debug) {echo  "runtastic-login\n";}
	$content .= "BEGIN:VCALENDAR" . "\n";
	$content .= "VERSION:2.0" . "\n";
	$content .= "PRODID:/source_code/Runstatic2Gagenda/NONSGML v1.0//EN" . "\n";
	$content .= "CALSCALE:GREGORIAN" . "\n";
	// echoruntastic ( $runtastic->getActivities ( null, null, $ym0 ) ,$service,$calendarId );
	if ($debug) {echo   (Date ( "W", strtotime ( "-0 weeks" )))." ".(Date ( "Y", strtotime ( "-0 weeks" )))."\n";}
	if ($debug) {echo   (Date ( "W", strtotime ( "-1 weeks" )))." ".(Date ( "Y", strtotime ( "-1 weeks" )))."\n";}
	if ((Date ( "W", strtotime ( "-0 weeks" ))) > 52 ){
		echoruntastic ( $runtastic->getActivities ( null , null, Date ( "Y", strtotime ( "-0 weeks" ) ) ), $service, $calendarId );
	}
	echoruntastic ( $runtastic->getActivities ( Date ( "W", strtotime ( "-0 weeks" ) ), null, Date ( "Y", strtotime ( "-0 weeks" ) ) ), $service, $calendarId );
	echoruntastic ( $runtastic->getActivities ( Date ( "W", strtotime ( "-1 weeks" ) ), null, Date ( "Y", strtotime ( "-1 weeks" ) ) ), $service, $calendarId );
	$content .= "END:VCALENDAR" . "\n";
	file_put_contents ( "runtastic.ics", $content );
	if ($debug) {echo  "countaj=$countaj\n";}
	if ($countaj > 0) {
		echo $countaj . " evenements ajouté , sur " . $count . " evenements recuperer dans runtastic ";
	}
} else {
	echo "runtastic->login dont work\n";// . $runtastic->http_code .  "*";
	echo "<pre>";print_r($runtastic);echo "</pre>\n";
}
?>