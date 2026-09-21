<?php

namespace App\Http\Controllers\Back;

use App\Entities\Quartier;
use App\Entities\Residence;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreResidenceRequest;
use App\Repositories\ResidenceRepository;
use Doctrine\ORM\EntityManagerInterface;

class ResidenceController extends Controller
{
    public function __construct(private readonly EntityManagerInterface $em)
    {
    }

    public function index()
    {
        /** @var ResidenceRepository $repo */
        $repo = $this->em->getRepository(Residence::class);
        $residences = $repo->allWithQuartier();

        return view('back.residences.index', compact('residences'));
    }

    public function create()
    {
        $quartiers = $this->em->getRepository(Quartier::class)->findBy([], ['nom' => 'ASC']);
        $residence = null;

        return view('back.residences.create', compact('quartiers', 'residence'));
    }

    public function store(StoreResidenceRequest $request)
    {
        $quartier = $this->em->getRepository(Quartier::class)->find($request->input('quartier_id'));
        abort_if(! $quartier, 422, 'Quartier invalide.');

        $residence = new Residence();
        $residence->setNom($request->input('nom'))
            ->setAdresse($request->input('adresse'))
            ->setNombreLogements((int) $request->input('nombre_logements', 0))
            ->setSalleClimatisee($request->boolean('salle_climatisee'))
            ->setPointFraicheur($request->boolean('point_fraicheur'))
            ->setQuartier($quartier);

        $this->em->persist($residence);
        $this->em->flush();

        return redirect()->route('back.residences.index')
            ->with('success', 'Résidence créée avec succès.');
    }

    public function edit(int $id)
    {
        $residence = $this->em->getRepository(Residence::class)->find($id);
        abort_if(! $residence, 404);

        $quartiers = $this->em->getRepository(Quartier::class)->findBy([], ['nom' => 'ASC']);

        return view('back.residences.edit', compact('residence', 'quartiers'));
    }

    public function update(StoreResidenceRequest $request, int $id)
    {
        $residence = $this->em->getRepository(Residence::class)->find($id);
        abort_if(! $residence, 404);

        $quartier = $this->em->getRepository(Quartier::class)->find($request->input('quartier_id'));
        abort_if(! $quartier, 422, 'Quartier invalide.');

        $residence->setNom($request->input('nom'))
            ->setAdresse($request->input('adresse'))
            ->setNombreLogements((int) $request->input('nombre_logements', 0))
            ->setSalleClimatisee($request->boolean('salle_climatisee'))
            ->setPointFraicheur($request->boolean('point_fraicheur'))
            ->setQuartier($quartier)
            ->touch();

        $this->em->flush();

        return redirect()->route('back.residences.index')
            ->with('success', 'Résidence mise à jour avec succès.');
    }

    public function destroy(int $id)
    {
        $residence = $this->em->getRepository(Residence::class)->find($id);
        abort_if(! $residence, 404);

        $this->em->remove($residence);
        $this->em->flush();

        return redirect()->route('back.residences.index')
            ->with('success', 'Résidence supprimée.');
    }
}
