<?php

// requires PHP 8.5

$slug = $title
    |> trim(...)
    |> strtolower(...)
    |> (fn(string $text): string => str_replace(' ', '-', $text));

(void) $logger->flush();
