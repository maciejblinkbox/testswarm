<?php
/**
 * "Devices" action.
 *
 * @author Marc Fallows, 2013
 * @since 2.0.0
 * @package TestSwarm
 */

class DevicesAction extends Action {

	/**
	 * @actionParam string sort: [optional] What to sort the results by.
	 *  Must be one of "name" or "updated". Defaults to "name".
	 * @actionParam string sort_dir: [optional]
	 *  Must be one of "asc" (ascending) or "desc" (decending). Defaults to "asc".
	 * @actionParam string include: [optional] What filter to apply.
	 *  Must be one of "all", "active" or "inactive". Defaults to "active".
	 * @actionParam string item: Fetch only information from clients by this name.
	 */
	public function doAction() {
		$context = $this->getContext();
		$request = $context->getRequest();

		$sortField = $request->getVal( 'sort', 'name' );
		$sortDir = $request->getVal( 'sort_dir', 'asc' );

		if ( !in_array( $sortField, array( 'name', 'clients.updated', 'clients.created', 'device_type', 'clients.useragent_id' ) ) ) {
			$this->setError( 'invalid-input', "Unknown sort `$sortField`." );
			return;
		}

		if ( !in_array( $sortDir, array( 'asc', 'desc' ) ) ) {
			$this->setError( 'invalid-input', "Unknown sort direction `$sortDir`." );
			return;
		}

		$devices = $this->getDevices( $sortField, $sortDir );

		$this->setData( array(
			'devices' => $devices,
		) );
	}

	/**
	 * @param string $sortField
	 * @param string $sortDir
	 * @param string $include
	 * @param string|bool $name
	 */
	protected function getDevices( $sortField, $sortDir ) {
		$context = $this->getContext();
		$db = $context->getDB();

		$sortDirQuery = strtoupper( $sortDir );
		$sortFieldQuery = "ORDER BY $sortField $sortDirQuery";

		$whereClause = array();
		$whereClause[] = "outerClients.updated IS NULL";

		if ( count( $whereClause ) ) {
			$whereClause = 'WHERE ' . implode( ' AND ', $whereClause );
		} else {
			$whereClause = '';
		}

		$deviceRows = $db->getRows( str_queryf(
			"SELECT
				devices.id,
				devices.name,
				devices.device_type,
				clients.useragent_id,
				clients.updated,
				clients.created,
				clients.updated >= %s AS active
			FROM
				devices
			INNER JOIN clients
				ON clients.device_id = devices.id
			LEFT JOIN clients AS outerClients
				ON clients.device_id = outerClients.device_id
				AND clients.updated < outerClients.updated
			$whereClause
			$sortFieldQuery;",
			swarmdb_dateformat( Client::getMaxAge( $context ) )
		) );

		$devices = array();
		$resultsLimit = 30;

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

		if ( $deviceRows ) {
			foreach ( $deviceRows as $deviceRow ) {

				$device = array(
					'name' => $deviceRow->name,
					'id' => $deviceRow->id,
					'deviceType' => $deviceRow->device_type,
					'useragentID' => $deviceRow->useragent_id,
					'active' => $deviceRow->active,
					'viewUrl' => swarmpath( "device/{$deviceRow->id}" ),
				);
				$this->addTimestampsTo( $device, $deviceRow->updated, 'updated' );
				$this->addTimestampsTo( $device, $deviceRow->created, 'created' );
				$devices[$deviceRow->id] = $device;
			}
		}

		return $devices;
	}
}
