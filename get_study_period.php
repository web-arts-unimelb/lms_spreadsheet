<?php

include_once("config.php");

$mysqli = new mysqli($hostname, $username, $password, $db_name);
if($mysqli->connect_errno) 
{
    echo "Failed to connect to MySQL: " . $mysqli->connect_error;
}

$array = __get_study_periods($table_name);
print_r($array);


// Define your functions
function __get_study_periods($table_name)
{
	global $mysqli;
	$current_year = 2013;
		
	$sql = "
		SELECT 
			DISTINCT study_period
		FROM
			$table_name
		WHERE
			year = '$current_year'
		ORDER BY
			study_period
	";
	
	//die($sql);
	
	$res = $mysqli->query($sql) or $mysqli->sqlstate;
	while($row = $res->fetch_array())
	{
		$return_array[] = $row[0];
	}

	return $return_array;
}

/*
		[0] => APR
    [1] => AUG
    [2] => FEB
    [3] => JAN
    [4] => JUL
    [5] => JUN
    [6] => MAR
    [7] => MAY
    [8] => MDYI
    [9] => NOV
    [10] => OCT
    [11] => RS1
    [12] => RS2
    [13] => SEP
    [14] => SM1
    [15] => SM2
    [16] => STYI
    [17] => SUM
    [18] => SUMI
    [19] => WIN
    
    1st half
    JAN
    FEB
    MAR
    APR
    MAY
    JUN
    SM1
    WIN
    RS1
    
    2nd half
    JUL
    AUG
    SEP
    OCT
    NOV
    SM2
    SUM
    SUMI
    RS2
    
    not sure
    MDYI
    STYI
*/

