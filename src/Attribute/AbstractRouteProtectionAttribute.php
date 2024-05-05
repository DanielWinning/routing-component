<?php

namespace Luma\RoutingComponent\Attribute;

#[\Attribute(\Attribute::TARGET_METHOD)]
class AbstractRouteProtectionAttribute
{
    public const string REDIRECT_PATH_KEY = 'redirectPath';
    public const string MESSAGE_KEY = 'message';

    public function __construct(?string $redirectPath = null, ?string $message = null)
    {
    }
}