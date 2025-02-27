<?php

namespace App\Form;

use App\Entity\Company;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\CollectionType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\File;
use Symfony\Component\Validator\Constraints\NotBlank;

class CompanyType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('name', TextType::class, [
                'required' => false,
                'label' => 'Company Name',
                'attr' => ['class' => 'form-control'],
            ])
            ->add('logoFile', FileType::class, [
                'label' => 'Upload New Logo',
                'mapped' => false,  // Not mapped to entity directly
                'required' => false,
                'constraints' => [
                    new File([
                        'maxSize' => '2M',
                        'mimeTypes' => ['image/jpeg', 'image/png', 'image/webp'],
                        'mimeTypesMessage' => 'Please upload a valid image file (JPG, PNG, WEBP)',
                    ])
                ],
                'attr' => ['class' => 'form-control'],
            ])
            ->add('rates', CollectionType::class, [
                'entry_type' => RateType::class,
                'allow_add' => true,
                'allow_delete' => true,
                'required' => false,
                'label' => 'Rates',
                'attr' => ['class' => 'form-control'],
                'entry_options' => [
                    'constraints' => [
                        new NotBlank(['message' => 'Rate name cannot be empty']),
                    ],
                    'attr' => ['class' => 'form-control'],
                ],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Company::class,
        ]);
    }
}
