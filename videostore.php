<?php 

include_once 'includes/config.php'; 
include_once 'includes/redirect_if_logged_out.php';

//----------------------------------------------------------------------------------------------------
// Session management code goes here
// If everything is in order, proceed to routing
//----------------------------------------------------------------------------------------------------

// Routing starts here



$slotPrices = [0,0,0,5000,12000,25000,60000,140000, 500000,2000000,5000000,10000000,30000000,10000000,500000000];

// Get videos from videostore
if(isset($_GET["category"])){ 
	$category = $_GET["category"];

	if($category != "Bronze" && $category != "Silver" && $category != "Gold" && $category != "Featured" && $category != "All"){
		echo "Bad request. Unknown category.";
		return;
	}



	if($category === "All"){
		$stmt = $pdo->prepare('SELECT * FROM videostore WHERE active=1');
		$stmt->execute();
		$data = $stmt->fetchAll();
		echo json_encode($data);
		return;
	}else{
		$stmt = $pdo->prepare('SELECT * FROM videostore WHERE category=? AND active=1');
		$stmt->execute([$category]);
		$data = $stmt->fetchAll();
		echo json_encode($data);
		return;
	}

	echo "Something went wrong. Please try again!";
	return;
}

// Get rented videos from videostore
else if(isset($_GET["rented"]) && $_GET["rented"] == "True"){ 

	// easier way is to create a view using mysql

	$stmt = $pdo->prepare('SELECT * FROM rented_video WHERE user_id=?');
	$stmt->execute([$user_id]);
	$rented = $stmt->fetchAll();


	$idArr = [];
	for($i=0; $i<count($rented); $i++){
		$idArr[] = $rented[$i]["rentedvideo_id"];
	}

	if(count($idArr)>0){
		$idString = implode(",", $idArr);

		$stmt = $pdo->prepare('SELECT * FROM videostore WHERE videostorevideo_id IN ('.$idString.')');
		$stmt->execute();
		$rentedInfo = $stmt->fetchAll();
	}
	else{
		echo "No videos rented.";
		return;
	}
	
	try
	{
		$stmt = $pdo->prepare('SELECT * FROM wallet WHERE user_id=?');
		$stmt->execute([$user_id]);
		$wallet = $stmt->fetch();
	}catch(Error $e){
		echo json_encode("Something went wrong.");
		return;
	}

    function findById($videoStoreVideoId, $rented){
        for($i=0; $i<count($rented); $i++){
            if($rented[$i]["rentedvideo_id"] == $videoStoreVideoId){
                return $rented[$i];
            }
                
        }
    }

	for($i=0; $i<count($rentedInfo); $i++){
        $current = findById($rentedInfo[$i]["videostorevideo_id"], $rented);
		$rentedInfo[$i]["dateofrent"] = $current["dateofrent"];
		$rentedInfo[$i]["collect"] = $current["collect"];
	}


        

	$rentedInfo[] = $slotPrices;
	$rentedInfo[] = $wallet["maxvideostoreslots"];

	echo json_encode($rentedInfo);
	return;
}

// Get wallet information
else if(isset($_GET["wallet"]) && $_GET["wallet"] == "True"){ 

	$stmt = $pdo->prepare('SELECT * FROM wallet WHERE user_id=?');
	$stmt->execute([$user_id]);
	$data = $stmt->fetchAll();

	echo json_encode($data);
	return;
}

