<?php

namespace Luma\RoutingComponent\Attribute;

#[\Attribute(\Attribute::TARGET_METHOD)]
class RequirePermissions
{
    /**
     * @param array $permissions
     * @param string|null $redirectPath
     * @param string|null $message
     */
    public function __construct(array $permissions, string $redirectPath = null, string $message = null)
    {
    }
}