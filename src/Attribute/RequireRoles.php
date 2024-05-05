<?php

namespace Luma\RoutingComponent\Attribute;

#[\Attribute(\Attribute::TARGET_METHOD)]
class RequireRoles
{
    /**
     * @param array $roles
     * @param string|null $redirectPath
     * @param string|null $message
     */
    public function __construct(array $roles, string $redirectPath = null, string $message = null)
    {
    }
}