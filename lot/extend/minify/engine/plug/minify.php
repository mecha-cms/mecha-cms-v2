<?php


/**
 * `0`: Remove
 * `1`: Keep
 * `2`: Remove if/but/when …
 */

function fn_minify($pattern, $input) {
    return preg_split('#(' . implode('|', $pattern) . ')#', $input, -1, PREG_SPLIT_NO_EMPTY | PREG_SPLIT_DELIM_CAPTURE);
}

function fn_minify_css($input, $comment = 2, $quote = 0) {
    if (!$input = trim($input)) return $input;
    $output = $prev = "";
    foreach (fn_minify([Minify::CSS_COMMENT, Minify::STRING], $input) as $part) {
        if (!trim($part)) continue;
        if ($comment !== 1 && strpos($part, '/*') === 0 && substr($part, -2) === '*/') {
            if (
                $comment === 2 && (
                    // Detect special comment(s) from the third character. It should be a `!` or `*` → `/*! keep */` or `/** keep */`
                    strpos('*!', $part[2]) !== false ||
                    // Detect license comment(s) from the content. It should contains character(s) like `@license`
                    stripos($part, '@licence') !== false || // noun
                    stripos($part, '@license') !== false || // verb
                    stripos($part, '@preserve') !== false
                )
            ) {
                $output .= $part;
            }
            continue;
        }
        if ($part[0] === '"' && substr($part, -1) === '"' || $part[0] === "'" && substr($part, -1) === "'") {
            // Remove quote(s) where possible …
            if (
                $quote === 0 && (
                    substr($prev, -4) === 'url(' && preg_match('#\burl\($#', $prev) || // <https://www.w3.org/TR/CSS2/syndata.html#uri>
                    substr($prev, -1) === '=' && preg_match('#^[a-zA-Z_][\w-]*?$#', $part) // <https://www.w3.org/TR/CSS2/syndata.html#characters>
                )
            ) {
                $part = t($part, $part[0]); // trim quote(s)
            }
            $output .= $part;
        } else {
            $output .= fn_minify_css_union($part);
        }
        $prev = $part;
    }
    return trim($output);
}

function fn_minify_css_union($input) {
    if (stripos($input, 'calc(') !== false) {
        // Keep important white–space(s) in `calc()`
        $input = preg_replace_callback('#\b(calc\()\s*(.*?)\s*\)#i', function($m) {
            return $m[1] . preg_replace('#\s+#', X, $m[2]) . ')';
        }, $input);
    }
    $input = preg_replace([
        // Fix case for `#foo<space>[bar="baz"]`, `#foo<space>*` and `#foo<space>:first-child` [^1]
        '#(?<=[\w])\s+(\*|\[|:[\w-]+)#',
        // Fix case for `[bar="baz"]<space>.foo`, `*<space>.foo` and `@media<space>(foo: bar)<space>and<space>(baz: qux)` [^2]
        '#(\*|\])\s+(?=[\w\#.])#', '#\b\s+\(#', '#\)\s+\b#',
        // Minify HEX color code … [^3]
        '#\#([a-f\d])\1([a-f\d])\2([a-f\d])\3\b#i',
        // Remove white–space(s) around punctuation(s) [^4]
        '#\s*([~!@*\(\)+=\{\}\[\]:;,>\/])\s*#',
        // Replace zero unit(s) with `0` [^5]
        '#\b(?:0\.)?0([a-z]+\b|%)#i',
        // Replace `0.6` with `.6` [^6]
        '#\b0+\.(\d+)#',
        // Replace `:0 0`, `:0 0 0` and `:0 0 0 0` with `:0` [^7]
        '#:(0\s+){0,3}0(?=[!,;\)\}]|$)#',
        // Replace `background(?:-position)?:(0|none)` with `background$1:0 0` [^8]
        '#\b(background(?:-position)?):(0|none)\b#i',
        // Replace `(border(?:-radius)?|outline):none` with `$1:0` [^9]
        '#\b(border(?:-radius)?|outline):none\b#i',
        // Remove empty selector(s) [^10]
        '#(^|[\{\}])(?:[^\{\}]+)\{\}#',
        // Remove the last semi–colon and replace multiple semi–colon(s) with a semi–colon [^11]
        '#;+([;\}])#',
        // Replace multiple white–space(s) with a space [^12]
        '#\s+#'
    ], [
        // [^1]
        X . '$1',
        // [^2]
        '$1' . X, X . '(', ')' . X,
        // [^3]
        '#$1$2$3',
        // [^4]
        '$1',
        // [^5]
        '0',
        // [^6]
        '.$1',
        // [^7]
        ':0',
        // [^8]
        '$1:0 0',
        // [^9]
        '$1:0',
        // [^10]
        '$1',
        // [^11]
        '$1',
        // [^12]
        ' '
    ], $input);
    return trim(str_replace(X, ' ', $input));
}

