<?php

namespace App\Http\Controllers;

use Illuminate\Foundation\Bus\DispatchesJobs;
use Illuminate\Routing\Controller as BaseController;
use Illuminate\Foundation\Validation\ValidatesRequests;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;

class Controller extends BaseController
{
    use AuthorizesRequests, DispatchesJobs, ValidatesRequests;

    const HTTP_OK = 200;
    const HTTP_CREATED = 201;
    const HTTP_ACCEPTED = 202;
    const HTTP_BAD_REQUEST = 400;
    const HTTP_NOT_FOUND = 404;
    const HTTP_UNPROCESSABLE_ENTITY = 422;
    const HTTP_SERVER_ERROR = 500;

    /**
     * @param string|array $data
     * @return \Illuminate\Http\Response
     */
    protected function responseOk($data = 'Ok'){
        return response($data, self::HTTP_OK);
    }

    /**
     * @param string|array $data
     * @return \Illuminate\Http\Response
     */
    protected function responseCreated($data = 'Created'){
        return response($data, self::HTTP_CREATED);
    }

    /**
     * @param string|array $data
     * @return \Illuminate\Http\Response
     */
    protected function responseAccepted($data = 'Accepted'){
        return response($data, self::HTTP_ACCEPTED);
    }

    /**
     * @param string|array $data
     * @return \Illuminate\Http\Response
     */
    protected function responseBadRequest($data = 'Bad Request'){
        return response($data, self::HTTP_BAD_REQUEST);
    }

    /**
     * @param string|array $data
     * @return \Illuminate\Http\Response
     */
    protected function responseNotFound($data = 'Not Found'){
        return response($data, self::HTTP_NOT_FOUND);
    }

    /**
     * @param string|array $data
     * @return \Illuminate\Http\Response
     */
    protected function responseUnprocessableEntity($data = 'Unprocessable Entity'){
        return response($data, self::HTTP_UNPROCESSABLE_ENTITY);
    }

    /**
     * @param string|array $data
     * @return \Illuminate\Http\Response
     */
    protected function responseServerError($data = 'Server Error'){
        return response($data, self::HTTP_SERVER_ERROR);
    }

    protected function getStatusCodeSuccess()
    {
        return 200;
    }

    protected function getStatusCodeError()
    {
        return 400;
    }

    protected function getStatusCodeUnauthorized()
    {
        return 401;
    }

    protected function getStatusCodeNotFound()
    {
        return 404;
    }

    protected function getStatusCodeUnprocessableEntity()
    {
        return 422;
    }
}
