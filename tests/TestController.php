<?php

namespace DannyXCII\RoutingComponentTests;

class TestController
{
    public function test_index(array $matches = []): ?string
    {
        return null;
    }

    public function test_1(array $matches = []): ?string
    {
        return null;
    }

    public function test_2(array $matches = []): ?string
    {
        return null;
    }

    public function test_3(array $matches = []): ?string
    {
        return null;
    }

    public function test_4(string $id): ?string
    {
        return $id;
    }
}