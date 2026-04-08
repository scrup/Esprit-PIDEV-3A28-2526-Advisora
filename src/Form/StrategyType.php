<?php

namespace App\Form;

use App\Entity\Project;
use App\Entity\Strategie;
use App\Entity\User;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use App\Enum\StatusStrategie;
use App\Enum\TypeStrategie;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints as Assert;

class StrategyType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('statusStrategie', ChoiceType::class, [
                'choices' => StatusStrategie::cases(),
                'choice_label' => fn(StatusStrategie $status) => $status->value,
                'choice_value' => fn(?StatusStrategie $status) => $status?->value,
                'constraints' => [
                    new Assert\NotBlank(),
                ],
            ])
         
            ->add('news')
            ->add('nomStrategie', null, [
                'constraints' => [
                    new Assert\NotBlank(),
                    new Assert\Length(['min' => 3, 'max' => 255]),
                ],
            ])
            ->add('justification')
            ->add('type', ChoiceType::class, [
                'choices' => TypeStrategie::cases(),
                'choice_label' => fn(TypeStrategie $type) => $type->value,
                'choice_value' => fn(?TypeStrategie $type) => $type?->value,
                'required' => false,
            ])
            ->add('budgetTotal', null, [
                'constraints' => [
                    new Assert\PositiveOrZero(),
                ],
            ])
            ->add('gainEstime')
             ->add('DureeTerme', IntegerType::class, [
                'constraints' => [
                    new Assert\NotBlank(),
                    new Assert\GreaterThan(0),
                ],
            ])
            ->add('project', EntityType::class, [
    'class' => Project::class,
    'choice_label' => 'titleProj', // Use a meaningful field (e.g., project name)
    'label' => 'Projet',
    'placeholder' => 'Sélectionnez un projet',
    'constraints' => [
        new Assert\NotBlank(),
    ],
])
->add('user', EntityType::class, [
    'class' => User::class,
    'choice_label' => 'PrenomUser', // Use email or username
    'label' => 'Utilisateur',
    'placeholder' => 'Sélectionnez un utilisateur',
    'constraints' => [
        new Assert\NotBlank(),
    ],
])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Strategie::class,
        ]);
    }
}
