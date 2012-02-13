<?php
/**
 * Deployer
 * Copyright 2011, Xavier Decuyper
 * 
 * Deploying web applications through git and github
 *
 */
ini_set("log_errors", "On");
ini_set("error_log", "log.txt");

// Enable this is you want detailed logs
$detail_log = false;

// The branch you wish to deploy automaticly
$branch = "refs/heads/deploy";

// The project URL with /commit/ added to it
$project_url = "https://github.com/Savjee/Project-ABC/commit/";

// The project name. Should be the same as <username-projectname>
$project_name = "Savjee-Project-ABC-";

// Get the POST parameters from Github
$json = json_decode($_POST['payload']);

// POST parameters loggen?
if($detail_log){
	error_log("--> GOT THIS POST MESSAGE FROM GITHUB: " . $_POST['payload'], 0);
}else{
	error_log("--> GOT A POST MESSAGE FROM GITHUB.", 0);
}

if($json->{'ref'} != $branch){
	error_log("--> Deploy branch was not updated. Not downloading..", 0);
	die("Nothing was updated");
}

// Get the commit's hash
$commit_hash = substr($json->{'after'}, 0, 7);
error_log("--> This is the commit hash: ". $commit_hash, 0);

// Place the current commit hash in the currentBranch file
// this can be accessed by the application to show on which version it runs
error_log("--> Placing the current hash in the currentBranch file.", 0);
file_put_contents('currentBranch', $commit_hash);

//
// Wait 5 seconds. Github is processing the commit and zipball
//
error_log("--> Waiting 5 seconds", 0);
usleep(5000000);

//
// Download the latest code from from Github
//
error_log("--> Downloading latest code from Github....", 0);
file_put_contents('deploy_commit.zip', file_get_contents('https://github.com/Savjee/Project-ABC/zipball/deploy'));
error_log("--> Download complete!", 0);

//
// Unzip the downloaded code
//
error_log("--> Unpacking directory: ". $project_name. $commit_hash, 0);
unzip("deploy_commit.zip");
error_log("--> Unpack finished.", 0);

//
// Move the new code to live version
//
error_log("--> Deploying...", 0);
recurse_copy($project_name. $commit_hash, "../");
error_log("--> Deploy finished!", 0);

//
// Removing unpack data
//
error_log("--> Removing the unpack data...", 0);
rrmdir($project_name.$commit_hash);
error_log("--> Unpack data removed!", 0);

error_log("--> Done. Everything should be fine!", 0);
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
        echo "Unable to open zip file\n"; 
    } 
}


//
// Function to copy a directory's contents to another
// source: http://php.net/manual/en/function.copy.php
//
function recurse_copy($src,$dst) { 
    $dir = opendir($src); 
    @mkdir($dst); 
    while(false !== ( $file = readdir($dir)) ) { 
        if (( $file != '.' ) && ( $file != '..' )) { 
            if ( is_dir($src . '/' . $file) ) { 
                recurse_copy($src . '/' . $file,$dst . '/' . $file); 
            } 
            else { 
                copy($src . '/' . $file,$dst . '/' . $file); 
            } 
        } 
    } 
    closedir($dir); 
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

?>