<?php

function __to_query__($array, $key) {
    $out = [];
    $s = $key ? '%5D' : "";
    foreach ($array as $k => $v) {
        $k = urlencode($k);
        if (is_array($v)) {
            $out = array_merge($out, __to_query__($v, $key . $k . $s . '%5B'));
        } else {
            $out[$key . $k . $s] = $v;
        }
    }
    return $out;
}

function __to_yaml__($in, $d = '  ', $safe = false, $dent = 0) {
    if (__is_anemon__($in)) {
        $out = "";
        $li = __is_anemon_0__($in) && !$safe; // is numeric array?
        $t = str_repeat($d, $dent);
        foreach ($in as $k => $v) {
            if (strpos($k, ':') !== false) {
                $k = '"' . $k . '"';
            }
            if (!__is_anemon__($v) || empty($v)) {
                if (is_array($v)) {
                    $v = '[]';
                } else if (is_object($v)) {
                    $v = '{}';
                } else if ($v === "") {
                    $v = '""';
                } else {
                    $v = s($v);
                }
                $v = strpos($v, "\n") !== false ? "|\n" . $t . $d . str_replace("\n", "\n" . $t . $d, $v) : $v;
                // Comment…
                if (strpos($v, '#') === 0) {
                    $out .= $t . trim($v) . "\n";
                // …
                } else {
                    $out .= $t . ($li ? '- ' : trim($k) . ': ') . $v . "\n";
                }
            } else {
                $out .= $t . $k . ":\n" . __to_yaml__($v, $d, $safe, $dent + 1) . "\n";
            }
        }
        return rtrim($out, "\n");
    }
    return strpos($in, ': ') === false ? json_encode($in) : $in;
}

