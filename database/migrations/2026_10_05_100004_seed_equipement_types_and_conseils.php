<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Données initiales : types d'équipements sensibles + conseils de base.
 * Ces données peuvent être enrichies via le back office.
 *
 * Module 4 — Ghazi.
 */
return new class extends Migration
{
    public function up(): void
    {
        // -- Types d'équipements -----------------------------------------------
        $types = [
            ['nom' => 'Réfrigérateur / congélateur', 'slug' => 'refrigerateur',  'icone' => 'kitchen',         'medical' => false, 'description' => 'Appareil de conservation alimentaire sensible aux coupures prolongées.',               'ordre' => 1],
            ['nom' => 'Climatiseur',                  'slug' => 'climatiseur',     'icone' => 'mode_cool',        'medical' => false, 'description' => 'Système de refroidissement, consommateur électrique critique en canicule.',             'ordre' => 2],
            ['nom' => 'Ventilateur / brumisateur',   'slug' => 'ventilateur',     'icone' => 'mode_fan',         'medical' => false, 'description' => 'Appareil de confort thermique portable.',                                               'ordre' => 3],
            ['nom' => 'Respirateur médical',         'slug' => 'respirateur',     'icone' => 'pulmonology',      'medical' => true,  'description' => 'CPAP, BiPAP, ventilateur assisté — alimentation continue critique.',                   'ordre' => 4],
            ['nom' => 'Appareil médical électrique', 'slug' => 'medical_autre',   'icone' => 'health_and_safety','medical' => true,  'description' => 'Dialyse à domicile, pompe à perfusion, moniteur cardiaque…',                           'ordre' => 5],
            ['nom' => 'Chauffe-eau électrique',      'slug' => 'chauffe_eau',     'icone' => 'water_heater',     'medical' => false, 'description' => 'Ballon d\'eau chaude — peut être délésté lors des coupures programmées.',              'ordre' => 6],
            ['nom' => 'Onduleur / batterie de secours','slug' => 'onduleur',      'icone' => 'battery_charging_full','medical' => false,'description' => 'Source d\'alimentation de secours autonome.',                                      'ordre' => 7],
            ['nom' => 'Autre équipement',            'slug' => 'autre',           'icone' => 'devices',          'medical' => false, 'description' => 'Tout autre équipement sensible à déclarer.',                                           'ordre' => 99],
        ];

        $now = now()->toDateTimeString();
        foreach ($types as &$t) {
            $t['actif'] = true;
            $t['created_at'] = $now;
            $t['updated_at'] = $now;
        }

        DB::table('equipement_types')->insert($types);

        // -- Conseils de base --------------------------------------------------
        $refId      = DB::table('equipement_types')->where('slug', 'refrigerateur')->value('id');
        $climId     = DB::table('equipement_types')->where('slug', 'climatiseur')->value('id');
        $respId     = DB::table('equipement_types')->where('slug', 'respirateur')->value('id');
        $medId      = DB::table('equipement_types')->where('slug', 'medical_autre')->value('id');
        $ondId      = DB::table('equipement_types')->where('slug', 'onduleur')->value('id');

        $conseils = [
            [
                'titre' => 'Chaîne du froid : ne pas ouvrir le réfrigérateur',
                'categorie' => 'equipements',
                'contenu' => "En cas de coupure, gardez le réfrigérateur fermé : un frigo bien fermé maintient sa température 4 à 6 heures, un congélateur plein jusqu'à 48 heures. Jetez viandes et produits laitiers après 2h à plus de 8°C.",
                'icone' => 'kitchen',
                'ordre' => 1,
                'types' => [$refId],
            ],
            [
                'titre' => 'Climatiseur : coupez avant la coupure programmée',
                'categorie' => 'energie',
                'contenu' => "Éteignez le climatiseur 30 minutes avant une coupure prévue pour éviter la surtension au redémarrage. Réglez-le à 26°C minimum pour réduire la consommation.",
                'icone' => 'mode_cool',
                'ordre' => 2,
                'types' => [$climId],
            ],
            [
                'titre' => 'Respirateur médical : préparez la batterie de secours',
                'categorie' => 'medical',
                'contenu' => "Chargez la batterie de secours de votre respirateur avant 17h00 (pic réseau). En cas de coupure imprévue, contactez votre relais santé immédiatement. En urgence vitale, appelez le 190 sans attendre.",
                'icone' => 'pulmonology',
                'ordre' => 3,
                'types' => [$respId, $medId],
            ],
            [
                'titre' => 'Onduleur : vérifiez l\'autonomie avant la saison',
                'categorie' => 'equipements',
                'contenu' => "Testez l'autonomie réelle de votre onduleur chaque année. Un onduleur de 600 VA alimentera un respirateur CPAP environ 4 à 6 heures. Branchez en priorité les équipements médicaux critiques.",
                'icone' => 'battery_charging_full',
                'ordre' => 4,
                'types' => [$ondId, $respId],
            ],
            [
                'titre' => 'Hydratation : buvez sans attendre la soif',
                'categorie' => 'hydratation',
                'contenu' => "Par forte chaleur, buvez au moins 1,5 L d'eau par heure de chaleur intense. Évitez les boissons glacées (<10°C) qui coupent la transpiration. Préférez l'eau à température ambiante.",
                'icone' => 'water_drop',
                'ordre' => 5,
                'types' => [],
            ],
            [
                'titre' => 'Médicaments thermosensibles : protocole coupure froide',
                'categorie' => 'medical',
                'contenu' => "Placez insuline, vaccins et collyres dans le bac à légumes (zone la plus stable du frigo). N'utilisez jamais de glaçons en contact direct. Un flacon entamé d'insuline supporte 30°C pendant 28 jours.",
                'icone' => 'medication_liquid',
                'ordre' => 6,
                'types' => [$refId, $medId],
            ],
            [
                'titre' => 'Préparez un kit d\'urgence chaleur',
                'categorie' => 'general',
                'contenu' => "Gardez à portée : brumisateur manuel, linge humide, eau fraîche, radio à piles, lampe torche, liste des numéros d'urgence (190 SAMU, 198 Protection Civile, 0800 06 66 66 Canicule Info).",
                'icone' => 'emergency_home',
                'ordre' => 7,
                'types' => [],
            ],
        ];

        foreach ($conseils as $c) {
            $types = $c['types'];
            unset($c['types']);
            $c['actif'] = true;
            $c['user_id'] = null;
            $c['created_at'] = $now;
            $c['updated_at'] = $now;

            $id = DB::table('conseils')->insertGetId($c);

            foreach ($types as $typeId) {
                if ($typeId) {
                    DB::table('conseil_equipement_type')->insert([
                        'conseil_id' => $id,
                        'equipement_type_id' => $typeId,
                    ]);
                }
            }
        }
    }

    public function down(): void
    {
        DB::table('conseil_equipement_type')->truncate();
        DB::table('conseils')->truncate();
        DB::table('equipement_types')->truncate();
    }
};
