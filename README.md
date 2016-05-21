## Electrum Server discovery

Electrum wallets use a list of predefined servers, from which they can request other peers. 
Besides this, the only way to learn about electrum servers is to join the freenode #electrum channel. 

This library uses `phergie/phergie-irc-client` to obtain a list of electrum servers from the channel.
Presently, the connection is terminated once the full list is obtained - the client will not track 
new nicks, kicks, parts, etc.

### Usage

This is probably the simplest way to use the library: 

```php
use BitWasp\ElectrumServer\Discovery;

$loop = \React\EventLoop\Factory::create();
Discovery::lookup($loop)->then(function (array $list) {
    print_r($list);
});

$loop->run();
```

