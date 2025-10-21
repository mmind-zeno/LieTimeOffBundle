<?php
declare(strict_types=1);

namespace KimaiPlugin\LieTimeOffBundle\DependencyInjection;

use App\Plugin\AbstractPluginExtension;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\PrependExtensionInterface;
use Symfony\Component\DependencyInjection\Loader\YamlFileLoader;

final class LieTimeOffExtension extends AbstractPluginExtension implements PrependExtensionInterface
{
    public function load(array $configs, ContainerBuilder $container): void
    {
        $loader = new YamlFileLoader($container, new FileLocator(__DIR__ . "/../Resources/config"));
        $loader->load("services.yaml");
    }

    public function prepend(ContainerBuilder $container): void
    {
        $container->prependExtensionConfig("twig", [
            "paths" => [
                __DIR__ . "/../Resources/views" => "LieTimeOffBundle",
            ],
        ]);
    }
}