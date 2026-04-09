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
                'choices' => $this->enumChoices(StatusStrategie::cases()),
                'constraints' => [
                    new Assert\NotBlank(),
                ],
            ])
         
           
            ->add('nomStrategie', null, [
                'constraints' => [
                    new Assert\NotBlank(),
                    new Assert\Length(['min' => 3, 'max' => 255]),
                ],
            ])
           
            ->add('type', ChoiceType::class, [
                'choices' => $this->enumChoices(TypeStrategie::cases()),
                'required' => false,
            ])
            ->add('budgetTotal', null, [
                'required' => false,
                'constraints' => [
                    new Assert\PositiveOrZero(),
                ],
            ])
            ->add('gainEstime', null, [
                'required' => false,
                'constraints' => [
                    new Assert\PositiveOrZero(),
                ],
            ])
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

    private function enumChoices(array $cases): array
    {
        $choices = [];

        foreach ($cases as $case) {
            if (!$case instanceof \BackedEnum) {
                continue;
            }

            $choices[(string) $case->value] = (string) $case->value;
        }

        return $choices;
    }
}
