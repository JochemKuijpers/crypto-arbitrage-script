# crypto-arbitrage-script

A script that automatically detects positive cycles on crypto exchanges and trades. YMMV
I do not take responsibility for any losses (or gains) you make using this script.

Script outputs colored text, not all software supports this. Again; YMMV.

## AS SOON AS YOU START THIS SCRIPT, TRADING MAY OCCUR WITHOUT CONFIRMATION. BE AWARE.

## Setup

Add your private and public key in the `api_config.temp.php` file and rename it `api_config.php`.

## Start

Start the script from the root directory command line using PHP5+ and php-curl enabled:

Trading on WEX:
```php -f trader.php -- wex```

Trading on TradeSatoshi:
```php -f trader.php -- ts```

## Known issues

TradeSatoshi is broken and unreliable. Actual trading might not through the API.

## Other APIs are welcome

If you've succesfully implemented an API of an exchange, you can create a pull request.

I'm not picky about code quality, but your code should at least catch all exceptions that might occur and handle unsuccesfull API responses correctly. You should not need to modify trader.php except from startup code to add your own API. Read the comments on the `api/api_` files.
