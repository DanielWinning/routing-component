<?php

namespace DannyXCII\RoutingComponentTests;

use DannyXCII\HttpComponent\Response;
use DannyXCII\HttpComponent\Stream;
use DannyXCII\HttpComponent\StreamBuilder;

class TestController
{
    public const STRING_RETURN = 'string';

    public function test_index(array $matches = []): ?string
    {
        return self::STRING_RETURN;
    }

    public function test_1(array $matches = []): ?string
    {
        return self::STRING_RETURN;
    }

    public function test_2(array $matches = []): string
    {
        return self::STRING_RETURN;
    }

    public function test_3(array $matches = []): ?string
    {
        return self::STRING_RETURN;
    }

    public function test_4(string $id): ?string
    {
        return $id;
    }

    public function test_5(string $category, string $id): ?string
    {
        return sprintf('Category: %s | Post ID: %s', $category, $id);
    }

    public function test_6(array $matches = []): Response
    {
        return (new Response())->withStatus(200)->withBody(StreamBuilder::build('Test response'));
    }

    public function test_7(array $matches = []): ?string
    {
        return null;
    }
}