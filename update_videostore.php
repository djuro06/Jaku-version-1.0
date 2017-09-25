<?php

include_once("includes/config.php");
include_once("includes/youtube_data_API.php");

$stmt = $pdo->prepare('SELECT * FROM videostore WHERE active=1');
$stmt->execute();
$videos = $stmt->fetchAll();

if(count($videos) == 0)
{
	echo "No active videos in database";
	return;
}

$counter = 0;
$info = [];
foreach($videos as $v) 
{
	$video = $youtube->getVideoInfo($v["youtubeid"]);
	$viewCount = $video->statistics->viewCount;

	if(empty($video))
	{
		//echo 'Data unavailable for <b>'.$v["title"].'.</b> Please check at <a href="https://www.youtube.com/watch?v='.$v["youtubeid"].'">https://www.youtube.com/watch?v='.$v["youtubeid"].'</a>';

		array_splice($videos, $counter, 1);
	}
	else
	{
		$info[] = $viewCount;
	}

	$counter++;
}

if(count($videos) != count($info))
{
	echo "Something went wrong.";
	return;
}


$idArray = [];
for($i=0; $i<count($videos); $i++)
{
	$idArray[] = $videos[$i]["videostorevideo_id"];
}
print_r($videos);
try {

	$pdo->beginTransaction();

	$i = 0;
	foreach($videos as $v) 
	{
		$category = $v["category"];
		$viewCount = $info[$i];
		if($category == "Bronze"){
			$counter = 5000;
			$price = $viewCount/50;
		}else if($category == "Silver"){
			$counter = 3000;
			$price = $viewCount/30;
		}else if($category == "Gold"){
			$counter = 1000;
			$price = $viewCount/10;
		}else if($category == "Featured"){
			$counter = 10000;
			$price = $viewCount/100;
		}
		// update videostore
		$stmt = $pdo->prepare("UPDATE videostore SET views=?, lastviews=?, price=? WHERE videostorevideo_id=?");
		$stmt->execute([$info[$i], $info[$i], $price, $v["videostorevideo_id"]]);

		// update rented_video
		$stmt = $pdo->prepare("UPDATE rented_video SET collect=collect+? WHERE rentedvideo_id=?");
		$stmt->execute([$info[$i]-$v["lastviews"], $v["videostorevideo_id"]]);

		$i++;
	}

	$pdo->commit();

}catch (Exception $e){
	$pdo->rollback();
	echo $e;
	return;
}

echo "Successfully updated!";




?>
