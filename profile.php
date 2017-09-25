<?php 
include_once 'includes/config.php'; 
include_once 'includes/redirect_if_logged_out.php';
echo $user_id;
$stmt = $pdo->prepare('SELECT * FROM users WHERE user_id=?');
$stmt->execute([$user_id]);
$users = $stmt->fetch();

if(!isset($users)){
    echo json_encode("Error. User doesn't exist.");
    return;
}


$stmt = $pdo->prepare('SELECT * FROM wallet WHERE user_id=?');
$stmt->execute([$user_id]);
$wallet = $stmt->fetchAll();

?>

<!DOCTYPE html>
<html lang="en">
<head>
	<?php include_once 'template/head.php';  ?>
</head>

<body>
    <?php include_once 'template/menu.php';  ?>
    <div class="container">

        <h1><?php echo $users["username"]; ?>'s profile</h1>
        <br><br>

        <div class="col-sm-4">
            <h3>Personal data</h3><br>
            <p><b>E-mail: </b><?php echo $users["email"]; ?> </p>
            <p><b>Username: </b><?php echo $users["username"]; ?> </p>
            <p><b>Name: </b><?php echo $users["name"]; ?> </p>
            <p><b>Surname: </b><?php echo $users["surname"] ?> </p>
            <p><b>Personalid: </b><?php echo $users["personalid"] ?> </p>
            <p><b>Birth Date: </b><?php echo $users["birthdate"] ?> </p>
            <p><b>Street: </b><?php echo $users["street"] ?> </p>
            <p><b>City: </b><?php echo $users["city"] ?> </p>
            <p><b>Country: </b><?php echo $users["country"] ?> </p>
            <p><b>Postalcode: </b><?php echo $users["postalcode"] ?> </p>

            <a href="change_password.php">Change password</a>

            
        </div>
        <div class="col-sm-4">
            <h3>Wallet data</h3><br>
            <?php 
            $fieldNames = ["Balance", "Currency"];

            for($i=2; $i<4; $i++){
                if($i==2){
                    echo "<b><p>".$fieldNames[$i-2].":</b> ".number_format($wallet[0][$i], 2, ".", "," )."<p>"; 
                }
                else
                {
                    echo "<b><p>".$fieldNames[$i-2].":</b> ".$wallet[0][$i]."<p>"; 
                }
            } 
            ?> 
        </div>

    </div><!-- /.container -->

	<?php include_once 'template/scripts.php'; ?>

</body>
</html>