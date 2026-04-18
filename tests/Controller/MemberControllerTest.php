<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\DataFixtures\AppFixtures;
use App\Entity\TeamMember;
use App\Repository\TeamMemberRepository;
use App\Repository\TeamRepository;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Lexik\Bundle\JWTAuthenticationBundle\Services\JWTTokenManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class MemberControllerTest extends WebTestCase
{
    private KernelBrowser $client;
    private JWTTokenManagerInterface $jwtManager;
    private UserRepository $userRepository;
    private TeamRepository $teamRepository;
    private TeamMemberRepository $memberRepository;

    protected function setUp(): void
    {
        $this->client = static::createClient();
        $container = static::getContainer();

        $this->jwtManager = $container->get(JWTTokenManagerInterface::class);
        $this->userRepository = $container->get(UserRepository::class);
        $this->teamRepository = $container->get(TeamRepository::class);
        $this->memberRepository = $container->get(TeamMemberRepository::class);

        $em = $container->get(EntityManagerInterface::class);
        $fixture = $container->get(AppFixtures::class);
        $schemaTool = new \Doctrine\ORM\Tools\SchemaTool($em);
        $schemaTool->dropSchema($em->getMetadataFactory()->getAllMetadata());
        $schemaTool->createSchema($em->getMetadataFactory()->getAllMetadata());
        $fixture->load($em);
    }

    private function authAs(string $email): void
    {
        $user = $this->userRepository->findOneBy(['email' => $email]);
        $token = $this->jwtManager->create($user);
        $this->client->setServerParameter('HTTP_AUTHORIZATION', 'Bearer ' . $token);
    }

    // --- GET /api/teams/{teamId}/members ---

    public function testListMembers(): void
    {
        $this->authAs('alice@example.com');
        $team = $this->teamRepository->findOneBy(['name' => 'Alpha']);

        $this->client->request('GET', '/api/teams/' . $team->getId() . '/members');
        $this->assertResponseIsSuccessful();

        $data = json_decode($this->client->getResponse()->getContent(), true);
        $this->assertCount(3, $data); // Alice, Bob, Charlie
    }

    public function testListMembersForbiddenForNonMember(): void
    {
        $this->authAs('diana@example.com');
        $team = $this->teamRepository->findOneBy(['name' => 'Alpha']);

        $this->client->request('GET', '/api/teams/' . $team->getId() . '/members');
        $this->assertResponseStatusCodeSame(403);
    }

    // --- POST /api/teams/{teamId}/members ---

    public function testAddMemberAsAdmin(): void
    {
        $this->authAs('alice@example.com');
        $team = $this->teamRepository->findOneBy(['name' => 'Alpha']);
        $diana = $this->userRepository->findOneBy(['email' => 'diana@example.com']);

        $this->client->request('POST', '/api/teams/' . $team->getId() . '/members', [], [], [
            'CONTENT_TYPE' => 'application/json',
        ], json_encode([
            'userId' => $diana->getId()->toRfc4122(),
            'role' => 'member',
        ]));

        $this->assertResponseStatusCodeSame(201);
        $data = json_decode($this->client->getResponse()->getContent(), true);
        $this->assertSame('member', $data['role']);
    }

    public function testAddMemberAsMemberForbidden(): void
    {
        $this->authAs('bob@example.com');
        $team = $this->teamRepository->findOneBy(['name' => 'Alpha']);
        $diana = $this->userRepository->findOneBy(['email' => 'diana@example.com']);

        $this->client->request('POST', '/api/teams/' . $team->getId() . '/members', [], [], [
            'CONTENT_TYPE' => 'application/json',
        ], json_encode([
            'userId' => $diana->getId()->toRfc4122(),
            'role' => 'member',
        ]));

        $this->assertResponseStatusCodeSame(403);
    }

    public function testAddDuplicateMemberConflict(): void
    {
        $this->authAs('alice@example.com');
        $team = $this->teamRepository->findOneBy(['name' => 'Alpha']);
        $bob = $this->userRepository->findOneBy(['email' => 'bob@example.com']);

        $this->client->request('POST', '/api/teams/' . $team->getId() . '/members', [], [], [
            'CONTENT_TYPE' => 'application/json',
        ], json_encode([
            'userId' => $bob->getId()->toRfc4122(),
            'role' => 'member',
        ]));

        $this->assertResponseStatusCodeSame(409);
    }

    // --- DELETE /api/teams/{teamId}/members/{memberId} ---

    public function testRemoveMemberAsAdmin(): void
    {
        $this->authAs('alice@example.com');
        $team = $this->teamRepository->findOneBy(['name' => 'Alpha']);
        $bob = $this->userRepository->findOneBy(['email' => 'bob@example.com']);
        $member = $this->memberRepository->findOneByTeamAndUser($team, $bob);

        $this->client->request('DELETE', '/api/teams/' . $team->getId() . '/members/' . $member->getId());
        $this->assertResponseStatusCodeSame(204);
    }

    public function testRemoveMemberAsGuestForbidden(): void
    {
        $this->authAs('charlie@example.com');
        $team = $this->teamRepository->findOneBy(['name' => 'Alpha']);
        $bob = $this->userRepository->findOneBy(['email' => 'bob@example.com']);
        $member = $this->memberRepository->findOneByTeamAndUser($team, $bob);

        $this->client->request('DELETE', '/api/teams/' . $team->getId() . '/members/' . $member->getId());
        $this->assertResponseStatusCodeSame(403);
    }
}
