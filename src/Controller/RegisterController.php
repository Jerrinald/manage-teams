<?php

declare(strict_types=1);

namespace App\Controller;

use App\Dto\Input\RegisterInput;
use App\Dto\Output\UserView;
use App\Entity\User;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Symfony\Component\ObjectMapper\ObjectMapperInterface;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;

final class RegisterController extends AbstractController
{
    #[Route('/api/register', methods: ['POST'])]
    public function __invoke(
        #[MapRequestPayload] RegisterInput $input,
        UserRepository $userRepository,
        UserPasswordHasherInterface $passwordHasher,
        ObjectMapperInterface $objectMapper,
        EntityManagerInterface $em,
    ): JsonResponse {
        if ($userRepository->findOneBy(['email' => $input->email]) !== null) {
            throw new ConflictHttpException('Cet email est déjà utilisé.');
        }

        $user = $objectMapper->map($input, User::class);
        $user->setPassword($passwordHasher->hashPassword($user, $input->password));

        $em->persist($user);
        $em->flush();

        return $this->json($objectMapper->map($user, UserView::class), 201);
    }
}
