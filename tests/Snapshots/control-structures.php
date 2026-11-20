<?php

if ($a) {
    $b = 1;
} elseif ($c) {
    $b = 2;
} else {
    $b = 3;
}

for ($i = 0, $j = 10; $i < $j; $i++, $j--) {
    continue;
}

foreach ($items as $key => $value) {
    break;
}

while ($x) {
    $x--;
}

do {
    $y++;
} while ($y < 10);

try {
    risky();
} catch (FirstException | SecondException $e) {
    report($e);
} catch (\Throwable) {
} finally {
    cleanUp();
}

if ($short) echo 'short';
