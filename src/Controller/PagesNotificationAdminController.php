<?php
// src/Controller/PagesNotificationAdminController.php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use App\Repository\ForumRepository;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\JsonResponse; // Ajoutez cette ligne
use App\Repository\UserRepository; // Assurez-vous que ce repository existe
use App\Entity\Forum;
use App\Entity\Client;
use Doctrine\ORM\EntityManagerInterface;
use App\Form\ForumType;
use App\Entity\Notification;
use App\Repository\NotificationRepository;
use App\Repository\WebtaskRepository;

class PagesNotificationAdminController extends AbstractController
{
    private $forumRepository;
    private $notificationRepository;
    private $webTaskRepository;

    public function __construct(
        ForumRepository $forumRepository,
        NotificationRepository $notificationRepository,
        WebtaskRepository $webTaskRepository,
    ) {
        $this->forumRepository = $forumRepository;
        $this->notificationRepository = $notificationRepository;
        $this->webTaskRepository = $webTaskRepository;
    }

    #[Route('/admin/notification', name: 'app_notificationadmin')]
    public function notificationadmin(Request $request, EntityManagerInterface $entityManager, NotificationRepository $notificationRepository, WebtaskRepository $webtaskRepository): Response
    {
        // Récupérer l'utilisateur connecté
        $user = $this->getUser();

        // Vérifiez si l'utilisateur est connecté
        if (!$user) {
            return $this->redirectToRoute('app_login');
        }

        $idclient = $user->getIdclient();

        // Vérifier si un client est associé à l'utilisateur
        if (!$idclient) {
            throw $this->createNotFoundException('Aucun client associé à cet utilisateur.');
        }

        // Récupérer le logo du client
        $logo = null;
        if ($idclient->getLogo()) {
            $logo = base64_encode(stream_get_contents($idclient->getLogo()));
        }

        // Récupérer l'ID du client depuis les paramètres d'URL
        $clientId = $request->query->get('id'); // Changer 'client_id' en 'id'

        // Vérifiez si l'ID du client est fourni
        if ($clientId) {
            // Récupérer le client
            $client = $entityManager->getRepository(Client::class)->find($clientId);
            // Récupérer les forums du client
            $forums = $this->forumRepository->findBy(['client' => $clientId]);
        } else {
            throw $this->createNotFoundException('Aucun ID de client fourni.');
        }

        $notifications = $notificationRepository->findBy([
            'user' => $user->getId(),
            'visible' => true
        ]);

        // Créer un tableau pour lier codeWebtask à id
        $idWebtaskMap = [];
        foreach ($notifications as $notification) {
            $idWebtask = $this->webTaskRepository->findIdByCodeWebtask($notification->getCodeWebtask());
            if ($idWebtask !== null) {
                $idWebtaskMap[$notification->getCodeWebtask()] = $idWebtask;
            }
        }
        return $this->render('Admin/notification.html.twig', [
            'forums' => $forums, // Passer les résumés à la vue
            'client' => $client, // Passer le client à la vue
            'client_id' => $clientId, // Passer l'ID du client à la vue si nécessaire
            'notifications' => $notifications,
            'logo' => $logo,
            'idWebtaskMap' => $idWebtaskMap,
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
