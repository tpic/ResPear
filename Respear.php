<?php

/**
 * Respear
 *
 * (c) 2011 Thomas Picard
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */
 
include_once('RespearStatus.php');

class Respear 
{
	public $channel_name     = '';
    public $pirum_path       = '';
    public $pear_server_path = '';		
	public $tmp_path         = '';
    public $log_path         = '';
    public $htpasswd_file    = '';
    public $htapikey_file    = '';
	public $htblacklist_file = '';
	public $stream_context   = '';
	
		 
    public function __construct($args = array())
    {
        foreach ($args as $k=>$v) {
            $this->$k = $v;
        }
    }
    
    /**
     * Sends the header HTTP and the text of the reply
     */ 	
    function send_respear_status($h,$type='text/plain')
    {
        $h->add("content-type",$type);
        $instance = RespearStatus::getInstance();
        $h->send($instance->getStatus());
        $this->show_message($instance->getContent());
    }
    
    /**
     * List the file present in the package
     */
    function show_package($p,$h)
    {
        //Check configuration
        $conf = $this->check_config($p,$h);
        if ($conf == '') return '';

        $name    = (string)$p->__sections[0];
        $release = (string)$p->__sections[1];
        $path    = (string)$p->__sections[2];
        $tgz     = $this->pear_server_path.'get/'.$name.'-'.$release.'.tgz';
        
        // On va dans GET voir si le packet existe
        if (!file_exists($tgz)) {
            RespearStatus::getInstance()->addStatus(4040);
            $this->send_respear_status($h);
            return;
        }    

        if ($path == 'index') {
            $path = '';
        }

        // On liste le contenu de l'archive avec la commande tar --list        
        exec('tar --list --file='.$tgz.' '.$name.'-'.$release.'/'.$path,$msg,$status);

        // Création du flux ATOM
        include_once("ATOMWriter.php");
        
        $xmlWriter = new XMLWriter();
        $xmlWriter->openUri('php://output');
        $xmlWriter->setIndent(true);
        
        //Titre du flux ATOM
        $f = new ATOMWriter($xmlWriter, true);
        $f->startFeed('urn:respear')
          ->writeStartIndex(1)
          ->writeItemsPerPage(10)
          ->writeTotalResults(100)
          ->writeTitle('List of the file in Pear package '.$name.'-'.$release.'/'.$path.' :');
        
        // Affichage de package.xml si on est a la racine
        if ($path == '') {
            $f->startEntry("urn:respear:$name-$release/package.xml")
              ->writeTitle("package.xml")
              ->writeLink("package.xml")
              ->endEntry();
            $f->flush();
        }
        
        // On boucle sur la liste et on affiche
        if (empty($msg)) {
            $f->startEntry("urn:respear:$name-$release")
                       ->writeTitle('We are sorry they are an error in the script. Please contact developpeur')
                       ->endEntry();
                     $f->flush();
        }
        foreach ($msg as $v) {
            if (preg_match("|^$name-$release/$path|",$v,$tab)) {
                $line = preg_replace("|^$name-$release/$path|",'',$v);
                if ($line != '' && $line != '/') {
                     $f->startEntry("urn:respear:$name-$release/$v")
                       ->writeTitle($line)
                       ->writeLink($line)
                       ->endEntry();
                     $f->flush();
                }
            } else {
                 $f->startEntry("urn:respear:$name-$release/$v")
                   ->writeTitle($v)
                   ->writeLink($v)
                   ->endEntry();
                 $f->flush();
            }
        }
        $f->endFeed();
        $f->flush();  
        RespearStatus::getInstance()->addStatus(200);
        $this->send_respear_status($h);   
    }
    
