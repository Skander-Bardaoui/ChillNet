{{--
    Carte Leaflet des points de fraîcheur (module 3), partagée back / front.
    Variables : $idCarte, $marqueurs (lat, lng, nom, type, couleur, lien,
    [statut], [score], [distance]), $origine [lat, lng]|null, $rayonKm|null.
--}}
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<script>
(function () {
    const MARQUEURS = @json($marqueurs, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT);
    const ORIGINE = @json($origine ?? null);
    const RAYON_KM = @json($rayonKm ?? null);

    const carte = L.map(@json($idCarte));
    L.tileLayer('https://tile.openstreetmap.org/{z}/{x}/{y}.png', { maxZoom: 19, attribution: '&copy; OpenStreetMap' }).addTo(carte);

    // Les popups contiennent du HTML : on échappe les données saisies.
    const echapper = (t) => { const d = document.createElement('div'); d.textContent = t ?? ''; return d.innerHTML; };

    const bornes = [];
    MARQUEURS.forEach((m) => {
        const nonValide = m.statut && m.statut !== 'valide';
        L.circleMarker([m.lat, m.lng], {
            radius: 9, color: m.couleur, fillColor: m.couleur, fillOpacity: nonValide ? 0.25 : 0.75,
            weight: 2, dashArray: nonValide ? '4 3' : null,
        }).addTo(carte).bindPopup(
            '<strong>' + echapper(m.nom) + '</strong><br>' + echapper(m.type)
            + (m.score !== undefined ? '<br>Score IA : ' + m.score + '/100' : '')
            + (m.distance !== undefined ? ' · ' + m.distance + ' m' : '')
            + '<br><a href="' + m.lien + '">Voir le détail →</a>'
        );
        bornes.push([m.lat, m.lng]);
    });

    if (ORIGINE) {
        L.marker(ORIGINE).addTo(carte).bindPopup('Votre position');
        bornes.push(ORIGINE);
        if (RAYON_KM) {
            L.circle(ORIGINE, { radius: RAYON_KM * 1000, color: '#1b77ba', weight: 1, fillOpacity: 0.04 }).addTo(carte);
        }
    }

    if (bornes.length > 1) {
        carte.fitBounds(bornes, { padding: [30, 30], maxZoom: 16 });
    } else {
        carte.setView(bornes[0] ?? [36.8065, 10.1815], 14);
    }
})();
</script>
