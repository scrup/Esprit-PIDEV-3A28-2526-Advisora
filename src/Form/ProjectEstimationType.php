<?php

namespace App\Form;

use App\Dto\ProjectEstimationRequest;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * @extends AbstractType<ProjectEstimationRequest>
 */
final class ProjectEstimationType extends AbstractType
{
    /**
     * @param FormBuilderInterface<ProjectEstimationRequest|null> $builder
     * @param array<string, mixed> $options
     */
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('projectName', TextType::class, [
                'label' => 'Nom du projet',
                'attr' => [
                    'placeholder' => 'Ex: Atelier de fabrication, application mobile, residence locative...',
                    'maxlength' => 160,
                ],
            ])
            ->add('projectType', ChoiceType::class, [
                'label' => 'Type de projet',
                'choices' => $this->buildChoices(ProjectEstimationRequest::PROJECT_TYPES),
                'placeholder' => 'Choisir un type',
            ])
            ->add('projectDescription', TextareaType::class, [
                'label' => 'Description du projet',
                'attr' => [
                    'rows' => 5,
                    'placeholder' => 'Expliquez l activite, la valeur apportee, le public vise et la logique de lancement en Tunisie.',
                ],
            ])
            ->add('launchRegion', ChoiceType::class, [
                'label' => 'Region de lancement',
                'choices' => $this->buildChoices(ProjectEstimationRequest::REGIONS),
                'placeholder' => 'Choisir une region',
            ])
            ->add('desiredLaunchDate', DateType::class, [
                'label' => 'Date souhaitee de lancement',
                'widget' => 'single_text',
                'input' => 'datetime_immutable',
            ])
            ->add('totalBudgetDt', NumberType::class, [
                'label' => 'Budget total disponible en DT',
                'scale' => 2,
                'html5' => true,
                'attr' => [
                    'min' => 0.01,
                    'step' => '0.01',
                    'placeholder' => 'Ex: 45000',
                ],
            ])
            ->add('marketingBudgetDt', NumberType::class, [
                'label' => 'Budget marketing prevu en DT',
                'scale' => 2,
                'html5' => true,
                'attr' => [
                    'min' => 0,
                    'step' => '0.01',
                    'placeholder' => 'Ex: 8000',
                ],
            ])
            ->add('fundingSource', ChoiceType::class, [
                'label' => 'Source de financement',
                'choices' => $this->buildChoices(ProjectEstimationRequest::FUNDING_SOURCES),
                'placeholder' => 'Choisir une source',
            ])
            ->add('estimatedMonthlyRevenueDt', NumberType::class, [
                'label' => 'Revenu mensuel estime en DT',
                'scale' => 2,
                'html5' => true,
                'attr' => [
                    'min' => 0,
                    'step' => '0.01',
                    'placeholder' => 'Ex: 12000',
                ],
            ])
            ->add('estimatedProfitabilityDelayMonths', IntegerType::class, [
                'label' => 'Delai de rentabilite estime en mois',
                'attr' => [
                    'min' => 1,
                    'step' => 1,
                    'placeholder' => 'Ex: 18',
                ],
            ])
            ->add('teamSize', IntegerType::class, [
                'label' => 'Nombre de personnes impliquees au lancement',
                'attr' => [
                    'min' => 1,
                    'step' => 1,
                    'placeholder' => 'Ex: 4',
                ],
            ])
            ->add('founderExperienceYears', NumberType::class, [
                'label' => 'Experience pertinente sur ce type de projet',
                'scale' => 1,
                'html5' => true,
                'attr' => [
                    'min' => 0,
                    'step' => '0.5',
                    'placeholder' => 'Ex: 6',
                ],
            ])
            ->add('teamKeySkills', TextareaType::class, [
                'label' => 'Competences et ressources cles mobilisables',
                'attr' => [
                    'rows' => 4,
                    'placeholder' => 'Ex: gestion de chantier, reseau commercial, production, logistique, digital, achats...',
                ],
            ])
            ->add('alreadyLaunchedInTunisia', ChoiceType::class, [
                'label' => 'Avez-vous deja pilote un projet similaire en Tunisie ?',
                'choices' => [
                    'Oui' => true,
                    'Non' => false,
                ],
                'expanded' => true,
            ])
            ->add('targetMarket', ChoiceType::class, [
                'label' => 'Clientele ou beneficiaires principaux',
                'choices' => $this->buildChoices(ProjectEstimationRequest::TARGET_MARKETS),
                'placeholder' => 'Choisir une cible',
            ])
            ->add('directCompetitorsTunisia', IntegerType::class, [
                'label' => 'Nombre d alternatives ou concurrents en Tunisie',
                'attr' => [
                    'min' => 0,
                    'step' => 1,
                    'placeholder' => 'Ex: 7',
                ],
            ])
            ->add('competitiveAdvantage', TextareaType::class, [
                'label' => 'Proposition de valeur et differenciation',
                'attr' => [
                    'rows' => 4,
                    'placeholder' => 'Expliquez pourquoi le projet sera choisi plutot qu une autre solution deja presente.',
                ],
            ])
            ->add('tunisianMarketStudyStatus', ChoiceType::class, [
                'label' => 'Validation terrain ou etude locale deja realisee ?',
                'choices' => [
                    'Oui, de facon solide' => 'Oui',
                    'Pas encore' => 'Non',
                    'Partiellement / en cours' => 'En cours',
                ],
                'expanded' => true,
            ])
            ->add('exportTarget', ChoiceType::class, [
                'label' => 'Le projet vise-t-il aussi des clients hors Tunisie ?',
                'choices' => [
                    'Oui' => true,
                    'Non' => false,
                ],
                'expanded' => true,
            ])
            ->add('mvpStatus', ChoiceType::class, [
                'label' => 'Niveau d avancement actuel du projet',
                'choices' => [
                    'Pret pour un test, une vente ou une mise en service' => 'Oui',
                    'En preparation ou en cours de finalisation' => 'En cours',
                    'Encore au stade d idee ou de cadrage' => 'Non',
                ],
                'expanded' => true,
            ])
            ->add('mainTechnology', TextType::class, [
                'label' => 'Moyen principal de production, savoir-faire ou technologie',
                'attr' => [
                    'placeholder' => 'Ex: atelier, flotte logistique, plateforme web, savoir-faire artisanal, equipement...',
                    'maxlength' => 160,
                ],
            ])
            ->add('plannedLegalStatus', ChoiceType::class, [
                'label' => 'Structuration juridique ou administrative prevue',
                'choices' => $this->buildChoices(ProjectEstimationRequest::LEGAL_STATUSES),
                'placeholder' => 'Choisir un statut',
            ])
            ->add('needsCertification', ChoiceType::class, [
                'label' => 'Autorisation, agrement ou conformite particuliere necessaire ?',
                'choices' => [
                    'Oui' => 'Oui',
                    'Non' => 'Non',
                    'Je ne sais pas encore' => 'Je ne sais pas',
                ],
                'expanded' => true,
            ])
            ->add('tunisianSpecificRisks', TextareaType::class, [
                'label' => 'Contraintes et risques de mise en oeuvre en Tunisie',
                'attr' => [
                    'rows' => 4,
                    'placeholder' => 'Ex: approvisionnement, saisonnalite, autorisations, recrutement, tresorerie, logistique...',
                ],
            ])
            ->add('save', SubmitType::class, [
                'label' => 'Lancer l analyse du projet',
                'attr' => [
                    'class' => 'btn estimation-submit-btn',
                ],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => ProjectEstimationRequest::class,
            'csrf_token_id' => 'project_estimation',
        ]);
    }

    /**
     * @param string[] $values
     *
     * @return array<string, string>
     */
    private function buildChoices(array $values): array
    {
        return array_combine($values, $values) ?: [];
    }
}