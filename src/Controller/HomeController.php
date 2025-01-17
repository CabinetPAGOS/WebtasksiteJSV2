<?php

namespace App\Controller;

use App\Entity\Client;
use App\Entity\Forum;
use App\Repository\ClientRepository;
use App\Repository\ForumRepository;
use App\Repository\SettingsRepository;
use App\Repository\WebtaskRepository;
use App\Repository\NotificationRepository;
use App\Services\TextTransformer;
use App\Services\VersionService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\Routing\Annotation\Route;

class HomeController extends AbstractController
{
    private $webTaskRepository;
    private $notificationRepository;
    private $versionService;
    private $textTransformer;
    private $entityManager;
    private $settingsRepository;
    private $clientRepository;
    private $forumRepository;

    public function __construct(
        WebtaskRepository $webTaskRepository,
        NotificationRepository $notificationRepository,
        VersionService $versionService,
        TextTransformer $textTransformer,
        EntityManagerInterface $entityManager,
        SettingsRepository $settingsRepository,
        ForumRepository $forumRepository,
        ClientRepository $clientRepository
    ) {
        $this->webTaskRepository = $webTaskRepository;
        $this->notificationRepository = $notificationRepository;
        $this->versionService = $versionService;
        $this->textTransformer = $textTransformer;
        $this->entityManager = $entityManager;
        $this->settingsRepository = $settingsRepository;
        $this->forumRepository = $forumRepository;
        $this->clientRepository = $clientRepository;
    }

