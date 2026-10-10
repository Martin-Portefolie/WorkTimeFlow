<?php

namespace App\Form\Admin;

use Symfony\Component\Form\Flow\AbstractFlowType;
use Symfony\Component\Form\Flow\FormFlowBuilderInterface;
use Symfony\Component\Form\Flow\Type\NavigatorFlowType;
use Symfony\Component\OptionsResolver\OptionsResolver;

final class CompanyFlowType extends AbstractFlowType
{
    public function buildFormFlow(FormFlowBuilderInterface $builder, array $options): void
    {
        $builder
            ->addStep('details', CompanyDetailsType::class)
            ->addStep('logo', CompanyLogoType::class)
            ->addStep('rates', CompanyRatesType::class)
            ->add('navigator', NavigatorFlowType::class);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => CompanyFlowData::class,
            'step_property_path' => 'currentStep',
        ]);
    }
}