// Rent a video from videostore
else if(isset($_POST["video_id"]) && isset($_POST["action"]) && $_POST["action"] === "Rent"){

	// collect data
	//$user_id = $_POST["user_id"];
	$video_id = $_POST["video_id"];

	// check if user exists
	$stmt = $pdo->prepare('SELECT * FROM users WHERE user_id=?');
	$stmt->execute([$user_id]);
	$users = $stmt->fetchAll();
	if(count($users) != 1){
		echo json_encode("Error. User doesn't exist.");
		return;
	}

	// check if video exists and if counter is greater than 0 and if active
	$stmt = $pdo->prepare('SELECT * FROM videostore WHERE videostorevideo_id=?');
	$stmt->execute([$video_id]);
	$video = $stmt->fetchAll();
	if(count($video) != 1){
		echo json_encode("Error. Video doesn't exist.");
		return;
	}else if($video[0]["counter"] <= 0){
		echo json_encode("Error. Video counter is 0.");
		return;
	}else if($video[0]["active"] == 0){
		echo json_encode("Error. Video inactive.");
		return;
	}
	$price = $video[0]["price"];

	// check the number of available slots and balance
	$stmt = $pdo->prepare('SELECT * FROM wallet WHERE user_id=?'); // extend here when support is added for real money transactions
	$stmt->execute([$user_id]);
	$wallet = $stmt->fetchAll();
	if($wallet[0]["videostoreslots"] <= 0){
		echo json_encode("Error. Insufficient slots.");
		return;
	}else if($price > $wallet[0]["balance"]){
		echo json_encode("Error. Insufficient funds.");
		return;
	}

	// check if video is already rented
	$stmt = $pdo->prepare('SELECT * FROM rented_video WHERE rentedvideo_id=? AND user_id=?');
	$stmt->execute([$video_id, $user_id]);
	$video = $stmt->fetchAll();
	if(count($video) != 0){
		echo json_encode("Error. Video already rented.");
		return;
	}

	// add video to database, decrement slots and decrement counter
	// rollback if anything fails

	//-----------------------------------------------------------------------------------------------------------------
	// collect data from youtube API
	// collect();
	//-----------------------------------------------------------------------------------------------------------------

	try {
		$pdo->beginTransaction();

		// insert into rented_video
		$stmt = $pdo->prepare("INSERT INTO rented_video (rentedvideo_id, user_id, dateofrent) VALUES (?, ?, ?)");
		$date = new Datetime();
		$formattedDate = $date->format('Y-m-d H:i'); 
		$stmt->execute([$video_id, $user_id, $formattedDate]);

		// decrement counter
		$stmt = $pdo->prepare("UPDATE videostore SET counter=counter-1 WHERE videostorevideo_id=?");
		$stmt->execute([$video_id]);

		// decrement slots
		$stmt = $pdo->prepare("UPDATE wallet SET videostoreslots=videostoreslots-1, balance=balance-? WHERE user_id=?");
		$stmt->execute([$price, $user_id]);

		videostoreArchive($pdo, $user_id, $video_id, "Rent");
		$pdo->commit();

	}catch (Exception $e){
		$pdo->rollback();
		echo json_encode($e);
		return;
	}

	echo json_encode("Successfully rented.");
	return;

}

// Return a video 
else if(isset($_POST["video_id"]) && isset($_POST["action"]) && $_POST["action"] === "Return"){
	
	// collect data
	//$user_id = $_POST["user_id"];
	$video_id = $_POST["video_id"];

	// check if user exists
	$stmt = $pdo->prepare('SELECT * FROM users WHERE user_id=?'); 
	$stmt->execute([$user_id]);
	$users = $stmt->fetchAll();
	if(count($users) != 1){
		echo json_encode("Error. User doesn't exist.");
		return;
	}
	
	// check if video rented
	$stmt = $pdo->prepare('SELECT * FROM rented_video WHERE (rentedvideo_id=? AND user_id=?)');
	$stmt->execute([$video_id, $user_id]);
	$video = $stmt->fetchAll();
	if(count($video) != 1){
		echo json_encode("Error. Video not found.");
		return;
	}

	// begin transaction
	try {
		$pdo->beginTransaction();

		// remove video from rented_video table
		$stmt = $pdo->prepare("DELETE FROM rented_video WHERE (rentedvideo_id=? AND user_id=?)");
		$stmt->execute([$video_id, $user_id]);

		// increment counter in videostore
		$stmt = $pdo->prepare("UPDATE videostore SET counter=counter+1 WHERE videostorevideo_id=?");
		$stmt->execute([$video_id]);

		// increment available slots
		$stmt = $pdo->prepare("UPDATE wallet SET videostoreslots=videostoreslots+1 WHERE user_id=?");
		$stmt->execute([$user_id]);

		// archive
		videostoreArchive($pdo, $user_id, $video_id, "Return");

		$pdo->commit();

	}catch (Exception $e){
		$pdo->rollback();
		echo json_encode($e);
		return;
	}

	echo json_encode("Successfully returned.");
	return;
}

else if(isset($_POST["getSlot"]) && $_POST["getSlot"] === "True"){
	
	// check if user exists
	$stmt = $pdo->prepare('SELECT * FROM users WHERE user_id=?'); 
	$stmt->execute([$user_id]);
	$users = $stmt->fetchAll();
	if(count($users) != 1){
		echo json_encode("Error. User doesn't exist.");
		return;
	}

	$stmt = $pdo->prepare('SELECT * FROM wallet WHERE user_id=?'); 
	$stmt->execute([$user_id]);
	$wallet = $stmt->fetch();

	if($wallet["maxvideostoreslots"] >= 15){
		echo json_encode("All slots bought!");
		return;
	}

	$price = $slotPrices[$wallet[0]["maxvideostoreslots"]];

	if($price > $wallet["balance"]){
		echo json_encode("Insufficient funds");
		return;
	}

	// increment available slots
	try{
		$stmt = $pdo->prepare("UPDATE wallet SET videostoreslots=videostoreslots+1, maxvideostoreslots=maxvideostoreslots+1, balance=balance-? WHERE user_id=?");
		$stmt->execute([$slotPrices[$wallet["maxvideostoreslots"]], $user_id]);
	}catch(Error $e){
		echo json_encode("Something went wrong. Please try again.");
		return;
	}


	echo json_encode("Successfully bought slot.");
	return;
}

