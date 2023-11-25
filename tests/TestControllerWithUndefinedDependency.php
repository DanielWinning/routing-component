<?php

namespace Luma\RoutingComponentTests;

class TestControllerWithUndefinedDependency
{
    public function __construct(UndefinedTestHelper $undefinedTestHelper)
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