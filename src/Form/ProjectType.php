<?php

namespace App\Form;

use App\Entity\Client;
use App\Entity\Project;
use App\Entity\Team;
use App\Enum\Priority;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class ProjectType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('name', TextType::class, [
                'label' => 'Project Name',
            ])
            ->add('description', TextType::class, [
                'label' => 'Description',
                'required' => false,
            ])
            ->add('client', EntityType::class, [
                'class' => Client::class,
                'choice_label' => 'name',
                'label' => 'Select Client',
                'placeholder' => 'Choose a Client',
                'required' => true, // Ensure client is required
            ])
            ->add('teams', EntityType::class, [
                'class' => Team::class,
                'choice_label' => 'name',
                'multiple' => true,
                'expanded' => true, // Uses checkboxes
                'label' => 'Assign Teams',
                'required' => false,
            ])
            ->add('priority', ChoiceType::class, [
                'choices' => array_combine(Priority::getValues(), Priority::cases()),
                'label' => 'Priority',
                'expanded' => false, // Use dropdown
                'multiple' => false,
            ])
            ->add('deadline', DateType::class, [
                'label' => 'Deadline',
                'widget' => 'single_text',
                'required' => true, // Since deadline cannot be null
            ])
            ->add('estimatedBudget', NumberType::class, [
                'label' => 'Estimated Budget',
                'required' => false,
                'scale' => 2, // Allow decimals
            ])
            ->add('estimatedHours', NumberType::class, [
                'label' => 'Estimated Hours',
                'required' => false,
            ])
            ->add('isArchived', CheckboxType::class, [
                'label' => 'Archived',
                'required' => false,
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Project::class,
        ]);
    }
}
