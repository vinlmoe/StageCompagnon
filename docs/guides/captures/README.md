# Refaire les captures d'écran des guides

Les images de `docs/guides/images/` sont produites sur un site de démonstration reconstitué
par script, puis photographiées par un navigateur piloté. Tout est rejouable : quand
l'interface change, on relance les deux outils et on recompile les guides.

## 1. Site de démonstration

Sur un Moodle vide (4.5 recommandé) où `mod/stage` et `mod/stagesynthesis` sont déployés,
avec le paquet de langue français installé :

```bash
php docs/guides/captures/demo_data.php /chemin/vers/moodle/config.php
```

Le script crée le site « ENV », les promotions 2030, 2031 et 2032, le cours de suivi des
référents, les comptes (DEVE Claire Martin, référents Paul Durand et Julie Bernard, étudiants
Camille Leroy, Léa Moreau, Thomas Petit…), les thématiques, listes d'évaluation, gabarits,
stages et demandes de convention que montrent les guides. Mot de passe de tous les comptes :
`Demo-2026!`. Il n'est pas prévu pour être rejoué sur un site déjà peuplé.

Sans accès à `download.moodle.org`, `lang-fr-minimal/` fournit les quelques chaînes de
l'habillage Moodle (navigation, boutons de formulaire, sélecteur de fichiers) qui apparaissent
dans les captures : copier le dossier sous `moodledata/lang/fr`, puis `php admin/cli/cfg.php
--name=lang --set=fr` et `php admin/cli/purge_caches.php`. Le paquet officiel reste préférable.

## 2. Captures

Le serveur web intégré de PHP suffit (`php -S localhost:8001` depuis la racine Moodle, avec
`$CFG->wwwroot` sur cette adresse). Playwright et Chromium doivent être installés (`npm i
playwright && npx playwright install chromium`).

```bash
cd docs/guides/captures
MOODLE_URL=http://localhost:8001 node shot.js plan.json ../images
```

`plan.json` décrit chaque capture : compte, URL, actions préalables (déplier les sections,
choisir une valeur, cliquer), et découpe (page entière jusqu'au bas du contenu, section de
formulaire par son identifiant, ou plage entre deux titres). Les identifiants de modules,
saisies et thématiques y sont ceux que produit `demo_data.php` sur un site vide ; sur un autre
site, les adapter d'après le résumé affiché à la fin du script.

L'ordre des captures compte : le transfert d'Emma Fournier vers la promotion 2031 (captures 31
à 33) modifie le site, et les captures de la synthèse (70 à 72) le supposent fait.

Les captures des pages Moodle standard (installation des plugins, paramètres d'activité,
participants, sauvegarde, services web) et des exports PDF et Excel ne sont pas dans le plan :
elles sont refaites à la main quand ces écrans changent.

## 3. Guides

```bash
cd docs/guides
for g in guide-parametrage guide-etudiant guide-enseignant-referent; do
  pdflatex -interaction=nonstopmode $g.tex && pdflatex -interaction=nonstopmode $g.tex
done
```

Mettre à jour le numéro de version et l'historique en tête de chaque `.tex`.
