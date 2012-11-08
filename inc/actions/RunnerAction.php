<?php
/**
 * "Wipejob" action
 *
 * @author Maciej Borzecki, 2012
 * @since 1.0.0
 * @package TestSwarm
 */

class RunnerAction extends Action {

	/**
	 * @actionMethod GET/POST: Required.
	 * @actionParam int resultsId
	 * @actionParam string type: one of 'specStart', 'timeoutCheck'
	 */
	public function doAction() {
		$request = $this->getContext()->getRequest();
		
		$resultsId = $request->getInt( "resultsId" );
		$type = $request->getVal( "type" );

		if ( !$resultsId || !$type ) {
			$this->setError( "missing-parameters" );
			return;
		}
		
		if ( !in_array( $type, array( "specStart", "timeoutCheck" ) ) ) {
			$this->setError( "invalid-input" );
			return;
		}
		
		$now = time();		
		$db = $this->getContext()->getDB();
		$result = "";
		
		switch( $type ) {
			case "stepStart":
				if ( !$request->wasGetted() ) {
					$this->setError( "requires-get" );
					return;
				}
				
				$beatRate = $request->getInt( "beatRate" );
				$fail = $request->getInt( "fail" );
				$error = $request->getInt( "error" );
				$total = $request->getInt( "total" );
				if ( !$beatRate ) {
					$this->setError( "missing-parameters" );
					return;
				}
				
				$expected_update = $now + $beatRate;
				
				$db->query(str_queryf(
					"UPDATE runresults
					SET
						fail = %u,
						error = %u,
						total = %u,
						expected_update = %s,
						updated = %s
					WHERE id = %u
					AND status = 1;",
					$fail,
					$error,
					$total,
					swarmdb_dateformat( $expected_update ),
					swarmdb_dateformat( $now ),
					$resultsId
				));
				
				$result = "ok";
			break;
			
			case "timeoutCheck":
				if ( !$request->wasPosted() ) {
					$this->setError( "requires-post" );
					return;
				}
				
				$timeoutMargin = 10;	// 10 seconds margin
				$timestamp = $now - $timeoutMargin;
				
				// Check if run is timedout. Null expected_update stands for not timedout.
				$isTimedout = (bool) $db->getOne(str_queryf(
					"SELECT IF(expected_update IS NULL, false, expected_update < %u)
					FROM runresults
					WHERE id = %u;",
					swarmdb_dateformat( $timestamp ),
					$resultsId
				));
				
				$result = array(
					"testTimedout" => $isTimedout ? 'true' : 'false'
				);
			break;			
		}
		
		$this->setData( $result );
	}
}
