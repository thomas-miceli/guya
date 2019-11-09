<?php

namespace App\DataFixtures;

use App\Entity\User;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Common\Persistence\ObjectManager;
use Symfony\Component\Security\Core\Encoder\UserPasswordEncoderInterface;

class AppFixtures extends Fixture
{

    private $passwordEncoder;

    public function __construct(UserPasswordEncoderInterface $passwordEncoder)
    {
        $this->passwordEncoder = $passwordEncoder;
    }

    public function load(ObjectManager $manager)
    {
        // $product = new Product();
        // $manager->persist($product);

        $user = new User();
        $user->setUsername('thomas');
        $user->setPassword($this->passwordEncoder->encodePassword($user,'thomas'));
        $manager->persist($user);

        $user = new User();
        $user->setUsername('andrea');
        $user->setPassword($this->passwordEncoder->encodePassword($user,'andrea'));
        $manager->persist($user);
        $manager->flush();
    }
}
