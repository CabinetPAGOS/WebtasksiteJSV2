<?php

// src/Controller/ClientsdePagosAdminController.php
namespace App\Controller;

use App\Repository\UserRepository;
use App\Repository\ClientRepository;
use App\Repository\WebtaskRepository;
use App\Services\TextTransformer;
use App\Services\VersionService;
use App\Entity\Notification;
use App\Repository\NotificationRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

class ClientsdePagosAdminController extends AbstractController
{
    private $webTaskRepository;
    private $versionService;
    private $textTransformer;
    private $userRepository;
    private $entityManager;
    private $notificationRepository;
    private $clientRepository;

    public function __construct(
        WebtaskRepository $webTaskRepository,
        VersionService $versionService,
        TextTransformer $textTransformer,
        UserRepository $userRepository,
        EntityManagerInterface $entityManager,
        NotificationRepository $notificationRepository,
        ClientRepository $clientRepository
    ) {
        $this->webTaskRepository = $webTaskRepository;
        $this->versionService = $versionService;
        $this->textTransformer = $textTransformer;
        $this->userRepository = $userRepository;
        $this->entityManager = $entityManager;
        $this->notificationRepository = $notificationRepository;
        $this->clientRepository = $clientRepository;
    }

    #[Route('/admin/clientsdepagos', name: 'app_clientsdepagosadmin')]
    public function clientsdepagosadmin(ClientRepository $clientRepository, WebtaskRepository $webtaskRepository, NotificationRepository $notificationRepository, Request $request): Response
    {
        $selectedAvancement = $request->query->get('filter', 'all');

        // Récupérer l'utilisateur connecté
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
        // Récupérer les Webtasks associées à cet ID client
        $webtasks = $this->webTaskRepository->findBy(['idclient' => $idclient]);


        // Filtrer les tâches avec un état d'avancement 'ON'
        $webtasksON = array_filter($webtasks, function ($webtask) {
            return $webtask->getEtatDeLaWebtask() === 'ON';
        });

        // Extraire les pilotes avec initiales (format: Initiale. NOM)
        $pilotes = [];
        foreach ($webtasksON as $webtask) {
            $piloteId = $webtask->getPiloteid();
            if ($piloteId) {
                $piloteKey = $piloteId->getId();
                if (!isset($pilotes[$piloteKey])) {
                    $prenom = $piloteId->getPrenom();
                    $nom = $piloteId->getNom();

                    $pilotes[$piloteKey] = [
                        'prenom' => $piloteId->getPrenom(),
                        'initiale' => strtoupper(mb_substr($prenom, 0, 1)) . '.', // Convertit l'initiale en majuscule
                        'nom' => strtoupper($nom) // Convertit le nom complet en majuscule

                    ];
                }
            }
        }

        // Trier les pilotes par nom et prénom dans l'ordre croissant
        uasort($pilotes, function ($a, $b) {
            // Comparer d'abord par le nom, puis par le prénom
            $nomComparison = strcmp($a['nom'], $b['nom']);
            if ($nomComparison === 0) {
                // Si les noms sont identiques, trier par prénom
                return strcmp($a['prenom'], $b['prenom']);
            }
            return $nomComparison;
        });

        // Appliquer au filtre
        if (!empty($filterByPilote)) {
            $webtasksON = array_filter($webtasksON, function ($webtask) use ($filterByPilote) {
                return $webtask->getPiloteid() && $webtask->getPiloteid()->getId() == $filterByPilote;
            });
        }

        // Appliquer le filtre par statut d'avancement si spécifié
        if ($selectedAvancement !== 'all') {
            $webtasksON = array_filter($webtasksON, function ($webtask) use ($selectedAvancement) {
                switch ($selectedAvancement) {
                    case 'nonPriseEnCompte':
                        return $webtask->getAvancementDeLaTache() === '0';
                    case 'priseEnCompte':
                        return $webtask->getAvancementDeLaTache() === '1';
                    case 'terminee':
                        return $webtask->getAvancementDeLaTache() === '2';
                    case 'amelioration':
                        return $webtask->getAvancementDeLaTache() === '3';
                    case 'refusee':
                        return $webtask->getAvancementDeLaTache() === '4';
                    case 'validee':
                        return $webtask->getAvancementDeLaTache() === '5';
                    case 'stopClient':
                        return $webtask->getAvancementDeLaTache() === '6';
                    case 'goClient':
                        return $webtask->getAvancementDeLaTache() === '7';
                    default:
                        return true; // Renvoie toutes les tâches si le filtre ne correspond à rien
                }
            });
        }

        // Récupérer le logo du client
        $logo = null;
        if ($idclient->getLogo()) {
            $logo = base64_encode(stream_get_contents($idclient->getLogo()));
        }

        $excludedId = 'e4e080b3758761bd01758f5fcfed03d9';
        $clients = $clientRepository->findAll();

        $webtask->setDescription($this->textTransformer->transformCrToNewLine($webtask->getDescription()));

        $webtask->mappedAvancement = $this->mapAvancementDeLaTache($webtask->getAvancementdelatache());

        // Filtrer les clients par leur ID exclu
        $filteredClients = array_filter($clients, function ($client) use ($excludedId) {
            return $client->getId() !== $excludedId;
        });

        // Récupérer la requête de recherche (query)
        $query = $request->query->get('query');

        // Si une requête de recherche est envoyée, filtrer les clients
        if ($query) {
            $filteredClients = array_filter($filteredClients, function ($client) use ($query) {
                return stripos($client->getRaisonSociale(), $query) !== false;
            });
        }

        // Récupérer le critère de tri (tri par défaut : raisonSociale)
        $sortBy = $request->query->get('sort_by', 'raisonSociale');
        $sortOrder = $request->query->get('sort_order', 'asc'); // 'asc' pour croissant, 'desc' pour décroissant

        // Trier les clients
        usort($filteredClients, function ($a, $b) use ($sortBy, $sortOrder) {
            $valueA = $a->{'get' . ucfirst($sortBy)}();
            $valueB = $b->{'get' . ucfirst($sortBy)}();

            if ($sortOrder === 'asc') {
                return $valueA <=> $valueB;
            } else {
                return $valueB <=> $valueA;
            }
        });

        // Créer un tableau associatif avec les informations du client, y compris le logo encodé
        $clientsData = [];
        foreach ($filteredClients as $client) {
            $logoBase64 = null;

            // Si un logo est disponible, on l'encode en base64
            if ($client->getLogo()) {
                $logoBase64 = base64_encode(stream_get_contents($client->getLogo()));
            }

            $clientsData[] = [
                'id' => $client->getId(),
                'raisonSociale' => $client->getRaisonSociale(),
                'logoBase64' => $logoBase64, // Ajouter le logo encodé ici
            ];
        }

        // Récupérer les utilisateurs depuis le UserRepository
        $users = $this->userRepository->findAll();  // Récupération de tous les utilisateurs

        // Récupérer les notifications visibles de l'utilisateur connecté
        $notifications = $notificationRepository->findBy([
            'user' => $user->getId(),
            'visible' => true
        ]);

        foreach ($notifications as $notification) {
            // Récupérer le libellé de la notification
            $libelleNotification = $notification->getLibelleWebtask();
            
            // Trouver la webtask ON correspondante au libellé de la notification
            $webtaskOn = $this->webTaskRepository->findOneBy([
                'libelle' => $libelleNotification,
                'etat_de_la_webtask' => 'ON'
            ]);
        
            // Si la webtask ON existe, ajouter le lien
            if ($webtaskOn) {
                $notification->setCodeWebtask($webtaskOn->getCode());
            }
        }

        // Créer un tableau pour lier codeWebtask à id
        $idWebtaskMap = [];
        foreach ($notifications as $notification) {
            $idWebtask = $this->webTaskRepository->findIdByCodeWebtask($notification->getCodeWebtask());
            if ($idWebtask !== null) {
                $idWebtaskMap[$notification->getCodeWebtask()] = $idWebtask;
            }
        }

        // Passer $clientsData et $users à la vue
        return $this->render('Admin/ClientsdePagosadmin.html.twig', [
            'clients' => $clientsData,  // Ici on passe le tableau $clientsData
            'users' => $users,          // Passer la liste des utilisateurs à la vue
            'query' => $query,
            'sort_by' => $sortBy,
            'sort_order' => $sortOrder,
            'logo' => $logo,
            'notifications' => $notifications,
            'idWebtaskMap' => $idWebtaskMap,
            'client' => $client,
            'selectedAvancement' => $selectedAvancement,
        ]);
    }

