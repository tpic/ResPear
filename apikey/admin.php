<?php
/*
 * Respear
 *
 * (c) 2011 Thomas Picard
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */
 
    // Insertion du fichier de configuration s'il existe
    $chemin_config = "../config.php";
    if (!file_exists($chemin_config)) {
        echo "<center><h3>Error - Configuration file missing</h3></center>";
        exit();
    }
    include_once($chemin_config);

    // Verification de sa structure et de son contenu
    if (! (isset($GLOBALS['respear']['htpasswd_file']) && isset($GLOBALS['respear']['htapikey_file']) 
        && isset($GLOBALS['respear']['htblacklist_file']))) {
        echo "<center><h3>Error - Configuration file malformed <br /> Variables missing </h3></center>";
        exit();
    }
    if ($GLOBALS['respear']['htpasswd_file'] == "" && $GLOBALS['respear']['htpasswd_file'] == "" && $GLOBALS['respear']['htpasswd_file'] == "" ) {
        echo "<center><h3>Error - Configuration file malformed <br /> Variables not initialized </h3></center>";
        exit();
    }

    // Switch sur les choix de l'utilisateur
    if (isset($_POST["action"])) {
       switch ($_POST["action"]) {
            case "bloque":
                bloqueUser($_POST["name"],$_POST["apikey"]);
                break;
            case "debloque":
                debloqueUser($_POST["name"],$_POST["apikey"]);
                break;
            case "del":
                deleteUser($_POST["name"],$_POST["mail"],$_POST["apikey"]);
                break;
        }
    }
    affichePage();

    function affichePage()
    {
        $fichierPWD  = file_get_contents($GLOBALS['respear']['htpasswd_file']);
        $fichierMail = file_get_contents($GLOBALS['respear']['htapikey_file']);
        $blackList   = file_get_contents($GLOBALS['respear']['htblacklist_file']);

        $tab_pwd  = split("[ \n]",$fichierPWD);
        $tab_bl   = split("[ \n]",$blackList);
        $tab_mail = split("[ :\n]",$fichierMail);

        $i = 0 ;
        // Pour chaque nom on va chercher son API key
        while ($i< count($tab_mail)) {
            if ($tab_mail[$i] != "") {
                $tab[$tab_mail[$i]] = $tab_mail[++$i];
                $i++;
            } else {
                $i += 1;
            }
        }

        echo '<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Strict//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-strict.dtd">
            <html xmlns="http://www.w3.org/1999/xhtml" xml:lang="fr" >
            <head>
                <title>Interface d\'ApiKey</title>
                <meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
                <link rel="stylesheet" href="apikey.css" type="text/css" />
            </head>
            <body>
                <center>
                    <h1> Respear - Interface d\'API Key </h1>
                    <TABLE BORDER="1">
                    <tr><th> <center>Login</center> </th> <th><center> Mail</center> </th> <th><center> Api Key</center> </th> <th></th>
                    </tr>';
        foreach ($tab_pwd as $k=>$v) {
            $t = split(":",$v);
            if ($t[0] != "") {
                echo "<tr> <th> $t[0] </th> <th>".$tab[$t[0]]."</th> <th> $t[1] </th> <th>";
                echo "<form method='post' action='admin.php'>
                       <input type='hidden' name='action' value='bloque' />
                       <input type='hidden' name='mail' value=".$tab[$t[0]]." />
                       <input type='hidden' name='name' value=".$t[0]." />
                       <input type='hidden' name='apikey' value=".$t[1]." />
                       <input type='image' value='submit' src='ko.png' title='block this user' />
                       </form>  ";
                echo "<form method='post' action='admin.php'>
                       <input type='hidden' name='action' value='del' />
                       <input type='hidden' name='mail' value=".$tab[$t[0]]." />
                       <input type='hidden' name='name' value=".$t[0]." />
                       <input type='hidden' name='apikey' value=".$t[1]." />
                       <input type='image' value='submit' src='del.png' title='Delete this user'/> 
                       </form> </th></tr>";
            }
        }
        echo "</TABLE><br /><br />
            <h2> Member in the blacklist</h2> 
            <TABLE BORDER=\"1\">
            <tr><th> <center>Login</center> </th> <th><center> Mail</center> </th> <th><center> Api Key</center> </th> <th></th> </tr>";
        foreach ($tab_bl as $k=>$v) {
            $t = split(":",$v);
            if ($t[0] != "") {
                echo "<tr> <th> $t[0] </th> <th>".$tab[$t[0]]."</th> <th> $t[1] </th> <th>
                    <form method='post' action='admin.php'> 
                    <input type='hidden' name='action' value='debloque' />
                    <input type='hidden' name='mail' value=".$tab[$t[0]]." />
                    <input type='hidden' name='name' value=".$t[0]." />
                    <input type='hidden' name='apikey' value=".$t[1]." />
                    <input type='image' value='submit'  src='ok.png' title='unblock this user' /> 
                    </form>  ";
                echo "<form method='post' action='admin.php?action=del&mail=".$tab[$t[0]]."&name=".$t[0]."&apikey=".$t[1]."'>
                        <input type='hidden' name='action' value='del' />
                        <input type='hidden' name='mail' value=".$tab[$t[0]]." />
                        <input type='hidden' name='name' value=".$t[0]." />
                        <input type='hidden' name='apikey' value=".$t[1]." />
                        <input type='image' value='submit' src='del.png' title='Delete this user'/> 
                      </form> </th></tr>";
            }
        }

        echo "</TABLE></center></body></html>";
    }


    function bloqueUser($name,$apikey)
    {
        if (!file_put_contents($GLOBALS['respear']['htblacklist_file'],$name.":".$apikey."\n",FILE_APPEND)) {
            echo "une erreur est survenue";
        }
        // On l'enleve du fichier htpasswd
        $fichierPWD  = split("[\n]",file_get_contents($GLOBALS['respear']['htpasswd_file']));
        $l           = array();
        foreach ($fichierPWD as $k=>$v) {
            if (!strstr($v,"$name:$apikey") && $v != "") {
                $l[] = $v."\n";
            }
        }
        file_put_contents($GLOBALS['respear']['htpasswd_file'],$l);
    }

    function debloqueUser($name,$apikey)
    {
        $blackList = file_get_contents($GLOBALS['respear']['htblacklist_file']);
        $list      = split("[\n]", $blackList);
        $l         = array();
        // On recopie les membres de la black list dans un tab temporaire qu'on ecrit dans le fichier
        foreach ($list as $k=>$v) {
            if($v!= "" && $v != "$name:$apikey")
                $l[] = $v."\n";
        }

        file_put_contents($GLOBALS['respear']['htblacklist_file'],$l);
        file_put_contents($GLOBALS['respear']['htpasswd_file'],$name.":".$apikey."\n",FILE_APPEND);
    }

    function deleteUser($name,$mail,$apikey)
    {
        $fichierPWD  = split("[\n]",file_get_contents($GLOBALS['respear']['htpasswd_file']));
        $fichierMail = split("[\n]",file_get_contents($GLOBALS['respear']['htapikey_file']));
        $fichierBl   = split("[\n]",file_get_contents($GLOBALS['respear']['htblacklist_file']));

        // On l'enleve de la blackList s'il y est
        $l = array();
        foreach ($fichierBl as $k=>$v) {
            if ($v!= "" && $v != "$name:$apikey") {
                $l[] = $v."\n";
            }
        }
        file_put_contents($GLOBALS['respear']['htblacklist_file'],$l);

        // On l'enleve du fichier htpasswd
        $l = array();
        foreach($fichierPWD as $k=>$v){
            if (!strstr($v,"$name:$apikey") && $v != "") {
                $l[] = $v."\n";
            }
        }
        file_put_contents($GLOBALS['respear']['htpasswd_file'],$l);

        // On l'enleve du fichier htapikey
        $l = array();
        foreach ($fichierMail as $k=>$v) {
            if (!strstr($v,"$name:$mail") && $v != "") {
                $l[] = $v."\n";
            }
        }
        file_put_contents($GLOBALS['respear']['htapikey_file'],$l);
    }

?>
