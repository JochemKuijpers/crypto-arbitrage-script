<?php

include_once "api_config.php";

/**
 * $market pair: "BTC_LTC"
 * Returns an array ["buy" => [[<rate>, <amount>], ...], "sell" => [[<rate>, <amount>], ...]]
 */
function GetOrderBook($market) {
    try {
        $res = tsApi("GetOrderBook", ["Market" => $market]);
    } catch (Exception $e) {
        return $e->getMessage() ?? false;
    }

    $ret = [];
    foreach($res['result']['buy'] as $buy) {
        $ret["buy"][] = [$buy['rate'], $buy['quantity']];
    }
    foreach($res['result']['sell'] as $sell) {
        $ret["sell"][] = [$sell['rate'], $sell['quantity']];
    }

    return $ret;
}

/**
 * Returns an array ["BTC"=>0.12345678, ...], etc.
 */
function GetBalances() {
    try {
        $res = tsApi("GetBalances");
    } catch (Exception $e) {
        return $e->getMessage() ?? false;
    }

    $balances = [];
    foreach($res['result'] as $currency) {
        if ($currency["total"] > 0) {
            $balances[$currency["currency"]] = $currency["total"];
        }
    }
    return $balances;
}

/**
 * Returns an array of order IDs: [01234, 02345, ...]
 */
function GetOrders() {
    try {
        $res = tsApi("GetOrders", ["Market" => "all", "Count" => 20]);
    } catch (Exception $e) {
        return $e->getMessage() ?? false;
    }

    $orders = [];
    foreach($res['result'] as $order) {
        $orders[] = $order["Id"];
    }
    return $orders;
}

/**
 * Returns true on success, false or a message on failure
 */
function CancelOrder($id) {
    try {
        tsApi("CancelOrder", ["Type" => "Single", "OrderId" => $id]);
    } catch (Exception $e) {
        return $e->getMessage() ?? false;
    }
    return true;
}

/**
 * $market pair: "BTC_LTC"
 * $amount amount to sell
 * $rate   the rate (price)
 *
 * Returns true on success, false or a message on failure
 */
function Sell($market, $amount, $rate) {
    try {
        tsApi("SubmitOrder", ["Market" => $market, "Type" => "Sell", "Amount" => $amount, "Price" => $rate]);
    } catch (Exception $e) {
        return $e->getMessage() ?? false;
    }
    return true;
}

/**
 * $market pair: "BTC_LTC"
 * $amount amount to buy
 * $rate   the rate (price)
 *
 * Returns true on success, false or a message on failure
 */
function Buy($market, $amount, $rate) {
    try {
        tsApi("SubmitOrder", ["Market" => $market, "Type" => "Buy", "Amount" => $amount, "Price" => $rate]);
    } catch (Exception $e) {
        return $e->getMessage() ?? false;
    }
    return true;
}

$COOKIES = [];
function tsApi($ENDPOINT, array $REQ = array())
{
    global $COOKIES, $CONFIG;
    $API_PUBLIC_KEY = $CONFIG['tradesatoshi']['public'];
    $API_SECRET_KEY = $CONFIG['tradesatoshi']['private'];

    $PUBLIC_API = array('GetCurrencies', 'GetTicker', 'GetMarketHistory', 'GetMarketSummary', 'GetMarketSummaries', 'GetOrderBook');
    $PRIVATE_API = array('GetBalance', 'GetBalances', 'GetOrder', 'GetOrders', 'SubmitOrder', 'CancelOrder', 'GetTradeHistory', 'GenerateAddress', 'SubmitWithdraw', 'GetDeposits', 'GetWithdrawals');

    // Init curl
    static $ch = null;
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/4.0 (compatible; TradeSatoshi API PHP client; ' . php_uname('s') . '; PHP/' . phpversion() . ')');

    // remove those 2 line to secure after test.
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);

    // PUBLIC or PRIVATE API
    $HEADERS = [];
    if (in_array($ENDPOINT, $PUBLIC_API)) {
        $URL = "https://tradesatoshi.com/api/public/" . strtolower($ENDPOINT);
        if ($REQ) $URL .= '?' . http_build_query($REQ, '', '&');
        curl_setopt($ch, CURLOPT_URL, $URL);
    } elseif (in_array($ENDPOINT, $PRIVATE_API)) {
        $URL = "https://tradesatoshi.com/api/private/" . strtolower($ENDPOINT);
        $mt = explode(' ', microtime());
        $NONCE = $mt[1] . substr($mt[0], 2, 6);
        $REQ = json_encode($REQ);
        $SIGNATURE = $API_PUBLIC_KEY . 'POST' . strtolower(urlencode($URL)) . $NONCE . base64_encode($REQ);
        $HMAC_SIGN = base64_encode(hash_hmac('sha512', $SIGNATURE, base64_decode($API_SECRET_KEY), true));
        $HEADER = 'Basic ' . $API_PUBLIC_KEY . ':' . $HMAC_SIGN . ':' . $NONCE;
        $HEADERS = array("Content-Type: application/json; charset=utf-8", "Authorization: $HEADER");
        curl_setopt($ch, CURLOPT_URL, $URL);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $REQ);
    }
    $cookie = "Cookie: " . http_build_query($COOKIES);
    curl_setopt($ch, CURLOPT_HTTPHEADER, array_merge($HEADERS, [$cookie]));
    curl_setopt($ch, CURLOPT_HEADER, 1);
    // run the query
    $res = false;
    $tries = 10;
    do {
        try {
            $res = curl_exec($ch);
        } finally {
            $tries -= 1;
        }
    } while ($res == "The service is unavailable." && $tries > 0);

    if ($res === false) {
        $res = "{\"success\": false, \"message\": \"Could not get reply: '" . curl_error($ch) . "'\"}";
    }

    preg_match_all('/^Set-Cookie:\s*([^;]*)/mi', $res, $matches);
    foreach($matches[1] as $item) {
        parse_str($item, $cookie);
        $COOKIES = array_merge($COOKIES, $cookie);
    }

    $res = substr($res, strpos($res, "\r\n\r\n"));
    if ($res === false) { throw new Exception("Request error: " . curl_error($ch)); }
    $dec = json_decode($res, true);
    if (!$dec) { throw new Exception("Response error: Not JSON format"); }
    if ($dec['message']) {
        throw new Exception("Response error: " . $dec['message']);
    }
    return $dec;
}