// Collect coins generated by video
else if(isset($_POST["video_id"]) && isset($_POST["action"]) && $_POST["action"] === "Collect"){

	// collect data
	//$user_id = $_POST["user_id"];
	$video_id = $_POST["video_id"];

	// check if user exists
	$stmt = $pdo->prepare('SELECT * FROM users WHERE user_id=?');
	$stmt->execute([$user_id]);
	$users = $stmt->fetchAll();
	if(count($users) != 1){
		echo json_encode("Error. User doesn't exist.");
		return;
	}
	
	// check if video rented
	$stmt = $pdo->prepare('SELECT * FROM rented_video WHERE (rentedvideo_id=? AND user_id=?)');
	$stmt->execute([$video_id, $user_id]);
	$video = $stmt->fetchAll();
	if(count($video) != 1){
		echo json_encode("Error. Video not found.");
		return;
	}

	// get amount to collect 
	$amount = $video[0]["collect"];

	// begin transaction
	try {
		$pdo->beginTransaction();

		// set collect column to 0
		$stmt = $pdo->prepare("UPDATE rented_video SET collect=0 WHERE (rentedvideo_id=? AND user_id=?)");
		$stmt->execute([$video_id, $user_id]);

		// add collected amount to balance
		$stmt = $pdo->prepare("UPDATE wallet SET balance=balance+? WHERE user_id=?");
		$stmt->execute([$amount, $user_id]);

		$pdo->commit();

	}catch (Exception $e){
		$pdo->rollback();
		echo json_encode($e);
		return;
	}

	echo json_encode("Collected successfully");
	return;
}

function videostoreArchive($pdo, $user_id, $rentedvideo_id, $action){
	if($action == "Rent"){
		$actionString = "Rent";
		$descString = "Rented a video from videostore.";
	}else if($action == "Return"){
		$actionString = "Return";
		$descString = "Returned a video to videostore.";
	}else{
		throw new Error("Invalid request.");
	}

	$stmt = $pdo->prepare('SELECT * FROM wallet WHERE user_id=?');
	$stmt->execute([$user_id]);
	$wallet = $stmt->fetchAll();

	$date = new Datetime();
	$formattedDate = $date->format('Y-m-d H:i'); 
	$stmt = $pdo->prepare("INSERT INTO rent_archive (wallet_id, rentedvideo_id, dateandtime, balance, action, description) VALUES (?, ?, ? , ?, ?, ?)");
	$stmt->execute([$wallet[0]["wallet_id"], $rentedvideo_id, $formattedDate, $wallet[0]["balance"], $actionString, $descString]);

}
?>

<?php include_once("template/scripts.php") ?>

<!DOCTYPE html>
<html lang="en">
<head>
	<?php include_once 'template/head.php' ?>
</head>

<?php include_once 'template/menu.php' ?>

<body>
<div  class="container">
	<div class="panel panel-primary">
		<div class="panel-heading"><h4>Videostore</h4></div>
		<div id="videostoreVideos" class="panel-body"></div>
	</div>

</div>

<div class="container">
	<div class="panel panel-primary">
		<div class="panel-heading"><h4>Videos you've rented</h4></div>
		<div id="rentedVideos" class="panel-body"></div>
	</div>
	
	<button id="buyMore" type="button" class="btn btn-success" onclick="buySlot()">Buy another slot!</button>
	<div style="display: inline-block; margin-left:40px;" id="slotPriceTag"></div>
	<div style="display: inline-block; margin-left:40px;" id="slotMsg"></div>
</div>
	
</body>
</html>

<script>

$(document).ready(function(){

	load();

});



window.setInterval(function(){
  	load();
}, 6000000000);



function getWallet()
{

	$.ajax({
		url: "videostore.php",
		type: "get", //send it through get method
		data: { 
			wallet: "True"
		},
		success: function(response) {
			try{
				$("#userInfo").html('');
				var jsonData = JSON.parse(response);
				showWallet(jsonData);
			}catch(err){
				//$("#videostoreVideos").html(response)
				//console.log(err)
				return
			}

		},
		error: function(xhr) {
			alert(xhr)
		}
	});

	
}