foreach([
    'anemon' => function($in) {
        return (array) (is_array($in) || is_object($in) ? a($in) : json_decode($in, true));
    },
    'base64' => 'base64_encode',
    'camel' => 'c',
    'dec' => function($in, $z = false, $f = ['&#', ';']) {
        $out = "";
        for($i = 0, $count = strlen($in); $i < $count; ++$i) {
            $s = ord($in[$i]);
            if ($z) $s = str_pad($s, 4, '0', STR_PAD_LEFT);
            $out .= $f[0] . $s . $f[1];
        }
        return $out;
    },
    'file' => function($in) {
        $in = explode(DS, str_replace('/', DS, $in));
        $n = explode('.', array_pop($in));
        $x = array_pop($n);
        $s = "";
        foreach ($in as $v) {
            $s .= h($v, '-', true, '_') . DS;
        }
        return $s . h(implode('.', $n), '-', true, '_.') . '.' . h($x, '-', true);
    },
    'folder' => function($in) {
        $in = explode(DS, str_replace('/', DS, $in));
        $n = array_pop($in);
        $s = "";
        foreach ($in as $v) {
            $s .= h($v, '-', true, '_') . DS;
        }
        return $s . h($n, '-', true, '_');
    },
    'hex' => function($in, $z = false, $f = ['&#x', ';']) {
        $out = "";
        for($i = 0, $count = strlen($in); $i < $count; ++$i) {
            $s = dechex(ord($in[$i]));
            if ($z) $s = str_pad($s, 4, '0', STR_PAD_LEFT);
            $out .= $f[0] . $s . $f[1];
        }
        return $out;
    },
    'HTML' => ['htmlspecialchars_decode', [null, ENT_QUOTES | ENT_HTML5]],
    'JSON' => function($in, $tidy = false) {
        if ($tidy) {
            $i = JSON_FORCE_OBJECT | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT;
        } else {
            $i = JSON_FORCE_OBJECT | JSON_UNESCAPED_UNICODE;
        }
        return json_encode($in, $i);
    },
    'kebab' => function($in, $a = true) {
        return trim(h($in, '-', $a), '-');
    },
    'key' => function($in, $a = true) {
        $s = trim(h($in, '_', $a), '_');
        return $s && is_numeric($s[0]) ? '_' . $s : $s;
    },
    'lower' => 'l',
    'pascal' => 'p',
    'path' => function($in) {
        $u = $GLOBALS['URL'];
        $s = str_replace('/', DS, $u['$']);
        $in = str_replace([$u['$'], '\\', '/', $s], [ROOT, DS, DS, ROOT], $in);
        return file_exists($in) ? realpath($in) : $in;
    },
    'query' => function($in, $c = []) {
        $c = array_replace(['?', '&', '=', ""], $c);
        foreach (__to_query__($in, "") as $k => $v) {
            if ($v === false) continue; // `['a' => 'false', 'b' => false]` → `a=false`
            $v = $v !== true ? $c[2] . urlencode(s($v)) : ""; // `['a' => 'true', 'b' => true]` → `a=true&b`
            $out[] = $k . $v; // `['a' => 'null', 'b' => null]` → `a=null&b=null`
        }
        return !empty($out) ? $c[0] . implode($c[1], $out) . $c[3] : "";
    },
    'sentence' => function($in, $tail = '.') {
        $in = trim($in);
        if (extension_loaded('mbstring')) {
            return mb_strtoupper(mb_substr($in, 0, 1)) . mb_strtolower(mb_substr($in, 1)) . $tail;
        }
        return ucfirst(strtolower($in)) . $tail;
    },
    'serial' => 'serialize',
    'slug' => function($in, $s = '-', $a = true) {
        return trim(h($in, $s, $a), $s);
    },
    'snake' => function($in, $a = true) {
        return trim(h($in, '_', $a), '_');
    },
    'snippet' => function($in, $html = true, $x = [200, '&#x2026;']) {
        $s = w($in, $html ? HTML_WISE_I : []);
        $utf8 = extension_loaded('mbstring');
        if (is_int($x)) {
            $x = [$x, '&#x2026;'];
        }
        $utf8 = extension_loaded('mbstring');
        // <https://stackoverflow.com/a/1193598/1163000>
        if ($html && (strpos($s, '<') !== false || strpos($s, '&') !== false)) {
            $out = "";
            $done = $i = 0;
            $tags = [];
            while ($done < $x[0] && preg_match('#</?([a-z\d:.-]+)(?:\s[^<>]*?)?>|&(?:[a-z\d]+|\#\d+|\#x[a-f\d]+);|[\x80-\xFF][\x80-\xBF]*#i', $s, $m, PREG_OFFSET_CAPTURE, $i)) {
                list($tag, $pos) = $m[0];
                $str = substr($s, $i, $pos - $i);
                if ($done + strlen($str) > $x[0]) {
                    $out .= substr($str, 0, $x[0] - $done);
                    $done = $x[0];
                    break;
                }
                $out .= $str;
                $done += strlen($str);
                if ($done >= $x[0]) {
                    break;
                }
                if ($tag[0] === '&' || ord($tag) >= 0x80) {
                    $out .= $tag;
                    ++$done;
                } else {
                    // `tag`
                    $n = $m[1][0];
                    // `</tag>`
                    if ($tag[1] === '/') {
                        $open = array_pop($tags);
                        assert($open === $n); // Check that tag(s) are properly nested!
                        $out .= $tag;
                    // `<tag/>`
                    } else if (substr($tag, -2) === '/>' || preg_match('#<(?:br|hr|img|input|link|meta|svg)(?:\s[^<>]*?)?>#i', $tag)) {
                        $out .= $tag;
                    // `<tag>`
                    } else {
                        $out .= $tag;
                        $tags[] = $n;
                    }
                }
                // Continue after the tag…
                $i = $pos + strlen($tag);
            }
            // Print rest of the text…
            if ($done < $x[0] && $i < strlen($s)) {
                $out .= substr($s, $i, $x[0] - $done);
            }
            // Close any open tag(s)…
            while ($close = array_pop($tags)) {
                $out .= '</' . $close . '>';
            }
            $out = trim(str_replace('<br>', ' ', $out));
            $s = trim(strip_tags($s));
            $t = $utf8 ? mb_strlen($s) : strlen($s);
            return $out . ($t > $x[0] ? $x[1] : "");
        }
        $s = $utf8 ? mb_substr($s, 0, $x[0]) : substr($s, 0, $x[0]);
        $t = $utf8 ? mb_strlen($s) : strlen($s);
        return trim($s) . ($t > $x[0] ? $x[1] : "");
    },
    'text' => 'w',
    'title' => function($in) {
        $in = w($in);
        if (extension_loaded('mbstring')) {
            return mb_convert_case($in, MB_CASE_TITLE);
        }
        return ucwords($in);
    },
    'upper' => 'u',
    'URL' => function($in, $raw = false) {
        $u = $GLOBALS['URL'];
        $s = str_replace(DS, '/', ROOT);
        $in = file_exists($in) ? realpath($in) : $in;
        $in = str_replace([ROOT, DS, '\\', $s], [$u['$'], '/', '/', $u['$']], $in);
        return $raw ? rawurldecode($in) : urldecode($in);
    },
    'YAML' => function(...$lot) {
        if (!__is_anemon__($lot[0])) {
            return s($lot[0]);
        }
        if (is_string($lot[0]) && Is::path($lot[0], true)) {
            $lot[0] = include $lot[0];
        }
        return __to_yaml__(...$lot);
    }
] as $k => $v) {
    To::_($k, $v);
}

// Alias(es)…
foreach ([
    'files' => 'folder',
    'html' => 'HTML',
    'url' => 'URL',
    'yaml' => 'YAML'
] as $k => $v) {
    To::_($k, To::_($v));
}