<?php

namespace App\DataFixtures;

use App\Entity\User;
use App\Entity\Role;
use App\Entity\Country;
use App\Entity\City;
use App\Entity\Pharmacy;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;
use Doctrine\Bundle\FixturesBundle\FixtureGroupInterface;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class UserFixtures extends Fixture implements FixtureGroupInterface
{
    private UserPasswordHasherInterface $hasher;

    public function __construct(UserPasswordHasherInterface $hasher)
    {
        $this->hasher = $hasher;
    }

    public function load(ObjectManager $manager): void
    {
        // === ROLES ===
        $roleAdmin = $manager->getRepository(Role::class)->findOneBy(['name' => 'Admin']);
        $roleApollo = $manager->getRepository(Role::class)->findOneBy(['name' => 'Apollo']);
        $roleProP   = $manager->getRepository(Role::class)->findOneBy(['name' => 'ProP']);
        $roleManP   = $manager->getRepository(Role::class)->findOneBy(['name' => 'ManP']);
        $roleEmpP   = $manager->getRepository(Role::class)->findOneBy(['name' => 'EmpP']);

        if (!$roleAdmin || !$roleApollo || !$roleProP || !$roleManP || !$roleEmpP) {
            throw new \Exception("Un ou plusieurs rôles manquent !");
        }

        // === COUNTRY ID 14 ===
        $country = $manager->getRepository(Country::class)->find(14);
        if (!$country) {
            throw new \Exception("Country ID 14 n'existe pas !");
        }

        // === CITY ID 6 (COCODY) ===
        $city = $manager->getRepository(City::class)->find(6);
        if (!$city) {
            throw new \Exception("La ville ID 6 est introuvable.");
        }

        // === OPENING DAYS ===
        $openingDays = ["Lundi","Mardi","Mercredi","Jeudi","Vendredi","Samedi"];

        /*
        |--------------------------------------------------------------------------
        | 1) USERS SYSTÈME (Admin + Apollo)
        |--------------------------------------------------------------------------
        */
        $superAdmin = new User();
        $superAdmin->setFirstname("Super");
        $superAdmin->setLastname("Admin");
        $superAdmin->setEmail("admin@apollo.ci");
        $superAdmin->setPhone("0101010101");
        $superAdmin->setRole($roleAdmin);
        $superAdmin->setCountry($country);
        $superAdmin->setCreatedAt(new \DateTimeImmutable());
        $superAdmin->setStatus(true);
        $superAdmin->setPassword(
            $this->hasher->hashPassword($superAdmin, "password123")
        );
        $manager->persist($superAdmin);

        $apollo = new User();
        $apollo->setFirstname("Apollo");
        $apollo->setLastname("System");
        $apollo->setEmail("apollo@apollo.ci");
        $apollo->setPhone("0202020202");
        $apollo->setRole($roleApollo);
        $apollo->setCountry($country);
        $apollo->setCreatedAt(new \DateTimeImmutable());
        $apollo->setStatus(true);
        $apollo->setPassword(
            $this->hasher->hashPassword($apollo, "password123")
        );
        $manager->persist($apollo);


        /*
        |--------------------------------------------------------------------------
        | 2) CRÉATION DES 40 PROPRIÉTAIRES + PHARMACIES
        |--------------------------------------------------------------------------
        */
        for ($i = 1; $i <= 40; $i++) {

            // ---- CREATE OWNER ----
            $owner = new User();
            $owner->setFirstname("Owner");
            $owner->setLastname("N°" . $i);
            $owner->setEmail("owner{$i}@pharma.ci");
            $owner->setPhone("03030303" . str_pad($i, 2, "0", STR_PAD_LEFT));
            $owner->setRole($roleProP);
            $owner->setCountry($country);
            $owner->setCreatedAt(new \DateTimeImmutable());
            $owner->setStatus(true);
            $owner->setPassword(
                $this->hasher->hashPassword($owner, "password123")
            );
            $manager->persist($owner);

            // ---- CREATE PHARMACY FOR THIS OWNER ----
            $pharmacy = new Pharmacy();
            $pharmacy->setName("Pharmacie Cocody - N°" . $i);
            $pharmacy->setAddress("Cocody, Abidjan");
            $pharmacy->setCountry($country);
            $pharmacy->setCity($city); // obligatoire
            $pharmacy->setOwner($owner);
            $pharmacy->setOpeningDay($openingDays);
            $pharmacy->setIsOnline(false);

            // relation inverse
            $owner->setPharmacy($pharmacy);

            $manager->persist($pharmacy);

            /*
            |--------------------------------------------------------------------------
            | Optionnel : Managers + Employés pour la première pharmacie uniquement
            |--------------------------------------------------------------------------
            */
            if ($i === 1) {
                // 2 managers
                for ($m = 1; $m <= 2; $m++) {
                    $managerUser = new User();
                    $managerUser->setFirstname("Manager");
                    $managerUser->setLastname("N°" . $m);
                    $managerUser->setEmail("manager{$m}@pharma.ci");
                    $managerUser->setPhone("040404040$m");
                    $managerUser->setRole($roleManP);
                    $managerUser->setCountry($country);
                    $managerUser->setPharmacy($pharmacy);
                    $managerUser->setCreatedAt(new \DateTimeImmutable());
                    $managerUser->setStatus(true);
                    $managerUser->setPassword(
                        $this->hasher->hashPassword($managerUser, "password123")
                    );
                    $manager->persist($managerUser);
                }

                // 5 employés
                for ($e = 1; $e <= 5; $e++) {
                    $employee = new User();
                    $employee->setFirstname("Employe");
                    $employee->setLastname("N°" . $e);
                    $employee->setEmail("emp{$e}@pharma.ci");
                    $employee->setPhone("060606060$e");
                    $employee->setRole($roleEmpP);
                    $employee->setCountry($country);
                    $employee->setPharmacy($pharmacy);
                    $employee->setCreatedAt(new \DateTimeImmutable());
                    $employee->setStatus(true);
                    $employee->setPassword(
                        $this->hasher->hashPassword($employee, "password123")
                    );
                    $manager->persist($employee);
                }
            }
        }

        $manager->flush();
    }

    public static function getGroups(): array
    {
        return ['users'];
    }
}