    /**
     * Sends a file which is a pear package
     */
    function get_file($p,$h)
    {
        $path = (string)$p->__sections[2]; 
        if (substr($path,strlen($path)-1)== '/') {
            $this->show_package($p,$h);
            return;
        }
        
        //Check configuration
        $conf = $this->check_config($p,$h);
        if ($conf == '') return;
        
        $name    = (string)$p->__sections[0];
        $release = (string)$p->__sections[1];

        // On va dans GET voir si le packet existe
        if (!file_exists($this->pear_server_path.'get/'.$name.'-'.$release.'.tgz')) {     
            RespearStatus::getInstance()->addStatus(4040);
            $this->send_respear_status($h);
            return;
        }    
        
        // Création d'un dossier temporaire
        $dossier = "dossier_".rand(100,900)."/";
        exec('mkdir '.$this->tmp_path.$dossier,$msg,$statut);
        if (!file_exists($this->tmp_path.$dossier) || $statut > 0) {       
            $this->write_server_log(RespearStatus::getInstance()->getMessage(5002).'  from get_file',"500");
            RespearStatus::getInstance()->addStatus(5002);
            $this->send_respear_status($h);
            return;
        }
        chdir($this->tmp_path.$dossier);
        
        $tgz       = $this->pear_server_path.'get/'.$name.'-'.$release.'.tgz';
        $file_name = substr($path,strrpos($path,'/'));
        
        // Si on trouve un / ca signifie qu'on est pas dans le repertoire racine donc faut rentrer dans le dossier nom-release
      
        if ( $path != 'package.xml')  {
            $path = $name.'-'.$release.'/'.$path;            
        }
        
        // On extrait le fichier
        exec('tar --extract --file='.$tgz.' '.$path,$msg,$status);  
        if ($status > 0 || !file_exists($this->tmp_path.$dossier.$path) ) {
            RespearStatus::getInstance()->addStatus(4041);
            $this->send_respear_status($h);
            exec('rm -rf '.$this->tmp_path.$dossier);
            return;
        }
        
        // On recupere son MIME-Type
        require_once 'MIME/Type.php';                 
        $type_file = trim(MIME_Type::autoDetect($this->tmp_path.$dossier.$path));
        
        // On recupere le contenu
        $return = @file_get_contents($this->tmp_path.$dossier.$path);
        
        // On precise le MIME-Type
        $h->add("content-type",$type_file);
        file_put_contents('php://output', $return);
        
        // On ecrit le log, on supprime le fichier
        RespearStatus::getInstance()->addStatus(200);
        //$this->send_respear_status($h);  
        //$this->write_log(" - ",$path,"200","-");
        exec('rm -rf '.$this->tmp_path.$dossier);

    }

    /**
     * Remove a or several pear package from the server PIRUM
     * Command line with Curl: curl -u login:ApiKey -X DELETE http://my_server_pirum/respear/package_name/release/
     * Command line with Curl for delete several package: curl -u login:ApiKey -X DELETE http://my_server_pirum/respear/package_name/
     */
    function remove_pear_package($p, $h)
    {
        //Check configuration
        $conf = $this->check_config($p,$h);
        if ($conf == '') return;
        
        // Verification des  de login/API Key, 
        $login = $this->check_autorization($p,$h);
        if ($login == '') return;

        // On recupere le nom du package a supprimer
        $name    = (string)$p->__sections[0];
        $release = (string)$p->__sections[1];

        $noms = array();
        if ( $release == "index" ) {
	        // On veut supprimer toute une famille de package
	        $rep = $this->pear_server_path."get/";
	        $dir = opendir($this->pear_server_path."get/");
	        while ($f = readdir($dir)) {                
		        if (is_file($rep.$f)) {
			        if (eregi("^".$name."-[0-9.]+.tgz$",$f)) {
				        $noms[] = $f;
			        }
		        }
            }
        } else {
	        $noms[] = $name."-".$release.".tgz";
        }

        if (count($noms) == 0 ){
            $this->write_log($login,$name.'/'.$release.'/',"404","-");
            RespearStatus::getInstance()->addStatus(4040);
            $this->send_respear_status($h);
            return;
        }
        // Pour chaque package on fait une verif et supprime
        foreach ($noms as $nom) {
            // Vérification que le package existe
            if (!file_exists($this->pear_server_path."get/".$nom)) {
                $this->write_log($login,$name.'/'.substr($nom,strpos($nom,'-')+1,5).'/',"404","-");
                RespearStatus::getInstance()->addStatus(4040);
                $this->send_respear_status($h);
                return;
            }
        
            // On le copie avant la suppréssion par Pirum
            exec('cp '.$this->pear_server_path.'/get/'.$nom.' '.$this->pear_server_path.'/respear/attic/'.time().'_'.$nom ,$msg,$statut); 
            if ($statut > 0) {
                $this->write_log($login,$name.'/'.substr($nom,strpos($nom,'-')+1,5).'/',"500","-");
                RespearStatus::getInstance()->addStatus(5004);
                $this->send_respear_status($h);
                return;
            }    

            // On execute la commande Pirum pour la suppréssion d'un packet sur le serveur
            exec($this->pirum_path.' remove '.$this->pear_server_path." ".$nom,$msg,$statut); 
            if ($statut > 0) {
                $this->write_log($login,$name.'/'.substr($nom,strpos($nom,'-')+1,5).'/',"400","-");
                RespearStatus::getInstance()->addStatus(4000,$msg);
                $this->send_respear_status($h);
                return;
            } else {
                $this->write_log($login,$name.'/'.substr($nom,strpos($nom,'-')+1,5).'/',"200","-");
                RespearStatus::getInstance()->addStatus(2001,array('Name: '.$name.'/'.substr($nom,strpos($nom,'-')+1,5).'/'));
            }
        }
        // La suppréssion a eu lieu avec succès
        $this->send_respear_status($h);
    } 

