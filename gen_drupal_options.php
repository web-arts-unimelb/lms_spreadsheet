<?php

include_once("config.php");
include_once("lib.php");

// Lib path
$php_excel_lib_path = "/var/www/test/testme/lms_spreadsheet_1/php_excel/Classes";

// Need to have a data only excel speadsheet
$input_file = "./input/lms_cross_year.xlsx";

// Output contains html files.
$output_folder = "drupal_output";

// Set load sheet name
$sheet_name = "RAW";
$table_name = "drupal_subject";
$prev_year = "2012";
$curr_year = "2013";
$single_subject_per_line = true; // One subject per line in drupal option


/* 
	The spreadsheet is like:
	package code, year, period code, subject name, other_columns_not_cared
	.....
*/

set_include_path(get_include_path(). PATH_SEPARATOR. $php_excel_lib_path);
include_once 'PHPExcel/IOFactory.php';

$mysqli = new mysqli($hostname, $username, $password, $db_name);
if ($mysqli->connect_errno) 
{
    echo "Failed to connect to MySQL: " . $mysqli->connect_error;
}

__create_output_folder($output_folder);

if(__is_table_empty($table_name))
{
	__import_from_excel_to_db($input_file, $sheet_name, $table_name);
}
else
{
	// Truncate table, then import excel to db
	__truncate_table($table_name);
	__import_from_excel_to_db($input_file, $sheet_name, $table_name);
}


$prev_option = __output_drupal_option($prev_year, $table_name, $output_folder);
$curr_option = __output_drupal_option($curr_year, $table_name, $output_folder);

// Write the drupal options to file
__write($prev_option, $output_folder, "prev_option.txt");
__write($curr_option, $output_folder, "curr_option.txt");
__write($prev_option. $curr_option, $output_folder, "cross_option.txt");

/*
//test
echo "\nprev\n";
echo $prev_option;

echo "\ncurr\n";
echo $curr_option;
*/

//close the connection
$mysqli->close();
echo "\nDone!\n";


// ---------------------------------- Define your functions here
function __output_drupal_option($year, $table_name, $output_folder)
{
	$return_option = "";
	//test
	//echo "\nyear: $year\n";
	
	$teaching_org_ids = __get_teaching_org_ids($year, $table_name);
	foreach($teaching_org_ids as $teaching_org_id)
	{
		//test
		//echo "\nteaching org: $teaching_org_id\n";
	
		$study_period_array = __get_study_periods($year, $teaching_org_id, $table_name);
		foreach($study_period_array as $study_period)
		{
			//test
			//echo "\nstudy period: $study_period\n";
		
			$subject_array = __get_school_subjects($year, $study_period, $teaching_org_id, $table_name);
			
			//test
			//print_r($subject_array);
			
			foreach($subject_array as $subject_name)
			{
				$return_option .= __build_drupal_option($year, $study_period, $teaching_org_id, $subject_name, $table_name);
			}
		}
	}
	
	return $return_option;
}

function __build_drupal_option($year, $study_period, $teaching_org_id, $subject_name, $table_name)
{
	global $mysqli;
	global $single_subject_per_line;
	
	$return_option = "";	
	
	$sql_subject_name = $mysqli->real_escape_string($subject_name);
	$sql_study_period = $mysqli->real_escape_string($study_period);
	
	$sql = "
		SELECT
			CONCAT_WS('_', package_code, study_period, year)
		FROM
			$table_name 
		WHERE 
			subject_name = '$sql_subject_name' AND
			study_period = '$sql_study_period' AND
			teaching_org_id = '$teaching_org_id' AND
			year = '$year'
	";
	
	$res = $mysqli->query($sql) or $mysqli->sqlstate;
	
	if($res->num_rows)
	{	
		$num = $res->num_rows;
	}
	else
	{
		die($sql);
	}
	
	if($num > 1)
	{
		$subject_code_array = array();
		while($row = $res->fetch_array())
		{
			if(!in_array($row[0], $subject_code_array)) {
				$subject_code_array[] = $row[0];
			}
		}
		
		// In drupal, you can add multiple subject by adding more button
		if($single_subject_per_line) {
			foreach($subject_code_array as $single_subject_code) {
				$return_option .= $single_subject_code. " - ". $subject_name. "\n";
			}
		}
		else {
			$subject_code_string = implode(" / ", $subject_code_array);
			$string = $subject_code_string. " - ". $subject_name;	
			$return_option = "$string\n";
		}
	}
	elseif($num == 1)
	{
		while($row = $res->fetch_array())
		{
			$subject_code = $row[0];
			$string = $subject_code. " -- ". $subject_name;
			$return_option = "$string\n";
			break;
		}
	}
	
	return $return_option;
}

function __write($option_text, $output_folder, $filename)
{
	$fp = fopen($output_folder. "/". $filename, 'w');
	fwrite($fp, $option_text);
	fclose($fp);
}
