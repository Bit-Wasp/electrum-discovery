<?php

namespace BitWasp\ElectrumServer;

use Evenement\EventEmitter;
use Phergie\Irc\Client\React\Client;
use Phergie\Irc\Client\React\WriteStream;
use Phergie\Irc\Connection;
use Phergie\Irc\ConnectionInterface;
use Psr\Log\LoggerInterface;
use React\EventLoop\LoopInterface;
use Psr\Log\NullLogger;
use React\Promise\Deferred;

class Discovery extends EventEmitter
{
    /**
     * @var WriteStream
     */
    private $write;

    /**
     * @var bool
     */
    private $connected = false;

    /**
     * @var Deferred
     */
    private $deferred;

    /**
     * @var Client
     */
    private $client;

    /**
     * @var array
     */
    private $serverDeferred = [];

    /**
     * @var array
     */
    private $users = [];

    /**
     * Discovery constructor.
     * @param Client $client
     */
    public function __construct(Client $client)
    {
        $this->client = $client;
        $this->client->on('irc.received', [$this, 'onIrcReceived']);
        $this->deferred = new Deferred();
        $this->on('connection', [$this, 'onConnect']);
        $this->on('list', [$this, 'onUserList']);
        $this->on('chanusers', [$this, 'onChanUsers']);
        $this->on('whoreply', [$this, 'onWho']);
    }

    /**
     * @param LoopInterface $loop
     * @param LoggerInterface|null $logger
     * @return \React\Promise\PromiseInterface|static
     */
    public static function lookup(LoopInterface $loop, LoggerInterface $logger = null)
    {
        if (null === $logger) {
            $logger = new NullLogger();
        }

        $client = new Client();
        $client->setLogger($logger);
        $client->setLoop($loop);

        $random = bin2hex(openssl_random_pseudo_bytes(3));
        $nick = 'discovery_' . $random;

        $connection = (new Connection())
            ->setServerHostname('irc.freenode.net')
            ->setServerPort(6667)
            ->setNickname($nick)
            ->setUsername($nick)
            ->setHostname('none')
            ->setServername('freenode')
            ->setRealname('none')
        ;

        $discovery = new Discovery($client);
        $promise = $discovery->run()->then(function (array $list) {
            return $list;
        })->then(function (array $list) use ($discovery) {
            $discovery->stop();
            return $list;
        });

        $client->run($connection, false);
        return $promise;
    }

    /**
     * @param array $message
     * @param WriteStream $write
     * @param ConnectionInterface $connection
     * @param LoggerInterface $logger
     */
    public function onIrcReceived(array $message, WriteStream $write, ConnectionInterface $connection, LoggerInterface $logger)
    {
        $this->write = $write;
        $command = $message['command'];
        $code = isset($message['code']) ? $message['code'] : null;
        
        if ($command == 376 && $code === 'RPL_ENDOFMOTD') {
            $this->emit('connection', [$write, $connection]);
            return;
        }

        if ($command == 366 && $code === 'RPL_ENDOFNAMES') {
            $this->emit('list', [$write, $connection]);
        }

        if ($command == 352 && $code === 'RPL_WHOREPLY') {
            $this->emit('whoreply', [$message, $write, $connection]);
            return;
        }

        if ($command == 353) {
            $this->emit('chanusers', [$message, $write, $connection]);
            return;
        }

        if ($command == 'JOIN') {
            $this->emit('join', [$write, $connection]);
            return;
        }

        if ($command == 'PING') {
            $write->ircPong('testclient');
            return;
        }
    }

    /**
     * @param WriteStream $stream
     */
    public function onConnect(WriteStream $stream)
    {
        if (false === $this->connected) {
            $this->connected = true;
            $stream->ircJoin('#electrum');
        }
    }

    /**
     * @param array $message
     * @param WriteStream $write
     * @param ConnectionInterface $connection
     */
    public function onChanUsers(array $message, WriteStream $write, ConnectionInterface $connection)
    {
        $userStr = $message['params'][3];
        $users = explode(" ", $userStr);
        foreach ($users as $user) {
            if (substr($user, 0, 2) === 'E_') {
                $this->users[] = $user;
            }
        }
    }

    /**
     * @param array $message
     * @param WriteStream $write
     * @param ConnectionInterface $connection
     */
    public function onWho(array $message, WriteStream $write, ConnectionInterface $connection)
    {
        $serverHost = $message['params'][3];
        $serverName = $message['params'][5];
        $info = explode(" ", $message['params'][7]);
        $pruning = substr($info[3], 1);
        $ssl = $tcp = false;
        $tcpPort = 50001;
        $sslPort = 50002;

        // Parse supported services
        foreach (array_slice($info, 4) as $status) {
            $i = substr($status, 0, 1);
            if ($i === 's') {
                $ssl = true;
                if (strlen($status) > 1) {
                    $sslPort = intval(substr($status, 1));
                }
            }

            if ($i === 't') {
                $tcp = true;
                if (strlen($status) > 1) {
                    $tcpPort = intval(substr($status, 1));
                }
            }
        }

        if (isset($this->serverDeferred[$serverName])) {
            $this->serverDeferred[$serverName]->resolve(new ServerRecord($info[2], $serverName, $serverHost, $pruning, $tcp, $ssl, $tcpPort, $sslPort));
        }
    }

    /**
     * @param WriteStream $write
     */
    public function onUserList(WriteStream $write)
    {
        $promise = [];
        foreach ($this->users as $user) {
            $deferred = new Deferred();
            $this->serverDeferred[$user] = $deferred;
            $promise[] = $deferred->promise();
            $write->ircWho($user);
        }

        \React\Promise\all($promise)->then(function ($serverList) use ($write) {
            $this->connected = false;
            $write->close();
            $this->deferred->resolve($serverList);
        });
    }

    /**
     * @return \React\Promise\Promise|\React\Promise\PromiseInterface
     */
    public function run()
    {
        return $this->deferred->promise();
    }

    /**
     *
     */
    public function stop()
    {
        return $this->write->close();
    }
}
