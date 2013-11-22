<?php
/**
 * "Job" action.
 *
 * @author Timo Tijhof, 2012
 * @since 0.1.0
 * @package TestSwarm
 */
class JobAction extends Action {

	public static $STATE_SUSPENDED = 3;

	protected $item, $runs, $userAgents;

	/**
	 * @actionParam int item: Job ID.
	 */
	public function doAction() {
		$db = $this->getContext()->getDB();
		$request = $this->getContext()->getRequest();

		$this->item = $request->getInt( 'item' );
		$includeRuns = $request->getBool( 'runs' );
		if ( !$this->item ) {
			$this->setError( 'missing-parameters' );
			return;
		}

		// Get job information
		$jobInfo = $this->getInfo();

		if ( !$jobInfo ) {
			$this->setError( 'invalid-input', 'Job not found' );
			return;
		}

		if ( $includeRuns ) {
			$processed = self::getRunRows( $this->getContext(), array("jobID" => $this->item) );
			$this->runs = $processed['runs'];
			$this->userAgents = $this->getUaInfo($processed['userAgentIDs']);
		}

		$uaSummaries = $this->getUaSummaries();

		// Start of response data
		$this->setData( array(
			'info' => $jobInfo,
			'runs' => $this->runs,
			// Mapping of useragent id and information about them.
			// Will contain all distinct user agents that one or more
			// runs of this job is scheduled to run for.
			'userAgents' => $this->userAgents,
			'uaSummaries' => $uaSummaries,
			'summary' => $this->getSummary( $uaSummaries ),
		) );
	}

	protected function getUaSummaries() {
		$db = $this->getContext()->getDB();

		if ( empty($this->userAgents) ) {
			$userAgentIds = array();

			$userAgentRows = $db->getRows(str_queryf(
				'SELECT DISTINCT(useragent_id)
				FROM
					run_useragent,
					runs
				WHERE run_useragent.run_id = runs.id
					AND runs.job_id = %u',
				$this->item
			));

			foreach ($userAgentRows as $userAgentRow ){
				$userAgentIds[] = $userAgentRow->useragent_id;
			}
			$this->userAgents = $this->getUaInfo($userAgentIds);
		}

		$jobStatusRows = $db->getRows(str_queryf(
			'SELECT
				useragent_id,
				calculated_summary
			FROM
				job_useragent
			WHERE job_id = %u',
			$this->item
		));

		$calculatedSummaries = array();

		if( $jobStatusRows ) {
			foreach ($jobStatusRows as $jobStatusRow ){
				$uaID = $jobStatusRow->useragent_id;
				$calculatedSummaries[$uaID] = unserialize($jobStatusRow->calculated_summary);
			}
		}

		$uaSummaries = array();
		$runs = $this->runs;

		foreach( $this->userAgents as $uaID => $uaInfo ){

			if ( !empty( $calculatedSummaries[$uaID] ) ) {
				$uaSummaries[$uaID] = $calculatedSummaries[$uaID];
				continue;
			}

			if ( empty($runs) ) {
				$processed = self::getRunRows( $this->getContext(), array("jobID" => $this->item) );
				$runs = $processed['runs'];
			}

			$uaStatuses = array();
			foreach ( $runs as $run ) {
				if ( isset($run['uaRuns'][$uaID]) ) {
					$uaRun = $run['uaRuns'][$uaID];
					$uaStatuses[$uaID][] = array('status' => $uaRun['runStatus']);
				}
			}

			$uaSummaries[$uaID] = self::getUaSummaryFromStatuses( $uaStatuses[$uaID] );

			if( isset($calculatedSummaries[$uaID]) ) {
				$db->query(str_queryf(
					'UPDATE
						job_useragent
					SET
						calculated_summary = %s
					WHERE job_id = %u
					AND useragent_id = %s
					LIMIT 1;',
					serialize($uaSummaries[$uaID]),
					$this->item,
					$uaID
				));
			} else {
				$db->query(str_queryf(
					'INSERT INTO job_useragent (job_id, useragent_id, calculated_summary)
					VALUES(%u, %s, %s);',
					$this->item,
					$uaID,
					serialize($uaSummaries[$uaID])
				));
			}
		}

		return $uaSummaries;
	}

