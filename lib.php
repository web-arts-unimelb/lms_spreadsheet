<?php

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

function __import_from_excel_to_db_simple($input_file, $sheet_name, $table_name)
{
	global $mysqli;

	$input_file_type = PHPExcel_IOFactory::identify($input_file);
	$obj_reader = PHPExcel_IOFactory::createReader($input_file_type);
	$obj_reader->setLoadSheetsOnly($sheet_name); 
	$excel_obj = $obj_reader->load($input_file);
	
	$sheet_data = $excel_obj->getActiveSheet()->toArray(null,true,true,true);
	
	$counter = 0;
	$row_count = 0;
	$max_row_num = 1491;
	foreach($sheet_data as $row)
	{	
		if($row_count >= $max_row_num) {
			break;
		}
	
		// Ignore 1st and 2nd rows
		if($row_count <= 1) {
			++$row_count;
			continue;
		}
		
		// School is empty, skip
		$school = trim($row["D"]);
		if( empty($school) ) {
			continue;
		}

		// Different schools, but has same teaching_org_id					
		$school = $mysqli->real_escape_string( trim($row["D"]) ); 

		$subject_id = trim($row["F"]);
		$parts = explode("_", $subject_id, 3);
		
		$package_code = $mysqli->real_escape_string( $parts[0] );
		$year = $mysqli->real_escape_string( $parts[1] );
		$study_period = $mysqli->real_escape_string( $parts[2] );
		
		$subject_name = $mysqli->real_escape_string( trim($row["G"]) );
		$first_name = $mysqli->real_escape_string( trim($row["I"]) );
		$last_name = $mysqli->real_escape_string( trim($row["J"]) );
		
		// Remove duplication
		if(!__subject_exists($table_name, $package_code, $study_period, $year)) {
			$sql = "
				INSERT INTO 
					$table_name 
				SET
					school = '$school', 
					package_code = '$package_code',
					year = '$year',
					study_period = '$study_period',
					
					subject_name = '$subject_name',
					first_name = '$first_name',
					last_name = '$last_name'
			";
			
			if(!$mysqli->query($sql)) 
			{
		  	printf("Error: %s\n", $mysqli->sqlstate);
			}
			
			++$counter;
		}
		
		++$row_count;
	}
	
	echo "\nImport num: $counter\n";
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
		//echo "Read $package_code, $study_period, $year, $subject_name, $school, $teaching_org_id\n";
		
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
			
			// Insert
			//echo "Insert $package_code, $study_period, $year, $subject_name, $school, $teaching_org_id\n";
		
			if(!$mysqli->query($sql)) 
			{
		  	printf("Error: %s\n", $mysqli->sqlstate);
			}
			
			++$counter;
		}
	}
	
	echo "\nImport num: $counter\n";
}




function __get_teaching_org_ids($year, $table_name)
{
	global $mysqli;

	$return_array = array();
	
	$sql = "
		SELECT 
			DISTINCT teaching_org_id 
		FROM 
			$table_name
		WHERE
			year = '$year'	
	";
	
	$res = $mysqli->query($sql) or $mysqli->sqlstate;
	
	while($row = $res->fetch_array())
	{
		$return_array[] = $row[0];
	}

	return $return_array;
}

function __get_study_periods($year, $teaching_org_id, $table_name)
{
	global $mysqli;
	$return_array = array();
	
	$sql = "
		SELECT 
			DISTINCT study_period
		FROM
			$table_name
		WHERE
			teaching_org_id = '$teaching_org_id' AND
			year = '$year'
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

function __get_school_subjects($year, $study_period, $teaching_org_id, $table_name)
{
	global $mysqli;
	$return_array = array();
	$study_period = $mysqli->real_escape_string($study_period);

	// Most of subject_code: PPMN40005_2011_SM2
	$sql = "
		SELECT 
			DISTINCT subject_name
		FROM 
			$table_name 
		WHERE
			year = '$year' AND
			teaching_org_id = '$teaching_org_id' AND
			study_period = '$study_period'
		ORDER BY
			package_code
	";

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

// Study period is changing each time
function __get_sm1_study_periods()
{	
	$array = array(
		'JAN',
		'FEB',
		'MAR',
		'MAR_PAR_2',
		'APR',
		"APR_PAR_2",
		"MAY",
		"MAY_PAR_2",
    "JUN",
    "SM1",
    "SM1_PAR_2",
    "SM1_PAR_3",
    "SUM",
    "SUM_SIN_1",
    "RS1",
	);
	
	return $array;
}


function __get_sm2_study_periods()
{	
	$array = array(
		"JUL",
		"JUL_PAR_2",
    "AUG",
    "AUG_PAR_2",
    "SEP",
    "SEP_PAR_2",
    "OCT",
    "NOV",
    "NOV_PAR_2",
    "SM2",
    "SM2_PAR_2",
    "SM2_PAR_3",
    "WIN",
    "RS2"
	);
	
	return $array;
}

function __write($option_text, $output_folder, $filename)
{
	$fp = fopen($output_folder. "/". $filename, 'w');
	fwrite($fp, $option_text);
	fclose($fp);
}

function __build_study_period_sql_string($semester) {
	$study_period_string = "";

	$myfunc = "__get_". $semester. "_study_periods";
	
	$study_periods = $myfunc();
	$total_num = count($study_periods);
	$count = 1;
	foreach($study_periods as $study_period) {
		if($count <= $total_num - 1) {
			$study_period_string .= "'". $study_period. "',";
		}
		else {
			$study_period_string .= "'". $study_period. "'";
		}
		
		++$count;
	}
	
	return $study_period_string;
}
