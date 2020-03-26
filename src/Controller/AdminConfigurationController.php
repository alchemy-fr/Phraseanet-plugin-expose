<?php

namespace Alchemy\ExposePlugin\Controller;

use Alchemy\ExposePlugin\Configuration\Config;
use Alchemy\ExposePlugin\Form\ExposePluginConfigurationType;
use Alchemy\Phrasea\Application as PhraseaApplication;
use Alchemy\Phrasea\Controller\Controller;
use Symfony\Component\HttpFoundation\Request;

class AdminConfigurationController extends Controller
{
    /**
     * @param PhraseaApplication $app
     * @param Request $request
     * @return mixed
     */
    public function configurationAction(PhraseaApplication $app, Request $request)
    {
        $config = Config::getConfiguration();

        $form = $app->form(new ExposePluginConfigurationType(), $config['expose_plugin']);

        $form->handleRequest($request);

        if ($form->isValid()) {
            Config::setConfiguration($form->getData());

            return $app->redirectPath('admin_plugins_list');
        }

        return $app['twig']->render('phraseanet-plugin-expose/admin/expose_plugin_configuration.html.twig', [
            'form' => $form->createView()
        ]);
    }
}