<?php
error_reporting(-1);
date_default_timezone_set('America/New_York');

/**
 * @var string Directory To Archive
 * This should be an absolute path that is passed on the command line to this script
 */
$directoryToArchive = $argv[1];

/**
 * @var string the S3 Bucket archives should be uploaded to
 */
$bucketName = 'kimmellj-backup';

/**
 * @var string The directory this file resides
 */
$currentDir = dirname(__FILE__);

/**
 * @var string The directory this script is being executed from all of my tests
 * were executed with this being in my home directory
 */
$workingDir = str_replace("\n", "", shell_exec("pwd"));

/**
 * @var string the current date time to be used in the file name
 */
$date = date('Y-m-d-H-i-s');

/**
 * @var string the name of the directory without the path
 */
$baseName = basename($directoryToArchive);

/**
 * @var string the name of the file on S3 the path to the current working path is
 * mimicked
 */
$s3Name = str_replace($workingDir . '/', '', $directoryToArchive).'-'.$date.'.tar.gz';

/**
 * @var string the name of the gziped file to upload
 */
$gzFile = "./$baseName.$date.tar.gz";

/**
 * Make sure to set up the the config.inc.php file in the aws folder
 */
require_once $currentDir . '/aws/sdk.class.php';

$s3 = new AmazonS3();

shell_exec("tar cf - $directoryToArchive | gzip -9 - > $gzFile");

$response = $s3->create_object(
    $bucketName,
    $s3Name,
    array(
        'fileUpload' => $gzFile,
        'acl' => AmazonS3::ACL_PRIVATE,
        'headers' => array( // raw headers
            'Cache-Control' => 'max-age',
            'Content-Encoding' => 'gzip',
            'Content-Language' => 'en-US',
            'Expires' => 'Thu, 01 Dec 1994 16:00:00 GMT',
        ),
        'meta' => array(
            'dir' => $directoryToArchive, 
        ),
    )
);

/**
 * If the file uploaded ok remove the source directory
 * otherwise print out a message for debugging
 */
if ($response->isOk()) {
    shell_exec("rm -fR $directoryToArchive");
} else {
    $tmpfname = tempnam("./", "s3-archive-error");
    
    $handle = fopen($tmpfname, "w");
    fwrite($handle, print_f($response, true));
    fclose($handle);
}

/**
 * Always clean up after yourself
 */
shell_exec("rm -f $gzFile");

