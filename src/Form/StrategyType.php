<?php

namespace App\Form;

use App\Entity\Project;
use App\Entity\Strategie;
use App\Entity\User;
use App\Enum\StatusStrategie;
use App\Enum\TypeStrategie;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints as Assert;

class StrategyType extends AbstractType
{
    private const MAX_DURATION_MONTHS = 600;
    private const MAX_BUDGET_TOTAL = 1000000000;
    private const MAX_GAIN_PERCENTAGE = 1000;

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('statusStrategie', ChoiceType::class, [
                'choices' => $this->enumChoices(StatusStrategie::cases()),
                'placeholder' => 'Selectionnez un statut',
                'constraints' => [
                    new Assert\NotBlank([
                        'message' => 'Le statut est obligatoire.',
                    ]),
                ],
            ])
            ->add('nomStrategie', null, [
                'constraints' => [
                    new Assert\NotBlank([
                        'message' => 'Le nom de la strategie est obligatoire.',
                    ]),
                    new Assert\Length([
                        'min' => 3,
                        'max' => 255,
                        'minMessage' => 'Le nom de la strategie doit contenir au moins {{ limit }} caracteres.',
                        'maxMessage' => 'Le nom de la strategie ne doit pas depasser {{ limit }} caracteres.',
                    ]),
                    new Assert\Regex([
                        'pattern' => "/^(?=.*\\p{L})[\\p{L}\\p{N}\\s'().-]+$/u",
                        'message' => 'Le nom de la strategie doit contenir au moins une lettre et uniquement des caracteres autorises.',
                    ]),
                    new Assert\Regex([
                        'pattern' => '/^(?!.*\s{2,}).+$/',
                        'message' => 'Le nom de la strategie ne doit pas contenir plusieurs espaces consecutifs.',
                    ]),
                ],
            ])
            ->add('type', ChoiceType::class, [
                'choices' => $this->enumChoices(TypeStrategie::cases()),
                'placeholder' => 'Selectionnez un type',
                'required' => true,
                'constraints' => [
                    new Assert\NotBlank([
                        'message' => 'Le type de strategie est obligatoire.',
                    ]),
                ],
            ])
            ->add('budgetTotal', null, [
                'required' => false,
                'constraints' => [
                    new Assert\PositiveOrZero([
                        'message' => 'Le budget total doit etre superieur ou egal a 0.',
                    ]),
                    new Assert\LessThanOrEqual([
                        'value' => self::MAX_BUDGET_TOTAL,
                        'message' => 'Le budget total ne doit pas depasser ' . self::MAX_BUDGET_TOTAL . '.',
                    ]),
                ],
            ])
            ->add('gainEstime', null, [
                'required' => false,
                'constraints' => [
                    new Assert\PositiveOrZero([
                        'message' => 'Le gain estime doit etre superieur ou egal a 0.',
                    ]),
                    new Assert\LessThanOrEqual([
                        'value' => self::MAX_GAIN_PERCENTAGE,
                        'message' => 'Le gain estime ne doit pas depasser ' . self::MAX_GAIN_PERCENTAGE . ' %.',
                    ]),
                ],
            ])
            ->add('DureeTerme', IntegerType::class, [
                'constraints' => [
                    new Assert\NotBlank([
                        'message' => 'La duree est obligatoire.',
                    ]),
                    new Assert\Range([
                        'min' => 1,
                        'max' => self::MAX_DURATION_MONTHS,
                        'notInRangeMessage' => 'La duree doit etre comprise entre {{ min }} et {{ max }} mois.',
                    ]),
                ],
            ])
            ->add('project', EntityType::class, [
                'class' => Project::class,
                'choice_label' => 'titleProj',
                'label' => 'Projet',
                'placeholder' => 'Selectionnez un projet',
                'constraints' => [
                    new Assert\NotBlank([
                        'message' => 'Le projet associe est obligatoire.',
                    ]),
                ],
            ])
            ->add('user', EntityType::class, [
                'class' => User::class,
                'choice_label' => 'PrenomUser',
                'label' => 'Utilisateur',
                'placeholder' => 'Selectionnez un utilisateur',
                'constraints' => [
                    new Assert\NotBlank([
                        'message' => 'L utilisateur responsable est obligatoire.',
                    ]),
                ],
            ]);
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
