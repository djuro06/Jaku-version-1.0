<?php 

include_once '../includes/config.php'; 
include_once '../includes/redirect_if_logged_in.php';

if($_SERVER["REQUEST_METHOD"] == "POST") {
	// username and password sent from form 

	$username = $_POST['username'];
	$pwd1 = $_POST['pwd1'];
	$pwd2 = $_POST['pwd2'];
	$email = $_POST['email'];
	$name = $_POST['name'];
	$surname = $_POST['surname'];
	$personalid = $_POST['personalid'];

	if(strlen($username)==0 || strlen($email)==0 || strlen($pwd1)==0 || strlen($name)==0 || strlen($surname)==0  || strlen($personalid)==0){
		header("location: register.php?unsuccessful");
		return;
	}else if($pwd1 != $pwd2){
		header("location: register.php?mismatch");
		return;
	}

	$stmt = $pdo->prepare('SELECT * FROM users WHERE username=?');
	$stmt->execute([$username]);
	$user = $stmt->fetchAll();
	if(count($user) != 0){
		header("location: register.php?username");
		return;
	}

	$stmt = $pdo->prepare('SELECT * FROM users WHERE email=?');
	$stmt->execute([$email]);
	$user = $stmt->fetchAll();
	if(count($user) != 0){
		header("location: register.php?email");
		return;
	}

	// create new user
	$stmt = $pdo->prepare('INSERT INTO users (username, email, password, name, surname, personalid) VALUES (?, ?, ?, ?,?,?)');
	$stmt->execute([$username, $email, $pwd1, $name, $surname, $personalid]);

	// get id of newly created user
	$stmt = $pdo->prepare('SELECT * FROM users WHERE username=?');
	$stmt->execute([$username]);
	$user = $stmt->fetchAll();
	if(count($user) == 1){
		$new_id = $user[0]["user_id"];
		// create new wallet
		$stmt = $pdo->prepare('INSERT INTO wallet (user_id, balance, videostoreslots, maxvideostoreslots) VALUES (?, ?, ?, ?)');
		$stmt->execute([$new_id, 50000, 3, 3]);


		$_SESSION['user_id'] = $user[0]["user_id"];
	 
	 	header("location: ../videostore.php");
	 	return;
	}else{
		header("location: register.php?error");
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
	<div class="col-md-2 col-md-offset-2">

		<h2 style="width: 100%; text-align: center"><?php echo $applicationName ?></h2><br>
		<h3 style="width: 100%; text-align: center">New user registration</h3><br>

		<form method="post">
			<div class="form-group">
				<label for="email">Email address:</label>
				<input type="email" class="form-control" name="email" id="email">
			</div>
			<div class="form-group">
				<label for="username">Username:</label>
				<input type="text" class="form-control" name="username" id="username">
			</div>
			<div class="form-group">
				<label for="pwd1">Password:</label>
				<input type="password" class="form-control" name="pwd1" id="pwd1">
			</div>
			<div class="form-group">
				<label for="pwd2">Repeat Password:</label>
				<input type="password" class="form-control" name="pwd2" id="pwd2">
			</div>
			<div class="form-group">
				<label for="name">First Name:</label>
				<input type="text" class="form-control" name="name" id="name">
			</div>

			<div class="form-group">
				<label for="surname">Surname:</label>
				<input type="text" class="form-control" name="surname" id="surname">
			</div>

			<div class="form-group">
				<label for="name">Personalid:</label>
				<input type="text" class="form-control" name="personalid" id="personalid">
			</div>

			
			<button type="submit" class="btn btn-default">Submit</button>

			<?php 
			if(isset($_GET["unsuccessful"])){
				echo "Please fill out all fields!";
			}
			if(isset($_GET["mismatch"])){
				echo "Passwords do not match!";
			}
			if(isset($_GET["username"])){
				echo "Username already taken!";
			}
			if(isset($_GET["email"])){
				echo "E-mail already taken!";
			}
			if(isset($_GET["error"])){
				echo "Oooops!";
			}
			?>

		</form>
	</div>
</div><!-- /.container -->

<?php include_once '../template/scripts.php'; ?>

</body>
</html>
