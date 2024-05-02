<?php

namespace Luma\RoutingComponent\Attribute;

#[\Attribute(\Attribute::TARGET_METHOD)]
class RequirePermissions
{
    /**
     * @param array $permissions
     */
    public function __construct(array $permissions)
    {
    }
}