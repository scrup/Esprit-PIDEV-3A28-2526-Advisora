<?php

namespace App\Form;

use App\Entity\Investment;
use App\Entity\Project;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\GreaterThan;
use Symfony\Component\Validator\Constraints\GreaterThanOrEqual;
use Symfony\Component\Validator\Constraints\NotBlank;

class InvestmentType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        if ($options['include_project']) {
            $builder->add('project', EntityType::class, [
                'class' => Project::class,
                'choice_label' => static function (Project $project): string {
                    return sprintf(
                        '#%d - %s',
                        (int) ($project->getId() ?? 0),
                        (string) ($project->getTitle() ?? 'Projet sans titre')
                    );
                },
                'placeholder' => 'Selectionnez un projet',
                'label' => 'Projet cible',
                'required' => true,
                'choices' => $options['project_choices'],
                'attr' => [
                    'data-validation-label' => 'Projet cible',
                ],
                'constraints' => [
                    new NotBlank(['message' => 'Le projet est obligatoire.']),
                ],
            ]);
        }

        $builder
            ->add('commentaireInv', TextareaType::class, [
                'label' => 'Commentaire',
                'required' => false,
                'attr' => [
                    'rows' => 4,
                    'maxlength' => 1000,
                    'placeholder' => 'Contexte de votre investissement sur ce projet.',
                    'data-validation-label' => 'Commentaire',
                ],
            ])
            ->add('durationEstimateLabel', TextType::class, [
                'label' => 'Duree estimee',
                'required' => false,
                'attr' => [
                    'maxlength' => 100,
                    'placeholder' => 'Ex: 4 mois, 2 ans, 18 mois',
                    'data-validation-label' => 'Duree estimee',
                ],
                'help' => 'Champ libre compatible avec des durees longues. La colonne legacy `dureeInv` ne permet pas de stocker proprement des mois ou des annees.',
            ])
            ->add('bud_minInv', NumberType::class, [
                'label' => 'Montant minimum',
                'required' => true,
                'scale' => 2,
                'html5' => true,
                'attr' => [
                    'min' => 0.01,
                    'step' => '0.01',
                    'placeholder' => 'Ex: 500',
                    'data-validation-label' => 'Montant minimum',
                ],
                'constraints' => [
                    new NotBlank(['message' => 'Le montant minimum est requis.']),
                    new GreaterThan(['value' => 0, 'message' => 'Le montant minimum doit etre strictement superieur a 0.']),
                ],
            ])
            ->add('bud_maxInv', NumberType::class, [
                'label' => 'Montant maximum',
                'required' => true,
                'scale' => 2,
                'html5' => true,
                'attr' => [
                    'min' => 0.01,
                    'step' => '0.01',
                    'placeholder' => 'Ex: 2500',
                    'data-validation-label' => 'Montant maximum',
                ],
                'constraints' => [
                    new NotBlank(['message' => 'Le montant maximum est requis.']),
                    new GreaterThanOrEqual(['value' => 0.01, 'message' => 'Le montant maximum doit etre superieur ou egal a 0.01.']),
                ],
            ])
            ->add('CurrencyInv', TextType::class, [
                'label' => 'Devise',
                'required' => true,
                'attr' => [
                    'maxlength' => 20,
                    'placeholder' => 'Ex: TND',
                    'data-validation-label' => 'Devise',
                ],
                'constraints' => [
                    new NotBlank(['message' => 'La devise est obligatoire.']),
                ],
            ])
            ->add('save', SubmitType::class, [
                'label' => $options['submit_label'],
                'attr' => [
                    'class' => 'pm-btn pm-btn-primary',
                ],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Investment::class,
            'submit_label' => 'Enregistrer',
            'include_project' => false,
            'project_choices' => [],
        ]);

        $resolver->setAllowedTypes('submit_label', 'string');
        $resolver->setAllowedTypes('include_project', 'bool');
        $resolver->setAllowedTypes('project_choices', 'array');
    }
}
