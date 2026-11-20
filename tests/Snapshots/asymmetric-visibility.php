<?php

class Account
{
    public private(set) int $balance = 0;
    public protected(set) string $owner;

    public function __construct(private(set) string $id)
    {
    }
}
