<?php

function state(string $query) {
    $a = explode(':', $query, 2);
    if (isset($GLOBALS['X'][1][$query])) {
        return $GLOBALS['X'][1][$query];
    }
    if (is_file($f = X . DS . $a[0] . DS . 'index.php')) {
        $out = [];
        if (is_file($f = dirname($f) . DS . 'lot' . DS . 'state' . DS . ($a[1] ?? 'config') . '.php')) {
            extract($GLOBALS, EXTR_SKIP);
            $out = require $f;
        }
        $out = Hook::fire('x.state.' . strtr($query, '.', '/'), [$out]);
        return ($GLOBALS['X'][1][$query] = $out);
    }
    return null;
}

$uses = [];
foreach (glob(X . DS . '*' . DS . 'index.php', GLOB_NOSORT) as $v) {
    $n = basename($r = dirname($v));
    $uses[$v] = content($r . DS . $n) ?? $n;
}

// Sort by name
natsort($uses);
$GLOBALS['X'][0] = $uses = array_keys($uses);

// Load class(es)…
foreach ($uses as $v) {
    d(($f = dirname($v) . DS . 'engine' . DS) . 'kernel', function($v, $n) use($f) {
        $f .= 'plug' . DS . $n . '.php';
        if (is_file($f)) {
            extract($GLOBALS, EXTR_SKIP);
            require $f;
        }
    });
}

// Load extension(s)…
foreach ($uses as $v) {
    call_user_func(function() use($v) {
        extract($GLOBALS, EXTR_SKIP);
        if (is_file($k = dirname($v) . DS . 'task.php')) {
            require $k;
        }
        require $v;
    });
}