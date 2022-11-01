<?php

namespace SunriseIntegration\Etsy\Models;

use ArrayAccess;
use Exception;

use JsonSerializable;
use RuntimeException;
use SunriseIntegration\Etsy\Http\Request;
use SunriseIntegration\Etsy\Http\Client;

abstract class AbstractEntity implements JsonSerializable, ArrayAccess
{


    protected $params;

    /**
     * Object attributes
     *
     * @var array
     */
    protected $_data = [];

    /**
     * handler for non existing properties
     * @var array
     */
    protected $_magicProperties = array();

    /**
     * @var Client
     */
    private $request;

    /**
     * @var Auth
     */
    private $apiAuthorization = null;

    /**
     * @return Auth null
     */
    public function getApiAuthorization()
    {
        return $this->apiAuthorization;
    }

    /**
     * @param Auth $apiAuthorization
     *
     * @return $this
     */
    public function setApiAuthorization($apiAuthorization)
    {
        $this->apiAuthorization = $apiAuthorization;

        return $this;
    }

    /**
     * AbstractEntity constructor.
     *
     * @param $data string
     * @throws RuntimeException
     */
    public function __construct($data = null)
    {
//        if ($auth !== null) {
//            $this->setApiAuthorization($auth);
//        }
        $this->setRequest(new Client());

        $this->load($data);
    }

    public function load($data)
    {

        $entity = [];

        if (is_string($data) && $data !== '') {
            /**
             * @var array $entity
             */
            $entity = json_decode($data, true);
        } else {
            $entity = $data;
        }

        if (!empty($entity)) {

            if (!empty($entity->results)) {
                if(is_array($entity->results)){
                    $entity = $entity->results[0];
                } else {
                    $entity = $entity->results;
                }
            }

            foreach ($entity as $property => $value) {
                $property = strtolower($property);

                $method = 'set' . ucwords($property, '_');
                $method = str_replace('_', '', $method);

                if (method_exists($this, $method)) {
                    if ($value instanceof \stdClass) {
                        $value = (array)$value;
                    }
                    $this->{$method}($value);
                } else if (property_exists($this, $property)) {
                    $this->{$property} = $value;
                } else {
                    $this->_magicProperties[$property] = $value;
                }
            }
        }

    }

    /**
     * @return Client
     */
    public function getRequest(): Client
    {
        return $this->request;
    }

    /**
     * @param Client $request
     *
     * @return AbstractEntity
     */
    public function setRequest(Client $request): AbstractEntity
    {
        $this->request = $request;

        return $this;
    }

    public function __call($method, $params)
    {
        $methodName = substr($method, 3);
        //$methodName = lcfirst($methodName);

        $methodName = strtolower(preg_replace('/(?<!^)[A-Z]/', '_$0', $methodName));


        if (strpos($method, 'get') !== false) {
            if (property_exists($this, $methodName)) {
                return $this->$methodName;
            }

            if (array_key_exists($methodName, $this->_magicProperties)) {
                $methodName = strtolower(preg_replace('/(?<!^)[A-Z]/', '_$0', $methodName));
                return $this->_magicProperties[$methodName];
            }

        }


        if (strpos($method, 'set') !== false) {
            if (property_exists($this, $methodName)) {
                $this->$methodName = $params[0];
            } else {
                $this->_magicProperties[$methodName] = $params[0];
            }

        } else {
            if (isset($this->$method)) {
                $func = $this->$method;
                return call_user_func_array($func, $params);
            }
        }

        return null;
    }


    /**
     * all call to the  specified endpoint
     * @param $endpoint
     * @param null $query
     *
     * @return mixed
     */
    protected function getRequestData($endpoint, $query = null)
    {
        $request = new Request([]);
        $request->setHeaders([
            'Accept: application/json',
            'Content-Type: application/json',
            'X-Etsy-Access-Token: ' . $this->getApiAuthorization()->getPassword(),
        ]);
        $request->setMethod(Request::METHOD_GET);

        $request->setUri($this->getPostUrl($endpoint));

        return $this->getRequest()->send($request);

    }

