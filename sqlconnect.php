<?php
session_start();
$servername = "192.168.115.253";
$username = "tubecuring";
$password = "tubecuring@2021";
$db_name = "TUBE";



try{
	$conn = new PDO("sqlsrv:server=$servername ; Database = $db_name", $username, $password);
	$conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
}
catch(Exception $e){
	die(print_r($e->getMessage()));
}


?>

