<?php

define("FILE_PREREQUISITES_CONFIG", "prerequisites.ini"); // this file contains the definitions of the prerequisites that are being checked
include_once('DatabaseUtils.class.php');

$usage_string = 'Usage is php '.__FILE__.' <apachectl> <db host> <db port> <db user> <db pass>'.PHP_EOL;
$usage_string .= 'Prints all the missing prerequisites and exits with code 0 if all verifications passes and 1 otherwise'.PHP_EOL;

if (count($argv) < 5) {
	echo $usage_string;
	exit(1);
}

// get user arguments
$db_params = array();
$httpd_bin = trim($argv[1]);
$db_params['db_host'] = trim($argv[2]);
$db_params['db_port'] = trim($argv[3]);
$db_params['db_user'] = trim($argv[4]);
if (count($argv) > 4) $db_params['db_pass'] = trim($argv[5]);
else $db_params['db_pass'] = "";

$prerequisites_config = parse_ini_file(FILE_PREREQUISITES_CONFIG, true);
$prerequisites = "";

// check php version
if (!(intval(phpversion()) >= intval($prerequisites_config["php_min_version"]))) {
	$prerequisites .= "PHP version should be >= $php_min_version (current version is ".phpversion().")".PHP_EOL;
}
	
// check php extensions
foreach ($prerequisites_config["php_extensions"] as $ext) {
	if (!extension_loaded($ext)) {
		$prerequisites .= "Missing $ext PHP extension".PHP_EOL;
	}
}

// check mysql
if (!extension_loaded('mysqli')) {
	$prerequisites .= "Cannot check MySQL connection, version and settings because PHP mysqli extension is not loaded".PHP_EOL;
} else if (!DatabaseUtils::connect($link, $db_params, null)) {
		$prerequisites .= "Failed to connect to database ".$db_params['db_host'].":".$db_params['db_port']." user:".$db_params['db_user'].PHP_EOL;
} else {
	// check mysql version and settings
	$mysql_version = getMysqlSetting($link, 'version'); // will always return the value
	if (intval($mysql_version) < intval($prerequisites_config["mysql_min_version"])) {
		$prerequisites .= "MySQL version should be >= ".$prerequisites_config["mysql_min_version"]." (current version is $mysql_version)".PHP_EOL;
	}
	
	$lower_case_table_names = getMysqlSetting($link, 'lower_case_table_names');
	if (!isset($lower_case_table_names)) {
		$prerequisites .= "Please set 'lower_case_table_names = ".$prerequisites_config["lower_case_table_names"]."' in my.cnf and restart MySQL".PHP_EOL;
	} else if (intval($lower_case_table_names) != intval($prerequisites_config["lower_case_table_names"])) {
		$prerequisites .= "Please set 'lower_case_table_names = ".$prerequisites_config["lower_case_table_names"]."' in my.cnf and restart MySQL (current value is $lower_case_table_names)".PHP_EOL;
	}
	
	$thread_stack = getMysqlSetting($link, 'thread_stack');
	if (!isset($thread_stack)) {
		$prerequisites .= "Please set 'thread_stack >= ".$prerequisites_config["thread_stack"]."' in my.cnf and restart MySQL".PHP_EOL;
	} else if (intval($thread_stack) < intval($prerequisites_config["thread_stack"])) {
		$prerequisites .= "Please set 'thread_stack >= ".$prerequisites_config["thread_stack"]."' in my.cnf and restart MySQL (current value is $thread_stack)".PHP_EOL;
	}	
}

// check apache modules
@exec("$httpd_bin -M 2>&1", $current_modules, $exit_code);
if ($exit_code !== 0) {
	$prerequisites .= "Cannot check apache modules, please make sure that '$httpd_bin -t' command runs properly".PHP_EOL;
} else {	
	foreach ($prerequisites_config["apache_modules"] as $module) {
		$found = false;
		
		for ($i=0; !$found && $i<count($current_modules); $i++) {
			if (strpos($current_modules[$i],$module) !== false) {
				$found = true;
			}				
		}
		
		if (!$found) {
			$prerequisites .= "Missing $module Apache module".PHP_EOL;
		}
	}
}	

// check binaries
foreach ($prerequisites_config["binaries"] as $bin) {
	@exec("which $bin", $output, $exit_code);		
	if ($exit_code !== 0) {
		$prerequisites .= "Missing $bin binary file".PHP_EOL;
	}
}

// check pentaho exists
if (!is_file($prerequisites_config["pentaho_path"])) {
	$prerequisites .= "Missing pentaho at $pentaho".PHP_EOL;
}

// check if something is missing and exit accordingly
if (empty($prerequisites)) {
	exit(0);
} else {	
	echo $prerequisites;
	exit(1);
}

// checks if the mysql settings $key is as $expected using the db $link
// if $allow_greater it also checks if the value is greater the the $expected (not only equal)
function getMysqlSetting(&$link, $key) {	
	$result = mysqli_query($link, "SELECT @@$key;");
	if ($result === false) {
		return null;
	} else {			
		$tmp = '@@'.$key;
		$current = $result->fetch_object()->$tmp;
		return $current;
	}		
}