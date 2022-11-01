<?php

namespace SunriseIntegration\Etsy\Models;

class Auth extends AbstractEntity
{
    private $api_key;
    private $secret;
    private $password;
    private $store_url;

    /**
     * Authorization constructor.
     *
     * @param array $parameters
     */
    public function __construct($parameters = [])
    {
        parent::__construct();
        if (!empty($parameters)) {
            foreach ($parameters as $method => $value) {
                $method = "set$method";
                $method = str_replace(['_', ' '], '', $method);

                if (method_exists($this, $method)) {
                    $this->{$method}($value);
                }
            }
        }
    }

    /**
     * @return int
     */
    public function getType()
    {
        return $this->type;
    }

    /**
     * @param int $type
     *
     * @return Auth
     */
    public function setType($type)
    {
        $this->type = $type;

        return $this;
    }

    /**
     * @return string
     */
    public function getApiKey()
    {
        return $this->api_key;
    }

    /**
     * @param string $api_key
     *
     * @return Auth
     */
    public function setApiKey($api_key)
    {
        $this->api_key = $api_key;

        return $this;
    }

    /**
     * @return string
     */
    public function getSecret()
    {
        return $this->secret;
    }

    /**
     * @param string $secret
     *
     * @return Auth
     */
    public function setSecret($secret)
    {
        $this->secret = $secret;

        return $this;
    }

    /**
     * @return string
     */
    public function getPassword()
    {
        return $this->password;
    }

    /**
     * @param string $password
     *
     * @return Auth
     */
    public function setPassword($password)
    {
        $this->password = $password;

        return $this;
    }

    /**
     * @return string
     */
    public function getStoreUrl()
    {
        return $this->store_url;
    }

    /**
     * @param string $store_url
     *
     * @return Auth
     */
    public function setStoreUrl($store_url)
    {
        $this->store_url = $store_url;

        return $this;
    }


}
