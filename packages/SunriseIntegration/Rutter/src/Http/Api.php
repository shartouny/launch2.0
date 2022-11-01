<?php

namespace SunriseIntegration\Rutter\Http;

use GuzzleHttp\Client;
use Monolog\Logger;
use Exception;
use SunriseIntegration\Etsy\Exceptions\RateLimitException;
use SunriseIntegration\Etsy\Pagination;

class Api
{

    public $rutterApiUrl;
    public $rutterClientId;
    public $rutterSecretKey;
    public $rutterPublicKey;
    public $rutterClientAccessToken = null;

    /**
     * @var Logger|null
     */
    public $logger;

    public $logChannel = 'rutter';

    function __construct($access_token = null)
    {
        if(config('app.env') === 'local') {
            $this->rutterApiUrl     = 'https://sandbox.rutterapi.com';                   //env('RUTTER_DEV_API_LINK');
            $this->rutterClientId   = '489e2fce-2629-41d8-afcc-2d0d1c897c0e';            //env('RUTTER_DEV_CLIENT_ID');
            $this->rutterSecretKey  = 'sandbox_sk_e626700e-f09e-4a05-bcc3-bc5764fbf608'; //env('RUTTER_DEV_SECRET_KEY');
            $this->rutterPublicKey  = 'sandbox_pk_ed3b88bf-e4ba-4199-a282-9e2ca9a0291c'; //env('RUTTER_DEV_PUBLIC_KEY');
            $this->rutterClientAccessToken  = $access_token;
        }
        else{
            $this->rutterApiUrl     = 'https://production.rutterapi.com';     //env('RUTTER_PROD_API_LINK');
            $this->rutterClientId   = '489e2fce-2629-41d8-afcc-2d0d1c897c0e'; //env('RUTTER_PROD_CLIENT_ID');
            $this->rutterSecretKey  = 'f889c18b-3ed4-4b32-a247-5b9da16e8cd1'; //env('RUTTER_PROD_SECRET_KEY');
            $this->rutterPublicKey  = '630a3eee-687b-4524-919a-425308082bcc'; //env('RUTTER_PROD_PUBLIC_KEY');
            $this->rutterClientAccessToken  = $access_token;
        }
    }

    function getStore()
    {
        return $this->makeCall('/store', 'GET', null);
    }

    function deleteConnection($connection)
    {
        return $this->makeCall('/connections/'.$connection, 'DELETE', null, [
            'delete' => true
        ]);
    }

    function exchangeToken($publicToken)
    {
        return $this->makeCall('/item/public_token/exchange', 'POST', [
            'public_token' => $publicToken
        ]);
    }

    function getAllOrders(int $limit = 50, string $cursor = '', array $passedParams = [])
    {
        $params = [];

        $tmpParams = [
            'limit' => $limit,
            'cursor' => $cursor
        ];

        $mergedParams = array_merge($tmpParams, $passedParams);

        foreach ($mergedParams as $key => $value) {
            $params[] = $key.'='.$value;
        }

        return $this->makeCall('/orders', 'GET', null, $params);
    }

    function getAllProducts(int $limit = 50, string $cursor = '', array $passedParams = [])
    {
        $params = [];

        $tmpParams = [
            'limit' => $limit,
            'cursor' => $cursor,
            'status' => 'active'
        ];

        $mergedParams = array_merge($tmpParams, $passedParams);

        foreach ($mergedParams as $key => $value) {
            $params[] = $key.'='.$value;
        }

        return $this->makeCall('/products', 'GET', null, $params);
    }

    function getProduct($id)
    {
        return $this->makeCall('/products/'.$id, 'GET', null);
    }

    function addProduct($product, array $passedParams = []){
        $data = [];
        $data['product'] = $product;

        return $this->makeCall('/products', 'POST', $data);
    }

    function submitTracking($orderId, $tracking, $carrier)
    {
        $data = [];
        $data['fulfillment'] = array(
            'tracking_number'=>$tracking,
            'carrier'=>$carrier
        );

        return $this->makeCall('/orders/'.$orderId.'/fulfillments', 'POST', $data);
    }

    function log($message, $level = 'info')
    {
        if ($this->logger) {
            try {
                $this->logger->$level($message);
            } catch (Exception $e) {
                $this->logger->error($e);
            }
        }
    }

    function logError($message)
    {
        $this->log($message, 'error');
    }

    function logDebug($message)
    {
        $this->log($message, 'debug');
    }

    /**
     * @param $endpoint
     * @param string $method
     * @param array|null $data
     * @return mixed
     */
    function makeCall($endpoint, $method, $data = null, $params = [])
    {
        $headers = [];
        $requestData = [];

        if(!empty($this->rutterClientAccessToken)){
            if(!isset($params['delete'])){
                $params[]= 'access_token='.$this->rutterClientAccessToken;
            }

            $headers['Accept'] = 'application/json';
            $headers['Authorization'] = 'Basic '. base64_encode($this->rutterClientId.':'.$this->rutterSecretKey);
        }

        $endpoint = $this->rutterApiUrl.$endpoint.'?' . implode('&', $params);

        if(empty($this->rutterClientAccessToken)) {
            $requestData = array(
                'client_id' => $this->rutterClientId,
                'secret' => $this->rutterSecretKey
            );
        }

        if(!empty($data)){
            $requestData = array_merge($requestData, $data);
        }

        try {
            $client = new Client();
            $response = $client->request($method, $endpoint, [
                'json' => $requestData,
                'headers' => $headers
            ]);

            if($response->getStatusCode() == 200){
                $response = array(
                    'success' => true,
                    'code' => $response->getStatusCode(),
                    'data' => json_decode($response->getBody()->getContents()),
                    'message' => 'Request OK'
                );
            }
            else{
                $response = array(
                    'success' => false,
                    'code' => $response->getStatusCode(),
                    'data' => json_decode($response->getBody()->getContents()),
                    'message' => 'Request OK'
                );
            }
        }
        catch (Exception $e) {

            $this->logError($e->getMessage());

            $response = array(
                'success' => false,
                'code' => $e->getCode(),
                'data' => [],
                'message' => $e->getMessage()
            );
        }

        return $response;
    }

}
