<?php

declare(strict_types=1);

namespace App\Security\Resolver;

use App\Entity\TeamMember;
use App\Repository\TeamMemberRepository;
use App\Security\Attribute\TeamScopedMember;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Controller\ValueResolverInterface;
use Symfony\Component\HttpKernel\ControllerMetadata\ArgumentMetadata;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Uid\Uuid;

final class TeamScopedMemberResolver implements ValueResolverInterface
{
    public function __construct(
        private readonly TeamMemberRepository $memberRepository,
    ) {}

    public function resolve(Request $request, ArgumentMetadata $argument): iterable
    {
        $attributes = $argument->getAttributesOfType(TeamScopedMember::class);
        if ($attributes === []) {
            return [];
        }
        if ($argument->getType() !== TeamMember::class) {
            return [];
        }

        $attribute = $attributes[0];
        $teamId = $request->attributes->get($attribute->teamParam);
        $memberId = $request->attributes->get($attribute->memberParam);

        if ($teamId === null || $memberId === null) {
            throw new NotFoundHttpException('Member not found in this team.');
        }

        $member = $this->memberRepository->find(Uuid::fromString((string) $memberId));
        if ($member === null || (string) $member->getTeam()->getId() !== (string) $teamId) {
            throw new NotFoundHttpException('Member not found in this team.');
        }

        return [$member];
    }
}
