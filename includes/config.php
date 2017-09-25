<?php
session_start();
error_reporting(E_ERROR | E_WARNING | E_PARSE);

$applicationName = "Jaku v1.0";

switch ($_SERVER["HTTP_HOST"]) {
	case 'localhost':
		$pathAPP="/jaku/"; 
		$mysqlHost="localhost";
		$mysqlDB="jaku";
		$mysqlUser="root";
		$mysqlPassword="";
		break;
	case 'http://www.jakuuu.byethost22.com':
		$pathAPP="/";
		$mysqlHost="sql307.byethost22.com";
		$mysqlDB="b22_20140847_jaku";
		$mysqlKorisnik="b22_20140847";
		$mysqlLozinka="ThhKR5K78";
		break;
	default:
		$pathAPP="/";
		break;
}



try{
	$pdo = new PDO("mysql:host=" . $mysqlHost. ";dbname=" . $mysqlDB,$mysqlUser,$mysqlPassword);
	$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
	$pdo->setAttribute(PDO::ATTR_EMULATE_PREPARES, false);
	$pdo->exec("SET CHARACTER SET utf8");
	$pdo->exec("SET NAMES utf8");
}catch(PDOException $e){
	switch ($e->getCode()) {
		case 2002:
			echo "Can't connect to MySQL server";
			break;
		case 1049:
			echo "There is database with that name on MySQL server";
			break;
		case 1045:
			echo "There is no combination of that username and password on MySQL server";
			break;
		default:
			print_r($e);
			break;
	}
	exit;
}
