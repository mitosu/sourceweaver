<?php

namespace App\Controller;

use App\Entity\ApiConfiguration;
use App\Entity\ApiConfigurationOption;
use App\Entity\Workspace;
use App\Form\ApiConfigurationType;
use App\Repository\ApiConfigurationRepository;
use App\Repository\WorkspaceRepository;
use App\Repository\WorkspaceMembershipRepository;
use App\Service\Workspace\GetUserWorkspaces;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api-configuration')]
#[IsGranted('ROLE_USER')]
final class ApiConfigurationController extends AbstractController
{
    #[Route('/', name: 'api_configuration_index')]
    public function index(
        ApiConfigurationRepository $repository,
        WorkspaceMembershipRepository $membershipRepository,
        GetUserWorkspaces $getUserWorkspaces
    ): Response {
        $user = $this->getUser();
        $workspaces = $getUserWorkspaces($user);
        $memberships = $membershipRepository->findBy(['user' => $user]);
        $configurations = [];
        
        foreach ($memberships as $membership) {
            $workspace = $membership->getWorkspace();
            $workspaceConfigs = $repository->findByWorkspace($workspace);
            if (!empty($workspaceConfigs)) {
                $configurations[$workspace->getName()] = $workspaceConfigs;
            }
        }

        return $this->render('api_configuration/index.html.twig', [
            'configurations' => $configurations,
            'workspaces' => $workspaces
        ]);
    }

    #[Route('/new/{workspaceId}', name: 'api_configuration_new')]
    public function new(
        string $workspaceId,
        Request $request,
        EntityManagerInterface $entityManager,
        WorkspaceRepository $workspaceRepository
    ): Response {
        $workspace = $workspaceRepository->find($workspaceId);
        if (!$workspace) {
            throw $this->createNotFoundException('Workspace not found');
        }

        $apiConfiguration = new ApiConfiguration();
        $apiConfiguration->setWorkspace($workspace);
        $apiConfiguration->setCreatedBy($this->getUser());

        $form = $this->createForm(ApiConfigurationType::class, $apiConfiguration);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->persist($apiConfiguration);
            $entityManager->flush();

            $this->addFlash('success', 'Configuración de API creada exitosamente');
            return $this->redirectToRoute('api_configuration_index');
        }

        return $this->render('api_configuration/new.html.twig', [
            'form' => $form,
            'workspace' => $workspace
        ]);
    }

    #[Route('/{id}/edit', name: 'api_configuration_edit')]
    public function edit(
        ApiConfiguration $apiConfiguration,
        Request $request,
        EntityManagerInterface $entityManager
    ): Response {
        $form = $this->createForm(ApiConfigurationType::class, $apiConfiguration);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->flush();

            $this->addFlash('success', 'Configuración de API actualizada exitosamente');
            return $this->redirectToRoute('api_configuration_index');
        }

        return $this->render('api_configuration/edit.html.twig', [
            'form' => $form,
            'apiConfiguration' => $apiConfiguration
        ]);
    }

    #[Route('/{id}/toggle', name: 'api_configuration_toggle', methods: ['POST'])]
    public function toggle(
        ApiConfiguration $apiConfiguration,
        EntityManagerInterface $entityManager
    ): JsonResponse {
        $apiConfiguration->setIsActive(!$apiConfiguration->isActive());
        $entityManager->flush();

        return new JsonResponse([
            'success' => true,
            'isActive' => $apiConfiguration->isActive()
        ]);
    }

    #[Route('/{id}/delete', name: 'api_configuration_delete', methods: ['POST'])]
    public function delete(
        ApiConfiguration $apiConfiguration,
        EntityManagerInterface $entityManager
    ): Response {
        $entityManager->remove($apiConfiguration);
        $entityManager->flush();

        $this->addFlash('success', 'Configuración de API eliminada exitosamente');
        return $this->redirectToRoute('api_configuration_index');
    }

    #[Route('/download-template', name: 'api_configuration_download_template')]
    public function downloadTemplate(): JsonResponse
    {
        $template = [
            'name' => 'API Name Example',
            'description' => 'Description of the API and its purpose',
            'options' => [
                [
                    'option_name' => 'API_KEY',
                    'option_value' => 'your_api_key_here',
                    'is_encrypted' => true
                ],
                [
                    'option_name' => 'BASE_URL',
                    'option_value' => 'https://api.example.com',
                    'is_encrypted' => false
                ],
                [
                    'option_name' => 'TIMEOUT',
                    'option_value' => '30',
                    'is_encrypted' => false
                ]
            ]
        ];

        $response = new JsonResponse($template);
        $response->headers->set('Content-Disposition', 'attachment; filename="api_configuration_template.json"');
        
        return $response;
    }

    #[Route('/upload-template', name: 'api_configuration_upload_template', methods: ['POST'])]
    public function uploadTemplate(
        Request $request,
        WorkspaceRepository $workspaceRepository,
        EntityManagerInterface $entityManager
    ): JsonResponse {
        $uploadedFile = $request->files->get('template');
        $workspaceId = $request->request->get('workspace_id');

        if (!$uploadedFile) {
            return new JsonResponse(['error' => 'No se ha subido ningún archivo'], 400);
        }

        if (!$workspaceId) {
            return new JsonResponse(['error' => 'Workspace ID requerido'], 400);
        }

        $workspace = $workspaceRepository->find($workspaceId);
        if (!$workspace) {
            return new JsonResponse(['error' => 'Workspace no encontrado'], 404);
        }

        $content = file_get_contents($uploadedFile->getPathname());
        $data = json_decode($content, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            return new JsonResponse(['error' => 'El archivo no es un JSON válido'], 400);
        }

        try {
            $apiConfiguration = new ApiConfiguration();
            $apiConfiguration->setName($data['name'] ?? 'API Configuration');
            $apiConfiguration->setDescription($data['description'] ?? '');
            $apiConfiguration->setWorkspace($workspace);
            $apiConfiguration->setCreatedBy($this->getUser());

            foreach ($data['options'] ?? [] as $optionData) {
                $option = new ApiConfigurationOption();
                $option->setOptionName($optionData['option_name'] ?? '');
                $option->setOptionValue($optionData['option_value'] ?? '');
                $option->setIsEncrypted($optionData['is_encrypted'] ?? false);
                $apiConfiguration->addOption($option);
            }

            $entityManager->persist($apiConfiguration);
            $entityManager->flush();

            return new JsonResponse(['success' => true, 'message' => 'Configuración importada exitosamente']);
        } catch (\Exception $e) {
            return new JsonResponse(['error' => 'Error al procesar la configuración: ' . $e->getMessage()], 500);
        }
    }
}