    /**
     * Add a pear package in the server PIRUM depending on his type.
     * For TGZ type with curl: cat my_package.tgz | curl -u login:ApiKey -X POST --data-binary @- http://my_server_pirum/respear/
     * For TAR type with curl: cat my_package.tar | curl -u login:ApiKey -X POST --data-binary @- http://my_server_pirum/respear/
     * For ZIP type with curl: cat my_package.zip | curl -u login:ApiKey -X POST --data-binary @- http://my_server_pirum/respear/
     * For URL type: curl -u login:ApiKey -X POST -H "X_URL: http://the_url_toward_the_xml_of_pear_package" http://my_server_pirum/respear/
     */
    function add_pear_package($p,$h)
    {
    
        //Check configuration
        $conf = $this->check_config($p,$h);
        if ($conf == '') return;
        
        // Verification de l'user mdp
        $login = $this->check_autorization($p,$h);
        if ($login === '') return;               
        
        // Création d'un dossier temporaire
        $dossier = "dossier_".rand(100,900)."/";
        exec('mkdir '.$this->tmp_path.$dossier,$msg,$statut);
        if (!file_exists($this->tmp_path.$dossier) || $statut > 0 ) {       
            $this->write_server_log(RespearStatus::getInstance()->getMessage(5002).' from add_pear_package',"500");
            RespearStatus::getInstance()->addStatus(5002);
            $this->send_respear_status($h);
            return;
        }      

        $tab = array();
        if ( isset($_SERVER['HTTP_X_URL']) ) {
            // L'ajout se fait via une URL                    
            $tab = $this->prepare_pear_package_from_url($p,$h,$login,$_SERVER['HTTP_X_URL'],$dossier);
            if ($tab === '') {
                exec("rm -rf ".$this->tmp_path.$dossier);
                return;
            }
        } else {
            // Recuperation de l'argument
            $var = file_get_contents('php://input');
            if (!$var) {
                $this->write_log($login,'-',"404","-");
                RespearStatus::getInstance()->addStatus(4002);
                $this->send_respear_status($h);
                exec("rm -rf ".$this->tmp_path.$dossier);
                return;       
            }
            
            // Ecriture du binaire dans le temporaire   
            $ecriture = file_put_contents($this->tmp_path.$dossier."tmp.data",$var);  
            if (!file_exists($this->tmp_path.$dossier."tmp.data") || !$ecriture) {
                $this->write_server_log(RespearStatus::getInstance()->getMessage(5002).' from add_pear_package',"500");
                RespearStatus::getInstance()->addStatus(5002);
                $this->send_respear_status($h);
                exec("rm -rf ".$this->tmp_path.$dossier);
                return;
            }
            // Detection du mime type 
            require_once 'MIME/Type.php';                 
            switch (trim(MIME_Type::autoDetect($this->tmp_path.$dossier."tmp.data"))) {
                case "application/zip":
                    $tab = $this->prepare_pear_package_from_zip($p,$h,$login,$this->tmp_path.$dossier);
                    if ($tab === '') return;
                    break;
                case "application/x-tar":
                    $tab = $this->prepare_pear_package_from_tar($p,$h,$login,$this->tmp_path.$dossier);
                    if ($tab === '') return;
                    break;
                case "application/x-gzip":
                    $tab = $this->prepare_pear_package_from_tgz($p,$h,$login,$this->tmp_path.$dossier);
                    if ($tab === '') return;
                    break;
                default :
                    $this->write_log($login,'-',"415","-");
                    RespearStatus::getInstance()->addStatus(4157);
                    $this->send_respear_status($h);
                    return;
            }
        }
            		
        // On va voir dans le depot du serveur si le package existe
        if (file_exists($this->pear_server_path."get/".$tab['name'].".tgz")) {
            $this->write_log($login,$tab['name'],"409","-");
            RespearStatus::getInstance()->addStatus(4090);
            $this->send_respear_status($h);
            exec("rm -rf ".$this->tmp_path.$dossier);
            return;
        }
        
        // On execute la commande Pirum pour l'ajout du packet sur le serveur
        exec($this->pirum_path.' add '.$this->pear_server_path.' '.$tab['tgz_path'],$msg,$statut);
        if ($statut > 0) {
            $this->write_log($login,$tab['name'],"400","-");
            RespearStatus::getInstance()->addStatus(4000,preg_grep("/ERROR/",$msg));      

            exec("rm -rf ".$this->tmp_path.$dossier);
            // Supression des tgz s'il y a une erreur (bizarre qu'il ne le fasse pas lui meme...)        
            exec("rm ".$this->pear_server_path."get/".$tab['name'].".tgz");
            exec("rm ".$this->pear_server_path."get/".$tab['name'].".tar");
            $this->send_respear_status($h);
            return;
        }
        
        // L'ajout a eu lieu avec succès    
        $this->write_log($login,$tab['name'],"200","-");
        RespearStatus::getInstance()->addStatus(2000,array('Name: '.$tab['name']));
        $this->send_respear_status($h);
        exec('rm -rf '.$this->tmp_path.$dossier);
        return;
    }

