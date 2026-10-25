<?php

declare(strict_types=1);

namespace Altmenorg\SyliusGoogleShoppingPlugin;

use Sylius\Bundle\CoreBundle\Application\SyliusPluginTrait;
use Symfony\Component\HttpKernel\Bundle\Bundle;

final class AltmenorgSyliusGoogleShoppingPlugin extends Bundle
{
    use SyliusPluginTrait;

    public const VERSION = '0.1.0';

    public function getPath(): string
    {
        return \dirname(__DIR__);
    }
}
