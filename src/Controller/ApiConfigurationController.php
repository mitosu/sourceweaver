<?php

namespace App\Controller;

use App\Entity\ApiConfiguration;
use App\Entity\ApiConfigurationOption;
use App\Form\ApiConfigurationType;
use App\Repository\ApiConfigurationRepository;
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
        GetUserWorkspaces $getUserWorkspaces
    ): Response {
        $configurations = $repository->findAllOrderedByName();
        $workspaces = $getUserWorkspaces($this->getUser());

        return $this->render('api_configuration/index.html.twig', [
            'configurations' => $configurations,
            'workspaces' => $workspaces
        ]);
    }


    #[Route('/{id}/edit', name: 'api_configuration_edit')]
    public function edit(
        ApiConfiguration $apiConfiguration,
        Request $request,
        EntityManagerInterface $entityManager,
        GetUserWorkspaces $getUserWorkspaces
    ): Response {
        $form = $this->createForm(ApiConfigurationType::class, $apiConfiguration);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->flush();

            $this->addFlash('success', 'Configuración de API actualizada exitosamente');
            return $this->redirectToRoute('api_configuration_index');
        }

        $workspaces = $getUserWorkspaces($this->getUser());

        return $this->render('api_configuration/edit.html.twig', [
            'form' => $form,
            'apiConfiguration' => $apiConfiguration,
            'workspaces' => $workspaces
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
            'documentation_url' => 'https://api.example.com/docs',
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

    #[Route('/create-from-modal', name: 'api_configuration_create_from_modal', methods: ['POST'])]
    public function createFromModal(Request $request, EntityManagerInterface $entityManager): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            return new JsonResponse(['error' => 'Datos JSON inválidos'], 400);
        }
        
        if (!isset($data['name'])) {
            return new JsonResponse(['error' => 'El nombre es requerido'], 400);
        }
        
        try {
            $apiConfiguration = new ApiConfiguration();
            $apiConfiguration->setName($data['name']);
            $apiConfiguration->setDescription($data['description'] ?? '');
            $apiConfiguration->setDocumentationUrl($data['documentation_url'] ?? null);
            $apiConfiguration->setCreatedBy($this->getUser());
            
            foreach ($data['options'] ?? [] as $optionData) {
                if (empty($optionData['option_name'])) {
                    continue;
                }
                
                $option = new ApiConfigurationOption();
                $option->setOptionName($optionData['option_name']);
                $option->setOptionValue($optionData['option_value'] ?? '');
                $option->setIsEncrypted($optionData['is_encrypted'] ?? false);
                $apiConfiguration->addOption($option);
            }
            
            $entityManager->persist($apiConfiguration);
            $entityManager->flush();
            
            return new JsonResponse([
                'success' => true, 
                'message' => 'API configurada exitosamente',
                'api_id' => $apiConfiguration->getId()
            ]);
            
        } catch (\Exception $e) {
            return new JsonResponse(['error' => 'Error al crear la configuración: ' . $e->getMessage()], 500);
        }
    }

    #[Route('/upload-template', name: 'api_configuration_upload_template', methods: ['POST'])]
    public function uploadTemplate(Request $request, EntityManagerInterface $entityManager): JsonResponse
    {
        $uploadedFile = $request->files->get('template');

        if (!$uploadedFile) {
            return new JsonResponse(['error' => 'No se ha subido ningún archivo'], 400);
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
            $apiConfiguration->setDocumentationUrl($data['documentation_url'] ?? null);
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