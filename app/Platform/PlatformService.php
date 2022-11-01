<?php

namespace App\Platform;

use App\Logger\ConnectorLoggerFactory;

abstract class PlatformService
{
    protected $name;
    protected $accountId;
    protected $globalConnectorId;

    public function __construct($name)
    {
        $this->name = $name;
    }

    public function setAccountId($accountId)
    {
        $this->accountId = $accountId;
        return $this;
    }

    public function getAccountId()
    {
        return $this->accountId;
    }

    public function getName()
    {
        return $this->name;
    }

    public function getLogger($logChannel)
    {
        return (new ConnectorLoggerFactory($logChannel, $this->accountId, $this->name))->getLogger();
    }

    public function getConnectorManager($platformStoreId, $logChannel = null)
    {
        $logger = $logChannel ? $this->getLogger($logChannel) : null;

        $app = app();

        $className = "SunriseIntegration\\{$this->name}\\{$this->name}Manager";

        $connectorManager = $app->makeWith(
            $className,
            [
                'name' => $this->name,
                'accountId' => $this->accountId,
                'platformStoreId' => $platformStoreId,
                'logger' => $logger
            ]
        );

        return $connectorManager;
    }
}