    #[Route('/home', name: 'app_homeclient')]
    public function base(SessionInterface $session, Request $request, WebtaskRepository $webtaskRepository, NotificationRepository $notificationRepository): Response
    {

        $selectedAvancement = $request->query->get('filter', 'all'); // Remplace `filter` par `selectedAvancement`

        // Récupérer l'utilisateur connecté
        $user = $this->getUser();

        // Si l'utilisateur n'est pas connecté, rediriger vers la page de connexion
        if (!$user) {
            return $this->redirectToRoute('app_login');
        }

        // Récupérer les webtasks de l'utilisateur connecté
        $webtasks = $webtaskRepository->findBy(['Piloteid' => $user]);

        // Vérifier si l'utilisateur est le pilote de l'une des webtasks
        $isPilote = count($webtasks) > 0; // Si l'utilisateur est le pilote d'au moins une webtask

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

        // Convertir les dates en objets DateTime pour le tri
        usort($webtasks, function ($a, $b) {
            $dateA = \DateTime::createFromFormat('d/m/Y', $a->getDateFinDemandee());
            $dateB = \DateTime::createFromFormat('d/m/Y', $b->getDateFinDemandee());
            return $dateA <=> $dateB;
        });

        // Trouver la tâche la plus ancienne liée au même "webtask"
        $webtasksWithEarliestDates = [];
        foreach ($webtasksON as $webtaskON) {
            $relatedTasks = $this->webTaskRepository->findBy(['libelle' => $webtaskON->getLibelle()]);
            usort($relatedTasks, function ($a, $b) {
                $dateA = $a->getCreeLe(); // Remplacez par votre méthode d'accès à la date "Créé le"
                $dateB = $b->getCreeLe();
                return $dateA <=> $dateB;
            });
            if (!empty($relatedTasks)) {
                $webtasksWithEarliestDates[$webtaskON->getId()] = $relatedTasks[0]->getDateFinDemandee();
            }
        }

        // Mapper les tags et l'avancement des tâches
        $webtasks = array_map(function ($webtask) {
            $tagValue = $webtask->getTag();
            $mappedTag = $this->mapTag($tagValue);
            $webtask->setTag($mappedTag);


            $idVersion = $webtask->getIdversion();
            if ($idVersion === null) {
                $webtask->versionLibelle = 'Version non disponible';
            } else {
                $webtask->versionLibelle = $this->versionService->getLibelleById($idVersion);
            }

            $webtask->mappedTag = $this->mapTag($webtask->getTag());
            $webtask->mappedAvancement = $this->mapAvancementDeLaTache($webtask->getAvancementdelatache());
            $webtask->setDescription($this->textTransformer->transformCrToNewLine($webtask->getDescription()));

            $avancementValue = $webtask->getAvancementDeLaTache();
            $mappedAvancement = $this->mapAvancementDeLaTache($avancementValue)['label'];
            $webtask->setAvancementDeLaTache($mappedAvancement);

            return $webtask;
        }, $webtasks);

        // Initialiser les statistiques
        $nonPrisesEnCompte = 0;
        $priseEnCompte = 0;
        $terminee = 0;
        $refusee = 0;
        $validee = 0;
        $stopClient = 0;
        $totalTaches = 0;

        $totalPriseEnCompteEtAmelioration = 0;

        foreach ($webtasks as $webtask) {
            $etatWebtask = $webtask->getEtatDeLaWebtask();

            if ($etatWebtask == 'ON') {
                $totalTaches++;
                $avancement = $webtask->getAvancementDeLaTache();
                switch ($avancement) {
                    case 'Non Prise en Compte':
                        $nonPrisesEnCompte++;
                        break;

                    case 'Prise en Compte':
                        $priseEnCompte++;
                        $totalPriseEnCompteEtAmelioration++;  // Ajouter "Prise en Compte" au total
                        break;

                    case 'Terminée':
                        $terminee++;
                        break;

                    case '⛔️ Refusée ⛔':
                        $refusee++;
                        break;

                    case '✅ Validée':
                        $validee++;
                        break;

                    case '❌ Stop Client ❌':
                        $stopClient++;
                        break;

                    case '❇️ Amélioration ❇️':  // Ajouter les tâches en "Amélioration" au total
                        $totalPriseEnCompteEtAmelioration++;
                        break;
                }
            }
        }

        $lastModifiedStopClientWebtasks = array_filter($webtasks, function ($webtask) {
            return $webtask->getAvancementDeLaTache() === '❌ Stop Client ❌';
        });

        usort($lastModifiedStopClientWebtasks, function ($a, $b) {
            return $a->getDateFinDemandee() <=> $b->getDateFinDemandee();
        });

        $lastModifiedStopClientWebtasks = array_slice($lastModifiedStopClientWebtasks, 0, 3);

        // Vérifier le mode maintenance
        $settings = $this->settingsRepository->find(1);
        $maintenanceMode = $settings ? $settings->getMaintenanceMode() : false;

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

        return $this->render('Client/home.html.twig', [
            'webtasks' => $webtasks,
            'earliestDates' => $webtasksWithEarliestDates,
            'nonPrisesEnCompte' => $nonPrisesEnCompte,
            'priseEnCompte' => $priseEnCompte,
            'totalPriseEnCompteEtAmelioration' => $totalPriseEnCompteEtAmelioration,
            'terminee' => $terminee,
            'refusee' => $refusee,
            'validee' => $validee,
            'stopClient' => $stopClient,
            'totalTaches' => $totalTaches,
            'lastModifiedStopClientWebtasks' => $lastModifiedStopClientWebtasks,
            'maintenance_mode' => $maintenanceMode,
            'logo' => $logo,
            'notifications' => $notifications,
            'idWebtaskMap' => $idWebtaskMap,
            'isPilote' => $isPilote,
            'client' => $client,
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

    #[Route('/client/forum/{id}', name: 'app_forum', methods: ['GET'])]
    public function forum($id, WebtaskRepository $webtaskRepository, NotificationRepository $notificationRepository): Response
    {
        // Récupérer le client par son ID
        $client = $this->clientRepository->find($id);

        if (!$client) {
            throw $this->createNotFoundException('Client non trouvé');
        }
        
        // Récupérer les forums associés à ce client
        $forums = $this->forumRepository->findBy(
            ['client' => $client],
            ['date' => 'DESC']  // Tri par la date dans l'ordre décroissant
        );
        $user = $this->getUser();
        if (!$user) {
            return $this->redirectToRoute('app_login');
        }

        // Vérifier si le client est trouvé (ceci est généralement déjà garanti grâce à la route)
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

        // Créer un tableau pour lier codeWebtask à id
        $idWebtaskMap = [];
        foreach ($notifications as $notification) {
            $idWebtask = $this->webTaskRepository->findIdByCodeWebtask($notification->getCodeWebtask());
            if ($idWebtask !== null) {
                $idWebtaskMap[$notification->getCodeWebtask()] = $idWebtask;
            }
        }

        return $this->render('Client/forum.html.twig', [
            'forums' => $forums,
            'client' => $client,
            'logo' => $logo,
            'notifications' => $notifications,
            'idWebtaskMap' => $idWebtaskMap,
        ]);
    }

    private function mapTag(?string $tag): string
    {
        $tags = [
            0 => '(1) Mineur',
            1 => '(2) Grave',
            2 => '(3) Bloquant'
        ];

        return isset($tags[$tag]) ? $tags[$tag] : 'Inconnu';
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