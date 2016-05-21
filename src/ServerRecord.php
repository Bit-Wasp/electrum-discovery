<?php

namespace BitWasp\ElectrumServer;

class ServerRecord
{
    /**
     * @var string
     */
    private $name;

    /**
     * @var string
     */
    private $host;

    /**
     * @var bool
     */
    private $tcp = false;

    /**
     * @var bool
     */
    private $ssl = false;

    /**
     * @var int
     */
    private $tcpPort = 50001;

    /**
     * @var int
     */
    private $sslPort = 50002;

    /**
     * @var int
     */
    private $pruning;

    /**
     * @var string
     */
    private $version;

    public function __construct($version, $name, $host, $pruning = 0, $tcp = false, $ssl = false, $tcpPort = 50001, $sslPort = 50002)
    {
        $this->version = $version;
        $this->name = $name;
        $this->host = $host;
        $this->tcp = $tcp;
        $this->ssl = $ssl;
        $this->pruning = $pruning;
        $this->tcpPort = $tcpPort;
        $this->sslPort = $sslPort;
    }

    /**
     * @return string
     */
    public function getName()
    {
        return $this->name;
    }

    /**
     * @return string
     */
    public function getHost()
    {
        return $this->host;
    }

    /**
     * @return string
     */
    public function getVersion()
    {
        return $this->version;
    }

    /**
     * @return int
     */
    public function getPruning()
    {
        return $this->pruning;
    }

    /**
     * @return boolean
     */
    public function offersTcp()
    {
        return $this->tcp;
    }

    /**
     * @return int
     */
    public function getTcpPort()
    {
        if (!$this->tcp) {
            throw new \RuntimeException('Server does not support TCP');
        }

        return $this->tcpPort;
    }

    /**
     * @return int
     */
    public function getSslPort()
    {
        if (!$this->ssl) {
            throw new \RuntimeException('Server does not support TCP');
        }

        return $this->sslPort;
    }

    /**
     * @return boolean
     */
    public function offersSsl()
    {
        return $this->ssl;
    }
}
