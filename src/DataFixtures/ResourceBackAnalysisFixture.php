<?php

namespace App\DataFixtures;

use App\Entity\Cataloguefournisseur;
use App\Entity\Resource;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Bundle\FixturesBundle\FixtureGroupInterface;
use Doctrine\Persistence\ObjectManager;
use Faker\Factory;

/**
 * Fixtures demo dediees a la gestion "Resource Back".
 *
 * Objectif:
 * - fournir rapidement un jeu de donnees coherent pour la demo d'analyse,
 * - inclure des cas normaux et des cas a risque (stock critique, surstock, prix extremes),
 * - rester strictement dans le scope du module resource.
 */
class ResourceBackAnalysisFixture extends Fixture implements FixtureGroupInterface
{
    /**
     * @return array<int, string>
     */
    public static function getGroups(): array
    {
        // Permet un chargement cible:
        // php bin/console doctrine:fixtures:load --group=resource_back
        return ['resource_back'];
    }

    public function load(ObjectManager $manager): void
    {
        $resourceCount = (int) $manager->getRepository(Resource::class)->count([]);
        if ($resourceCount > 0) {
            // Protection anti ecrasement des donnees existantes.
            return;
        }

        $faker = Factory::create('fr_FR');

        $suppliers = [];
        for ($i = 0; $i < 8; ++$i) {
            $supplier = new Cataloguefournisseur();
            $supplier->setNomFr('Catalogue ' . $faker->company());
            $supplier->setFournisseur($faker->company());
            $supplier->setEmailFr($faker->companyEmail());
            $supplier->setLocalisationFr($faker->city());
            $supplier->setNumTelFr($faker->phoneNumber());
            $supplier->setQuantite($faker->numberBetween(10, 400));

            $manager->persist($supplier);
            $suppliers[] = $supplier;
        }

        $statusPool = [
            Resource::STATUS_AVAILABLE,
            Resource::STATUS_RESERVED,
            Resource::STATUS_UNAVAILABLE,
        ];

        for ($i = 0; $i < 36; ++$i) {
            $resource = new Resource();

            $generatedWords = $faker->words(2, true);
            $resourceName = is_array($generatedWords) ? implode(' ', $generatedWords) : $generatedWords;
            $resource->setNomRs(ucfirst($resourceName));

            // Cas metier varies pour alimenter l'analyse:
            // - quelques stocks tres faibles,
            // - beaucoup de stocks confortables pour tester surstock,
            // - une minorite indisponible.
            $stock = match (true) {
                $i < 6 => $faker->numberBetween(0, 3),
                $i < 20 => $faker->numberBetween(30, 140),
                default => $faker->numberBetween(8, 60),
            };

            $status = $statusPool[array_rand($statusPool)];
            if ($stock <= 0) {
                $status = Resource::STATUS_UNAVAILABLE;
            }

            // Prix avec quelques valeurs extremes pour exercer "prix anormal".
            $price = match (true) {
                $i % 11 === 0 => $faker->randomFloat(3, 18000, 60000),
                $i % 13 === 0 => $faker->randomFloat(3, 8, 35),
                default => $faker->randomFloat(3, 90, 5200),
            };

            $resource->setQuantity($stock);
            $resource->setStatus($status);
            $resource->setPrice((float) $price);
            $resource->setCataloguefournisseur($suppliers[array_rand($suppliers)]);

            $manager->persist($resource);
        }

        $manager->flush();
    }
}