<?php

// channel_name is the name of your pear server 
// (you can find this information in your <name> attribut in pirum.xml)
$GLOBALS["respear"]["channel_name"]     = "serveur-pirum";

// pirum_path is the path where is located the pirum commande
$GLOBALS["respear"]["pirum_path"]       = "/usr/bin/pirum";

$GLOBALS["respear"]["pirum_baseurl"]    = "/";

// pear_server_path is the directory where your pear server is deployed
// (respear directory should be located here)
$GLOBALS["respear"]["pear_server_path"] = "/var/www/";

// tmp_path is a temporary directory
// (by default it is located into the respear directory)
$GLOBALS["respear"]["tmp_path"]         = $GLOBALS["respear"]["pear_server_path"]."respear/tmp/";

// log_path is the path of the directory where the logs are written
$GLOBALS["respear"]["log_path"]         = "/var/log/respear/";

// htpasswd_file is a file where username and password are written
// (used for the release POST and DELETE)
$GLOBALS["respear"]["htpasswd_file"]    = "/etc/apache2/htpasswd";

// htapikey_file is a file where logins and emails are stored
// (used to keep in memory the connection between emails and usernames)
$GLOBALS["respear"]["htapikey_file"]    = "/etc/apache2/htapikey";

// htblacklist_file is a file where blocked users are stored
$GLOBALS["respear"]["htblacklist_file"] = "/etc/apache2/htblacklist";

// stream_context is an array which contains the stream context when connecting to HTTP stream.
// Have a look to the doc for detailed parameters: http://www.php.net/manual/en/context.http.php
$GLOBALS["respear"]["stream_context"]   = array(
                                            //"proxy" => "tcp://proxyout.inist.fr:8080",
                                            //"request_fulluri" => "true"
                                          );

// overload generic configuration if necessary
$conf_local_file = dirname(__FILE__).'/config.local.php';
if (file_exists($conf_local_file)) {
    include $conf_local_file;
}
