<?php
// src/Controller/GestionUserController.php

namespace App\Controller;

use App\Repository\WebtaskRepository;
use App\Entity\Notification;
use App\Repository\NotificationRepository;
use Doctrine\ORM\EntityManagerInterface;
use App\Entity\User;
use App\Entity\Webtask;
use App\Entity\Settings; // Ajout pour gérer la maintenance
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use App\Repository\ClientRepository;
use App\Entity\Client;
use App\Form\ClientType;


class GestionClientController extends AbstractController
{
    private $webTaskRepository;
    private $entityManager;
    private $notificationRepository;
    private $clientRepository;


    public function __construct(
        WebtaskRepository $webTaskRepository,
        EntityManagerInterface $entityManager,
        NotificationRepository $notificationRepository,
        ClientRepository $clientRepository

    ) {
        $this->webTaskRepository = $webTaskRepository;
        $this->entityManager = $entityManager;
        $this->notificationRepository = $notificationRepository;
        $this->clientRepository = $clientRepository;
    }

    #[Route('/gestionclient', name: 'app_gestionclient')]
    public function gestionClient(EntityManagerInterface $entityManager, WebtaskRepository $webtaskRepository, NotificationRepository $notificationRepository): Response
    {
        // Récupérer l'utilisateur connecté
        $user = $this->getUser();

        // Récupérer tous les clients
        $clients = $this->clientRepository->findAll();
        $clients = $this->clientRepository->findBy([], ['raison_sociale' => 'ASC']);

        // Si l'utilisateur n'est pas connecté, rediriger vers la page de connexion
        if (!$user) {
            return $this->redirectToRoute('app_login');
        }

        // Récupérer l'ID du client associé à l'utilisateur connecté
        $idclient = $user->getIdclient();

        // Vérifier si un client est associé à l'utilisateur
        if (!$idclient) {
            throw $this->createNotFoundException('Aucun client associé à cet utilisateur.');
        }

        // Récupérer le client à partir de l'ID
        $client = $this->clientRepository->find($idclient);

        if (!$client) {
            throw $this->createNotFoundException('Client non trouvé');
        }

        // Récupérer le logo du client
        $logo = null;
        if ($client->getLogo()) {
            $logo = base64_encode(stream_get_contents($client->getLogo()));
        }
        
        // Récupérer les notifications visibles de l'utilisateur connecté
        $notifications = $notificationRepository->findBy([
            'user' => $user->getId(),
            'visible' => true
        ]);

        return $this->render('Admin/GestionClient.html.twig', [
            'clients' => $clients,
            'currentUser' => $user,
            'logo' => $logo,
            'notifications' => $notifications,
            'client' => $client,
        ]);
    }

    #[Route('/client/{id}/edit', name: 'app_edit_client')]
    public function edit(Request $request, Client $client, NotificationRepository $notificationRepository): Response
    {
        $user = $this->getUser();

        $form = $this->createForm(ClientType::class, $client);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {

            $this->entityManager->flush();

            return $this->redirectToRoute('app_gestionclient');
        }

        // Récupérer le logo du client
        $logo = null;
        if ($client->getLogo()) {
            $logo = base64_encode(stream_get_contents($client->getLogo()));
        }

        // Récupérer les notifications visibles de l'utilisateur connecté
        $notifications = $notificationRepository->findBy([
            'user' => $user->getId(),
            'visible' => true
        ]);

        return $this->render('Admin/ModifierClient.html.twig', [
            'form' => $form->createView(),
            'client' => $client,
            'notifications' => $notifications,
            'logo' => $logo,

            
        ]);
    }


    #[Route('/notifications', name: 'get_notifications', methods: ['GET'])]
    public function getNotifications(): JsonResponse
    {
        // Récupérer les notifications visibles
        $notifications = $this->notificationRepository->findVisibleNotifications();

        return $this->json([
            'count' => count($notifications), // Compte le nombre de notifications
            'notifications' => $notifications, // Renvoie les notifications
        ]);
    }

    #[Route('/mark-as-read/{id}', name: 'app_mark_as_read', methods: ['POST'])]
    public function markAsRead($id): JsonResponse
    {
        // Récupérer la notification par son ID
        $notification = $this->notificationRepository->find($id); // Utiliser le repository injecté

        if (!$notification) {
            return new JsonResponse(['status' => 'not_found'], 404);
        }

        // Mettre à jour le champ visible à 0
        $notification->setVisible(0); // Assurez-vous que vous avez une méthode pour cela

        // Enregistrer les modifications
        $this->entityManager->persist($notification);
        $this->entityManager->flush();

        return new JsonResponse(['status' => 'success']);
    }

    
}
