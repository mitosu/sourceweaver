<?php

namespace App\Service\Workspace;

use App\Repository\WorkspaceMembershipRepository;
use Symfony\Component\Security\Core\User\UserInterface;

class GetUserWorkspaces
{
    public function __construct(
        private WorkspaceMembershipRepository $membershipRepository
    ) {}

    public function __invoke(UserInterface $user): array
    {
        $memberships = $this->membershipRepository->findBy(['user' => $user]);

        $result = [];
        foreach ($memberships as $membership) {
            $workspace = $membership->getWorkspace();
            $id = $workspace->getId();

            $result[] = [
                'name' => $workspace->getName(),
                'id' => $id,
                'routes' => [
                    ['label' => 'Overview', 'icon' => 'bi-grid', 'path' => 'workspace_overview', 'params' => ['id' => $id]],
                    ['label' => 'Documentos', 'icon' => 'bi-file-earmark-text', 'path' => 'workspace_documents', 'params' => ['id' => $id]],
                ]
            ];
        }

        return $result;
    }
}
