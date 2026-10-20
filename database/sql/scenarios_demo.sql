-- ============================================================================
-- ChillNet — jeu de scénarios complet
-- ----------------------------------------------------------------------------
-- Ce fichier remplit la base avec des situations qui couvrent tous les cas
-- de l'application : les trois rôles, les trois niveaux d'alerte, les quatre
-- types de coupure dans les trois statuts, les quatre catégories de
-- signalement dans les trois urgences et les trois statuts, les trois types
-- de points de fraicheur dans les trois statuts de modération, les avis de
-- 1 à 5 étoiles, les équipements sensibles et les traces IA.
--
-- Base       : MySQL 8, schéma `chillnet`
-- Comptes    : mot de passe `password`
-- Rejouable  : les lignes de ce jeu (repérées par leurs libellés, leurs
--              adresses e-mail et leurs versions de modèle) sont supprimées
--              puis recréées. Les données existantes ne sont pas touchées.
--
-- Exécution manuelle :
--   mysql -u root -p chillnet < database/sql/scenarios_demo.sql
-- ============================================================================

SET NAMES utf8mb4;
USE chillnet;

SET @maintenant := NOW();

START TRANSACTION;

-- ----------------------------------------------------------------------------
-- 0. Nettoyage des scénarios précédents (dans l'ordre des dépendances)
--    Les clés de nettoyage sont les libellés, comptes et versions ci-dessous :
--    le jeu peut être rejoué sans créer de doublon.
-- ----------------------------------------------------------------------------
DELETE FROM signalement_analyses
 WHERE version_modele IN ('triage-1.0', 'triage-1.0')
    OR signalement_id IN (
        SELECT id FROM signalements
         WHERE description LIKE '[SCENARIO]%'
            OR description IN (
                'Écoulement lent au sous-sol, seau en place.',
                'Évier de la cuisine qui fuit depuis hier soir.',
                'Fuite importante près du tableau électrique, risque de court-circuit.',
                'Lampe du couloir du 2e étage hors service.',
                'Ascenseur bloqué entre le 1er et le 2e étage.',
                'Porte de secours du hall bloquée par des cartons.',
                'Voisine âgée isolée, sans nouvelles depuis deux jours.',
                'Personne sous respirateur sans électricité depuis trois heures.',
                'Malaise dans le hall, secours appelés.',
                'Nuisance sonore répétée après 23 h.',
                'Odeur de gaz dans la cage escalier.',
                'Dégât des eaux au plafond du 3e étage.'
            )
    );

DELETE FROM signalements
 WHERE description LIKE '[SCENARIO]%'
    OR description IN (
        'Écoulement lent au sous-sol, seau en place.',
        'Évier de la cuisine qui fuit depuis hier soir.',
        'Fuite importante près du tableau électrique, risque de court-circuit.',
        'Lampe du couloir du 2e étage hors service.',
        'Ascenseur bloqué entre le 1er et le 2e étage.',
        'Porte de secours du hall bloquée par des cartons.',
        'Voisine âgée isolée, sans nouvelles depuis deux jours.',
        'Personne sous respirateur sans électricité depuis trois heures.',
        'Malaise dans le hall, secours appelés.',
        'Nuisance sonore répétée après 23 h.',
        'Odeur de gaz dans la cage escalier.',
        'Dégât des eaux au plafond du 3e étage.'
    );

DELETE FROM avis
 WHERE user_id IN (
        SELECT id FROM users
         WHERE email LIKE '%@scenarios.chillnet.test'
            OR email IN (
                'sami.benali@chillnet.tn',
                'fatma.trabelsi@chillnet.tn',
                'ines.mansour@chillnet.tn',
                'mohamed.gharbi@chillnet.tn',
                'leila.bouzid@chillnet.tn',
                'nadia.khelifi@chillnet.tn'
            )
    )
    OR point_fraicheur_id IN (
        SELECT id FROM points_fraicheur
         WHERE nom LIKE '[SCENARIO]%'
            OR nom IN (
                'Parc ombragé du Belvédère',
                'Salle climatisée El Manar',
                'Fontaine de La Marsa',
                'Parc de quartier Ariana',
                'Parc des Jasmins',
                'Salle climatisée Ariana',
                'Fontaine Sidi Bou Saïd',
                'Jardin de la villa El Aïda',
                'Salle de réunion Ibn Khaldoun',
                'Fontaine du Quai des Brumes'
            )
    );

DELETE FROM ai_predictions
 WHERE model_version IN ('chillnet-1.0', 'scenario-1.0');

DELETE FROM alertes
 WHERE titre LIKE '[SCENARIO]%'
    OR titre IN (
        'Canicule rouge — Centre-Ville',
        'Vigilance orange — Ariana et Sidi Bou Saïd',
        'Vigilance jaune — La Marsa',
        'Épisode caniculaire — Centre-Ville (terminé)',
        'Prévision canicule rouge — Ariana Ville'
    );

DELETE FROM points_fraicheur
 WHERE nom LIKE '[SCENARIO]%'
    OR nom IN (
        'Parc ombragé du Belvédère',
        'Salle climatisée El Manar',
        'Fontaine de La Marsa',
        'Parc de quartier Ariana',
        'Parc des Jasmins',
        'Salle climatisée Ariana',
        'Fontaine Sidi Bou Saïd',
        'Jardin de la villa El Aïda',
        'Salle de réunion Ibn Khaldoun',
        'Fontaine du Quai des Brumes'
    );

DELETE FROM coupures
 WHERE description LIKE '[SCENARIO]%'
    OR description IN (
        'Délestage en cours, prévoyez vos équipements sensibles.',
        'Surcharge réseau, coupure ponctuelle en cours de diagnostic.',
        'Panne localisée sur la corniche, équipe sur place.',
        'Maintenance en cours sur le poste de transformation.',
        'Délestage programmé demain matin.',
        'Surcharge annoncée, coupure préventive de deux heures.',
        'Remplacement de câble prévu, coupure de trois heures.',
        'Maintenance planifiée sur le réseau électrique.',
        'Délestage résolu après deux heures.',
        'Surcharge résolue, deux heures trente de coupure.',
        'Panne transformateur réparée en trois heures.',
        'Maintenance terminée sur le poste de quartier.'
    );

DELETE FROM coupure_risk_snapshots
 WHERE model_version IN ('chillnet-1.0', 'scenario-1.0');

DELETE FROM point_sante_snapshots
 WHERE model_version IN ('chillnet-1.0', 'scenario-1.0');

DELETE FROM equipements
 WHERE user_id IN (
        SELECT id FROM users
         WHERE email LIKE '%@scenarios.chillnet.test'
            OR email IN (
                'sami.benali@chillnet.tn',
                'fatma.trabelsi@chillnet.tn',
                'ines.mansour@chillnet.tn',
                'mohamed.gharbi@chillnet.tn',
                'leila.bouzid@chillnet.tn',
                'nadia.khelifi@chillnet.tn'
            )
    );

DELETE FROM lieux
 WHERE user_id IN (
        SELECT id FROM users
         WHERE email LIKE '%@scenarios.chillnet.test'
            OR email IN (
                'sami.benali@chillnet.tn',
                'fatma.trabelsi@chillnet.tn',
                'ines.mansour@chillnet.tn',
                'mohamed.gharbi@chillnet.tn',
                'leila.bouzid@chillnet.tn',
                'nadia.khelifi@chillnet.tn'
            )
    );

DELETE FROM users
 WHERE email LIKE '%@scenarios.chillnet.test'
    OR email IN (
        'sami.benali@chillnet.tn',
        'fatma.trabelsi@chillnet.tn',
        'ines.mansour@chillnet.tn',
        'mohamed.gharbi@chillnet.tn',
        'leila.bouzid@chillnet.tn',
        'nadia.khelifi@chillnet.tn'
    );

-- Résidence de démonstration sans coordonnées : supprimée seulement si plus
-- aucun compte ni signalement ne la référence, puis recréée en section 2.
DELETE FROM residences
 WHERE nom IN ('Résidence El Yasmine', 'Résidence Sans Adresse')
   AND NOT EXISTS (SELECT 1 FROM users WHERE users.residence_id = residences.id)
   AND NOT EXISTS (SELECT 1 FROM signalements WHERE signalements.residence_id = residences.id);

-- ----------------------------------------------------------------------------
-- 1. Quartiers (réutilisés s'ils existent déjà)
-- ----------------------------------------------------------------------------
INSERT INTO quartiers (nom, ville, code_postal, description, latitude, longitude, created_at, updated_at)
SELECT 'Centre-Ville', 'Tunis', '1000', 'Quartier central, forte densité de population.',
       36.8008000, 10.1800000, @maintenant, @maintenant FROM DUAL
 WHERE NOT EXISTS (SELECT 1 FROM quartiers WHERE nom = 'Centre-Ville' AND ville = 'Tunis');

SET @q_centre := (SELECT id FROM quartiers WHERE nom = 'Centre-Ville' AND ville = 'Tunis' ORDER BY id LIMIT 1);

INSERT INTO quartiers (nom, ville, code_postal, description, latitude, longitude, created_at, updated_at)
SELECT 'Les Berges du Lac', 'Tunis', '1053', 'Quartier résidentiel et d''affaires.',
       36.8325000, 10.2800000, @maintenant, @maintenant FROM DUAL
 WHERE NOT EXISTS (SELECT 1 FROM quartiers WHERE nom = 'Les Berges du Lac' AND ville = 'Tunis');

SET @q_berges := (SELECT id FROM quartiers WHERE nom = 'Les Berges du Lac' AND ville = 'Tunis' ORDER BY id LIMIT 1);

INSERT INTO quartiers (nom, ville, code_postal, description, latitude, longitude, created_at, updated_at)
SELECT 'La Marsa', 'Tunis', '2070', 'Quartier littoral, forte chaleur en été.',
       36.8782000, 10.3247000, @maintenant, @maintenant FROM DUAL
 WHERE NOT EXISTS (SELECT 1 FROM quartiers WHERE nom = 'La Marsa' AND ville = 'Tunis');

SET @q_marsa := (SELECT id FROM quartiers WHERE nom = 'La Marsa' AND ville = 'Tunis' ORDER BY id LIMIT 1);

INSERT INTO quartiers (nom, ville, code_postal, description, latitude, longitude, created_at, updated_at)
SELECT 'Ariana Ville', 'Ariana', '2080', 'Zone urbaine dense, réseau parfois chargé.',
       36.8625000, 10.1956000, @maintenant, @maintenant FROM DUAL
 WHERE NOT EXISTS (SELECT 1 FROM quartiers WHERE nom = 'Ariana Ville' AND ville = 'Ariana');

SET @q_ariana := (SELECT id FROM quartiers WHERE nom = 'Ariana Ville' AND ville = 'Ariana' ORDER BY id LIMIT 1);

INSERT INTO quartiers (nom, ville, code_postal, description, latitude, longitude, created_at, updated_at)
SELECT 'Sidi Bou Saïd', 'Tunis', '2026', 'Village côtier, ruelles étroites.',
       36.8708000, 10.3417000, @maintenant, @maintenant FROM DUAL
 WHERE NOT EXISTS (SELECT 1 FROM quartiers WHERE nom = 'Sidi Bou Saïd' AND ville = 'Tunis');

SET @q_sidibou := (SELECT id FROM quartiers WHERE nom = 'Sidi Bou Saïd' AND ville = 'Tunis' ORDER BY id LIMIT 1);

-- ----------------------------------------------------------------------------
-- 2. Résidences (dont une sans adresse ni coordonnées)
-- ----------------------------------------------------------------------------
INSERT INTO residences (nom, adresse, latitude, longitude, nombre_logements, salle_climatisee, point_fraicheur, quartier_id, created_at, updated_at)
SELECT 'Résidence Les Oliviers', '12 Avenue Habib Bourguiba', 36.8008000, 10.1800000, 48, 1, 1,
       @q_centre, @maintenant, @maintenant FROM DUAL
 WHERE NOT EXISTS (SELECT 1 FROM residences WHERE nom = 'Résidence Les Oliviers' AND quartier_id = @q_centre);

SET @r_oliviers := (SELECT id FROM residences WHERE nom = 'Résidence Les Oliviers' AND quartier_id = @q_centre ORDER BY id LIMIT 1);

INSERT INTO residences (nom, adresse, latitude, longitude, nombre_logements, salle_climatisee, point_fraicheur, quartier_id, created_at, updated_at)
SELECT 'Résidence El Manar', '5 Rue de la Liberté', 36.8020000, 10.1815000, 30, 0, 0,
       @q_centre, @maintenant, @maintenant FROM DUAL
 WHERE NOT EXISTS (SELECT 1 FROM residences WHERE nom = 'Résidence El Manar' AND quartier_id = @q_centre);

SET @r_manar := (SELECT id FROM residences WHERE nom = 'Résidence El Manar' AND quartier_id = @q_centre ORDER BY id LIMIT 1);

INSERT INTO residences (nom, adresse, latitude, longitude, nombre_logements, salle_climatisee, point_fraicheur, quartier_id, created_at, updated_at)
SELECT 'Résidence Lac View', '20 Rue du Lac Léman', 36.8325000, 10.2800000, 60, 1, 1,
       @q_berges, @maintenant, @maintenant FROM DUAL
 WHERE NOT EXISTS (SELECT 1 FROM residences WHERE nom = 'Résidence Lac View' AND quartier_id = @q_berges);

SET @r_lac := (SELECT id FROM residences WHERE nom = 'Résidence Lac View' AND quartier_id = @q_berges ORDER BY id LIMIT 1);

INSERT INTO residences (nom, adresse, latitude, longitude, nombre_logements, salle_climatisee, point_fraicheur, quartier_id, created_at, updated_at)
SELECT 'Résidence Les Jasmins', '8 Avenue de la Plage', 36.8782000, 10.3247000, 24, 0, 0,
       @q_marsa, @maintenant, @maintenant FROM DUAL
 WHERE NOT EXISTS (SELECT 1 FROM residences WHERE nom = 'Résidence Les Jasmins' AND quartier_id = @q_marsa);

SET @r_jasmins := (SELECT id FROM residences WHERE nom = 'Résidence Les Jasmins' AND quartier_id = @q_marsa ORDER BY id LIMIT 1);

INSERT INTO residences (nom, adresse, latitude, longitude, nombre_logements, salle_climatisee, point_fraicheur, quartier_id, created_at, updated_at)
SELECT 'Résidence El Ghazala', '3 Rue de l''Innovation', 36.8625000, 10.1956000, 36, 1, 0,
       @q_ariana, @maintenant, @maintenant FROM DUAL
 WHERE NOT EXISTS (SELECT 1 FROM residences WHERE nom = 'Résidence El Ghazala' AND quartier_id = @q_ariana);

SET @r_ghazala := (SELECT id FROM residences WHERE nom = 'Résidence El Ghazala' AND quartier_id = @q_ariana ORDER BY id LIMIT 1);

INSERT INTO residences (nom, adresse, latitude, longitude, nombre_logements, salle_climatisee, point_fraicheur, quartier_id, created_at, updated_at)
SELECT 'Résidence Sidi Bou', '7 Rue Habib Thameur', 36.8708000, 10.3417000, 18, 0, 0,
       @q_sidibou, @maintenant, @maintenant FROM DUAL
 WHERE NOT EXISTS (SELECT 1 FROM residences WHERE nom = 'Résidence Sidi Bou' AND quartier_id = @q_sidibou);

SET @r_sidibou := (SELECT id FROM residences WHERE nom = 'Résidence Sidi Bou' AND quartier_id = @q_sidibou ORDER BY id LIMIT 1);

INSERT INTO residences (nom, adresse, latitude, longitude, nombre_logements, salle_climatisee, point_fraicheur, quartier_id, created_at, updated_at)
SELECT 'Résidence El Yasmine', NULL, NULL, NULL, 12, 0, 0,
       @q_marsa, @maintenant, @maintenant FROM DUAL
 WHERE NOT EXISTS (SELECT 1 FROM residences WHERE nom = 'Résidence El Yasmine' AND quartier_id = @q_marsa);

SET @r_sansadresse := (SELECT id FROM residences WHERE nom = 'Résidence El Yasmine' AND quartier_id = @q_marsa ORDER BY id LIMIT 1);

-- ----------------------------------------------------------------------------
-- 3. Comptes de scénario (mot de passe : password)
-- ----------------------------------------------------------------------------
INSERT INTO users (name, email, email_verified_at, password, role, profil_vulnerabilites, residence_id, created_at, updated_at)
VALUES ('Sami Ben Ali', 'sami.benali@chillnet.tn', @maintenant,
        '$2y$10$U9q6sku3DsZfUpBn38I.W.orKwB5rSXKeIuJMIrDipgnhnW.Xrl/G',
        'habitant', NULL, @r_oliviers, @maintenant, @maintenant);
SET @u_standard := LAST_INSERT_ID();

INSERT INTO users (name, email, email_verified_at, password, role, profil_vulnerabilites, residence_id, created_at, updated_at)
VALUES ('Fatma Trabelsi', 'fatma.trabelsi@chillnet.tn', @maintenant,
        '$2y$10$U9q6sku3DsZfUpBn38I.W.orKwB5rSXKeIuJMIrDipgnhnW.Xrl/G',
        'habitant', JSON_ARRAY('personne_agee'), @r_manar, @maintenant, @maintenant);
SET @u_age := LAST_INSERT_ID();

INSERT INTO users (name, email, email_verified_at, password, role, profil_vulnerabilites, residence_id, created_at, updated_at)
VALUES ('Inès Mansour', 'ines.mansour@chillnet.tn', @maintenant,
        '$2y$10$U9q6sku3DsZfUpBn38I.W.orKwB5rSXKeIuJMIrDipgnhnW.Xrl/G',
        'habitant', JSON_ARRAY('enfant'), @r_jasmins, @maintenant, @maintenant);
SET @u_enfant := LAST_INSERT_ID();

INSERT INTO users (name, email, email_verified_at, password, role, profil_vulnerabilites, residence_id, created_at, updated_at)
VALUES ('Mohamed Gharbi', 'mohamed.gharbi@chillnet.tn', @maintenant,
        '$2y$10$U9q6sku3DsZfUpBn38I.W.orKwB5rSXKeIuJMIrDipgnhnW.Xrl/G',
        'habitant', JSON_ARRAY('personne_agee', 'equipement_medical'), @r_lac, @maintenant, @maintenant);
SET @u_medical := LAST_INSERT_ID();

INSERT INTO users (name, email, email_verified_at, password, role, profil_vulnerabilites, residence_id, created_at, updated_at)
VALUES ('Leïla Bouzid', 'leila.bouzid@chillnet.tn', @maintenant,
        '$2y$10$U9q6sku3DsZfUpBn38I.W.orKwB5rSXKeIuJMIrDipgnhnW.Xrl/G',
        'gestionnaire', NULL, @r_ghazala, @maintenant, @maintenant);
SET @u_gestionnaire := LAST_INSERT_ID();

INSERT INTO users (name, email, email_verified_at, password, role, profil_vulnerabilites, residence_id, created_at, updated_at)
VALUES ('Nadia Khelifi', 'nadia.khelifi@chillnet.tn', @maintenant,
        '$2y$10$U9q6sku3DsZfUpBn38I.W.orKwB5rSXKeIuJMIrDipgnhnW.Xrl/G',
        'admin', NULL, NULL, @maintenant, @maintenant);
SET @u_admin := LAST_INSERT_ID();

-- ----------------------------------------------------------------------------
-- 4. Lieux personnels des habitants (domicile, travail, autre)
-- ----------------------------------------------------------------------------
INSERT INTO lieux (user_id, nom, type, adresse, latitude, longitude, quartier_id, est_principal, created_at, updated_at) VALUES
(@u_standard, 'Domicile', 'domicile', '12 Avenue Habib Bourguiba', 36.8008000, 10.1800000, @q_centre, 1, @maintenant, @maintenant),
(@u_standard, 'Travail', 'travail', '8 Avenue de la Plage', 36.8782000, 10.3247000, @q_marsa, 0, @maintenant, @maintenant),
(@u_age, 'Domicile', 'domicile', '5 Rue de la Liberté', 36.8020000, 10.1815000, @q_centre, 1, @maintenant, @maintenant),
(@u_enfant, 'Domicile', 'domicile', '8 Avenue de la Plage', 36.8782000, 10.3247000, @q_marsa, 1, @maintenant, @maintenant),
(@u_enfant, 'École', 'autre', 'Rue de la Plage', 36.8790000, 10.3250000, @q_marsa, 0, @maintenant, @maintenant),
(@u_medical, 'Domicile', 'domicile', '20 Rue du Lac Léman', 36.8325000, 10.2800000, @q_berges, 1, @maintenant, @maintenant),
(@u_medical, 'Cabinet médical', 'autre', '3 Rue de l''Innovation', 36.8625000, 10.1956000, @q_ariana, 0, @maintenant, @maintenant);

-- ----------------------------------------------------------------------------
-- 5. Équipements sensibles (les 8 types, les 3 criticités)
-- ----------------------------------------------------------------------------
SET @t_ref         := (SELECT id FROM equipement_types WHERE slug = 'refrigerateur');
SET @t_clim        := (SELECT id FROM equipement_types WHERE slug = 'climatiseur');
SET @t_ventilo     := (SELECT id FROM equipement_types WHERE slug = 'ventilateur');
SET @t_respirateur := (SELECT id FROM equipement_types WHERE slug = 'respirateur');
SET @t_medical     := (SELECT id FROM equipement_types WHERE slug = 'medical_autre');
SET @t_chauffe     := (SELECT id FROM equipement_types WHERE slug = 'chauffe_eau');
SET @t_onduleur    := (SELECT id FROM equipement_types WHERE slug = 'onduleur');
SET @t_autre       := (SELECT id FROM equipement_types WHERE slug = 'autre');

INSERT INTO equipements (user_id, equipement_type_id, nom, criticite, notes, contact_urgence, created_at, updated_at) VALUES
(@u_standard, @t_clim,        'Climatiseur du salon',        'elevee',  'Salon, 12 000 BTU.', NULL, @maintenant, @maintenant),
(@u_standard, @t_autre,       'Congélateur d''appoint',       'normale', 'Garage, conserve des aliments.', NULL, @maintenant, @maintenant),
(@u_age,      @t_ref,         'Réfrigérateur',               'normale', 'Cuisine.', NULL, @maintenant, @maintenant),
(@u_age,      @t_chauffe,     'Chauffe-eau électrique',      'elevee',  'Salle de bain.', NULL, @maintenant, @maintenant),
(@u_enfant,   @t_ventilo,     'Ventilateur de chambre',      'normale', 'Chambre des enfants.', NULL, @maintenant, @maintenant),
(@u_medical,  @t_respirateur, 'Respirateur nocturne',        'vitale',  'Chambre, CPAP.', 'Fille — +216 21 345 678', @maintenant, @maintenant),
(@u_medical,  @t_medical,     'Dialyse à domicile',          'vitale',  'Salon, 3 séances par semaine.', 'Fils — +216 98 765 432', @maintenant, @maintenant),
(@u_medical,  @t_onduleur,    'Onduleur 600 VA',             'elevee',  'Bureau, alimente le respirateur.', NULL, @maintenant, @maintenant),
(@u_medical,  @t_ref,         'Réfrigérateur à médicaments', 'normale', 'Cuisine, insuline.', NULL, @maintenant, @maintenant);

-- ----------------------------------------------------------------------------
-- 6. Alertes canicule (3 niveaux, validées ou non, cercle ou quartiers)
-- ----------------------------------------------------------------------------
INSERT INTO alertes (titre, niveau, seuil_temperature, temperature_actuelle, temperature_ressentie,
                     humidite, source_meteo, debut, fin, message, latitude, longitude, rayon_metres,
                     lieu, validee, validee_le, validee_par, user_id, created_at, updated_at)
VALUES ('Canicule rouge — Centre-Ville', 'rouge', 40.0, 42.5, 46.2, 35, 'manuel',
        DATE_SUB(@maintenant, INTERVAL 6 HOUR), DATE_ADD(@maintenant, INTERVAL 6 HOUR),
        'Canicule exceptionnelle. Limitez les efforts et vérifiez vos voisins isolés.',
        36.8008000, 10.1800000, 1500, 'Avenue Habib Bourguiba',
        1, @maintenant, @u_admin, @u_gestionnaire, @maintenant, @maintenant);
SET @a_rouge := LAST_INSERT_ID();

INSERT INTO alertes (titre, niveau, seuil_temperature, temperature_actuelle, temperature_ressentie,
                     humidite, source_meteo, debut, fin, message, latitude, longitude, rayon_metres,
                     lieu, validee, validee_le, validee_par, user_id, created_at, updated_at)
VALUES ('Vigilance orange — Ariana et Sidi Bou Saïd', 'orange', 37.0, 38.0, 40.5, 55, 'manuel',
        DATE_SUB(@maintenant, INTERVAL 2 HOUR), DATE_ADD(@maintenant, INTERVAL 10 HOUR),
        'Forte chaleur. Buvez régulièrement et évitez le soleil entre 11 h et 16 h.',
        NULL, NULL, NULL, NULL,
        1, @maintenant, @u_admin, @u_gestionnaire, @maintenant, @maintenant);
SET @a_orange := LAST_INSERT_ID();

INSERT INTO alertes (titre, niveau, seuil_temperature, temperature_actuelle, temperature_ressentie,
                     humidite, source_meteo, debut, fin, message, latitude, longitude, rayon_metres,
                     lieu, validee, validee_le, validee_par, user_id, created_at, updated_at)
VALUES ('Vigilance jaune — La Marsa', 'jaune', 35.0, 35.2, 36.4, 60, 'manuel',
        TIMESTAMP(DATE_ADD(CURDATE(), INTERVAL 1 DAY), '08:00:00'),
        TIMESTAMP(DATE_ADD(CURDATE(), INTERVAL 1 DAY), '20:00:00'),
        'Chaleur modérée demain. Pensez à fermer les volets en journée.',
        36.8782000, 10.3247000, 800, 'Avenue de la Plage',
        1, @maintenant, @u_admin, @u_gestionnaire, @maintenant, @maintenant);
SET @a_jaune := LAST_INSERT_ID();

INSERT INTO alertes (titre, niveau, seuil_temperature, temperature_actuelle, temperature_ressentie,
                     humidite, source_meteo, debut, fin, message, latitude, longitude, rayon_metres,
                     lieu, validee, validee_le, validee_par, user_id, created_at, updated_at)
VALUES ('Épisode caniculaire — Centre-Ville (terminé)', 'orange', 37.0, 39.4, 42.1, 48, 'manuel',
        DATE_SUB(@maintenant, INTERVAL 4 DAY), DATE_SUB(@maintenant, INTERVAL 3 DAY),
        'Épisode passé. Les points de fraicheur restent ouverts.',
        NULL, NULL, NULL, NULL,
        1, DATE_SUB(@maintenant, INTERVAL 4 DAY), @u_admin, @u_gestionnaire,
        DATE_SUB(@maintenant, INTERVAL 4 DAY), DATE_SUB(@maintenant, INTERVAL 4 DAY));
SET @a_terminee := LAST_INSERT_ID();

INSERT INTO alertes (titre, niveau, seuil_temperature, temperature_actuelle, temperature_ressentie,
                     humidite, source_meteo, debut, fin, message, latitude, longitude, rayon_metres,
                     lieu, validee, validee_le, validee_par, user_id, created_at, updated_at)
VALUES ('Prévision canicule rouge — Ariana Ville', 'rouge', 40.0, 41.8, 45.0, 30, 'manuel',
        DATE_SUB(@maintenant, INTERVAL 1 HOUR), DATE_ADD(@maintenant, INTERVAL 5 HOUR),
        'Prévision à confirmer par le gestionnaire avant publication.',
        36.8625000, 10.1956000, 1200, 'Rue de l''Innovation',
        0, NULL, NULL, @u_gestionnaire, @maintenant, @maintenant);
SET @a_brouillon := LAST_INSERT_ID();

INSERT INTO alerte_quartier (alerte_id, quartier_id) VALUES
(@a_rouge,     @q_centre),
(@a_rouge,     @q_marsa),
(@a_orange,    @q_ariana),
(@a_orange,    @q_sidibou),
(@a_jaune,     @q_marsa),
(@a_terminee,  @q_centre),
(@a_brouillon, @q_ariana);

-- ----------------------------------------------------------------------------
-- 7. Coupures (4 types × 3 statuts, quartier ou point libre)
-- ----------------------------------------------------------------------------
INSERT INTO coupures (quartier_id, latitude, longitude, lieu, type, statut, debut, fin, description,
                      confirmations, user_id, created_at, updated_at) VALUES
(@q_centre, NULL, NULL, 'Rue des Lilas', 'delestage', 'en_cours',
 DATE_SUB(@maintenant, INTERVAL 45 MINUTE), DATE_ADD(@maintenant, INTERVAL 2 HOUR),
 'Délestage en cours, prévoyez vos équipements sensibles.', 7, @u_gestionnaire,
 DATE_SUB(@maintenant, INTERVAL 45 MINUTE), @maintenant),

(@q_ariana, NULL, NULL, 'Avenue de la République', 'surcharge', 'en_cours',
 DATE_SUB(@maintenant, INTERVAL 20 MINUTE), NULL,
 'Surcharge réseau, coupure ponctuelle en cours de diagnostic.', 3, @u_gestionnaire,
 DATE_SUB(@maintenant, INTERVAL 20 MINUTE), @maintenant),

(NULL, 36.8712000, 10.3402000, 'Corniche de Sidi Bou Saïd', 'panne', 'en_cours',
 DATE_SUB(@maintenant, INTERVAL 2 HOUR), DATE_ADD(@maintenant, INTERVAL 1 HOUR),
 'Panne localisée sur la corniche, équipe sur place.', 12, @u_gestionnaire,
 DATE_SUB(@maintenant, INTERVAL 2 HOUR), @maintenant),

(@q_marsa, NULL, NULL, 'Avenue de la Plage', 'maintenance', 'en_cours',
 DATE_SUB(@maintenant, INTERVAL 30 MINUTE), DATE_ADD(@maintenant, INTERVAL 3 HOUR),
 'Maintenance en cours sur le poste de transformation.', 1, @u_gestionnaire,
 DATE_SUB(@maintenant, INTERVAL 30 MINUTE), @maintenant),

(@q_sidibou, NULL, NULL, 'Rue Habib Thameur', 'delestage', 'prevue',
 TIMESTAMP(DATE_ADD(CURDATE(), INTERVAL 1 DAY), '09:00:00'),
 TIMESTAMP(DATE_ADD(CURDATE(), INTERVAL 1 DAY), '11:00:00'),
 'Délestage programmé demain matin.', 0, @u_gestionnaire,
 @maintenant, @maintenant),

(@q_centre, NULL, NULL, 'Rue de Marseille', 'surcharge', 'prevue',
 TIMESTAMP(DATE_ADD(CURDATE(), INTERVAL 2 DAY), '14:00:00'),
 TIMESTAMP(DATE_ADD(CURDATE(), INTERVAL 2 DAY), '16:00:00'),
 'Surcharge annoncée, coupure préventive de deux heures.', 0, @u_gestionnaire,
 @maintenant, @maintenant),

(@q_ariana, NULL, NULL, 'Rue de l''Innovation', 'panne', 'prevue',
 TIMESTAMP(DATE_ADD(CURDATE(), INTERVAL 3 DAY), '10:00:00'),
 TIMESTAMP(DATE_ADD(CURDATE(), INTERVAL 3 DAY), '13:00:00'),
 'Remplacement de câble prévu, coupure de trois heures.', 0, @u_gestionnaire,
 @maintenant, @maintenant),

(@q_berges, NULL, NULL, 'Quai des Brumes', 'maintenance', 'prevue',
 TIMESTAMP(DATE_ADD(CURDATE(), INTERVAL 1 DAY), '08:00:00'),
 TIMESTAMP(DATE_ADD(CURDATE(), INTERVAL 1 DAY), '12:00:00'),
 'Maintenance planifiée sur le réseau électrique.', 0, @u_gestionnaire,
 @maintenant, @maintenant),

(@q_centre, NULL, NULL, 'Rue des Lilas', 'delestage', 'resolue',
 DATE_SUB(@maintenant, INTERVAL 3 DAY), DATE_SUB(@maintenant, INTERVAL 3 DAY) + INTERVAL 2 HOUR,
 'Délestage résolu après deux heures.', 5, @u_gestionnaire,
 DATE_SUB(@maintenant, INTERVAL 3 DAY), DATE_SUB(@maintenant, INTERVAL 3 DAY) + INTERVAL 2 HOUR),

(@q_marsa, NULL, NULL, 'Avenue de la Plage', 'surcharge', 'resolue',
 DATE_SUB(@maintenant, INTERVAL 5 DAY), DATE_SUB(@maintenant, INTERVAL 5 DAY) + INTERVAL 150 MINUTE,
 'Surcharge résolue, deux heures trente de coupure.', 4, @u_gestionnaire,
 DATE_SUB(@maintenant, INTERVAL 5 DAY), DATE_SUB(@maintenant, INTERVAL 5 DAY) + INTERVAL 150 MINUTE),

(@q_berges, NULL, NULL, 'Rue du Lac Léman', 'panne', 'resolue',
 DATE_SUB(@maintenant, INTERVAL 7 DAY), DATE_SUB(@maintenant, INTERVAL 7 DAY) + INTERVAL 3 HOUR,
 'Panne transformateur réparée en trois heures.', 9, @u_gestionnaire,
 DATE_SUB(@maintenant, INTERVAL 7 DAY), DATE_SUB(@maintenant, INTERVAL 7 DAY) + INTERVAL 3 HOUR),

(NULL, 36.8625000, 10.1956000, 'Poste El Ghazala', 'maintenance', 'resolue',
 DATE_SUB(@maintenant, INTERVAL 10 DAY), DATE_SUB(@maintenant, INTERVAL 10 DAY) + INTERVAL 3 HOUR,
 'Maintenance terminée sur le poste de quartier.', 2, @u_gestionnaire,
 DATE_SUB(@maintenant, INTERVAL 10 DAY), DATE_SUB(@maintenant, INTERVAL 10 DAY) + INTERVAL 3 HOUR);

-- ----------------------------------------------------------------------------
-- 8. Signalements (4 catégories × 3 urgences × 3 statuts)
-- ----------------------------------------------------------------------------
INSERT INTO signalements (user_id, residence_id, categorie, categorie_autre, urgence, description,
                          photo_path, statut, date_signalement, created_at, updated_at)
VALUES (@u_standard, @r_oliviers, 'fuite', NULL, 'normale',
        'Écoulement lent au sous-sol, seau en place.', NULL, 'nouveau',
        CURDATE(), DATE_SUB(@maintenant, INTERVAL 1 DAY), DATE_SUB(@maintenant, INTERVAL 1 DAY));
SET @s1 := LAST_INSERT_ID();

INSERT INTO signalements (user_id, residence_id, categorie, categorie_autre, urgence, description,
                          photo_path, statut, date_signalement, created_at, updated_at)
VALUES (@u_age, @r_manar, 'fuite', NULL, 'prioritaire',
        'Évier de la cuisine qui fuit depuis hier soir.', NULL, 'en_traitement',
        DATE_SUB(CURDATE(), INTERVAL 1 DAY), DATE_SUB(@maintenant, INTERVAL 2 DAY), DATE_SUB(@maintenant, INTERVAL 1 DAY));
SET @s2 := LAST_INSERT_ID();

INSERT INTO signalements (user_id, residence_id, categorie, categorie_autre, urgence, description,
                          photo_path, statut, date_signalement, created_at, updated_at)
VALUES (@u_enfant, @r_jasmins, 'fuite', NULL, 'vitale',
        'Fuite importante près du tableau électrique, risque de court-circuit.', NULL, 'resolu',
        DATE_SUB(CURDATE(), INTERVAL 2 DAY), DATE_SUB(@maintenant, INTERVAL 3 DAY), DATE_SUB(@maintenant, INTERVAL 2 DAY));
SET @s3 := LAST_INSERT_ID();

INSERT INTO signalements (user_id, residence_id, categorie, categorie_autre, urgence, description,
                          photo_path, statut, date_signalement, created_at, updated_at)
VALUES (@u_medical, @r_lac, 'panne_locale', NULL, 'normale',
        'Lampe du couloir du 2e étage hors service.', NULL, 'resolu',
        DATE_SUB(CURDATE(), INTERVAL 1 DAY), DATE_SUB(@maintenant, INTERVAL 2 DAY), DATE_SUB(@maintenant, INTERVAL 1 DAY));
SET @s4 := LAST_INSERT_ID();

INSERT INTO signalements (user_id, residence_id, categorie, categorie_autre, urgence, description,
                          photo_path, statut, date_signalement, created_at, updated_at)
VALUES (@u_standard, @r_oliviers, 'panne_locale', NULL, 'prioritaire',
        'Ascenseur bloqué entre le 1er et le 2e étage.', NULL, 'nouveau',
        DATE_SUB(CURDATE(), INTERVAL 3 DAY), DATE_SUB(@maintenant, INTERVAL 4 DAY), DATE_SUB(@maintenant, INTERVAL 3 DAY));
SET @s5 := LAST_INSERT_ID();

INSERT INTO signalements (user_id, residence_id, categorie, categorie_autre, urgence, description,
                          photo_path, statut, date_signalement, created_at, updated_at)
VALUES (@u_age, @r_manar, 'panne_locale', NULL, 'vitale',
        'Porte de secours du hall bloquée par des cartons.', NULL, 'en_traitement',
        DATE_SUB(CURDATE(), INTERVAL 4 DAY), DATE_SUB(@maintenant, INTERVAL 5 DAY), DATE_SUB(@maintenant, INTERVAL 4 DAY));
SET @s6 := LAST_INSERT_ID();

INSERT INTO signalements (user_id, residence_id, categorie, categorie_autre, urgence, description,
                          photo_path, statut, date_signalement, created_at, updated_at)
VALUES (@u_enfant, @r_jasmins, 'personne_vulnerable', NULL, 'normale',
        'Voisine âgée isolée, sans nouvelles depuis deux jours.', NULL, 'en_traitement',
        DATE_SUB(CURDATE(), INTERVAL 2 DAY), DATE_SUB(@maintenant, INTERVAL 3 DAY), DATE_SUB(@maintenant, INTERVAL 2 DAY));
SET @s7 := LAST_INSERT_ID();

INSERT INTO signalements (user_id, residence_id, categorie, categorie_autre, urgence, description,
                          photo_path, statut, date_signalement, created_at, updated_at)
VALUES (@u_medical, @r_lac, 'personne_vulnerable', NULL, 'prioritaire',
        'Personne sous respirateur sans électricité depuis trois heures.', NULL, 'nouveau',
        DATE_SUB(CURDATE(), INTERVAL 5 DAY), DATE_SUB(@maintenant, INTERVAL 6 DAY), DATE_SUB(@maintenant, INTERVAL 5 DAY));
SET @s8 := LAST_INSERT_ID();

INSERT INTO signalements (user_id, residence_id, categorie, categorie_autre, urgence, description,
                          photo_path, statut, date_signalement, created_at, updated_at)
VALUES (@u_standard, @r_oliviers, 'personne_vulnerable', NULL, 'vitale',
        'Malaise dans le hall, secours appelés.', NULL, 'resolu',
        DATE_SUB(CURDATE(), INTERVAL 6 DAY), DATE_SUB(@maintenant, INTERVAL 7 DAY), DATE_SUB(@maintenant, INTERVAL 6 DAY));
SET @s9 := LAST_INSERT_ID();

INSERT INTO signalements (user_id, residence_id, categorie, categorie_autre, urgence, description,
                          photo_path, statut, date_signalement, created_at, updated_at)
VALUES (@u_age, @r_manar, 'autre', 'Nuisance sonore', 'normale',
        'Nuisance sonore répétée après 23 h.', NULL, 'nouveau',
        DATE_SUB(CURDATE(), INTERVAL 3 DAY), DATE_SUB(@maintenant, INTERVAL 4 DAY), DATE_SUB(@maintenant, INTERVAL 3 DAY));
SET @s10 := LAST_INSERT_ID();

INSERT INTO signalements (user_id, residence_id, categorie, categorie_autre, urgence, description,
                          photo_path, statut, date_signalement, created_at, updated_at)
VALUES (@u_enfant, @r_jasmins, 'autre', 'Odeur de gaz', 'prioritaire',
        'Odeur de gaz dans la cage escalier.', NULL, 'en_traitement',
        DATE_SUB(CURDATE(), INTERVAL 7 DAY), DATE_SUB(@maintenant, INTERVAL 8 DAY), DATE_SUB(@maintenant, INTERVAL 7 DAY));
SET @s11 := LAST_INSERT_ID();

INSERT INTO signalements (user_id, residence_id, categorie, categorie_autre, urgence, description,
                          photo_path, statut, date_signalement, created_at, updated_at)
VALUES (@u_medical, @r_lac, 'autre', 'Dégât des eaux', 'vitale',
        'Dégât des eaux au plafond du 3e étage.', NULL, 'resolu',
        DATE_SUB(CURDATE(), INTERVAL 8 DAY), DATE_SUB(@maintenant, INTERVAL 9 DAY), DATE_SUB(@maintenant, INTERVAL 8 DAY));
SET @s12 := LAST_INSERT_ID();

-- Analyses de triage : une par signalement, scores et suggestions variés.
INSERT INTO signalement_analyses (signalement_id, score_urgence, urgence_suggeree, indicateurs_urgence,
                                  termes_urgents, signalements_lies, empreinte_description,
                                  version_modele, analysee_le, created_at, updated_at)
VALUES
(@s1, 18, 'normale',
 JSON_OBJECT('mots_cles', JSON_ARRAY('écoulement', 'sous-sol'), 'exclamations', 0, 'longueur', 55),
 JSON_ARRAY(), NULL,
 (SELECT SHA2(description, 256) FROM signalements WHERE id = @s1),
 'triage-1.0', DATE_SUB(@maintenant, INTERVAL 1 DAY) + INTERVAL 5 MINUTE,
 DATE_SUB(@maintenant, INTERVAL 1 DAY) + INTERVAL 5 MINUTE, DATE_SUB(@maintenant, INTERVAL 1 DAY) + INTERVAL 5 MINUTE),

(@s2, 46, 'prioritaire',
 JSON_OBJECT('mots_cles', JSON_ARRAY('fuite', 'cuisine'), 'exclamations', 0, 'longueur', 58),
 JSON_ARRAY('fuite'), NULL,
 (SELECT SHA2(description, 256) FROM signalements WHERE id = @s2),
 'triage-1.0', DATE_SUB(@maintenant, INTERVAL 2 DAY) + INTERVAL 5 MINUTE,
 DATE_SUB(@maintenant, INTERVAL 2 DAY) + INTERVAL 5 MINUTE, DATE_SUB(@maintenant, INTERVAL 2 DAY) + INTERVAL 5 MINUTE),

(@s3, 92, 'vitale',
 JSON_OBJECT('mots_cles', JSON_ARRAY('tableau électrique', 'court-circuit'), 'exclamations', 0, 'longueur', 76),
 JSON_ARRAY('court-circuit', 'électrique'), JSON_ARRAY(@s2),
 (SELECT SHA2(description, 256) FROM signalements WHERE id = @s3),
 'triage-1.0', DATE_SUB(@maintenant, INTERVAL 3 DAY) + INTERVAL 5 MINUTE,
 DATE_SUB(@maintenant, INTERVAL 3 DAY) + INTERVAL 5 MINUTE, DATE_SUB(@maintenant, INTERVAL 3 DAY) + INTERVAL 5 MINUTE),

(@s4, 12, 'normale',
 JSON_OBJECT('mots_cles', JSON_ARRAY('lampe', 'couloir'), 'exclamations', 0, 'longueur', 46),
 JSON_ARRAY(), NULL,
 (SELECT SHA2(description, 256) FROM signalements WHERE id = @s4),
 'triage-1.0', DATE_SUB(@maintenant, INTERVAL 2 DAY) + INTERVAL 5 MINUTE,
 DATE_SUB(@maintenant, INTERVAL 2 DAY) + INTERVAL 5 MINUTE, DATE_SUB(@maintenant, INTERVAL 2 DAY) + INTERVAL 5 MINUTE),

(@s5, 55, 'prioritaire',
 JSON_OBJECT('mots_cles', JSON_ARRAY('ascenseur', 'bloqué'), 'exclamations', 0, 'longueur', 51),
 JSON_ARRAY('bloqué'), NULL,
 (SELECT SHA2(description, 256) FROM signalements WHERE id = @s5),
 'triage-1.0', DATE_SUB(@maintenant, INTERVAL 4 DAY) + INTERVAL 5 MINUTE,
 DATE_SUB(@maintenant, INTERVAL 4 DAY) + INTERVAL 5 MINUTE, DATE_SUB(@maintenant, INTERVAL 4 DAY) + INTERVAL 5 MINUTE),

(@s6, 88, 'vitale',
 JSON_OBJECT('mots_cles', JSON_ARRAY('porte de secours', 'bloquée'), 'exclamations', 0, 'longueur', 55),
 JSON_ARRAY('secours', 'bloquée'), JSON_ARRAY(@s5),
 (SELECT SHA2(description, 256) FROM signalements WHERE id = @s6),
 'triage-1.0', DATE_SUB(@maintenant, INTERVAL 5 DAY) + INTERVAL 5 MINUTE,
 DATE_SUB(@maintenant, INTERVAL 5 DAY) + INTERVAL 5 MINUTE, DATE_SUB(@maintenant, INTERVAL 5 DAY) + INTERVAL 5 MINUTE),

(@s7, 38, 'normale',
 JSON_OBJECT('mots_cles', JSON_ARRAY('isolée', 'sans nouvelles'), 'exclamations', 0, 'longueur', 65),
 JSON_ARRAY('isolée'), NULL,
 (SELECT SHA2(description, 256) FROM signalements WHERE id = @s7),
 'triage-1.0', DATE_SUB(@maintenant, INTERVAL 3 DAY) + INTERVAL 5 MINUTE,
 DATE_SUB(@maintenant, INTERVAL 3 DAY) + INTERVAL 5 MINUTE, DATE_SUB(@maintenant, INTERVAL 3 DAY) + INTERVAL 5 MINUTE),

(@s8, 84, 'prioritaire',
 JSON_OBJECT('mots_cles', JSON_ARRAY('respirateur', 'sans électricité'), 'exclamations', 0, 'longueur', 70),
 JSON_ARRAY('respirateur', 'électricité'), JSON_ARRAY(@s6),
 (SELECT SHA2(description, 256) FROM signalements WHERE id = @s8),
 'triage-1.0', DATE_SUB(@maintenant, INTERVAL 6 DAY) + INTERVAL 5 MINUTE,
 DATE_SUB(@maintenant, INTERVAL 6 DAY) + INTERVAL 5 MINUTE, DATE_SUB(@maintenant, INTERVAL 6 DAY) + INTERVAL 5 MINUTE),

(@s9, 97, 'vitale',
 JSON_OBJECT('mots_cles', JSON_ARRAY('malaise', 'secours'), 'exclamations', 0, 'longueur', 45),
 JSON_ARRAY('malaise', 'secours'), NULL,
 (SELECT SHA2(description, 256) FROM signalements WHERE id = @s9),
 'triage-1.0', DATE_SUB(@maintenant, INTERVAL 7 DAY) + INTERVAL 5 MINUTE,
 DATE_SUB(@maintenant, INTERVAL 7 DAY) + INTERVAL 5 MINUTE, DATE_SUB(@maintenant, INTERVAL 7 DAY) + INTERVAL 5 MINUTE),

(@s10, 22, 'normale',
 JSON_OBJECT('mots_cles', JSON_ARRAY('nuisance sonore'), 'exclamations', 0, 'longueur', 44),
 JSON_ARRAY(), NULL,
 (SELECT SHA2(description, 256) FROM signalements WHERE id = @s10),
 'triage-1.0', DATE_SUB(@maintenant, INTERVAL 4 DAY) + INTERVAL 5 MINUTE,
 DATE_SUB(@maintenant, INTERVAL 4 DAY) + INTERVAL 5 MINUTE, DATE_SUB(@maintenant, INTERVAL 4 DAY) + INTERVAL 5 MINUTE),

(@s11, 69, 'prioritaire',
 JSON_OBJECT('mots_cles', JSON_ARRAY('gaz', 'odeur'), 'exclamations', 0, 'longueur', 44),
 JSON_ARRAY('gaz', 'odeur'), NULL,
 (SELECT SHA2(description, 256) FROM signalements WHERE id = @s11),
 'triage-1.0', DATE_SUB(@maintenant, INTERVAL 8 DAY) + INTERVAL 5 MINUTE,
 DATE_SUB(@maintenant, INTERVAL 8 DAY) + INTERVAL 5 MINUTE, DATE_SUB(@maintenant, INTERVAL 8 DAY) + INTERVAL 5 MINUTE),

(@s12, 90, 'vitale',
 JSON_OBJECT('mots_cles', JSON_ARRAY('dégât des eaux', 'plafond'), 'exclamations', 0, 'longueur', 50),
 JSON_ARRAY('dégât'), JSON_ARRAY(@s3, @s9),
 (SELECT SHA2(description, 256) FROM signalements WHERE id = @s12),
 'triage-1.0', DATE_SUB(@maintenant, INTERVAL 9 DAY) + INTERVAL 5 MINUTE,
 DATE_SUB(@maintenant, INTERVAL 9 DAY) + INTERVAL 5 MINUTE, DATE_SUB(@maintenant, INTERVAL 9 DAY) + INTERVAL 5 MINUTE);

-- ----------------------------------------------------------------------------
-- 9. Points de fraicheur (3 types, 3 statuts, préférences variées)
-- ----------------------------------------------------------------------------
INSERT INTO points_fraicheur (nom, type, description, adresse, latitude, longitude, capacite,
                              ouvert_24h, heure_ouverture, heure_fermeture, photo,
                              accessible_pmr, climatise, ombrage, eau_potable,
                              statut, motif_refus, valide_par, valide_le, quartier_id, user_id,
                              created_at, updated_at)
VALUES ('Parc ombragé du Belvédère', 'parc', 'Grand parc arboré, bancs à l''ombre.',
        'Rue du Parc', 36.8035000, 10.1760000, 300, 0, '07:00:00', '21:00:00', NULL,
        1, 0, 1, 1, 'valide', NULL, @u_admin, @maintenant, @q_centre, @u_standard,
        @maintenant, @maintenant);
SET @pf1 := LAST_INSERT_ID();

INSERT INTO points_fraicheur (nom, type, description, adresse, latitude, longitude, capacite,
                              ouvert_24h, heure_ouverture, heure_fermeture, photo,
                              accessible_pmr, climatise, ombrage, eau_potable,
                              statut, motif_refus, valide_par, valide_le, quartier_id, user_id,
                              created_at, updated_at)
VALUES ('Salle climatisée El Manar', 'salle_climatisee', 'Salle communale climatisée avec eau fraiche.',
        '5 Rue de la Liberté', 36.8020000, 10.1815000, 60, 0, '08:00:00', '20:00:00', NULL,
        1, 1, 0, 1, 'valide', NULL, @u_admin, @maintenant, @q_centre, @u_age,
        @maintenant, @maintenant);
SET @pf2 := LAST_INSERT_ID();

INSERT INTO points_fraicheur (nom, type, description, adresse, latitude, longitude, capacite,
                              ouvert_24h, heure_ouverture, heure_fermeture, photo,
                              accessible_pmr, climatise, ombrage, eau_potable,
                              statut, motif_refus, valide_par, valide_le, quartier_id, user_id,
                              created_at, updated_at)
VALUES ('Fontaine de La Marsa', 'fontaine', 'Fontaine publique accessible jour et nuit.',
        'Avenue de la Plage', 36.8782000, 10.3247000, NULL, 1, NULL, NULL, NULL,
        0, 0, 0, 1, 'valide', NULL, @u_admin, @maintenant, @q_marsa, @u_enfant,
        @maintenant, @maintenant);
SET @pf3 := LAST_INSERT_ID();

INSERT INTO points_fraicheur (nom, type, description, adresse, latitude, longitude, capacite,
                              ouvert_24h, heure_ouverture, heure_fermeture, photo,
                              accessible_pmr, climatise, ombrage, eau_potable,
                              statut, motif_refus, valide_par, valide_le, quartier_id, user_id,
                              created_at, updated_at)
VALUES ('Parc de quartier Ariana', 'parc', 'Parc de quartier avec jeux pour enfants.',
        'Rue de l''Innovation', 36.8625000, 10.1956000, 90, 0, '06:00:00', '22:00:00', NULL,
        1, 0, 1, 1, 'valide', NULL, @u_admin, @maintenant, @q_ariana, @u_standard,
        @maintenant, @maintenant);
SET @pf4 := LAST_INSERT_ID();

INSERT INTO points_fraicheur (nom, type, description, adresse, latitude, longitude, capacite,
                              ouvert_24h, heure_ouverture, heure_fermeture, photo,
                              accessible_pmr, climatise, ombrage, eau_potable,
                              statut, motif_refus, valide_par, valide_le, quartier_id, user_id,
                              created_at, updated_at)
VALUES ('Parc des Jasmins', 'parc', 'Espace vert proposé par un habitant.',
        'Avenue de la Plage', 36.8790000, 10.3250000, 120, 0, '06:00:00', '22:00:00', NULL,
        1, 0, 1, 1, 'en_attente', NULL, NULL, NULL, @q_marsa, @u_enfant,
        @maintenant, @maintenant);

INSERT INTO points_fraicheur (nom, type, description, adresse, latitude, longitude, capacite,
                              ouvert_24h, heure_ouverture, heure_fermeture, photo,
                              accessible_pmr, climatise, ombrage, eau_potable,
                              statut, motif_refus, valide_par, valide_le, quartier_id, user_id,
                              created_at, updated_at)
VALUES ('Salle climatisée Ariana', 'salle_climatisee', 'Salle associative proposée par un habitant.',
        'Rue de l''Innovation', 36.8630000, 10.1960000, 40, 0, '09:00:00', '17:00:00', NULL,
        1, 1, 0, 0, 'en_attente', NULL, NULL, NULL, @q_ariana, @u_standard,
        @maintenant, @maintenant);

INSERT INTO points_fraicheur (nom, type, description, adresse, latitude, longitude, capacite,
                              ouvert_24h, heure_ouverture, heure_fermeture, photo,
                              accessible_pmr, climatise, ombrage, eau_potable,
                              statut, motif_refus, valide_par, valide_le, quartier_id, user_id,
                              created_at, updated_at)
VALUES ('Fontaine Sidi Bou Saïd', 'fontaine', 'Fontaine proposée près du port.',
        'Rue Habib Thameur', 36.8710000, 10.3400000, NULL, 1, NULL, NULL, NULL,
        0, 0, 0, 1, 'en_attente', NULL, NULL, NULL, @q_sidibou, @u_age,
        @maintenant, @maintenant);

INSERT INTO points_fraicheur (nom, type, description, adresse, latitude, longitude, capacite,
                              ouvert_24h, heure_ouverture, heure_fermeture, photo,
                              accessible_pmr, climatise, ombrage, eau_potable,
                              statut, motif_refus, valide_par, valide_le, quartier_id, user_id,
                              created_at, updated_at)
VALUES ('Jardin de la villa El Aïda', 'parc', 'Terrain privé proposé par un habitant.',
        'Rue de la Corniche', 36.8720000, 10.3420000, NULL, 0, '08:00:00', '18:00:00', NULL,
        0, 0, 1, 0, 'refuse', 'Terrain privé, accès non autorisé', @u_admin, @maintenant, @q_sidibou, @u_standard,
        @maintenant, @maintenant);

INSERT INTO points_fraicheur (nom, type, description, adresse, latitude, longitude, capacite,
                              ouvert_24h, heure_ouverture, heure_fermeture, photo,
                              accessible_pmr, climatise, ombrage, eau_potable,
                              statut, motif_refus, valide_par, valide_le, quartier_id, user_id,
                              created_at, updated_at)
VALUES ('Salle de réunion Ibn Khaldoun', 'salle_climatisee', 'Salle de réunion sans équipement de froid.',
        'Rue de l''Innovation', 36.8610000, 10.1940000, 25, 0, '09:00:00', '17:00:00', NULL,
        0, 0, 0, 0, 'refuse', 'Salle non climatisée', @u_admin, @maintenant, @q_ariana, @u_enfant,
        @maintenant, @maintenant);

INSERT INTO points_fraicheur (nom, type, description, adresse, latitude, longitude, capacite,
                              ouvert_24h, heure_ouverture, heure_fermeture, photo,
                              accessible_pmr, climatise, ombrage, eau_potable,
                              statut, motif_refus, valide_par, valide_le, quartier_id, user_id,
                              created_at, updated_at)
VALUES ('Fontaine du Quai des Brumes', 'fontaine', 'Point d''eau signalé par un habitant.',
        'Quai des Brumes', 36.8330000, 10.2810000, NULL, 0, NULL, NULL, NULL,
        0, 0, 0, 0, 'refuse', 'Eau non potable', @u_admin, @maintenant, @q_berges, @u_medical,
        @maintenant, @maintenant);

-- ----------------------------------------------------------------------------
-- 10. Avis (notes 1 à 5, trois sentiments, trois niveaux d affluence)
-- ----------------------------------------------------------------------------
INSERT INTO avis (point_fraicheur_id, user_id, note, commentaire, affluence, sentiment, score_sentiment, created_at, updated_at) VALUES
(@pf1, @u_standard, 5, 'Très ombragé et calme, parfait en fin de journée.', 'moyenne', 'positif', 0.920, DATE_SUB(@maintenant, INTERVAL 3 DAY), DATE_SUB(@maintenant, INTERVAL 3 DAY)),
(@pf1, @u_age,      4, 'Bancs à l''ombre, eau disponible.', 'faible', 'positif', 0.780, DATE_SUB(@maintenant, INTERVAL 6 DAY), DATE_SUB(@maintenant, INTERVAL 6 DAY)),
(@pf1, @u_enfant,   3, 'Correct mais beaucoup de monde à midi.', 'forte', 'neutre', 0.500, DATE_SUB(@maintenant, INTERVAL 9 DAY), DATE_SUB(@maintenant, INTERVAL 9 DAY)),
(@pf1, @u_medical,  5, 'Endroit frais, je recommande.', 'moyenne', 'positif', 0.880, DATE_SUB(@maintenant, INTERVAL 12 DAY), DATE_SUB(@maintenant, INTERVAL 12 DAY)),

(@pf2, @u_standard, 4, 'Salle propre et bien climatisée.', 'faible', 'positif', 0.700, DATE_SUB(@maintenant, INTERVAL 2 DAY), DATE_SUB(@maintenant, INTERVAL 2 DAY)),
(@pf2, @u_age,      2, 'Trop de monde, pas de place assise.', 'forte', 'negatif', 0.150, DATE_SUB(@maintenant, INTERVAL 5 DAY), DATE_SUB(@maintenant, INTERVAL 5 DAY)),
(@pf2, @u_enfant,   5, 'Très frais, accueil agréable.', 'moyenne', 'positif', 0.930, DATE_SUB(@maintenant, INTERVAL 8 DAY), DATE_SUB(@maintenant, INTERVAL 8 DAY)),
(@pf2, @u_medical,  4, 'Eau fraiche disponible, bon point.', 'faible', 'positif', 0.760, DATE_SUB(@maintenant, INTERVAL 11 DAY), DATE_SUB(@maintenant, INTERVAL 11 DAY)),

(@pf3, @u_standard, 3, 'Eau tiède en fin de journée.', 'moyenne', 'neutre', 0.450, DATE_SUB(@maintenant, INTERVAL 1 DAY), DATE_SUB(@maintenant, INTERVAL 1 DAY)),
(@pf3, @u_age,      4, 'Toujours accessible, même la nuit.', 'faible', 'positif', 0.680, DATE_SUB(@maintenant, INTERVAL 4 DAY), DATE_SUB(@maintenant, INTERVAL 4 DAY)),
(@pf3, @u_enfant,   1, 'Fontaine sale, eau trouble.', 'forte', 'negatif', 0.050, DATE_SUB(@maintenant, INTERVAL 7 DAY), DATE_SUB(@maintenant, INTERVAL 7 DAY)),
(@pf3, @u_medical,  2, 'Pas d''ombre autour de la fontaine.', 'forte', 'negatif', 0.120, DATE_SUB(@maintenant, INTERVAL 10 DAY), DATE_SUB(@maintenant, INTERVAL 10 DAY));

-- ----------------------------------------------------------------------------
-- 11. Prédictions IA canicule (modèle, règles, correction, accord/désaccord)
-- ----------------------------------------------------------------------------
INSERT INTO ai_predictions (alerte_id, quartier_ids, context, niveau_modele, probabilites,
                            niveau_regles, niveau_final, model_version, source, created_at, updated_at) VALUES
(@a_rouge, JSON_ARRAY(@q_centre, @q_marsa),
 JSON_OBJECT('temperature', 42.5, 'ressentie', 46.2, 'humidite', 35, 'seuil', 40,
             'historique', JSON_ARRAY(JSON_OBJECT('date', 'J-1', 'niveau', 'orange')),
             'previsions', JSON_ARRAY(JSON_OBJECT('date', 'J+1', 'temperature', 43.0))),
 'rouge', JSON_OBJECT('normal', 0.01, 'jaune', 0.02, 'orange', 0.07, 'rouge', 0.90),
 'rouge', 'rouge', 'chillnet-1.0', 'modele', @maintenant, @maintenant),

(@a_orange, JSON_ARRAY(@q_ariana, @q_sidibou),
 JSON_OBJECT('temperature', 38.0, 'ressentie', 40.5, 'humidite', 55, 'seuil', 37,
             'historique', JSON_ARRAY(JSON_OBJECT('date', 'J-1', 'niveau', 'jaune')),
             'previsions', JSON_ARRAY(JSON_OBJECT('date', 'J+1', 'temperature', 39.0))),
 'orange', JSON_OBJECT('normal', 0.03, 'jaune', 0.15, 'orange', 0.62, 'rouge', 0.20),
 'orange', 'orange', 'chillnet-1.0', 'modele', @maintenant, @maintenant),

(@a_jaune, JSON_ARRAY(@q_marsa),
 JSON_OBJECT('temperature', 35.2, 'ressentie', 36.4, 'humidite', 60, 'seuil', 35,
             'historique', JSON_ARRAY(), 'previsions', JSON_ARRAY(JSON_OBJECT('date', 'J+1', 'temperature', 35.5))),
 'jaune', JSON_OBJECT('normal', 0.20, 'jaune', 0.55, 'orange', 0.20, 'rouge', 0.05),
 'jaune', 'jaune', 'chillnet-1.0', 'modele', @maintenant, @maintenant),

(@a_terminee, JSON_ARRAY(@q_centre),
 JSON_OBJECT('temperature', 39.4, 'ressentie', 42.1, 'humidite', 48, 'seuil', 37,
             'historique', JSON_ARRAY(JSON_OBJECT('date', 'J-2', 'niveau', 'orange')),
             'previsions', JSON_ARRAY()),
 'orange', JSON_OBJECT('normal', 0.02, 'jaune', 0.10, 'orange', 0.58, 'rouge', 0.30),
 'jaune', 'rouge', 'chillnet-1.0', 'modele', DATE_SUB(@maintenant, INTERVAL 4 DAY), DATE_SUB(@maintenant, INTERVAL 4 DAY)),

(NULL, JSON_ARRAY(@q_ariana),
 JSON_OBJECT('temperature', 40.2, 'ressentie', 43.0, 'humidite', 40, 'seuil', 40,
             'historique', JSON_ARRAY(), 'previsions', JSON_ARRAY()),
 'orange', JSON_OBJECT('normal', 0.01, 'jaune', 0.09, 'orange', 0.60, 'rouge', 0.30),
 'jaune', NULL, 'chillnet-1.0', 'modele', @maintenant, @maintenant),

(NULL, JSON_ARRAY(@q_marsa),
 JSON_OBJECT('temperature', 34.8, 'ressentie', 35.9, 'humidite', 62, 'seuil', 35,
             'historique', JSON_ARRAY(), 'previsions', JSON_ARRAY()),
 NULL, NULL, 'jaune', NULL, 'chillnet-1.0', 'regles', @maintenant, @maintenant);

-- ----------------------------------------------------------------------------
-- 12. Instantanés de risque coupure (labellisés et non labellisés)
-- ----------------------------------------------------------------------------
INSERT INTO coupure_risk_snapshots (quartier_id, captured_at, features, p24, p48, score,
                                    model_version, outcome_24h, outcome_48h, labeled_at,
                                    created_at, updated_at) VALUES
(@q_centre, @maintenant,
 JSON_OBJECT('frequence', 3, 'en_cours', 1, 'chaudes', 2, 'confirmations', 12,
             'niveau_chaleur', 'rouge', 'temperature', 42.5, 'coupures_7j', 3,
             'coupures_14j', 5, 'part_chaleur_14j', 0.60, 'heures_depuis_derniere', 0.5,
             'confirmations_total', 12, 'ressentie', 46.2, 'heat_index', 46.2,
             'max_24h', 42.5, 'max_48h', 43.0, 'canicule_niveau', 3,
             'jours_chauds_consecutifs', 4, 'heure', 16, 'jour_semaine', 1,
             'nb_logements_zone', 78, 'nb_residences', 2),
 0.78, 0.81, 82, 'chillnet-1.0', NULL, NULL, NULL, @maintenant, @maintenant),

(@q_ariana, @maintenant,
 JSON_OBJECT('frequence', 2, 'en_cours', 1, 'chaudes', 1, 'confirmations', 3,
             'niveau_chaleur', 'orange', 'temperature', 38.0, 'coupures_7j', 2,
             'coupures_14j', 4, 'part_chaleur_14j', 0.50, 'heures_depuis_derniere', 0.3,
             'confirmations_total', 3, 'ressentie', 40.5, 'heat_index', 40.5,
             'max_24h', 38.0, 'max_48h', 39.0, 'canicule_niveau', 2,
             'jours_chauds_consecutifs', 3, 'heure', 16, 'jour_semaine', 1,
             'nb_logements_zone', 36, 'nb_residences', 1),
 0.52, 0.60, 55, 'chillnet-1.0', NULL, NULL, NULL, @maintenant, @maintenant),

(@q_marsa, @maintenant,
 JSON_OBJECT('frequence', 1, 'en_cours', 0, 'chaudes', 1, 'confirmations', 0,
             'niveau_chaleur', 'jaune', 'temperature', 35.2, 'coupures_7j', 1,
             'coupures_14j', 2, 'part_chaleur_14j', 0.50, 'heures_depuis_derniere', 30.0,
             'confirmations_total', 0, 'ressentie', 36.4, 'heat_index', 36.4,
             'max_24h', 35.2, 'max_48h', 35.5, 'canicule_niveau', 1,
             'jours_chauds_consecutifs', 1, 'heure', 16, 'jour_semaine', 1,
             'nb_logements_zone', 36, 'nb_residences', 2),
 0.18, 0.24, 21, 'chillnet-1.0', NULL, NULL, NULL, @maintenant, @maintenant),

(@q_sidibou, @maintenant,
 JSON_OBJECT('frequence', 0, 'en_cours', 0, 'chaudes', 0, 'confirmations', 0,
             'niveau_chaleur', 'orange', 'temperature', 38.0, 'coupures_7j', 0,
             'coupures_14j', 0, 'part_chaleur_14j', 0.0, 'heures_depuis_derniere', 336.0,
             'confirmations_total', 0, 'ressentie', 40.5, 'heat_index', 40.5,
             'max_24h', 38.0, 'max_48h', 40.0, 'canicule_niveau', 2,
             'jours_chauds_consecutifs', 3, 'heure', 16, 'jour_semaine', 1,
             'nb_logements_zone', 18, 'nb_residences', 1),
 0.34, 0.40, 35, 'chillnet-1.0', NULL, NULL, NULL, @maintenant, @maintenant),

(@q_centre, DATE_SUB(@maintenant, INTERVAL 3 DAY),
 JSON_OBJECT('frequence', 2, 'en_cours', 0, 'chaudes', 1, 'confirmations', 5,
             'niveau_chaleur', 'orange', 'temperature', 39.4, 'coupures_7j', 2,
             'coupures_14j', 4, 'part_chaleur_14j', 0.50, 'heures_depuis_derniere', 12.0,
             'confirmations_total', 5, 'ressentie', 42.1, 'heat_index', 42.1,
             'max_24h', 39.4, 'max_48h', 40.0, 'canicule_niveau', 2,
             'jours_chauds_consecutifs', 2, 'heure', 11, 'jour_semaine', 5,
             'nb_logements_zone', 78, 'nb_residences', 2),
 0.64, 0.70, 66, 'chillnet-1.0', 1, 1, DATE_SUB(@maintenant, INTERVAL 2 DAY),
 DATE_SUB(@maintenant, INTERVAL 3 DAY), DATE_SUB(@maintenant, INTERVAL 2 DAY)),

(@q_ariana, DATE_SUB(@maintenant, INTERVAL 3 DAY),
 JSON_OBJECT('frequence', 1, 'en_cours', 0, 'chaudes', 1, 'confirmations', 2,
             'niveau_chaleur', 'jaune', 'temperature', 36.0, 'coupures_7j', 1,
             'coupures_14j', 2, 'part_chaleur_14j', 0.50, 'heures_depuis_derniere', 24.0,
             'confirmations_total', 2, 'ressentie', 37.5, 'heat_index', 37.5,
             'max_24h', 36.0, 'max_48h', 37.0, 'canicule_niveau', 1,
             'jours_chauds_consecutifs', 1, 'heure', 11, 'jour_semaine', 5,
             'nb_logements_zone', 36, 'nb_residences', 1),
 0.22, 0.30, 28, 'chillnet-1.0', 0, 0, DATE_SUB(@maintenant, INTERVAL 2 DAY),
 DATE_SUB(@maintenant, INTERVAL 3 DAY), DATE_SUB(@maintenant, INTERVAL 2 DAY)),

(@q_marsa, DATE_SUB(@maintenant, INTERVAL 3 DAY),
 JSON_OBJECT('frequence', 0, 'en_cours', 0, 'chaudes', 0, 'confirmations', 0,
             'niveau_chaleur', 'jaune', 'temperature', 35.0, 'coupures_7j', 0,
             'coupures_14j', 1, 'part_chaleur_14j', 0.0, 'heures_depuis_derniere', 48.0,
             'confirmations_total', 0, 'ressentie', 36.0, 'heat_index', 36.0,
             'max_24h', 35.0, 'max_48h', 36.0, 'canicule_niveau', 1,
             'jours_chauds_consecutifs', 1, 'heure', 11, 'jour_semaine', 5,
             'nb_logements_zone', 36, 'nb_residences', 2),
 0.15, 0.20, 17, 'chillnet-1.0', 0, 0, DATE_SUB(@maintenant, INTERVAL 2 DAY),
 DATE_SUB(@maintenant, INTERVAL 3 DAY), DATE_SUB(@maintenant, INTERVAL 2 DAY)),

(@q_sidibou, DATE_SUB(@maintenant, INTERVAL 3 DAY),
 JSON_OBJECT('frequence', 2, 'en_cours', 1, 'chaudes', 1, 'confirmations', 8,
             'niveau_chaleur', 'orange', 'temperature', 38.5, 'coupures_7j', 2,
             'coupures_14j', 3, 'part_chaleur_14j', 0.50, 'heures_depuis_derniere', 1.0,
             'confirmations_total', 8, 'ressentie', 41.0, 'heat_index', 41.0,
             'max_24h', 38.5, 'max_48h', 39.5, 'canicule_niveau', 2,
             'jours_chauds_consecutifs', 2, 'heure', 11, 'jour_semaine', 5,
             'nb_logements_zone', 18, 'nb_residences', 1),
 0.58, 0.63, 60, 'chillnet-1.0', 1, 0, DATE_SUB(@maintenant, INTERVAL 2 DAY),
 DATE_SUB(@maintenant, INTERVAL 3 DAY), DATE_SUB(@maintenant, INTERVAL 2 DAY));

-- ----------------------------------------------------------------------------
-- 13. Instantanés santé des points de fraicheur (4 niveaux)
-- ----------------------------------------------------------------------------
INSERT INTO point_sante_snapshots (point_fraicheur_id, captured_at, features, p14, score,
                                   niveau, source, model_version, outcome_14j, labeled_at,
                                   created_at, updated_at) VALUES
(@pf1, @maintenant,
 JSON_OBJECT('type', 'parc', 'type_code', 0, 'capacite', 300, 'climatise', 0, 'ombrage', 1,
             'eau_potable', 1, 'accessible_pmr', 1, 'heures_ouverture_jour', 14,
             'anciennete_jours', 30, 'a_photo', 0, 'note_moyenne', 4.4, 'nb_avis_30j', 4,
             'note_moyenne_30j', 4.4, 'evolution_note_30j', 0.1, 'part_negatifs_30j', 0.0,
             'jours_depuis_dernier_negatif', 60, 'part_affluence_forte_30j', 0.25,
             'sentiment_moyen_30j', 0.77, 'temperature', 42.5, 'canicule_niveau', 3, 'mois', 10),
 0.05, 12, 'bon', 'modele', 'chillnet-1.0', NULL, NULL, @maintenant, @maintenant),

(@pf2, @maintenant,
 JSON_OBJECT('type', 'salle_climatisee', 'type_code', 1, 'capacite', 60, 'climatise', 1, 'ombrage', 0,
             'eau_potable', 1, 'accessible_pmr', 1, 'heures_ouverture_jour', 12,
             'anciennete_jours', 25, 'a_photo', 0, 'note_moyenne', 3.8, 'nb_avis_30j', 4,
             'note_moyenne_30j', 3.8, 'evolution_note_30j', -0.4, 'part_negatifs_30j', 0.25,
             'jours_depuis_dernier_negatif', 5, 'part_affluence_forte_30j', 0.25,
             'sentiment_moyen_30j', 0.44, 'temperature', 42.5, 'canicule_niveau', 3, 'mois', 10),
 0.34, 38, 'a_surveiller', 'regles', 'chillnet-1.0', NULL, NULL, @maintenant, @maintenant),

(@pf3, @maintenant,
 JSON_OBJECT('type', 'fontaine', 'type_code', 2, 'capacite', 0, 'climatise', 0, 'ombrage', 0,
             'eau_potable', 1, 'accessible_pmr', 0, 'heures_ouverture_jour', 24,
             'anciennete_jours', 20, 'a_photo', 0, 'note_moyenne', 2.6, 'nb_avis_30j', 4,
             'note_moyenne_30j', 2.6, 'evolution_note_30j', -1.1, 'part_negatifs_30j', 0.50,
             'jours_depuis_dernier_negatif', 1, 'part_affluence_forte_30j', 0.75,
             'sentiment_moyen_30j', 0.23, 'temperature', 42.5, 'canicule_niveau', 3, 'mois', 10),
 0.62, 64, 'degrade', 'modele', 'chillnet-1.0', 0, DATE_SUB(@maintenant, INTERVAL 1 DAY),
 DATE_SUB(@maintenant, INTERVAL 2 DAY), DATE_SUB(@maintenant, INTERVAL 1 DAY)),

(@pf4, @maintenant,
 JSON_OBJECT('type', 'parc', 'type_code', 0, 'capacite', 90, 'climatise', 0, 'ombrage', 1,
             'eau_potable', 1, 'accessible_pmr', 1, 'heures_ouverture_jour', 16,
             'anciennete_jours', 15, 'a_photo', 0, 'note_moyenne', 2.1, 'nb_avis_30j', 4,
             'note_moyenne_30j', 2.1, 'evolution_note_30j', -1.8, 'part_negatifs_30j', 0.75,
             'jours_depuis_dernier_negatif', 0, 'part_affluence_forte_30j', 1.0,
             'sentiment_moyen_30j', 0.12, 'temperature', 42.5, 'canicule_niveau', 3, 'mois', 10),
 0.86, 88, 'critique', 'modele', 'chillnet-1.0', 1, DATE_SUB(@maintenant, INTERVAL 1 DAY),
 DATE_SUB(@maintenant, INTERVAL 2 DAY), DATE_SUB(@maintenant, INTERVAL 1 DAY));

COMMIT;

-- ----------------------------------------------------------------------------
-- Récapitulatif attendu après exécution :
--   6 comptes de scénario (1 admin, 1 gestionnaire, 4 habitants)
--   5 alertes, 12 coupures, 12 signalements analysés
--   10 points de fraicheur (4 validés, 3 en attente, 3 refusés)
--   12 avis, 9 équipements, 6 prédictions, 8 + 4 instantanés IA
-- ----------------------------------------------------------------------------
