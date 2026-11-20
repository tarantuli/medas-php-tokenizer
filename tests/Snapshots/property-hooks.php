<?php

class Person
{
    public string $fullName {
        get => $this->first . ' ' . $this->last;
    }

    public string $email {
        set(string $value) {
            $this->email = strtolower($value);
        }
    }

    public function __construct(
        public string $first { set => ucfirst($value); },
        public string $last = '',
    )
    {
    }
}
