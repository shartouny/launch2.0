<?php

namespace App\Models\Accounts\AccountPaymentMethod;

class ChargeDetails
{
    const STATUS_FAIL = 0;
    const STATUS_SUCCESS = 1;

    protected $platformTransactionId;
    protected $platform;
    protected $status;
    protected $platformStatus;
    protected $platformMessage;
    protected $platformErrors;
    protected $amount;
    protected $platformData;

    public function getPlatformTransactionId()
    {
        return $this->platformTransactionId;
    }

    public function setPlatformTransactionId($id)
    {
        $this->platformTransactionId = $id;

        return $this;
    }

    public function getPlatform()
    {
        return $this->platform;
    }

    public function setPlatform($platform)
    {
        $this->platform = $platform;

        return $this;
    }

    public function getStatus()
    {
        return $this->status;
    }

    public function setStatus($status)
    {
        $this->status = $status;

        return $this;
    }

    public function getPlatformStatus()
    {
        return $this->platformStatus;
    }

    public function setPlatformStatus($status)
    {
        $this->platformStatus = $status;

        return $this;
    }

    public function getPlatformMessage()
    {
        return $this->platformMessage;
    }

    public function setPlatformMessage($message)
    {
        $this->platformMessage = $message;

        return $this;
    }

    public function getPlatformErrors() {
      return $this->platformErrors;
    }

    public function setPlatformErrors($errors) {
      $this->platformErrors = $errors;
    }

    public function getPlatformData()
    {
        return $this->platformData;
    }

    public function setPlatformData($data)
    {
        $this->platformData = $data;

        return $this;
    }
}
