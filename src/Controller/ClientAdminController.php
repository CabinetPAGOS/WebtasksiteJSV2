<?php

// src/Controller/ClientController.php
namespace App\Controller;

use App\Repository\WebtaskRepository;
use App\Entity\Notification;
use App\Repository\NotificationRepository;
use Doctrine\ORM\EntityManagerInterface;
use App\Entity\Client;
use App\Repository\UserRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use App\Repository\ClientRepository;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use App\Entity\Responsable;
use App\Entity\User;

class ClientAdminController extends AbstractController
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

    #[Route('/admin/client/create', name: 'app_createclientadmin')]
    public function create(Request $request, EntityManagerInterface $entityManager, WebtaskRepository $webtaskRepository, NotificationRepository $notificationRepository): Response
    {
        // Récupérer l'utilisateur connecté
        $user = $this->getUser();
        $responsables = $entityManager->getRepository(Responsable::class)->findAll();

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

        // Récupérer les Webtasks associées à cet ID client
        $webtasks = $this->webTaskRepository->findBy(['idclient' => $idclient]);

        // Récupérer tous les responsables de la base de données
        $clientPagos = new Client();

        // Créer le formulaire
        $form = $this->createFormBuilder($clientPagos)
            ->add('id', TextType::class, [
                'label' => 'Identifiant Interne SYLOB :',
                'required' => true,
                'mapped' => true,
            ])
            ->add('code', TextType::class, [
                'label' => 'Code du client :',
                'required' => false,
            ])
            ->add('raisonsociale', TextType::class, [
                'label' => 'Raison Sociale :',
                'required' => false,
            ])
            ->add('google_drive_webtask', TextType::class, [
                'label' => 'Lien Google Drive Webtask :',
                'required' => false,
            ])
            ->add('webtaskOuvertureContact', CheckboxType::class, [
                'label' => 'WebTask Ouverture Client :',
                'required' => false,
            ])

            ->add('responsable', ChoiceType::class, [
                'label' => 'Responsable :',
                'required' => false,
                'placeholder' => 'Choisissez un Responsable',
                'choices' => array_reduce($responsables, function ($choices, $responsable) {
                    $label = $responsable->getPrenom() . ' ' . $responsable->getNom();
                    // Utilisez la fonction pour donner une valeur simple (ID) et un label lisible
                    $choices[$label] = $responsable; // Utilisez l'objet Responsable comme valeur
                    return $choices;
                }, []),
                'choice_value' => function (?Responsable $responsable) {
                    // Retourne l'ID du responsable, ou null si l'élément est nul
                    return $responsable ? $responsable->getId() : null;
                },
                'choice_label' => function (?Responsable $responsable) {
                    // Définit le label comme étant le prénom + nom avec l'ID
                    return $responsable ? $responsable->getPrenom() . ' ' . $responsable->getNom() : '';
                },
            ])

            ->getForm();

        // Traitement du formulaire
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $clientPagos->setId($form->get('id')->getData());

            // Récupérer l'ID du responsable sélectionné
            $responsableId = $form->get('responsable')->getData();
            if ($responsableId) {
                // Récupérer le responsable depuis la base de données
                $responsable = $entityManager->getRepository(Responsable::class)->find($responsableId);
                $clientPagos->setResponsable($responsable);
            
                // Chercher l'utilisateur avec le même prénom et nom
                $userRepository = $entityManager->getRepository(User::class); // Assurez-vous d'avoir importé la classe User et son repository
                $pilote = $userRepository->findOneBy([
                    'prenom' => $responsable->getPrenom(),
                    'nom' => $responsable->getNom(),
                ]);
            
                if ($pilote) {
                    // Définir le pilote
                    $clientPagos->setPilote($pilote);
                } else {
                    // Gérer le cas où aucun utilisateur ne correspond
                    $this->addFlash('error', 'Aucun utilisateur ne correspond au responsable sélectionné.');
                    return $this->redirectToRoute('app_createclientadmin');
                }
            }            
        
            // Enregistrer le client en base de données
            $entityManager->persist($clientPagos);
            $entityManager->flush();
        
            // Rediriger vers une page
            return $this->redirectToRoute('app_homeadmin');
        }

        // Récupérer les notifications visibles de l'utilisateur connecté
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

        // Afficher le formulaire
        return $this->render('Admin/createclient.html.twig', [
            'form' => $form->createView(),
            'notifications' => $notifications,
            'idWebtaskMap' => $idWebtaskMap,
            'client' => $client,
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