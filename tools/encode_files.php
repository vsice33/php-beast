<?php

/**
 * Encode files by directory
 * @author: liexusong
 */

$nfiles = 0;
$finish = 0;
$version_folders = ['.git', '.github'];

function calculate_directory_schedule($dir, $exclude_folders, $extra_files)
{
    global $nfiles, $version_folders;

    $dir = rtrim($dir, '/');

    $handle = opendir($dir);
    if (!$handle) {
        return false;
    }

    while (($file = readdir($handle))) {
        if ($file == '.' || $file == '..' || in_array($file, $version_folders) || in_array($file, $exclude_folders)) {
            continue;
        }

        $path = $dir . '/' . $file;

        if (is_dir($path)) {
            calculate_directory_schedule($path, $exclude_folders, $extra_files);
            continue;
        }

        $infos = explode('.', $file);
        if (strtolower($infos[count($infos)-1]) == 'php' || in_array($file, $extra_files)) {
            $nfiles++;
        }
    }

    closedir($handle);
}

function encrypt_directory($dir, $new_dir, $expire, $type, $exclude_folders, $extra_files)
{
    global $nfiles, $finish, $version_folders;

    $dir = rtrim($dir, '/');
    $new_dir = rtrim($new_dir, '/');

    $handle = opendir($dir);
    if (!$handle) {
        return false;
    }

    while (($file = readdir($handle))) {
        if ($file == '.' || $file == '..' || in_array($file, $version_folders)) {
            continue;
        }

        $path = $dir . '/' . $file;
        $new_path =  $new_dir . '/' . $file;

        if (is_dir($path) && in_array($file, $exclude_folders)) {
            printf(PHP_EOL . 'folder: %s will not to be encoded.' . PHP_EOL, $file);
            recurseCopy($path, $new_path);
            continue;
        } else if (is_dir($path) && !is_dir($new_path)) {
            mkdir($new_path, 0777);
        }

        if (is_dir($path)) {
             encrypt_directory($path, $new_path, $expire, $type, $exclude_folders, $extra_files);
             continue;
        }

        $infos = explode('.', $file);
        if ((strtolower($infos[count($infos)-1]) == 'php' || in_array($file, $extra_files))
            && filesize($path) > 0)
        {
            if ($expire > 0) {
                $result = beast_encode_file($path, $new_path, $expire, $type);
            } else {
                $result = beast_encode_file($path, $new_path, 0, $type);
            }

            if (!$result) {
                echo "Failed to encode file `{$path}'\n";
            }

            $finish++;
            $percent = intval($finish / $nfiles * 100);
            printf("\rProcessed encrypt files [%d%%] - 100%%", $percent);
        } else {
            copy($path, $new_path);
        }
    }

    closedir($handle);
}

function recurseCopy($src, $dst, $override = false)
{
    $dir = opendir($src);
    if (is_dir($dst) && $override == false) {
        return;
    }

    if (!is_dir($dst)) {
        mkdir($dst, 0777);
    }

    while(false !== ( $file = readdir($dir)) ) {
        if ($file == '.' || $file == '..') {
            continue;
        }

        if (is_dir($src . '/' . $file)) {
            recurseCopy($src . '/' . $file, $dst . '/' . $file);
            continue;
        }

        copy($src . '/' . $file, $dst . '/' . $file);
    }
    closedir($dir);
}

//////////////////////////////// run here ////////////////////////////////////

$conf = parse_ini_file(dirname(__FILE__) . '/configure.ini');
if (!$conf) {
    exit("Fatal: failed to read configure.ini file\n");
}

$src_path        = trim($conf['src_path']);
$dst_path        = trim($conf['dst_path']);
$expire          = trim($conf['expire']);
$encrypt_type    = strtoupper(trim($conf['encrypt_type']));
$exclude_folders = explode(',', trim($conf['exclude_folders']));
$extra_files     = explode(',', trim($conf['extra_files']));

if (empty($src_path) || !is_dir($src_path)) {
    exit("Fatal: source path `{$src_path}' not exists\n\n");
}

if (empty($dst_path) || (!is_dir($dst_path) && !mkdir($dst_path, 0777, true))) {
    exit("Fatal: can not create directory `{$dst_path}'\n\n");
}

switch ($encrypt_type)
{
case 'AES':
    $entype = BEAST_ENCRYPT_TYPE_AES;
    break;
case 'BASE64':
    $entype = BEAST_ENCRYPT_TYPE_BASE64;
    break;
case 'DES':
default:
    $entype = BEAST_ENCRYPT_TYPE_DES;
    break;
}

printf("Source code path: %s\n", $src_path);
printf("Destination code path: %s\n", $dst_path);
printf("Exclude folders: %s\n", trim($conf['exclude_folders']));
printf("Extra files: %s\n", trim($conf['extra_files']));
printf("Expire time: %s\n", $expire);
printf("------------- start process -------------\n");

$expire_time = 0;
if ($expire) {
    $expire_time = strtotime($expire);
}

$time = microtime(TRUE);

calculate_directory_schedule($src_path, $exclude_folders, $extra_files);
encrypt_directory($src_path, $dst_path, $expire_time, $entype, $exclude_folders, $extra_files);

$used = microtime(TRUE) - $time;

printf("\nFinish processed encrypt files, used %f seconds\n", $used);
