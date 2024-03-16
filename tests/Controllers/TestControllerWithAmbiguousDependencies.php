<?php

namespace Luma\Tests\Controllers;

class TestControllerWithAmbiguousDependencies
{
    public function __construct($var)
    {
        //
    }

    public function index(): string
    {
        return TestController::STRING_RETURN;
    }
}