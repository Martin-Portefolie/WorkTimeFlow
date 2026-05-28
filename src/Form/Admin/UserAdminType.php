<?php

namespace App\Form\Admin;

use App\Entity\Team;
use App\Entity\User;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class UserAdminType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $mode = $options['mode'];

        $builder
            ->add('username', TextType::class, [
                'required' => false,
            ])

            ->add('email', EmailType::class)

            ->add('roles', ChoiceType::class, [
                'choices' => [
                    'User' => 'ROLE_USER',
                    'Admin' => 'ROLE_ADMIN',
                ],
                'expanded' => true,
                'multiple' => true,
                'required' => false,
            ])

            ->add('isActive', CheckboxType::class, [
                'required' => false,
            ])

            ->add('teams', EntityType::class, [
                'class' => Team::class,
                'choice_label' => 'name',
                'multiple' => true,
                'expanded' => true,
                'required' => false,
            ]);

        if ($mode === 'create') {
            $builder->add('plainPassword', PasswordType::class, [
                'mapped' => false,
                'required' => false,
                'help' => 'Leave empty to auto-generate a password.',
            ]);
        }
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => User::class,
            'mode' => 'create',
        ]);

        $resolver->setAllowedValues('mode', [
            'create',
            'update',
        ]);
    }
}
