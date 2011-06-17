<?php

/**
 * Respear
 *
 * (c) 2011 Thomas Picard
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

require_once 'Respear.php';
require_once 'translaters.php';
require_once 'plor/PRSUrl.php';
require_once 'config.php';


$respear = new Respear($GLOBALS['respear']);

// list pear packages
$pear_packages = PRSUrl::factory('/{index}.xml')
    ->translate('index', 'translaters_index')
    ->bindMethod('GET', array($respear,'list_pear_packages'))
    ->bindMethod('POST',array($respear,'add_pear_package'))
    ->bindMethod('DELETE',array($respear,'remove_pear_package'));
    
// list versions of a pear package or delete a package family
$package_versions = PRSUrl::factory('/{package}/{index}.xml')
    ->translate('index', 'translaters_index')
    ->translate('package', 'translaters_package')
    ->bindMethod('GET', array($respear,'list_package_versions'))
    ->bindMethod('DELETE', array($respear,'remove_pear_package'));

// information about one version of pear package or delete this version
$package_information = PRSUrl::factory('/{package}/{packageversion}/{index}.xml')
    ->translate('index', 'translaters_index')
    ->translate('package', 'translaters_package')
    ->translate('packageversion', 'translaters_package_version')
    ->bindMethod('DELETE', array($respear,'remove_pear_package'))
    ->bindMethod('GET',array($respear,'show_package'));

// explore a pear package
$package_contents_1 = PRSUrl::factory('/{package}/{packageversion}/{packagefile}')
    ->translate('package', 'translaters_package')
    ->translate('packageversion', 'translaters_package_version')
    ->translate('packagefile', 'translaters_package_file')
    ->bindMethod('GET', array($respear,'get_file'));
    
// Launch the server
require_once 'plor/PRS.php';
$options = array(
    'base' => '/respear',
    );

$app = PRS::factory($options);
$app[] = $pear_packages;
$app[] = $package_versions;
$app[] = $package_information;
$app[] = $package_contents_1;
$app->listen();

