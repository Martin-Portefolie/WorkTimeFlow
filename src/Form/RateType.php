<?php

namespace App\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\NotBlank;

class RateType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('key', TextType::class, [
                'label' => 'Rate Name (Identifier)',
                'attr' => [
                    'class' => 'form-control',
                    'placeholder' => 'e.g. rate_for_washing_a_car'
                ],
                'constraints' => [
                    new NotBlank(['message' => 'The rate key cannot be empty.']),
                ]
            ])
            ->add('value', TextType::class, [
                'label' => 'Rate Amount',
                'attr' => [
                    'class' => 'form-control',
                    'placeholder' => 'e.g. 600'
                ],
                'constraints' => [
                    new NotBlank(['message' => 'The rate amount cannot be empty.']),
                ]
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => null,
        ]);
    }
}
