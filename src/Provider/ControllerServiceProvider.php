<?php

namespace Alchemy\ExposePlugin\Provider;

use Alchemy\ExposePlugin\ActionBar\ActionBarPlugin;
use Alchemy\ExposePlugin\Configuration\Config;
use Alchemy\ExposePlugin\Configuration\ConfigurationTab;
use Alchemy\ExposePlugin\Controller\AdminConfigurationController;
use Alchemy\ExposePlugin\Controller\ExposeController;
use Alchemy\ExposePlugin\Security\ExposePluginConfigurationVoter;
use Alchemy\ExposePlugin\Security\PluginVoter;
use Alchemy\Phrasea\Application as PhraseaApplication;
use Alchemy\Phrasea\Plugin\BasePluginMetadata;
use Alchemy\Phrasea\Plugin\PluginProviderInterface;
use Alchemy\Phrasea\Security\Firewall;
use Silex\Application;


class ControllerServiceProvider implements PluginProviderInterface
{
    const PLUGIN_NAMESPACE = 'plugin_expose';

    /**
     * {@inheritdoc}
     *
     */
    public function register(Application $app)
    {
        $app['expose_plugin.name'] = 'phraseanet-plugin-expose';
        $app['expose_plugin.version'] = '1.0.0';

        $app['controller.admin.configuration'] = $app->share(function (PhraseaApplication $app) {
            return new AdminConfigurationController($app);
        });

        $app['controller.expose'] = $app->share(function (PhraseaApplication $app) {
            return new ExposeController($app);
        });

        $app[self::PLUGIN_NAMESPACE . '.config'] = $app->share(
            function (/** @noinspection PhpUnusedParameterInspection */ Application $app) {

                return Config::getConfiguration();
            }
        );

        $app[self::PLUGIN_NAMESPACE . '.actionbar'] = $app->share(
            function (/** @noinspection PhpUnusedParameterInspection */ PhraseaApplication $app) {
                return new ActionBarPlugin(
                    $app['expose_plugin.name'],
                    self::PLUGIN_NAMESPACE,
                    self::PLUGIN_NAMESPACE,
                    $app['twig']
                );
            }
        );

        // register admin tab
        $this->registerConfigurationTabs($app);

        // register voters
        $this->registerVoters($app);

        // register route
        $this->registerRoutes($app);

        // register translator resource
        $app['translator'] = $app->share(
            $app->extend('translator', function($translator, $app) {

                $translator->addResource('po',__DIR__ . '/../../locale/en_GB/expose-plugin.po', 'en', self::PLUGIN_NAMESPACE);
                $translator->addResource('po', __DIR__ . '/../../locale/fr_FR/expose-plugin.po', 'fr', self::PLUGIN_NAMESPACE);

                return $translator;
            })
        );

        $app['plugin.actionbar'] = $app->share(
            $app->extend('plugin.actionbar',
                function (\Pimple $plugins) use ($app) {
                    $plugin = $app[self::PLUGIN_NAMESPACE . '.actionbar'];
                    $plugins[spl_object_hash($plugin)] = $plugin;

                    return $plugins;
                }
            )
        );
    }

    /**
     * {@inheritdoc}
     */
    public function boot(Application $app)
    {
        /** @var \Pimple $plugins */
        $plugins = $app['plugins'];
        $plugins[$app['expose_plugin.name']] = $plugins->share(function () use ($app) {
            return new BasePluginMetadata(
                $app['expose_plugin.name'],
                $app['expose_plugin.version'],
                '',
                self::PLUGIN_NAMESPACE,
                $app['expose_plugin.configuration_tabs']
            );
        });
    }

    /**
     * {@inheritdoc}
     */
    public static function create(PhraseaApplication $app)
    {
        return new static();
    }

    private function registerRoutes(Application $app)
    {
        /** @var Firewall $firewall */
        $firewall = $this->getFirewall($app);

        $app->match('/expose-plugin/configuration', 'controller.admin.configuration:configurationAction')
            ->method('GET|POST')
            ->before(function () use ($firewall) {
                $firewall->requireAccessToModule('admin');
            })
            ->bind('expose_plugin_admin_configuration');

        $app->match('/expose/', 'controller.expose:exposeAction')
            ->method('POST')
            ->bind('expose_plugin');

        $app->match('/expose/create-publication/', 'controller.expose:createPublicationAction')
            ->method('POST')
            ->bind('expose_create_publication');

        $app->match('/expose/list-publication/', 'controller.expose:listPublicationAction')
            ->method('GET')
            ->bind('expose_list_publication');
    }

    /**
     * @param Application $app
     */
    private function registerConfigurationTabs(Application $app)
    {
        $app['expose_plugin.configuration_tabs'] = [
            'configuration' => 'expose_plugin.configuration_tabs.configuration',
        ];

        $app['expose_plugin.configuration_tabs.configuration'] = $app->share(function (PhraseaApplication $app) {
            return new ConfigurationTab($app['url_generator']);
        });
    }

    /**
     * @param Application $app
     */
    private function registerVoters(Application $app)
    {
        $app['phraseanet.voters'] = $app->share(
            $app->extend('phraseanet.voters', function (array $voters, PhraseaApplication $app) {
                $voters[] = new PluginVoter($app['repo.users'], $app[self::PLUGIN_NAMESPACE . '.config']);
                $voters[] = new ExposePluginConfigurationVoter($app['repo.users']);

                return $voters;
            })
        );
    }

    /**
     * @param Application $app
     * @return mixed
     */
    private function getFirewall(Application $app)
    {
        return $app['firewall'];
    }
}
