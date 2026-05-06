<?php

namespace App\DataFixtures;

use App\Entity\Country;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;
use Doctrine\Bundle\FixturesBundle\FixtureGroupInterface;

class CountryFixtures extends Fixture implements FixtureGroupInterface
{
    public function load(ObjectManager $manager): void
    {
        $countries = [
            ['name' => 'Afrique du Sud', 'iso_code' => 'ZA'],
            ['name' => 'Algérie', 'iso_code' => 'DZ'],
            ['name' => 'Angola', 'iso_code' => 'AO'],
            ['name' => 'Bénin', 'iso_code' => 'BJ'],
            ['name' => 'Botswana', 'iso_code' => 'BW'],
            ['name' => 'Burkina Faso', 'iso_code' => 'BF'],
            ['name' => 'Burundi', 'iso_code' => 'BI'],
            ['name' => 'Cabo Verde', 'iso_code' => 'CV'],
            ['name' => 'Cameroun', 'iso_code' => 'CM'],
            ['name' => 'Centrafrique', 'iso_code' => 'CF'],
            ['name' => 'Comores', 'iso_code' => 'KM'],
            ['name' => 'Congo (Brazzaville)', 'iso_code' => 'CG'],
            ['name' => 'Congo (Kinshasa)', 'iso_code' => 'CD'],
            ['name' => 'Côte d\'Ivoire', 'iso_code' => 'CI'],
            ['name' => 'Djibouti', 'iso_code' => 'DJ'],
            ['name' => 'Égypte', 'iso_code' => 'EG'],
            ['name' => 'Érythrée', 'iso_code' => 'ER'],
            ['name' => 'Eswatini', 'iso_code' => 'SZ'],
            ['name' => 'Éthiopie', 'iso_code' => 'ET'],
            ['name' => 'Gabon', 'iso_code' => 'GA'],
            ['name' => 'Gambie', 'iso_code' => 'GM'],
            ['name' => 'Ghana', 'iso_code' => 'GH'],
            ['name' => 'Guinée', 'iso_code' => 'GN'],
            ['name' => 'Guinée-Bissau', 'iso_code' => 'GW'],
            ['name' => 'Guinée équatoriale', 'iso_code' => 'GQ'],
            ['name' => 'Kenya', 'iso_code' => 'KE'],
            ['name' => 'Lesotho', 'iso_code' => 'LS'],
            ['name' => 'Liberia', 'iso_code' => 'LR'],
            ['name' => 'Libye', 'iso_code' => 'LY'],
            ['name' => 'Madagascar', 'iso_code' => 'MG'],
            ['name' => 'Malawi', 'iso_code' => 'MW'],
            ['name' => 'Mali', 'iso_code' => 'ML'],
            ['name' => 'Maroc', 'iso_code' => 'MA'],
            ['name' => 'Maurice', 'iso_code' => 'MU'],
            ['name' => 'Mauritanie', 'iso_code' => 'MR'],
            ['name' => 'Mozambique', 'iso_code' => 'MZ'],
            ['name' => 'Namibie', 'iso_code' => 'NA'],
            ['name' => 'Niger', 'iso_code' => 'NE'],
            ['name' => 'Nigeria', 'iso_code' => 'NG'],
            ['name' => 'Ouganda', 'iso_code' => 'UG'],
            ['name' => 'Rwanda', 'iso_code' => 'RW'],
            ['name' => 'Sao Tomé-et-Principe', 'iso_code' => 'ST'],
            ['name' => 'Sénégal', 'iso_code' => 'SN'],
            ['name' => 'Seychelles', 'iso_code' => 'SC'],
            ['name' => 'Sierra Leone', 'iso_code' => 'SL'],
            ['name' => 'Somalie', 'iso_code' => 'SO'],
            ['name' => 'Soudan', 'iso_code' => 'SD'],
            ['name' => 'Soudan du Sud', 'iso_code' => 'SS'],
            ['name' => 'Tanzanie', 'iso_code' => 'TZ'],
            ['name' => 'Tchad', 'iso_code' => 'TD'],
            ['name' => 'Togo', 'iso_code' => 'TG'],
            ['name' => 'Tunisie', 'iso_code' => 'TN'],
            ['name' => 'Zambie', 'iso_code' => 'ZM'],
            ['name' => 'Zimbabwe', 'iso_code' => 'ZW'],
        ];

        foreach ($countries as $c) {
            $country = new Country();
            $country->setName($c['name']);
            $country->setIsoCode($c['iso_code']); // Assure-toi que ta propriété existe dans l'entité Country
            $country->setStatus('active'); // ou '1' selon ton schéma
            $manager->persist($country);
        }

        $manager->flush();
    }

    public static function getGroups(): array
    {
        return ['country'];
    }
}


//php bin/console doctrine:fixtures:load --group=country
//php bin/console doctrine:fixtures:load --group=country --append

