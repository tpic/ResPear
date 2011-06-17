<?php
/**
 * Respear
 *
 * (c) 2011 Thomas Picard
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */
if (!file_exists('../Respear.php') && !file_exists('...config.php') ) {
    echo 'Respear.php or config.php missing in the directory \'respear\'';
    return '';
}
include('../Respear.php');
include('../config.php');

class ResPearTest extends PHPUnit_Framework_TestCase
{

    protected $respear_host     = '127.0.0.1'; 
    protected $respear_port     = 8763;
    protected $respear_path     = '/respear/';
    protected $login            = 'UserTest';
    protected $apikey           = 'its-a-real-ApiKey-test';    
    
    public function setUp()
    {
        file_put_contents($GLOBALS['respear']['htpasswd_file'],'UserTest:its-a-real-ApiKey-test'."\n",FILE_APPEND);
        file_put_contents($GLOBALS['respear']['htblacklist_file'],'UserBlocked:its-a-real-ApiKey-test-but-blocked'."\n",FILE_APPEND);
    }
    
    public function tearDown()
    {
        //Delete the UserTest
        $fichierPWD  = split("[\n]",file_get_contents($GLOBALS['respear']['htpasswd_file']));
        $l = array();
		foreach ($fichierPWD as $k=>$v) {
			if ($v != "" && $v !='UserTest:its-a-real-ApiKey-test') {
				$l[] = $v."\n";
			}
		}
		file_put_contents($GLOBALS['respear']['htpasswd_file'],$l);
        
        //Delete the UserBlocked
        $fichierBl   = split("[\n]",file_get_contents($GLOBALS['respear']['htblacklist_file']));
        $l = array();
		foreach ($fichierBl as $k=>$v) {
			if($v!= "" && $v != 'UserBlocked:its-a-real-ApiKey-test-but-blocked') {
				$l[] = $v."\n";
		    }
		}		
		file_put_contents($GLOBALS['respear']['htblacklist_file'],$l);
    }
  
    /**
     * POST with a wrong apikey
     */
    public function testWrongApikey()
    {
        $login            = 'wronglogin';
        $apikey           = 'wrongapikey';
        $package_tgz_file = dirname(__FILE__).'/ExAppli-1.0.1.tgz';
        
        require_once 'REST/EasyClient.php';
        $rc = new  REST_EasyClient($this->respear_host, $this->respear_port);
        $rc->setAuth($login, $apikey);
        $o = $rc->post($this->respear_path, file_get_contents($package_tgz_file));
        
        preg_match('/^([0-9]+)/', $o->content, $matches);
        $this->assertEquals((integer)$matches[1], 4011);
        $this->assertEquals((integer)$o->code, 401);
    }   

    /**
     * POST with a blocked apikey (blacklisted)
     */  
    public function testBlockedApikey()
    {
        $login            = 'UserBlocked';
        $apikey           = 'its-a-real-ApiKey-test-but-blocked';
        $package_tgz_file = dirname(__FILE__).'/ExAppli-1.0.1.tgz';
        
        require_once 'REST/EasyClient.php';
        $rc = new  REST_EasyClient($this->respear_host, $this->respear_port);
        $rc->setAuth($login, $apikey);
        $o = $rc->post($this->respear_path, file_get_contents($package_tgz_file));
        
        preg_match('/^([0-9]+)/', $o->content, $matches);
        $this->assertEquals((integer)$matches[1], 4012);
        $this->assertEquals((integer)$o->code, 401);
    }
    
    /**
     * POST http://127.0.0.1:8763/respear/
     * ExAppli-1.0.1.tgz
     */    
    public function testAddPackageFromTgz()
    {
        $package_tgz_file = dirname(__FILE__).'/ExAppli-1.0.1.tgz';
        
        require_once 'REST/EasyClient.php';
        $rc = new  REST_EasyClient($this->respear_host, $this->respear_port);
        $rc->setAuth($this->login, $this->apikey);
        $o = $rc->post($this->respear_path, file_get_contents($package_tgz_file));
                     
        preg_match('/^([0-9]+)/', $o->content, $matches);
        $this->assertEquals((integer)$matches[1],2000);
        $this->assertEquals((integer)$o->code, 200);
    }
   
    /**
     * POST http://127.0.0.1:8763/respear/
     * ExAppli-2.0.0.tgz (without package.xml)
     */
    public function testAddPackageWithoutXML()
    {
        $package_tgz_file = dirname(__FILE__).'/ExAppli-2.0.0.tgz';
        
        require_once 'REST/EasyClient.php';
        $rc = new  REST_EasyClient($this->respear_host, $this->respear_port);
        $rc->setAuth($this->login, $this->apikey);
        $o = $rc->post($this->respear_path, file_get_contents($package_tgz_file));
                     
        preg_match('/^([0-9]+)/', $o->content, $matches);
        $this->assertEquals((integer)$matches[1], 4151);
        $this->assertEquals((integer)$o->code, 415);
    }
    
