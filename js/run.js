z/**
 * JavaScript file for the "run" page in the browser.
 *
 * @author John Resig, 2008-2011
 * @author Timo Tijhof, 2012
 * @since 0.1.0
 * @package TestSwarm
 */
(function ( $, SWARM, undefined ) {
	var currRunId, currRunUrl, timeoutHeartbeatInterval, timeoutHeartbeatInProgress, confUpdateTimeout, pauseTimer, isCurrRunDone, sleepTimer, cmds, errorOut, PS3ChunkSize = 32768, isPS3 = /PlayStation 3/i.test(navigator.userAgent);

	var health = {
		threshold: 25,
		readyForNextJob: function() { return health.score > health.threshold; },
		calculate: function() { return Math.round( ( health.score / health.threshold ) * 100 ); },
		label: function () { 
			var max = 400;
			var score = health.calculate();
			
			if(score >= max) {
				return max + '(+)%';
			}
			
			return score + '%';
		},
		score: 0,
		element: $('<a/>').appendTo( $('<li/>').prependTo( $('ul.nav.pull-right') ) )
	};
	
	// work out whether device is healthy or not
	// params:
	// - health - object that holds results and display element
	// - setInterval - window.setInterval function
	(function(health, setInterval) {
	
		var config = {
			lastTick: null,
			element: health.element,
			progress: {
				chars: ['-', '\\', '|', '/'],
				pos: 0
			},
			intervalMs: 250,
			average: {
				value: 0,
				data: [],
				length: 10,
				getAverage: function() {
				
					config.average.data.splice(0, config.average.data.length - config.average.length);
					
					var sum = 0;
					for(var i = 0; i < config.average.data.length; i++) {
						sum += config.average.data[i];
					}
					
					return Math.round( sum / config.average.data.length );
				}
			}
		};
				
		config.element.text('health label');
		
		setInterval(function() {
			var newTick = (new Date()).getTime();
			
			config.progress.pos = (config.progress.pos == config.progress.chars.length - 1 ) ? 0 : ( config.progress.pos + 1 );
			var msg = [ config.progress.chars[config.progress.pos] ];
			
			if(!!config.lastTick) {
				var diff = newTick - config.lastTick;
				
				config.average.data.push(diff);
				
				msg.push('last=' + diff + 'ms');
				
				// calculate average time on last few interval ticks
				config.average.value = config.average.getAverage();
				
				msg.push( 'avg=' + config.average.value + 'ms' );

				// determine whether device is healthy. apply 5% margin.
				var isNowHealthy = ( config.average.value <= config.intervalMs * 1.05 );
				if (isNowHealthy) {
					health.score++;
				} else {
					health.score = 0;
				}
				
				msg.push( 'H=' + health.label() );
			}

			config.element.html( msg.join(' ') );
			
			config.lastTick = newTick;
		}, config.intervalMs);
	
	}) (health, window.setInterval);
	
	var refreshCodes = [
		13, 	// Enter/OK on most devices
		29443	// Enter/OK for Samsung devices
	];

	function msg( htmlMsg ) {
		$( '#msg' ).text( htmlMsg );
	}

	function log( htmlMsg, doNotShowMessage ) {
		$( '#history' ).prepend( '<li><strong>' +
			new Date().toString().replace( /^\w+ /, '' ).replace( /:[^:]+$/, '' ) +
			':</strong> ' + htmlMsg + '</li>'
		);

		// keep only 20 latest history entries
		$( '#history li:gt(20)' ).remove();
		
		if( !doNotShowMessage )
		{
			msg( htmlMsg );
		}
	}

	/**
	 * Softly validate the SWARM object
	 */
	if ( !SWARM.client_id || !SWARM.conf ) {
		$( function () {
			msg( 'Error: No client id configured! Aborting.' );
		});
		return;
	}

	errorOut = 0;
	cmds = {
		sleep: function () {

			if(sleepTimer)
			{
				return;
			}

			cancelTest();
			if ( confUpdateTimeout ) {
				clearTimeout( confUpdateTimeout );
				confUpdateTimeout = 0;
			}

			var sleep_msg, sleepTimeLeft

			sleep_msg = 'Server unavailable, sleeping.';
			sleepTimeLeft = SWARM.conf.client.serverNotRespondingSleep;
			msg(sleep_msg);

			sleepTimer = setTimeout(function sleepLeftTimer() {
				msg(sleep_msg + ' Waking up in ' + sleepTimeLeft + ' seconds.' );
				if ( sleepTimeLeft >= 1 ) {
					sleepTimeLeft -= 1;
					sleepTimer = setTimeout( sleepLeftTimer, 1000 );
				} else {
					sleepTimeLeft -= 1;
					resetAfterSleep();
				}
			}, 1000);
		},
		reload: function () {
			window.location.reload();
		}
	};

	function resetAfterSleep(){
		errorOut = 0
		if ( sleepTimer ) {
			clearTimeout( sleepTimer );
			sleepTimer = 0;
		}
		getTests();
		confUpdate();
	}

	/**
	 * @param query String|Object: $.ajax "data" option, converted with $.param.
	 * @param retry Function
	 * @param ok Function
	 */
	function retrySend( query, retry, ok ) {
		function error( errMsg ) {
			if ( errorOut > SWARM.conf.client.saveRetryMax ) {
				cmds.sleep();
			} else {
				errorOut += 1;
				errMsg = errMsg ? (' (' + errMsg + ')') : '';
				msg( 'Error connecting to server' + errMsg + ', retrying...' );
				setTimeout( retry, SWARM.conf.client.saveRetrySleep * 1000 );
			}
		}

		function submitChunkedRequest( query ) {
			// PS3 needs to have the form submission chunked as too big requests ( >64kb ) are truncated

			function splitData( data, chunkSize ) {
				var regex = new RegExp('.{1,' + chunkSize + '}', 'g');
				return data.match(regex);
			}

			// encode data to base64
			var encodedData = Base64.encode( query );
			// split data into 32 kb chunks
			var chunks = splitData( encodedData, PS3ChunkSize );

			// Initalize submission
			function chunkSuccessResponse(chunkResponseData, textStatus, jqXHR)
			{
				// callback on last chunk submission contains server response.
				if( chunkResponseData !== '' ) {
					var jsonData = jQuery.parseJSON(chunkResponseData);
					if ( jsonData.error ) {
						log( 'run.js: retrySend: chunked request: PS3MultipartRequest.php: success: incorrect data' );
						error( jsonData.error.info );
					} else {
						log( 'run.js: retrySend: chunked request: PS3MultipartRequest.php: success' );
						errorOut = 0;
						ok.apply( this, jsonData );
					}
				}
			}

			function submitChunks()
			{
				log( 'run.js: retrySend: chunked request: PS3MultipartRequestInit.php: success' );
				for(var i = 0; i < chunks.length; i++) {
					var chunk = chunks[i];
					var chunkRequestData = { runId: currRunId, chunkNumber: i, totalChunks: chunks.length, data: chunk};
					$.ajax({
						type: 'POST',
						url: SWARM.conf.web.contextpath + 'PS3MultipartRequest.php',
						timeout: SWARM.conf.client.saveReqTimeout * 1000,
						cache: false,
						data: chunkRequestData,
						success: chunkSuccessResponse,
						error: error
					});
				}
			}

			var initializationData = { totalChunks: chunks.length, runId: currRunId };
			
			$.ajax({
				type: 'POST',
				url: SWARM.conf.web.contextpath + 'PS3MultipartRequestInit.php',
				timeout: SWARM.conf.client.saveReqTimeout * 1000,
				cache: false,
				data: initializationData,
				success: submitChunks,
				error: error
			});
		}

		if( isPS3 && ( typeof query === "string" || typeof query === "object" ) ) {
			var queryString = typeof query === "object" ? JSON.stringify( query ) : query;
			if ( queryString.length > PS3ChunkSize ) {
				submitChunkedRequest( queryString );
				return;
			}
		}

		// default results submission
		var params = {
			type: 'POST',
			url: SWARM.conf.web.contextpath + 'api.php',
			timeout: SWARM.conf.client.saveReqTimeout * 1000,
			cache: false,
			data: query,
			dataType: 'json',
			success: function ( data ) {
				if ( !data || data.error ) {
					log( 'run.js: retrySend: ajax: success: incorrect data' );
					error( data.error.info );
				} else {
					errorOut = 0;
					ok.apply( this, arguments );
				}
			},
			error: function () {
				error();
			}
		};
		
		log( JSON.stringify( params ), true );
		
		$.ajax(params);
	}

	function getTests() {
		hideTimeoutTimer();

		if ( currRunId === undefined ) {
			log( 'Connected to the swarm.' );
		}

		currRunId = 0;
		currRunUrl = false;
		isCurrRunDone = false;
		timeoutHeartbeatInProgress = false;

		function queryForTests() {		
			msg( 'Querying for tests to run...' );
			retrySend( {
				action: 'getrun',
				client_id: SWARM.client_id,
				run_token: SWARM.run_token
			}, getTests, runTests );
		}
		
		(function healthCheck() {
			if(!health.readyForNextJob()) {
				msg( 'Device is not responsive enough, cooling down a bit...' );
				setTimeout( healthCheck, 1000 );
			} else {
				queryForTests();
			}
		})();
	}

	function cancelTest() {
		if ( timeoutHeartbeatInterval ) {
			clearInterval( timeoutHeartbeatInterval );
			timeoutHeartbeatInterval = 0;
		}

		$( 'iframe' ).remove();
	}

	function timeoutHeartbeatCheck( runInfo ) {

		if (isCurrRunDone){
			return;
		}

		timeoutHeartbeatInProgress = true;

		// is test really timed out? check database
		retrySend( { action: 'runheartbeat', resultsId: runInfo.resultsId, type: 'timeoutCheck' }, function () {
			log('run.js: timeoutHeartbeatCheck(): retry');
			timeoutHeartbeatCheck( runInfo );
		}, function ( data ) {
			timeoutHeartbeatInProgress = false;

			log( JSON.stringify(data), true);
			
			if ( data.runheartbeat.testTimedout === 'true' ) {
				log('run.js: timeoutHeartbeatCheck(): true');
				testTimedout( runInfo );
			}
		});
	}

	function testTimedout( runInfo ) {
		if (isCurrRunDone){
			return;
		}

		log('run.js: testTimedout()');

		cancelTest();
		retrySend(
			{
				action: 'saverun',
				status: 5, // ResultAction::STATE_HEARTBEAT
				job_id: runInfo.jobId,
				run_id: currRunId,
				client_id: SWARM.client_id,
				run_token: SWARM.run_token,
				results_id: runInfo.resultsId,
				results_store_token: runInfo.resultsStoreToken
			},
			function () {
				log('run.js: testTimedout(): retry');
				testTimedout( runInfo );
			},
			function ( data ) {
				if ( data.saverun === 'ok' ) {
					log('run.js: testTimedout(): ok, SWARM.runDone()');
					SWARM.runDone();
				} else {
					log('run.js: testTimedout(): ok, getTests()');
					getTests();
				}
			}
		);
	}

	function setupTimeoutHeartbeat( runInfo ) {
		timeoutHeartbeatInterval = setInterval( function () {
			if (!timeoutHeartbeatInProgress) {
				timeoutHeartbeatCheck( runInfo );
			}
		}, SWARM.conf.client.runHeartbeatRate * 1000 );
	}

	function hideTimeoutTimer() {
		$( '#timeoutTimer' ).hide();
	}

	function logTimeoutCountdown(secondsLeft) {
		$( '#timeoutCountdown' ).html( secondsLeft );
		$( '#timeoutTimer' ).show();
	}

	/**
	 * @param data Object: Reponse from api.php?action=getrun
	 */
	function runTests( data ) {
		var norun_msg, timeLeft, runInfo, params, iframe;

		if ( !$.isPlainObject( data ) || data.error ) {
			// Handle session timeout, where server sends back "Username required."
			// Handle TestSwarm reset, where server sends back "Client doesn't exist."
			if ( data.error ) {
				$(function () {
					msg( 'action=getrun failed. ' + $( '<div>' ).text( data.error.info ).html() );
				});
				return;
			}
		}

		if ( data.getrun ) {

			// Handle actual retreived tests from runInfo
			runInfo = data.getrun.runInfo;
			if ( runInfo ) {
				currRunId = runInfo.id;
				currRunUrl = runInfo.url;

				log( 'Running ' + ( runInfo.desc || '' ) + ' tests...' );

				iframe = document.createElement( 'iframe' );
				iframe.width = 1000;
				iframe.height = 600;
				iframe.className = 'test-runner-frame';
				iframe.src = currRunUrl + (currRunUrl.indexOf( '?' ) > -1 ? '&' : '?') + $.param({
					// Cache buster
					'_' : new Date().getTime(),
					// Homing signal for inject.js so that it can find its target for action=saverun
					'swarmURL' : window.location.protocol + '//' + window.location.host + SWARM.conf.web.contextpath +
						'index.php?' +
						$.param({
							status: 2, // ResultAction::STATE_FINISHED
							run_id: currRunId,
							job_id: runInfo.jobId,
							client_id: SWARM.client_id,
							run_token: SWARM.run_token,
							results_id: runInfo.resultsId,
							results_store_token: runInfo.resultsStoreToken
						})
				});

				$( '#iframes' ).append( iframe );

				setupTimeoutHeartbeat( runInfo );

				return;
			}

		}

		// If we're still here then either there are no new tests to run, or this is a call
		// triggerd by an iframe to continue the loop. We'll do so a short timeout,
		// optionally replacing the message by data.timeoutMsg
		clearTimeout( pauseTimer );

		norun_msg = data.timeoutMsg || 'No new tests to run.';

		msg( norun_msg );

		// If we just completed a run, do a cooldown before we fetch the next run (if there is one).
		// If we just completed a cooldown a no runs where available, nonewruns_sleep instead.
		timeLeft = currRunUrl ? SWARM.conf.client.cooldownSleep : SWARM.conf.client.nonewrunsSleep;

		pauseTimer = setTimeout(function leftTimer() {
			msg(norun_msg + ' Getting more in ' + timeLeft + ' seconds.' );
			if ( timeLeft >= 1 ) {
				timeLeft -= 1;
				pauseTimer = setTimeout( leftTimer, 1000 );
			} else {
				timeLeft -= 1;
				getTests();
			}
		}, 1000);

	}

	// Needs to be a publicly exposed function,
	// so that when inject.js does a <form> submission,
	// it can call this from within the frame
	// as window.parent.SWARM.runDone();
	SWARM.runDone = function () {
		isCurrRunDone = true;
		cancelTest();
		runTests({ timeoutMsg: 'Cooling down.' });
	};

	function handleMessage(e) {

		if (isCurrRunDone){
			return;
		}

		e = e || window.event;
		isCurrRunDone = true;

		sendMessage(e);
	}

	function sendMessage(obj){
		log( 'run.js: sendMessage()' );
		retrySend( obj.data, function () {
			sendMessage(obj);
		}, SWARM.runDone );
	}

	function confUpdate() {
		$.ajax({
			type: 'POST',
			url: SWARM.conf.web.contextpath + 'api.php',
			timeout: SWARM.conf.client.saveReqTimeout * 1000,
			cache: false,
			data: {
				action: 'ping',
				client_id: SWARM.client_id,
				run_token: SWARM.run_token
			},
			dataType: 'json'
		}).done( function ( data ) {
			// Handle configuration update
			if ( data.ping && data.ping.confUpdate ) {
				// Refresh control
				if ( SWARM.conf.client.refreshControl < data.ping.confUpdate.client.refreshControl ) {
					cmds.sleep();
					return;
				}

				$.extend( SWARM.conf, data.ping.confUpdate );
			}
		}).always( function () {
			confUpdateTimeout = setTimeout( confUpdate, SWARM.conf.client.pingTime * 1000 );
		});
	}


	/**
	 * Bind
	 */
	if ( window.addEventListener ) {
		window.addEventListener( 'message', handleMessage, false );
	} else if ( window.attachEvent ) {
		window.attachEvent( 'onmessage', handleMessage );
	}

	$( function () {
		getTests();
		confUpdate();

		$(document).keyup(function(event){
			if (refreshCodes.indexOf(event.which) >= 0 ) {
				cmds.reload();
			}
		});
	});

}( jQuery, SWARM ) );
