<?php

include 'log.php';

if ($argc < 2) {
    logError("Use a second parameter to indicate the exchange");
    die();
}

switch($argv[1]) {

    case "wex":
        include 'api/api_wexnz.php';
        $BASE = "BTC";
        $PREFERRED = ["BTC", "USD", "ETH", "LTC", "EUR"];
        $COINS = ["BTC", "LTC", "NMC", "NVC", "USD", "EUR", "PPC", "DSH", "ETH", "BCH", "ZEC", "RUR"];
        $PAIRS = [
            "BTC_USD", "BTC_RUR", "BTC_EUR",
            "LTC_BTC", "LTC_USD", "LTC_RUR", "LTC_EUR",
//                "NMC_BTC", "NMC_USD",
//                "NVC_BTC", "NVC_USD",
            "USD_RUR",
            "EUR_USD", "EUR_RUR",
//                "PPC_BTC", "PPC_USD",
//                "DSH_BTC", "DSH_RUR", "DSH_EUR", "DSH_LTC", "DSH_ETH", "DSH_ZEC",
            "ETH_BTC", "ETH_USD", "ETH_EUR", "ETH_LTC", "ETH_RUR", "ETH_ZEC",
//                "BCH_USD", "BCH_BTC", "BCH_RUR", "BCH_EUR", "BCH_LTC", "BCH_ETH", "BCH_DSH", "BCH_ZEC",
//                "ZEC_BTC", "ZEC_USD", "ZEC_LTC",
        ];
        $FEE = 0.002;
        break;

    case "ts":
        include "api/api_tradesatoshi.php";
        $BASE = "BTC";
        $PREFERRED = ["BTC", "LTC"];
        $COINS = ["BTC", "LTC", "DOGE", "GRLC"];
        $PAIRS = ["GRLC_BTC", "GRLC_DOGE", "GRLC_LTC", "LTC_BTC", "DOGE_BTC", "DOGE_LTC"];
        $FEE = 0.002;
        break;
}

//// ----

function cancelAllOrders() {
    $orders = GetOrders();
    if (!is_array($orders)) {
        logError("cancelAllOrders.GetOrders failed", $orders);
        return false;
    }

    foreach ($orders as $id) {
        logDebug("Cancelling order $id");
        $success = CancelOrder($id);
        if ($success !== true) {
            logError("cancelAllOrders.CancelOrder failed", $success);
            return false;
        }
    }
    return true;
}

function updateBalances() {
    global $COINS;
    $balances = GetBalances();
    if (!is_array($balances)) {
        logError("updateBalances failed", $balances);
        return [];
    }

    foreach($COINS as $coin) {
        if (!isset($balances[$coin])) {
            $balances[$coin] = 0;
        }
    }

    return $balances;
}

function updatePrices($pairs, $balances) {
    $prices = [];
    foreach ($pairs as $pair) {
        // the asset is the 'thing' being bought or sold, currency is the 'price'
        list($asset, $currency) = explode("_", $pair, 2);

        $orderbook = GetOrderBook($pair);
        if (!is_array($orderbook)) {
            logError("updatePrices.GetOrderBook('$pair') failed", $orderbook);
            return [];
        }

        usort($orderbook["buy"], function($a, $b) { return $a[0] - $b[0]; });
        usort($orderbook["sell"], function($a, $b) { return $b[0] - $a[0]; });

        $amountAsset = $balances[$asset];
        foreach($orderbook["buy"] as $buy) {
            // 1 * asset = price * currency
            // accepting a buy order is trading 1 asset for price * currency
            if ($amountAsset >= 0) {
                $prices["$asset==>$currency"] = [$buy[0], $pair, "Buy"];
                $amountAsset -= $buy[1];
            }
        }

        $amountCurrency = $balances[$currency];
        foreach($orderbook["sell"] as $sell) {
            // 1 * asset = price * currency
            // accepting a sell order is trading price * currency for 1 asset, so you get 1/price asset for 1 currency
            if ($amountCurrency >= 0) {
                $prices["$currency==>$asset"] = [1 / $sell[0], $pair, "Sell"];
                $amountCurrency -= 1/$sell[0] * $sell[1];
            }
        }
    }
    return $prices;
}

