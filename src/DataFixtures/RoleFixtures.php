<?php

namespace App\DataFixtures;

use App\Entity\Role;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;
use Doctrine\Bundle\FixturesBundle\FixtureGroupInterface;

class RoleFixtures extends Fixture implements FixtureGroupInterface
{
    public function load(ObjectManager $manager): void
    {
        $roles = ['User', 'EmpP', 'ManP', 'ProP', 'Apollo', 'Admin'];

        foreach ($roles as $roleName) {
            $role = new Role();
            $role->setName($roleName);
            $manager->persist($role);
        }

        $manager->flush();
    }

    // Définir un groupe pour pouvoir charger uniquement cette fixture
    public static function getGroups(): array
    {
        return ['role'];
    }
}
//php bin/console doctrine:fixtures:load --group=role
