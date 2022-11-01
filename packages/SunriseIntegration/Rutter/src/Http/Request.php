<?php

namespace SunriseIntegration\Rutter\Http;


/**
 * Stores and formats the parameters for the request
 */
class Request
{
	const METHOD_OPTIONS  = 'OPTIONS';
	const METHOD_GET      = 'GET';
	const METHOD_HEAD     = 'HEAD';
	const METHOD_POST     = 'POST';
	const METHOD_PUT      = 'PUT';
	const METHOD_DELETE   = 'DELETE';
	const METHOD_TRACE    = 'TRACE';
	const METHOD_CONNECT  = 'CONNECT';
	const METHOD_PATCH    = 'PATCH';
	const METHOD_PROPFIND = 'PROPFIND';
	const OAUTH = 1;
	const HTTP_BASIC = 2;

	private $authorization;
	private $dataToProcess;
	private $uri;
	private $headers;
	private $method;
	private $body;
	private $user;
	private $password;
	private $type;


	/**
	 * RequestParameters constructor.
	 *
	 * @param $data
	 */
	public function __construct($data)
	{
		$this->dataToProcess = $data;
	}

	/**
	 * @return mixed
	 */
	public function getBody() {
		return $this->body;
	}

	/**
	 * @param mixed $body
	 *
	 * @return Request
	 */
	public function setBody( $body ) {
		$this->body = $body;

		return $this;
	}


	/**
	 * @return mixed
	 */
	public function getDataToProcess() {
		return $this->dataToProcess;
	}

	/**
	 * @param mixed $dataToProcess
	 *
	 * @return Request
	 */
	public function setDataToProcess( $dataToProcess ) {
		$this->dataToProcess = $dataToProcess;

		return $this;
	}

	/**
	 * @return mixed
	 */
	public function getUri() {
		return $this->uri;
	}

	/**
	 * @param mixed $uri
	 *
	 * @return Request
	 */
	public function setUri( $uri ) {
		$this->uri = $uri;

		return $this;
	}

	/**
	 * @return mixed
	 */
	public function getHeaders() {
		return $this->headers;
	}

	/**
	 * @param mixed $headers
	 *
	 * @return Request
	 */
	public function setHeaders( $headers ) {
		$this->headers = $headers;

		return $this;
	}

	/**
	 * @return mixed
	 */
	public function getMethod() {
		return $this->method;
	}

	/**
	 * @param mixed $method
	 *
	 * @return Request
	 */
	public function setMethod( $method ) {
		$this->method = $method;

		return $this;
	}

	/**
	 * @return mixed
	 */
	public function getUser() {
		return $this->user;
	}

	/**
	 * @param mixed $user
	 *
	 * @return Request
	 */
	public function setUser( $user ) {
		$this->user = $user;

		return $this;
	}

	/**
	 * @return mixed
	 */
	public function getPassword() {
		return $this->password;
	}

	/**
	 * @param mixed $password
	 *
	 * @return Request
	 */
	public function setPassword( $password ) {
		$this->password = $password;

		return $this;
	}

	/**
	 * @return mixed
	 */
	public function getType() {
		return $this->type;
	}

	/**
	 * @param mixed $type
	 *
	 * @return Request
	 */
	public function setType( $type ) {
		$this->type = $type;

		return $this;
	}




	/**
	 * Query string representation for HTTP request.
	 *
	 * @return string Query string formatted parameters.
	 */
	public function toQueryString()
	{
		return http_build_query($this->toArray(), '', '&');
	}
}
