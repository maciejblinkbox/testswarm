<?php
/**
 * "Wipebrowserjob" action
 * Sourced from "Wipejob" action
 *
 * @author Marc Fallows, 2013
 * @since 0.1.0
 * @package TestSwarm
 */

class WipebrowserjobAction extends Action {

	/**
	 * @actionMethod POST: Required.
	 * @actionParam int job_id
	 * @actionParam string type: one of 'delete', 'reset'.
	 * @actionAuth: Required.
	 */
	public function doAction() {
		$db = $this->getContext()->getDB();
		$request = $this->getContext()->getRequest();

		$jobID = $request->getInt( 'job_id' );
		$useragentID = $request->getVal( "useragent_id" );
		$wipeType = $request->getVal( 'type' );

		if ( !$jobID || !$useragentID || !$wipeType ) {
			$this->setError( 'missing-parameters' );
			return;
		}

		if ( !in_array( $wipeType, array( 'delete', 'reset', 'resetsuspended', 'resetfailed', 'suspend' ) ) ) {
			$this->setError( 'invalid-input', 'Invalid wipeType' );
			return;
		}

		$projectID = $db->getOne(str_queryf(
			'SELECT
				project_id
			FROM jobs
			WHERE id = %u;',
			$jobID
		));

		if ( !$projectID ) {
			$this->setError( 'invalid-input', 'Job not found' );
			return;
		}

		// Check authentication
		if ( !$this->doRequireAuth( $projectID ) ) {
			return;
		}

		if ( $wipeType === 'delete' ) {
			$db->query(str_queryf(
				'DELETE
				FROM job_useragent
				WHERE job_id = %u
					AND useragent_id = %s;',
				$jobID,
				$useragentID
			));
		} else {
			$db->query(str_queryf(
				'UPDATE
					job_useragent
				SET calculated_summary = NULL
				WHERE job_id = %u
					AND useragent_id = %s;',
				$jobID,
				$useragentID
			));
		}

		$runRows = $db->getRows(str_queryf(
			'SELECT id
			FROM runs
			WHERE job_id = %u;',
			$jobID
		));

		if ( $runRows ) {
			foreach ( $runRows as $runRow ) {
				if ( $wipeType === 'delete' ) {
					$db->query(str_queryf(
						'DELETE
						FROM run_useragent
						WHERE run_id = %u
							AND useragent_id = %s;',
						$runRow->id,
						$useragentID
					));
				} elseif ( $wipeType === 'reset' ) {
					$db->query(str_queryf(
						'UPDATE run_useragent
						SET
							status = 0,
							completed = 0,
							results_id = NULL,
							updated = %s
						WHERE run_id = %u
							AND useragent_id = %s;',
						swarmdb_dateformat( SWARM_NOW ),
						$runRow->id,
						$useragentID
					));
				} elseif ( $wipeType === 'resetsuspended' ) {
					$db->query(str_queryf(
						'UPDATE run_useragent
						SET
							status = 0,
							completed = 0,
							results_id = NULL,
							updated = %s
						WHERE run_id = %u
							AND status = %u
							AND useragent_id = %s;',
						swarmdb_dateformat( SWARM_NOW ),
						$runRow->id,
						JobAction::$STATE_SUSPENDED,
						$useragentID
					));
				} elseif ( $wipeType === 'suspend' ) {
					$db->query(str_queryf(
						'UPDATE run_useragent
						SET
							status = %u,
							updated = %s
						WHERE run_id = %u
							AND status = 0
							AND useragent_id = %s;',
						JobAction::$STATE_SUSPENDED,
						swarmdb_dateformat( SWARM_NOW ),
						$runRow->id,
						$useragentID
					));
				}
			}
		}

		$this->setData( array(
			'jobID' => $jobID,
			'type' => $wipeType,
			'result' => 'ok',
		) );
	}
}