function tradePath($optimalPath, $balances, $prices) {
    global $FEE;
    $fromCoin = "";
    $firstCoin = "";

    logInfo("Starting positive cycle");

    foreach($optimalPath as $toCoin) {
        if ($fromCoin == "") { $fromCoin = $toCoin; $firstCoin = $toCoin; continue; }

        logTrade($fromCoin, $balances[$fromCoin], $toCoin, $prices["$fromCoin==>$toCoin"][0] * $balances[$fromCoin] * (1 - $FEE));

        if ($prices["$fromCoin==>$toCoin"][2] == "Buy") {
            $response = Sell($prices["$fromCoin==>$toCoin"][1], $balances[$fromCoin], $prices["$fromCoin==>$toCoin"][0]);
        } else {
            $response = Buy($prices["$fromCoin==>$toCoin"][1], $balances[$fromCoin] * $prices["$fromCoin==>$toCoin"][0], 1 / $prices["$fromCoin==>$toCoin"][0]);
        }
        if ($response !== true) {
            logError("tradePath.Buy/Sell ($fromCoin => $toCoin) failed", $response);
            return false;
        }

        logTradeSuccess();

//        $balances[$toCoin] += $prices["$fromCoin==>$toCoin"][0] * $balances[$fromCoin] * (1 - $FEE);
//        $balances[$fromCoin] = 0;
        $balances = updateBalances();

        $fromCoin = $toCoin;
    }

    $toCoin = $firstCoin;
    logTrade($fromCoin, $balances[$fromCoin], $toCoin, $prices["$fromCoin==>$toCoin"][0] * $balances[$fromCoin] * (1 - $FEE));

    if ($prices["$fromCoin==>$toCoin"][2] == "Buy") {
        $response = Sell($prices["$fromCoin==>$toCoin"][1], $balances[$fromCoin], $prices["$fromCoin==>$toCoin"][0]);
    } else {
        $response = Buy($prices["$fromCoin==>$toCoin"][1], $balances[$fromCoin] * $prices["$fromCoin==>$toCoin"][0], 1 / $prices["$fromCoin==>$toCoin"][0]);
    }

    if ($response !== true) {
        logError("tradePath.Buy/Sell ($fromCoin => $toCoin) failed", $response);
        return false;
    }

    logTradeSuccess();

    logInfo("Finished positive cycle");
}

function iterate($logBalance) {
    global $BASE, $PREFERRED, $COINS, $PAIRS, $FEE;

    // cancel orders
    cancelAllOrders();
    // determine currency with largest amount
    $balances = updateBalances();
    if (count($balances) == 0) { return false; }
    $prices = updatePrices($PAIRS, $balances);
    if (count($prices) == 0) { return false; }

    if ($logBalance) {
        logBalances($balances, $prices);
    }

    // find which coin we want to trade.. highest preferred coin in base equivalent value wins.
    $tradeCoin = $BASE;
    $tradeBtcVal = 0;
    foreach ($COINS as $coin) {
        if ($coin != $BASE && !isset($prices["$coin==>$BASE"])) { continue; }
        if ($coin == $BASE) {
            $baseVal = $balances[$coin];
        } else {
            $baseVal = $balances[$coin] * $prices["$coin==>$BASE"][0] * (1 - $FEE);
        }

        if ($baseVal > $tradeBtcVal) {
            $tradeCoin = $coin;
            $tradeBtcVal = $baseVal;
        }
    }

    // now find the optimal path
    $optimalPath = [];
    $optimalPathMultiplier = 0;
    foreach ($COINS as $inter1) {
        if ($inter1 == $tradeCoin) { continue; }
        if (!isset($prices["$tradeCoin==>$inter1"])) { continue; }
        foreach ($COINS as $inter2) {
            if ($inter1 == $inter2 || $inter2 == $tradeCoin) { continue; }
            if (!isset($prices["$inter1==>$inter2"])) { continue; }
            if (isset($prices["$inter2==>$tradeCoin"])) {
                $mult = $prices["$tradeCoin==>$inter1"][0] * $prices["$inter1==>$inter2"][0] * $prices["$inter2==>$tradeCoin"][0] * pow(1 - $FEE, 3);
                if ($mult > $optimalPathMultiplier) {
                    $optimalPath = [$tradeCoin, $inter1, $inter2];
                    $optimalPathMultiplier = $mult;
                }
            }

            foreach ($COINS as $inter3) {
                if ($inter1 == $inter3 || $inter2 == $inter3 || $tradeCoin == $inter3) { continue; }
                if (!isset($prices["$inter2==>$inter3"])) { continue; }
                if (!isset($prices["$inter3==>$tradeCoin"])) { continue; }

                $mult = $prices["$tradeCoin==>$inter1"][0] * $prices["$inter1==>$inter2"][0] * $prices["$inter2==>$inter3"][0] * $prices["$inter3==>$tradeCoin"][0] * pow(1 - $FEE, 4);
                if ($mult > $optimalPathMultiplier) {
                    $optimalPath = [$tradeCoin, $inter1, $inter2, $inter3];
                    $optimalPathMultiplier = $mult;
                }
            }
        }
    }

    logPath($optimalPath, $optimalPathMultiplier);

    if ($optimalPathMultiplier > 1) {
        if (!tradePath($optimalPath, $balances, $prices)) {
            return false;
        };
    }

    return true;
}

//// ----




$min = "";
$logBalance = true;
$backoff = 1;
$minWait = 1;
$maxWait = 60;
while(true) {
    if ($min != date("i")) {
        $logBalance = true;
        $min = date("i");
    }

    if (!iterate($logBalance)) {
        $backoff = min($maxWait, $backoff*2);
    } else {
        $backoff = max($minWait, $backoff/2);
    }

    $logBalance = false;
    sleep($backoff);
}