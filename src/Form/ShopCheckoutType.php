<?php

namespace App\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Choice;
use Symfony\Component\Validator\Constraints\GreaterThan;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\LessThanOrEqual;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\Regex;

/**
 * @extends AbstractType<array<string, mixed>>
 */
class ShopCheckoutType extends AbstractType
{
    /**
     * @param FormBuilderInterface<array<string, mixed>|null> $builder
     * @param array<string, mixed> $options
     */
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $maxQuantity = max(1, (int) $options['max_quantity']);
        $projectChoices = is_array($options['project_choices']) ? $options['project_choices'] : [];

        if ((bool) $options['include_quantity']) {
            $builder->add('quantity', IntegerType::class, [
                'label' => 'Quantite',
                'required' => true,
                'attr' => [
                    'placeholder' => 'Quantite',
                    'min' => 1,
                    'max' => $maxQuantity,
                    'step' => 1,
                    'inputmode' => 'numeric',
                ],
                'constraints' => [
                    new NotBlank(['message' => 'La quantite est obligatoire.']),
                    new GreaterThan(['value' => 0, 'message' => 'La quantite doit etre superieure a 0.']),
                    new LessThanOrEqual([
                        'value' => $maxQuantity,
                        'message' => sprintf('La quantite maximale autorisee est %d.', $maxQuantity),
                    ]),
                ],
            ]);
        }

        $builder
            ->add('project_id', ChoiceType::class, [
                'label' => 'Projet cible',
                'required' => false,
                'placeholder' => 'Dernier projet ou creation auto',
                'choices' => $projectChoices,
                'invalid_message' => 'Le projet selectionne est invalide.',
            ])
            ->add('topup_provider', ChoiceType::class, [
                'label' => 'Fournisseur recharge auto',
                'required' => true,
                'choices' => [
                    'STRIPE' => 'STRIPE',
                    'FLOUCI' => 'FLOUCI',
                    'D17' => 'D17',
                ],
                'constraints' => [
                    new Choice([
                        'choices' => ['STRIPE', 'FLOUCI', 'D17'],
                        'message' => 'Le fournisseur recharge est invalide.',
                    ]),
                ],
            ])
            ->add('recipient_name', TextType::class, [
                'label' => 'Destinataire',
                'required' => true,
                'attr' => [
                    'placeholder' => 'Nom complet',
                    'maxlength' => 120,
                    'autocomplete' => 'name',
                ],
                'constraints' => [
                    new NotBlank(['message' => 'Le nom du destinataire est obligatoire.']),
                    new Length([
                        'min' => 2,
                        'max' => 120,
                        'minMessage' => 'Le nom du destinataire est trop court.',
                        'maxMessage' => 'Le nom du destinataire est trop long.',
                    ]),
                ],
            ])
            ->add('governorate', TextType::class, [
                'label' => 'Gouvernorat',
                'required' => true,
                'attr' => [
                    'placeholder' => 'Tunis',
                    'maxlength' => 80,
                    'autocomplete' => 'address-level1',
                ],
                'constraints' => [
                    new NotBlank(['message' => 'Le gouvernorat est obligatoire.']),
                    new Length([
                        'min' => 2,
                        'max' => 80,
                        'minMessage' => 'Le gouvernorat est trop court.',
                        'maxMessage' => 'Le gouvernorat est trop long.',
                    ]),
                ],
            ])
            ->add('city', TextType::class, [
                'label' => 'Ville',
                'required' => true,
                'attr' => [
                    'placeholder' => 'Ville',
                    'maxlength' => 80,
                    'autocomplete' => 'address-level2',
                ],
                'constraints' => [
                    new NotBlank(['message' => 'La ville est obligatoire.']),
                    new Length([
                        'min' => 2,
                        'max' => 80,
                        'minMessage' => 'La ville est trop courte.',
                        'maxMessage' => 'La ville est trop longue.',
                    ]),
                ],
            ])
            ->add('postal_code', TextType::class, [
                'label' => 'Code postal',
                'required' => true,
                'attr' => [
                    'placeholder' => '1000',
                    'maxlength' => 8,
                    'inputmode' => 'numeric',
                    'autocomplete' => 'postal-code',
                ],
                'constraints' => [
                    new NotBlank(['message' => 'Le code postal est obligatoire.']),
                    new Regex([
                        'pattern' => '/^\d{4,8}$/',
                        'message' => 'Le code postal doit contenir uniquement des chiffres (4 a 8).',
                    ]),
                ],
            ])
            ->add('phone', TextType::class, [
                'label' => 'Telephone',
                'required' => true,
                'attr' => [
                    'placeholder' => 'Numero principal',
                    'maxlength' => 20,
                    'inputmode' => 'tel',
                    'autocomplete' => 'tel',
                ],
                'constraints' => [
                    new NotBlank(['message' => 'Le numero de telephone principal est obligatoire.']),
                    new Regex([
                        'pattern' => '/^\+?[0-9][0-9\s\-]{6,19}$/',
                        'message' => 'Le numero de telephone principal est invalide.',
                    ]),
                ],
            ])
            ->add('phone2', TextType::class, [
                'label' => 'Telephone 2',
                'required' => false,
                'empty_data' => '',
                'attr' => [
                    'placeholder' => 'Numero secondaire',
                    'maxlength' => 20,
                    'inputmode' => 'tel',
                    'autocomplete' => 'tel-national',
                ],
                'constraints' => [
                    new Regex([
                        'pattern' => '/^$|^\+?[0-9][0-9\s\-]{6,19}$/',
                        'message' => 'Le numero de telephone secondaire est invalide.',
                    ]),
                ],
            ])
            ->add('address_line', TextType::class, [
                'label' => 'Adresse',
                'required' => true,
                'attr' => [
                    'placeholder' => 'Adresse complete',
                    'maxlength' => 180,
                    'autocomplete' => 'street-address',
                ],
                'constraints' => [
                    new NotBlank(['message' => 'L adresse complete est obligatoire.']),
                    new Length([
                        'min' => 5,
                        'max' => 180,
                        'minMessage' => 'L adresse complete est trop courte.',
                        'maxMessage' => 'L adresse complete est trop longue.',
                    ]),
                ],
            ])
            ->add('delivery_note', TextType::class, [
                'label' => 'Message livraison',
                'required' => false,
                'empty_data' => '',
                'attr' => [
                    'placeholder' => 'Ex: Appeler avant livraison',
                    'maxlength' => 255,
                ],
                'constraints' => [
                    new Length([
                        'max' => 255,
                        'maxMessage' => 'Le message de livraison ne doit pas depasser 255 caracteres.',
                    ]),
                ],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'include_quantity' => false,
            'max_quantity' => 1,
            'project_choices' => [],
            'csrf_protection' => true,
            'allow_extra_fields' => true,
        ]);

        $resolver->setAllowedTypes('include_quantity', 'bool');
        $resolver->setAllowedTypes('max_quantity', 'int');
        $resolver->setAllowedTypes('project_choices', 'array');
    }
}