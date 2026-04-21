<?php

namespace App\Form;

use App\Entity\Cataloguefournisseur;
use App\Entity\Resource;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\UrlType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\GreaterThanOrEqual;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\Choice;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\NotNull;
use Symfony\Component\Validator\Constraints\Type;
use Symfony\Component\Validator\Constraints\Url;

class ResourceType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('nomRs', TextType::class, [
                'label' => 'Nom de la ressource',
                'required' => true,
                'attr' => [
                    'placeholder' => 'Ex: Ordinateur portable HP',
                    'maxlength' => 160,
                    'data-validation-label' => 'Nom de la ressource',
                ],
                'constraints' => [
                    new NotBlank(['message' => 'Le nom de la ressource est requis.']),
                    new Length(['max' => 160, 'maxMessage' => 'Le nom de la ressource ne doit pas depasser 160 caracteres.']),
                ],
            ])
            ->add('cataloguefournisseur', EntityType::class, [
                'class' => Cataloguefournisseur::class,
                'label' => 'Fournisseur',
                'required' => true,
                'placeholder' => 'Selectionner un fournisseur',
                'choice_label' => static function (Cataloguefournisseur $supplier): string {
                    $mainLabel = trim((string) ($supplier->getFournisseur() ?: $supplier->getNomFr()));

                    if ($supplier->getFournisseur() && $supplier->getNomFr() && $supplier->getFournisseur() !== $supplier->getNomFr()) {
                        return sprintf('%s (%s)', $supplier->getFournisseur(), $supplier->getNomFr());
                    }

                    return $mainLabel !== '' ? $mainLabel : 'Fournisseur #' . $supplier->getIdFr();
                },
                'attr' => [
                    'data-validation-label' => 'Fournisseur',
                ],
                'help' => 'Le fournisseur est obligatoire pour enregistrer la ressource dans le catalogue.',
                'constraints' => [
                    new NotNull(['message' => 'Le fournisseur selectionne est requis.']),
                ],
            ])
            ->add('prixRs', NumberType::class, [
                'label' => 'Prix unitaire',
                'required' => true,
                'scale' => 2,
                'html5' => true,
                'attr' => [
                    'placeholder' => 'Ex: 1500',
                    'min' => 0,
                    'step' => '0.01',
                    'data-validation-label' => 'Prix unitaire',
                ],
                'help' => 'Le prix doit etre superieur ou egal a 0.',
                'constraints' => [
                    new NotBlank(['message' => 'Le prix unitaire est requis.']),
                    new GreaterThanOrEqual(['value' => 0, 'message' => 'Le prix unitaire doit etre superieur ou egal a 0.']),
                ],
            ])
            ->add('QuantiteRs', IntegerType::class, [
                'label' => 'Stock total',
                'required' => true,
                'attr' => [
                    'placeholder' => 'Ex: 12',
                    'min' => 0,
                    'data-validation-label' => 'Stock total',
                    'data-validation-integer' => 'true',
                ],
                'help' => 'Si la quantite est egale a 0, la ressource devient indisponible.',
                'constraints' => [
                    new NotBlank(['message' => 'Le stock total est requis.']),
                    new Type(['type' => 'integer', 'message' => 'Le stock total doit etre un entier valide.']),
                    new GreaterThanOrEqual(['value' => 0, 'message' => 'La quantite doit etre positive ou nulle.']),
                ],
            ])
            ->add('availabilityStatusRs', ChoiceType::class, [
                'label' => 'Statut de disponibilite',
                'required' => true,
                'choices' => array_flip($options['status_choices']),
                'placeholder' => 'Choisir un statut',
                'attr' => [
                    'data-validation-label' => 'Statut de disponibilite',
                    'data-validation-required-message' => 'Le statut de disponibilite est requis.',
                    'data-validation-choices' => implode(',', array_keys($options['status_choices'])),
                    'data-validation-choice-message' => 'Le statut de disponibilite selectionne est invalide.',
                ],
                'help' => 'Statuts autorises: AVAILABLE, RESERVED, UNAVAILABLE.',
                'constraints' => [
                    new NotBlank(['message' => 'Le statut de disponibilite est requis.']),
                    new Choice([
                        'choices' => array_keys($options['status_choices']),
                        'message' => 'Le statut de disponibilite selectionne est invalide.',
                    ]),
                ],
            ])
            ->add('imageUrlRs', UrlType::class, [
                'label' => 'Image URL',
                'required' => false,
                'default_protocol' => 'https',
                'attr' => [
                    'placeholder' => 'https://...',
                    'maxlength' => 255,
                    'data-validation-label' => 'Image URL',
                ],
                'help' => 'Champ optionnel pour illustrer la ressource dans le catalogue web.',
                'constraints' => [
                    new Length(['max' => 255, 'maxMessage' => 'L URL de l image ne doit pas depasser 255 caracteres.']),
                    new Url(['message' => 'L URL de l image doit etre valide.']),
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
            'data_class' => Resource::class,
            'submit_label' => 'Enregistrer',
            'status_choices' => Resource::STATUSES,
        ]);

        $resolver->setAllowedTypes('submit_label', 'string');
        $resolver->setAllowedTypes('status_choices', 'array');
    }
}
