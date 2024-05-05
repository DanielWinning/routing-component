<?php

namespace Luma\RoutingComponent\Attribute;

#[\Attribute(\Attribute::TARGET_METHOD)]
class RequireUnauthenticated
{
    /**
     * @param string|null $redirectPath
     * @param string|null $message
     */
    public function __construct(string $redirectPath = null, string $message = null)
    {
    }
}