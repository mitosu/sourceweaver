<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class HomeController extends AbstractController
{
    #[Route('/', name: 'home')]
    public function index(): Response
    {
        // Si el usuario ya está autenticado, redirigir al dashboard
        if ($this->getUser()) {
            return $this->redirectToRoute('dashboard');
        }

        // Si no está autenticado, mostrar la página de inicio
        return $this->render('home/index.html.twig');
    }
}