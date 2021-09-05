#Rebalance TA Paper Trade Bot

By: u/callunquirka aka leafypineglass on github

License: CC BY

Uses TA to dial in the sell/buy time. It doesn't rebalance the entire portfolio at once, instead it only buys or sells assets that have gone outside the range allowed by $threshold in config.

##Dependencies

PHP 7

Either the Binance API from jaggedsoft or KrakenAPI

https://github.com/jaggedsoft/php-binance-api

https://github.com/krakenfx/kraken-api-client

##Install
Run on local dev platform with install=1 in config. Then upload to the private section of webserver to avoid webcrawling.
Set a cron job for every 5 minutes or so.
eg cron command:
/usr/local/php70/bin/php /home/<folder>/rebalance/rebalance_live.php >> /home/<folder>/rebalance/log/rebalance_live.log 2>&1

##Path taken by program
Check that the candlesticks/indicators are update, update if not.
Check balance, if some assets are too high or low run sellPotential() or buyPotential().
sellPotential() or buyPotential() checks if the TA conditions are acceptable for doing stuff, eg price relative to EMA.
Writes to json files accordingly.

##rebalanceConfig
Config for stuff like moduleName, how unbalanced the portfolio must be before it rebalances.
Look here for install, portfolio make up, exchange, etc.

##json Files
**rebalance-main.json** - Records the balance, average buy price per asset, starting quantity of each asset.
**QuickExt.json** - Look here for the tempTotalValue and holdValue (value of your assets if you only held).
**pairName-history.json** - Records each sell operation and the average buy price at the time of the sell.


##Includes
###kraken-data-rest.php and binance-data-rest.php
Restructures and rekeys data from either API into a format useable by the bot.

###movingAverages.php
For unusual moving averages if you prefer those.
zema() - Zero Lag Exponential Moving Average
frama() - Fractal Adaptive Moving Average


##indic/ folder
Location for all the calculated indicators of each pair.
