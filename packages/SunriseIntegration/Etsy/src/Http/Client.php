<?php

namespace SunriseIntegration\Etsy\Http;

class Client
{
	protected $lastRequest;

	/**
	 * Curl connection
	 * @var Curl
	 */
	private $curl;



	protected function setLastRequest($request) {
		$this->lastRequest = $request;

		return $this;
	}


	public function send(Request $request )
	{

		$handle = $this->init();

		$options = array(
			CURLOPT_URL => $request->getUri(),
			CURLOPT_SSL_VERIFYHOST =>2,
			CURLOPT_TIMEOUT    => 20,
			CURLOPT_CONNECTTIMEOUT => 10,
			CURLOPT_SSL_VERIFYPEER => true,
			CURLOPT_RETURNTRANSFER => true,
			CURLINFO_HEADER_OUT    => true,
			CURLOPT_VERBOSE        => true,
			CURLOPT_HEADER         => false,
			CURLOPT_FOLLOWLOCATION => false,
			CURLOPT_STDERR => $verbose = fopen('php://temp', 'rwb+'),
            CURLOPT_CAINFO => __DIR__ . DIRECTORY_SEPARATOR . 'Curl' . DIRECTORY_SEPARATOR .  'cacert.pem'
		);


		$options[ CURLOPT_HTTPHEADER ] = $request->getHeaders();


		switch ( $request->getMethod() ) {
			case Request::METHOD_POST:
				$options[ CURLOPT_POST ] = 1;
				break;
			case Request::METHOD_GET:
				$options[ CURLOPT_POST ] = 0;
				break;
			case Request::METHOD_PUT :
				$options[ CURLOPT_CUSTOMREQUEST ] = $request->getMethod();
				break;
			case Request::METHOD_DELETE :
				$options[ CURLOPT_CUSTOMREQUEST ] = $request->getMethod();
				break;
		}

		if (is_array($request->getBody()) && !empty($request->getBody())) {
			$options[ CURLOPT_POSTFIELDS ] = json_encode( $request->getBody() );
		} else if (!empty($request->getBody())) {
			$options[ CURLOPT_POSTFIELDS ] = $request->getBody();
		}

		switch ( $request->getType() ) {
			case Request::OAUTH :
				$options[ CURLOPT_HTTPAUTH ] = CURLAUTH_BASIC;
				break;
			case Request::HTTP_BASIC :
				$options[ CURLOPT_HTTPAUTH ] = CURLAUTH_BASIC;
				$options[ CURLOPT_USERPWD ] = $request->getUser() . ':' . $request->getPassword();
				break;
			default :
				$options[ CURLOPT_HTTPAUTH ] = CURLAUTH_NONE;
				break;
		}

		$this->setoptArray($handle, $options);

		$response = $this->exec($handle);
		$this->setLastRequest( curl_getinfo( $handle ) );
		$this->close($handle);
		return $response;
	}

    /**
     * @see http://php.net/curl_init
     * @param string $url
     * @return resource cURL handle
     */
    public function init($url = null)
    {
        return curl_init($url);
    }

    /**
     * @see http://php.net/curl_setopt_array
     * @param resource $ch
     * @param array $options
     * @return bool
     */
    public function setoptArray($ch, array $options)
    {
        return curl_setopt_array($ch, $options);
    }

    /**
     * @see http://php.net/curl_exec
     * @param resource $ch
     * @return mixed
     */
    public function exec($ch)
    {
        return curl_exec($ch);
    }

    /**
     * @see http://php.net/curl_close
     * @param resource $ch
     */
    public function close($ch)
    {
        curl_close($ch);
    }

	public function getLastRequest() {
		return $this->lastRequest;
	}
}
