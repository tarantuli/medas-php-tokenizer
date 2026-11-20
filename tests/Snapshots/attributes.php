<?php

#[Entity]
#[Table(name: 'users', options: ['charset' => 'utf8'])]
class User
{
    #[Column(type: Types::STRING, length: 255)]
    public string $name;

    #[Route('/users/{id}', methods: ['GET'])]
    public function show(#[FromRoute] int $id, #[Inject(Logger::class)] Logger $logger): void
    {
    }

    #[First, Second(1)]
    public function multiple(): void
    {
    }
}

$fn = #[Pure] fn(int $x): int => $x;
