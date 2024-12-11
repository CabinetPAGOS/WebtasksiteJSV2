<?php

namespace App\Controller;

use App\Entity\Webtask;
use App\Entity\Notification;
use App\Services\TextTransformer;
use App\Services\VersionService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use App\Repository\ClientRepository;
use App\Entity\User;
use App\Entity\Client;
use App\Repository\WebtaskRepository;
use App\Repository\NotificationRepository;

class ReponseController extends AbstractController
{
    private $webTaskRepository;
    private $notificationRepository;
    private $textTransformer;
    private $versionService;
    private $clientRepository;

    public function __construct(
        TextTransformer $textTransformer,
        VersionService $versionService,
        ClientRepository $clientRepository,
        WebtaskRepository $webTaskRepository,
        NotificationRepository $notificationRepository
    ) {
        $this->textTransformer = $textTransformer;
        $this->versionService = $versionService;
        $this->clientRepository = $clientRepository;
        $this->webTaskRepository = $webTaskRepository;
        $this->notificationRepository = $notificationRepository;
    }

    #[Route('/reponsetaches/{id}', name: 'app_reponsetaches')]
    public function Reponse($id, Request $request, EntityManagerInterface $entityManager, WebtaskRepository $webtaskRepository, NotificationRepository $notificationRepository): Response
    {
        // Récupérer l'utilisateur connecté
        $user = $this->getUser();

        // Si l'utilisateur n'est pas connecté, rediriger vers la page de connexion
        if (!$user) {
            return $this->redirectToRoute('app_login');
        }

        // Vérifiez si $id est bien un entier
        if (!is_numeric($id) || intval($id) <= 0) {
            throw $this->createNotFoundException('L\'ID de la tâche n\'est pas valide');
        }

        // Convertir $id en entier
        $id = (int) $id;

        // Récupérer la tâche depuis la base de données
        $tache = $entityManager->getRepository(Webtask::class)->find($id);

        // Si la tâche n'existe pas, renvoyer une erreur 404
        if (!$tache) {
            throw $this->createNotFoundException('La tâche n\'existe pas');
        }

        $client = $tache->getIdclient();
        $googleDriveLink = $client ? $client->getGoogleDriveWebtask() : null;

        if (!$client) {
            throw $this->createNotFoundException('Client non trouvé');
        }

        // Récupérer le logo du client
        $logo = null;
        if ($client->getLogo()) {
            $logo = base64_encode(stream_get_contents($client->getLogo()));
        }

        if ($request->isMethod('POST')) {
            // Créer une nouvelle tâche
            $newTache = new Webtask();

            // Générer le code automatique pour les tâches commençant par 'SWT-'
            $lastTaskWithSWTCode = $entityManager->getRepository(Webtask::class)->createQueryBuilder('w')
                ->where('w.code LIKE :prefix')
                ->setParameter('prefix', 'SWT-%')
                ->orderBy('w.code', 'DESC')
                ->setMaxResults(1)
                ->getQuery()
                ->getOneOrNullResult();

            if ($lastTaskWithSWTCode) {
                // On extrait le numéro à partir de 'SWT-' et on l'incrémente
                $newCodeNumber = (int) substr($lastTaskWithSWTCode->getCode(), 6) + 1;
            } else {
                // Si aucune tâche avec un code 'SWT-' n'existe, on commence à 1
                $newCodeNumber = 1;
            }
            $newTache->setCode(sprintf('SWT-%06d', $newCodeNumber));

            $fileLinks = [
                $request->request->get('fileLink1', ''),
                $request->request->get('fileLink2', ''),
                $request->request->get('fileLink3', '')
            ];

            $fileTitles = [
                $request->request->get('fileTitle1', ''),
                $request->request->get('fileTitle2', ''),
                $request->request->get('fileTitle3', '')
            ];

            // Initialiser la variable pour savoir s'il y a des documents attachés
            $hasDocuments = false;

            // Vérifier si au moins un des liens ou titres est renseigné
            foreach ($fileLinks as $index => $fileLink) {
                $fileTitle = $fileTitles[$index];

                // Si un lien ou un titre est fourni, considérer que le document est attaché
                if (!empty($fileLink) || !empty($fileTitle)) {
                    $hasDocuments = true;
                    break; // Si un document est trouvé, on arrête la boucle
                }
            }

            // Mettre à jour la tâche avec le statut de documents attachés
            $newTache->setDocumentsAttaches($hasDocuments ? '1' : '0');  // Stockage en tant que chaîne

            for ($i = 1; $i <= 3; $i++) {
                $fileLink = $request->request->get("fileLink{$i}", "");   // lien de fichier
                $fileTitle = $request->request->get("fileTitle{$i}", ""); // titre de fichier

                // Enregistrer uniquement le titre si le lien est vide, sinon les deux
                $liendrive = !empty($fileTitle) ? "{$fileLink}?" . urlencode($fileTitle) : "";

                // Si seulement le titre est fourni sans lien
                if (empty($fileLink) && !empty($fileTitle)) {
                    $liendrive = urlencode($fileTitle);
                }

                // Appel dynamique du setter
                $setter = "setLienDrive{$i}";
                $newTache->$setter($liendrive);
            }

            $areDocumentsAttaches = !empty($documentsAttaches[0]) || !empty($documentsAttaches[1]) || !empty($documentsAttaches[2]);
            $newTache->setDocumentsAttaches($areDocumentsAttaches);

            // Convertir la date en chaîne
            $dueDate = $request->request->get('due_date');
            $newTache->setDateFinDemandee($dueDate);

            // Date et Heure de création et Créateur
            $currentDateTime = new \DateTime();
            $newTache->setCreeLe($currentDateTime->format('d/m/Y - H:i:s'));
            $newTache->setCreerPar($user->getPrenom() . ' ' . $user->getNom());

            // Copie des autres champs
            $newTache->setEtatDeLaWebtask('ON'); // Le nouvel état
            $tache->setEtatDeLaWebtask('OFF'); // L'ancien état devient OFF
            $newTache->setLibelle($tache->getLibelle());
            $newTache->setIdclient($tache->getIdclient());
            $newTache->setTitre($tache->getTitre() ?? '');
            $newTache->setWebtask($tache->getWebtask());
            $newTache->setDescription($tache->getDescription());
            $newTache->setEntite($tache->getEntite());
            $newTache->setTag($tache->getTag());
            $newTache->setIddemandeur($tache->getIddemandeur());
            $newTache->setResponsable($tache->getResponsable());
            $newTache->setPiloteid($tache->getPiloteid());
            $newTache->setEstimationTemps($tache->getEstimationTemps());
            // Date de fin demandée enregistrée dans le code jute au dessus

            // Mise à jour de l'avancement
            if ($tache->getAvancementDeLaTache() == 6) {
                $newTache->setAvancementDeLaTache(7);
            } elseif ($tache->getAvancementDeLaTache() == 5) {
                $newTache->setAvancementDeLaTache(4);
            } else {
                // Si l'avancement est différent de 6 ou 5, garder la valeur actuelle
                $newTache->setAvancementDeLaTache($tache->getAvancementDeLaTache());
            }
            
            $newTache->setDemandeDeRecettage($tache->getDemandeDeRecettage());
            $newTache->setCommentaireWebtaskClient($request->request->get('nouveau_commentaire'));
            // Pas de commentaire interne PAGOS
            // Documents attachés s'enregistre dans le code au-dessus
            // État de la webtask se met à jour à la première ligne de ce bloc
            $newTache->setArchiver($tache->getArchiver());
            $newTache->setOrdre($tache->getOrdre());
            $newTache->setOrdonnele($tache->getOrdonnele());
            $newTache->setRecommandations($tache->getRecommandations());
            $newTache->setIdversion($tache->getIdversion());
            $newTache->setetatVersion($tache->getetatVersion());
            $newTache->setIdtracabilite($tache->getIdtracabilite());
            $newTache->setWebtaskMere($tache->getWebtaskMere());
            $newTache->setBaseclient($tache->getBaseclient());
            $newTache->setsylob5('0');
            $newTache->setNomDocExport('');

            // Le filtre s'enregistre juste en-dessous

            // Récupérer le libellé de la version
            $idVersion = $tache->getIdversion();
            $versionLibelle = $idVersion !== null ? $this->versionService->getLibelleById($idVersion) : 'Version non disponible';

            // Générer le filtre
            $currentDateTime = new \DateTime();
            $filter = sprintf(
                '%s_%s_%s',
                $currentDateTime->format('Ymd'),
                $currentDateTime->format('H:i'),
                $versionLibelle
            );
            $newTache->setFiltre($filter); // Assurez-vous que la méthode setFiltre existe dans l'entité Webtask

            // Persister les entités
            $entityManager->persist($newTache);
            $entityManager->flush();

            // Récupérer tous les utilisateurs du client CABINET PAGOS
            $cabinetPagosClient = $entityManager->getRepository(Client::class)
                ->findOneBy(['raison_sociale' => 'CABINET PAGOS']);

            $usersCabinetPagos = $cabinetPagosClient
                ? $entityManager->getRepository(User::class)
                ->findBy(['idclient' => $cabinetPagosClient])
                : [];

            // Récupérer tous les utilisateurs du client actuel
            $usersCurrentClient = $entityManager->getRepository(User::class)
                ->findBy(['idclient' => $client]);

            // Fusionner les listes d'utilisateurs sans doublons
            $allUsers = array_unique(array_merge($usersCabinetPagos, $usersCurrentClient), SORT_REGULAR);

            // Créer une notification pour chaque utilisateur, sauf l'utilisateur connecté
            foreach ($allUsers as $userNotification) {
                if ($userNotification === $user) {
                    continue; // Ignorer l'utilisateur connecté
                }

                $notification = new Notification();
                $notification->setMessage('Réponse à la WebTask : ' . $newTache->getLibelle());
                $notification->setLibelleWebtask($newTache->getLibelle());
                $notification->setDateCreation(new \DateTime());
                $notification->setVisible(true);
                $notification->setClient($newTache->getIdclient());
                $notification->setTitreWebtask($newTache->getTitre());
                $notification->setCodeWebtask($newTache->getCode());
                $notification->setUser($userNotification);

                $entityManager->persist($notification);
            }

            // Sauvegarder les notifications en base de données
            $entityManager->flush();

            return $this->redirectToRoute('app_taches');
        }

        // Ajouter le libellé de la version
        $idVersion = $tache->getIdversion();
        $versionLibelle = $idVersion !== null ? $this->versionService->getLibelleById($idVersion) : 'Version non disponible';
        $tache->versionLibelle = $versionLibelle;

        // Transformer la description
        $tache->setDescription($this->textTransformer->transformCrToNewLine($tache->getDescription()));

        // Mapper les valeurs
        $mappedTag = $this->mapTag($tache->getTag());
        $tagClass = $this->getTagClass($tache->getTag());
        $mappedAvancement = $this->mapAvancementDeLaTache($tache->getAvancementdelatache());

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

        return $this->render('Client/reponsetaches.html.twig', [
            'webtask' => $tache,
            'mappedTag' => $mappedTag,
            'tagClass' => $tagClass,
            'mappedAvancement' => $mappedAvancement,
            'googleDriveLink' => $googleDriveLink,
            'client' => $client,
            'logo' => $logo,
            'notifications' => $notifications,
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

    private function mapTag(?int $tag): string
    {
        $tags = [
            0 => '(1) Mineur',
            1 => '(2) Grave',
            2 => '(3) Bloquant'
        ];

        return isset($tags[$tag]) ? $tags[$tag] : 'Inconnu';
    }

    private function getTagClass(?int $tag): string
    {
        $classes = [
            0 => 'tag-minor',
            1 => 'tag-serious',
            2 => 'tag-blocking'
        ];

        return isset($classes[$tag]) ? $classes[$tag] : 'tag-unknown';
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

        return isset($avancements[$avancement]) ? $avancements[$avancement] : ['label' => 'Inconnu', 'class' => 'text-muted'];
    }
}
