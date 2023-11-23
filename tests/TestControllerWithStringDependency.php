<?php

namespace DannyXCII\RoutingComponentTests;

class TestControllerWithStringDependency
{
    public function __construct(string $invalidParamType)
    {
        //
    }

    /**
     * @return string
     */
    public function testMethod(): string
    {
        return TestController::STRING_RETURN;
    }
}