function showWallet(data){
	//console.log(data);
	htmlString = '	<li><a href="#" class="navbar-nav pull-right">Balance: '+parseFloat(data[0].balance).toFixed(2).replace(/(\d)(?=(\d{3})+\.)/g, '$1,')+' jc</a></li>\
					<li><a href="#" class="navbar-nav pull-right">Slots available: '+data[0].maxvideostoreslots+'</a></li>'

	$("#userInfo").append(htmlString);
}

// Get and show videos available for rent
function getVideostore()
{

	$.ajax({
		url: "videostore.php",
		type: "get", //send it through get method
		data: { 
			category: "All"
		},
		success: function(response) {
			try{
				$("#videostoreVideos").html('');
				var jsonData = JSON.parse(response);
			}catch(err){
				//$("#videostoreVideos").html(response)
				console.log(err)
				return
			}
			
			var sorted = sortByCategory(jsonData)

			for(var i=0; i<4; i++){
				if(sorted[i].length > 0)
					showVideostoreVideos(sorted[i])
			}
		},
		error: function(xhr) {
			alert(xhr)
		}
	});

	
}
function sortByCategory(allVideos)
{
	var sortedVideos = [[], [], [], []];

	for(var i=0; i<allVideos.length; i++){
		if(allVideos[i].category == "Bronze"){
			sortedVideos[0].push(allVideos[i])
		}else if(allVideos[i].category == "Silver"){
			sortedVideos[1].push(allVideos[i])
		}else if(allVideos[i].category == "Gold"){
			sortedVideos[2].push(allVideos[i])
		}else if(allVideos[i].category == "Featured"){
			sortedVideos[3].push(allVideos[i])
		}
	}

	//console.log(sortedVideos)
	return sortedVideos
}
function showVideostoreVideos(videos)
{
	var numOfSlides;
	if(videos.length % 3 == 0)
		numOfSlides = videos.length / 3
	else
		numOfSlides = parseInt(videos.length / 3) + 1

	var category = videos[0].category
	
	//console.log(videos)


	
	$("#videostoreVideos").append(createCarouselString())

	function createCarouselString(){
		var fontColors = {Bronze:"#9e4909", Silver:"#8f959e", Gold:"#997f01"};

		var htmlString = '\
	  		<h3 style="cursor: pointer; cursor: hand; color:'+fontColors[category]+'" data-targetid="#'+category+'Carousel">'+category+' Category</h3>\
			<div id="'+category+'Carousel" class="carousel slide" data-ride="carousel">\
				<!-- Indicators -->'+
				createIndicators()
				+'\
				<!-- Wrapper for slides -->'+
				createCarouselItems()
				+
				'<!-- Left and right controls -->\
				<a class="left carousel-control" href="#'+category+'Carousel" data-slide="prev">\
					<span class="glyphicon glyphicon-chevron-left"></span>\
					<span class="sr-only">Previous</span>\
				</a>\
				<a class="right carousel-control" href="#'+category+'Carousel" data-slide="next">\
					<span class="glyphicon glyphicon-chevron-right"></span>\
					<span class="sr-only">Next</span>\
				</a>\
			</div>\
		<br>'

		return htmlString
	}

	function createIndicators(){
		var indicators = '<ol class="carousel-indicators">';

		for(var i=0; i<numOfSlides; i++){
			if(i==0)
				indicators += '<li data-target="#'+category+'Carousel" data-slide-to="'+i+'" class="active"></li>'
			else
				indicators += '<li data-target="#'+category+'Carousel" data-slide-to="'+i+'"></li>'
		}

		indicators += '</ol>';	

		return indicators;
	}

	function createCarouselItems(){
		var c = 0;

		var items = '<div class="carousel-inner">'
		for(var i=0; i<numOfSlides; i++){
			if(i==0)
				items += '<div class="item active">'
			else
				items += '<div class="item">'

			for(var j=0; j<3; j++){
				if(videos[c]){
					items += '\
						<div class="col-sm-4">\
							<div class="col-sm-10 col-sm-push-1">\
								<iframe width="150" height="112"\
									src="https://www.youtube.com/embed/'+videos[c].youtubeid+'">\
								</iframe>\
								<p>\
									<h4>'+videos[c].title+'</h4><br>\
									Price: <b>'+parseFloat(videos[c].price).toFixed(2).replace(/(\d)(?=(\d{3})+\.)/g, '$1,')+'</b>jc<br>\
									Views: <b>'+videos[c].views.toString().replace(/\B(?=(\d{3})+(?!\d))/g, ",")+'</b><br>\
									Left in store: <b>'+videos[c].counter+'</b><br>\
								</p>\
								<div class="col-sm-2 col-sm-push-4">\
									<button data-videoid="'+videos[c].videostorevideo_id+'" class="btn btn-primary" onclick="rentVideo(this.getAttribute(\'data-videoid\'))">\
										Rent this video!\
									</button>\
								</div>\
							</div>\
						</div>'
					c++
				}
				else{
					break;
				}
				
			}

			items += "</div>"
			
		}

		items += '</div>'

		return items
	}
}

