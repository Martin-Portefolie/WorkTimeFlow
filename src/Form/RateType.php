<?php

namespace App\Form;

use App\Entity\Rate;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class RateType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('name', TextType::class, [
                'label' => 'Rate Name',
                'attr' => ['class' => 'form-control', 'placeholder' => 'Enter rate name'],
            ])
            ->add('value', NumberType::class, [
                'label' => 'Rate Value',
                'attr' => ['class' => 'form-control', 'placeholder' => 'Enter amount'],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Rate::class,
        ]);
    }
}
