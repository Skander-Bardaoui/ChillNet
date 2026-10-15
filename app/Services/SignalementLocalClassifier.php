<?php

namespace App\Services;

use Illuminate\Support\Str;

class SignalementLocalClassifier
{
    private const EXEMPLES = [
        'fuite' => [
            'Il y a une fuite d’eau dans la salle de bain',
            'De l’eau coule du plafond et mouille le sol',
            'Une canalisation fuit dans le couloir',
            'Le robinet est cassé et laisse couler de l’eau',
            'Une infiltration apparaît sur le mur',
            'La chasse d’eau fuit dans les toilettes',
            'De l’eau sort du tuyau sous l’évier',
            'Le plafond est humide à cause d’une fuite',
        ],
        'panne_locale' => [
            'La lumière ne fonctionne plus dans l’escalier',
            'La porte électrique ne s’ouvre plus',
            'L’ascenseur est en panne depuis ce matin',
            'La chaudière est cassée et il n’y a plus de chauffage',
            'La serrure de la porte est bloquée',
            'La prise électrique ne marche plus dans le hall',
            'Le portail automatique est en panne',
            'Le chauffage du bâtiment ne fonctionne plus',
        ],
        'personne_vulnerable' => [
            'Une personne âgée est seule et a besoin d’aide',
            'Un voisin handicapé est isolé dans son appartement',
            'Une personne vulnérable ne répond plus',
            'Une personne âgée a besoin de médicaments',
            'Un voisin malade est seul et ne peut pas sortir',
            'Une personne à mobilité réduite est bloquée chez elle',
            'Un enfant est seul dans le logement',
            'Une personne fragile a besoin d’assistance',
        ],
        'autre' => [
            'Il y a des déchets dans la cour',
            'Le hall est très sale depuis plusieurs jours',
            'Il y a du bruit tard le soir dans le bâtiment',
            'Des objets encombrent le passage commun',
            'La peinture du mur est abîmée',
            'Un véhicule bloque l’entrée de la résidence',
            'Il y a une odeur désagréable dans le couloir',
            'Les espaces verts ont besoin d’entretien',
        ],
    ];

    private const MOTS_VIDES = [
        'a', 'au', 'aux', 'avec', 'ce', 'ces', 'dans', 'de', 'des', 'du', 'elle',
        'en', 'est', 'et', 'il', 'la', 'le', 'les', 'leur', 'mais', 'mon', 'ne',
        'nos', 'notre', 'ou', 'par', 'pas', 'pour', 'qui', 'sa', 'se', 'son',
        'sous', 'sur', 'un', 'une', 'vers', 'votre',
    ];

    /**
     * Classe localement le texte par Naive Bayes multinomial.
     *
     * @return array{categorie: string, confiance: int}
     */
    public function classer(string $description): array
    {
        $exemples = [];
        $vocabulaire = [];
        foreach (self::EXEMPLES as $categorie => $phrases) {
            foreach ($phrases as $phrase) {
                $mots = $this->tokens($phrase);
                $exemples[$categorie][] = $mots;
                foreach ($mots as $mot) {
                    $vocabulaire[$mot] = true;
                }
            }
        }

        $motsDescription = $this->tokens($description);
        $logScores = [];
        $totalCategories = count(self::EXEMPLES);
        $tailleVocabulaire = count($vocabulaire);

        foreach ($exemples as $categorie => $phrases) {
            $comptes = [];
            $totalMots = 0;
            foreach ($phrases as $mots) {
                foreach ($mots as $mot) {
                    $comptes[$mot] = ($comptes[$mot] ?? 0) + 1;
                    $totalMots++;
                }
            }

            $score = log(count($phrases) / array_sum(array_map('count', $exemples)));
            foreach ($motsDescription as $mot) {
                if (isset($vocabulaire[$mot])) {
                    $score += log((($comptes[$mot] ?? 0) + 1) / ($totalMots + $tailleVocabulaire));
                }
            }
            $logScores[$categorie] = $score;
        }

        arsort($logScores);
        $categorie = (string) array_key_first($logScores);
        $maximum = max($logScores);
        $probabilitesRelatives = array_map(
            fn (float $score): float => exp($score - $maximum),
            $logScores,
        );
        $confiance = (int) round(100 / array_sum($probabilitesRelatives));

        return [
            'categorie' => $categorie,
            'confiance' => $confiance,
        ];
    }

    /**
     * @return list<string>
     */
    private function tokens(string $texte): array
    {
        $normalise = Str::ascii(mb_strtolower($texte));
        preg_match_all('/[a-z0-9]+/', $normalise, $matches);

        return array_values(array_filter(
            $matches[0],
            fn (string $mot): bool => strlen($mot) > 1 && ! in_array($mot, self::MOTS_VIDES, true),
        ));
    }
}
