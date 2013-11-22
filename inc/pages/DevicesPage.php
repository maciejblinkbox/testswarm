<?php
/**
 * "Devices" page.
 *
 * @author Marc Fallows, 2013
 * @since 2.0.0
 * @package TestSwarm
 */

class DevicesPage extends Page {

	public function execute() {
		$action = DevicesAction::newFromContext( $this->getContext() );
		$action->doAction();

		$this->setAction( $action );
		$this->content = $this->initContent();
	}

	protected function initContent() {
		$context = $this->getContext();
		$request = $context->getRequest();

		$this->setTitle( 'Devices' );
		$this->bodyScripts[] = swarmpath( "js/devices.js" );

		$html = '';

		$error = $this->getAction()->getError();
		$data = $this->getAction()->getData();
		$item = $request->getVal( 'item' );
		$mode = $request->getVal( 'mode', 'clients' );

		if ( $error ) {
			$html .= html_tag( 'div', array( 'class' => 'alert alert-error' ), $error['info'] );
			return $html;
		}

		if ( !count( $data['devices'] ) ) {
			$html .= '<div class="alert alert-info">No devices found.</div>';
		} else {
			$html .= $this->showOverview( $data );
		}

		return $html;
	}

	/**
	 * @param Array $data Overview data from DevicesAction
	 * @return string: HTML
	 */
	protected function showOverview( $data ) {
		$context = $this->getContext();
		$request = $context->getRequest();

		$devices = $data['devices'];

		$sortField = $request->getVal( 'sort', 'name' );
		$sortDir = $request->getVal( 'sort_dir', 'asc' );

		$sortableFields = array( 'name', 'device_type', 'clients.updated', 'clients.created', 'clients.useragent_id' );

		$navigationSort = array();
		foreach ( $sortableFields as $field ) {
			$navigationSort[$field] = array(
				'toggleQuery' => array(
					'sort' => $field,
					'sort_dir' => $sortDir === 'asc' ? 'desc' : null,
				),
				'arrowHtml' => $sortField !== $field
					? '<b class="swarm-arrow-muted">'
					: (
					$sortDir === 'asc'
						? '<b class="swarm-arrow-up">'
						: '<b class="swarm-arrow-down">'
					)
			);
		}

		$html = '<table class="table table-striped swarm-devices">'
			. '<thead><tr>'
			. '<th class="swarm-toggle" data-toggle-query="' . htmlspecialchars( json_encode( $navigationSort['name']['toggleQuery'] ) ) . '">Device ' . $navigationSort['name']['arrowHtml'] . '</th>'
			. '<th class="swarm-toggle" data-toggle-query="' . htmlspecialchars( json_encode( $navigationSort['clients.useragent_id']['toggleQuery'] ) ) . '">Last UA ' . $navigationSort['clients.useragent_id']['arrowHtml'] . '</th>'
			. '<th class="swarm-toggle" data-toggle-query="' . htmlspecialchars( json_encode( $navigationSort['device_type']['toggleQuery'] ) ) . '">Type ' . $navigationSort['device_type']['arrowHtml'] . '</th>'
			. '<th class="swarm-toggle" data-toggle-query="' . htmlspecialchars( json_encode( $navigationSort['clients.updated']['toggleQuery'] ) ) . '">Last ping ' . $navigationSort['clients.updated']['arrowHtml'] . '</th>'
			. '<th class="swarm-toggle" data-toggle-query="' . htmlspecialchars( json_encode( $navigationSort['clients.created']['toggleQuery'] ) ) . '">Created ' . $navigationSort['clients.created']['arrowHtml'] . '</th>'
			. '</tr></thead>'
			. '<tbody>';

		foreach ( $devices as $info ) {

			$html .= '<tr>'
				. '<td><a href="' . htmlspecialchars( $info['viewUrl'] ) . '" title="View ' . htmlspecialchars( $info['name'] ) . ' device">' . htmlspecialchars( $info['name'] ) . '</td>'
				. '<td><code>' .$info['useragentID'] . '</code></td>'
				. '<td>' .$info['deviceType'] . '</td>'

				. ( $info['active']
					? '<td class="swarm-status-client swarm-status-client-active">' . $this->getPrettyDateHtml( $info, 'updated' ) . ' <span class="icon-ok"></span></td>'
					: '<td class="swarm-status-client swarm-status-client-inactive">' . $this->getPrettyDateHtml( $info, 'updated' ) . ' <span class="icon-remove"></span></td>'
				)
				. '<td>' . $this->getPrettyDateHtml( $info, 'created' ) . ' </td>'
				. '</tr>';
		}
		$html .= '</tbody></table>';

		return $html;
	}

}
