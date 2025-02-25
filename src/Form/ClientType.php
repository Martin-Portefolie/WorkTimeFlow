<?php

namespace App\Form;

use App\Entity\Client;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\TelType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class ClientType extends AbstractType
{
public function buildForm(FormBuilderInterface $builder, array $options): void
{
$builder
->add('name', TextType::class, [
'label' => 'Client Name',
'attr' => ['class' => 'form-input']
])
    ->add('postalCode', TextType::class, [
        'label' => 'Postal Code',
        'attr' => ['class' => 'form-input']
    ])
    ->add('city', TextType::class, [
        'label' => 'City',
        'attr' => ['class' => 'form-input']
    ])
    ->add('country', TextType::class, [
        'label' => 'Country',
        'attr' => ['class' => 'form-input']
    ])
    ->add('adress', TextType::class, [
        'label' => 'Client Adress',
        'attr' => ['class' => 'form-input']
    ])

->add('contactPerson', TextType::class, [
'label' => 'Contact Person',
'attr' => ['class' => 'form-input']
])
->add('contactEmail', EmailType::class, [
'label' => 'Client Email',
'attr' => ['class' => 'form-input']
])
->add('contactPhone', TelType::class, [
'label' => 'Client Phone',
'attr' => ['class' => 'form-input']
]);
}

public function configureOptions(OptionsResolver $resolver): void
{
$resolver->setDefaults([
'data_class' => Client::class,
]);
}
}
