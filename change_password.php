<?php 
include_once 'includes/config.php'; 
include_once 'includes/redirect_if_logged_out.php';

if(isset($_POST["oldPass"]) && isset($_POST["newPass1"]) && isset($_POST["newPass2"]))
{
	$stmt = $pdo->prepare('SELECT * FROM users WHERE user_id=?');
	$stmt->execute([$user_id]);
	$users = $stmt->fetchAll();
	if(count($users) != 1){
	    header("location: public/login.php?noPermission");
	    return;
	}

	if($_POST["oldPass"] !== $users[0]["password"])
	{
		header("location: change_password.php?badPass");
		return;
	}
	else if($_POST["newPass1"] !== $_POST["newPass2"] || $_POST["newPass1"] === ""){
		header("location: change_password.php?mismatch");
		return;
	}

	$stmt = $pdo->prepare("UPDATE users SET password=? WHERE user_id=?");
	$stmt->execute([$_POST["newPass1"], $user_id]);

	header("location: logout.php");
	return;
}
?>


<!DOCTYPE html>
<html lang="en">
  <head>
  	<?php include_once 'template/head.php';  ?>
  </head>

  <body>

<?php include_once 'template/menu.php';  ?>
<div class="container">
    	<div class="col-sm-4">

			<h2 >Change password</h2>
			<br><br>
			
			<form method="post">
				<div class="form-group">
					<label for="oldPass">Old password:</label>
					<input type="text" class="form-control" placeholder="Enter your old password" name="oldPass" id="oldPass"/>
				</div>
				<div class="form-group">
					<label for="lozinka">New password:</label>
					<input type="password" class="form-control" placeholder="Enter your new password" name="newPass1" id="newPass1" />
				</div>
				<div class="form-group">
					<label for="lozinka">Repeat new password:</label>
					<input type="password" class="form-control" placeholder="Enter your new password" name="newPass2" id="newPass2" />
				</div>
				<br>
				<input type="submit" class="btn btn-primary" value="Change password" /><br><br>
			</form>
			<?php 
  			if(isset($_GET["badPass"])){
				echo "Wrong password. Please try again!";
			}
			
			if(isset($_GET["mismatch"])){
				echo "Your new passwords do not match!";
			}
			?>
		</div>

</div><!-- /.container -->

	<?php include_once 'template/scripts.php'; ?>

  </body>
</html>
