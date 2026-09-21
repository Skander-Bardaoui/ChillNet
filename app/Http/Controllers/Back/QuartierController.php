<?php

namespace App\Http\Controllers\Back;

use App\Entities\Quartier;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreQuartierRequest;
use Doctrine\ORM\EntityManagerInterface;

class QuartierController extends Controller
{
    public function __construct(private readonly EntityManagerInterface $em)
    {
    }

    public function index()
    {
        $quartiers = $this->em->getRepository(Quartier::class)
            ->findBy([], ['nom' => 'ASC']);

        return view('back.quartiers.index', compact('quartiers'));
    }

    public function create()
    {
        $quartier = null;

        return view('back.quartiers.create', compact('quartier'));
    }

    public function store(StoreQuartierRequest $request)
    {
        $quartier = new Quartier();
        $quartier->setNom($request->input('nom'))
            ->setVille($request->input('ville'))
            ->setCodePostal($request->input('code_postal'))
            ->setDescription($request->input('description'));

        $this->em->persist($quartier);
        $this->em->flush();

        return redirect()->route('back.quartiers.index')
            ->with('success', 'Quartier créé avec succès.');
    }

    public function edit(int $id)
    {
        $quartier = $this->em->getRepository(Quartier::class)->find($id);
        abort_if(! $quartier, 404);

        return view('back.quartiers.edit', compact('quartier'));
    }

    public function update(StoreQuartierRequest $request, int $id)
    {
        $quartier = $this->em->getRepository(Quartier::class)->find($id);
        abort_if(! $quartier, 404);

        $quartier->setNom($request->input('nom'))
            ->setVille($request->input('ville'))
            ->setCodePostal($request->input('code_postal'))
            ->setDescription($request->input('description'))
            ->touch();

        $this->em->flush();

        return redirect()->route('back.quartiers.index')
            ->with('success', 'Quartier mis à jour avec succès.');
    }

    public function destroy(int $id)
    {
        $quartier = $this->em->getRepository(Quartier::class)->find($id);
        abort_if(! $quartier, 404);

        if (! $quartier->getResidences()->isEmpty()) {
            return back()->with('error', 'Impossible de supprimer un quartier qui contient des résidences.');
        }

        $this->em->remove($quartier);
        $this->em->flush();

        return redirect()->route('back.quartiers.index')
            ->with('success', 'Quartier supprimé.');
    }
}
