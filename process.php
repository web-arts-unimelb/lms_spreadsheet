<?php

include_once("config.php");

// Lib path
$php_excel_lib_path = "/var/www/test/testme/lms_spreadsheet_1/php_excel/Classes";

// NOTE: Change it to sm1 or sm2 (semeter 1 or semester 2)
$current_study_period = "sm2";
$current_year = "2013";

// Need to have a data only excel speadsheet
$input_file = "./input/lms.xlsx";

// Output contains html files.
$output_folder = "output";

// Set load sheet name
$sheet_name = "RAW";
$table_name = "subjects_1";

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

// Output html
__output_html($table_name, $output_folder);

//close the connection
$mysqli->close();
echo "\nDone!\n";



// ---------------------------------- Define your functions here
function __output_html($table_name, $output_folder)
{
	$return_drupal_options = "";
	$teaching_org_ids = __get_teaching_org_ids($table_name);
	
	foreach($teaching_org_ids as $teaching_org_id)
	{
		$return_html = "";
		
		$school_name = __teaching_org_id_to_school_name($teaching_org_id);
		$return_html .= "<option value=\"\" selected=\"selected\">$school_name</option>\n";
	
		// Handle unlisted subject
		$return_html .= "<option value=\"\">&nbsp;</option>\n";
		$return_html .= "<option value=\"My subject is not listed\">My subject is not listed</option>\n";
		$return_html .= "<option value=\"\">&nbsp;</option>\n";
	
		$study_period_array = __get_study_periods($teaching_org_id, $table_name);
		foreach($study_period_array as $study_period)
		{
			if(__is_current_study_period($study_period))
			{
				$return_html .= "\n<option value=\"\">---- start study period: $study_period ----</option>\n";
				$subject_array = __get_school_subjects($study_period, $teaching_org_id, $table_name);
				foreach($subject_array as $subject_name)
				{
					// Output html files
					$return_html .= __build_option_html($subject_name, $study_period, $teaching_org_id, $table_name);
					
					// Output options for drupal options
					$return_drupal_options .= __build_drupal_options($subject_name, $study_period, $teaching_org_id, $table_name);
				}

				// Make some space
				$return_html .= "<option value=\"\">&nbsp;</option>\n";
			}
			else
			{
				$return_html .= "";
				$return_drupal_options .= "";
			}
		}
		
		// Write the html to file
		$filename = __teaching_org_id_to_html_file_name($teaching_org_id);
		$fp = fopen($output_folder. "/". $filename, 'w');
		fwrite($fp, $return_html);
		fclose($fp);
	}
	
	// Write the drupal options to file
	$fp = fopen($output_folder. "/drupal_options.txt", 'w');
	fwrite($fp, $return_drupal_options);
	fclose($fp);
}

function __get_study_periods($teaching_org_id, $table_name)
{
	global $mysqli;
	global $current_year;
	
	$return_array = array();
	
	$sql = "
		SELECT 
			DISTINCT study_period
		FROM
			$table_name
		WHERE
			teaching_org_id = '$teaching_org_id' AND
			year = '$current_year'
		ORDER BY
			study_period
	";
	
	$res = $mysqli->query($sql) or $mysqli->sqlstate;
	while($row = $res->fetch_array())
	{
		$return_array[] = $row[0];
	}

	return $return_array;
}


function __is_current_study_period($study_period)
{
	$study_periods = __get_current_study_periods(); 
	if(in_array($study_period, $study_periods))
	{
		return true;
	}
	else
	{
		return false;
	}
}

function __get_current_study_periods()
{
	global $current_study_period;
	$return_array = null;
	
	if($current_study_period == "sm1")
	{
		$return_array = __get_sm1_study_periods();
	}
	elseif($current_study_period == "sm2")
	{
		$return_array = __get_sm2_study_periods();
	}
	else
	{
		die("study period error.");
	}

	return $return_array;
}


// Study period is changing each time
function __get_sm1_study_periods()
{
	$array = array(
		"JAN",
    "FEB",
    "MAR",
    "APR",
    "MAY",
    "JUN",
    "SM1",
    "WIN",
    "RS1",
    "MDYI"
	);
	
	return $array;
}


