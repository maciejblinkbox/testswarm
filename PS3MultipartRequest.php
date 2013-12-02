<?php
	session_start();

	// Validate the submission
	$runId = $_POST['runId'] or null;
	if (!isset($runId) || !isset($_SESSION['ChunckedDataSubmission']) || !isset($_SESSION['ChunckedDataSubmission'][$runId]))
	{
		header("HTTP/1.0 400 Bad Request");
		die("Submission Not Initilized");
	}

	// Extract the submission data
	$session = $_SESSION['ChunckedDataSubmission'][$runId];
	$totalChunks = $session["totalChunks"];
	$chunkNumber = $_POST['chunkNumber'];
	$submissionData = $_POST['data'];

	// If this is a duplicate chunk, ignore it.
	if (isset($session['data'][$chunkNumber]))
	{
		exit();
	}

	// Set the session data for this submission
	$session['data'][$chunkNumber] = $submissionData;

	// If all chunkes have been recived, reassemble the submission and submit it
	if (count($session['data']) === $totalChunks)
	{
		$submisstionDataString = "";
		for ($i = 0; $i< $totalChunks; ++$i)
		{
			$submisstionDataString .= $session['data'][$i];
		}

		// This submission is compleate, remove it from the session.
		unset($_SESSION['ChunckedDataSubmission'][$runId]);

		// After compleating sumission, clean up
		ExpireDeadSubmissions();

		// Fowarding the results can be slow, so relase the session lock to allow other scripts to run.
		session_write_close();

		// Foward the results to TestSwarm
		FowardToApi(base64_decode($submisstionDataString));
	}
	else
	{
		// Write back our session changes
		$_SESSION['ChunckedDataSubmission'][$runId] = $session;
	}

	function ExpireDeadSubmissions()
	{
		// Lost chunkes will cause a session to wait indefinatly for the missing chunkes, so remove the
		// session for any incompleate submissions older than 5 mins.
		$now = time();
		$sessions = $_SESSION['ChunckedDataSubmission'];
		foreach ($sessions as $sessionId => $session)
		{
			$sessionStartTime = $session['submissionStartTime'];
			if ($session === null || $sessionStartTime <= ($now - 300))
			{
				unset($_SESSION['ChunckedDataSubmission'][$sessionId]);
			}
		}
	}

	function getSwarmUrl() {
		$output = array();
		preg_match ( "/(.*)PS3MultipartRequest.php/i", $_SERVER['REQUEST_URI'], $output );
		$virtualDirectory = $output[1];

		$url = 'http://' . $_SERVER['HTTP_HOST'] . $virtualDirectory;

		return $url . 'api.php';
	}

	function FowardToApi($requestBody)
	{
		// Configure connection to server
		$curlRequest = curl_init();

		$url = getSwarmUrl();

		curl_setopt($curlRequest, CURLOPT_URL, $url); // Set teh request URL
		curl_setopt($curlRequest, CURLOPT_RETURNTRANSFER, true); // Prevent curl_exec from echoing result
		curl_setopt($curlRequest, CURLOPT_POST, true); // Set HTTp mode to POST

		$excludedHeaders = array("host", "content-length");
		$incommingRequestheaders = getallheaders();
		$outgoingHeaders = array();
		foreach ($incommingRequestheaders as $key => $value)
		{
			if (!in_array(strtolower($key), $excludedHeaders))
			{
				array_push($outgoingHeaders,"{$key}:{$value}");
			}
		}
		curl_setopt($curlRequest, CURLOPT_HTTPHEADER, $outgoingHeaders);

		// Add the post data
		curl_setopt($curlRequest, CURLOPT_POSTFIELDS, $requestBody);

		// Make API request
		echo curl_exec($curlRequest);

		// Clean up
		curl_close($curlRequest);
	}
?>