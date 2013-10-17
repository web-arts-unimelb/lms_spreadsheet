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