    /**
     * Routes all call to the  specified endpoint
     * @param $endpoint
     * @param $dataToPost
     * @param string $httpMethod
     *
     * @return mixed
     * @throws Exception
     */
    protected function postData($endpoint, $dataToPost, $httpMethod = Request::METHOD_POST)
    {
        throw new Exception('Method not implemented');
    }

    public function toArray()
    {
        $properties = $this->createArray();

        if (!empty($this->_magicProperties)) {
            foreach ($this->_magicProperties as $propertyName => $propertyValue) {
                $properties[$propertyName] = $propertyValue;
            }
            unset($properties['_magicProperties']);
        }

        foreach ($properties as $key => $value) {
            if ($value === null || $value === '' || (is_array($value) && empty($value)) || $key === '_data' || $key === 'request' || $key === 'apiAuthorization') {
                unset($properties[$key]);
            }
        }

        return $properties;
    }


    public function toJson($includeMetafields = true)
    {

        $json = $this->createJson();

//        if (!$includeMetafields) {
//
//            $json = json_decode($json);
//
//            if (property_exists($json, 'order') || property_exists($json, 'customer') || property_exists($json, 'product')) {
//                unset(current($json)->metafields);
//            } else if (property_exists($json, 'metafields')) {
//                unset($json->metafields);
//            }
//
//            $json = json_encode($json);
//        }

        return $json;
    }

    public function getPostUrl($endpoint)
    {
        $store = str_replace('http | https', '', $this->getApiAuthorization()->getStoreUrl());

        $postUrl = 'https://' . $this->getApiAuthorization()->getSecret() . ':' . $this->getApiAuthorization()->getPassword() . '@' . $store . $endpoint;

        return $postUrl;
    }


    /**
     * @return array
     */
    private function createArray()
    {
        $properties = get_object_vars($this);

        return $this->convertToArray($properties);
    }


    /**
     * @param array $properties
     *
     * @return array
     */
    protected function convertToArray(array $properties): array
    {
        foreach ($properties as $key => $value) {

            if (is_array($value)) {
                $properties[$key] = $this->convertToArray($value);
            } else {
                if (is_object($value) && method_exists($value, 'toArray')) {
                    $properties[$key] = $value->toArray();
                }
            }
        }

        return $properties;
    }

    /**
     * Convert object data to JSON
     *
     * @return string
     */
    private function createJson()
    {
        return json_encode($this);
    }

    /**
     * Implementation of \JsonSerializable::jsonSerialize()
     *
     * Returns data which can be serialized by json_encode(), which is a value of any type other than a resource.
     * @return mixed
     * @link http://php.net/manual/en/jsonserializable.jsonserialize.php
     */
    public function jsonSerialize()
    {
        return $this->toArray();
    }

    /**
     * Implementation of \ArrayAccess::offsetSet()
     *
     * @param string $offset
     * @param mixed $value
     * @return void
     * @link http://www.php.net/manual/en/arrayaccess.offsetset.php
     */
    public function offsetSet($offset, $value)
    {
        $this->_data[$offset] = $value;
    }

    /**
     * Implementation of \ArrayAccess::offsetExists()
     *
     * @param string $offset
     * @return bool
     * @link http://www.php.net/manual/en/arrayaccess.offsetexists.php
     */
    public function offsetExists($offset)
    {
        return isset($this->_data[$offset]) || array_key_exists($offset, $this->_data);
    }

    /**
     * Implementation of \ArrayAccess::offsetUnset()
     *
     * @param string $offset
     * @return void
     * @link http://www.php.net/manual/en/arrayaccess.offsetunset.php
     */
    public function offsetUnset($offset)
    {
        unset($this->_data[$offset]);
    }

    /**
     * Implementation of \ArrayAccess::offsetGet()
     *
     * @param string $offset
     * @return mixed
     * @link http://www.php.net/manual/en/arrayaccess.offsetget.php
     */
    public function offsetGet($offset)
    {
        if (isset($this->_data[$offset])) {
            return $this->_data[$offset];
        }
        return null;
    }
}
