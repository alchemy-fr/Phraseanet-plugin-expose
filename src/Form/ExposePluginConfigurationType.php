<?php

namespace Alchemy\ExposePlugin\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;

class ExposePluginConfigurationType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        parent::buildForm($builder, $options);

        $builder
            ->add('exposeFrontUri', 'text', [
                'label' => 'Expose Front base uri',
                'attr'  => [
                    'class' => 'input-xxlarge'
                ]
            ])
            ->add('exposeBaseUri', 'text', [
                'label' => 'Base Uri Expose api',
                'attr'  => [
                    'class' => 'input-xxlarge'
                ]
            ])
            ->add('clientSecret', 'text', [
                'label' => 'Client secret',
                'attr'  => [
                    'class' => 'input-xxlarge'
                ]
            ])
            ->add('clientId', 'text', [
                'label' => 'Client ID',
                'attr'  => [
                    'class' => 'input-xxlarge'
                ]
            ])
        ;
    }

    public function getName()
    {
        return 'expose_plugin_configuration';
    }
}
