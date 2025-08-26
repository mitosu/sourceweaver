<?php

namespace App\DataFixtures;

use App\Entity\User;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class AppFixtures extends Fixture
{
    private UserPasswordHasherInterface $passwordHasher;

    public function __construct(UserPasswordHasherInterface $passwordHasher)
    {
        $this->passwordHasher = $passwordHasher;
    }

    public function load(ObjectManager $manager): void
    {
        $adminUsersData = [
            ['email' => 'admin1@example.com', 'password' => 'adminpass1', 'ref' => 'admin-user'],
            ['email' => 'admin2@example.com', 'password' => 'adminpass2', 'ref' => 'admin-user2'],
            ['email' => 'user1@example.com', 'password' => 'userpass1', 'ref' => 'regular-user'],
            ['email' => 'miguel@mail.com', 'password' => 'miguelpass1', 'ref' => 'miguel-user'],
        ];

        foreach ($adminUsersData as $userData) {
            $user = new User();
            $user->setEmail($userData['email']);
            $hashedPassword = $this->passwordHasher->hashPassword(
                $user,
                $userData['password']
            );
            $user->setPassword($hashedPassword);

            if (str_starts_with($userData['email'], 'admin')) {
                $user->setRoles(['ADMIN']); // The User entity's getRoles() adds ROLE_USER automatically
            } else {
                $user->setRoles([]); // Will get ROLE_USER by default
            }

            $manager->persist($user);
            
            // Set reference for other fixtures
            $this->setReference($userData['ref'], $user);
        }

        $manager->flush();
    }
}
