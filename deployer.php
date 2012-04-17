<?php
/**
 * Deployer
 * Copyright 2011, Xavier Decuyper
 *
 * Deploying web applications through git and github
 */
ini_set("log_errors", "On");
ini_set("error_log", "log.txt");
include("jsmin.php");
include("cssmin.php");

//
// ----------------------------------------------------------------------------
//  Configuration
// ----------------------------------------------------------------------------
//

// IP whitelist: list of IP's that are allowed to trigger deployer
// Default: 207.97.227.253, 50.57.128.197 (IPs of Github)
$ip_whitelist = array("207.97.227.253", "50.57.128.197", '108.171.174.178');


// Branch to deploy
// Default: refs/heads/deploy
$deploy_branch = "refs/heads/deploy";

// Log the JSON array, passed on by Github
// Default: false (it created really long log files)
$detailed_log = false;

// The project URL with /commit/ added to it
//$project_url = "https://github.com/Savjee/Project-ABC/commit/";

// The project name. Should be the same as <username-projectname>
$project_name = "Savjee-Project-ABC-";



//
// ----------------------------------------------------------------------------
//  First running security checks
// ----------------------------------------------------------------------------
//
$ip_remote = $_SERVER['REMOTE_ADDR'];

//
// Check if the request type is POST (Github only uses POST)
//
if($_SERVER['REQUEST_METHOD'] != "POST"){
	die(error_log("Error! Did not get a POST request. (IP: $ip_remote)", 0));
}


//
// Check if the IP adres is in whitelist
//
if (!in_array($ip_remote, $ip_whitelist) ) {
	die(
		error_log("Error! Unrecognized IP address: $remote_ip", 0)
	);
}


//
// Get the posted JSON & check if valid
//
$json = json_decode($_POST['payload']);

if($json === NULL ||$json === false){
	die(
		error_log("Error! No valid JSON array in payload $remote_ip", 0)
	);
}

//
// POST parameters loggen?
//
if($detailed_log){
	error_log("Got this JSON array from Github: " . $_POST['payload'], 0);
}else{
	error_log("Got POST message from Github.", 0);
}

//
// Was the deploy branch updated?
//
if($json->{'ref'} != $deploy_branch){
	die(
		error_log("Deploy branch not updated. Doing nothing.", 0)
	);
}

// Get the commit's hash
$commit_hash = substr($json->{'after'}, 0, 7);
error_log("Commit hash: ". $commit_hash, 0);

// Place the current commit hash in the currentBranch file
// this can be accessed by the application to show on which version it runs
error_log("Placing the current hash in the currentBranch file.", 0);
file_put_contents('currentBranch', $commit_hash);

//
// Wait 5 seconds. Github is processing the commit and zipball
//
error_log("Waiting 5 seconds before downloading zipball", 0);
usleep(5000000);

//
// Download the latest code from from Github
//
error_log("Downloading latest code from Github....", 0);
file_put_contents('deploy_commit.zip', file_get_contents('https://github.com/Savjee/Project-ABC/zipball/deploy'));
error_log("Download complete!", 0);

//
// Unzip the downloaded code
//
error_log("Unpacking directory: ". $project_name. $commit_hash, 0);
unzip("deploy_commit.zip");
error_log("Unpack finished.", 0);

//
// Move the new code to live version
//
error_log("Deploying...", 0);
copyr($project_name. $commit_hash, "../");
error_log("--> Deploy finished!", 0);

//
// Removing unpack data
//
error_log("Removing the unpack data...", 0);
rrmdir($project_name.$commit_hash);
error_log("Unpack data removed!", 0);

error_log("Done. Everything should be fine!", 0);
error_log("------------------------------------", 0);

//
// Function to unzip a zip file while using the correct rights (on UNIX systems)
// source: http://php.net/manual/en/ref.zip.php
//
function unzip($file){
    $zip = zip_open($file);
    if(is_resource($zip)){
        $tree = "";
        while(($zip_entry = zip_read($zip)) !== false){
            echo "Unpacking ".zip_entry_name($zip_entry)."\n";
            if(strpos(zip_entry_name($zip_entry), DIRECTORY_SEPARATOR) !== false){
                $last = strrpos(zip_entry_name($zip_entry), DIRECTORY_SEPARATOR);
                $dir = substr(zip_entry_name($zip_entry), 0, $last);
                $file = substr(zip_entry_name($zip_entry), strrpos(zip_entry_name($zip_entry), DIRECTORY_SEPARATOR)+1);
                if(!is_dir($dir)){
                    @mkdir($dir, 0755, true) or die("Unable to create $dir\n");
                }
                if(strlen(trim($file)) > 0){
                    $return = @file_put_contents($dir."/".$file, zip_entry_read($zip_entry, zip_entry_filesize($zip_entry)));
                    if($return === false){
                        die("Unable to write file $dir/$file\n");
                    }
                }
            }else{
                file_put_contents($file, zip_entry_read($zip_entry, zip_entry_filesize($zip_entry)) );
            }
        }
    }else{
        die(error_log("Error! Unable to open zip file. Corrupt?", 0));
    }
}


//
// Function to remove a not-empty directory
// source: http://php.net/manual/en/function.rmdir.php
//
function rrmdir($dir) {
  if (is_dir($dir)) {
    $objects = scandir($dir);
    foreach ($objects as $object) {
      if ($object != "." && $object != "..") {
        if (filetype($dir."/".$object) == "dir") rrmdir($dir."/".$object); else unlink($dir."/".$object);
      }
    }
    reset($objects);
    rmdir($dir);
  }
}


/**
 * Copy a file, or recursively copy a folder and its contents
 *
 * @author      Aidan Lister <aidan@php.net>
 * @link        http://aidanlister.com/2004/04/recursively-copying-directories-in-php/
 * @param       string   $source    Source path
 * @param       string   $dest      Destination path
 * @return      bool     Returns TRUE on success, FALSE on failure
 */
function copyr($source, $dest){

    // Simple copy for a file
    if (is_file($source)) {
		if( end(explode('.', $source)) == "css"){

			error_log("Found a CSS file. Compressing...", 0);
			file_put_contents($source, CssMin::minify(file_get_contents($source)) );
			error_log("CSS Compressing done.", 0);

		}

		if( end(explode('.', $source)) == "js"){
			error_log("Found a Javascript file. Compressing...", 0);
			file_put_contents($source, JSMin::minify(file_get_contents($source)) );
			error_log("Javascript Compressing done.", 0);
		}
        return copy($source, $dest);
    }

    // Make destination directory
    if (!is_dir($dest)) {
        mkdir($dest);
    }

    // Loop through the folder
    $dir = dir($source);
    while (false !== $entry = $dir->read()) {
        // Skip pointers
        if ($entry == '.' || $entry == '..') {
            continue;
        }

        // Deep copy directories
        copyr("$source/$entry", "$dest/$entry");
    }

    // Clean up
    $dir->close();
    return true;
}

?>