<?php

if ($a):
    $b = 1;
elseif ($c):
    $b = 2;
else:
    $b = 3;
endif;

foreach ($items as $item):
    echo $item;
endforeach;

while ($x):
    $x--;
endwhile;
