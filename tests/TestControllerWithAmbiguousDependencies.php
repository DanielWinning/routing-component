<?php

namespace Luma\RoutingComponentTests;

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