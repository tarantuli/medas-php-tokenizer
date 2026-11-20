<?php

$single = 'it\'s {$not} interpolated';
$double = "Hello $name, you have {$count} new {$items['type']}s and {$user->name()}";
$dollarBrace = "Value: ${value}";
$heredoc = <<<TEXT
    Dear {$user->name},
    Total: $total
    TEXT;
$nowdoc = <<<'TEXT'
    No {$interpolation} here
    TEXT;
$escaped = "Tab\tand \"quotes\" and \$dollar";
