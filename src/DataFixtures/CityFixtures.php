<?php
namespace App\DataFixtures;

use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;
use App\Entity\City;
use App\Entity\Country;
use Doctrine\Bundle\FixturesBundle\FixtureGroupInterface;

class CityFixtures extends Fixture implements FixtureGroupInterface
{
    public static function getGroups(): array
    {
        return ['city'];
    }

    public function load(ObjectManager $manager): void
    {
        // Récupérer le pays Côte d'Ivoire
        $country = $manager->getRepository(Country::class)->find(14);

        if (!$country) {
            throw new \Exception("Le pays avec ID 14 n'existe pas !");
        }

        $cities = [
            'Abobo','Adjamé','Attécoubé','Cocody','Koumassi','Marcory',
            'Plateau','Port-Bouët','Treichville','Yopougon','Songon',
            'Abengourou','Aboisso','Abongoua','Adiake','Adzopé','Afféry','Agboville','Agnibilékrou',
            'Akoupé','Akouré','Alepe','Ananda','Anoumaba','Anyama','Arrah','Assinie','Ayamé','Azaguié',
            'Bangolo','Bako','Baniasso','Batié','Baya','Bediala','Beoumi','Béttié','Biankouma','Bloléquin',
            'Bocanda','Bondoukou','Bongouanou','Bonon','Bouaflé','Bouaké','Bouna','Boundiali','Buyo','Dabakala',
            'Dabou','Daloa','Danané','Daoukro','Diabo','Diaké','Diawala','Didiévi','Dimbokro','Divo',
            'Djebonoua','Duekoué','Facobly','Ferkessédougou','Fresco','Gagnoa','Gbongaha','Gohitafla','Gonaté','Guéhiébli',
            'Guibéroua','Guitry','Issia','Jacqueville','Kani','Kaniasso','Katiola','Kokoumbo','Kong','Korangui',
            'Korhogo','Kouassi-Datékro','Kouassi-Kouassikro','Kouibly','Kounahiri','Kouto','Krindjabo','Lakota','Logoualé',
            'Mankono','Man','Mayo','M\'Bahiakro','Méagui','Minignan','Moapleu','Moralokro','Morondo','Niakaramadougou',
            'Niablé','Nielle','N\'Douci','Odienné','Oumé','Ouangolodougou','Rubino','Sakassou','San Pedro','Sassandra',
            'Séguelon','Sicobois','Sinfra','Sinématiali','Sipilou','Soubré','Tabou','Tafiré','Tanda','Taabo',
            'Tengrela','Tiassalé','Tiebissou','Tingréla','Toulepleu','Toulepleu-Guezon','Toumodi','Vavoua','Yakassé-Mé',
            'Yamoussoukro','Yaou','Zikisso','Zoukougbeu','Zuenoula'
        ];

        foreach ($cities as $name) {
            $city = new City();
            $city->setName($name);
            $city->setCountry($country);
            $manager->persist($city);
        }

        $manager->flush();
    }
}
