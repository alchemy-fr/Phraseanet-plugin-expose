<?php

namespace Alchemy\ExposePlugin\ActionBar;

use Alchemy\Phrasea\Plugin\ActionBarPluginInterface;
use Twig_Environment;


class ActionBarPlugin implements ActionBarPluginInterface
{
    private $assetNamespace;
    private $pluginName;
    private $pluginLocale;
    /** @var Twig_Environment */
    private $twig;

    public function __construct($pluginName, $assetNamespace, $pluginLocale, $twig)
    {
        $this->assetNamespace = $assetNamespace;
        $this->pluginName = $pluginName;
        $this->pluginLocale = $pluginLocale;
        $this->twig = $twig;
    }

    public function getActionBar()
    {
        return [
            'push' => [
                '1' => [
                    'classes' => 'TOOL_expose_btn',
                    'label'   => 'Expose',
                    'icon'    => 'img/expose.png'
                ],
            ],
        ];
    }

    public function getPluginName()
    {
        return $this->pluginName;
    }

    public function getPluginLocale()
    {
        return $this->pluginLocale;
    }

    public function getActionbarTemplate()
    {
        return sprintf('%s/prod/actionbar.html.twig', $this->pluginName);
    }
}
