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

		$uaItem = $browserInfo->getSwarmUaItem();

		$runToken = null;
		if ( $conf->client->requireRunToken ) {
			$runToken = $request->getVal( "run_token" );
			if ( !$runToken ) {
				throw new SwarmException( "This swarm has restricted access to join the swarm." );
			}
		}

		$this->setTitle( "Test runner" );
		$this->displayPageTitle = false;
		$this->displayNavBar = false;
		$this->useContainerCssClass = true;
		
		if ( $browserInfo->getSwarmUaItem()->id === "PS3" ) {
			$this->bodyScripts[] = swarmpath( "js/base64encoder.js");
		}
		
		$this->bodyScripts[] = swarmpath( "js/run.js?" . time() );

		$client = null;

		// Try to get the client id from the cookie.
		$clientId = $this->getClientIdFromCookie();		
		if ( isset( $clientId ) ) {
			// If client id is present then try to reuse it. 
			// This might throw an exception if client id doesn't exist on the database, the user agent doesn't match or the ip address doesn't match the database record.
			try {
				$client = Client::newFromContext( $this->getContext(), $runToken, $clientId, true );		
			} catch ( Exception $e ) { }
		}
		
		// $client is not set if restoring client id failed or cookie is not present.
		if( !isset( $client ) ) { 
			$client = Client::newFromContext( $this->getContext(), $runToken );
			$clientId = $client->getClientRow()->id;
			$this->saveClientIdInCookie( $clientId );
		}		
		
		$deviceIP = $this->context->getRequest()->getIP();
		
		$html = '<script>'
			. 'SWARM.client_id = ' . json_encode( $clientId ) . ';'
			. 'SWARM.run_token = ' . json_encode( $runToken ) . ';'
			. 'SWARM.decode_html = ' . json_encode ( isMaple() ) . ';'
			. '</script>';

		$html .=
			'<div class="row">'
				. '<div class="span2">'
					. '<div class="well pagination-centered thumbnail">'
					. '<img src="' . swarmpath( "img/{$uaItem->displayicon}.sm.png" )
						. '" class="swarm-browsericon '
						. '" alt="' . htmlspecialchars( $uaItem->displaytitle )
						. '" title="' . htmlspecialchars( $uaItem->displaytitle ) . '">'
					. '<span class="label">' . htmlspecialchars( $uaItem->displaytitle ) . '</span>'
					. '<br/>'
					. '<span class="label label-info" id="deviceName" title="Device IP address or Testswarm Client ID">' . htmlspecialchars( $deviceIP ) . '</span>'
					. '</div>'
				. '</div>'
				. '<div class="span7">'
					. '<h2>' . htmlspecialchars( $client->getUserRow()->name ) . '</h2>'
					. '<p><strong>Status:</strong> <span id="msg"></span></p>'
				. '</div>'
			. '</div>'
			. '<div id="iframes"></div>'
			. '<div class="well">'
				. '<h3>History</h3>'
				. '<ul id="history"></ul>'
			. '</div>';

		return $html;
	}
	
	private function saveClientIdInCookie( $clientId ) {
		$expire = time() + 3600 * 24 * 30 * 12;
		setcookie( 'clientId', $clientId, $expire );
	}
	
	private function getClientIdFromCookie() {
		if( isset( $_COOKIE['clientId'] ) ) {			
			try {
				return intval( $_COOKIE['clientId'] );
			} catch ( Exception $e ) { }		
		}
		return null;
	}
}
