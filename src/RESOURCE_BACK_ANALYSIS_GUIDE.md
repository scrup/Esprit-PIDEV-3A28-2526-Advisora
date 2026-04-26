# Guide Detaille - Gestion Resource Back (Analyse + PDF)

Ce guide explique la logique implementee pour que tu puisses la presenter facilement en validation.

## 1) Scope et securite
- Scope strict: uniquement `/back/resources/...`.
- Autorisation: deja geree par la regle `canManageResources()` (`admin` et `gerant`).
- Aucun impact volontaire sur les autres modules metier (events, projects, investments, shop flow).

## 2) Pipeline metier d'analyse
Fichier: `src/Service/ResourceActionAnalysisService.php`

Etapes:
1. Charger les donnees ressources:
   - stock total
   - stock reserve (depuis `project_resources`)
   - statut
   - prix
   - fournisseur
2. Calculer les signaux:
   - `stock_critique`
   - `surstock`
   - `prix_anormal`
   - `fournisseur_risque`
3. Transformer chaque signal en action recommandee:
   - `priority` (haute/moyenne/basse)
   - `action_code`
   - `impact_metier`
   - `justification`
   - `confidence_pct`
   - `delay`
4. Produire `summary + kpis`.

Le moteur est deterministic (pas d'IA pour le calcul), donc stable, explicable et peu couteux.

## 3) Execution arriere-plan
Fichiers:
- `src/Command/RunResourceAnalysisCommand.php`
- `src/Service/ResourceAnalysisResultStore.php`

Principe:
- bouton "Analyser" => lancement de `app:resource:analysis:run` en process fond,
- status `running.lock` dans `var/resource-analysis/`,
- resultat JSON dans `var/resource-analysis/latest.json`,
- erreurs dans `var/resource-analysis/error.json`.

## 4) UI back office
Fichiers:
- `templates/back/resource/analysis.html.twig`
- `templates/back/resource/_module_nav.html.twig`

Fonctions:
- filtre par priorite,
- filtre par code action,
- export CSV,
- export PDF metier,
- chat low-token.

## 5) Export PDF metier avance
Fichiers:
- `src/Service/ResourceAnalysisPdfReportService.php`
- `templates/back/resource/analysis_pdf.html.twig`

Strategie:
- render HTML professionnel,
- tentative conversion PDF via `PdfGeneratorService` (Gotenberg),
- fallback automatique en HTML imprimable si PDF indisponible.

## 6) Chat low-token
Fichier: `src/Service/ResourceAnalysisChatService.php`

Role:
- ne recalcule pas le metier,
- explique les KPI/actions calcules par le moteur,
- prompt compresse (KPI + top actions) pour limiter la consommation.

## 7) Bundles demandes (regle equipe)
- `StofDoctrineExtensionsBundle` configure dans `config/packages/stof_doctrine_extensions.yaml`.
- `DoctrineFixturesBundle` + `Faker` installes.
- Fixture demo module resource:
  - `src/DataFixtures/ResourceBackAnalysisFixture.php`
  - groupe: `resource_back`
  - commande: `php bin/console doctrine:fixtures:load --group=resource_back`

## 8) Points de soutenance (pitch rapide)
1. "Le moteur metier est deterministic, l'IA est seulement une couche d'explication."
2. "Le traitement tourne en arriere-plan pour ne pas bloquer l'interface."
3. "Le rapport est exportable CSV et PDF metier avec fallback robuste."
4. "Le scope est strictement limite au module resource back (admin/gerant)."

