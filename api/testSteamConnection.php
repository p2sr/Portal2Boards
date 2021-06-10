<?php
	$ch = curl_init("http://steamcommunity.com");
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    $result = curl_exec($ch);

	print "http code: " . curl_getinfo($ch, CURLINFO_HTTP_CODE) . "<br>";
	print curl_error($ch) . "<br>";
	//print $result . "\n";

	curl_close($ch);
?>
