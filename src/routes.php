<?php

use Slim\App;
use Slim\Psr7\Request;
use Slim\Psr7\Response;

return function (App $app) {
    require_once __DIR__ . '/db.php'; // Connexion à la base de données


    // Route racine avec documentation
    $app->get('/', function (Request $request, Response $response) {
        $docs = [
            'title' => 'API Documentation',
            'version' => '1.0.0',
            'endpoints' => [
                'clients' => [
                    'GET /clients' => 'Récupérer tous les clients',
                    'GET /clients/{id}' => 'Récupérer un client spécifique',
                    'POST /clients' => 'Créer un nouveau client'
                ],
                'factures' => [
                    'GET /factures?client_id={id}' => 'Récupérer les factures d\'un client',
                    'GET /factures/{id}' => 'Récupérer les détails d\'une facture',
                    'POST /factures' => 'Créer une nouvelle facture'
                ]
            ],
            'exemples' => [
                'création client' => [
                    'méthode' => 'POST /clients',
                    'body' => [
                        'nom' => 'Dupont',
                        'prenom' => 'Jean',
                        'telephone' => '0123456789',
                        'mel' => 'jean.dupont@email.com',
                        'adresse' => '123 rue Example',
                        'code_postal' => '75000',
                        'ville' => 'Paris'
                    ]
                ],
                'création facture' => [
                    'méthode' => 'POST /factures',
                    'body' => [
                        'id_client' => 1,
                        'id_voiture' => 1,
                        'date' => '2024-02-22'
                    ]
                ]
            ]
        ];

        $response->getBody()->write(json_encode($docs, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
        return $response->withHeader('Content-Type', 'application/json');
    });


    // === CLIENTS ===
    $app->get('/clients', function (Request $request, Response $response) use ($pdo) {
        $stmt = $pdo->query("SELECT * FROM client");
        $clients = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $response->getBody()->write(json_encode($clients));
        return $response->withHeader('Content-Type', 'application/json');
    });

    $app->get('/clients/{id}', function (Request $request, Response $response, array $args) use ($pdo) {
        $stmt = $pdo->prepare("SELECT * FROM client WHERE id_client = ?");
        $stmt->execute([$args['id']]);
        $client = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$client) {
            return $response->withStatus(404)->withHeader('Content-Type', 'application/json')
                ->getBody()->write(json_encode(["message" => "Client non trouvé"]));
        }
        $response->getBody()->write(json_encode($client));
        return $response->withHeader('Content-Type', 'application/json');
    });

    $app->post('/clients', function (Request $request, Response $response) use ($pdo) {
        // Lire le corps de la requête
        $body = $request->getBody()->getContents();
        $data = json_decode($body, true);

        // Vérifier si le JSON est valide
        if (json_last_error() !== JSON_ERROR_NONE) {
            $response->getBody()->write(json_encode(["error" => "JSON invalide"]));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(400);
        }

        // Vérifier la présence des champs obligatoires
        if (!isset($data['nom']) || !isset($data['prenom'])) {
            $response->getBody()->write(json_encode(["error" => "Données manquantes pour 'nom' ou 'prenom'"]));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(400);
        }

        // Exécuter la requête SQL
        $stmt = $pdo->prepare("INSERT INTO client (nom, prenom, telephone, mel, adresse, code_postal, ville) VALUES (?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([
            $data['nom'],
            $data['prenom'],
            $data['telephone'] ?? null,
            $data['mel'] ?? null,
            $data['adresse'] ?? null,
            $data['code_postal'] ?? null,
            $data['ville'] ?? null
        ]);

        $response->getBody()->write(json_encode(["message" => "Client ajouté"]));
        return $response->withHeader('Content-Type', 'application/json')->withStatus(201);
    });

    // === FACTURES ===
    $app->get('/factures', function (Request $request, Response $response) use ($pdo) {
        $queryParams = $request->getQueryParams();

        if (!isset($queryParams['client_id'])) {
            return $response->withStatus(400)->withHeader('Content-Type', 'application/json')
                ->getBody()->write(json_encode(["message" => "Paramètre client_id requis"]));
        }

        $client_id = (int) $queryParams['client_id'];

        $stmt = $pdo->prepare("SELECT * FROM facture WHERE client_id = ?");
        $stmt->execute([$client_id]);
        $factures = $stmt->fetchAll(PDO::FETCH_ASSOC);

        if (!$factures) {
            return $response->withStatus(404)->withHeader('Content-Type', 'application/json')
                ->getBody()->write(json_encode(["message" => "Aucune facture trouvée pour ce client"]));
        }

        $response->getBody()->write(json_encode($factures));
        return $response->withHeader('Content-Type', 'application/json');
    });

    $app->post('/factures', function (Request $request, Response $response) use ($pdo) {
        $data = $request->getParsedBody();
        $stmt = $pdo->prepare("INSERT INTO facture (id_client, id_voiture, montant, date) VALUES (?, ?, 0, ?)");
        $stmt->execute([$data['id_client'], $data['id_voiture'], $data['date']]);
        $response->getBody()->write(json_encode(["message" => "Facture ajoutée"]));
        return $response->withHeader('Content-Type', 'application/json');
    });

    $app->get('/factures/{id}', function (Request $request, Response $response, array $args) use ($pdo) {
        $id = $args['id'];

        $stmt = $pdo->prepare("
        SELECT 
            f.id_facture AS facture_id, f.date AS date_facture, f.montant,
            c.id_client, c.nom AS client_nom, c.prenom AS client_prenom, 
            c.telephone AS client_telephone, c.mel AS client_email, 
            c.adresse AS client_adresse, c.code_postal AS client_code_postal, c.ville AS client_ville,
            v.id_voiture, v.marque, v.modele, v.annee, v.immatriculation, v.kilometrage,
            tr.id_reparation, tr.description AS reparation_description, 
            tr.cout AS reparation_cout, ftr.quantite AS reparation_quantite
        FROM facture f
        JOIN client c ON f.client_id = c.id_client 
        JOIN voiture v ON f.voiture_id = v.id_voiture
        LEFT JOIN facture_type_reparation ftr ON f.id_facture = ftr.id_facture
        LEFT JOIN type_reparation tr ON ftr.id_reparation = tr.id_reparation
        WHERE f.id_facture = ?
        ");

        $stmt->execute([$id]);
        $factureData = $stmt->fetchAll(PDO::FETCH_ASSOC);

        if (!$factureData) {
            return $response->withStatus(404)->withHeader('Content-Type', 'application/json')
                ->getBody()->write(json_encode(["message" => "Facture non trouvée"]));
        }

        // Structurer la réponse
        $facture = [
            "facture_id" => $factureData[0]['facture_id'],
            "date_facture" => $factureData[0]['date_facture'],
            "montant" => $factureData[0]['montant'],
            "client" => [
                "id_client" => $factureData[0]['id_client'],
                "nom" => $factureData[0]['client_nom'],
                "email" => $factureData[0]['client_email'],
                "telephone" => $factureData[0]['client_telephone']
            ],
            "voiture" => [
                "id" => $factureData[0]['id_voiture'],
                "marque" => $factureData[0]['marque'],
                "modele" => $factureData[0]['modele'],
                "annee" => $factureData[0]['annee']
            ],
            "reparations" => []
        ];

        foreach ($factureData as $row) {
            if (!empty($row['id_reparation'])) {
                $facture["reparations"][] = [
                    "id" => $row['id_reparation'],
                    "description" => $row['reparation_description'],
                    "cout" => $row['reparation_cout'],
                    "quantite" => $row['reparation_quantite']
                ];
            }
        }

        $response->getBody()->write(json_encode($facture));
        return $response->withHeader('Content-Type', 'application/json');
    });
};