    #[Route('/user/{id}', name: 'app_user_details', methods: ['GET'])]
    public function userDetails($id, UserRepository $userRepository): JsonResponse
    {
        $user = $userRepository->find($id);

        if (!$user) {
            return new JsonResponse(['error' => 'User not found'], 404);
        }

        $data = [
            'id' => $user->getId(),
            'name' => $user->getName(),
            'email' => $user->getEmail(),
        ];

        return new JsonResponse($data);
    }

    #[Route('/client/{id}', name: 'app_client_details', methods: ['GET'])]
    public function clientDetails($id, ClientRepository $clientRepository): JsonResponse
    {
        $client = $clientRepository->find($id);

        if (!$client) {
            return new JsonResponse(['error' => 'Client not found'], 404);
        }

        $data = [
            'id' => $client->getId(),
            'raisonSociale' => $client->getRaisonSociale(),
        ];

        return new JsonResponse($data);
    }

    #[Route('/client/{id}/name', name: 'app_client_name', methods: ['GET'])]
    public function clientName($id, ClientRepository $clientRepository): JsonResponse
    {
        $client = $clientRepository->find($id);

        if (!$client) {
            return new JsonResponse(['error' => 'Client not found'], 404);
        }

        return new JsonResponse([
            'name' => $client->getRaisonSociale()
        ]);
    }

