<?php

namespace Luma\RoutingComponentTests;

class TestControllerWithDependencies
{
    public function __construct(TestHelper $helper)
    {
        //
    }

    /**
     * @return string
     */
    public function testMethodWithDependantController(): string
    {
        return TestController::STRING_RETURN;
    }
}