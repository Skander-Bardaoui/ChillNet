<!DOCTYPE html>
<html lang="fr">
<head><meta charset="UTF-8"><title>Signalement #{{ $signalement->id }}</title><style>body{font-family:DejaVu Sans,sans-serif;color:#172033;font-size:14px}h1{color:#1269a5;border-bottom:2px solid #1269a5;padding-bottom:10px}.label{font-weight:bold;color:#526071;margin-top:16px}.value{margin-top:4px;padding:10px;background:#f1f5f9;border-radius:4px}.status{display:inline-block;padding:6px 10px;background:#dbeafe;border-radius:4px}</style></head>
<body>
<h1>Signalement communautaire #{{ $signalement->id }}</h1>
<p><strong>Date :</strong> {{ $signalement->date_signalement->format('d/m/Y') }}</p>
<p><strong>Habitant :</strong> {{ $signalement->habitant->name }}</p>
<p><strong>Résidence :</strong> {{ $signalement->residence->nom }}</p>
<div class="label">Catégorie</div><div class="value">{{ $signalement->categorie === 'autre' ? $signalement->categorie_autre : str_replace('_', ' ', ucfirst($signalement->categorie)) }}</div>
<div class="label">Urgence</div><div class="value">{{ ucfirst($signalement->urgence) }}</div>
<div class="label">Statut</div><div class="value"><span class="status">{{ str_replace('_', ' ', ucfirst($signalement->statut)) }}</span></div>
<div class="label">Description</div><div class="value">{{ $signalement->description }}</div>
</body>
</html>