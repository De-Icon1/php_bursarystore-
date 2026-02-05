<?php
$path = __DIR__ . '/../manage_users.php';
$src = file_get_contents($path);
$tokens = token_get_all($src);
$open = $close = 0;
$stack = [];
$line = 1;
$in_php = false;
foreach($tokens as $tok){
    if(is_array($tok)){
        $tok_id = $tok[0];
        $text = $tok[1];
        $nl = substr_count($text, "\n");
        // track entering/exiting PHP mode
        if($tok_id === T_OPEN_TAG || $tok_id === T_OPEN_TAG_WITH_ECHO){
            $in_php = true;
            $line += $nl;
            continue;
        }
        if($tok_id === T_CLOSE_TAG){
            $in_php = false;
            $line += $nl;
            continue;
        }
        // only process tokens when inside PHP
        if(!$in_php){
            $line += $nl;
            continue;
        }
        // skip strings and comments inside PHP
        if(in_array($tok_id, [T_CONSTANT_ENCAPSED_STRING, T_ENCAPSED_AND_WHITESPACE, T_COMMENT, T_DOC_COMMENT])){
            $line += $nl;
            continue;
        }
        // scan token text for braces
        $pos = 0;
        while(($idx = strpos($text, '{', $pos)) !== false){
            $open++;
            $line_for_brace = $line + substr_count(substr($text, 0, $idx), "\n");
            $stack[] = $line_for_brace;
            $pos = $idx + 1;
        }
        $pos = 0;
        while(($idx = strpos($text, '}', $pos)) !== false){
            $close++;
            if(!empty($stack)) array_pop($stack);
            $pos = $idx + 1;
        }
        $line += $nl;
    } else {
        // single-char token (only relevant inside PHP)
        if(!$in_php) continue;
        $char = $tok;
        if($char === '{'){
            $open++; $stack[] = $line;
        }
        if($char === '}'){
            $close++; if(!empty($stack)) array_pop($stack);
        }
    }
}

echo "open={$open} close={$close}\n";

// print lines that contain braces for context
$lines = explode("\n", $src);
for($i=0;$i<count($lines);$i++){
    $ln = $i+1;
    if(strpos($lines[$i], '{') !== false || strpos($lines[$i], '}') !== false){
        echo sprintf("%4d: %s\n", $ln, $lines[$i]);
    }
}

if(!empty($stack)){
    echo "Unmatched opening brace estimated at line: " . array_pop($stack) . "\n";
}
// Also print cumulative balance per line (ignoring quoted strings)
$balance = 0;
$in_single = $in_double = false; $escaped = false;
$lines = explode("\n", $src);
for($ln=0;$ln<count($lines);$ln++){
    $lineText = $lines[$ln];
    for($i=0;$i<strlen($lineText);$i++){
        $ch = $lineText[$i];
        if($in_single){
            if($ch === "\\" && !$escaped){ $escaped = true; continue; }
            if($ch === "'" && !$escaped){ $in_single = false; }
            $escaped = false; continue;
        }
        if($in_double){
            if($ch === "\\" && !$escaped){ $escaped = true; continue; }
            if($ch === '"' && !$escaped){ $in_double = false; }
            $escaped = false; continue;
        }
        if($ch === "'") { $in_single = true; continue; }
        if($ch === '"') { $in_double = true; continue; }
        if($ch === '{') $balance++;
        if($ch === '}') $balance--;
    }
    if(strpos($lineText,'{')!==false || strpos($lineText,'}')!==false){
        echo sprintf("%4d: bal=%+3d %s\n", $ln+1, $balance, $lineText);
    }
}