    /**
     * Renames the pear package type to TGZ and extract this tgz
     * @return The result of prepare_pear_package (the name and the tgz path of the pear package)
     */
    function prepare_pear_package_from_tgz($p,$h,$login,$dossier_tmp)
    {
        // On renomme le fichier tmp en tgz
        exec("mv $dossier_tmp"."tmp.data $dossier_tmp"."tmp.tgz",$msg,$status);
        if ($status > 0) {
            $this->write_log($login,'-','415',"-");
            RespearStatus::getInstance()->addStatus(5008);
            $this->send_respear_status($h);    
            exec("rm -rf $dossier_tmp");
            return '';
        }
        
        // On extrait le tgz
        chdir($dossier_tmp);
        exec("tar xvzf $dossier_tmp"."tmp.tgz",$msg,$status);
        if ($status > 0) {
            $this->write_log($login,'-','415',"-");
            RespearStatus::getInstance()->addStatus(4150); 
            $this->send_respear_status($h);    
            exec("rm -rf $dossier_tmp");
            return '';     
        }
       
       return $this->prepare_pear_package($p,$h,$login,$dossier_tmp);
    }
    
    /**
     * Renames the pear package type to TAR and extract this tar
     * @return The result of prepare_pear_package (the name and the tgz path of the pear package)
     */
    function prepare_pear_package_from_tar($p,$h,$login,$dossier_tmp)
    {
        // On renomme le fichier tmp en tar
        exec("mv $dossier_tmp"."tmp.data $dossier_tmp"."tmp.tar",$msg,$status);
        if ($status > 0) {
            $this->write_log($login,'-','415',"-");
            RespearStatus::getInstance()->addStatus(5008); 
            $this->send_respear_status($h);    
            exec("rm -rf $dossier_tmp");
            return '';
        }
        
        // On decompression du tar
        chdir($dossier_tmp);
        exec("tar xvf $dossier_tmp"."tmp.data ",$msg,$status);
        if ($status > 0) {
            $this->write_log($login,'-','415',"-");
            RespearStatus::getInstance()->addStatus(4150); 
            $this->send_respear_status($h);    
            exec("rm -rf $dossier_tmp");
            return '';   
        }
        
        return $this->prepare_pear_package($p,$h,$login,$dossier_tmp);
    }

    /**
     * Renames the pear package type to TGZ and extract this tgz
     * @return The result of prepare_pear_package (the name and the tgz path of the pear package)
     */
    function prepare_pear_package_from_zip($p,$h,$login,$dossier_tmp)
    {
        // On renomme le fichier tmp en zip
        exec("mv $dossier_tmp"."tmp.data $dossier_tmp"."tmp.zip",$msg,$status);
        if ($status > 0) {
            $this->write_log($login,'-','415',"-");
            RespearStatus::getInstance()->addStatus(5008); 
            $this->send_respear_status($h);    
            exec("rm -rf $dossier_tmp");
            return '';
        }
        
        // On extrait le zip
        exec("unzip $dossier_tmp"."tmp.zip -d $dossier_tmp",$msg,$status);
        if ($status > 0) {
            $this->write_log($login,'-','415',"-");
            RespearStatus::getInstance()->addStatus(4150); 
            $this->send_respear_status($h);    
            exec("rm -rf $dossier_tmp");
            return '';       
        }
       
       return $this->prepare_pear_package($p,$h,$login,$dossier_tmp);
    }

