<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\DataFixtures\AppFixtures;
use App\Entity\Team;
use App\Entity\User;
use App\Repository\TeamRepository;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Lexik\Bundle\JWTAuthenticationBundle\Services\JWTTokenManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class TeamControllerTest extends WebTestCase
{
    private KernelBrowser $client;
    private JWTTokenManagerInterface $jwtManager;
    private UserRepository $userRepository;
    private TeamRepository $teamRepository;

    protected function setUp(): void
    {
        $this->client = static::createClient();
        $container = static::getContainer();

        $this->jwtManager = $container->get(JWTTokenManagerInterface::class);
        $this->userRepository = $container->get(UserRepository::class);
        $this->teamRepository = $container->get(TeamRepository::class);

        // Load fixtures
        $em = $container->get(EntityManagerInterface::class);
        $fixture = $container->get(AppFixtures::class);
        // Reset DB schema
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

    // --- POST /api/teams ---

    public function testCreateTeam(): void
    {
        $this->authAs('alice@example.com');

        $this->client->request('POST', '/api/teams', [], [], [
            'CONTENT_TYPE' => 'application/json',
        ], json_encode(['name' => 'Gamma']));

        $this->assertResponseStatusCodeSame(201);
        $data = json_decode($this->client->getResponse()->getContent(), true);
        $this->assertSame('Gamma', $data['name']);
        $this->assertArrayHasKey('id', $data);
        $this->assertArrayHasKey('createdAt', $data);
    }

    public function testCreateTeamUnauthenticated(): void
    {
        $this->client->request('POST', '/api/teams', [], [], [
            'CONTENT_TYPE' => 'application/json',
        ], json_encode(['name' => 'Gamma']));

        $this->assertResponseStatusCodeSame(401);
    }

    // --- GET /api/teams ---

    public function testListTeams(): void
    {
        $this->authAs('alice@example.com');

        $this->client->request('GET', '/api/teams');
        $this->assertResponseIsSuccessful();

        $data = json_decode($this->client->getResponse()->getContent(), true);
        // Alice is in Alpha only (from fixtures)
        $this->assertCount(1, $data);
        $this->assertSame('Alpha', $data[0]['name']);
    }

    public function testListTeamsBob(): void
    {
        $this->authAs('bob@example.com');

        $this->client->request('GET', '/api/teams');
        $this->assertResponseIsSuccessful();

        $data = json_decode($this->client->getResponse()->getContent(), true);
        // Bob is in Alpha + Beta
        $this->assertCount(2, $data);
    }

    // --- GET /api/teams/{id} ---

    public function testShowTeamAsGuest(): void
    {
        $this->authAs('charlie@example.com');
        $team = $this->teamRepository->findOneBy(['name' => 'Alpha']);

        $this->client->request('GET', '/api/teams/' . $team->getId());
        $this->assertResponseIsSuccessful();
    }

    public function testShowTeamForbidden(): void
    {
        $this->authAs('alice@example.com');
        $team = $this->teamRepository->findOneBy(['name' => 'Beta']);

        $this->client->request('GET', '/api/teams/' . $team->getId());
        $this->assertResponseStatusCodeSame(403);
    }

    // --- PATCH /api/teams/{id} ---

    public function testUpdateTeamAsAdmin(): void
    {
        $this->authAs('alice@example.com');
        $team = $this->teamRepository->findOneBy(['name' => 'Alpha']);

        $this->client->request('PATCH', '/api/teams/' . $team->getId(), [], [], [
            'CONTENT_TYPE' => 'application/json',
        ], json_encode(['name' => 'Alpha Updated']));

        $this->assertResponseIsSuccessful();
        $data = json_decode($this->client->getResponse()->getContent(), true);
        $this->assertSame('Alpha Updated', $data['name']);
    }

    public function testUpdateTeamAsGuestForbidden(): void
    {
        $this->authAs('charlie@example.com');
        $team = $this->teamRepository->findOneBy(['name' => 'Alpha']);

        $this->client->request('PATCH', '/api/teams/' . $team->getId(), [], [], [
            'CONTENT_TYPE' => 'application/json',
        ], json_encode(['name' => 'Hacked']));

        $this->assertResponseStatusCodeSame(403);
    }

    // --- DELETE /api/teams/{id} ---

    public function testDeleteTeamAsAdmin(): void
    {
        $this->authAs('alice@example.com');
        $team = $this->teamRepository->findOneBy(['name' => 'Alpha']);

        $this->client->request('DELETE', '/api/teams/' . $team->getId());
        $this->assertResponseStatusCodeSame(204);
    }

    public function testDeleteTeamAsMemberForbidden(): void
    {
        $this->authAs('bob@example.com');
        $team = $this->teamRepository->findOneBy(['name' => 'Alpha']);

        $this->client->request('DELETE', '/api/teams/' . $team->getId());
        $this->assertResponseStatusCodeSame(403);
    }

    public function testDeleteTeamFromOtherTeamForbidden(): void
    {
        $this->authAs('alice@example.com');
        $team = $this->teamRepository->findOneBy(['name' => 'Beta']);

        $this->client->request('DELETE', '/api/teams/' . $team->getId());
        $this->assertResponseStatusCodeSame(403);
    }
}
