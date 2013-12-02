<?php
/**
 * "Device" action.
 *
 * @author Marc Fallows, 2013
 * @since 2.0.0
 * @package TestSwarm
 */

class DeviceAction extends Action {

	/**
	 * @actionParam string item: Device id.
	 */
	public function doAction() {
		$context = $this->getContext();
		$db = $context->getDB();
		$request = $context->getRequest();
		$resultLimit = 100;
		$resultDashboardLimit = 40;
		$clientLimit = 100;

		$item = $request->getInt( 'item' );
		$includeClients = $request->getBool( 'clients' );
		$includeResults = $request->getBool( 'results' );
		if ( !$item ) {
			$this->setError( 'missing-parameters' );
			return;
		}

		// Device information
		$row = $db->getRow(str_queryf(
			'SELECT
				id,
				name,
				device_type,
				device_key,
				model
			FROM
				devices
			WHERE id = %u;',
			$item
		));
		if ( !$row ) {
			$this->setError( 'invalid-input', 'Device not found' );
			return;
		}

		$info = array(
			'id' => intval( $row->id ),
			'name' => $row->name,
			'deviceKey' => $row->device_key,
			'deviceType' => $row->device_type,
			'model' => empty( $row->model ) ? "N/A" : $row->model,
			'viewUrl' => swarmpath( "device/{$row->id}" ),
		);

		$data = array(
			'info' => $info,
		);

		if ($includeResults) {

			// Boynton's list of "eleven colors that are almost never confused"
			$contrastingColours = array(
				//"white",
				"red",
				"yellow",
				"blue",
				"gray",
				"green",
				"brown",
				"pink",
				//"black",
				"orange",
				"purple"
			);
			$numberOfContrastingColours = count($contrastingColours);

			// Run results
			$results = array();
			$resultDashboardItems = array();

			$rows = $db->getRows(str_queryf(
				'SELECT
					runresults.id,
					runresults.run_id,
					runresults.client_id,
					runresults.status,
					runresults.total,
					runresults.fail,
					runresults.error,
					runresults.updated,
					runresults.created,
					clients.device_index,
					clients.useragent
				FROM runresults
				INNER JOIN clients
					ON clients.id = runresults.client_id
				WHERE clients.device_id = %u
					AND runresults.status <> 1
				ORDER BY runresults.updated DESC
				LIMIT %u;',
				$item,
				max($resultLimit, $resultDashboardLimit)
			));
			if ( $rows ) {
				$deviceIndexToRowIndexMap = array();

				foreach ( $rows as $row ) {

					if ( count($results) < $resultLimit ) {

						$runRow = $jobRow = false;
						$result = array(
							'id' => intval( $row->id ),
							'clientId' => intval( $row->client_id ),
							'viewUrl' => swarmpath( "result/{$row->id}" ),
							'viewClientUrl' => swarmpath( "client/{$row->client_id}" ),
							'status' => JobAction::getRunresultsStatus( $row ),
						);
						$runRow = $db->getRow(str_queryf(
							'SELECT
								name,
								job_id
							FROM runs
							WHERE id = %u;',
							$row->run_id
						));
						if ( $runRow ) {
							$jobRow = $db->getRow(str_queryf(
								'SELECT
									id,
									name,
									project_id
								FROM
									jobs
								WHERE id = %u',
								$runRow->job_id
							));
							if ( $jobRow ) {
								$projectRow = $db->getRow(str_queryf(
									'SELECT
										display_title
									FROM projects
									WHERE id = %s;',
									$jobRow->project_id
								));
								$result['job'] = array(
									// See also JobAction:;getInfo
									'nameText' => strip_tags( $jobRow->name ),
									'viewUrl' => swarmpath( "job/{$jobRow->id}" ),
								);
								$result['run'] = array(
									'name' => $runRow->name
								);
								$result['project'] = array(
									'id' => $jobRow->project_id,
									'display_title' => $projectRow->display_title,
									'viewUrl' => swarmpath( "project/{$jobRow->project_id}" ),
								);
							}
						}
						// Runs and jobs could be deleted, results are preserved.
						if ( !$jobRow ) {
							$result['job'] = null;
							$result['run'] = null;
							$result['project'] = null;
						}
						$results[] = $result;
					}


					if ( count($results) < $resultDashboardLimit ) {
						$contrastingColourIndex = $row->device_index % $numberOfContrastingColours;
						$clientContrastingColour = $contrastingColours[$contrastingColourIndex];

						if ( !isset($deviceIndexToRowIndexMap[$row->device_index]) )
						{
							$deviceIndexToRowIndexMap[$row->device_index] = count($deviceIndexToRowIndexMap);
						}

						$rowIndex = $deviceIndexToRowIndexMap[$row->device_index];

						$status = JobAction::getRunresultsStatus( $row );
						switch( $status) {
							case "passed":
								$health = "good";
								break;

							case "error":
							case "failed":
								$health = "ok";
								break;

							case "heartbeat":
								$health = "bad";
								break;

							case "lost":
								$health = "severe";
								break;
						}

						$bi = BrowserInfo::newFromContext( $context, $row->useragent );

						$resultDashboardItem = array(
							'health' => $health,
							'clientContrastingColour' => $clientContrastingColour,
							'contrastingColourIndex' => $contrastingColourIndex,
							'rowIndex' => $rowIndex,
							'clientId' => $row->client_id,
							'uaData' => $bi->getUaData()
						);

						$resultDashboardItems[] = $resultDashboardItem;
					}
				}
			}

			$data['results'] = $results;
			$data['resultsDashboard'] = $resultDashboardItems;
		}

		if ( $includeClients ) {

			// Clients
			$clients = array();
			$clientDetailGroups = array();
			$clientDetailIndex = -1;
			$lastDeviceDetails = null;

			$rows = $db->getRows(str_queryf(
				'SELECT
					id,
					useragent_id,
					details_json
				FROM clients
				WHERE device_id = %u
				ORDER BY updated DESC
				LIMIT %u;',
				$item,
				$clientLimit
			));

			if ( $rows ) {
				foreach ( $rows as $row ) {

					$deviceDetails = array();

					if( !empty($row->details_json)){
						$deviceDetails = json_decode(gzdecode($row->details_json));
					}

					$client = array(
						'id' => intval( $row->id ),
						'deviceDetails' => $deviceDetails,
						'viewUrl' => swarmpath( "client/{$row->id}" ),
						'uaID' => $row->useragent_id
					);

					if($lastDeviceDetails != $deviceDetails)
					{
						$clientDetailIndex++;
						$clientDetailGroups[$clientDetailIndex] = array(
							'deviceDetails' => $deviceDetails,
							'clients' => array(),
							'uaID' => $row->useragent_id
						);

						$lastDeviceDetails = $deviceDetails;
					}

					$clientDetailGroups[$clientDetailIndex]['clients'][] = $client;

					$clients[] = $client;
				}
			}

			$data['clients'] = $clients;
			$data['clientDetailGroups'] = $clientDetailGroups;
		}

		$this->setData( $data );
	}
}
