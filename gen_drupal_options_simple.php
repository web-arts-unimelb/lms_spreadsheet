<?php

include_once("config.php");
include_once("lib.php");

// Lib path
$php_excel_lib_path = "/var/www/test/testme/lms_spreadsheet_1/php_excel/Classes";

// Need to have a data only excel speadsheet
$input_file = "./input/arts_master_list.xlsx";

// Output contains files.
$output_folder = "drupal_output_simple";
$is_import_db = false;

// Set load sheet name
$sheet_name = "Arts";
$table_name = "drupal_subject_simple";
$curr_semester = "sm1";

set_include_path(get_include_path(). PATH_SEPARATOR. $php_excel_lib_path);
include_once 'PHPExcel/IOFactory.php';

$mysqli = new mysqli($hostname, $username, $password, $db_name);
if ($mysqli->connect_errno) 
{
    echo "Failed to connect to MySQL: " . $mysqli->connect_error;
}

__create_output_folder($output_folder);

if($is_import_db) {
	if(__is_table_empty($table_name))
	{
		__import_from_excel_to_db_simple($input_file, $sheet_name, $table_name);
	}
	else
	{
		// Truncate table, then import excel to db
		__truncate_table($table_name);
		__import_from_excel_to_db_simple($input_file, $sheet_name, $table_name);
	}
}

$curr_option = __output_drupal_option($table_name, $output_folder);
$staff_list = __output_drupal_staff_list($table_name, $output_folder);

// Write the drupal options to file
__write($curr_option, $output_folder, "curr_option.txt");
__write($staff_list, $output_folder, "staff_list.txt");

//close the connection
$mysqli->close();
echo "\nDone!\n";


// ---------------------------------- Define your functions here
function __output_drupal_option($table_name, $output_folder)
{
	global $mysqli;
	global $curr_semester;
	
	$return_string = "";
	$study_period_string = __build_study_period_sql_string($curr_semester);

	$sql = "
    SELECT
      CONCAT( CONCAT_WS('_', package_code, year, study_period), ' - ', subject_name ) AS my_column_name
    FROM 
      $table_name 
    ORDER BY
      my_column_name  
  ";

	/*	
	$sql = "
		SELECT
			CONCAT( CONCAT_WS('_', package_code, year, study_period), ' - ', subject_name ) AS my_column_name
		FROM 
			$table_name 
		WHERE 
			study_period IN ($study_period_string)
		ORDER BY
			my_column_name	
	";
	*/	

	$res = $mysqli->query($sql) or $mysqli->sqlstate;
	while($row = $res->fetch_array())
	{
		$return_string .= $row[0]. "\n";
	}

	return $return_string;
}

function __output_drupal_staff_list($table_name, $output_folder) {
	global $mysqli;
	global $curr_semester;
	
	$return_string = "";
	$study_period_string = __build_study_period_sql_string($curr_semester);
	
	$sql = "
		SELECT 
			DISTINCT CONCAT_WS(' ', first_name, last_name) AS my_column_name
		FROM 
			$table_name 
		WHERE 
			study_period IN ($study_period_string)
		ORDER BY
			my_column_name
	";
	
	$res = $mysqli->query($sql) or $mysqli->sqlstate;
	while($row = $res->fetch_array())
	{
		$return_string .= $row[0]. "\n";
	}

	return $return_string;	
}
