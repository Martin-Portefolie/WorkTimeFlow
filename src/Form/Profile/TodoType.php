<?php

namespace App\Form\Profile;

use App\Entity\Project;
use App\Entity\Todo;
use App\Enum\TodoStatus;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class TodoType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('name', TextType::class, [
                'label' => 'Todo Name',
                'attr' => ['class' => 'form-control'],
            ])
            ->add('project', EntityType::class, [
                'class' => Project::class,
                'choice_label' => 'name',
                'label' => 'Select Project',
                'attr' => ['class' => 'form-control'],
            ])
            ->add('dateStart', DateType::class, [
                'widget' => 'single_text',
                'label' => 'Start Date',
                'attr' => ['class' => 'form-control'],
            ])
            ->add('dateEnd', DateType::class, [
                'widget' => 'single_text',
                'label' => 'End Date',
                'attr' => ['class' => 'form-control'],
            ])
            ->add('status', ChoiceType::class, [
                'choices' => array_combine(
                    array_map(fn ($status) => $status->getLabel(), TodoStatus::cases()),
                    TodoStatus::cases()
                ),
                'label' => 'Status',
                'attr' => ['class' => 'form-control'],
            ])
            ->add('save', SubmitType::class, [
                'label' => 'Create Todo',
                'attr' => ['class' => 'btn btn-primary'],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Todo::class,
        ]);
    }
}
