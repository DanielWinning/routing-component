<?php

namespace Luma\RoutingComponent\Attribute;

#[\Attribute(\Attribute::TARGET_METHOD)]
class RequireRoles
{
    /**
     * @param array $roles
     */
    public function __construct(array $roles)
    {
    }
}