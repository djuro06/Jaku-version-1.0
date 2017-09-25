<?php 
	
require_once("authorization.php");

if(isset($user_id)){
	header("location: ".$pathAPP."index.php");
	return;
}

?>