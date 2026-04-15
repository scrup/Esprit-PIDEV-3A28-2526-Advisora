<?php

namespace App\Form;

use App\Entity\Project;
use App\Entity\Strategie;
use App\Entity\User;
use App\Enum\TypeStrategie;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints as Assert;

class StrategyType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $typeValues = $this->enumValues(TypeStrategie::cases());

        $builder
            ->add('nomStrategie', TextType::class, [
                'trim' => true,
                'required' => true,
                'constraints' => [
                    new Assert\NotBlank([
                        'message' => 'Le nom de la strategie est obligatoire.',
                        'normalizer' => 'trim',
                    ]),
                    new Assert\Length([
                        'min' => 3,
                        'minMessage' => 'Le nom de la strategie doit contenir au moins 3 caracteres.',
                        'max' => 255,
                        'maxMessage' => 'Le nom de la strategie ne doit pas depasser 255 caracteres.',
                        'normalizer' => 'trim',
                    ]),
                ],
            ])
            ->add('type', ChoiceType::class, [
                'choices' => $this->enumChoices(TypeStrategie::cases()),
                'placeholder' => 'Selectionnez un type',
                'required' => false,
                'invalid_message' => 'Le type de strategie selectionne est invalide.',
                'constraints' => [
                    new Assert\Choice([
                        'choices' => $typeValues,
                        'message' => 'Le type de strategie selectionne est invalide.',
                    ]),
                ],
            ])
            ->add('budgetTotal', NumberType::class, [
                'required' => false,
                'invalid_message' => 'Le budget total doit etre un nombre valide.',
                'constraints' => [
                    new Assert\PositiveOrZero([
                        'message' => 'Le budget total doit etre superieur ou egal a 0.',
                    ]),
                ],
            ])
            ->add('gainEstime', NumberType::class, [
                'required' => false,
                'invalid_message' => 'Le gain estime doit etre un nombre valide.',
                'constraints' => [
                    new Assert\PositiveOrZero([
                        'message' => 'Le gain estime doit etre superieur ou egal a 0.',
                    ]),
                ],
            ])
            ->add('DureeTerme', IntegerType::class, [
                'required' => true,
                'invalid_message' => 'La duree doit etre un nombre entier valide.',
                'constraints' => [
                    new Assert\NotBlank([
                        'message' => 'La duree est obligatoire.',
                    ]),
                    new Assert\GreaterThan([
                        'value' => 0,
                        'message' => 'La duree doit etre strictement superieure a 0.',
                    ]),
                ],
            ])
            ->add('project', EntityType::class, [
                'class' => Project::class,
                'choice_label' => 'titleProj',
                'label' => 'Projet',
                'placeholder' => 'Aucun projet',
                'required' => false,
                'invalid_message' => 'Le projet selectionne est invalide.',
            ])
            ->add('user', EntityType::class, [
                'class' => User::class,
                'choice_label' => static function (User $user): string {
                    $fullName = trim(sprintf('%s %s', (string) $user->getPrenomUser(), (string) $user->getNomUser()));

                    return $fullName !== '' ? $fullName : sprintf('Utilisateur #%d', (int) $user->getIdUser());
                },
                'label' => 'Utilisateur',
                'placeholder' => 'Selectionnez un utilisateur',
                'required' => true,
                'invalid_message' => 'L utilisateur responsable selectionne est invalide.',
                'constraints' => [
                    new Assert\NotNull([
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

    private function enumValues(array $cases): array
    {
        $values = [];

        foreach ($cases as $case) {
            if (!$case instanceof \BackedEnum) {
                continue;
            }

            $values[] = (string) $case->value;
        }

        return $values;
    }
}
