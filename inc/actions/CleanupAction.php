<?php
/**
 * "Cleanup" action (previously WipeAction)
 *
 * @author John Resig, 2008-2011
 * @since 0.1.0
 * @package TestSwarm
 */
class CleanupAction extends Action {

	/**
	 * @actionNote This action takes no parameters.
	 */
	public function doAction() {
		$context = $this->getContext();
		$browserInfo = $context->getBrowserInfo();
		$db = $context->getDB();
		$conf = $context->getConf();
		$request = $context->getRequest();

		$resetTimedoutRuns = 0;

		$now = time();
		$maxHeartbeatAge = $now - $conf->client->runHeartbeatTimeMargin;

		$rows = $db->getRows(str_queryf(
			"SELECT
				id,
				run_id
			FROM runresults
			WHERE status = %u
				AND next_heartbeat < %s;",
			ResultAction::$STATE_BUSY,
			swarmdb_dateformat( $maxHeartbeatAge )
		));

		if ( $rows ) {
			foreach ( $rows as $row ) {

				$runID = $row->run_id;
				$resultsID = $row->id;

				$ret = $db->query(str_queryf(
					'UPDATE
						run_useragent
					SET
						completed = completed + 1,
						status = IF(completed < max, 0, 2),
						updated = %s
					WHERE results_id = %u
					LIMIT 1;',
					swarmdb_dateformat( SWARM_NOW ),
					$resultsID
				));

				$db->query(str_queryf(
					'UPDATE
						job_useragent,
						runs,
						run_useragent
					SET calculated_summary = NULL
					WHERE job_useragent.job_id = runs.job_id
						AND job_useragent.useragent_id = run_useragent.useragent_id
						AND run_useragent.run_id = runs.id
						AND run_useragent.results_id = %u;',
					$resultsID
				));

				if ( $ret ) {
					$ret = $db->query(str_queryf(
						'UPDATE
							runresults
						SET
							status = %u,
							updated = %s
						WHERE id = %u
						LIMIT 1;',
						ResultAction::$STATE_LOST,
						swarmdb_dateformat( SWARM_NOW ),
						$resultsID
					));

					if ( $ret ) {
						$resetTimedoutRuns++;
					}
				}
			}
		}

		// Suspend user agent runs which have not updated within the auto-suspend threshold.
		$maxAutoSuspendedAge = time() - $conf->general->autoSuspendAfterTime;

		$autoSuspendRows = $db->getRows(str_queryf(
			"SELECT
				run_useragent.id as run_useragent_id,
				job_id,
				useragent_id
			FROM
				run_useragent,
				runs
			WHERE run_useragent.run_id = runs.id
				AND run_useragent.status = 0
				AND run_useragent.updated < %s;",
			swarmdb_dateformat( $maxAutoSuspendedAge )
		));

		$numAutoSuspendedRunRows = 0;

		if ( $autoSuspendRows ) {
			$numAutoSuspendedRunRows = count($autoSuspendRows);

			foreach( $autoSuspendRows as $autoSuspendRow ){
				$db->query(str_queryf(
					"UPDATE run_useragent
					SET	status = %u,
						results_id = NULL
					WHERE id = %u;",
					JobAction::$STATE_SUSPENDED,
					$autoSuspendRow->run_useragent_id
				));

				$db->query(str_queryf(
					'UPDATE
						job_useragent
					SET calculated_summary = NULL
					WHERE job_id = %u
						AND useragent_id = %s;',
					$autoSuspendRow->job_id,
					$autoSuspendRow->useragent_id
				));
			}
		}

		$this->setData(array(
			"resetTimedoutRuns" => $resetTimedoutRuns,
			"autoSuspendedRuns" => $numAutoSuspendedRunRows,
		));
	}
}

