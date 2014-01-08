<?php
/**
 * "Result" action.
 *
 * @author Timo Tijhof, 2012
 * @since 1.0.0
 * @package TestSwarm
 */
class ResultAction extends Action {
	// Currently being run in a client
	public static $STATE_BUSY = 1;

	// Run by the client is finished, results
	// have been submitted.
	public static $STATE_FINISHED = 2;

	// Run by the client was aborted by the client.
	// Either the test (inject.js) lost pulse internally
	// and submitted a partial result, or the test runner (run.js)
	// aborted the test.
	public static $STATE_ABORTED = 3;

	// Client did not submit results, and from CleanAction it
	// was determined that the client died (no longer sends pings).
	public static $STATE_LOST = 4;

	// Has been suspended.
	public static $STATE_HEARTBEAT = 5;

	/**
	 * @actionParam int item: Runresults ID.
	 */
	public function doAction() {
		$context = $this->getContext();
		$db = $context->getDB();
		$conf = $context->getConf();
		$request = $context->getRequest();

		$item = $request->getInt( 'item' );
		$includeReport = $request->getBool( 'report' );

		$row = $db->getRow(str_queryf(
			'SELECT
				id,
				run_id,
				client_id,
				status,
				updated,
				created,
				report_json
			FROM runresults
			WHERE id = %u;',
			$item
		));

		if ( !$row ) {
			$this->setError( 'invalid-input', 'Runresults ID not found.' );
			return;
		}

		$data = array();

		// A job can be deleted without nuking the runresults,
		// this is by design so results stay permanently accessible
		// under a simple url.
		// If the job is no longer in existance, properties
		// 'otherRuns' and 'job' will be set to null.

		$processed = JobAction::getRunRows( $context, array("runID" => $row->run_id) );

		if ( empty($processed['runs']) ) {
			$data['otherRuns'] = null;
			$data['job'] = null;
		} else {

			$data['otherRuns'] = $processed;

			$jobID = intval( $processed['jobID'] );

			$data['job'] = array(
				'id' => $jobID,
				'url' => swarmpath( "job/$jobID", "fullurl" ),
			);

			// get userAgents from userAgentIDs
			$data['otherRuns']['userAgents'] = JobAction::getUaInfo($processed['userAgentIDs']);		
		}

		$clientRow = $db->getRow(str_queryf(
			'SELECT
				id,
				name,
				useragent_id,
				useragent
			FROM clients
			WHERE id = %u;',
			$row->client_id
		));

		$data['info'] = array(
			'id' => intval( $row->id ),
			'runID' => intval( $row->run_id ),
			'clientID' => intval( $row->client_id ),
			'status' => self::getStatus( $row->status )
		);

		$data['client'] = array(
			'id' => $clientRow->id,
			'name' => $clientRow->name,
			'uaID' => $clientRow->useragent_id,
			'uaRaw' => $clientRow->useragent,
			'viewUrl' => swarmpath( 'client/' . $clientRow->id ),
		);

		if ( $includeReport ) {
			$data['report'] = json_decode(gzdecode($row->report_json));
		}

		// If still busy or if the client was lost, then the last update time is irrelevant
		// Alternatively this could test if $row->updated == $row->created, which would effectively
		// do the same.
		if ( $row->status == self::$STATE_BUSY || $row->status == self::$STATE_LOST || $row->status == self::$STATE_HEARTBEAT ) {
			$data['info']['runTime'] = null;
		} else {
			$data['info']['runTime'] = gmstrtotime( $row->updated ) - gmstrtotime( $row->created );
			self::addTimestampsTo( $data['info'], $row->updated, 'saved' );
		}
		self::addTimestampsTo( $data['info'], $row->created, 'started' );

		$this->setData( $data );
	}

	public static function getStatus( $statusId ) {
		$mapping = array();
		$mapping[self::$STATE_BUSY] = 'Busy';
		$mapping[self::$STATE_FINISHED] = 'Finished';
		$mapping[self::$STATE_ABORTED] = 'Aborted';
		$mapping[self::$STATE_LOST] = 'Client lost';
		$mapping[self::$STATE_HEARTBEAT] = 'Heartbeat';

		return isset( $mapping[$statusId] )
			? $mapping[$statusId]
			: false;
	}
}