function toggleVisibility(elementId){
	$(elementId).toggle();
}

function getRented(){
	$.ajax({
		url: "videostore.php",
		type: "get", //send it through get method
		data: { 
			rented: "True"
		},
		success: function(response) {
			try{
				var jsonData = JSON.parse(response);
			}catch(err){
				$("#rentedVideos").html(response)
				return
			}
			
			$("#rentedVideos").html('')
			if(jsonData.length>0){
				showRented(jsonData);
			}
			
		},
		error: function(xhr) {
			alert("err")
		}
	});
}

function showRented(rentedVideos){
	var tableString = '\
			<table class="table">\
				<thead>\
					<tr>\
						<th>Title</th>\
						<th>Views</th>\
						<th>Date and Time of Rent</th>\
						<th>Category</th>\
						<th>Collect</th>\
						<th></th>\
					</tr>\
				</thead>\
				<tbody>'
				+createTableRows();+
				'\
				</tbody>\
			</table>\
			<br>';

	console.log(rentedVideos)
	if(rentedVideos[rentedVideos.length-1]<15)
		$("#slotPriceTag").html('<p style="font-size:18px">Additional slot price: ' + rentedVideos[rentedVideos.length-2][rentedVideos[rentedVideos.length-1]].toFixed(2).replace(/(\d)(?=(\d{3})+\.)/g, '$1,') + " jc</p>");
	else
		$("#buyMore").hide();

	//console.log(tableString)
	$("#rentedVideos").html(tableString)


	function createTableRows(){
		var tableRow = "";

		for(var i=0; i<rentedVideos.length-2; i++){
			tableRow += '\
					<tr>\
						<td>'+rentedVideos[i].title+'</td>\
						<td>'+rentedVideos[i].views.toString().replace(/\B(?=(\d{3})+(?!\d))/g, ",")+'</td>\
						<td>'+rentedVideos[i].dateofrent+'</td>\
						<td>'+rentedVideos[i].category+'</td>\
						<td>'+parseFloat(rentedVideos[i].collect).toFixed(2).replace(/(\d)(?=(\d{3})+\.)/g, '$1,')+'</td>\
						<td>'


			if(rentedVideos[i].collect>0){
				tableRow +=	'<button data-videoid="'+rentedVideos[i].videostorevideo_id+'" class="btn btn-success" onclick="collectCoins(this.getAttribute(\'data-videoid\'))">\
										Collect!\
							</button>'
			}
							
			tableRow +=		'<button data-videoid="'+rentedVideos[i].videostorevideo_id+'" class="btn btn-danger" onclick="returnVideo(	this.getAttribute(\'data-videoid\'))">\
								Return\
							</button>\
						</td>\
					</tr>'
		}

		return tableRow;
	}
}

function rentVideo(id){
	//console.log(id)
	$.ajax({
		url: "videostore.php",
		type: "post", //send it through get method
		data: { 
			video_id: id,
			action: "Rent"
		},
		success: function(response) {
			alert(response);
			load();
		},
		error: function(xhr) {
			alert("Something went wrong.")
		}
	});
}

function returnVideo(id){
	console.log(id)
	$.ajax({
		url: "videostore.php",
		type: "post", //send it through get method
		data: { 
			video_id: id,
			action: "Return"
		},
		success: function(response) {
			alert(response);
			load();
		},
		error: function(xhr) {
			alert("Something went wrong.")
		}
	});
}

function collectCoins(id){
	console.log(id)
	$.ajax({
		url: "videostore.php",
		type: "post", //send it through get method
		data: { 
			video_id: id,
			action: "Collect"
		},
		success: function(response) {
			alert(response)
			load();
		},
		error: function(xhr) {
			alert("Something went wrong.")
		}
	});
}

function buySlot(){
	$.ajax({
		url: "videostore.php",
		type: "post",
		data: { 
			getSlot: "True"
		},
		success: function(response) {
			var r = JSON.parse(response)
			
			$("#slotMsg").html("<p>"+r+"</p>")
			console.log(r)
			load();
		},
		error: function(xhr) {
			alert(xhr)
		}
	});
}

function load(){
	getRented();
	getVideostore();
	getWallet();
}

</script>

