<?php

include_once "api_config.php";

/**
 * $market pair: "BTC_LTC"
 * Returns an array ["buy" => [[<rate>, <amount>], ...], "sell" => [[<rate>, <amount>], ...]]
 */
function GetOrderBook($market) {
    $market = strtolower($market);
    try {
        $resp = wexnzPublicApi("depth/$market/");
    } catch(Exception $e) {
        return $e->getMessage() ?? false;
    }

    $ret = [];
    foreach($resp[$market]['bids'] as $buy) {
        $ret["buy"][] = $buy;
    }
    foreach($resp[$market]['asks'] as $sell) {
        $ret["sell"][] = $sell;
    }

    return $ret;
}

/**
 * Returns an array ["BTC"=>0.12345678, ...], etc.
 */
function GetBalances() {
    global $COINS;
    try {
        $resp = wexnzTradeApi("getInfo");
    } catch(Exception $e) {
        return $e->getMessage() ?? false;
    }

    $funds = [];
    foreach($resp['return']['funds'] as $coin => $amount) {
        $coin = strtoupper($coin);
        if (in_array($coin, $COINS)) {
            $funds[$coin] = doubleval($amount);
        }
    }

    return $funds;
}

/**
 * Returns an array of order IDs: [01234, 02345, ...]
 */
function GetOrders() {
    try {
        $resp = wexnzTradeApi("ActiveOrders");
    } catch(Exception $e) {
        if ($e->getMessage() == "Request not successful: no orders") { return []; }
        return $e->getMessage() ?? false;
    }

    $orders = [];
    foreach($resp['return'] as $id => $info) {
        $orders[] = $id;
    }
    return $orders;
}

/**
 * Returns true on success, false or a message on failure
 */
function CancelOrder($id) {
    try {
        $resp = wexnzTradeApi("CancelOrder", ["order_id" => $id]);
    } catch(Exception $e) {
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
        $resp = wexnzTradeApi("Trade", ["pair" => strtolower($market), "type" => "sell", "rate" => $rate, "amount" => $amount]);
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
        $resp = wexnzTradeApi("Trade", ["pair" => strtolower($market), "type" => "buy", "rate" => $rate, "amount" => $amount]);
    } catch (Exception $e) {
        return $e->getMessage() ?? false;
    }
    return true;
}

// ---

function wexnzTradeApi($method, array $req = array()) {
    global $CONFIG;
    $key = $CONFIG['wexnz']['public'];
    $secret = $CONFIG['wexnz']['private'];

    $req['method'] = $method;

    static $mt = null;
    if (is_null($mt)) {
        $mt = explode(' ', microtime());
    }
    $req['nonce'] = $mt[1]++;

    // generate the POST data string
    $post_data = http_build_query($req, '', '&');
    $sign = hash_hmac('sha512', $post_data, $secret);

    // generate the extra headers
    $headers = array(
        'Sign: '.$sign,
        'Key: '.$key,
    );

    static $ch = null;
    if (is_null($ch)) {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/4.0 (compatible; WexNZ PHP client; '.php_uname('s').'; PHP/'.phpversion().')');
    }
    curl_setopt($ch, CURLOPT_URL, 'https://wex.nz/tapi');
    curl_setopt($ch, CURLOPT_POSTFIELDS, $post_data);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, FALSE);

    // run the query
    $res = curl_exec($ch);
    if ($res === false) throw new Exception('Request error: ' . curl_error($ch));
    $dec = json_decode($res, true);
    if (!$dec) throw new Exception('Response error: Not JSON formatted.');
    if (!$dec["success"]) throw new Exception('Request not successful: ' . $dec["error"]);
    return $dec;
}

function wexnzPublicApi($path) {
    static $ch = null;
    if (is_null($ch)) {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/4.0 (compatible; WexNZ PHP client; '.php_uname('s').'; PHP/'.phpversion().')');
    }
    curl_setopt($ch, CURLOPT_URL, 'https://wex.nz/api/3/' . $path);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, FALSE);

    // run the query
    $res = curl_exec($ch);
    if ($res === false) throw new Exception('Request error: ' . curl_error($ch));
    $dec = json_decode($res, true);
    if (!$dec) throw new Exception('Response error: Not JSON formatted.');
    return $dec;
}