function __get_sm2_study_periods()
{
	$array = array(
		"JUL",
    "AUG",
    "SEP",
    "OCT",
    "NOV",
    "SM2",
    "WIN",
    "RS2"
	);
	
	return $array;
}


function __get_school_subjects($study_period, $teaching_org_id, $table_name)
{
	global $mysqli;
	global $current_year;

	$return_array = array();

	$study_period = $mysqli->real_escape_string($study_period);

	// Most of subject_code: PPMN40005_2011_SM2
	
	$sql = "
		SELECT 
			DISTINCT subject_name
		FROM 
			$table_name 
		WHERE
			year = '$current_year' AND
			teaching_org_id = '$teaching_org_id' AND
			study_period = '$study_period'
		ORDER BY
			package_code
	";

	$res = $mysqli->query($sql) or $mysqli->sqlstate;
	
	while($row = $res->fetch_array())
	{
		$return_array[] = $row[0];
		
		//test only
		//echo "subject_name: ". $row[0]. "\n";
		//echo "subject_code:". $row[1]. "\n";
		//echo "---------------------------\n";
	}

	return $return_array;
}

function __build_option_html($subject_name, $study_period, $teaching_org_id, $table_name)
{
	global $mysqli;
	global $current_year;

	$return_html = "";
	
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
			year = '$current_year'
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
			$subject_code_array[] = $row[0];
		}
		
		$subject_code_string = implode(" / ", $subject_code_array);
		$string = $subject_code_string. " -- ". $subject_name;	
		$return_html = "<option value=\"$string\">$string</option>\n";	
	}
	elseif($num == 1)
	{
		while($row = $res->fetch_array())
		{
			$subject_code = $row[0];
			$string = $subject_code. " -- ". $subject_name;
			$return_html = "<option value=\"$string\">$string</option>\n";
		}
	}
	
	return $return_html;
}

function __build_drupal_options($subject_name, $study_period, $teaching_org_id, $table_name)
{
	global $mysqli;
	global $current_year;

	$return_options = "";
	
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
			year = '$current_year'
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
			$subject_code_array[] = $row[0];
		}
		
		$subject_code_string = implode(" / ", $subject_code_array);
		$string = $subject_code_string. " -- ". $subject_name;	
		$return_options = "$string\n";	
	}
	elseif($num == 1)
	{
		while($row = $res->fetch_array())
		{
			$subject_code = $row[0];
			$string = $subject_code. " -- ". $subject_name;
			$return_options = "$string\n";
		}
	}
	
	return $return_options;
}

function __create_html_filename($school_name)
{
	$return_filename = "";
	
	$school_name = strtolower($school_name);
	$filename = str_replace(" ", "_", $school_name);
	$return_filename = $filename. ".html";
	
	
	return $return_filename;
}

function __teaching_org_id_to_school_name($id)
{
	$return_name = "";
	
	if($id == "100")
	{
		// Arts become Other
		$return_name = "Other";
	}
	elseif($id == "106")
	{
		$return_name = "Culture and Communication";
	}
	elseif($id == "131")
	{
		$return_name = "Historical and Philosophical Studies";
	}
	elseif($id == "166")
	{
		$return_name = "Social and Political Sciences";
	}
	elseif($id == "110")
	{
		$return_name = "Asia Institute";
	}
	elseif($id == "119")
	{
		$return_name = "Languages and Linguistics";
	}
	elseif($id == "114")
	{
		$return_name = "Graduate School of Humanities and Social Sciences";
	}

	return $return_name;
}

function __teaching_org_id_to_html_file_name($id)
{
	$return_name = "";
	
	if($id == "100")
	{
		// Arts become Other
		$return_name = "other";
	}
	elseif($id == "106")
	{
		$return_name = "cc";
	}
	elseif($id == "131")
	{
		$return_name = "hps";
	}
	elseif($id == "166")
	{
		$return_name = "sps";
	}
	elseif($id == "110")
	{
		$return_name = "ai";
	}
	elseif($id == "119")
	{
		$return_name = "ll";
	}
	elseif($id == "114")
	{
		$return_name = "gshss";
	}

	return $return_name. ".html";
}

