# mvnaz/imapconnector

This package allows you to connect to proxy via proxy.

Good qualities:
* Package is very flexible, you can replace any element with your own (ResponseContainer, Parser, Commander, implement your own proxy types)
* Already implemented Socks5 and Https proxies

Limitations
* Proxy authorization need to be implemented
* Parser, Commander - these objects I would not recommend to use from this package in real project.
   I have included it for example, to show how you can use the stream.



To include it for use in your project, please add following code to your composer.json:

```
    "repositories": [
        {
            "type": "vcs",
            "url": "https://github.com/mvnaz/ImapConnector.git"
        }
    ],
    "require": {
        "mvnaz/imapconnector": "dev-master"
    }
```

## Usage

```php
// This object contains all success and errors (You can use your own)
$responseContainer = \Mvnaz\ImapConnector\Containers\ResponseContainer::getInstance();

$connector = new \Mvnaz\ImapConnector\Connector($responseContainer);

// This object is for imap response parsing (You can use your own)
$parser = new \Mvnaz\ImapConnector\Parsers\Parser();

// Socks 5 proxy instance (You can also use HTTP proxy or your own implementation)
$socks5Proxy = new \Mvnaz\ImapConnector\Proxies\Socks5Proxy($responseContainer, "ip", 'port');

// Connecting to the proxy (if you skip this line script will connect to imap directly, without proxy)
$connector->connectToProxy($socks5Proxy);

// Here we get the stream which is via proxy (You can use this stream in your own order, i.e with your own commander)
$stream = $connector->connectToImap("imap_host", 'imap_port');

// Here we check if we was successfully connected to imap
if(is_resource($stream)) {

    // Here we create the commander and pass the stream
    $commander = new \Mvnaz\ImapConnector\Commander($stream, $parser, $responseContainer);

    // Lets login to imap
    if($commander->login("login", "password")){
        echo "Success!";
    }

}
```