	protected function getSummary( $uaSummaries ) {
		return self::getUaSummaryFromStatuses( array_values( $uaSummaries ) );
	}

	/**
	 * @return array|bool
	 */
	protected function getInfo() {
		$db = $this->getContext()->getDB();
		$jobRow = $db->getRow(str_queryf(
			'SELECT
				id,
				name,
				project_id,
				created
			FROM
				jobs
			WHERE id = %u',
			$this->item
		));

		if ( !$jobRow ) {
			return false;
		}
		$jobID = intval( $jobRow->id );

		$projectRow = $db->getRow(str_queryf(
			'SELECT
				display_title
			FROM projects
			WHERE id = %s;',
			$jobRow->project_id
		));

		$ret = array(
			'id' => $jobID,
			'nameHtml' => $jobRow->name,
			'nameText' => strip_tags( $jobRow->name ),
			'project' => array(
				'id' => $jobRow->project_id,
				'display_title' => $projectRow->display_title,
				'viewUrl' => swarmpath( "project/{$jobRow->project_id}" ),
			),
			'viewUrl' => swarmpath( "job/$jobID", 'fullurl' )
		);
		self::addTimestampsTo( $ret, $jobRow->created, 'created' );
		return $ret;
	}

	/**
	 * Get all run rows aggregated with the runs and user agents.
	 * @return Array List of runs and userAgents.
	 */
	public static function getRunRows( TestSwarmContext $context, $options = array() ) {
		$db = $context->getDB();
		$userAgentIDs = array();
		$runs = array();

		$runClause = array();
		if ( isset($options['jobID']) ) {
			$runClause[] = 'runs.job_id = ' . $options['jobID'];
		}
		if ( isset($options['runID']) ) {
			$runClause[] = 'runs.id = ' . $options['runID'];
		}
		if ( count( $runClause ) ) {
			$runQuery = implode( ' AND ', $runClause );
		} else {
			$runQuery = '';
		}

		$runRows = $db->getRows(
			"SELECT
				runs.id as runId,
				runs.job_id as runJobId,
				runs.url as runUrl,
				runs.name as runName,
				run_useragent.status as runUaStatus,
				run_useragent.useragent_id as runUaId,
				runresults.id as runResultsId,
				runresults.client_id,
				runresults.status,
				runresults.total,
				runresults.fail,
				runresults.error
			FROM
				runs,
				run_useragent
			LEFT JOIN
				runresults
				ON runresults.id = run_useragent.results_id
			WHERE
				$runQuery
				AND run_useragent.run_id = runs.id
			ORDER BY runs.id;"
		);

		foreach ( $runRows as $runRow ) {

			$jobID = $runRow->runJobId;

			if ( !isset($runs[$runRow->runId]) ){
				$runInfo = array(
					'id' => $runRow->runId,
					'name' => $runRow->runName,
					'url' => $runRow->runUrl,
				);

				$runs[$runRow->runId] = array(
					'info' => $runInfo,
					'uaRuns' => array()
				);
			}

			$run = &$runs[$runRow->runId];
			$runUaRuns = &$run['uaRuns'];

			$userAgentIDs[] = $runRow->runUaId;

			if ( !$runRow->runResultsId ) {
				if($runRow->runUaStatus == JobAction::$STATE_SUSPENDED) {
					$runUaRuns[$runRow->runUaId] = array(
						'runStatus' => 'suspended',
					);
				} else {
					$runUaRuns[$runRow->runUaId] = array(
						'runStatus' => 'new',
					);
				}
			} else {

				$runUaRuns[$runRow->runUaId] = array(
					'useragentID' => $runRow->runUaId,
					'clientID' => $runRow->client_id,

					'failedTests' => $runRow->fail,
					'totalTests' => $runRow->total,
					'errors' => $runRow->error,

					'runStatus' => self::getRunresultsStatus( $runRow ),
					// Add link to runresults
					'runResultsID' => $runRow->runResultsId,
					'runResultsUrl' => swarmpath( 'result/' . $runRow->runResultsId ),
					'runResultsLabel' =>
						$runRow->status != ResultAction::$STATE_FINISHED
							// If not finished, we don't have any numeric label to show
							// (test could be in progress, or maybe it was aborted/lost)
							? ''
							: ( $runRow->error > 0
							// If there were errors, show number of errors
							? $runRow->error
							: ( $runRow->fail > 0
								// If it failed, show number of failures
								? $runRow->fail
								// If it passed, show total number of tests
								: $runRow->total
							)
						),
				);
			}
		}

		foreach ( $runs as &$run ) {
			uksort( $run['uaRuns'], array( $context->getBrowserInfo(), 'sortUaId' ) );
		}

		return array(
			'jobID' => $jobID,
			'runs' => $runs,
			'userAgentIDs' => $userAgentIDs
		);
	}

