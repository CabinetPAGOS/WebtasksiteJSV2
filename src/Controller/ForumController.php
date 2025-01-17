<?php

namespace App\Controller;

use App\Repository\ForumRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpFoundation\JsonResponse;
use App\Repository\UserRepository;
use App\Entity\Forum;
use App\Entity\Client;
use Doctrine\ORM\EntityManagerInterface;
use App\Form\ForumType;
use App\Entity\Notification;
use App\Repository\NotificationRepository;
use App\Repository\WebtaskRepository;

class ForumController extends AbstractController
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

    #[Route('/admin/forum', name: 'app_adminforum', methods: ['GET'])]
    public function forumadmin(Request $request, EntityManagerInterface $entityManager, NotificationRepository $notificationRepository, WebtaskRepository $webtaskRepository): Response
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
            $forums = $this->forumRepository->findBy(['client' => $clientId], ['date' => 'DESC']);
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
        
        return $this->render('Admin/forum.html.twig', [
            'forums' => $forums, // Passer les résumés à la vue
            'client' => $client, // Passer le client à la vue
            'client_id' => $clientId, // Passer l'ID du client à la vue si nécessaire
            'notifications' => $notifications,
            'logo' => $logo,
            'idWebtaskMap' => $idWebtaskMap,
        ]);
    }

    #[Route('/admin/forum/details/{id}', name: 'app_adminforum_details', methods: ['GET'])]
    public function forumDetails(int $id, EntityManagerInterface $entityManager): Response
    {
        // Récupérer le forum par son ID
        $forum = $entityManager->getRepository(Forum::class)->find($id);

        // Si le forum n'existe pas
        if (!$forum) {
            return $this->json(['success' => false, 'message' => 'Forum non trouvé'], 404);
        }

        // Retourner les détails du forum sous forme de JSON
        return $this->json([
            'success' => true,
            'forum' => [
                'id' => $forum->getId(),
                'titre' => $forum->getTitre(),
                'content' => $forum->getContent(),
                'date' => $forum->getDate()->format('d/m/Y H:i'),
                'auteur' => $forum->getAuteur()->getNom(),
            ]
        ]);
    }

    #[Route('/admin/forum/create', name: 'app_adminforum_create', methods: ['POST'])]
    public function createForum(Request $request, EntityManagerInterface $entityManager): JsonResponse
    {
        $user = $this->getUser();

        if (!$user) {
            return new JsonResponse(['message' => 'Utilisateur non authentifié'], 401);
        }

        $data = json_decode($request->getContent(), true);

        if (!isset($data['title'], $data['content'], $data['clientId'])) {
            return new JsonResponse(['message' => 'Données invalides'], 400);
        }

        $client = $entityManager->getRepository(Client::class)->find($data['clientId']);

        if (!$client) {
            return new JsonResponse(['message' => 'Client non trouvé'], 404);
        }

        $forum = new Forum();
        $forum->setTitre($data['title']);
        $forum->setContent($data['content']);
        $forum->setClient($client);
        $forum->setAuteur($user);
        $forum->setDate(new \DateTime());

        $entityManager->persist($forum);
        $entityManager->flush();

        return new JsonResponse(['message' => 'Forum créé avec succès']);
    }

    #[Route('/admin/forum/edit/{id}/{clientId}', name: 'app_adminforum_edit', methods: ['GET', 'POST'])]
    public function edit(Forum $forum, Request $request, EntityManagerInterface $em, WebtaskRepository $webtaskRepository, NotificationRepository $notificationRepository, $clientId): Response
    {
        // Créez le formulaire
        $form = $this->createForm(ForumType::class, $forum);
        $form->handleRequest($request);

        $user = $this->getUser();
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

        $clientId = $request->get('clientId'); // Récupère l'ID du client à partir de l'URL

        if ($clientId) {
            $client = $em->getRepository(Client::class)->find($clientId);
            if (!$client) {
                throw $this->createNotFoundException('Client introuvable.');
            }
            // Associer les forums avec ce client
            $forums = $this->forumRepository->findBy(['client' => $client]);
        } else {
            throw $this->createNotFoundException('Aucun ID de client fourni.');
        }

        // Traitement de la soumission
        if ($form->isSubmitted() && $form->isValid()) {
            $em->flush(); // Sauvegarder les modifications dans la base de données
            return $this->redirectToRoute('app_adminforum', ['id' => $forum->getClient()->getId()]);
        }

        // Récupérer les notifications visibles de l'utilisateur connecté
        $notifications = $notificationRepository->findBy([
            'user' => $user->getId(),
            'visible' => true
        ]);

        // Créer un tableau pour lier codeWebtask à id
        $idWebtaskMap = [];
        foreach ($notifications as $notification) {
            $idWebtask = $webtaskRepository->findIdByCodeWebtask($notification->getCodeWebtask());
            if ($idWebtask !== null) {
                $idWebtaskMap[$notification->getCodeWebtask()] = $idWebtask;
            }
        }

        return $this->render('Admin/forum_edit.html.twig', [
            'form' => $form->createView(),
            'forum' => $forum,
            'logo' => $logo,
            'idWebtaskMap' => $idWebtaskMap,
            'notifications' => $notifications,
            'client' => $client, // Passer le client à la vue
            'forums' => $forums, // Passer les forums à la vue
        ]);
    }

    #[Route('/client/{id}/forum', name: 'app_client_add_forum', methods: ['POST'])]
    public function addForum(Request $request, Client $client, EntityManagerInterface $entityManager, WebtaskRepository $webtaskRepository, NotificationRepository $notificationRepository): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        $user = $this->getUser();

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

        // Récupérer le logo du client
        $logo = null;
        if ($idclient->getLogo()) {
            $logo = base64_encode(stream_get_contents($idclient->getLogo()));
        }

        $notifications = $notificationRepository->findBy([
            'user' => $user->getId(),
            'visible' => true
        ]);

        // Debugging step: Ensure the notifications are fetched correctly
        if (empty($notifications)) {
            return new JsonResponse(['error' => 'No notifications found'], Response::HTTP_NOT_FOUND);
        }

        // Créer un tableau pour lier codeWebtask à id
        $idWebtaskMap = [];
        foreach ($notifications as $notification) {
            $idWebtask = $webtaskRepository->findIdByCodeWebtask($notification->getCodeWebtask());
            if ($idWebtask !== null) {
                $idWebtaskMap[$notification->getCodeWebtask()] = $idWebtask;
            }
        }

        if (isset($data['content']) && !empty($data['content'])) {
            $forum = new Forum();
            $forum->setContent($data['content']);
            $forum->setClient($client);
            $forum->setDate(new \DateTime());

            $entityManager->persist($forum);
            $entityManager->flush();

            // Renvoyer la réponse avec le contenu et la date formatée
            return new JsonResponse([
                'message' => 'Résumé ajouté avec succès',
                'content' => $forum->getContent(),
                'date' => $forum->getDate()->format('Y-m-d H:i:s'),
                'logo' => $logo,
                'notifications' => $notifications,
                'idWebtaskMap' => $idWebtaskMap,
            ], Response::HTTP_CREATED);
        }
        return new JsonResponse(['error' => 'Le contenu du résumé est vide'], Response::HTTP_BAD_REQUEST);
    }

    #[Route('/admin/forum/delete/{id}', name: 'app_adminforum_delete', methods: ['POST'])]
    public function delete(Forum $forum, EntityManagerInterface $entityManager): Response
    {
        // Supprimer le forum
        $entityManager->remove($forum);
        $entityManager->flush();

        // Rediriger vers la page des forums après suppression
        $clientId = $forum->getClient()->getId();
        return $this->redirectToRoute('app_adminforum', ['id' => $clientId]);
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