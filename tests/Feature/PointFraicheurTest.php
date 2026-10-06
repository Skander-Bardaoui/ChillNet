<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Enums\Sentiment;
use App\Enums\StatutPointFraicheur;
use App\Models\Avis;
use App\Models\PointFraicheur;
use App\Models\Quartier;
use App\Models\Residence;
use App\Models\User;
use App\Services\RecommandationFraicheurService;
use App\Services\SentimentAvisService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Module 3 : points de fraîcheur + avis (CRUD, modération, validation, IA).
 * Les clés Groq / WeatherAPI sont neutralisées : l'IA passe par ses replis.
 */
class PointFraicheurTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config(['services.groq.key' => null, 'services.weather.key' => null]);
        Quartier::create(['nom' => 'Centre-Ville', 'ville' => 'Tunis', 'latitude' => 36.8008, 'longitude' => 10.1800]);
    }

    private function user(Role $role, ?int $residenceId = null): User
    {
        return User::factory()->create(['role' => $role, 'residence_id' => $residenceId]);
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(array $overrides = []): array
    {
        return array_merge([
            'nom' => 'Parc du Belvédère',
            'type' => 'parc',
            'adresse' => 'Avenue Taïeb Mhiri, Tunis',
            'latitude' => '36.8190',
            'longitude' => '10.1760',
            'capacite' => 300,
            'heure_ouverture' => '07:00',
            'heure_fermeture' => '20:00',
            'ombrage' => '1',
            'accessible_pmr' => '1',
        ], $overrides);
    }

    public function test_public_listing_shows_only_validated_points(): void
    {
        PointFraicheur::factory()->valide()->create(['nom' => 'Point visible']);
        PointFraicheur::factory()->enAttente()->create(['nom' => 'Point en attente']);

        $this->get(route('refuges.index'))
            ->assertOk()
            ->assertSee('Point visible')
            ->assertDontSee('Point en attente');
    }

    public function test_pending_point_detail_is_hidden_from_the_public(): void
    {
        $point = PointFraicheur::factory()->enAttente()->create();

        $this->get(route('points.show', $point))->assertNotFound();
    }

    public function test_habitant_proposal_is_stored_as_pending_with_photo(): void
    {
        Storage::fake('public');
        $habitant = $this->user(Role::Habitant);

        $this->actingAs($habitant)
            ->post(route('points.store'), $this->payload([
                'statut' => 'valide', // ignoré : un habitant ne choisit pas le statut
                'photo' => UploadedFile::fake()->image('parc.jpg', 800, 600),
            ]))
            ->assertRedirect(route('points.mine'));

        $point = PointFraicheur::firstOrFail();
        $this->assertSame(StatutPointFraicheur::EnAttente, $point->statut);
        $this->assertSame($habitant->id, $point->user_id);
        $this->assertNotNull($point->quartier_id);
        Storage::disk('public')->assertExists($point->photo);
    }

    public function test_advanced_validation_rules(): void
    {
        $habitant = $this->user(Role::Habitant);

        // Capacité obligatoire pour un parc, horaires obligatoires hors 24h/24.
        $this->actingAs($habitant)
            ->post(route('points.store'), $this->payload(['capacite' => '', 'heure_ouverture' => '']))
            ->assertSessionHasErrors(['capacite', 'heure_ouverture']);

        // Fontaine ouverte 24h/24 : ni capacité ni horaires requis.
        $this->actingAs($habitant)
            ->post(route('points.store'), $this->payload(['type' => 'fontaine', 'capacite' => '', 'ouvert_24h' => '1', 'heure_ouverture' => '', 'heure_fermeture' => '']))
            ->assertSessionHasNoErrors();

        // Coordonnées hors de Tunisie.
        $this->actingAs($habitant)
            ->post(route('points.store'), $this->payload(['nom' => 'Paris', 'latitude' => '48.8566', 'longitude' => '2.3522']))
            ->assertSessionHasErrors('latitude');

        // Doublon : même type à moins de 50 m d'un point existant.
        $this->actingAs($habitant)
            ->post(route('points.store'), $this->payload(['type' => 'fontaine', 'capacite' => '', 'ouvert_24h' => '1', 'latitude' => '36.8192', 'longitude' => '10.1761']))
            ->assertSessionHasErrors('latitude');

        // Photo trop petite / mauvais format.
        $this->actingAs($habitant)
            ->post(route('points.store'), $this->payload(['nom' => 'Autre', 'latitude' => '36.79', 'photo' => UploadedFile::fake()->image('mini.png', 100, 100)]))
            ->assertSessionHasErrors('photo');
    }

    public function test_admin_crud_and_moderation(): void
    {
        $admin = $this->user(Role::Admin);
        $habitant = $this->user(Role::Habitant);

        // Création back office : validé d'office.
        $this->actingAs($admin)->post(route('back.points.store'), $this->payload(['statut' => 'valide']))->assertRedirect();
        $point = PointFraicheur::firstOrFail();
        $this->assertTrue($point->estValide());

        // Affichage liste + détail, modification.
        $this->actingAs($admin)->get(route('back.points.index'))->assertOk()->assertSee($point->nom);
        $this->actingAs($admin)->get(route('back.points.edit', $point))->assertOk()->assertSee('value="Parc du Belvédère"', false);
        $this->actingAs($admin)->put(route('back.points.update', $point), $this->payload(['nom' => 'Parc renommé', 'statut' => 'valide']))->assertRedirect(route('back.points.show', $point));
        $this->assertSame('Parc renommé', $point->fresh()->nom);

        // Refus sans motif interdit, puis refus motivé, puis validation.
        $proposition = PointFraicheur::factory()->enAttente()->proposePar($habitant)->create();
        $this->actingAs($admin)->patch(route('back.points.refuser', $proposition), ['motif_refus' => ''])->assertSessionHasErrors('motif_refus');
        $this->actingAs($admin)->patch(route('back.points.refuser', $proposition), ['motif_refus' => 'Lieu privé non accessible au public.']);
        $this->assertSame(StatutPointFraicheur::Refuse, $proposition->fresh()->statut);
        $this->actingAs($admin)->patch(route('back.points.valider', $proposition));
        $this->assertSame(StatutPointFraicheur::Valide, $proposition->fresh()->statut);
        $this->assertSame($admin->id, $proposition->fresh()->valide_par);

        // Suppression : les avis suivent.
        Avis::factory()->for($point, 'pointFraicheur')->create();
        $this->actingAs($admin)->delete(route('back.points.destroy', $point))->assertRedirect(route('back.points.index'));
        $this->assertModelMissing($point);
        $this->assertSame(0, Avis::where('point_fraicheur_id', $point->id)->count());
    }

    public function test_only_admin_can_moderate_and_gestionnaire_is_limited_to_his_zone(): void
    {
        $autreZone = Quartier::create(['nom' => 'Les Berges du Lac', 'ville' => 'Tunis', 'latitude' => 36.8325, 'longitude' => 10.2800]);
        $residence = Residence::create(['nom' => 'Lac View', 'adresse' => 'Rue du Lac', 'quartier_id' => $autreZone->id]);
        $gestionnaire = $this->user(Role::Gestionnaire, $residence->id);
        $pointCentre = PointFraicheur::factory()->enAttente()->a(36.8010, 10.1802)->create();

        $this->actingAs($gestionnaire)->patch(route('back.points.valider', $pointCentre))->assertForbidden();
        $this->actingAs($gestionnaire)->get(route('back.points.edit', $pointCentre))->assertForbidden();
        $this->actingAs($this->user(Role::Habitant))->get(route('back.points.index'))->assertForbidden();
    }

    public function test_habitant_reviews_rules(): void
    {
        $habitant = $this->user(Role::Habitant);
        $point = PointFraicheur::factory()->valide()->create();

        // Note ≤ 2 sans commentaire : refusé.
        $this->actingAs($habitant)->post(route('points.avis.store', $point), ['note' => 2])->assertSessionHasErrors('commentaire');
        // Note hors bornes.
        $this->actingAs($habitant)->post(route('points.avis.store', $point), ['note' => 6])->assertSessionHasErrors('note');

        // Avis valide, sentiment calculé (repli lexical).
        $this->actingAs($habitant)->post(route('points.avis.store', $point), [
            'note' => 1, 'commentaire' => 'Très sale, fontaine cassée, vraiment décevant.', 'affluence' => 'forte',
        ])->assertSessionHasNoErrors();
        $avis = Avis::firstOrFail();
        $this->assertSame(Sentiment::Negatif, $avis->sentiment);

        // Un seul avis par habitant et par point.
        $this->actingAs($habitant)->post(route('points.avis.store', $point), ['note' => 5])->assertSessionHasErrors('note');

        // Modification (pré-remplie) puis suppression de SON avis uniquement.
        $this->actingAs($habitant)->get(route('avis.edit', $avis))->assertOk()->assertSee('Très sale');
        $this->actingAs($habitant)->put(route('avis.update', $avis), ['note' => 5, 'commentaire' => 'Nettoyé depuis, super agréable et frais !']);
        $this->assertSame(Sentiment::Positif, $avis->fresh()->sentiment);
        $this->actingAs($this->user(Role::Habitant))->delete(route('avis.destroy', $avis))->assertForbidden();
        $this->actingAs($habitant)->delete(route('avis.destroy', $avis))->assertRedirect();
        $this->assertModelMissing($avis);
    }

    public function test_every_module_page_renders(): void
    {
        $admin = $this->user(Role::Admin);
        $habitant = $this->user(Role::Habitant);
        $point = PointFraicheur::factory()->valide()->create();
        $proposition = PointFraicheur::factory()->refuse()->proposePar($habitant)->create();
        $avis = Avis::factory()->negatif()->par($habitant)->for($point, 'pointFraicheur')->create();

        foreach ([
            route('back.points.index'), route('back.points.create'), route('back.points.show', $point),
            route('back.points.edit', $point), route('back.avis.index'), route('back.avis.index', ['sentiment' => 'negatif']),
        ] as $url) {
            $this->actingAs($admin)->get($url)->assertOk();
        }

        foreach ([
            route('refuges.index', ['pmr' => 1, 'climatise' => 1, 'lat' => 36.8, 'lng' => 10.18, 'rayon' => 10]),
            route('points.show', $point), route('points.show', $proposition), route('points.create'),
            route('points.mine'), route('points.edit', $proposition), route('avis.edit', $avis),
        ] as $url) {
            $this->actingAs($habitant)->get($url)->assertOk();
        }

        // Un point validé n'est plus modifiable par l'habitant.
        $publie = PointFraicheur::factory()->valide()->proposePar($habitant)->create();
        $this->actingAs($habitant)->get(route('points.edit', $publie))->assertForbidden();
    }

    public function test_ai_detects_recurring_badly_rated_points_and_ranks_recommendations(): void
    {
        $mauvais = PointFraicheur::factory()->valide()->salleClimatisee()->a(36.8010, 10.1802)->create(['heure_ouverture' => '00:00', 'heure_fermeture' => '23:59']);
        $bon = PointFraicheur::factory()->valide()->parc()->a(36.8015, 10.1805)->create(['accessible_pmr' => false, 'ouvert_24h' => true]);
        Avis::factory()->count(3)->negatif()->for($mauvais, 'pointFraicheur')->create();
        Avis::factory()->count(3)->positif()->for($bon, 'pointFraicheur')->create();

        $malNotes = app(SentimentAvisService::class)->pointsMalNotes();
        $this->assertCount(1, $malNotes);
        $this->assertSame($mauvais->id, $malNotes->first()['point']->id);

        $reco = app(RecommandationFraicheurService::class);
        $classement = $reco->recommander(36.8008, 10.1800, ['climatise' => true], null, 5, 5, now()->setTime(14, 0), 0);
        $this->assertSame($mauvais->id, $classement->first()['point']->id, 'Le seul lieu climatisé arrive en tête quand on demande la clim.');

        // PMR = contrainte stricte : le parc non accessible disparaît.
        $pmr = $reco->recommander(36.8008, 10.1800, ['pmr' => true], null, 5, 5, null, 0);
        $this->assertFalse($pmr->contains(fn ($r) => $r['point']->id === $bon->id));

        $this->assertNotEmpty($reco->conseil($classement, ['climatise' => true], null));
    }
}
