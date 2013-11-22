<?php
	session_start();

	// generate a GUID for this submission
	$runId = $_POST['runId'] or null;
	$totalChunks = (int)$_POST['totalChunks'] or null;

	if ( $totalChunks === null || $runId === null )
	{
		header("HTTP/1.0 400 Bad Request");
		die("Total Chunks or RunId not set.");
	}

	// Prepare the session
	if (!isset($_SESSION['ChunckedDataSubmission']))
	{
		$_SESSION['ChunckedDataSubmission'] = array();
	};

	$submissionArray = array(
		"runId" => $runId,
		"totalChunks" => $totalChunks,
		"data" => array(),
		"submissionStartTime" => time());

	$_SESSION['ChunckedDataSubmission'][$runId] = $submissionArray;

	// Return the submission ID
	echo json_encode(array ("runId" => $runId));
?>