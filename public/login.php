<?php 

include_once '../includes/config.php'; 
include_once '../includes/redirect_if_logged_in.php';

if($_SERVER["REQUEST_METHOD"] == "POST") {
	// username and password sent from form 

	$username = $_POST['user'];
	$password = $_POST['password'];

	echo $username;
	echo $password;
	echo strlen($username);

	$stmt = $pdo->prepare('SELECT * FROM users WHERE username=? AND password=?');
	$stmt->execute([$username, $password]);
	$user = $stmt->fetchAll();

	$count = count($user);
	
	if($count == 1) {
		$_SESSION['user_id'] = $user[0]["user_id"];
	 
	 	header("location: ../videostore.php");
	 	return;
	}else {
	 	header("location: login.php?unsuccessful"); 
	 	return;
	}
}

?>

<!DOCTYPE html>
<html lang="en">
  <head>
  	<?php include_once '../template/head.php';  ?>
  </head>

  <body>

    <?php include_once '../template/menu.php';  ?>

    <div class="row">
    	<div class="col-md-2 col-md-offset-5">

    			<h2 style="width: 100%; text-align: center"><?php echo $applicationName ?></h2>

				
					<form method="post">
						<div class="form-group">
						<label for="korisnik">Username</label>
						<input type="text" class="form-control" placeholder="Enter your username" name="user" id="user" value="<?php echo isset($_GET["user"]) ? $_GET["user"] : ""; ?>"/>
						</div>
						<div class="form-group">
						<label for="lozinka">Password</label>
						<input type="password" class="form-control" placeholder="Enter your password" name="password" id="password" />
						</div>
						<input type="submit" class="btn btn-primary" value="Login" /><br><br>
	      				<?php 
		      			if(isset($_GET["unsuccessful"])){
							echo "Invalid username or password!";
						}
						
						if(isset($_GET["noPermission"])){
							echo "You must log in first!";
						}
								
						if(isset($_GET["loggedOut"])){
							echo "You have successfully logged out!";
						}
						?>
					</form>
     	</div>
    </div><!-- /.container -->

	<?php include_once '../template/scripts.php'; ?>

  </body>
</html>
