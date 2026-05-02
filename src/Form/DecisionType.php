<?php

namespace App\Form;

use App\Entity\Decision;
use App\Entity\Project;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Choice;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;

/**
 * @extends AbstractType<Decision>
 */
class DecisionType extends AbstractType
{
    /**
     * @param FormBuilderInterface<Decision|null> $builder
     * @param array<string, mixed> $options
     */
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $project = $options['project'] ?? null;

        if ($project instanceof Project) {
            $client = $project->getUser()?->getPrenomUser() . ' ' . $project->getUser()?->getNomUser();

            $builder
                ->add('projectId', HiddenType::class, [
                    'mapped' => false,
                    'data' => (string) $project->getIdProj(),
                ])
                ->add('projectTitle', TextType::class, [
                    'mapped' => false,
                    'label' => 'Projet',
                    'data' => (string) $project->getTitleProj(),
                    'disabled' => true,
                ])
                ->add('projectClient', TextType::class, [
                    'mapped' => false,
                    'label' => 'Client',
                    'data' => trim((string) $client),
                    'disabled' => true,
                ]);
        }

        $builder
            ->add('decisionTitle', ChoiceType::class, [
                'label' => 'Statut de la décision',
                'required' => true,
                'choices' => [
                    'En attente' => Decision::STATUS_PENDING,
                    'Accepté' => Decision::STATUS_ACTIVE,
                    'Refusé' => Decision::STATUS_REFUSED,
                ],
                'placeholder' => 'Choisir une décision',
                'attr' => [
                    'data-validation-label' => 'Statut de la décision',
                ],
                'help' => 'Choisissez si le projet reste en attente, est accepté ou est refusé.',
                'constraints' => [
                    new NotBlank(['message' => 'Le statut de la décision est requis.']),
                    new Choice([
                        'choices' => [
                            Decision::STATUS_PENDING,
                            Decision::STATUS_ACTIVE,
                            Decision::STATUS_REFUSED,
                        ],
                        'message' => 'Le statut de la decision selectionne est invalide.',
                    ]),
                ],
            ])
            ->add('description', TextareaType::class, [
                'label' => 'Justification de la décision',
                'required' => true,
                'attr' => [
                    'rows' => 5,
                    'placeholder' => 'Expliquez pourquoi le projet est accepté, refusé ou laissé en attente.',
                    'maxlength' => 2000,
                    'data-validation-label' => 'Justification de la décision',
                ],
                'constraints' => [
                    new NotBlank(['message' => 'La justification de la décision est requise.']),
                    new Length([
                        'min' => 10,
                        'minMessage' => 'La justification doit contenir au moins 10 caracteres.',
                        'max' => 2000,
                        'maxMessage' => 'La justification ne doit pas depasser 2000 caracteres.',
                    ]),
                ],
            ])
            ->add('decisionDate', DateType::class, [
                'label' => 'Date de décision',
                'required' => true,
                'widget' => 'single_text',
                'disabled' => true,
                'attr' => [
                    'data-validation-label' => 'Date de décision',
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
            'data_class' => Decision::class,
            'submit_label' => 'Enregistrer la décision',
            'project' => null,
        ]);

        $resolver->setAllowedTypes('submit_label', 'string');
        $resolver->setAllowedTypes('project', ['null', Project::class]);
    }
}