	public static function getUaInfo($userAgentIDs){

		// Get information for all encounted useragents
		$browserIndex = BrowserInfo::getBrowserIndex();
		$userAgents = array();
		foreach ( $userAgentIDs as $uaID ) {
			if ( !isset( $browserIndex->$uaID ) ) {
				// If it isn't in the index anymore, it means it has been removed from the browserSets
				// configuration. Use a generic fallback object;
				$userAgents[$uaID] = BrowserInfo::makeGenericUaData( $uaID );
			} else {
				$userAgents[$uaID] = (array)$browserIndex->$uaID;
			}
		}
		uasort( $userAgents, 'BrowserInfo::sortUaData' );

		return $userAgents;
	}

	public static function getUaSummaryFromStatuses( Array $statuses ) {
		$strengths = array_flip(array(
			'passed',
			'new',
			'progress',
			'suspended',
			'lost',
			'timedout',
			'heartbeat',
			'failed',
			'error', // highest priority
		));

		$isNew = true;
		$strongest = null;
		$hasIncomplete = false;
		$total = 0;

		// Enforce the order of the counts by the strengths.
		foreach($strengths as $strengthKey => $strength){
			$counts[$strengthKey] = 0;
		}

		foreach ( $statuses as $statusInfo ) {

			$status = $statusInfo['status'];

			$total++;

			$counts[$status]++;

			if ( $status !== 'new' && $isNew ) {
				$isNew = false;
			}
			if ( $status === 'new' || $status === 'progress' ) {
				if ( !$hasIncomplete ) {
					$hasIncomplete = true;
				}
			}
			if ( !$strongest || $strengths[$status] > $strengths[$strongest] ) {
				$strongest = $status;
			}
		}

		return array(
			'status' => $isNew
				? 'new'
				: ( $hasIncomplete
					? 'progress'
					: $strongest
				),
			'total' => $total,
			'counts' => $counts
		);
	}

	/**
	 * @param $row object: Database row from runresults.
	 * @return string: One of 'progress', 'passed', 'failed', 'timedout', 'error', 'heartbeat', or 'lost'
	 */
	public static function getRunresultsStatus( $row ) {
		$status = (int)$row->status;
		if ( $status === ResultAction::$STATE_BUSY ) {
			return 'progress';
		}
		if ( $status === ResultAction::$STATE_FINISHED ) {
			// BLINKBOX NOTE: we might have few tests where total might be equal to 0 and it should be considered as success
			if ( intval( $row->error ) === 0 && intval( $row->fail ) === 0 ) {
				return 'passed';
			}

			// A total of 0 tests ran is also considered an error
			if ( $row->error > 0 || intval( $row->total ) === 0 ) {
				return 'error';
			}

			return 'failed';
		}
		if ( $status === ResultAction::$STATE_ABORTED ) {
			return 'timedout';
		}
		if ( $status === ResultAction::$STATE_HEARTBEAT) {
			return 'heartbeat';
		}
		if ( $status === ResultAction::$STATE_LOST ) {
			return 'lost';
		}
		// If status is 4 (ResultAction::$STATE_LOST) it means a CleanupAction
		// was aborted between two queries. This is no longer possible, but old
		// data may still be corrupted. Run fixRunresultCorruption.php to fix
		// these entries.
		throw new SwarmException( 'Corrupt run result #' . $row->id );
	}
}
