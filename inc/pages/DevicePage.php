<?php
/**
 * "Device" page.
 *
 * @author Marc Fallows, 2013
 * @since 2.0.0
 * @package TestSwarm
 */

class DevicePage extends Page {

	public function execute() {
		$context = $this->getContext();
		$request = $context->getRequest();
		$item = $request->getInt( 'item' );

		$action = DeviceAction::newFromContext( $context->createDerivedRequestContext(
			array(
				'clients' => true,
				'results' => true,
				'item' => $item,
			)
		) );
		$action->doAction();

		$this->setAction( $action );
		$this->content = $this->initContent();
	}

	protected function initContent() {
		$this->setTitle( 'Device' );
		$this->bodyScripts[] = swarmpath( "js/device.js" );

		$error = $this->getAction()->getError();
		$data = $this->getAction()->getData();
		$html = '';

		$html .= '<script>SWARM.deviceInfo = ' . json_encode( $data["info"] ) . ';</script>';

		if ( $error ) {
			$html .= html_tag( 'div', array( 'class' => 'alert alert-error' ), $error['info'] );
		}

		if ( !isset( $data['info'] ) ) {
			return $html;
		}

		$info = $data['info'];

		$this->setSubTitle( '<span class="device-name">' . $info['name'] . '</span>' );

		$html .= '<h3>Information</h3>'
			. '<div class="alert alert-error swarm-savedevice-error" style="display: none;"></div>'
			. '<table class="table table-striped swarm-device">'
			. '<tbody>'
			. '<tr><th>Device Name</th><td>'
			. '<span class="device-name-edit">'
				. '<span class="device-name device-name-source">' . htmlspecialchars( $info['name'] ) . '</span> '
				. '<span class="device-name-edit-icon"><span class="icon-edit"></span></span>'
			. '</span>'
			. '<div class="device-name-form form-inline" style="display: none;">'
				. '<input type="text" class="device-name-form-input" /> '
				. '<div class="device-name-form-submit btn btn-primary">Save</div> '
				. '<div class="device-name-form-cancel btn">Cancel</div> '
			. '</div>'
			. '</td></tr>'
			. '<tr><th>Device Key</th><td>'
			. '<code>' . htmlspecialchars( $info['deviceKey'] ) . '</code>'
			. '</td></tr>'
			. '<tr><th>Device Type</th><td>'
				. $info['deviceType']
			. '</td></tr>'
			. '<tr><th>Model</th><td>'
			. '<code>' . htmlspecialchars( $info['model'] ) . '</code>'
			. '</td></tr>'
			. '</tbody></table>';

		$html .= '<h3>Recent Results</h3>'
			. $this->getStatusLegend()
			. '<table class="table table-condensed table-bordered swarm-device-health">'
			. '<tbody>'
			. '<tr>';

		foreach ( $data['resultsDashboard'] as $resultDashboard ) {
			$displayInfo = $resultDashboard['uaData']['displayInfo'];

			$html .= '<td>'
				. '<span class="swarm-device-run-health swarm-device-run-health-' . $resultDashboard['health'] . '" style="margin-top: '. ($resultDashboard['rowIndex'] * 5) .'px;">'
				. '<span class="swarm-device-run-client"><span class="swarm-device-run-client-colour" style="background-color: ' . $resultDashboard['clientContrastingColour'] . ';"></span></span>'
				. BrowserInfo::buildIconHtml( $displayInfo )
				. '</span>'
				. '</td>';
		}

		$html .= '</tr>'
			. '</tbody></table>';

		$html .= '<h3>Device Details History</h3>';
		if ( !$data['clientDetailGroups'] ) {
			$html .= '<div class="alert alert-info">Device has no clients.</div>';
		} else {
			$html .= '<table class="table table-striped swarm-device-history">'
				. '<thead><tr><th>Details</th><th>UA ID</th><th>Clients</th></thead>'
				. '<tbody>';

			foreach ( $data['clientDetailGroups'] as $detailGroup ) {
				$html .= '<tr>'
					. '<td>';

				foreach( $detailGroup['deviceDetails'] as $detailKey => $detail ) {
					$html .= '<div class="device-details">'
						. '<strong>' . snakeCaseToTitle($detailKey) . "</strong>: " . $detail
						. '</div>';
				}

				$html .= '</td>'
					. '<td><code>' . $detailGroup['uaID'] . '</code></td>'
					. '<td>';

				foreach( $detailGroup['clients'] as $client ) {
					$html .= html_tag( 'a', array( 'href' => $client['viewUrl'], 'class' => 'client-id-key' ), '#' . $client['id'] );
				}

				$html .= '</td>'
					. '</tr>';
			}

			$html .= '</tbody></table>';
		}

		$html .= '<h3>Log</h3>';
		if ( !$data['results'] ) {
			$html .= '<div class="alert alert-info">Device has no run log.</div>';
		} else {
			$html .= '<table class="table table-striped">'
				. '<thead><tr><th>Result</th><th>Project</th><th>Client</th></th><th>Job</th><th>Run</th><th>Status</th>'
				. '<tbody>';

			foreach ( $data['results'] as $run ) {
				$html .= '<tr>'
					. '<td>' . html_tag( 'a', array( 'href' => $run['viewUrl'] ), '#' . $run['id'] ) . '</td>'
					. '<td>' . ( $run['project']
						? html_tag( 'a', array( 'href' => $run['project']['viewUrl'] ), $run['project']['display_title'] )
						: '-' ) . '</td>'
					. '<td>' . html_tag( 'a', array( 'href' => $run['viewClientUrl'] ), '#' . $run['clientId'] ) . '</td>'
					. '<td class="swarm-device-log-job-name">' . ( $run['job']
						? html_tag( 'a', array( 'href' => $run['job']['viewUrl'] ), $run['job']['nameText'] )
						: '<em>Job has been deleted</em>' ) . '</td>'
					. '<td class="swarm-device-log-run-name">' . ( $run['job'] && $run['run']
						? html_tag( 'a', array( 'href' => $run['job']['viewUrl'] ), $run['run']['name'] )
						: '<em>Job has been deleted</em>' ) . '</td>'
					. JobPage::getJobStatusHtmlCell( $run['status'] )
					. '</tr>';
			}

			$html .= '</tbody></table>';
		}

		return $html;
	}

	public static function getStatusLegend() {
		return
			'<table class="table table-condensed table-bordered swarm-device-run-health-legend">'
			. '<tbody>'
			. '<tr>'
			. '<td class="swarm-device-run-health swarm-device-run-health-good"> </td>'
			. '<td>Good</td>'
			. '<td>Device run has completed and passed all tests.</td>'
			. '</tr>'
			. '<tr>'
			. '<td class="swarm-device-run-health swarm-device-run-health-ok"> </td>'
			. '<td>OK</td>'
			. '<td>Device run has completed all tests, but there were errors or failures.</td>'
			. '</tr>'
			. '<tr>'
			. '<td class="swarm-device-run-health swarm-device-run-health-bad"> </td>'
			. '<td>Bad</td>'
			. '<td>Device run was aborted due to a heartbeat. The runner did not heartbeat to the server in time.</td>'
			. '</tr>'
			. '<tr>'
			. '<td class="swarm-device-run-health swarm-device-run-health-severe"> </td>'
			. '<td>Severe</td>'
			. '<td>Device run was aborted due to a lost client. The client did not communicate with the server in time.</td>'
			. '</tr>'
			. '</tbody></table>';
	}

}
