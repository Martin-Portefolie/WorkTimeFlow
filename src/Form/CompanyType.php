<?php

namespace App\Form;

use App\Entity\Company;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CollectionType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\File;

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
            ->add('logoFile', FileType::class, [ // ✅ Ensure logo upload field exists
                'label' => 'Upload New Logo',
                'mapped' => false, // Prevents mapping to the entity directly
                'required' => false,
                'constraints' => [
                    new File([
                        'maxSize' => '2M',
                        'mimeTypes' => ['image/jpeg', 'image/png', 'image/webp'],
                        'mimeTypesMessage' => 'Please upload a valid image file (JPG, PNG, WEBP)',
                    ]),
                ],
                'attr' => ['class' => 'form-control'],
            ])
            ->add('rates', CollectionType::class, [
                'entry_type' => RateType::class, // ✅ Use `RateType`
                'allow_add' => true,
                'allow_delete' => true,
                'by_reference' => false, // ✅ Important for managing collections in Symfony
                'attr' => ['class' => 'rates-collection'],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Company::class, // ✅ Maps to `Company`
        ]);
    }
}
