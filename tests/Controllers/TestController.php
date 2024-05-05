<?php

namespace Luma\Tests\Controllers;

use Luma\HttpComponent\Request;
use Luma\HttpComponent\Response;
use Luma\HttpComponent\StreamBuilder;
use Luma\RoutingComponent\Attribute\RequireAuthentication;
use Luma\RoutingComponent\Attribute\RequireUnauthenticated;

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
     * @param Request $request
     *
     * @return Response
     */
    public function testReturnResponseClass(Request $request): Response
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

    /**
     * @return void
     */
    #[RequireAuthentication]
    public function notAuthenticated(): void
    {
        //
    }

    /**
     * @return string
     */
    #[RequireUnauthenticated]
    public function notAuthenticatedSuccess(): string
    {
        return 'Success';
    }

    #[RequireUnauthenticated(redirectPath: '/', message: 'You must be signed out to view this page.')]
    public function notAuthenticatedWithArgumentsSuccess(): string
    {
        return 'Success';
    }
}