function __create_output_folder($output_folder)
{
	if(is_dir($output_folder))
	{
		__rrmdir($output_folder);	
	}

	mkdir($output_folder, 0777);
}


// Recursive deleting
function __rrmdir($dir) 
{
	if(is_dir($dir)) 
	{
		$objects = scandir($dir);
		foreach($objects as $object) 
		{
			if($object != "." && $object != "..") 
			{
				if(filetype($dir."/".$object) == "dir") 
					rrmdir($dir."/".$object); 
				else unlink($dir."/".$object);
			}
		}
		reset($objects);
		rmdir($dir);
	}
}

function __is_table_empty($table_name)
{
	global $mysqli;

	$sql = "SELECT * FROM $table_name";
	$res = $mysqli->query($sql) or $mysqli->sqlstate;
	$num = $res->num_rows;
	$res->free();
	
	if($num > 0)
	{
		return false;
	}
	else
	{
		return true;
	}
}


function __truncate_table($table_name)
{
	global $mysqli;
	
	$sql = "TRUNCATE $table_name";
	$res = $mysqli->query($sql) or $mysqli->sqlstate;
}

function __import_from_excel_to_db($input_file, $sheet_name, $table_name)
{
	global $mysqli;

	$input_file_type = PHPExcel_IOFactory::identify($input_file);
	$obj_reader = PHPExcel_IOFactory::createReader($input_file_type);
	$obj_reader->setLoadSheetsOnly($sheet_name); 
	$excel_obj = $obj_reader->load($input_file);
	
	$sheet_data = $excel_obj->getActiveSheet()->toArray(null,true,true,true);
	
	$is_first_row = true;
	$counter = 0;
	
	foreach($sheet_data as $row)
	{
		// Ignore first row
		if($is_first_row)
		{
			$is_first_row = false;
			continue;
		}
		
		// If package code is empty, skip
		$school = trim($row["A"]);
		if( empty($school) )
		{
			continue;
		}
			
		$school = $mysqli->real_escape_string( trim($row["A"]) ); // Different schools, but has same teaching_org_id 
		$teaching_org_id = $mysqli->real_escape_string( trim($row["B"]) );
		$package_code = $mysqli->real_escape_string( trim($row["C"]) );
		$year = $mysqli->real_escape_string( trim($row["D"]) );
		$study_period = $mysqli->real_escape_string( trim($row["E"]) );
		$subject_name = $mysqli->real_escape_string( trim($row["F"]) );
		
		// What do we read
		//echo "\nRead $package_code, $study_period, $year, $subject_name, $school, $teaching_org_id\n";
		
		// Remove duplication
		if(!__subject_exists($table_name, $package_code, $study_period, $year)) {
			$sql = "
				INSERT INTO 
					$table_name 
				SET 
					package_code = '$package_code',
					year = '$year',
					study_period = '$study_period',
					subject_name = '$subject_name',
					school = '$school',
					teaching_org_id = '$teaching_org_id'
			";
			
			//echo "\nInsert $package_code, $study_period, $year, $subject_name, $school, $teaching_org_id\n";
		
			if(!$mysqli->query($sql)) 
			{
		  	printf("Error: %s\n", $mysqli->sqlstate);
			}
			
			++$counter;
		}
	}
	
	echo "\nImport num: $counter\n";
}

function __get_teaching_org_ids($table_name)
{
	global $mysqli;

	$return_array = array();
	
	$sql = "SELECT DISTINCT teaching_org_id from $table_name";
	$res = $mysqli->query($sql) or $mysqli->sqlstate;
	
	while($row = $res->fetch_array())
	{
		$return_array[] = $row[0];
	}

	return $return_array;
}


function __subject_exists($table_name, $package_code, $study_period, $year) {
	global $mysqli;

	$sql = "
		SELECT 
			*
		FROM
			$table_name 
		WHERE 
			package_code = '$package_code' AND
			year = '$year' AND
			study_period = '$study_period'
	";
	
	$res = $mysqli->query($sql) or $mysqli->sqlstate;
	$num = $res->num_rows;
	
	if($num > 0) {
		return true;
	}
	else {
		return false;
	}
}