    /**
     * POST http://127.0.0.1:8763/respear/
     * https://github.com/kerphi/rest_server/raw/master/package.xml (dans le header X-URL)
     */      
    public function testAddPackageFromUrl()
    {
        $url = 'https://github.com/kerphi/rest_server/raw/master/package.xml'; 
        
        require_once 'REST/Client.php';
        $rc = REST_Client::factory('sync');
        
        $r = REST_Request::newInstance()
                ->setProtocol('http')->setHost($this->respear_host)->setPort($this->respear_port)
                ->setMethod('POST')->setUrl($this->respear_path)
                ->setAuth($this->login, $this->apikey)
                ->setCurlOption(CURLOPT_HTTPHEADER, array('X-URL: '.$url));

        $rc->fire($r);
        $o = $rc->fetch();
                   
        preg_match_all('/([0-9]+)/', $o->content, $matches);
        $this->assertEquals((integer)$matches[0][0], 2002);
        $this->assertEquals((integer)$o->code, 200);
    }
    
    /**
     * POST http://127.0.0.1:8763/respear/
     * https://thomas.picar@inist.fr:MY_PSW@svn.inist.fr/repository/respear/trunk/var/www/package.xml (dans le header X-URL)
     */
    public function testAddPackageFromProtectedUrl()
    {

        echo "\n";
        echo "Enter your INIST login  \n";
        $handle = fopen ("php://stdin","r");
        $login = trim(fgets($handle));
        $mdp   = trim(shell_exec('./ask_mdp'));
        echo "\n";

        $url = "https://@svn.inist.fr/repository/respear/trunk/var/www/package.xml";
        require_once 'REST/Client.php';
        $rc = REST_Client::factory('sync');
        
        $r = REST_Request::newInstance()
                ->setProtocol('http')->setHost($this->respear_host)->setPort($this->respear_port)
                ->setMethod('POST')->setUrl($this->respear_path)
                ->setAuth($this->login, $this->apikey)
                ->setCurlOption(CURLOPT_HTTPHEADER, array('X-URL: '.$url, 'X_URL_AUTH: '.base64_encode($login.':'.$mdp)));

        $rc->fire($r);
        $o = $rc->fetch();
                   
        preg_match_all('/([0-9]+)/', $o->content, $matches);
        $this->assertEquals((integer)$matches[0][0], 2000);
        $this->assertEquals((integer)$o->code, 200);
        
    }
    
    /**
     * GET http://127.0.0.1:8763/respear/REST_Server/2.0.0/
     */
    public function testGetDirInPackage()
    {   
        require_once 'REST/EasyClient.php';
        $rc = new  REST_EasyClient($this->respear_host, $this->respear_port);
        $rc->setAuth($this->login, $this->apikey);
        $o = $rc->get($this->respear_path.'REST_Server/2.0.0/','');
                        
        $isatom = (strpos($o->content, 'http://www.w3.org/2005/Atom') !== FALSE);
        $this->assertTrue($isatom);

        $this->assertEquals((integer)$o->code, 200);
    }            
        
    /**
     * GET http://127.0.0.1:8763/respear/REST_Server/2.0.0/REST/Headers.php
     */
    public function testGetFileInPackage()
    {
        require_once 'REST/EasyClient.php';
        $rc = new  REST_EasyClient($this->respear_host, $this->respear_port);
        $rc->setAuth($this->login, $this->apikey);
        $o = $rc->get($this->respear_path.'REST_Server/2.0.0/REST/Headers.php');


        $isphp = (preg_match('/^<\?php/', $o->content) === 1);
        $this->assertTrue($isphp);

        $this->assertEquals((integer)$o->code, 200);
    } 
    
    /** 
     * DELETE /respear/ExAppli/1.0.1/
     */
    public function testRemoveSimplePackage()
    {
        $package_name     = 'ExAppli';
        $package_version  = '1.0.1';
        
        require_once 'REST/EasyClient.php';
        $rc = new  REST_EasyClient($this->respear_host, $this->respear_port);
        $rc->setAuth($this->login, $this->apikey);
        $o = $rc->delete($this->respear_path.$package_name.'/'.$package_version.'/','');
        
        preg_match('/^([0-9]+)/', $o->content, $matches);
        $this->assertEquals((integer)$matches[1], 2001);
        $this->assertEquals((integer)$o->code, 200);
    }
    
    /**
     * DELETE /respear/REST_Server/
     */    
    public function testRemoveAllPackage()
    {
        $package_name     = 'REST_Server';
        
        require_once 'REST/EasyClient.php';
        $rc = new  REST_EasyClient($this->respear_host, $this->respear_port);
        $rc->setAuth($this->login, $this->apikey);
        $o = $rc->delete($this->respear_path.$package_name.'/','');
        
        preg_match('/^([0-9]+)/', $o->content, $matches);
        $this->assertEquals((integer)$matches[1], 2001);
        $this->assertEquals((integer)$o->code, 200);
    }
    
    /**
     * DELETE /respear/ResPear/
     */    
    public function testRemoveAllPackage2()
    {
        $package_name     = 'ResPear';
        
        require_once 'REST/EasyClient.php';
        $rc = new  REST_EasyClient($this->respear_host, $this->respear_port);
        $rc->setAuth($this->login, $this->apikey);
        $o = $rc->delete($this->respear_path.$package_name.'/','');
        
        preg_match('/^([0-9]+)/', $o->content, $matches);
        $this->assertEquals((integer)$matches[1], 2001);
        $this->assertEquals((integer)$o->code, 200);
    }
    

}