    /**
     * Download the XML file. Treats this XML and calls builder_pear architecture.
     * @return The result of prepare_pear_package (the name and the tgz path of the pear package)
     */
    function prepare_pear_package_from_url($p,$h,$login,$var,$dossier_tmp)
    {
        $protected = false;
        if (isset($_SERVER['HTTP_X_URL_AUTH']) ) {
            $protected = true;
        }
             
        // Configuration du proxy
        $ct_params = array();
        $ct_params['http'] = array(); 
        foreach ($this->stream_context as $k=>$v) {
            $ct_params['http'][$k] = $v;
        }
        if ($protected) {
            $ct_params['http']['header'] = 'Authorization: Basic '.$_SERVER['HTTP_X_URL_AUTH'];
        }
        $ct = stream_context_create($ct_params);

        
        // La source du fichier XML
        $lien_xml = $var;

        // Recuperation du fichier XML
        $source_xml = @file_get_contents($lien_xml,false, $ct);
        if (!$source_xml) {
            $this->write_log($login,$var,'404',"-");
            RespearStatus::getInstance()->addStatus(4042,array('XML not found maybe because this file is protected')); 
            $this->send_respear_status($h);
            exec("rm -rf $dossier_tmp");
            return ''; 
        }
        
        // Ecriture du fichier XML 
        if (!file_put_contents($this->tmp_path.$dossier_tmp."package.xml",$source_xml)) {
            $this->write_log($login,$var,'415',"-");
            RespearStatus::getInstance()->addStatus(4156); 
            $this->send_respear_status($h);    
            exec("rm -rf ".$this->tmp_path.$dossier_tmp);
            return ''; 
        }    

        // On enleve tous les possibles / qui sont a la fin du lien    
        while ( strrpos($lien_xml,"/") == strlen($lien_xml)-1) {
            $lien_xml = substr($lien_xml,0,strlen($lien_xml)-1);
        }
        
        // Le lien du dossier source
        $source_dossier = substr($lien_xml,0,strrpos($lien_xml,"/"))."/";
        
        $nom = $this->extract_package_name($p,$h,$login,$this->tmp_path.$dossier_tmp,$source_xml);
        if ($nom === '') return '';
        
        // Chargement du XML en tant que XML    
        $xml = simplexml_load_string($source_xml);
        
        chdir($this->tmp_path.$dossier_tmp);
        // Création du dossier $nom-$release pour respecter la syntaxe PEAR
        exec("mkdir $nom");
        
        // On appel builder_pear_architecture qui parcours le XML et créé l'architecture adequat
        $children = $this->builder_pear_architecture($login,$nom,$h,$xml->contents,$source_dossier,$ct,$this->tmp_path.$dossier_tmp.$nom);
        if ( $children == '') {  
            return '';
        }

	    return $this->prepare_pear_package($p,$h,$login,$this->tmp_path.$dossier_tmp);
    }

    /**
     * Call extract_package_name (which extract the package name) update_channel_name (which update the channel_name)
     * and builds the pear package.
     * @return The name and the tgz path of the pear package
     */
    function prepare_pear_package($p,$h,$login,$dossier_tmp)
    {
        // Verification de l'existence de package.xml
        if (!file_exists($dossier_tmp."package.xml")) {
            $this->write_log($login,'-','415',"-");
            RespearStatus::getInstance()->addStatus(4151); 
            $this->send_respear_status($h);    
            exec("rm -rf $dossier_tmp");
            return ''; 
        }

	    $nom = $this->extract_package_name($p,$h,$login,$dossier_tmp,file_get_contents($dossier_tmp."package.xml"));
        if ($nom === '') return '';	
        
        $xml = $this->update_channel_name($p,$h,$login,$dossier_tmp,file_get_contents($dossier_tmp."package.xml"));
        if ($xml === '') return '';
        
        // On recopie le nouveau XML
        $b = file_put_contents($dossier_tmp."package.xml",$xml);
        if (!$b) {
            $this->write_server_log(RespearStatus::getInstance()->getMessage(5003).' from prepare_pear_package',"500");
            RespearStatus::getInstance()->addStatus(5003); 
            $this->send_respear_status($h);    
            exec("rm -rf $dossier_tmp");
            return ''; 
        }
        
        chdir($dossier_tmp);
        exec("tar czf $nom.tgz $nom package.xml",$msg,$status);
        if ($status > 0|| !file_exists($dossier_tmp.$nom.".tgz") ){
            $this->write_server_log(RespearStatus::getInstance()->getMessage(5006).' from prepare_pear_package',"500");
            RespearStatus::getInstance()->addStatus(5006); 
            $this->send_respear_status($h);    
            exec("rm -rf $dossier_tmp");
            return '';
        }
        
        
        $return['name']     = $nom;
        $return['tgz_path'] = $dossier_tmp."/$nom.tgz";
        
        return $return;    
    }

