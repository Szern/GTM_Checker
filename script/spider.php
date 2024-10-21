#!/usr/bin/php8.3

<?php

date_default_timezone_set('Europe/Warsaw');

// configuration

$domain = ''; // i.e. 'domain.com'
$protocol = ''; // i.e. 'https'

$gtm1 = 'www.googletagmanager.com/gtm.js';
$gtm2 = 'www.googletagmanager.com/ns.html';

$exclude = array('webp','png','apng','jpg','jpeg','pdf','gif','avif','svg','bmp','ico','tiff','mp4','webm'); // file extensions

$dir = '/home/www/metricsmaster.pl/html';

$act = 0;

// file's names

$active_file = 'on_off.txt';
$ce = 'ce_website.pem';
$list_to_check_file = 'urls.txt';
$map_file = 'map.txt';
$exc_file = 'exclude.txt';
$hyperlinks_file = 'links.txt';
$errors = 'errors.txt';
$finded = 'including_tags.txt';
$not_finded = 'excluding_tags.txt';
$project_file = 'projekt.txt';
$sitemap = 'sitemap.xml';

// end of configuration

chdir($dir);

function scrape($url) {
    global $ce,$errors,$cert;
    if (!empty($cert)) {
        $headers = Array(
            "Accept: text/xml,application/xml,application/xhtml+xml,text/html;q=0.9,text/plain;q=0.8,image/png,*/*;q=0.5",
            "Cache-Control: max-age=0",
            "Connection: keep-alive",
            "Keep-Alive: 3600",
            "Accept-Charset: ISO-8859-1,utf-8;q=0.7,*;q=0.7",
            "Accept-Language: en-us,en;q=0.5",
            "Pragma: "
        );
        $config = Array(
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_CAINFO => $ce,
            CURLOPT_RETURNTRANSFER => TRUE,
            CURLOPT_FOLLOWLOCATION => TRUE,
            CURLOPT_AUTOREFERER => TRUE,
            CURLOPT_CONNECTTIMEOUT => 300,
            CURLOPT_TIMEOUT => 300,
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_USERAGENT => "Mozilla/5.0 (X11; U; Linux i686; en-US; rv:1.9.1a2pre) Gecko/2008073000 Shredder/3.0a2pre ThunderBrowse/3.2.1.8",
            CURLOPT_URL => $url
        );
        $output = new StdClass;
        $handle = curl_init();
        curl_setopt_array($handle,$config);
        curl_setopt($handle,CURLOPT_HTTPHEADER,$headers);
        $output->data = curl_exec($handle);

        if (curl_exec($handle) === false) {
            $output->error = 'Curl error: ' . curl_error($handle);
            $return = 0;
        } else {
            $output->error = 'Operation completed without any errors';
            $return = $output;
        }
        curl_close($handle);
    } else {
        add_to_file($errors, 'page: ' . $url . ' without proper TSL' . PHP_EOL);
        $return = 0;
    }
    return $return;
}

function find_urls($web) {
    global $home, $domain, $errors;
    while ( strpos($web,'<a href=') ) {
        $one = substr($web,strpos($web,'<a href=') + 9);
        $two = substr($web,strpos($web,'<a href=') + 8,1);
        $three = substr($one,0,strpos($one,$two));
        if (substr($three,0,1) == '/') {
            $three = $home . $three;
        }
        if (strpos($three,'?')) {
            $three = substr($three,0,strpos($three,'?'));
        } elseif (strpos($three,'#')) {
            $three = substr($three,0,strpos($three,'#'));
        }
        if ( (substr($three,0,8) == 'https://') && (strpos($three,$domain)) ) {
            $urls[] = trim($three);
        }
        $web = $one;
    }
    $urls = array_unique($urls);
    if (!empty($urls)) {
        $return = array_values(array_unique($urls));
    } else {
        $return = 0;
    }
    return $return;
}

