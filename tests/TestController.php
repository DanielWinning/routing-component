<?php

namespace Luma\RoutingComponentTests;

use Luma\HttpComponent\Response;
use Luma\HttpComponent\StreamBuilder;

class TestController
{
    public const STRING_RETURN = 'string';

    public function testIndex(array $matches = []): ?string
    {
        return self::STRING_RETURN;
    }

    public function testParams(string $id): ?string
    {
        return $id;
    }

    public function testMultipleParams(string $category, string $id): ?string
    {
        return sprintf('Category: %s | Post ID: %s', $category, $id);
    }

    public function testReturnResponseClass(array $matches = []): Response
    {
        return (new Response())->withStatus(200)->withBody(StreamBuilder::build('Test response'));
    }

    public function testReturnInvalidResponse(array $matches = []): ?string
    {
        return null;
    }
}