    /**
     * Updates the channel name of the pear package to variable channel_name
     * @return the modified xml 
     */
    function update_channel_name($p,$h,$login,$dossier_tmp,$xml){
        // Modification du nom du channel dans le XML  
        $new_source_xml = preg_replace('/<channel> *[\w\.\-\_]* */','<channel>'.$this->channel_name,$xml,1);
       
        if ($new_source_xml == NULL ) {
            $this->write_log($login,$var,'415',"-");
            RespearStatus::getInstance()->addStatus(4154); 
            $this->send_respear_status($h);    
            exec('rm -rf '.$dossier_tmp);
            return ''; 
        }
        if (strcmp($new_source_xml,$xml) != 0 ) {
            RespearStatus::getInstance()->addStatus(2002);
        }
        return $new_source_xml;
    }

    /**
     * Checks the architecture of the XML and extract the name (name-release)
     * @return the name of the pear package 
     */
    function extract_package_name($p,$h,$login,$dossier_tmp,$xml){
	    // Création de l'objet SimpleXMLElement        
        try {
            $package = @new SimpleXMLElement($xml);
        } catch (Exception $e) {
            $this->write_log($login,'-','415',"-");
            RespearStatus::getInstance()->addStatus(4151,array(@$e->getMessage())); 
            $this->send_respear_status($h);    
            exec("rm -rf $dossier_tmp");
            return ''; 
        }

	    // S'il y a des erreurs dans le XML
        $tab = libxml_get_errors();
        if (count($tab) > 0) {
            $this->write_log($login,'-','415',"-");
            RespearStatus::getInstance()->addStatus(4153,$tab); 
            $this->send_respear_status($h);    
            exec("rm -rf $dossier_tmp");
            return '';
        }
        
	    // S'il y a bien l'attribut <name>
        if ( ($nom = $package->name) == "") {
            $this->write_log($login,'-','415',"-");
            RespearStatus::getInstance()->addStatus(4154); 
            $this->send_respear_status($h);    
            exec("rm -rf $dossier_tmp");
            return '';   
        }
	    // S'il y a bien l'attribut <version>
        if ($package->version == "") {
            $this->write_log($login,'-','415',"-");
            RespearStatus::getInstance()->addStatus(4154); 
            $this->send_respear_status($h);    
            exec("rm -rf $dossier_tmp");
            return ''; 
        }    
	    // S'il y a bien l'attribut <release>
        if ($package->version->release == "") {
            $this->write_log($login,'-','415',"-");
            RespearStatus::getInstance()->addStatus(4154); 
            $this->send_respear_status($h);    
            exec("rm -rf $dossier_tmp");
            return ''; 
        }
        $nom .= "-".$package->version->release ;
        
        return $nom;
    }

    /**
     *  Check the configuration file (config.php)
     */
    function check_config($p,$h) {
        // Verification de la structure de config.php
        if (! (isset($this->htpasswd_file) && isset($this->htapikey_file) && isset($this->htblacklist_file) && isset($this->tmp_path) 
        && isset($this->pear_server_path) && isset($this->pirum_path) && isset($this->channel_name) )) {
            $this->write_server_log(RespearStatus::getInstance()->getMessage(5000)." Missing variable (htpasswd_file, htapikey_file, htblacklist_file,
              tmp_path, pear_server_path, pirum_path, channel_name)","500");
            RespearStatus::getInstance()->addStatus(5000,array('Show log file for more information')); 
            $this->send_respear_status($h); 
            return '';   
        }
       
        // Verification du contenu (si non initialisé)
        if ($this->htpasswd_file == "" || $this->htapikey_file == "" || $this->htblacklist_file == "" || $this->tmp_path == "" || 
        $this->pear_server_path == "" || $this->pirum_path == "" || $this->channel_name == "") {
            $this->write_server_log(RespearStatus::getInstance()->getMessage(5000)." Variable not initialized in config.php","500");
            RespearStatus::getInstance()->addStatus(5000,array('Show log file for more information')); 
            $this->send_respear_status($h); 
            return '';
        }
        
        // Verification de l'integrité des valeurs
        if (!is_file($this->pirum_path)) {
            $this->write_server_log(RespearStatus::getInstance()->getMessage(5000)." Error. Variable pirum_path must be a file","500");
            RespearStatus::getInstance()->addStatus(5000,array('Show log file for more information')); 
            $this->send_respear_status($h); 
            return '';
        }
        if (!is_dir($this->pear_server_path)) {
            $this->write_server_log(RespearStatus::getInstance()->getMessage(5000)." Error. Variable pear_server_path must be a directory","500");
            RespearStatus::getInstance()->addStatus(5000,array('Show log file for more information')); 
            $this->send_respear_status($h); 
            return '';            
        }
        if (!is_dir($this->tmp_path)) {
            $this->write_server_log(RespearStatus::getInstance()->getMessage(5000)." Error. Variable tmp_path must be a directory","500");
            RespearStatus::getInstance()->addStatus(5000,array('Show log file for more information')); 
            $this->send_respear_status($h); 
            return ''; 
        }
        if (!is_file($this->htpasswd_file)) {
            touch($this->htpasswd_file);
        }
        if (!is_file($this->htapikey_file)) {
            touch($this->htapikey_file);
        }
        if (!is_file($this->htblacklist_file)) {
            touch($this->htblacklist_file);
        }
        
        return 'ok';
    } 
    