function get_from_file($file,$how_many) {
    global $errors;
    if ( noempty($file) ) {
        $data = file($file);
        if ($how_many == 'domain') {
            $line = trim($data[count($data)-1]);
        } elseif ($how_many == 'last') {
            $line = trim($data[count($data)-1]);
            remove_from_file($file,'last');
        } elseif ($how_many == 'first') {
            $line = trim($data[0]);
            remove_from_file($file,'first');
        } elseif ($how_many == 'all') {
            foreach ($data as $d) {
                if (substr($d,0,8) == 'https://') {
                    $line[] = trim($d);
                }
            }
        }
        return $line;
    } else {
//        add_to_file($errors, 'empty or no exist file ' . $file . ' in function get_from_file' . PHP_EOL);
        return 0;
    }
}

function remove_from_file($file,$lf) {
    if ( noempty($file) ) {
        $data = file($file);
        if ( $lf == 'first' ) {
            $x = count($data) - 1;
            $handle = fopen($file, 'w');
            for ($i = 1; $i < $x - 1; $i++) {
                fwrite($handle,$data[$i]);
            }
            fwrite($handle,trim($data[$x]));
            fclose($handle);
        } elseif ( $lf == 'last') {
           $x = count($data);
            if ( $x < 2 ) {
                $handle = @fopen($file, "r+");
                if ($handle !== false) {
                    ftruncate($handle, 0);
                    fclose($handle);
                }
                return;
            } else {
                $x = $x - 2;
            }
            $handle = fopen($file, 'w');
            for ($i = 0; $i < $x; $i++) {
                fwrite($handle,$data[$i]);
            }
            fwrite($handle,trim($data[$i]));
            fclose($handle);
        }
    }
}

function add_to_file($file,$line) {
    $line = trim($line);
    $mark = 1;
    if ( noempty($file) ) {
        $temp = get_from_file($file,'all');
        foreach ($temp as $t) {
            if ($line == trim($t)) {
                $mark = 0;
            }
        }
        $line = PHP_EOL . $line;
    }
    if ($mark) {
        $handle = fopen($file, 'a');
        fwrite($handle, $line);
        fclose($handle);
    }
}

function noempty($file) {
    clearstatcache();
    if (file_exists($file)) {
        if (filesize($file) > 0) {
            $return = 1;
        } else {
            $return = 0;
        }
    } else {
        $return = 0;
    }
    return $return;
}

function sort_file($file) {
    if ( noempty($file) ) {
        $data = file($file);
        sort($data);
        $data = array_values(array_filter($data));
        $handle = fopen($file, 'w');
        $x = count($data) - 1;
        for ($i = 0; $i < $x - 1; $i++) {
            fwrite($handle, $data[$i]);
        }
        fwrite($handle, trim($data[$i]));
        fclose($handle);
    }
}

