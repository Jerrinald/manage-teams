<?php

declare(strict_types=1);

namespace App\DataFixtures;

use App\Entity\Team;
use App\Entity\TeamMember;
use App\Entity\User;
use App\Enum\TeamRole;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class AppFixtures extends Fixture
{
    public function __construct(
        private readonly UserPasswordHasherInterface $hasher,
    ) {}

    public function load(ObjectManager $manager): void
    {
        // --- Users ---
        $alice = $this->createUser('alice@example.com', 'password', $manager);
        $bob = $this->createUser('bob@example.com', 'password', $manager);
        $charlie = $this->createUser('charlie@example.com', 'password', $manager);
        $diana = $this->createUser('diana@example.com', 'password', $manager);

        // --- Teams ---
        $teamAlpha = new Team('Alpha');
        $teamBeta = new Team('Beta');
        $manager->persist($teamAlpha);
        $manager->persist($teamBeta);

        // --- Memberships ---
        // Alpha : Alice=Admin, Bob=Member, Charlie=Guest
        $manager->persist(new TeamMember($teamAlpha, $alice, TeamRole::Admin));
        $manager->persist(new TeamMember($teamAlpha, $bob, TeamRole::Member));
        $manager->persist(new TeamMember($teamAlpha, $charlie, TeamRole::Guest));

        // Beta : Bob=Admin, Diana=Member
        // Alice et Charlie ne sont PAS dans Beta → doivent recevoir 403
        $manager->persist(new TeamMember($teamBeta, $bob, TeamRole::Admin));
        $manager->persist(new TeamMember($teamBeta, $diana, TeamRole::Member));

        $manager->flush();
    }

    private function createUser(string $email, string $plainPassword, ObjectManager $manager): User
    {
        $user = new User();
        $user->setEmail($email);
        $user->setPassword($this->hasher->hashPassword($user, $plainPassword));
        $manager->persist($user);

        return $user;
    }
}