    /**
     * Check the architecture of config.php 
     * Check the autorization. If this login is know and is not blocked
     * @return the login of this user
     */
    function check_autorization($p, $h) {        
        // Récuperation des informations de l'utilisateur    
        if (!isset($_SERVER["Authorization"])) {
            RespearStatus::getInstance()->addStatus(4010); 
            $this->send_respear_status($h); 
            return '';
        }
        $login_mdp_C = str_replace("Basic ","",$_SERVER["Authorization"]);
        $login_mdp   = base64_decode($login_mdp_C);

        // Chargement du fichier htpasswd et htblacklist
        $fichierPWD  = file_get_contents($this->htpasswd_file);
        if (!$fichierPWD) {
            $this->write_server_log(RespearStatus::getInstance()->getMessage(5001).' from check_autorization',"500");
            RespearStatus::getInstance()->addStatus(5001); 
            $this->send_respear_status($h); 
            return '';
        }
        $tab_pwd  = split("[\n]",$fichierPWD);

        
        // Est-il connu dans la base    
        if (!in_array($login_mdp,$tab_pwd) ) {
            $blackList = file_get_contents($this->htblacklist_file);
            $tab_black = split("[\n]",$blackList);
            // Est-il dans la black-list?    
            if (in_array($login_mdp,$tab_black)) {
                $this->write_log(substr($login_mdp,0,strrpos($login_mdp,":")),"-","401","-");
                RespearStatus::getInstance()->addStatus(4012); 
                $this->send_respear_status($h); 
                return '';
            }
            RespearStatus::getInstance()->addStatus(4011); 
            $this->send_respear_status($h); 
            return '';
        }

        return substr($login_mdp,0,strrpos($login_mdp,":"));
    }

    /**
     * Build the pear architecture compared with the node <content> of the xml file.
     * $node is the cotent of the attribut <content> in the xml file
     * $lien is the url of the xml documents without the xml path (ex: http://example.fr/thomas/package.xml $lien= http://example.fr/thomas )
     * $ct is a param for the proxy
     * $path is the temporary path (=$tmp_path)
     * $str is the path in the architecture
     * @returns an array with the file path in the xml document
     */
    function builder_pear_architecture($login,$nom,$h,$node,$lien,$ct,$path,$str='',$ecriture='',$login='') {
        foreach ($node->children() as $n) {
            if ($n->getName() == 'dir') {
                if ($n['name'] == '/') {
                    exec("mkdir ".$path.$str.$n['name']);
                    $tab = $this->builder_pear_architecture($login,$nom,$h,$n,$lien,$ct,$path,$str.$n['name']);
                } else {
                    exec("mkdir ".$path.$str.$n['name'].'/');
                    $tab = $this->builder_pear_architecture($login,$nom,$h,$n,$lien,$ct,$path,$str.$n['name'].'/');
                }
            } elseif ($n->getName() == 'file') {
                @file_put_contents($path.$str.$n['name'],@file_get_contents($lien.substr($str,1).$n['name'], false, $ct));
                if ( $ecriture  || !file_exists($path.$str.$n['name'])  ) {
                    $this->write_log($login,"-","415","-");
                    RespearStatus::getInstance()->addStatus(4156,array('file in package.xml not found')); 
                    $this->send_respear_status($h); 
                    return '';
                }
                $tab[] =  $str.$n['name'];
            }
        }
        return $tab;
    }

    /**
     * Write the development of the application in a log file (message 200-400)
     */
    function write_log($login,$nomPacket,$codeHtml,$tailleObj)
    {
        if (!is_dir($this->log_path)) {
            exec('mkdir '.$this->log_path,$msg,$status);
            if ($status > 0) {
                $this->write_server_log(RespearStatus::getInstance()->getMessage(5007).' from write_log',"500");
                RespearStatus::getInstance()->addStatus(5007);
                return '';
            }
        }
        @file_put_contents($this->log_path."respear.log",$_SERVER["REMOTE_ADDR"]." - ".$login." ".date("[d/M/Y:H:i:s O]")." \"".
                 $_SERVER["REQUEST_METHOD"] ." /respear/$nomPacket ".$_SERVER["SERVER_PROTOCOL"]."\" ".    $codeHtml." ".$tailleObj." \"".
                 "-"."\" \"".$_SERVER["HTTP_USER_AGENT"] ."\"\n",FILE_APPEND);
    }