    #[Route('/client/{id}/webtasks', name: 'app_client_webtasks', methods: ['GET'])]
    public function clientWebTasks($id, WebtaskRepository $webtaskRepository, Request $request): JsonResponse
    {
        $webTasks = $webtaskRepository->findByClient($id);

        if (empty($webTasks)) {
            return new JsonResponse(['error' => 'No webtasks found'], 404);
        }

        // Récupérer la requête de recherche (query)
        $query = $request->query->get('query');

        // Si une requête de recherche est envoyée, filtrer les webtasks sur le champ `titre`
        if ($query) {
            $query = strtolower($query); // Convertir en minuscule pour une recherche insensible à la casse
            $webTasks = array_filter($webTasks, function ($webTask) use ($query) {
                return stripos(strtolower($webTask->getTitre()), $query) !== false ||
                    stripos(strtolower($webTask->getWebtask()), $query) !== false ||
                    stripos(strtolower($webTask->getCode()), $query) !== false;
            });
        }

        // Trier les webtasks par le champ `webtask` dans l'ordre croissant
        usort($webTasks, function ($a, $b) {
            return strcmp($a->getWebtask(), $b->getWebtask());
        });

        $data = [];
        foreach ($webTasks as $webTask) {
            $idVersion = $webTask->getIdversion();

            if ($idVersion === null) {
                $versionLibelle = 'Version non disponible';
            } else {
                $versionLibelle = $this->versionService->getLibelleById($idVersion);
            }

            $webTask->setDescription($this->textTransformer->transformCrToNewLine($webTask->getDescription()));

            $data[] = [
                'id' => $webTask->getId(),
                'title' => $webTask->getTitre(),
                'description' => $webTask->getDescription(),
                'webtask' => $webTask->getWebtask(),
                'versionLibelle' => $versionLibelle,
                'datefinDemandee' => $webTask->getDateFinDemandee(),
                'code' => $webTask->getCode(),
                'etatdelawebtask' => $webTask->getEtatDeLaWebtask(),
            ];
        }

        return new JsonResponse($data);
    }

    #[Route('/client/webtask/consulter', name: 'app_consulter_webtask', methods: ['GET'])]
    public function consulterWebTask(Request $request, WebtaskRepository $webtaskRepository): Response
    {
        $id = $request->query->get('id'); // Récupère l'ID de la webtask dans la requête
        $webtask = $webtaskRepository->findOneBy(['code' => $id]); // Remplace 'id' par 'code' si c'est l'identifiant utilisé

        if (!$webtask) {
            throw $this->createNotFoundException('Webtask non trouvée');
        }

        return $this->render('Admin/consulter_webtask.html.twig', [
            'webtask' => $webtask,
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

    private function mapAvancementDeLaTache(?int $avancement): array
    {
        $avancements = [
            0 => ['label' => 'Non Prise en Compte', 'class' => 'text-npc'],
            1 => ['label' => 'Prise en Compte', 'class' => 'text-pc'],
            2 => ['label' => 'Terminée', 'class' => 'text-t'],
            3 => ['label' => '❇️ Amélioration ❇️', 'class' => 'text-a'],
            4 => ['label' => '⛔️ Refusée ⛔️', 'class' => 'text-r'],
            5 => ['label' => '✅ Validée', 'class' => 'text-v'],
            6 => ['label' => '❌ Stop Client ❌', 'class' => 'text-sc'],
            7 => ['label' => '😃 Go Client 😃', 'class' => 'text-gc']
        ];

        return $avancements[$avancement] ?? ['label' => 'Inconnu', 'class' => 'text-muted'];
    }
}