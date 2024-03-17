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
     * @return Response
     */
    public function testReturnResponseClass(): Response
    {
        return (new Response())->withStatus(200)->withBody(StreamBuilder::build('Test response'));
    }

    /**
     * @return string|null
     */
    public function testReturnInvalidResponse(): string|null
    {
        return null;
    }
}