function do_sitemap($file) {
    global $sitemap;
    $data = file($file);
    $handle = fopen($sitemap, 'w');
    fwrite($handle, '<?xml version="1.0" encoding="UTF-8"?>
<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">');
    $x = count($data) - 1;
    for ($i = 0; $i < $x; $i++) {
        fwrite($handle, '
  <url>
    <loc>');
        fwrite($handle, trim($data[$i]));
        fwrite($handle, '</loc>
    <lastmod>');
        fwrite($handle, date('Y-m-d'));
        fwrite($handle, '</lastmod>
  </url>');
    }
    fwrite($handle, '
</urlset>');
    fclose($handle);
}


if ( noempty($active_file) ) {
    $act = (int)file_get_contents($active_file);
} else {
    $act = $active;
}

if ( !($act) ) { exit(); }

if ( noempty($project_file) ) {
    $project_id = file_get_contents($project_file);
} else {
    $project_id = '';
    $characters = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
    for ($i = 0; $i < 30; $i++) {
        $index = rand(0, strlen($characters) - 1);
        $project_id .= $characters[$index];
        file_put_contents($project_file, $project_id);
    }
}

if ( (!(noempty($list_to_check_file))) && (noempty($map_file)) ) {
    sort_file($map_file);
    sort_file($exc_file);
    sort_file($hyperlinks_file);
    sort_file($finded);
    sort_file($not_finded);
    mkdir($project_id, 0744, true);
    rename($map_file, $project_id . '/' . $map_file);
    do_sitemap($project_id . '/' . $map_file);
    rename($map_file, $project_id . '/' . $map_file);
    rename($exc_file, $project_id . '/' . $exc_file);
    rename($hyperlinks_file, $project_id . '/' . $hyperlinks_file);
    rename($errors, $project_id . '/' . $errors);
    rename($finded, $project_id . '/' . $finded);
    rename($not_finded, $project_id . '/' . $not_finded);
    rename($sitemap, $project_id . '/' . $sitemap);
    unlink($ce);
    unlink($list_to_check_file);
    unlink($project_file);
    file_put_contents($active_file, '0');
    exit();
}

if ( !(noempty($list_to_check_file)) ) {
    add_to_file($errors,  'no domain error' . PHP_EOL);
    exit();
} else {
    $home = trim(get_from_file($list_to_check_file,'domain'));
    if (substr($home,-1) == '/') {
        $home = trim(substr($home,0,-1));
    }
    if ( substr($home,0,8) == 'https://' ) {
        $protocol = 'https://';
        $domain = substr($home,8);
    } elseif ( substr($home,0,7) == 'http://' ) {
        $protocol = 'http://';
        $domain = substr($home,7);
    } else {
        add_to_file($errors,  'home: ' . $home . ' protocol error' . PHP_EOL);
    }
}

if ( !(noempty($ce)) ) {
    $orignal_parse = parse_url($home, PHP_URL_HOST);
    $g = stream_context_create (array("ssl" => array("capture_peer_cert" => true)));
    $r = stream_socket_client("ssl://".$orignal_parse.":443", $errno, $errstr, 30, STREAM_CLIENT_CONNECT, $g);
    $cont = stream_context_get_params($r);
    openssl_x509_export($cont["options"]["ssl"]["peer_certificate"],$cert);
    if (!empty($cert)) {
        $cert = preg_replace("/(?<=[^\r]|^)\n/", "\r\n", $cert);
        file_put_contents($ce,$cert);
    }
} else {
    $cert = file_get_contents($ce);
}

while ( noempty($list_to_check_file) ) {
    $url = trim(get_from_file($list_to_check_file,'last')); // take url from checklist // how to add to map mainpage?
    if (substr($url,-1) != '/') {
        $url = $url . '/';
    }
    $scrape = scrape($url);
    if ( ($scrape) && ($scrape->error == 'Operation completed without any errors') ) { // ?
        $web = $scrape->data;
        if ( strpos($web,$gtm1) && strpos($web,$gtm2) ) { // serch gtm strings
            add_to_file($finded, $url . PHP_EOL);
        } else {
            add_to_file($not_finded, $url . ' * first searched string position (if find): ' . strpos($web,$gtm1) . ' * second serched string position (if find): ' . strpos($web,$gtm2) . PHP_EOL);
        }
        $urls = find_urls($web); // search hyperlinks
        if ($urls) {
            foreach ($urls as $u) {
                if ( strpos($u, '/cdn-cgi/l/email-protection') == false) {
                    $u = trim($u);
                    add_to_file($hyperlinks_file,$url . ' ==> ' . $u . PHP_EOL);
                    $mark = 1;
                    foreach ($exclude as $e) {
                        $one = strlen($e);
                        $two = -1 * $one;
                        if ( substr($u,$two,$one) == $e ) {
                            $mark = 0;
                            add_to_file($exc_file,$u); // add files to exclude.txt file
                        }
                    }
                    if ( $mark == 1 ) {
                        $map = get_from_file($map_file,'all');
                        if ($map) {
                            foreach ($map as $m) {
                                if ($u == trim($m)) {
                                    $mark = 0;
                                }
                            }
                        }
                    }
                    if ( $mark && (substr($u,0,8) == 'https://') ) {
                        if ( substr($u,-1,1) != '/' ) { $u = $u . '/'; }
                        add_to_file($list_to_check_file,$u); // add new finded urls to the checklist
                        add_to_file($map_file,$url); // add url to the map
                    }
                }
            }
        }
    } else {
        add_to_file($errors,  'url ' . $url . ' not scraped, error: ' . $scrape->error . PHP_EOL);
    }
}

?>