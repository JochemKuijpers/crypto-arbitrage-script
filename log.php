<?php

include 'colors.php';

function sprintfcoin($coin, $amount) {
    return sprintf("% 17.8F % 4s", $amount, strtoupper($coin));
}

function logInfo($str) {
    echo DGRAY . date('r') . WHITE . "\t[I] $str\n";
}

function logSuccess($str) {
    echo DGRAY . date('r') . LGREEN . "\t[S] $str\n";
}

function logError($str, $resp = false) {
    if ($resp) {
        echo DGRAY . date('r') . LRED . "\t[E] $str: $resp\n";
    } else {
        echo DGRAY . date('r') . LRED . "\t[E] $str\n";
    }
}

function logDebug($str) {
    echo DGRAY . date('r') . "\t[D] $str\n";
}


function logBalances($balances, $prices) {
    global $BASE, $FEE;
    echo "\n";
    echo DGRAY . date('r') . CYAN . "\t[B] Balance summary:\n";

    $baseSum = 0;
    foreach ($balances as $coin=>$amount) {
        if ($amount <= 0) { continue; }
        if ($coin == $BASE) {
            $baseSum += $amount;
            echo DGRAY . date('r') . CYAN . "\t[B] " . LCYAN . sprintfcoin($BASE, $amount) . "\n";
        } else {
            $baseEquiv = $balances[$coin] * $prices["$coin==>$BASE"][0] * (1-$FEE);
            $baseSum += $baseEquiv;
            echo DGRAY . date('r') . CYAN . "\t[B] " . LCYAN . sprintfcoin($coin, $amount) . CYAN . " = " . sprintfcoin($BASE, $baseEquiv) . "\n";
        }
    }
    echo DGRAY . date('r') . CYAN . "\t[B]        Total equivalent: " . LCYAN . sprintfcoin($BASE, $baseSum) . "\n";
}

function logTrade($coin1, $amount1, $coin2, $amount2) {
    echo DGRAY . date('r') . BROWN . "\t[T] Trading " . WHITE . sprintfcoin($coin1, $amount1) . BROWN . " => " . WHITE . sprintfcoin($coin2, $amount2) . BROWN . " pending..\n";
}

function logTradeSuccess() {
    echo DGRAY . date('r') . BROWN . "\t[T] Trade " . LGREEN . "OK\n";
}

function logPath($path, $multiplier) {
    echo DGRAY . date('r') . LBLUE . "\t[P] ";
    foreach($path as $coin) {
        echo sprintf("% 4s => ", $coin);
    }
    echo sprintf("% 4s", $path[0]) . " = " . ($multiplier > 1 ? LGREEN : DGRAY) . sprintf("% 8.4f", $multiplier*100) . "%" . str_repeat(" ", 10) . ($multiplier > 1 ? "\n" : "\r");
}