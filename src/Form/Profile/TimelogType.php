<?php

namespace App\Form\Profile;

use App\Entity\Timelog;
use App\Entity\Todo;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class TimelogType extends AbstractType
{
    private Security $security;

    public function __construct(Security $security)
    {
        $this->security = $security;
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('todo', EntityType::class, [
                'class' => Todo::class,
                'choice_label' => 'name',
                'placeholder' => 'Select a Task',
                'query_builder' => function ($repo) {
                    return $repo->createQueryBuilder('t')
                        ->join('t.project', 'p')
                        ->join('p.teams', 'team')
                        ->where('team IN (:teams)')
                        ->setParameter('teams', $this->security->getUser()->getTeams());
                },
            ])
            ->add('date', DateType::class, [
                'widget' => 'single_text',
                'required' => true,
            ])
            ->add('hours', IntegerType::class, [
                'required' => true,
                'attr' => ['min' => 0, 'max' => 24],
            ])
            ->add('minutes', IntegerType::class, [
                'required' => true,
                'attr' => ['min' => 0, 'max' => 59],
            ])
            ->add('description', TextareaType::class, [
                'required' => false,
                'attr' => ['rows' => 3],
            ])
            ->add('save', SubmitType::class, [
                'label' => 'Register Time',
                'attr' => ['class' => 'btn btn-primary'],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Timelog::class,
        ]);
    }
}