function fn_minify_html($input, $comment = 2, $quote = 1) {
    if (!$input = trim($input)) return $input;
    $output = "";
    foreach (fn_minify([Minify::HTML_COMMENT, Minify::HTML_KEEP, Minify::HTML], $input) as $part) {
        if ($part !== ' ' && !trim($part) || $comment !== 1 && strpos($part, '<!--') === 0) {
            // Detect IE conditional comment(s) by its closing tag …
            if ($comment === 2 && substr($part, -12) === '<![endif]-->') {
                $output .= $part;
            }
            continue;
        }
        if ($part[0] === '<' && substr($part, -1) === '>') {
            $output .= fn_minify_html_union($part, $quote);
        } else {
            $output .= preg_replace('#\s+#', ' ', $part);
        }
    }
    // Force space with `&#x0020;` and line–break with `&#x000A;`
    return str_ireplace(['&#x0020;', '&#x20;', '&#x000A;', '&#xA;'], [' ', ' ', N, N], $output);
}

function fn_minify_html_union($input, $quote) {
    if (
        strpos($input, ' ') === false &&
        strpos($input, "\n") === false &&
        strpos($input, "\t") === false
    ) return $input;
    return preg_replace_callback('#<\s*([^\/\s]+)\s*(?:>|(\s[^<>]+?)\s*>)#', function($m) use($quote) {
        if (isset($m[2])) {
            // Minify inline CSS declaration(s)
            if (stripos($m[2], ' style=') !== false) {
                $m[2] = preg_replace_callback('#( style=)([\'"]?)(.*?)\2#i', function($m) {
                    return $m[1] . $m[2] . fn_minify_css($m[3]) . $m[2];
                }, $m[2]);
            }
            $a = 'a(sync|uto(focus|play))|c(hecked|ontrols)|d(efer|isabled)|hidden|ismap|loop|multiple|open|re(adonly|quired)|s((cop|elect)ed|pellcheck)';
            $a = '<' . $m[1] . preg_replace([
                // From `a="a"`, `a='a'`, `a="true"`, `a='true'`, `a=""` and `a=''` to `a` [^1]
                '#\s(' . $a . ')(?:=([\'"]?)(?:true|\1)?\2)#i',
                // Remove extra white–space(s) between HTML attribute(s) [^2]
                '#\s*([^\s=]+?)(=(?:\S+|([\'"]?).*?\3)|$)#',
                // From `<img />` to `<img/>` [^3]
                '#\s+\/$#'
            ], [
                // [^1]
                ' $1',
                // [^2]
                ' $1$2',
                // [^3]
                '/'
            ], str_replace("\n", ' ', $m[2])) . '>';
            return $quote === 0 ? fn_minify_html_union_attr($a) : $a;
        }
        return '<' . $m[1] . '>';
    }, $input);
}

function fn_minify_html_union_attr($input) {
    if (strpos($input, '=') === false) return $input;
    return preg_replace_callback('#=(' . Minify::STRING . ')#', function($m) {
        $q = $m[1][0];
        if (strpos($m[1], ' ') === false && preg_match('#^' . $q . '[a-zA-Z_][\w-]*?' . $q . '$#', $m[1])) {
            return '=' . t($m[1], $q);
        }
        return $m[0];
    }, $input);
}

function fn_minify_js($input, $comment = 2) {
    if (!$input = trim($input)) return $input;
    foreach (fn_minify([Minify::CSS_COMMENT, Minify::JS_COMMENT, Minify::STRING, Minify::JS_PATTERN], $input) as $part) {
        // TODO
    }
    $output = $input;
    return $output;
}

Minify::plug('css', 'fn_minify_css');
Minify::plug('html', 'fn_minify_html');
Minify::plug('js', 'fn_minify_js');