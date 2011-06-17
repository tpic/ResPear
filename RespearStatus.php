<?php

/**
 * Respear
 *
 * (c) 2011 Thomas Picard
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */
 
include_once('plor/PRSHeaders.php');
class RespearStatus
{
    public $respear_status = array(
        2000 => '2000 The PEAR package was added successfully',
        2001 => '2001 The PEAR package was removed successfully',
        2002 => '2002 The <server> tag has been updated in package.xml',
        4000 => '4000 Error from Pirum',
        4002 => '4002 Argument empty', 
        4010 => '4010 Login and apikey required', 
        4011 => '4011 Login or apikey unknown',
        4012 => '4012 You are blacklisted',
        4040 => '4040 Pear package does not exist',
        4041 => '4041 File not found',
        4042 => '4042 Error during package.xml opening',
        4090 => '4090 This package\'s version already exists',
        4150 => '4150 Error durring extraction',
        4151 => '4151 package.xml not found',
        4152 => '4152 package.xml content is not XML',
        4153 => '4153 Unknown error in package.xml',
        4154 => '4154 Attribut not found or malformed in package.xml',
        4156 => '4156 Error when reading package sources',
        4157 => '4157 Type unsupported',
        5000 => '5000 Improper respear configuration file ',
        5001 => '5001 Authentication file missing',
        5002 => '5002 Error related to temporary folder',
        5003 => '5003 Error during the renaming', 
        5004 => '5004 Error while deleting package',
        5005 => '5005 Internal error when opening package.xml',
        5006 => '5006 Error when receiving remote file',
        5007 => '5007 Error when writing to the log file',
        5008 => '5008 Internal error when extracting'
    );
    
    protected $status = array();
    
    static protected $instance = null;

    protected function __construct()
    {
        $this->respear_status = $this->respear_status + PRSHeaders::$status;
    }
    
    /**
     * Return a instance of the class RespearStatus. Pattern Singleton
     */ 
    static public function getInstance()
    {
        if (is_null(self::$instance)) {
            self::$instance = new RespearStatus();
        }
        return self::$instance;
    }
    
    /**
     * Add the error code in the array $status
     */
    public function addStatus($code,$msg = array())
    {
        if (isset($this->respear_status[$code])) {
            $this->status[] = array($code,$msg);
        }
        
    }
   
   /**
    * Function (callback) which sorts the array compared with the error code (standart code)
    */
    public function trie($a,$b)
    {
        return (integer)(substr($a[0][0],0,3))-(integer)(substr($b[0][0],0,3));
    }   

    /**
     * Return the HTTP standard code the most exalted
     */
    public function getStatus()
    {
        $status = $this->status;
        usort($status, array($this,"trie"));
        
        return substr($status[0][0],0,3);
    }
     
    /**
     * Return all message for the body
     */ 
    public function getContent()
    {
            $return = '';
        foreach ($this->status as $k=>$v) {
            $return .= $this->respear_status[$v[0]]."\n";
            foreach ($v[1] as $value) {
                $return .= '# '.$value."\n";
            }
        }
        return $return;
    }
    
    public function getMessage($code)
    {
        if (!isset($this->respear_status[$code])) {
            return ' - ';
        }
        return $this->respear_status[$code];
    }
    
}
