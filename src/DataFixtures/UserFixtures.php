<?php

namespace App\DataFixtures;

use App\Entity\User;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class UserFixtures extends Fixture
{
    private UserPasswordHasherInterface $passwordHasher;

    public function __construct(UserPasswordHasherInterface $passwordHasher)
    {
        $this->passwordHasher = $passwordHasher;
    }
    public const USER_REFERENCE = 'users';

    public function load(ObjectManager $manager): void
    {
        // Create Admin User
        $admin = new User();
        $admin->setUsername('admin');
        $admin->setEmail('a@a.com');
        $admin->setPassword($this->passwordHasher->hashPassword($admin, 'admin123'));
        $admin->setRoles(['ROLE_ADMIN']);
        $manager->persist($admin);

        // Create Regular User 1
        $user1 = new User();
        $user1->setUsername('Martin');
        $user1->setEmail('m@m.com');
        $user1->setPassword($this->passwordHasher->hashPassword($user1, 'admin123'));
        $user1->setRoles(['ROLE_ADMIN']);
        $manager->persist($user1);

        // Create Regular User 2
        $user2 = new User();
        $user2->setUsername('Hest Test');
        $user2->setEmail('h@h.com');
        $user2->setPassword($this->passwordHasher->hashPassword($user2, 'user123'));
        $user2->setRoles(['ROLE_USER']);
        $manager->persist($user2);

        // Save users to the database
        $manager->flush();

        $this->addReference(self::USER_REFERENCE, $user2);
    }
}
