<?php
/*
 * Respear
 *
 * (c) 2011 Thomas Picard
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */
 
include('../Respear.php');
include('../config.php');

$login = isset($_GET['login']) ? trim($_GET['login']) : '';
$email = isset($_GET['email']) ? trim($_GET['email']) : '';

if ($login === '') exit;
if (!preg_match(',^\w+$,', $login)) die('Bad Login. Try again.');
if (!preg_match(',.+@.+,', $email)) die('Bad Email. Try again.');

$htpasswd    = $GLOBALS['respear']['htpasswd_file'] ;
$htapikey    = $GLOBALS['respear']['htapikey_file'] ;
$htblacklist = $GLOBALS['respear']['htblacklist_file'];
touch($htpasswd);
touch($htapikey);
touch($htblacklist);

$pos = stripos(($f_apikey = file_get_contents($htapikey)),":".$email);
if ($pos){
	$tab = split("[:\n]",$f_apikey);
	foreach($tab as $k=>$v){
		if($v == $email){
			$name = $tab[$k-1];
			break;
		}
			
	}	
	$tab = split("[:\n]",file_get_contents($htpasswd));
	foreach($tab as $k=>$v){
		if($v == $name){
			echo "Your API Key is: ".$tab[$k+1];
			$uuid = $tab[$k+1];
			break;
		}
	}
	if(!isset($uuid)){
		$tab = split("[:\n]",file_get_contents($htblacklist));
		foreach($tab as $k=>$v){
			if($v == $name){
				echo "Your API Key is: ".$tab[$k+1];
				$uuid = $tab[$k+1];
				break;
			}
		}	
	}
	sendMail($email,$name,$uuid,"");
	die('Email already exists, You will receive a message with your informations".');
}
if (preg_match(',^'.preg_quote($login).':,m',file_get_contents($htpasswd))) die('Login already exists. Try again.');


function uuid() {
   
    // The field names refer to RFC 4122 section 4.1.2
    return sprintf('%04x%04x-%04x-%03x4-%04x-%04x%04x%04x',
        mt_rand(0, 65535), mt_rand(0, 65535), // 32 bits for "time_low"
        mt_rand(0, 65535), // 16 bits for "time_mid"
        mt_rand(0, 4095),  // 12 bits before the 0100 of (version) 4 for "time_hi_and_version"
        bindec(substr_replace(sprintf('%016b', mt_rand(0, 65535)), '01', 6, 2)),
        // 8 bits, the last two of which (positions 6 and 7) are 01, for "clk_seq_hi_res"
        // (hence, the 2nd hex digit after the 3rd hyphen can only be 1, 5, 9 or d)
        // 8 bits for "clk_seq_low"
        mt_rand(0, 65535), mt_rand(0, 65535), mt_rand(0, 65535) // 48 bits for "node" 
    ); 
}
$uuid = uuid();
$b64 = base64_encode($login.':'.$email);
//on recupere le -u 
$command = sprintf('/usr/bin/htpasswd -p -b %s %s %s',
    escapeshellarg($htpasswd),
    escapeshellarg($login),
    escapeshellarg($uuid)
);
exec($command);
//echo $uuid;

file_put_contents($htapikey, $login.':'.$email.PHP_EOL, FILE_APPEND);

sendMail($email,$login,$uuid,$b64);

echo 'Your APIKEY was generated. You will receive a message.';

function sendMail($email,$login,$uuid,$b64){
$time = date(DATE_RSS);
$subject = '[IAPI] Your personal APIKEY';
$message = <<<EOT

You have request a APIKEY that authorizate you to use the IAPI server.


 APIKEY generated at $time
-------------------------------------------------------------------------------
  E-Mail   : $email 
  Login    : $login  
  Password : $uuid 
-------------------------------------------------------------------------------
  Basic $b64
-------------------------------------------------------------------------------

In case of abuse, we reserve the right to disable this key.

EOT;
$headers = <<<EOT
From: jungle@intra.inist.fr
Reply-To: noreply@inist.fr
EOT;

mail($email, $subject, $message, $headers);
}

