<?php

namespace App\DataFixtures;

use App\Entity\Pharmacy;
use App\Entity\User;
use App\Entity\City;
use App\Entity\Country;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;
use Doctrine\Bundle\FixturesBundle\FixtureGroupInterface;

class PharmacyFixtures extends Fixture implements FixtureGroupInterface
{
    public function load(ObjectManager $manager): void
    {
        // Country = Côte d'Ivoire
        $country = $manager->getRepository(Country::class)->find(14);
        if (!$country) {
            throw new \Exception("Country ID 14 introuvable.");
        }

        // City = Cocody
        $city = $manager->getRepository(City::class)->find(6);
        if (!$city) {
            throw new \Exception("City ID 6 introuvable.");
        }

        // Récupérer les utilisateurs par rôle
        $owners = $manager->getRepository(User::class)->findBy(['role' => 4]); // ProP
        $managers = $manager->getRepository(User::class)->findBy(['role' => 5]); // ManP
        $employees = $manager->getRepository(User::class)->findBy(['role' => 6]); // EmpP

        if (count($owners) === 0) {
            throw new \Exception("Aucun user ProP trouvé pour être owner !");
        }

        $openingDays = ['Lundi','Mardi','Mercredi','Jeudi','Vendredi','Samedi'];

        $pharmaciesNames = [
            'Pharmacie Sainte Bernadette',
            'Pharmacie Saint Viateur',
            'Pharmacie du Lycée Technique',
            'Pharmacie du Lycée Classique',
            'Pharmacie Royale Angré',
            'Pharmacie de la Riviera Palmeraie',
            'Pharmacie de l’Indénié',
            'Pharmacie de la Riviera Bonoumin',
            'Pharmacie des Jardins',
            'Pharmacie la Pépinière',
            'Pharmacie de la Riviera 2',
            'Pharmacie M’Pouto',
            'Pharmacie Angré 8e Tranche',
            'Pharmacie Angré Mahou',
            'Pharmacie Angré Ville',
            'Pharmacie des II Plateaux',
            'Pharmacie Vallon',
            'Pharmacie Danga',
            'Pharmacie Riviera Golf',
            'Pharmacie 2 Plateaux Aghien',
            'Pharmacie Riviera Faya',
            'Pharmacie Riviera M’Badon',
            'Pharmacie Akwaba',
            'Pharmacie du CHU de Cocody',
            'Pharmacie 7e Tranche',
            'Pharmacie Orchidee',
            'Pharmacie Kennedy',
            'Pharmacie Saint Jacques',
            'Pharmacie Saint Moïse',
            'Pharmacie Sainte Famille',
            'Pharmacie Montée',
            'Pharmacie Les Rosiers',
            'Pharmacie Cap Nord',
            'Pharmacie Orange',
            'Pharmacie Terminus 81',
            'Pharmacie Hôtel Communal',
            'Pharmacie 2 Plateaux Vallons',
            'Pharmacie Vallon Sud',
            'Pharmacie Riviera Attoban',
            'Pharmacie Vallon Angré',
        ];

        $ownerIndex = 0;

        foreach ($pharmaciesNames as $name) {
            // Choisir un owner unique
            $owner = $owners[$ownerIndex] ?? null;
            if (!$owner) {
                throw new \Exception("Pas assez d'owners pour assigner toutes les pharmacies !");
            }

            $pharmacy = new Pharmacy();
            $pharmacy->setName($name);
            $pharmacy->setAddress("Cocody, Abidjan");
            $pharmacy->setCountry($country);
            $pharmacy->setCity($city);
            $pharmacy->setOpeningDay($openingDays);
            $pharmacy->setIsOnline(false);
            $pharmacy->setOwner($owner);

            // Relation inverse
            $owner->setPharmacy($pharmacy);

            // Assign 1-2 managers si disponibles
            $assignedManagers = array_splice($managers, 0, 2);
            foreach ($assignedManagers as $managerUser) {
                $managerUser->setPharmacy($pharmacy);
            }

            // Assign 2-3 employés si disponibles
            $assignedEmployees = array_splice($employees, 0, 3);
            foreach ($assignedEmployees as $employeeUser) {
                $employeeUser->setPharmacy($pharmacy);
            }

            $manager->persist($pharmacy);
            $ownerIndex++;
        }

        $manager->flush();
    }

    public static function getGroups(): array
    {
        return ['pharmacy'];
    }
}
