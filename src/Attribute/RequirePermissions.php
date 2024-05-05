<?php

namespace Luma\RoutingComponent\Attribute;

#[\Attribute(\Attribute::TARGET_METHOD)]
class RequirePermissions extends AbstractRouteProtectionAttribute
{
    public const string PERMISSIONS_KEY = 'permissions';

    /**
     * @param array $permissions
     * @param string|null $redirectPath
     * @param string|null $message
     */
    public function __construct(array $permissions, string $redirectPath = null, string $message = null)
    {
        parent::__construct($redirectPath, $message);
    }
}