    /**
     *  Write the internal error in a log file (message 500)
     */
    function write_server_log($msg,$codeHtml)
    {
        if (!is_dir($this->log_path)) {
            exec('mkdir '.$this->log_path,$msg,$status);
            if ($status > 0) {
                RespearStatus::getInstance()->addStatus(5007);
                return '';
            }
        }
        @file_put_contents($this->log_path."error.log",date("[d/M/Y:H:i:s O]")." [".$codeHtml."] [".$_SERVER["REMOTE_ADDR"]."] "
        .$_SERVER["REQUEST_METHOD"]." \"".$msg."\"\n",FILE_APPEND);
    }    

    /**
     * Display a message
     */
    function show_message($arg, $prefix = '')
    {
        if (is_array($arg)) {
            foreach ($arg as $error) {
                echo $prefix.$error."\n";
            }
        } else {
            echo $arg."\n";
        }
    }

    /**
     *  list of PEAR packages which are  on the server Pirum
     */
    function list_pear_packages($p, $h)
    {
        //Check configuration
        $conf = $this->check_config($p,$h);
        if ($conf == '') return '';
        
        include_once("ATOMWriter.php");
        
        $xmlWriter = new XMLWriter();
        $xmlWriter->openUri('php://output');
        $xmlWriter->setIndent(true);

        // On recupere les infos directement dans le get
        $rep      = $this->pear_server_path."get/";
        $tab_nom  = array();
        $info_nom = array();

        $dir = opendir($rep);
        while ($f = readdir($dir)) {
           if (is_file($rep.$f)) {
               $nom = substr($f,0,strpos($f,"-"));
               if (!in_array($nom,$tab_nom)) {
                   $tab_nom[]             = $nom;
                   $info_nom[$nom."crea"] = filectime($rep.$f);
                   $info_nom[$nom."modi"] = filemtime($rep.$f);
               }
           }
        }
         
        $f = new ATOMWriter($xmlWriter, true);
        $f->startFeed('urn:respear')
          ->writeStartIndex(1)
          ->writeItemsPerPage(10)
          ->writeTotalResults(100)
          ->writeTitle('List of the packages on the server Pear:');
            
        foreach ($tab_nom as $k=>$v) {
            $f->startEntry("urn:respear:$v",$info_nom[$v."crea"],$info_nom[$v."modi"])
              ->writeTitle($v)
              ->writeLink($v.'/')
              ->endEntry();
            $f->flush();
        }
        
        $f->endFeed();
        $f->flush();
        
        $h->send(200);
    }

    /**
     * list the releases of a PEAR packages which are on the server Pirum
     */
    function list_package_versions($p, $h)
    {
        //Check configuration
        $conf = $this->check_config($p,$h);
        if ($conf == '') return '';
        
        include_once("ATOMWriter.php");
        
        $xmlWriter = new XMLWriter();
        $xmlWriter->openUri('php://output');
        $xmlWriter->setIndent(true);
        
        $rep = $this->pear_server_path."get/";
        
        $f = new ATOMWriter($xmlWriter, true);
           
        $nom  = array();
        $para = (string)$p->__sections[0];
	    $boolean = 0;
        $dir  = opendir($rep);         
        while ($fi = readdir($dir)) {                            
            if (is_file($rep.$fi)) {
                if (strpos($fi,$para) !== false && !in_array(substr($fi,0,strrpos($fi,".")),$nom)) {    
                   //s'il contient le mot en param et qu'on la pas en memoire
                   $boolean = 1;
                   $nom[] = substr($fi,0,strrpos($fi,"."));
                }
            }
        }
        if ($boolean == 0 ) {
        	$h->send(404);
        	$f->startFeed("urn:respear:$para:unknow")
          	  ->writeStartIndex(1)
	          ->writeItemsPerPage(10)
	          ->writeTotalResults(100)
	          ->writeTitle('Package not found');      
	          exit();
        }
	    $f->startFeed('urn:respear:$para')
          ->writeStartIndex(1)
          ->writeItemsPerPage(10)
          ->writeTotalResults(100)
          ->writeTitle('list of the different version of '.(string)$p->__sections[0].':');

        foreach ($nom as $v) {
		    $f->startEntry("urn:respear:$para:$v")
              ->writeTitle($v)
              ->writeLink(substr($v,strrpos($v,'-')+1).'/')
              ->endEntry();
            $f->flush();
        }   

        $h->send(200);
    } 
}
