<?php

/**
 * Respear
 *
 * (c) 2011 Thomas Picard
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */
 
function translaters_index ($url, $sec) 
{
    if ($url == '') {
        $url .= 'index.xml';
        $sec->exchange('index');
    } elseif (preg_match('/^index/', $url)) {
        $sec->exchange('index');
    }
    return $url;
}

function translaters_package ($url, $sec) 
{
    if (preg_match('/(^[\w\_]+)/i', $url, $m)) {
        $sec->exchange($m[1]);
    }
    return $url;
}

function translaters_package_version ($url, $sec) 
{
    if (preg_match('/(^[0-9]+\.[0-9]+\.[0-9]+)/', $url, $m)) {
        $sec->exchange($m[1]);
    }
    return $url;
}

function translaters_package_file($url, $sec)
{
    if (preg_match('/(^[\w\_\-\.0-9\/]+)/i', $url, $m)) {
        $sec->exchange($m[1]);
    }
    return $url;
}
