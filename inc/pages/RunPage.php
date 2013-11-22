<?php
/**
 * "Run" page.
 *
 * @author John Resig, 2008-2011
 * @since 0.1.0
 * @package TestSwarm
 */

class RunPage extends Page {

	protected function initContent() {
		$browserInfo = $this->getContext()->getBrowserInfo();
		$conf = $this->getContext()->getConf();
		$request = $this->getContext()->getRequest();

		$deviceKey = $request->getVal('device_key', '');

		$this->setTitle( 'Test runner' );
		$this->setClassName( 'run' );

		$runToken = null;

		if ( $conf->client->requireRunToken ) {
			$runToken = $request->getVal( "run_token" );
			if ( !$runToken ) {
				return '<div class="alert alert-error">This swarm has restricted access to join the swarm.</div>';
			}
		}

		$this->bodyScripts[] = swarmpath( "js/base64encoder.js" );
		$this->bodyScripts[] = swarmpath( "js/run.js?" . time() );

		$client = Client::newFromContext( $this->getContext(), $runToken );
		$runName = $client->getClientRow()->name;

		if( !empty($deviceKey))
		{
			$device = Device::newFromContext( $this->getContext() );
			$client->linkToDevice($device);

			$runName = $device->getDeviceRow()->name;
		}

		$html = '<script>'
			. 'SWARM.client_id = ' . json_encode( $client->getClientRow()->id ) . ';'
			. 'SWARM.run_token = ' . json_encode( $runToken ) . ';'
			. '</script>';

		$html .=
			'<div class="row run-status">'
				. '<div class="span2">'
					. $browserInfo->getIconHtml()
				. '</div>'
				. '<div class="span7">'
					. '<h2>' . htmlspecialchars( $runName ) . '</h2>'
					. '<p><strong>Status:</strong> <span id="msg"></span></p>'
					. '<p><strong>Refresh:</strong> Press Enter / OK to refresh</p>'
				. '</div>'
			. '</div>'
			. '<div class="well">'
				. '<div id="iframes"></div>'
			. '</div>'
			. '<div class="well">'
				. '<h3>History</h3>'
				. '<ul id="history"></ul>'
			. '</div>';

		return $html;
	}
}
