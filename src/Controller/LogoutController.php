<?php
namespace App\Controller;

use App\Entity\BlockedToken;
use Doctrine\ORM\EntityManagerInterface;
use Gesdinet\JWTRefreshTokenBundle\Model\RefreshTokenManagerInterface;
use Lexik\Bundle\JWTAuthenticationBundle\Services\JWTTokenManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;
use App\Entity\User;

final class LogoutController extends AbstractController
{
    #[Route('/api/logout', methods: ['POST'])]
    public function __invoke(
        Request $request,
        #[CurrentUser] User $user,
        JWTTokenManagerInterface $jwtManager,
        EntityManagerInterface $em,
        RefreshTokenManagerInterface $refreshManager,
    ): JsonResponse {
        // 1. blocklist l'access token courant
        $token = $this->container->get('security.token_storage')->getToken();
        $payload = $jwtManager->decode($token);
        $em->persist(new BlockedToken(
            $payload['jti'],
            (new \DateTimeImmutable())->setTimestamp($payload['exp'])
        ));

        // 2. invalide le refresh token envoyé dans le body (ou cookie)
        $refreshValue = $request->toArray()['refresh_token'] ?? null;
        if ($refreshValue && $rt = $refreshManager->get($refreshValue)) {
            $refreshManager->delete($rt);
        }

        $em->flush();
        return new JsonResponse(null, 204);
    }
}