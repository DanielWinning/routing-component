<?php

namespace Luma\Tests\Controllers;

use Luma\HttpComponent\Response;
use Luma\HttpComponent\StreamBuilder;

class TestController
{
    public const STRING_RETURN = 'string';

    /**
     * @return string|null
     */
    public function testIndex(): string|null
    {
        return self::STRING_RETURN;
    }

    /**
     * @param string $id
     *
     * @return string|null
     */
    public function testParams(string $id): string|null
    {
        return $id;
    }

    /**
     * @param string $category
     * @param string $id
     *
     * @return string|null
     */
    public function testMultipleParams(string $category, string $id): string|null
    {
        return sprintf('Category: %s | Post ID: %s', $category, $id);
    }

    /**
     * @param array $matches
     *
     * @return Response
     */
    public function testReturnResponseClass(array $matches = []): Response
    {
        return (new Response())->withStatus(200)->withBody(StreamBuilder::build('Test response'));
    }

    /**
     * @param array $matches
     *
     * @return string|null
     */
    public function testReturnInvalidResponse(array $matches = []): ?string
    {
        return null;
    }
}