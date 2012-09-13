/**
 * JavaScript file for the "run" page in the browser.
 *
 * @author John Resig, 2008-2011
 * @author Timo Tijhof, 2012
 * @since 0.1.0
 * @package TestSwarm
 */
(function ( $, SWARM, undefined ) {
	var currRunId, currRunUrl, testTimeout, pauseTimer, cmds, errorOut;

	function msg( htmlMsg ) {
		$( '#msg' ).html( htmlMsg );
	}

	function log( htmlMsg ) {
		$( '#history' ).prepend( '<li><strong>' +
			( function getDate() {
			
				function pad(num, size) {
					var s = num+"";
					while (s.length < size) s = "0" + s;
					return s;
				}
			
				var now = new Date();
				
				return 	now.getFullYear() + '/' + 
						pad( now.getMonth() + 1, 2 ) + '/' + 
						pad( now.getDate(), 2 ) + ' ' + 
						pad( now.getHours(), 2 ) + ':' + 
						pad( now.getMinutes(), 2 ) + ':' + 
						pad( now.getSeconds(), 2 ) + ": ";
			} ) () +
			'</strong> ' + htmlMsg + '</li>'
		);

		msg( htmlMsg );
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
		reload: function () {
			if( window.parent !== window && !!window.parent.Main && !!window.parent.Main.reload ) {	
				
				log( 'run.js: cmds.reload: Maple detected, reloading parent: window.parent.Main.reload();' );	
				// Samsung 2010 seems to need a delay in order to reload the page and bootstrap correctly (otherwise page doesn't start 'ticking down' at all!)
				setTimeout(function() {
					window.parent.Main.reload();	// Samsung 2010 and 2011 runs testswarm in an iframe. Reload parent instead of this window.
				}, 2000);
			} else {
				log( 'run.js: cmds.reload: window.location.reload();' );		
				window.location.reload();
			}
		}
	};

	/**
	 * @param query String|Object: $.ajax "data" option, converted with $.param.
	 * @param retry Function
	 * @param ok Function
	 */
	function retrySend( query, retry, ok ) {
		function error( errMsg ) {
				if ( errorOut > SWARM.conf.client.saveRetryMax ) {
					cmds.reload();
				} else {
					errorOut += 1;
					errMsg = errMsg ? (' (' + errMsg + ')') : '';
					msg( 'Error connecting to server' + errMsg + ', retrying...' );
					setTimeout( retry, SWARM.conf.client.saveRetrySleep * 1000 );
				}
		}

		$.ajax({
			type: 'POST',
			url: SWARM.conf.web.contextpath + 'api.php',
			timeout: SWARM.conf.client.saveReqTimeout * 1000,
			cache: false,
			data: query,
			dataType: 'json',
			success: function ( data ) {
				if ( !data || data.error ) {
					error( data.error.info );
				} else {
					errorOut = 0;
					ok.apply( this, arguments );
				}
			},
			error: function () {
				error();
			}
		});
	}

	function getTests() {
		if ( currRunId === undefined ) {
			log( 'Connected to the swarm.' );
		}

		currRunId = 0;
		currRunUrl = false;

		msg( 'Querying for tests to run...' );
		retrySend( {
			action: 'getrun',
			client_id: SWARM.client_id,
			run_token: SWARM.run_token
		}, getTests, runTests );
	}

	function cancelTest() {		
		$( 'iframe' ).remove();
		cancelTimeout();
	}
	
	function cancelTimeout() {
		if ( testTimeout ) {
			clearTimeout( testTimeout );
			testTimeout = 0;
		}
	}
	
	window.TestSwarm = window.TestSwarm || { };
	function setTestTimeout( runInfo ) {
		window.TestSwarm.runnerRunInfo = runInfo;	// store the parameter so we have it for runnerHeartBeat()
		testTimeout = setTimeout( function () {
			testTimedout( runInfo );
		}, SWARM.conf.client.runTimeout * 1000 );
	}
	
	window.TestSwarm.runnerHeartbeat = function () {
		log('run.js: window.TestSwarm.runnerHeartbeat');
		cancelTimeout();
		setTestTimeout( window.TestSwarm.runnerRunInfo );
	}
	
	function testTimedout( runInfo ) {
		log('run.js: testTimedout(): hello');
		cancelTest();
		retrySend(
			{
				action: 'saverun',
				fail: 0,
				error: 0,
				total: 0,
				status: 3, // ResultAction::STATE_ABORTED
				report_html: 'Test Timed Out.',
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
				iframe.width = '99%';
				iframe.height = 600;
				iframe.className = 'test-runner-frame';
				iframe.src = currRunUrl + (currRunUrl.indexOf( '?' ) > -1 ? '&' : '?') + $.param({
					// Cache buster
					'_' : new Date().getTime(),
					// Homing signal for inject.js so that it can find its target for action=saverun
					"swarmURL" : window.location.protocol + "//" + window.location.host + SWARM.conf.web.contextpath
						+ "index.php?"
						+ $.param({
							status: 2, // ResultAction::STATE_FINISHED
							run_id: currRunId,
							client_id: SWARM.client_id,
							run_token: SWARM.run_token,
							results_id: runInfo.resultsId,
							results_store_token: runInfo.resultsStoreToken,
							decode_html: SWARM.decode_html
						})
				});

				$( '#iframes' ).append( iframe );

				// Timeout after a period of time
				setTestTimeout( runInfo );

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
		log( 'run.js: runDone(): reloading page...' );
		cmds.reload();
		return;
		
		// code below won't get executed as we are reloading the test runner page
		
		log( 'run.js: runDone(): 1: iframe count: ' + $('iframe').length );
		cancelTest();
		log( 'run.js: runDone(): 2: iframe count: ' + $('iframe').length );
		runTests({ timeoutMsg: 'Cooling down.' });
		log( 'run.js: runDone(): 3: iframe count: ' + $('iframe').length );		
	};

	function handleMessage(e) {
		e = e || window.event;
		retrySend( e.data, function () {
			handleMessage(e);
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
					cmds.reload();
					return;
				}

				$.extend( SWARM.conf, data.ping.confUpdate );
			}
		}).always( function () {
			setTimeout( confUpdate, SWARM.conf.client.pingTime * 1000 );
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

	$( document).ready( function () {
		getTests();
		confUpdate();
	});

}( jQuery, SWARM ) );
